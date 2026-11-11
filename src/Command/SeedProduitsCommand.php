<?php

namespace App\Command;

use App\Entity\Produit;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Ajoute quelques produits de démonstration, uniquement si la table est vide.
 * Utile sur l'hébergement gratuit où la base peut être recréée.
 */
#[AsCommand(name: 'app:produits:seed', description: 'Ajoute des produits de démonstration si la boutique est vide')]
class SeedProduitsCommand extends Command
{
    private const DEMO = [
        // type, nom, marque, prix (€), promotion
        ['Casque audio', 'Casque Studio S1', 'Soniq', 89, false],
        ['Casque audio', 'Casque Sans fil Air', 'Soniq', 129, true],
        ['Casque audio', 'Écouteurs Mini', 'Auralis', 39, false],
        ['Enceinte', 'Enceinte Nomade 20W', 'Auralis', 59, true],
        ['Enceinte', 'Barre de son Salon', 'Soniq', 199, false],
        ['Clavier', 'Clavier mécanique K75', 'Keyra', 99, false],
        ['Clavier', 'Clavier compact sans fil', 'Keyra', 49, true],
        ['Souris', 'Souris ergonomique Verti', 'Keyra', 45, false],
        ['Souris', 'Souris légère Gaming', 'Voltek', 35, true],
        ['Écran', 'Écran 27" QHD', 'Voltek', 279, false],
        ['Écran', 'Écran 24" Full HD', 'Voltek', 149, true],
        ['Accessoire', 'Hub USB-C 7 ports', 'Voltek', 42, false],
        ['Accessoire', 'Support ordinateur portable', 'Keyra', 29, false],
        ['Accessoire', 'Câble HDMI 2 m', 'Voltek', 9, false],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProduitRepository $produits,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->produits->count([]) > 0) {
            $io->note('La boutique contient déjà des produits : rien à ajouter.');

            return Command::SUCCESS;
        }

        foreach (self::DEMO as [$type, $nom, $marque, $prix, $promotion]) {
            $this->em->persist(
                (new Produit())->setType($type)->setNom($nom)->setMarque($marque)->setPrix($prix)->setPromotion($promotion)
            );
        }
        $this->em->flush();

        $io->success(count(self::DEMO) . ' produits de démonstration ajoutés.');

        return Command::SUCCESS;
    }
}
