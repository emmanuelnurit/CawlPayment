<?php

declare(strict_types=1);

namespace CawlPayment\Tests\Service;

use CawlPayment\Service\OrderSignatureService;
use CawlPayment\Tests\Mock\ConfigQueryMock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests unitaires pour OrderSignatureService
 *
 * Couvre la signature HMAC de l'order_id expose dans les URLs de retour
 * et sa verification au callback (falsification, expiration, timing attacks).
 */
class OrderSignatureServiceTest extends TestCase
{
    private const TEST_KEY = 'dGVzdC1zaWduYXR1cmUta2V5LWZvci11bml0LXRlc3RzMDA=';

    private OrderSignatureService $service;

    protected function setUp(): void
    {
        ConfigQueryMock::reset();
        putenv('CAWL_URL_SIGNATURE_KEY=' . self::TEST_KEY);

        $this->service = new OrderSignatureService();
    }

    protected function tearDown(): void
    {
        putenv('CAWL_URL_SIGNATURE_KEY');
        ConfigQueryMock::reset();
    }

    // =========================================================================
    // Tests de signature
    // =========================================================================

    public function testSignReturnsHexSha256Signature(): void
    {
        $signature = $this->service->sign(42, time() + 3600);

        $this->assertSame(64, strlen($signature), 'Une signature HMAC-SHA256 fait 64 caracteres hex');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signature);
    }

    public function testSignIsDeterministicForSameInput(): void
    {
        $expiresAt = time() + 3600;

        $this->assertSame(
            $this->service->sign(42, $expiresAt),
            $this->service->sign(42, $expiresAt),
            'La signature doit etre stable pour un meme couple (order_id, expiration)'
        );
    }

    public function testSignDiffersForDifferentOrderIds(): void
    {
        $expiresAt = time() + 3600;

        $this->assertNotSame(
            $this->service->sign(42, $expiresAt),
            $this->service->sign(43, $expiresAt),
            'Deux commandes differentes doivent avoir des signatures differentes'
        );
    }

    public function testSignDiffersForDifferentExpirations(): void
    {
        $this->assertNotSame(
            $this->service->sign(42, 2000000000),
            $this->service->sign(42, 2000000001),
            'La date d expiration doit etre couverte par la signature'
        );
    }

    public function testSignDiffersWithAnotherKey(): void
    {
        $expiresAt = time() + 3600;
        $signature = $this->service->sign(42, $expiresAt);

        putenv('CAWL_URL_SIGNATURE_KEY=another-secret-key-value');
        $otherService = new OrderSignatureService();

        $this->assertNotSame(
            $signature,
            $otherService->sign(42, $expiresAt),
            'Une cle differente doit produire une signature differente'
        );
    }

    // =========================================================================
    // Tests des parametres et URLs signes
    // =========================================================================

    public function testGetSignedParametersReturnsOrderIdExpiresAndSignature(): void
    {
        $params = $this->service->getSignedParameters(42);

        $this->assertSame(42, $params['order_id']);
        $this->assertGreaterThan(time(), $params['expires']);
        $this->assertSame($this->service->sign(42, $params['expires']), $params['sig']);
    }

    public function testGetSignedParametersUsesDefaultTtl(): void
    {
        $before = time();
        $params = $this->service->getSignedParameters(42);

        $this->assertGreaterThanOrEqual($before + OrderSignatureService::DEFAULT_TTL, $params['expires']);
        $this->assertLessThanOrEqual(time() + OrderSignatureService::DEFAULT_TTL, $params['expires']);
    }

    public function testGetSignedParametersHonoursCustomTtl(): void
    {
        $before = time();
        $params = $this->service->getSignedParameters(42, 60);

        $this->assertGreaterThanOrEqual($before + 60, $params['expires']);
        $this->assertLessThanOrEqual(time() + 60, $params['expires']);
    }

    public function testBuildSignedUrlAppendsQueryString(): void
    {
        $url = $this->service->buildSignedUrl('https://shop.test/cawlpayment/success', 42);

        $this->assertStringStartsWith('https://shop.test/cawlpayment/success?', $url);
        $this->assertStringContainsString('order_id=42', $url);
        $this->assertStringContainsString('expires=', $url);
        $this->assertStringContainsString('sig=', $url);
    }

    public function testBuildSignedUrlKeepsExistingQueryString(): void
    {
        $url = $this->service->buildSignedUrl('https://shop.test/cawlpayment/success?foo=bar', 42);

        $this->assertStringContainsString('foo=bar&order_id=42', $url);
    }

    public function testBuildSignedUrlProducesVerifiableParameters(): void
    {
        $url = $this->service->buildSignedUrl('https://shop.test/cawlpayment/success', 42);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertTrue(
            $this->service->verify($query['order_id'], $query['expires'], $query['sig']),
            'Une URL construite par le service doit etre verifiable'
        );
    }

    // =========================================================================
    // Tests de verification
    // =========================================================================

    public function testVerifyAcceptsValidSignature(): void
    {
        $expiresAt = time() + 3600;

        $this->assertTrue($this->service->verify(42, $expiresAt, $this->service->sign(42, $expiresAt)));
    }

    public function testVerifyAcceptsStringParametersFromQueryString(): void
    {
        $expiresAt = time() + 3600;

        $this->assertTrue(
            $this->service->verify('42', (string) $expiresAt, $this->service->sign(42, $expiresAt)),
            'Les parametres arrivent en chaine depuis la query string'
        );
    }

    public function testVerifyRejectsTamperedOrderId(): void
    {
        $expiresAt = time() + 3600;
        $signature = $this->service->sign(42, $expiresAt);

        $this->assertFalse(
            $this->service->verify(43, $expiresAt, $signature),
            'Un order_id modifie doit invalider la signature'
        );
    }

    public function testVerifyRejectsTamperedExpiration(): void
    {
        $expiresAt = time() + 3600;
        $signature = $this->service->sign(42, $expiresAt);

        $this->assertFalse(
            $this->service->verify(42, $expiresAt + 86400, $signature),
            'Une expiration prolongee doit invalider la signature'
        );
    }

    public function testVerifyRejectsExpiredSignature(): void
    {
        $expiresAt = time() - 1;

        $this->assertFalse($this->service->verify(42, $expiresAt, $this->service->sign(42, $expiresAt)));
    }

    public function testVerifyAcceptsSignatureAtExactExpirationBoundary(): void
    {
        $expiresAt = 2000000000;

        $this->assertTrue(
            $this->service->verify(42, $expiresAt, $this->service->sign(42, $expiresAt), $expiresAt),
            'La signature reste valide jusqu au timestamp d expiration inclus'
        );
    }

    public function testVerifyRejectsSignatureOneSecondAfterExpiration(): void
    {
        $expiresAt = 2000000000;

        $this->assertFalse(
            $this->service->verify(42, $expiresAt, $this->service->sign(42, $expiresAt), $expiresAt + 1)
        );
    }

    public function testVerifyRejectsSignatureFromAnotherKey(): void
    {
        $expiresAt = time() + 3600;
        $signature = $this->service->sign(42, $expiresAt);

        putenv('CAWL_URL_SIGNATURE_KEY=another-secret-key-value');
        $otherService = new OrderSignatureService();

        $this->assertFalse($otherService->verify(42, $expiresAt, $signature));
    }

    /**
     * @dataProvider invalidSignatureProvider
     */
    public function testVerifyRejectsInvalidSignatureValues(?string $signature): void
    {
        $this->assertFalse($this->service->verify(42, time() + 3600, $signature));
    }

    public static function invalidSignatureProvider(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'random hex' => [str_repeat('a', 64)],
            'truncated' => ['abc'],
            'non hex' => ['not-a-signature'],
        ];
    }

    /**
     * @dataProvider invalidOrderIdProvider
     */
    public function testVerifyRejectsNonNumericOrderId(mixed $orderId): void
    {
        $expiresAt = time() + 3600;

        $this->assertFalse($this->service->verify($orderId, $expiresAt, $this->service->sign(42, $expiresAt)));
    }

    public static function invalidOrderIdProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'sql injection attempt' => ["42 OR 1=1"],
            'array' => [['42']],
        ];
    }

    public function testVerifyRejectsNonNumericExpiration(): void
    {
        $this->assertFalse($this->service->verify(42, 'soon', $this->service->sign(42, time() + 3600)));
    }

    public function testVerifyRejectsMissingExpiration(): void
    {
        $this->assertFalse($this->service->verify(42, null, $this->service->sign(42, time() + 3600)));
    }

    // =========================================================================
    // Tests de verification depuis une Request
    // =========================================================================

    public function testVerifyRequestAcceptsSignedReturnUrl(): void
    {
        $request = new Request($this->service->getSignedParameters(42));

        $this->assertTrue($this->service->verifyRequest($request));
    }

    public function testVerifyRequestRejectsTamperedOrderId(): void
    {
        $params = $this->service->getSignedParameters(42);
        $params['order_id'] = 43;

        $this->assertFalse(
            $this->service->verifyRequest(new Request($params)),
            'Remplacer order_id dans l URL de retour doit etre rejete'
        );
    }

    public function testVerifyRequestRejectsUnsignedReturnUrl(): void
    {
        $this->assertFalse(
            $this->service->verifyRequest(new Request(['order_id' => 42])),
            'Une URL sans signature doit etre rejetee'
        );
    }

    public function testVerifyRequestRejectsExpiredReturnUrl(): void
    {
        $params = $this->service->getSignedParameters(42, -1);

        $this->assertFalse($this->service->verifyRequest(new Request($params)));
    }

    // =========================================================================
    // Tests de gestion de la cle
    // =========================================================================

    public function testKeyFallsBackToTheliaConfiguration(): void
    {
        putenv('CAWL_URL_SIGNATURE_KEY');
        ConfigQueryMock::setValues(['cawl_url_signature_key' => 'config-stored-key']);

        $service = new OrderSignatureService();
        $expiresAt = time() + 3600;

        $this->assertTrue($service->verify(42, $expiresAt, $service->sign(42, $expiresAt)));
    }

    public function testKeyIsGeneratedAndPersistedWhenMissing(): void
    {
        putenv('CAWL_URL_SIGNATURE_KEY');
        ConfigQueryMock::reset();

        $service = new OrderSignatureService();
        $persistedKey = ConfigQueryMock::read('cawl_url_signature_key');

        $this->assertNotEmpty($persistedKey, 'La cle generee doit etre persistee pour rester verifiable');

        // Une seconde instance doit reutiliser la cle persistee
        $expiresAt = time() + 3600;
        $signature = $service->sign(42, $expiresAt);

        $this->assertTrue((new OrderSignatureService())->verify(42, $expiresAt, $signature));
    }
}
