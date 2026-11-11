<?php

namespace App\Repository;

use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Produit>
 */
class ProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }

    /**
     * Recherche par texte (nom ou marque) et/ou par type, triée par nom.
     *
     * @return Produit[]
     */
    public function search(?string $q = null, ?string $type = null, bool $promotionOnly = false): array
    {
        $qb = $this->createQueryBuilder('p')->orderBy('p.nom', 'ASC');

        $q = $q !== null ? trim($q) : '';
        if ($q !== '') {
            // LOWER() des deux côtés : insensible à la casse sur PostgreSQL comme sur MySQL
            $qb->andWhere('LOWER(p.nom) LIKE :q OR LOWER(p.marque) LIKE :q')
               ->setParameter('q', '%' . mb_strtolower(addcslashes($q, '%_\\')) . '%');
        }
        if ($type !== null && $type !== '') {
            $qb->andWhere('p.type = :type')->setParameter('type', $type);
        }
        if ($promotionOnly) {
            $qb->andWhere('p.promotion = true');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return string[] liste des types existants (pour le filtre)
     */
    public function findTypes(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT p.type AS type')
            ->orderBy('p.type', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return array_values($rows);
    }
}
