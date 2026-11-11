<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Form\CommandeType;
use App\Repository\CommandeRepository;
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
        $form = $this->createForm(CommandeType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
        if ($commande->isPayee()) {
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
            $this->addFlash('danger', 'Cette commande a été annulée.');

            return $this->redirectToRoute('app_panier');
        }

        if (!$this->paiement->isStripeActif()) {
            $commande->marquerPayee('simulation');
            $this->em->flush();

            return $this->redirectToRoute('app_commande_confirmation', ['reference' => $reference]);
        }

        try {
            $session = $this->paiement->creerSessionStripe(
                $commande,
                $this->generateUrl('app_commande_confirmation', ['reference' => $reference], UrlGeneratorInterface::ABSOLUTE_URL),
                $this->generateUrl('app_commande_annulation', ['reference' => $reference], UrlGeneratorInterface::ABSOLUTE_URL),
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
                    $commande->marquerPayee('stripe', $sessionId);
                    $this->em->flush();
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
     * Notification envoyée directement par Stripe à notre serveur quand un paiement réussit.
     * Utile si le client ferme son navigateur avant de revenir sur le site.
     */
    #[Route('/paiement/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function webhook(Request $request, CommandeRepository $commandes): Response
    {
        $evenement = $this->paiement->lireWebhook($request->getContent(), (string) $request->headers->get('Stripe-Signature', ''));
        if ($evenement === null) {
            return new Response('Signature invalide', Response::HTTP_BAD_REQUEST);
        }

        $type = $evenement['type'] ?? '';
        $session = $evenement['data']['object'] ?? [];
        if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)
            && ($session['payment_status'] ?? null) === 'paid') {
            $commande = $commandes->findOneBy(['reference' => (string) ($session['client_reference_id'] ?? '')]);
            if ($commande !== null && (int) ($session['amount_total'] ?? 0) === $commande->getTotal() * 100) {
                $commande->marquerPayee('stripe', (string) ($session['id'] ?? ''));
                $this->em->flush();
            }
        }

        return new Response('ok');
    }

    private function commandeDuVisiteur(Request $request, string $reference, CommandeRepository $commandes): Commande
    {
        $mesCommandes = $request->getSession()->get(self::CLE_SESSION, []);
        $commande = in_array($reference, $mesCommandes, true) ? $commandes->findOneBy(['reference' => $reference]) : null;
        if ($commande === null) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        return $commande;
    }
}
