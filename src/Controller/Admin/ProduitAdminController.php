<?php

namespace App\Controller\Admin;

use App\Entity\Produit;
use App\Form\ProduitType;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Ajouter / modifier / supprimer des produits. */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/produits')]
class ProduitAdminController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('', name: 'app_admin_produits', methods: ['GET'])]
    public function index(Request $request, ProduitRepository $produits): Response
    {
        $q = (string) $request->query->get('q', '');

        return $this->render('admin/produit/index.html.twig', [
            'produits' => $produits->search($q),
            'q' => $q,
        ]);
    }

    #[Route('/nouveau', name: 'app_admin_produit_nouveau', methods: ['GET', 'POST'])]
    public function nouveau(Request $request, ProduitRepository $produits): Response
    {
        $produit = new Produit();
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($produit);
            $this->em->flush();
            $this->addFlash('success', sprintf('Produit « %s » ajouté.', $produit->getNom()));

            return $this->redirectToRoute('app_admin_produits');
        }

        return $this->render('admin/produit/formulaire.html.twig', [
            'form' => $form,
            'produit' => $produit,
            'types' => $produits->findTypes(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_admin_produit_modifier', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function modifier(Produit $produit, Request $request, ProduitRepository $produits): Response
    {
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', sprintf('Produit « %s » modifié.', $produit->getNom()));

            return $this->redirectToRoute('app_admin_produits');
        }

        return $this->render('admin/produit/formulaire.html.twig', [
            'form' => $form,
            'produit' => $produit,
            'types' => $produits->findTypes(),
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_admin_produit_supprimer', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function supprimer(Produit $produit, Request $request): Response
    {
        if ($this->isCsrfTokenValid('supprimer-produit' . $produit->getId(), (string) $request->request->get('_token'))) {
            $nom = $produit->getNom();
            // Les commandes déjà passées gardent le nom et le prix du produit (voir LigneCommande).
            $this->em->remove($produit);
            $this->em->flush();
            $this->addFlash('success', sprintf('Produit « %s » supprimé.', $nom));
        }

        return $this->redirectToRoute('app_admin_produits');
    }
}
