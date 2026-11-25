<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\Utilisateur;
use App\Form\CommandeType;
use App\Repository\CommandeRepository;
use App\Service\ConfirmationCommande;
use App\Service\Paiement;
use App\Service\Panier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Parcours d'achat : panier → coordonnées → paiement → confirmation.
 */
class CommandeController extends AbstractController
{
    /** En session : références des commandes passées par ce visiteur (lui seul peut les voir). */
    private const CLE_SESSION = 'mes_commandes';

    public function __construct(
        private readonly Panier $panier,
        private readonly Paiement $paiement,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly ConfirmationCommande $confirmation,
    ) {
    }

    /** Étape 1 : coordonnées du client. */
    #[Route('/commande', name: 'app_commande', methods: ['GET', 'POST'])]
    public function coordonnees(Request $request): Response
    {
        $lignes = $this->panier->getLignes();
        if ($lignes === []) {
            $this->addFlash('warning', 'Votre panier est vide.');

            return $this->redirectToRoute('app_panier');
        }

        $commande = new Commande();
        $client = $this->getUser();
        if ($client instanceof Utilisateur) {
            // Client connecté : coordonnées pré-remplies depuis son compte
            $commande->setNom($client->getNom())->setEmail($client->getEmail())
                ->setAdresse($client->getAdresse())->setCodePostal($client->getCodePostal())->setVille($client->getVille());
        }
        $form = $this->createForm(CommandeType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($client instanceof Utilisateur) {
                $commande->setUtilisateur($client);
                // Première commande : on garde l'adresse dans le compte pour la prochaine fois
                if ($client->getAdresse() === null || $client->getAdresse() === '') {
                    $client->setNom($client->getNom() ?: $commande->getNom())->setAdresse($commande->getAdresse())
                        ->setCodePostal($commande->getCodePostal())->setVille($commande->getVille());
                }
            }
            foreach ($lignes as $ligne) {
                $commande->ajouterProduit($ligne['produit'], $ligne['quantite']);
            }
            $this->em->persist($commande);
            $this->em->flush();

            $session = $request->getSession();
            $session->set(self::CLE_SESSION, [...$session->get(self::CLE_SESSION, []), $commande->getReference()]);

            return $this->redirectToRoute('app_commande_paiement', ['reference' => $commande->getReference()]);
        }

        return $this->render('commande/coordonnees.html.twig', [
            'form' => $form,
            'lignes' => $lignes,
            'total' => array_sum(array_column($lignes, 'sousTotal')),
        ]);
    }

    /** Étape 2 : récapitulatif et bouton « Payer ». */
    #[Route('/commande/{reference}/paiement', name: 'app_commande_paiement', methods: ['GET'])]
    public function paiement(Request $request, string $reference, CommandeRepository $commandes): Response
    {
        $commande = $this->commandeDuVisiteur($request, $reference, $commandes);
        if ($commande->isPayee() || $commande->getStatut() === Commande::ANNULEE) {
            return $this->redirectToRoute('app_commande_confirmation', ['reference' => $reference]);
        }

        return $this->render('commande/paiement.html.twig', [
            'commande' => $commande,
            'stripe' => $this->paiement->isStripeActif(),
            'modeTest' => $this->paiement->isModeTest(),
        ]);
    }

    /** Envoie le client vers Stripe, ou valide directement en mode simulé. */
    #[Route('/commande/{reference}/payer', name: 'app_commande_payer', methods: ['POST'])]
    public function payer(Request $request, string $reference, CommandeRepository $commandes): Response
    {
        $commande = $this->commandeDuVisiteur($request, $reference, $commandes);
        if (!$this->isCsrfTokenValid('payer' . $reference, (string) $request->request->get('_token'))) {
            throw new AccessDeniedHttpException('Jeton de sécurité invalide, rechargez la page.');
        }
        if ($commande->isPayee()) {
            return $this->redirectToRoute('app_commande_confirmation', ['reference' => $reference]);
        }
        if ($commande->getStatut() === Commande::ANNULEE) {
            $this->addFlash('danger', 'Cette commande a expiré ou a été annulée. Vous pouvez la recommencer depuis votre panier.');

            return $this->redirectToRoute('app_panier');
        }

        if (!$this->paiement->isStripeActif()) {
            $this->confirmation->confirmerPaiement($commande, 'simulation');

            return $this->redirectToRoute('app_commande_confirmation', ['reference' => $reference]);
        }

        try {
            // Une page de paiement existe déjà pour cette commande (double clic, retour arrière…) :
            // on la réutilise tant qu'elle est ouverte, au lieu d'en créer une nouvelle.
            $precedente = $commande->getPaiementId();
            if ($precedente !== null) {
                $existante = $this->paiement->lireSession($precedente);
                if ($this->paiement->sessionPayeePour($existante, $commande)) {
                    $this->confirmation->confirmerPaiement($commande, 'stripe', $precedente);

                    return $this->redirectToRoute('app_commande_confirmation', ['reference' => $reference]);
                }
                if (($existante['status'] ?? null) === 'complete') {
                    // Paiement envoyé mais pas encore confirmé (virement, prélèvement…) : on attend Stripe
                    return $this->redirectToRoute('app_commande_confirmation', ['reference' => $reference]);
                }
                if (($existante['status'] ?? null) === 'open' && isset($existante['url'])) {
                    return $this->redirect((string) $existante['url'], Response::HTTP_SEE_OTHER);
                }
            }

            $session = $this->paiement->creerSessionStripe(
                $commande,
                $this->generateUrl('app_commande_confirmation', ['reference' => $reference], UrlGeneratorInterface::ABSOLUTE_URL),
                $this->generateUrl('app_commande_annulation', ['reference' => $reference], UrlGeneratorInterface::ABSOLUTE_URL),
                $precedente,
            );
        } catch (\Throwable $e) {
            $this->logger->error('Création du paiement Stripe impossible', ['exception' => $e, 'commande' => $reference]);
            $this->addFlash('danger', 'Le paiement est momentanément indisponible. Réessayez dans quelques instants.');

            return $this->redirectToRoute('app_commande_paiement', ['reference' => $reference]);
        }

        $commande->setPaiementId($session['id']);
        $this->em->flush();

        // Le client quitte notre site pour la page de paiement sécurisée de Stripe
        return $this->redirect($session['url'], Response::HTTP_SEE_OTHER);
    }

    /** Étape 3 : retour après paiement. On redemande à Stripe si c'est vraiment payé. */
    #[Route('/commande/{reference}/confirmation', name: 'app_commande_confirmation', methods: ['GET'])]
    public function confirmation(Request $request, string $reference, CommandeRepository $commandes): Response
    {
        $commande = $this->commandeDuVisiteur($request, $reference, $commandes);

        $sessionId = (string) $request->query->get('session_id', '');
        if (!$commande->isPayee() && $sessionId !== '' && $sessionId === $commande->getPaiementId() && $this->paiement->isStripeActif()) {
            try {
                if ($this->paiement->sessionEstPayee($sessionId, $commande)) {
                    $this->confirmation->confirmerPaiement($commande, 'stripe', $sessionId);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Vérification du paiement Stripe impossible', ['exception' => $e, 'commande' => $reference]);
            }
        }

        // Vide le panier une seule fois, au premier affichage de la confirmation
        // (si le visiteur revient plus tard sur cette page, son nouveau panier n'est pas touché).
        $dejaVide = 'panier_vide_' . $reference;
        if ($commande->isPayee() && !$request->getSession()->has($dejaVide)) {
            $this->panier->vider();
            $request->getSession()->set($dejaVide, true);
        }

        return $this->render('commande/confirmation.html.twig', ['commande' => $commande]);
    }

    /** Le client a cliqué sur « retour » sur la page Stripe : rien n'est payé. */
    #[Route('/commande/{reference}/annulation', name: 'app_commande_annulation', methods: ['GET'])]
    public function annulation(Request $request, string $reference, CommandeRepository $commandes): Response
    {
        $this->commandeDuVisiteur($request, $reference, $commandes);
        $this->addFlash('warning', 'Le paiement a été interrompu. Votre commande est en attente : vous pouvez réessayer.');

        return $this->redirectToRoute('app_commande_paiement', ['reference' => $reference]);
    }

    /**
     * Notifications envoyées directement par Stripe à notre serveur (webhook).
     * C'est la source qui fait foi : le client peut fermer son navigateur avant de revenir sur le site.
     *
     * - paiement réussi (checkout.session.completed payé, ou async_payment_succeeded) : commande payée + email
     * - page de paiement expirée, ou paiement différé refusé : commande annulée
     */
    #[Route('/paiement/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function webhook(Request $request, CommandeRepository $commandes): Response
    {
        $evenement = $this->paiement->lireWebhook($request->getContent(), (string) $request->headers->get('Stripe-Signature', ''));
        if ($evenement === null) {
            return new Response('Signature invalide', Response::HTTP_BAD_REQUEST);
        }

        $type = (string) ($evenement['type'] ?? '');
        $session = $evenement['data']['object'] ?? [];
        $commande = is_array($session)
            ? $commandes->findOneBy(['reference' => (string) ($session['client_reference_id'] ?? '')])
            : null;
        if ($commande === null) {
            return new Response('ok'); // événement qui ne concerne pas une de nos commandes
        }

        switch ($type) {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                // « completed » arrive aussi pour un paiement différé encore non payé : on vérifie payment_status
                if ($this->paiement->sessionPayeePour($session, $commande)) {
                    $this->confirmation->confirmerPaiement($commande, 'stripe', (string) ($session['id'] ?? ''));
                }
                break;

            case 'checkout.session.expired':
            case 'checkout.session.async_payment_failed':
                // Seulement si c'est la page de paiement en cours de la commande (pas une ancienne)
                if ($commande->getStatut() === Commande::EN_ATTENTE && $commande->getPaiementId() === ($session['id'] ?? null)) {
                    $commande->setStatut(Commande::ANNULEE);
                    $this->em->flush();
                    $this->logger->info('Commande annulée par Stripe', ['commande' => $commande->getReference(), 'evenement' => $type]);
                }
                break;
        }

        return new Response('ok');
    }

    private function commandeDuVisiteur(Request $request, string $reference, CommandeRepository $commandes): Commande
    {
        $commande = $commandes->findOneBy(['reference' => $reference]);
        $mesCommandes = $request->getSession()->get(self::CLE_SESSION, []);
        // Accès : commande passée dans cette visite (session) ou rattachée au compte connecté
        $autorise = $commande !== null && (
            in_array($reference, $mesCommandes, true)
            || ($commande->getUtilisateur() !== null && $commande->getUtilisateur() === $this->getUser())
        );
        if (!$autorise) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        return $commande;
    }
}
