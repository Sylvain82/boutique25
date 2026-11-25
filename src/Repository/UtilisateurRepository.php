<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    /** Symfony renouvelle automatiquement l'empreinte du mot de passe si l'algorithme évolue. */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Utilisateur) {
            throw new UnsupportedUserException(sprintf('Type non géré : %s', $user::class));
        }
        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    /** @return Utilisateur[] administrateurs, triés par email */
    public function findAdministrateurs(): array
    {
        return array_values(array_filter($this->findBy([], ['email' => 'ASC']), fn (Utilisateur $u) => $u->isAdmin()));
    }

    /**
     * Clients (comptes sans rôle administrateur), les plus récents d'abord.
     * Le filtre est fait en PHP : la colonne « roles » est du JSON, peu pratique à filtrer en DQL.
     *
     * @return Utilisateur[]
     */
    public function findClients(): array
    {
        return array_values(array_filter($this->findBy([], ['creeLe' => 'DESC']), fn (Utilisateur $u) => !$u->isAdmin()));
    }
}
