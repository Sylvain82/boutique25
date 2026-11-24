<?php

namespace App\Tests\Service;

use App\Repository\ProduitImageRepository;
use App\Service\ImageProduit;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ImageProduitTest extends TestCase
{
    private function service(): ImageProduit
    {
        return new ImageProduit($this->createMock(EntityManagerInterface::class), $this->createMock(ProduitImageRepository::class));
    }

    private function fichierImage(int $largeur, int $hauteur): string
    {
        $image = imagecreatetruecolor($largeur, $hauteur);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 30));
        $chemin = tempnam(sys_get_temp_dir(), 'img');
        imagejpeg($image, $chemin, 95);

        return $chemin;
    }

    public function testGrandePhotoReduiteEnWebp(): void
    {
        $chemin = $this->fichierImage(3000, 1500);
        $resultat = $this->service()->reduire($chemin);
        unlink($chemin);

        $infos = getimagesizefromstring($resultat);
        self::assertSame('image/webp', $infos['mime']);
        self::assertSame(ImageProduit::TAILLE_MAX, $infos[0], 'largeur ramenée à 800 px');
        self::assertSame(400, $infos[1], 'proportions gardées');
    }

    public function testPetitePhotoPasAgrandie(): void
    {
        $chemin = $this->fichierImage(300, 200);
        $infos = getimagesizefromstring($this->service()->reduire($chemin));
        unlink($chemin);

        self::assertSame([300, 200], [$infos[0], $infos[1]]);
    }

    public function testFichierQuiNestPasUneImage(): void
    {
        $chemin = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($chemin, 'ceci n\'est pas une image');
        $this->expectException(\InvalidArgumentException::class);
        try {
            $this->service()->reduire($chemin);
        } finally {
            unlink($chemin);
        }
    }
}
