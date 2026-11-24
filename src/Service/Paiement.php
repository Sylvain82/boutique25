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
 * Avec une clé de test (rk_test_… ou sk_test_…), on peut payer avec la carte 4242 4242 4242 4242.
 * Clé conseillée : une clé restreinte (rk_…) avec seulement le droit « Checkout Sessions : écriture ».
 */
class Paiement
{
    private const API = 'https://api.stripe.com/v1/';

    /** Version de l'API fixée : un changement de version sur le compte Stripe ne modifie pas les réponses. */
    public const VERSION_API = '2026-11-18.endive';

    /** Durée de validité de la page de paiement (Stripe accepte de 30 min à 24 h). */
    public const DUREE_SESSION = 3600;

    /** Étiquette des sessions dans le tableau de bord Stripe, pour suivre ce parcours de paiement. */
    public const IDENTIFIANT_INTEGRATION = 'boutique25-checkout-qmzrtvaw';

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
        return !$this->isStripeActif() || preg_match('/^[sr]k_test_/', (string) $this->cleSecrete) === 1;
    }

    /**
     * Crée la page de paiement Stripe et renvoie [identifiant de session, adresse où envoyer le client].
     *
     * La clé d'idempotence garantit qu'un double clic (ou une requête rejouée) ne crée pas deux pages :
     * Stripe renvoie la même session pour la même clé.
     *
     * @return array{id: string, url: string}
     */
    public function creerSessionStripe(Commande $commande, string $urlSucces, string $urlAnnulation, ?string $sessionPrecedente = null): array
    {
        $donnees = [
            'mode' => 'payment',
            'integration_identifier' => self::IDENTIFIANT_INTEGRATION,
            'expires_at' => time() + self::DUREE_SESSION,
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

        // Une nouvelle clé seulement si la page précédente a expiré (sinon on réutilise celle-ci)
        $cle = 'checkout-' . $commande->getReference() . '-' . ($sessionPrecedente ?? 'initiale');
        $session = $this->appel('POST', 'checkout/sessions', $donnees, $cle);

        return ['id' => $session['id'], 'url' => $session['url']];
    }

    /**
     * Lit une session de paiement chez Stripe.
     *
     * @return array<string, mixed> (status : open / complete / expired ; payment_status : paid / unpaid…)
     */
    public function lireSession(string $sessionId): array
    {
        return $this->appel('GET', 'checkout/sessions/' . rawurlencode($sessionId));
    }

    /** Demande à Stripe si la session de paiement a bien été payée. */
    public function sessionEstPayee(string $sessionId, Commande $commande): bool
    {
        return $this->sessionPayeePour($this->lireSession($sessionId), $commande);
    }

    /**
     * Vérifie qu'une session (lue chez Stripe ou reçue par webhook) correspond à la commande et est payée.
     *
     * @param array<string, mixed> $session
     */
    public function sessionPayeePour(array $session, Commande $commande): bool
    {
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
    private function appel(string $methode, string $chemin, array $donnees = [], ?string $cleIdempotence = null): array
    {
        $options = [
            'auth_bearer' => $this->cleSecrete,
            'timeout' => 20,
            'headers' => ['Stripe-Version' => self::VERSION_API],
        ];
        if ($cleIdempotence !== null) {
            $options['headers']['Idempotency-Key'] = $cleIdempotence;
        }
        if ($donnees !== []) {
            // Stripe attend un formulaire encodé « à la PHP » : line_items[0][price_data][currency]=eur…
            $options['body'] = http_build_query($donnees);
            $options['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        $reponse = $this->httpClient->request($methode, self::API . $chemin, $options);
        $resultat = $reponse->toArray(false);

        if ($reponse->getStatusCode() >= 400) {
            throw new \RuntimeException('Erreur Stripe : ' . ($resultat['error']['message'] ?? 'réponse ' . $reponse->getStatusCode()));
        }

        return $resultat;
    }
}
