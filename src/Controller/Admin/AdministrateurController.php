<?php

namespace App\Controller\Admin;

use App\Entity\Utilisateur;
use App\Form\AdminType;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Gestion des comptes administrateurs. */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/administrateurs')]
class AdministrateurController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    #[Route('', name: 'app_admin_administrateurs', methods: ['GET'])]
    public function index(UtilisateurRepository $utilisateurs): Response
    {
        return $this->render('admin/utilisateur/index.html.twig', [
            'administrateurs' => $utilisateurs->findBy([], ['email' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'app_admin_administrateur_nouveau', methods: ['GET', 'POST'])]
    public function nouveau(Request $request, UtilisateurRepository $utilisateurs): Response
    {
        $form = $this->createForm(AdminType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = mb_strtolower(trim((string) $form->get('email')->getData()));
            if ($utilisateurs->findOneBy(['email' => $email]) !== null) {
                $this->addFlash('danger', 'Un administrateur existe déjà avec cet email.');
            } else {
                $admin = (new Utilisateur())->setEmail($email)->setRoles(['ROLE_ADMIN']);
                $admin->setPassword($this->hasher->hashPassword($admin, (string) $form->get('motDePasse')->getData()));
                $this->em->persist($admin);
                $this->em->flush();
                $this->addFlash('success', sprintf('Administrateur %s ajouté.', $email));

                return $this->redirectToRoute('app_admin_administrateurs');
            }
        }

        return $this->render('admin/utilisateur/formulaire.html.twig', [
            'form' => $form,
            'titre' => 'Nouvel administrateur',
        ]);
    }

    #[Route('/mot-de-passe', name: 'app_admin_mot_de_passe', methods: ['GET', 'POST'])]
    public function motDePasse(Request $request): Response
    {
        /** @var Utilisateur $moi */
        $moi = $this->getUser();
        $form = $this->createForm(AdminType::class, null, ['avec_email' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $moi->setPassword($this->hasher->hashPassword($moi, (string) $form->get('motDePasse')->getData()));
            $this->em->flush();
            $this->addFlash('success', 'Votre mot de passe a été changé.');

            return $this->redirectToRoute('app_admin_administrateurs');
        }

        return $this->render('admin/utilisateur/formulaire.html.twig', [
            'form' => $form,
            'titre' => 'Changer mon mot de passe',
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_admin_administrateur_supprimer', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function supprimer(Utilisateur $admin, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('supprimer-admin' . $admin->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_admin_administrateurs');
        }
        if ($admin === $this->getUser()) {
            // Évite de se retrouver sans aucun accès à l'administration
            $this->addFlash('danger', 'Vous ne pouvez pas supprimer votre propre compte.');

            return $this->redirectToRoute('app_admin_administrateurs');
        }

        $this->em->remove($admin);
        $this->em->flush();
        $this->addFlash('success', sprintf('Administrateur %s supprimé.', $admin->getEmail()));

        return $this->redirectToRoute('app_admin_administrateurs');
    }
}
