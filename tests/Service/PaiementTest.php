<?php

namespace App\Tests\Service;

use App\Entity\Commande;
use App\Entity\Produit;
use App\Service\Paiement;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Vérifie l'échange avec Stripe sans appeler Stripe (réponses simulées).
 * Lancer : php bin/phpunit
 */
class PaiementTest extends TestCase
{
    private function commande(): Commande
    {
        $produit = (new Produit())->setNom('Casque')->setMarque('Soniq')->setType('Audio')->setPrix(89);
        $commande = (new Commande())->setNom('Jeanne')->setEmail('jeanne@exemple.fr')
            ->setAdresse('3 rue des Lilas')->setCodePostal('31000')->setVille('Toulouse');

        return $commande->ajouterProduit($produit, 2);
    }

    public function testSansCleLePaiementEstSimule(): void
    {
        $paiement = new Paiement(new MockHttpClient(), '');
        self::assertFalse($paiement->isStripeActif());
        self::assertTrue($paiement->isModeTest());
    }

    public function testCreationDeLaSessionStripe(): void
    {
        $requete = null;
        $client = new MockHttpClient(function (string $methode, string $url, array $options) use (&$requete) {
            $requete = compact('methode', 'url', 'options');

            return new MockResponse(json_encode(['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_123']));
        });
        $paiement = new Paiement($client, 'sk_test_abc');
        $commande = $this->commande();

        $session = $paiement->creerSessionStripe($commande, 'https://boutique.test/ok', 'https://boutique.test/ko');

        self::assertSame('cs_test_123', $session['id']);
        self::assertSame('POST', $requete['methode']);
        self::assertSame('https://api.stripe.com/v1/checkout/sessions', $requete['url']);
        self::assertContains('Authorization: Bearer sk_test_abc', $requete['options']['headers']);

        parse_str($requete['options']['body'], $corps);
        self::assertSame('payment', $corps['mode']);
        self::assertSame($commande->getReference(), $corps['client_reference_id']);
        self::assertSame('8900', $corps['line_items'][0]['price_data']['unit_amount']); // centimes
        self::assertSame('2', $corps['line_items'][0]['quantity']);
        self::assertSame('https://boutique.test/ok?session_id={CHECKOUT_SESSION_ID}', $corps['success_url']);
    }

    public function testErreurStripeRemonteeProprement(): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['error' => ['message' => 'Invalid API Key']]), ['http_code' => 401]));
        $this->expectExceptionMessage('Invalid API Key');
        (new Paiement($client, 'sk_test_faux'))->creerSessionStripe($this->commande(), 'https://a/ok', 'https://a/ko');
    }

    public function testVerificationDuPaiement(): void
    {
        $commande = $this->commande();
        $reponse = fn (string $statut, int $montant) => new MockResponse(json_encode([
            'payment_status' => $statut, 'client_reference_id' => $commande->getReference(), 'amount_total' => $montant,
        ]));

        self::assertTrue((new Paiement(new MockHttpClient($reponse('paid', 17800)), 'sk_test_x'))->sessionEstPayee('cs_1', $commande));
        self::assertFalse((new Paiement(new MockHttpClient($reponse('unpaid', 17800)), 'sk_test_x'))->sessionEstPayee('cs_1', $commande));
        self::assertFalse((new Paiement(new MockHttpClient($reponse('paid', 100)), 'sk_test_x'))->sessionEstPayee('cs_1', $commande), 'montant différent');
    }

    public function testSignatureDuWebhook(): void
    {
        $paiement = new Paiement(new MockHttpClient(), 'sk_test_x', 'whsec_secret');
        $contenu = '{"type":"checkout.session.completed"}';
        $t = time();
        $signature = hash_hmac('sha256', $t . '.' . $contenu, 'whsec_secret');

        self::assertSame('checkout.session.completed', $paiement->lireWebhook($contenu, "t=$t,v1=$signature")['type']);
        self::assertNull($paiement->lireWebhook($contenu, "t=$t,v1=" . str_repeat('0', 64)), 'mauvaise signature');
        self::assertNull($paiement->lireWebhook('{"type":"autre"}', "t=$t,v1=$signature"), 'contenu modifié');
        $vieux = $t - 3600;
        self::assertNull($paiement->lireWebhook($contenu, "t=$vieux,v1=" . hash_hmac('sha256', $vieux . '.' . $contenu, 'whsec_secret')), 'trop ancien');
        self::assertNull((new Paiement(new MockHttpClient(), 'sk_test_x'))->lireWebhook($contenu, "t=$t,v1=$signature"), 'webhook non configuré');
    }
}
