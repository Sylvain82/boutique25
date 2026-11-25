<?php

namespace App\Repository;

use App\Entity\Commande;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    /** @return Commande[] les plus récentes d'abord, éventuellement filtrées par statut */
    public function findDernieres(?string $statut = null, int $limite = 200): array
    {
        $qb = $this->createQueryBuilder('c')->orderBy('c.creeLe', \SortDirection::Descending)->setMaxResults($limite);
        if ($statut !== null && $statut !== '') {
            $qb->andWhere('c.statut = :statut')->setParameter('statut', $statut);
        }

        return $qb->getQuery()->getResult();
    }

    /** Chiffre d'affaires (commandes payées ou expédiées), en euros. */
    public function chiffreAffaires(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(c.total), 0)')
            ->andWhere('c.statut IN (:statuts)')
            ->setParameter('statuts', [Commande::PAYEE, Commande::EXPEDIEE])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<string, int> nombre de commandes par statut */
    public function compterParStatut(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.statut AS statut, COUNT(c.id) AS n')
            ->groupBy('c.statut')
            ->getQuery()
            ->getArrayResult();

        $resultat = array_fill_keys(array_keys(Commande::LIBELLES), 0);
        foreach ($rows as $row) {
            $resultat[$row['statut']] = (int) $row['n'];
        }

        return $resultat;
    }

    /** @return Commande[] commandes d'un client, les plus récentes d'abord */
    public function findPourUtilisateur(Utilisateur $utilisateur): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.utilisateur = :u')->setParameter('u', $utilisateur)
            ->orderBy('c.creeLe', \SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    /** @return array<int, int> nombre de commandes par identifiant de client */
    public function compterParUtilisateur(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.utilisateur) AS id, COUNT(c.id) AS n')
            ->andWhere('c.utilisateur IS NOT NULL')
            ->groupBy('c.utilisateur')
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(fn ($r) => ['id' => (int) $r['id'], 'n' => (int) $r['n']], $rows), 'n', 'id');
    }
}
