<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Commande passée par un visiteur à partir de son panier.
 * Les prix sont copiés au moment de la commande : si un prix change ensuite, la commande ne bouge pas.
 */
#[ORM\Entity(repositoryClass: CommandeRepository::class)]
class Commande
{
    public const EN_ATTENTE = 'en_attente';
    public const PAYEE = 'payee';
    public const EXPEDIEE = 'expediee';
    public const ANNULEE = 'annulee';

    public const LIBELLES = [
        self::EN_ATTENTE => 'En attente de paiement',
        self::PAYEE => 'Payée',
        self::EXPEDIEE => 'Expédiée',
        self::ANNULEE => 'Annulée',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'commande_id_seq', allocationSize: 1)]
    #[ORM\Column]
    private ?int $id = null;

    /** Référence affichée au client, difficile à deviner (sert aussi dans les adresses des pages). */
    #[ORM\Column(length: 32, unique: true)]
    private string $reference;

    #[ORM\Column(length: 20)]
    private string $statut = self::EN_ATTENTE;

    #[ORM\Column]
    private \DateTimeImmutable $creeLe;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $payeeLe = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Indiquez votre nom.')]
    #[Assert\Length(max: 120)]
    private ?string $nom = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Indiquez votre email.')]
    #[Assert\Email(message: 'Cet email n\'est pas valide.')]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Indiquez votre adresse.')]
    #[Assert\Length(max: 255)]
    private ?string $adresse = null;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank(message: 'Indiquez votre code postal.')]
    #[Assert\Regex(pattern: '/^\d{5}$/', message: 'Le code postal doit faire 5 chiffres.')]
    private ?string $codePostal = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Indiquez votre ville.')]
    #[Assert\Length(max: 120)]
    private ?string $ville = null;

    /** Total en euros. */
    #[ORM\Column]
    private int $total = 0;

    /** Mode de paiement utilisé : « stripe » ou « simulation ». */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $modePaiement = null;

    /** Identifiant de la session de paiement Stripe (cs_…). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paiementId = null;

    /** @var Collection<int, LigneCommande> */
    #[ORM\OneToMany(targetEntity: LigneCommande::class, mappedBy: 'commande', cascade: ['persist'], orphanRemoval: true)]
    private Collection $lignes;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
        $this->creeLe = new \DateTimeImmutable();
        $this->reference = date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function getStatutLibelle(): string
    {
        return self::LIBELLES[$this->statut] ?? $this->statut;
    }

    public function setStatut(string $statut): static
    {
        if (!isset(self::LIBELLES[$statut])) {
            throw new \InvalidArgumentException('Statut inconnu : ' . $statut);
        }
        $this->statut = $statut;

        return $this;
    }

    public function isPayee(): bool
    {
        return in_array($this->statut, [self::PAYEE, self::EXPEDIEE], true);
    }

    /** Enregistre le paiement (sans effet si la commande est déjà payée). */
    public function marquerPayee(string $mode, ?string $paiementId = null): void
    {
        if ($this->isPayee()) {
            return;
        }
        $this->statut = self::PAYEE;
        $this->payeeLe = new \DateTimeImmutable();
        $this->modePaiement = $mode;
        if ($paiementId !== null) {
            $this->paiementId = $paiementId;
        }
    }

    public function getCreeLe(): \DateTimeImmutable
    {
        return $this->creeLe;
    }

    public function getPayeeLe(): ?\DateTimeImmutable
    {
        return $this->payeeLe;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): static
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getModePaiement(): ?string
    {
        return $this->modePaiement;
    }

    public function getPaiementId(): ?string
    {
        return $this->paiementId;
    }

    public function setPaiementId(?string $paiementId): static
    {
        $this->paiementId = $paiementId;

        return $this;
    }

    /** @return Collection<int, LigneCommande> */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    /** Ajoute une ligne en copiant le nom et le prix actuels du produit. */
    public function ajouterProduit(Produit $produit, int $quantite): static
    {
        $ligne = (new LigneCommande())
            ->setCommande($this)
            ->setProduit($produit)
            ->setNomProduit($produit->getNom() . ' — ' . $produit->getMarque())
            ->setPrixUnitaire($produit->getPrix())
            ->setQuantite($quantite);
        $this->lignes->add($ligne);
        $this->total += $ligne->getSousTotal();

        return $this;
    }
}
