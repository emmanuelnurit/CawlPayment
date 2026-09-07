<?php

declare(strict_types=1);

namespace CawlPayment\Service;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;

/**
 * Service de signature HMAC des identifiants de commande exposes dans les URLs
 *
 * Les URLs de retour de paiement transportent l'order_id en clair, ce qui permet
 * a un attaquant de le manipuler pour cibler la commande d'un autre client.
 * Ce service signe l'order_id (avec une date d'expiration) via HMAC-SHA256 et
 * permet de verifier cette signature au retour du PSP.
 */
class OrderSignatureService
{
    private const KEY_ENV_VAR = 'CAWL_URL_SIGNATURE_KEY';
    private const CONFIG_KEY_NAME = 'cawl_url_signature_key';
    private const ALGO = 'sha256';

    /**
     * Nom des parametres ajoutes a l'URL de retour
     */
    public const PARAM_ORDER_ID = 'order_id';
    public const PARAM_EXPIRES = 'expires';
    public const PARAM_SIGNATURE = 'sig';

    /**
     * Duree de validite par defaut d'une URL signee (24 heures)
     *
     * Le client peut rester longtemps sur la page de paiement hebergee
     * (saisie 3DS, validation bancaire), la duree est donc volontairement large.
     */
    public const DEFAULT_TTL = 86400;

    private ?string $signatureKey = null;

    public function __construct()
    {
        $this->initializeKey();
    }

    /**
     * Calcule la signature HMAC d'un couple (order_id, expiration)
     *
     * @param int $orderId L'identifiant de la commande
     * @param int $expiresAt Timestamp UNIX d'expiration de la signature
     * @return string La signature en hexadecimal
     * @throws \RuntimeException Si la cle de signature n'est pas disponible
     */
    public function sign(int $orderId, int $expiresAt): string
    {
        return hash_hmac(self::ALGO, $this->buildPayload($orderId, $expiresAt), $this->getKey());
    }

    /**
     * Retourne les parametres a ajouter a une URL de retour
     *
     * @param int $orderId L'identifiant de la commande
     * @param int|null $ttl Duree de validite en secondes (DEFAULT_TTL si null)
     * @return array{order_id: int, expires: int, sig: string}
     */
    public function getSignedParameters(int $orderId, ?int $ttl = null): array
    {
        $expiresAt = time() + ($ttl ?? self::DEFAULT_TTL);

        return [
            self::PARAM_ORDER_ID => $orderId,
            self::PARAM_EXPIRES => $expiresAt,
            self::PARAM_SIGNATURE => $this->sign($orderId, $expiresAt),
        ];
    }

    /**
     * Construit une URL de retour signee
     *
     * @param string $baseUrl L'URL de retour sans query string (ex: https://site/cawlpayment/success)
     * @param int $orderId L'identifiant de la commande
     * @param int|null $ttl Duree de validite en secondes (DEFAULT_TTL si null)
     * @return string L'URL avec order_id, expires et sig
     */
    public function buildSignedUrl(string $baseUrl, int $orderId, ?int $ttl = null): string
    {
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . http_build_query($this->getSignedParameters($orderId, $ttl));
    }

    /**
     * Verifie la signature d'un order_id
     *
     * Utilise hash_equals() pour prevenir les timing attacks.
     *
     * @param mixed $orderId L'order_id recu (chaine ou entier)
     * @param mixed $expiresAt Le timestamp d'expiration recu
     * @param string|null $signature La signature recue
     * @param int|null $now Timestamp courant (injectable pour les tests)
     * @return bool True si la signature est valide et non expiree
     */
    public function verify(mixed $orderId, mixed $expiresAt, ?string $signature, ?int $now = null): bool
    {
        if (!is_numeric($orderId) || !is_numeric($expiresAt) || empty($signature)) {
            return false;
        }

        $orderId = (int) $orderId;
        $expiresAt = (int) $expiresAt;

        // Signature expiree
        if ($expiresAt < ($now ?? time())) {
            return false;
        }

        try {
            $expected = $this->sign($orderId, $expiresAt);
        } catch (\RuntimeException $e) {
            Tlog::getInstance()->error('[CawlPayment] Cannot verify order signature: ' . $e->getMessage());

            return false;
        }

        return hash_equals($expected, $signature);
    }

    /**
     * Verifie la signature portee par les parametres de query string d'une requete
     *
     * @param Request $request La requete de retour du PSP
     * @param int|null $now Timestamp courant (injectable pour les tests)
     * @return bool True si la signature est valide et non expiree
     */
    public function verifyRequest(Request $request, ?int $now = null): bool
    {
        return $this->verify(
            $request->query->get(self::PARAM_ORDER_ID),
            $request->query->get(self::PARAM_EXPIRES),
            $request->query->get(self::PARAM_SIGNATURE),
            $now
        );
    }

    /**
     * Construit la charge utile signee
     *
     * Le separateur "|" est sans ambiguite car les deux champs sont des entiers.
     */
    private function buildPayload(int $orderId, int $expiresAt): string
    {
        return $orderId . '|' . $expiresAt;
    }

    /**
     * Initialise la cle de signature depuis l'environnement ou la configuration
     */
    private function initializeKey(): void
    {
        // Priorite 1: Variable d'environnement (recommande pour la production)
        $key = getenv(self::KEY_ENV_VAR);

        if (!empty($key) && $key !== false) {
            $this->signatureKey = $key;

            return;
        }

        // Priorite 2: Cle stockee en configuration Thelia
        try {
            $existingKey = ConfigQuery::read(self::CONFIG_KEY_NAME, '');

            if (!empty($existingKey)) {
                $this->signatureKey = $existingKey;

                return;
            }
        } catch (\Exception $e) {
            Tlog::getInstance()->warning(
                '[CawlPayment] Could not read URL signature key from database: ' . $e->getMessage()
            );
        }

        // Priorite 3: Generer et persister une nouvelle cle
        $newKey = base64_encode(random_bytes(32));

        try {
            ConfigQuery::write(self::CONFIG_KEY_NAME, $newKey);

            Tlog::getInstance()->warning(
                '[CawlPayment] Generated new URL signature key. ' .
                'For production, set CAWL_URL_SIGNATURE_KEY environment variable.'
            );

            $this->signatureKey = $newKey;
        } catch (\Exception $e) {
            // Sans cle persistee, les URLs signees ne seraient pas verifiables
            // apres un redemarrage: on echoue explicitement plutot que silencieusement.
            Tlog::getInstance()->error(
                '[CawlPayment] Failed to persist URL signature key: ' . $e->getMessage()
            );
            $this->signatureKey = null;
        }
    }

    /**
     * Retourne la cle de signature
     *
     * @throws \RuntimeException Si aucune cle n'est disponible
     */
    private function getKey(): string
    {
        if (empty($this->signatureKey)) {
            throw new \RuntimeException(
                'URL signature key not configured. Set CAWL_URL_SIGNATURE_KEY environment variable.'
            );
        }

        return $this->signatureKey;
    }
}
