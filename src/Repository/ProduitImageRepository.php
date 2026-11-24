<?php

namespace App\Repository;

use App\Entity\Produit;
use App\Entity\ProduitImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProduitImage>
 */
class ProduitImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProduitImage::class);
    }

    public function findPourProduit(Produit $produit): ?ProduitImage
    {
        return $this->findOneBy(['produit' => $produit]);
    }
}
