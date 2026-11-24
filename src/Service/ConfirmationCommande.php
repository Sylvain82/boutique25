<?php

namespace App\Service;

use App\Entity\Commande;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Enregistre le paiement d'une commande et envoie l'email de confirmation au client
 * (avec une copie cachée à la boutique si MAILER_COPIE est renseigné).
 *
 * Tant que MAILER_DSN vaut « null://null », les emails ne partent pas (aucune erreur).
 */
class ConfirmationCommande
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $expediteur,
        #[Autowire('%env(default::MAILER_COPIE)%')]
        private readonly ?string $copie = null,
        #[Autowire('%env(MAILER_DSN)%')]
        private readonly string $dsn = 'null://null',
    ) {
    }

    /** Les emails partent-ils vraiment ? (affiché dans l'administration) */
    public function isEnvoiActif(): bool
    {
        return !str_starts_with($this->dsn, 'null://');
    }

    /**
     * Marque la commande comme payée et, si c'est la première fois, envoie l'email.
     * Appelée depuis les 3 chemins possibles : paiement simulé, retour de Stripe, webhook Stripe.
     */
    public function confirmerPaiement(Commande $commande, string $mode, ?string $paiementId = null): void
    {
        if (!$commande->marquerPayee($mode, $paiementId)) {
            return; // déjà payée : rien à refaire, pas de deuxième email
        }
        $this->em->flush();
        $this->envoyerEmail($commande);
    }

    public function envoyerEmail(Commande $commande): bool
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->expediteur, 'Boutique'))
            ->to(new Address((string) $commande->getEmail(), (string) $commande->getNom()))
            ->subject(sprintf('Confirmation de votre commande %s', $commande->getReference()))
            ->htmlTemplate('emails/confirmation_commande.html.twig')
            ->textTemplate('emails/confirmation_commande.txt.twig')
            ->context(['commande' => $commande]);
        if ($this->copie !== null && $this->copie !== '') {
            $email->bcc($this->copie);
        }

        try {
            $this->mailer->send($email);

            return true;
        } catch (\Throwable $e) {
            // Un email qui ne part pas ne doit jamais faire échouer la commande : on le note dans les journaux.
            $this->logger->error('Email de confirmation non envoyé', ['exception' => $e, 'commande' => $commande->getReference()]);

            return false;
        }
    }
}
