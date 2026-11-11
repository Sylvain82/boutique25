<?php

namespace App\Controller\Admin;

use App\Repository\CommandeRepository;
use App\Repository\ProduitRepository;
use App\Service\Paiement;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class TableauDeBordController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(CommandeRepository $commandes, ProduitRepository $produits, Paiement $paiement): Response
    {
        return $this->render('admin/tableau_de_bord.html.twig', [
            'nbProduits' => $produits->count([]),
            'parStatut' => $commandes->compterParStatut(),
            'chiffreAffaires' => $commandes->chiffreAffaires(),
            'dernieres' => $commandes->findDernieres(null, 5),
            'stripe' => $paiement->isStripeActif(),
            'stripeTest' => $paiement->isModeTest(),
            'webhook' => $paiement->isWebhookConfigure(),
        ]);
    }
}
