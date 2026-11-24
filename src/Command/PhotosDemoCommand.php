<?php

namespace App\Command;

use App\Repository\ProduitRepository;
use App\Service\ImageProduit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Ajoute les illustrations (data/photos-demo/*.webp) aux produits de démonstration.
 *
 * Seuls les produits qui portent exactement l'un de ces noms ET n'ont pas encore de photo sont modifiés :
 * une photo envoyée depuis l'administration n'est jamais remplacée.
 */
#[AsCommand(name: 'app:produits:photos', description: 'Ajoute les illustrations aux produits de démonstration sans photo')]
class PhotosDemoCommand extends Command
{
    /** Nom du produit => fichier dans data/photos-demo/ */
    private const ILLUSTRATIONS = [
        'Casque Studio S1' => 'casque-studio-s1.webp',
        'Casque Sans fil Air' => 'casque-sans-fil-air.webp',
        'Écouteurs Mini' => 'ecouteurs-mini.webp',
        'Enceinte Nomade 20W' => 'enceinte-nomade-20w.webp',
        'Barre de son Salon' => 'barre-de-son-salon.webp',
        'Clavier mécanique K75' => 'clavier-mecanique-k75.webp',
        'Clavier compact sans fil' => 'clavier-compact-sans-fil.webp',
        'Souris ergonomique Verti' => 'souris-ergonomique-verti.webp',
        'Souris légère Gaming' => 'souris-legere-gaming.webp',
        'Écran 27" QHD' => 'ecran-27-qhd.webp',
        'Écran 24" Full HD' => 'ecran-24-full-hd.webp',
        'Hub USB-C 7 ports' => 'hub-usb-c-7-ports.webp',
        'Support ordinateur portable' => 'support-ordinateur-portable.webp',
        'Câble HDMI 2 m' => 'cable-hdmi-2-m.webp',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProduitRepository $produits,
        private readonly ImageProduit $images,
        #[Autowire('%kernel.project_dir%/data/photos-demo')]
        private readonly string $dossier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $ajoutees = 0;

        foreach (self::ILLUSTRATIONS as $nom => $fichier) {
            $chemin = $this->dossier . '/' . $fichier;
            if (!is_file($chemin)) {
                $io->warning("Fichier manquant : $fichier");
                continue;
            }
            foreach ($this->produits->findBy(['nom' => $nom]) as $produit) {
                if ($produit->hasImage()) {
                    continue;
                }
                $this->images->enregistrer($produit, new File($chemin));
                ++$ajoutees;
            }
        }
        $this->em->flush();

        $io->success($ajoutees > 0 ? "$ajoutees illustration(s) ajoutée(s)." : 'Aucun produit de démonstration sans photo : rien à faire.');

        return Command::SUCCESS;
    }
}
