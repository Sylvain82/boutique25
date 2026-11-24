<?php

namespace App\Tests\Service;

use App\Entity\Commande;
use App\Entity\Produit;
use App\Service\ConfirmationCommande;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

class ConfirmationCommandeTest extends TestCase
{
    private function commande(): Commande
    {
        $produit = (new Produit())->setNom('Casque')->setMarque('Soniq')->setType('Audio')->setPrix(89);

        return (new Commande())->setNom('Jeanne')->setEmail('jeanne@exemple.fr')
            ->setAdresse('3 rue des Lilas')->setCodePostal('31000')->setVille('Toulouse')
            ->ajouterProduit($produit, 1);
    }

    public function testUnSeulEmailMemeSiLePaiementEstConfirmeDeuxFois(): void
    {
        $envoyes = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function ($email) use (&$envoyes) { $envoyes[] = $email; });
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $service = new ConfirmationCommande($em, $mailer, new NullLogger(), 'boutique@exemple.fr', 'copie@exemple.fr');
        $commande = $this->commande();

        // Le retour de Stripe et le webhook peuvent arriver tous les deux : un seul email doit partir.
        $service->confirmerPaiement($commande, 'stripe', 'cs_1');
        $service->confirmerPaiement($commande, 'stripe', 'cs_1');

        self::assertTrue($commande->isPayee());
        self::assertCount(1, $envoyes);
        /** @var TemplatedEmail $email */
        $email = $envoyes[0];
        self::assertSame('jeanne@exemple.fr', $email->getTo()[0]->getAddress());
        self::assertSame('copie@exemple.fr', $email->getBcc()[0]->getAddress());
        self::assertStringContainsString($commande->getReference(), $email->getSubject());
    }

    public function testUnEmailQuiNePartPasNeBloquePasLaCommande(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('serveur indisponible'));
        $service = new ConfirmationCommande($this->createMock(EntityManagerInterface::class), $mailer, new NullLogger(), 'boutique@exemple.fr');
        $commande = $this->commande();

        $service->confirmerPaiement($commande, 'simulation');

        self::assertTrue($commande->isPayee());
    }

    public function testEnvoiActifSelonMailerDsn(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $mailer = $this->createMock(MailerInterface::class);
        self::assertFalse((new ConfirmationCommande($em, $mailer, new NullLogger(), 'a@b.fr', null, 'null://null'))->isEnvoiActif());
        self::assertTrue((new ConfirmationCommande($em, $mailer, new NullLogger(), 'a@b.fr', null, 'brevo+api://cle@default'))->isEnvoiActif());
    }
}
