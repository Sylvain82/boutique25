<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\InscriptionType;
use App\Form\MotDePasseType;
use App\Form\ProfilType;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Comptes clients : inscription, historique des commandes, profil, mot de passe, suppression du compte.
 * Les pages /mon-compte sont réservées aux personnes connectées (access_control dans security.yaml).
 */
class CompteController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    #[Route('/inscription', name: 'app_inscription', methods: ['GET', 'POST'])]
    public function inscription(Request $request, Security $security): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_compte');
        }

        $utilisateur = new Utilisateur();
        $form = $this->createForm(InscriptionType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $utilisateur->setPassword($this->hasher->hashPassword($utilisateur, (string) $form->get('motDePasse')->getData()));
            $this->em->persist($utilisateur);
            $this->em->flush();

            // Connexion automatique après l'inscription
            $security->login($utilisateur, 'form_login', 'main');
            $this->addFlash('success', 'Bienvenue ! Votre compte est créé.');

            // Retour vers la commande si l'inscription a été faite pendant un achat
            return $request->query->get('retour') === 'commande'
                ? $this->redirectToRoute('app_commande')
                : $this->redirectToRoute('app_compte');
        }

        return $this->render('compte/inscription.html.twig', ['form' => $form]);
    }

    /** Page d'arrivée après connexion : chacun vers son espace. */
    #[Route('/apres-connexion', name: 'app_apres_connexion', methods: ['GET'])]
    public function apresConnexion(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin');
        }

        return $this->redirectToRoute($this->getUser() !== null ? 'app_compte' : 'app_connexion');
    }

    #[Route('/mon-compte', name: 'app_compte', methods: ['GET'])]
    public function index(CommandeRepository $commandes): Response
    {
        return $this->render('compte/index.html.twig', [
            'commandes' => $commandes->findPourUtilisateur($this->utilisateur()),
        ]);
    }

    #[Route('/mon-compte/commandes/{reference}', name: 'app_compte_commande', methods: ['GET'])]
    public function commande(string $reference, CommandeRepository $commandes): Response
    {
        $commande = $commandes->findOneBy(['reference' => $reference, 'utilisateur' => $this->utilisateur()]);
        if ($commande === null) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        return $this->render('compte/commande.html.twig', ['commande' => $commande]);
    }

    #[Route('/mon-compte/profil', name: 'app_compte_profil', methods: ['GET', 'POST'])]
    public function profil(Request $request): Response
    {
        $form = $this->createForm(ProfilType::class, $this->utilisateur());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Vos informations sont enregistrées.');

            return $this->redirectToRoute('app_compte');
        }
        if ($form->isSubmitted()) {
            // Formulaire invalide : on annule les modifications faites sur l'utilisateur connecté
            $this->em->refresh($this->utilisateur());
        }

        return $this->render('compte/formulaire.html.twig', ['form' => $form, 'titre' => 'Mes informations']);
    }

    #[Route('/mon-compte/mot-de-passe', name: 'app_compte_mot_de_passe', methods: ['GET', 'POST'])]
    public function motDePasse(Request $request): Response
    {
        $form = $this->createForm(MotDePasseType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $moi = $this->utilisateur();
            $moi->setPassword($this->hasher->hashPassword($moi, (string) $form->get('nouveau')->getData()));
            $this->em->flush();
            $this->addFlash('success', 'Votre mot de passe a été changé.');

            return $this->redirectToRoute('app_compte');
        }

        return $this->render('compte/formulaire.html.twig', ['form' => $form, 'titre' => 'Changer mon mot de passe']);
    }

    /** Suppression du compte : les commandes restent (pour la comptabilité) mais ne sont plus liées au compte. */
    #[Route('/mon-compte/supprimer', name: 'app_compte_supprimer', methods: ['POST'])]
    public function supprimer(Request $request, Security $security): Response
    {
        $moi = $this->utilisateur();
        if (!$this->isCsrfTokenValid('supprimer-compte', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_compte');
        }
        if ($moi->isAdmin()) {
            $this->addFlash('danger', 'Un compte administrateur se supprime depuis l\'administration, par un autre administrateur.');

            return $this->redirectToRoute('app_compte');
        }

        $security->logout(false);
        $this->em->remove($moi);
        $this->em->flush();
        $this->addFlash('info', 'Votre compte a été supprimé.');

        return $this->redirectToRoute('app_home');
    }

    private function utilisateur(): Utilisateur
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        return $utilisateur;
    }
}
