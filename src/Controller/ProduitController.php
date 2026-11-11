<?php

namespace App\Controller;

use App\Repository\ProduitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProduitController extends AbstractController
{
    #[Route('/produits', name: 'app_produits')]
    public function index(Request $request, ProduitRepository $repository): Response
    {
        $q = (string) $request->query->get('q', '');
        $type = (string) $request->query->get('type', '');
        $promo = $request->query->getBoolean('promo');

        return $this->render('produit/index.html.twig', [
            'produits' => $repository->search($q, $type, $promo),
            'types' => $repository->findTypes(),
            'q' => $q,
            'type' => $type,
            'promo' => $promo,
        ]);
    }
}
