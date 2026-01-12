<?php

declare(strict_types=1);

namespace CawlPayment\Hook;

use CawlPayment\CawlPayment;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * Admin hook for CAWL Payment module configuration
 */
class AdminHook extends BaseHook
{
    /**
     * Render module configuration content
     */
    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $moduleCode = $event->getArgument('module_code');

        // Try alternate argument name
        if (empty($moduleCode)) {
            $moduleCode = $event->getArgument('modulecode');
        }

        if ($moduleCode !== 'CawlPayment') {
            return;
        }

        // Get current configuration values
        $config = [
            'pspid' => CawlPayment::getConfigValue('pspid', ''),
            'api_key_test' => CawlPayment::getConfigValue('api_key_test', ''),
            'api_secret_test' => CawlPayment::getConfigValue('api_secret_test', ''),
            'api_key_prod' => CawlPayment::getConfigValue('api_key_prod', ''),
            'api_secret_prod' => CawlPayment::getConfigValue('api_secret_prod', ''),
            'webhook_key_test' => CawlPayment::getConfigValue('webhook_key_test', ''),
            'webhook_secret_test' => CawlPayment::getConfigValue('webhook_secret_test', ''),
            'webhook_key_prod' => CawlPayment::getConfigValue('webhook_key_prod', ''),
            'webhook_secret_prod' => CawlPayment::getConfigValue('webhook_secret_prod', ''),
            'environment' => CawlPayment::getConfigValue('environment', CawlPayment::ENV_TEST),
            'enabled_methods' => CawlPayment::getConfigValue('enabled_methods', ''),
            'enable_logging' => CawlPayment::getConfigValue('enable_logging', '1'),
            'checkout_description' => CawlPayment::getConfigValue('checkout_description', ''),
            'min_amount' => CawlPayment::getConfigValue('min_amount', '0'),
            'max_amount' => CawlPayment::getConfigValue('max_amount', '0'),
        ];

        // Parse enabled methods into array
        $enabledMethodsList = array_filter(array_map('trim', explode(',', $config['enabled_methods'])));

        // Get all payment methods by category
        $methodsByCategory = CawlPayment::getPaymentMethodsByCategory();

        // Get webhook URL
        $module = new CawlPayment();
        $webhookUrl = $module->getWebhookUrl();

        // Check if credentials are configured
        $hasTestCredentials = !empty($config['api_key_test']) && !empty($config['api_secret_test']);
        $hasProdCredentials = !empty($config['api_key_prod']) && !empty($config['api_secret_prod']);

        // Get CSRF token using null-safe request access
        $formToken = '';
        try {
            $request = $this->getRequest();
            if ($request !== null && $request->hasSession()) {
                $session = $request->getSession();
                $formToken = $session->get('cawlpayment_token');
                if (empty($formToken)) {
                    $formToken = bin2hex(random_bytes(32));
                    $session->set('cawlpayment_token', $formToken);
                }
            } else {
                $formToken = bin2hex(random_bytes(32));
            }
        } catch (\Throwable $e) {
            $formToken = bin2hex(random_bytes(32));
        }

        $event->add($this->render('module-configuration.html', [
            'config' => $config,
            'config_json' => json_encode($config),
            'enabled_methods_list' => $enabledMethodsList,
            'methods_by_category' => $methodsByCategory,
            'all_methods' => CawlPayment::PAYMENT_METHODS,
            'categories' => CawlPayment::CATEGORIES,
            'webhook_url' => $webhookUrl,
            'has_test_credentials' => $hasTestCredentials,
            'has_prod_credentials' => $hasProdCredentials,
            'is_production' => $config['environment'] === CawlPayment::ENV_PRODUCTION,
            'form_token' => $formToken,
            'webhook_key_test' => $config['webhook_key_test'],
            'webhook_key_prod' => $config['webhook_key_prod'],
        ]));
    }
}
