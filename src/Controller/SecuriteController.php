<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecuriteController extends AbstractController
{
    /** Formulaire de connexion. La vérification du mot de passe est faite par Symfony (form_login). */
    #[Route('/connexion', name: 'app_connexion')]
    public function connexion(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_apres_connexion');
        }

        // Retour vers la commande après connexion (seule page de retour autorisée : pas d'adresse libre)
        $retourCommande = $request->query->get('retour') === 'commande';

        return $this->render('securite/connexion.html.twig', [
            'dernier_email' => $authenticationUtils->getLastUsername(),
            'erreur' => $authenticationUtils->getLastAuthenticationError(),
            'cible' => $retourCommande ? $this->generateUrl('app_commande') : null,
            'retour' => $retourCommande ? 'commande' : null,
        ]);
    }

    /** Jamais exécutée : Symfony intercepte cette adresse pour déconnecter. */
    #[Route('/deconnexion', name: 'app_deconnexion')]
    public function deconnexion(): never
    {
        throw new \LogicException('Interceptée par le pare-feu de sécurité.');
    }
}
