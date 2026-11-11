<?php

namespace App\Controller;

use App\Entity\Produit;
use App\Service\Panier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/panier')]
class PanierController extends AbstractController
{
    public function __construct(private readonly Panier $panier)
    {
    }

    #[Route('', name: 'app_panier', methods: ['GET'])]
    public function index(): Response
    {
        $lignes = $this->panier->getLignes();

        return $this->render('panier/index.html.twig', [
            'lignes' => $lignes,
            'total' => array_sum(array_column($lignes, 'sousTotal')),
            'quantiteMax' => Panier::QUANTITE_MAX,
        ]);
    }

    #[Route('/ajouter/{id}', name: 'app_panier_ajouter', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function ajouter(Produit $produit, Request $request): Response
    {
        $this->verifierJeton($request);
        $this->panier->ajouter($produit, $request->request->getInt('quantite', 1));
        $this->addFlash('success', sprintf('« %s » a été ajouté au panier.', $produit->getNom()));

        return $this->retourPagePrecedente($request);
    }

    #[Route('/modifier/{id}', name: 'app_panier_modifier', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function modifier(Produit $produit, Request $request): Response
    {
        $this->verifierJeton($request);
        $this->panier->modifier($produit, $request->request->getInt('quantite'));

        return $this->redirectToRoute('app_panier');
    }

    #[Route('/retirer/{id}', name: 'app_panier_retirer', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function retirer(Produit $produit, Request $request): Response
    {
        $this->verifierJeton($request);
        $this->panier->retirer($produit);
        $this->addFlash('info', sprintf('« %s » a été retiré du panier.', $produit->getNom()));

        return $this->redirectToRoute('app_panier');
    }

    #[Route('/vider', name: 'app_panier_vider', methods: ['POST'])]
    public function vider(Request $request): Response
    {
        $this->verifierJeton($request);
        $this->panier->vider();
        $this->addFlash('info', 'Le panier a été vidé.');

        return $this->redirectToRoute('app_panier');
    }

    /** Protection CSRF : le formulaire doit venir de notre site. */
    private function verifierJeton(Request $request): void
    {
        if (!$this->isCsrfTokenValid('panier', (string) $request->request->get('_token'))) {
            throw new AccessDeniedHttpException('Jeton de sécurité invalide, rechargez la page.');
        }
    }

    /** Revient sur la page d'où vient le clic (liste, accueil…), uniquement si elle est sur notre site. */
    private function retourPagePrecedente(Request $request): RedirectResponse
    {
        $precedente = (string) $request->headers->get('referer', '');
        if ($precedente !== '' && str_starts_with($precedente, $request->getSchemeAndHttpHost() . '/')) {
            return $this->redirect($precedente);
        }

        return $this->redirectToRoute('app_panier');
    }
}
