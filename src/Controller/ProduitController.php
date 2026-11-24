<?php

namespace App\Controller;

use App\Entity\Produit;
use App\Repository\ProduitImageRepository;
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

    /**
     * Photo d'un produit, lue dans la base. L'adresse contient la date de mise à jour (?v=…),
     * donc le navigateur peut la garder en cache longtemps : une nouvelle photo aura une nouvelle adresse.
     */
    #[Route('/produits/{id}/image', name: 'app_produit_image', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function image(Produit $produit, ProduitImageRepository $images): Response
    {
        $image = $produit->hasImage() ? $images->findPourProduit($produit) : null;
        if ($image === null) {
            throw $this->createNotFoundException('Pas de photo pour ce produit.');
        }

        $reponse = new Response($image->getContenu(), 200, ['Content-Type' => $image->getTypeMime()]);
        $reponse->setPublic();
        $reponse->setMaxAge(31536000);
        $reponse->headers->addCacheControlDirective('immutable');

        return $reponse;
    }
}
