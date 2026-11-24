<?php

namespace App\Service;

use App\Entity\Produit;
use App\Entity\ProduitImage;
use App\Repository\ProduitImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Enregistre / supprime la photo d'un produit.
 * La photo envoyée est réduite à 800 px maximum et convertie en WebP (léger) avec l'extension GD de PHP.
 */
class ImageProduit
{
    public const TAILLE_MAX = 800;
    private const QUALITE = 80;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProduitImageRepository $images,
    ) {
    }

    /** À appeler avant le flush() du contrôleur. */
    public function enregistrer(Produit $produit, File $fichier): void
    {
        $contenu = $this->reduire($fichier->getPathname());

        $image = $this->images->findPourProduit($produit) ?? (new ProduitImage())->setProduit($produit);
        $image->setContenu($contenu)->setTypeMime('image/webp');
        $this->em->persist($image);

        $produit->setImageMaj(new \DateTimeImmutable());
    }

    /** À appeler avant le flush() du contrôleur. */
    public function supprimer(Produit $produit): void
    {
        $image = $this->images->findPourProduit($produit);
        if ($image !== null) {
            $this->em->remove($image);
        }
        $produit->setImageMaj(null);
    }

    /** Redimensionne (sans agrandir) et convertit en WebP. */
    public function reduire(string $chemin): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($chemin));
        if ($source === false) {
            throw new \InvalidArgumentException('Image illisible.');
        }

        $source = $this->redresser($source, $chemin);

        $largeur = imagesx($source);
        $hauteur = imagesy($source);
        $ratio = min(1, self::TAILLE_MAX / max($largeur, $hauteur));
        $nouvelleLargeur = max(1, (int) round($largeur * $ratio));
        $nouvelleHauteur = max(1, (int) round($hauteur * $ratio));

        $cible = imagecreatetruecolor($nouvelleLargeur, $nouvelleHauteur);
        // Garde la transparence des PNG
        imagealphablending($cible, false);
        imagesavealpha($cible, true);
        imagecopyresampled($cible, $source, 0, 0, 0, 0, $nouvelleLargeur, $nouvelleHauteur, $largeur, $hauteur);

        ob_start();
        imagewebp($cible, null, self::QUALITE);
        $contenu = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($cible);

        return $contenu;
    }

    /** Les photos de téléphone sont souvent enregistrées « couchées » avec une indication de rotation (EXIF). */
    private function redresser(\GdImage $image, string $chemin): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($chemin);
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }
        $tournee = imagerotate($image, $angle, 0);
        if ($tournee === false) {
            return $image;
        }
        imagedestroy($image);

        return $tournee;
    }
}
