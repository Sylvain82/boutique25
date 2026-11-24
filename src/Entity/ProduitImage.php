<?php

namespace App\Entity;

use App\Repository\ProduitImageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Photo d'un produit, stockée dans la base (le disque de Render est effacé à chaque redémarrage).
 * Table séparée pour ne pas charger les images quand on affiche une simple liste de produits.
 * L'image est réduite (800 px max, format WebP) avant d'être enregistrée : quelques dizaines de Ko.
 */
#[ORM\Entity(repositoryClass: ProduitImageRepository::class)]
class ProduitImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'produit_image_id_seq', allocationSize: 1)]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?Produit $produit = null;

    /** @var string|resource|null contenu binaire (BYTEA sur PostgreSQL) */
    #[ORM\Column(type: Types::BLOB)]
    private mixed $contenu = null;

    #[ORM\Column(length: 30)]
    private string $typeMime = 'image/webp';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduit(): ?Produit
    {
        return $this->produit;
    }

    public function setProduit(Produit $produit): static
    {
        $this->produit = $produit;

        return $this;
    }

    public function getContenu(): string
    {
        // PostgreSQL renvoie un flux : on le lit en texte binaire
        return is_resource($this->contenu) ? (string) stream_get_contents($this->contenu, -1, 0) : (string) $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function getTypeMime(): string
    {
        return $this->typeMime;
    }

    public function setTypeMime(string $typeMime): static
    {
        $this->typeMime = $typeMime;

        return $this;
    }
}
