<?php

namespace Integrations\DummyJsonNetsuite\Groups\OrdersToNetsuite\Flows\SyncOrders;

use App\Integrations\Contracts\Step;
use App\Integrations\DiskFlowContext;
use App\Integrations\StepResult;
use RuntimeException;

final class TransformOrderStep implements Step
{
    public const NETSUITE_DEFAULT_CUSTOMER_INTERNAL_ID = '9';

    public const NETSUITE_DEFAULT_ITEM_INTERNAL_ID = '75';

    public function run(DiskFlowContext $context): StepResult
    {
        $order = $context->context()['_fan_out_item'] ?? null;

        if (! is_array($order)) {
            throw new RuntimeException('Missing fan-out order item in context.');
        }

        $defaultItemId = self::NETSUITE_DEFAULT_ITEM_INTERNAL_ID;
        $defaultCustomerId = self::NETSUITE_DEFAULT_CUSTOMER_INTERNAL_ID;

        $products = $order['products'] ?? [];
        if (! is_array($products) || ! array_is_list($products)) {
            $products = [];
        }

        $lineItems = [];
        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }

            $quantity = max(1, (int) ($product['quantity'] ?? 1));
            $lineItems[] = [
                'item' => ['id' => $defaultItemId],
                'quantity' => $quantity,
                'rate' => (float) ($product['price'] ?? 0),
                'description' => (string) ($product['title'] ?? 'Dummy JSON item'),
            ];
        }

        if ($lineItems === []) {
            $lineItems[] = [
                'item' => ['id' => $defaultItemId],
                'quantity' => 1,
                'rate' => (float) ($order['total'] ?? 0),
                'description' => 'Fallback line from cart total',
            ];
        }

        $payload = [
            'entity' => ['id' => $defaultCustomerId],
            'externalId' => 'dummy-json-cart-'.($order['id'] ?? 'unknown'),
            'memo' => 'Imported from Dummy JSON cart '.($order['id'] ?? 'unknown'),
            'item' => [
                'items' => $lineItems,
            ],
        ];

        $context->logs->info('Transformed order for NetSuite.', [
            'dummy_order_id' => $order['id'] ?? null,
            'line_count' => count($lineItems),
        ]);

        return new StepResult(
            [
                'dummy_order_id' => $order['id'] ?? null,
                'netsuite_sales_order_payload' => $payload,
            ],
            CreateNetSuiteOrderStep::class,
        );
    }
}
