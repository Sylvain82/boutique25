<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Une ligne d'une commande : un produit, son prix au moment de l'achat, la quantité. */
#[ORM\Entity]
class LigneCommande
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'ligne_commande_id_seq', allocationSize: 1)]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Commande $commande = null;

    /** Peut devenir vide si le produit est supprimé plus tard : la ligne garde son nom et son prix. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Produit $produit = null;

    #[ORM\Column(length: 255)]
    private string $nomProduit = '';

    /** Prix unitaire en euros au moment de la commande. */
    #[ORM\Column]
    private int $prixUnitaire = 0;

    #[ORM\Column]
    private int $quantite = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    public function setCommande(Commande $commande): static
    {
        $this->commande = $commande;

        return $this;
    }

    public function getProduit(): ?Produit
    {
        return $this->produit;
    }

    public function setProduit(?Produit $produit): static
    {
        $this->produit = $produit;

        return $this;
    }

    public function getNomProduit(): string
    {
        return $this->nomProduit;
    }

    public function setNomProduit(string $nomProduit): static
    {
        $this->nomProduit = $nomProduit;

        return $this;
    }

    public function getPrixUnitaire(): int
    {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire(int $prixUnitaire): static
    {
        $this->prixUnitaire = $prixUnitaire;

        return $this;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getSousTotal(): int
    {
        return $this->prixUnitaire * $this->quantite;
    }
}
