<?php

declare(strict_types=1);

namespace CawlPayment\Controller\Admin;

use CawlPayment\CawlPayment;
use CawlPayment\Service\CawlApiService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\SecurityContext;
use Thelia\Tools\URL;

/**
 * Admin controller for CAWL Payment configuration
 */
class ConfigurationController
{
    public function __construct(
        private readonly SecurityContext $securityContext
    ) {
    }

    /**
     * Check if user has admin access to this module
     */
    private function checkAdminAccess(string $access = AccessManager::UPDATE): bool
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
     * Save configuration
     */
    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->checkAdminAccess(AccessManager::UPDATE)) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/CawlPayment', ['error' => 'Access denied'])
            );
        }

        try {
            // Get form data directly from request
            $formData = $request->request->all('cawlpayment_configuration');

            // Save credentials
            if (isset($formData['pspid'])) {
                CawlPayment::setConfigValue('pspid', $formData['pspid']);
            }

            // Only update API keys if they're not empty (allow keeping existing values)
            if (!empty($formData['api_key_test'])) {
                CawlPayment::setConfigValue('api_key_test', $formData['api_key_test']);
            }
            if (!empty($formData['api_secret_test'])) {
                CawlPayment::setConfigValue('api_secret_test', $formData['api_secret_test']);
            }
            if (!empty($formData['api_key_prod'])) {
                CawlPayment::setConfigValue('api_key_prod', $formData['api_key_prod']);
            }
            if (!empty($formData['api_secret_prod'])) {
                CawlPayment::setConfigValue('api_secret_prod', $formData['api_secret_prod']);
            }

            // Save webhook keys
            if (isset($formData['webhook_key_test'])) {
                CawlPayment::setConfigValue('webhook_key_test', $formData['webhook_key_test']);
            }
            if (!empty($formData['webhook_secret_test'])) {
                CawlPayment::setConfigValue('webhook_secret_test', $formData['webhook_secret_test']);
            }
            if (isset($formData['webhook_key_prod'])) {
                CawlPayment::setConfigValue('webhook_key_prod', $formData['webhook_key_prod']);
            }
            if (!empty($formData['webhook_secret_prod'])) {
                CawlPayment::setConfigValue('webhook_secret_prod', $formData['webhook_secret_prod']);
            }

            // Save environment
            CawlPayment::setConfigValue('environment', $formData['environment'] ?? CawlPayment::ENV_TEST);

            // Save enabled methods
            $enabledMethods = $formData['enabled_methods'] ?? '';
            CawlPayment::setConfigValue('enabled_methods', $enabledMethods);

            // Save options
            $enableLogging = isset($formData['enable_logging']) ? '1' : '0';
            CawlPayment::setConfigValue('enable_logging', $enableLogging);
            CawlPayment::setConfigValue('checkout_description', $formData['checkout_description'] ?? '');
            CawlPayment::setConfigValue('min_amount', $formData['min_amount'] ?? '0');
            CawlPayment::setConfigValue('max_amount', $formData['max_amount'] ?? '0');

            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/CawlPayment', ['success' => '1'])
            );

        } catch (\Exception $e) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/CawlPayment', ['error' => urlencode($e->getMessage())])
            );
        }
    }

    /**
     * Get available payment products from API (with caching)
     */
    public function paymentProductsAction(Request $request): JsonResponse
    {
        if (!$this->checkAdminAccess(AccessManager::VIEW)) {
            return new JsonResponse(['success' => false, 'error' => 'Access denied'], 403);
        }

        try {
            $apiService = new CawlApiService();

            // Get parameters from request
            $amount = (int) $request->query->get('amount', 10000); // Default 100 EUR in cents
            $currency = $request->query->get('currency', 'EUR');
            $country = $request->query->get('country', 'FR');

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
     * Test API connection
     */
    public function testConnectionAction(Request $request): JsonResponse
    {
        if (!$this->checkAdminAccess(AccessManager::VIEW)) {
            return new JsonResponse(['success' => false, 'error' => 'Access denied'], 403);
        }

        try {
            $apiService = new CawlApiService();
            $result = $apiService->testConnection();

            return new JsonResponse($result);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
