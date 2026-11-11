<?php

namespace App\Controller\Admin;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Suivi des commandes : liste, détail, changement de statut. */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/commandes')]
class CommandeAdminController extends AbstractController
{
    #[Route('', name: 'app_admin_commandes', methods: ['GET'])]
    public function index(Request $request, CommandeRepository $commandes): Response
    {
        $statut = (string) $request->query->get('statut', '');
        if (!isset(Commande::LIBELLES[$statut])) {
            $statut = '';
        }

        return $this->render('admin/commande/index.html.twig', [
            'commandes' => $commandes->findDernieres($statut),
            'statut' => $statut,
            'statuts' => Commande::LIBELLES,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_commande', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(Commande $commande): Response
    {
        return $this->render('admin/commande/detail.html.twig', [
            'commande' => $commande,
            'statuts' => Commande::LIBELLES,
        ]);
    }

    #[Route('/{id}/statut', name: 'app_admin_commande_statut', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function changerStatut(Commande $commande, Request $request, EntityManagerInterface $em): Response
    {
        $statut = (string) $request->request->get('statut');
        if ($this->isCsrfTokenValid('statut' . $commande->getId(), (string) $request->request->get('_token'))
            && isset(Commande::LIBELLES[$statut])) {
            $commande->setStatut($statut);
            $em->flush();
            $this->addFlash('success', sprintf('Commande %s : %s.', $commande->getReference(), mb_strtolower(Commande::LIBELLES[$statut])));
        }

        return $this->redirectToRoute('app_admin_commande', ['id' => $commande->getId()]);
    }
}
