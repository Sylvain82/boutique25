<?php

namespace App\Controller\Admin;

use App\Repository\CommandeRepository;
use App\Repository\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Liste des comptes clients (lecture seule). */
#[IsGranted('ROLE_ADMIN')]
class ClientAdminController extends AbstractController
{
    #[Route('/admin/clients', name: 'app_admin_clients', methods: ['GET'])]
    public function index(UtilisateurRepository $utilisateurs, CommandeRepository $commandes): Response
    {
        return $this->render('admin/client/index.html.twig', [
            'clients' => $utilisateurs->findClients(),
            'nbCommandes' => $commandes->compterParUtilisateur(),
        ]);
    }
}
