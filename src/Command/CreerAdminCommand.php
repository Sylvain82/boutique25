<?php

namespace App\Command;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée un administrateur (ou change le mot de passe s'il existe déjà).
 *
 *   php bin/console app:admin:creer moi@exemple.fr
 *
 * Le mot de passe est demandé sans s'afficher. Sur Render (pas de terminal sur l'offre gratuite),
 * docker/start.sh lance « app:admin:creer $ADMIN_EMAIL --si-absent » et le mot de passe est lu
 * dans la variable ADMIN_PASSWORD.
 */
#[AsCommand(name: 'app:admin:creer', description: 'Crée un administrateur ou change son mot de passe')]
class CreerAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UtilisateurRepository $utilisateurs,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de connexion')
            ->addArgument('motdepasse', InputArgument::OPTIONAL, 'Mot de passe (sinon variable ADMIN_PASSWORD, sinon demandé)')
            ->addOption('si-absent', null, InputOption::VALUE_NONE, 'Ne fait rien si le compte existe déjà (ne change pas son mot de passe)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim((string) $input->getArgument('email')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Email invalide.');

            return Command::FAILURE;
        }

        $utilisateur = $this->utilisateurs->findOneBy(['email' => $email]);
        if ($utilisateur !== null && $input->getOption('si-absent')) {
            $io->note("L'administrateur $email existe déjà : rien à faire.");

            return Command::SUCCESS;
        }

        $motDePasse = (string) ($input->getArgument('motdepasse')
            ?? (getenv('ADMIN_PASSWORD') ?: null)
            ?? $io->askHidden('Mot de passe (10 caractères minimum)'));
        if (mb_strlen($motDePasse) < 10) {
            $io->error('Le mot de passe doit faire au moins 10 caractères.');

            return Command::FAILURE;
        }

        $nouveau = $utilisateur === null;
        $utilisateur ??= (new Utilisateur())->setEmail($email);

        $utilisateur->setRoles(['ROLE_ADMIN']);
        $utilisateur->setPassword($this->hasher->hashPassword($utilisateur, $motDePasse));
        $this->em->persist($utilisateur);
        $this->em->flush();

        $io->success($nouveau ? "Administrateur $email créé." : "Mot de passe de $email mis à jour.");

        return Command::SUCCESS;
    }
}
