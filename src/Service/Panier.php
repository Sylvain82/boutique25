<?php

namespace App\Service;

use App\Entity\Produit;
use App\Repository\ProduitRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Panier du visiteur, gardé dans sa session (pas de compte client nécessaire).
 *
 * En session on ne stocke que [id du produit => quantité] : le nom et le prix
 * sont relus dans la base à chaque affichage, pour être toujours à jour.
 */
class Panier
{
    private const CLE_SESSION = 'panier';
    public const QUANTITE_MAX = 99;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ProduitRepository $produits,
    ) {
    }

    public function ajouter(Produit $produit, int $quantite = 1): void
    {
        $contenu = $this->lire();
        $id = $produit->getId();
        $contenu[$id] = ($contenu[$id] ?? 0) + max(1, $quantite);
        $this->ecrire($contenu);
    }

    /** Fixe la quantité d'un produit ; 0 (ou moins) le retire du panier. */
    public function modifier(Produit $produit, int $quantite): void
    {
        $contenu = $this->lire();
        $contenu[$produit->getId()] = $quantite;
        $this->ecrire($contenu);
    }

    public function retirer(Produit $produit): void
    {
        $contenu = $this->lire();
        unset($contenu[$produit->getId()]);
        $this->ecrire($contenu);
    }

    public function vider(): void
    {
        $this->requestStack->getSession()->remove(self::CLE_SESSION);
    }

    /**
     * Lignes du panier avec les produits lus en base.
     * Un produit supprimé de la base entre-temps disparaît du panier.
     *
     * @return list<array{produit: Produit, quantite: int, sousTotal: int}>
     */
    public function getLignes(): array
    {
        $contenu = $this->lire();
        if ($contenu === []) {
            return [];
        }

        $produits = $this->produits->findBy(['id' => array_keys($contenu)], ['nom' => 'ASC']);

        $lignes = [];
        $nettoye = [];
        foreach ($produits as $produit) {
            $quantite = $contenu[$produit->getId()];
            $nettoye[$produit->getId()] = $quantite;
            $lignes[] = [
                'produit' => $produit,
                'quantite' => $quantite,
                'sousTotal' => $produit->getPrix() * $quantite,
            ];
        }
        if (count($nettoye) !== count($contenu)) {
            $this->ecrire($nettoye);
        }

        return $lignes;
    }

    /** Total en euros. */
    public function getTotal(): int
    {
        return array_sum(array_column($this->getLignes(), 'sousTotal'));
    }

    /**
     * Nombre d'articles (pour le badge du menu). Ne touche pas la base,
     * et n'ouvre pas de session pour un visiteur qui n'en a pas encore.
     */
    public function getNombreArticles(): int
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !$request->hasPreviousSession()) {
            return 0;
        }

        return array_sum($this->lire());
    }

    /** @return array<int, int> */
    private function lire(): array
    {
        $contenu = $this->requestStack->getSession()->get(self::CLE_SESSION, []);

        return is_array($contenu) ? $contenu : [];
    }

    /** @param array<int, int> $contenu */
    private function ecrire(array $contenu): void
    {
        $propre = [];
        foreach ($contenu as $id => $quantite) {
            $quantite = min((int) $quantite, self::QUANTITE_MAX);
            if ($quantite > 0) {
                $propre[(int) $id] = $quantite;
            }
        }
        $this->requestStack->getSession()->set(self::CLE_SESSION, $propre);
    }
}
