<?php

namespace App\Controller;

use App\Repository\ProduitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ProduitRepository $produits): Response
    {
        return $this->render('home/index.html.twig', [
            'promotions' => array_slice($produits->search(null, null, true), 0, 4),
        ]);
    }

    /** Utilisée par Render pour savoir si le site est en ligne (sans toucher la base). */
    #[Route('/health', name: 'app_health')]
    public function health(): Response
    {
        return new Response('ok', 200, ['Content-Type' => 'text/plain']);
    }
}
