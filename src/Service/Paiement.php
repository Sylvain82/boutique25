<?php

namespace App\Service;

use App\Entity\Commande;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Paiement par carte via Stripe Checkout (page de paiement hébergée par Stripe :
 * les numéros de carte ne passent jamais par notre serveur).
 *
 * Sans clé STRIPE_SECRET_KEY, le site fonctionne en « paiement simulé » pour la démonstration.
 * Avec une clé de test (sk_test_…), on peut payer avec la carte 4242 4242 4242 4242.
 */
class Paiement
{
    private const API = 'https://api.stripe.com/v1/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(default::STRIPE_SECRET_KEY)%')]
        private readonly ?string $cleSecrete = null,
        #[Autowire('%env(default::STRIPE_WEBHOOK_SECRET)%')]
        private readonly ?string $secretWebhook = null,
    ) {
    }

    public function isStripeActif(): bool
    {
        return $this->cleSecrete !== null && $this->cleSecrete !== '';
    }

    public function isModeTest(): bool
    {
        return !$this->isStripeActif() || str_starts_with((string) $this->cleSecrete, 'sk_test_');
    }

    /**
     * Crée la page de paiement Stripe et renvoie [identifiant de session, adresse où envoyer le client].
     *
     * @return array{id: string, url: string}
     */
    public function creerSessionStripe(Commande $commande, string $urlSucces, string $urlAnnulation): array
    {
        $donnees = [
            'mode' => 'payment',
            'locale' => 'fr',
            'customer_email' => $commande->getEmail(),
            'client_reference_id' => $commande->getReference(),
            'metadata' => ['reference' => $commande->getReference()],
            // {CHECKOUT_SESSION_ID} est remplacé par Stripe au retour
            'success_url' => $urlSucces . (str_contains($urlSucces, '?') ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $urlAnnulation,
            'line_items' => [],
        ];
        foreach ($commande->getLignes() as $ligne) {
            $donnees['line_items'][] = [
                'quantity' => $ligne->getQuantite(),
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $ligne->getPrixUnitaire() * 100, // Stripe compte en centimes
                    'product_data' => ['name' => $ligne->getNomProduit()],
                ],
            ];
        }

        $session = $this->appel('POST', 'checkout/sessions', $donnees);

        return ['id' => $session['id'], 'url' => $session['url']];
    }

    /** Demande à Stripe si la session de paiement a bien été payée. */
    public function sessionEstPayee(string $sessionId, Commande $commande): bool
    {
        $session = $this->appel('GET', 'checkout/sessions/' . rawurlencode($sessionId));

        return ($session['payment_status'] ?? null) === 'paid'
            && ($session['client_reference_id'] ?? null) === $commande->getReference()
            && (int) ($session['amount_total'] ?? 0) === $commande->getTotal() * 100;
    }

    public function isWebhookConfigure(): bool
    {
        return $this->secretWebhook !== null && $this->secretWebhook !== '';
    }

    /**
     * Vérifie qu'une notification vient bien de Stripe (signature de l'en-tête Stripe-Signature)
     * et renvoie l'événement décodé, ou null si elle est invalide.
     *
     * @return array<string, mixed>|null
     */
    public function lireWebhook(string $contenu, string $entete, int $tolerance = 300): ?array
    {
        if (!$this->isWebhookConfigure()) {
            return null;
        }

        $horodatage = null;
        $signatures = [];
        foreach (explode(',', $entete) as $morceau) {
            [$cle, $valeur] = array_pad(explode('=', trim($morceau), 2), 2, '');
            if ($cle === 't') {
                $horodatage = (int) $valeur;
            } elseif ($cle === 'v1') {
                $signatures[] = $valeur;
            }
        }
        if ($horodatage === null || $signatures === [] || abs(time() - $horodatage) > $tolerance) {
            return null;
        }

        $attendue = hash_hmac('sha256', $horodatage . '.' . $contenu, (string) $this->secretWebhook);
        foreach ($signatures as $signature) {
            if (hash_equals($attendue, $signature)) {
                $evenement = json_decode($contenu, true);

                return is_array($evenement) ? $evenement : null;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function appel(string $methode, string $chemin, array $donnees = []): array
    {
        $options = ['auth_bearer' => $this->cleSecrete, 'timeout' => 20];
        if ($donnees !== []) {
            // Stripe attend un formulaire encodé « à la PHP » : line_items[0][price_data][currency]=eur…
            $options['body'] = http_build_query($donnees);
            $options['headers'] = ['Content-Type' => 'application/x-www-form-urlencoded'];
        }

        $reponse = $this->httpClient->request($methode, self::API . $chemin, $options);
        $resultat = $reponse->toArray(false);

        if ($reponse->getStatusCode() >= 400) {
            throw new \RuntimeException('Erreur Stripe : ' . ($resultat['error']['message'] ?? 'réponse ' . $reponse->getStatusCode()));
        }

        return $resultat;
    }
}
