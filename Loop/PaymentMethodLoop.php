<?php

declare(strict_types=1);

namespace CawlPayment\Loop;

use CawlPayment\CawlPayment;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;

/**
 * Loop to display enabled CAWL payment methods
 *
 * Usage:
 * {loop type="cawl_payment_method" name="my_loop" enabled="true"}
 *   {$CODE} - {$NAME} ({$CATEGORY})
 * {/loop}
 */
class PaymentMethodLoop extends BaseLoop implements ArraySearchLoopInterface
{
    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createBooleanTypeArgument('enabled', true),
            Argument::createAnyTypeArgument('category', null),
            Argument::createAnyTypeArgument('code', null)
        );
    }

    public function buildArray(): array
    {
        $module = new CawlPayment();
        $enabledOnly = $this->getEnabled();
        $filterCategory = $this->getCategory();
        $filterCode = $this->getCode();

        $methods = [];

        if ($enabledOnly) {
            $enabledMethods = $module->getEnabledPaymentMethods();
        } else {
            $enabledMethods = CawlPayment::PAYMENT_METHODS;
        }

        foreach ($enabledMethods as $code => $method) {
            // Filter by category
            if ($filterCategory && $method['category'] !== $filterCategory) {
                continue;
            }

            // Filter by code
            if ($filterCode && $code !== $filterCode) {
                continue;
            }

            $methods[] = [
                'code' => $code,
                'name' => $method['name'],
                'category' => $method['category'],
                'category_name' => CawlPayment::CATEGORIES[$method['category']] ?? $method['category'],
                'product_id' => $method['id'],
                'icon' => $method['icon'],
                'enabled' => $module->isPaymentMethodEnabled($code),
            ];
        }

        return $methods;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        foreach ($loopResult->getResultDataCollection() as $method) {
            $loopResultRow = new LoopResultRow();

            $loopResultRow
                ->set('CODE', $method['code'])
                ->set('NAME', $method['name'])
                ->set('CATEGORY', $method['category'])
                ->set('CATEGORY_NAME', $method['category_name'])
                ->set('PRODUCT_ID', $method['product_id'])
                ->set('ICON', $method['icon'])
                ->set('ENABLED', $method['enabled']);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
