<?php

declare(strict_types=1);

namespace CawlPayment\Controller\Admin;

use CawlPayment\CawlPayment;
use CawlPayment\Service\CawlApiService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Translation\Translator;

/**
 * Admin controller for CAWL Payment API testing
 */
class TestController extends BaseAdminController
{
    public function __construct(
        private readonly SecurityContext $securityContext,
        private readonly CawlApiService $apiService
    ) {
    }

    /**
     * Check if user has admin access to this module
     */
    private function checkAdminAccess(string $access = AccessManager::VIEW): bool
    {
        if (!$this->securityContext->hasAdminUser()) {
            return false;
        }

        return $this->securityContext->isGranted(
            ['ADMIN'],
            [AdminResources::MODULE],
            ['CawlPayment'],
            [$access]
        );
    }

    /**
     * Test API connection using the official SDK
     */
    #[Route(path: '/admin/cawlpayment/api-test/connection', name: 'cawlpayment.admin.api_test_connection', methods: ['POST'])]
    public function testConnectionAction(Request $request): JsonResponse
    {
        if (!$this->checkAdminAccess()) {
            return new JsonResponse(['success' => false, 'error' => 'Access denied'], 403);
        }

        try {
            $apiService = $this->apiService;
            $result = $apiService->testConnection();

            return new JsonResponse($result);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get configuration summary
     */
    #[Route(path: '/admin/cawlpayment/api-test/config', name: 'cawlpayment.admin.api_test_config', methods: ['GET'])]
    public function configurationAction(Request $request): JsonResponse
    {
        if (!$this->checkAdminAccess()) {
            return new JsonResponse(['success' => false, 'error' => 'Access denied'], 403);
        }

        try {
            $apiService = $this->apiService;
            $config = $apiService->getConfigurationSummary();

            return new JsonResponse([
                'success' => true,
                'configuration' => $config,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available payment products from the API
     */
    #[Route(path: '/admin/cawlpayment/api-test/products', name: 'cawlpayment.admin.api_test_products', methods: ['GET'])]
    public function paymentProductsAction(Request $request): JsonResponse
    {
        if (!$this->checkAdminAccess()) {
            return new JsonResponse(['success' => false, 'error' => 'Access denied'], 403);
        }

        try {
            $amount = (int) $request->query->get('amount', 10000);
            $currency = $request->query->get('currency', 'EUR');
            $country = $request->query->get('country', 'FR');

            $apiService = $this->apiService;
            $result = $apiService->getPaymentProducts($amount, $currency, $country);

            return new JsonResponse($result);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a test hosted checkout (10 EUR)
     */
    #[Route(path: '/admin/cawlpayment/api-test/create-checkout', name: 'cawlpayment.admin.api_test_create_checkout', methods: ['POST'])]
    public function createTestCheckoutAction(Request $request): JsonResponse
    {
        if (!$this->checkAdminAccess(AccessManager::UPDATE)) {
            return new JsonResponse(['success' => false, 'error' => 'Access denied'], 403);
        }

        try {
            $amount = (int) $request->query->get('amount', 1000);
            $currency = $request->query->get('currency', 'EUR');

            $apiService = $this->apiService;
            $result = $apiService->createTestHostedCheckout($amount, $currency);

            return new JsonResponse($result);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get hosted checkout status
     */
    #[Route(path: '/admin/cawlpayment/api-test/checkout-status/{hostedCheckoutId}', name: 'cawlpayment.admin.api_test_checkout_status', requirements: ['hostedCheckoutId' => '[a-zA-Z0-9_-]+'], methods: ['GET'])]
    public function checkoutStatusAction(Request $request, string $hostedCheckoutId): JsonResponse
    {
        if (!$this->checkAdminAccess()) {
            return new JsonResponse(['success' => false, 'error' => 'Access denied'], 403);
        }

        try {
            $apiService = $this->apiService;
            $result = $apiService->getHostedCheckoutStatus($hostedCheckoutId);

            return new JsonResponse($result);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment status
     */
    #[Route(path: '/admin/cawlpayment/api-test/payment-status/{paymentId}', name: 'cawlpayment.admin.api_test_payment_status', requirements: ['paymentId' => '[a-zA-Z0-9_-]+'], methods: ['GET'])]
    public function paymentStatusAction(Request $request, string $paymentId): JsonResponse
    {
        if (!$this->checkAdminAccess()) {
            return new JsonResponse(['success' => false, 'error' => 'Access denied'], 403);
        }

        try {
            $apiService = $this->apiService;
            $result = $apiService->getPaymentStatus($paymentId);

            return new JsonResponse($result);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test return page after checkout (displays result)
     */
    #[Route(path: '/admin/cawlpayment/test-return', name: 'cawlpayment.admin.api_test_return', methods: ['GET'])]
    public function testReturnAction(Request $request): Response
    {
        $hostedCheckoutId = $request->query->get('hostedCheckoutId');

        $status = null;
        $error = null;

        if ($hostedCheckoutId) {
            try {
                $apiService = $this->apiService;
                $status = $apiService->getHostedCheckoutStatus($hostedCheckoutId);
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        return $this->render('cawlpayment-test-return', [
            'error' => $error,
            'has_status' => null !== $status,
            'is_paid' => (bool) ($status['isPaid'] ?? false),
            'status_label' => $status['status'] ?? Translator::getInstance()->trans(
                'Unknown',
                [],
                CawlPayment::DOMAIN_NAME
            ),
            'status_json' => null !== $status
                ? json_encode($status, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)
                : '',
        ]);
    }

    /**
     * Show test dashboard with all available tests
     */
    #[Route(path: '/admin/cawlpayment/api-test', name: 'cawlpayment.admin.test_dashboard', methods: ['GET'])]
    public function dashboardAction(Request $request): Response
    {
        if (null !== $response = $this->checkAuth(
            AdminResources::MODULE,
            ['CawlPayment'],
            AccessManager::VIEW
        )) {
            return $response;
        }

        $config = $this->apiService->getConfigurationSummary();

        return $this->render('cawlpayment-test-dashboard', [
            'config' => $config,
            'enabled_methods' => implode(', ', $config['enabled_methods']),
        ]);
    }
}
