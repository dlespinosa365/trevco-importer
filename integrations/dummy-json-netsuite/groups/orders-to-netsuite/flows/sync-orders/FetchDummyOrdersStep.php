<?php

namespace Integrations\DummyJsonNetsuite\Groups\OrdersToNetsuite\Flows\SyncOrders;

use App\Integrations\Attributes\FanOut;
use App\Integrations\Contracts\DefinesFlowMetadata;
use App\Integrations\Contracts\Step;
use App\Integrations\DiskFlowContext;
use App\Integrations\StepResult;

#[FanOut(itemsPath: 'items', itemReferenceKey: 'id')]
final class FetchDummyOrdersStep implements DefinesFlowMetadata, Step
{
    public const DUMMY_CONNECTOR_KEY = 'dummy-json';

    public const DUMMY_ORDERS_LIMIT = 10;

    public static function flowDefinitionName(): string
    {
        return 'Dummy JSON orders to NetSuite';
    }

    public static function flowDefinitionIsActive(): bool
    {
        return true;
    }

    public static function flowDefinitionExtraConfig(): array
    {
        return [];
    }

    public function run(DiskFlowContext $context): StepResult
    {
        $connectorKey = self::DUMMY_CONNECTOR_KEY;
        $limit = self::DUMMY_ORDERS_LIMIT;

        $orders = $context->connectors
            ->dummyJson($connectorKey)
            ->getOrders(['limit' => max(1, $limit)]);

        $items = $orders['carts'] ?? [];
        if (! is_array($items) || ! array_is_list($items)) {
            $items = [];
        }

        $context->logs->info('Fetched orders from Dummy JSON.', [
            'connector_key' => $connectorKey,
            'count' => count($items),
        ]);

        return new StepResult(
            [
                'items' => $items,
                'total' => $orders['total'] ?? count($items),
                'source' => 'dummy-json',
            ],
            TransformOrderStep::class,
        );
    }
}
