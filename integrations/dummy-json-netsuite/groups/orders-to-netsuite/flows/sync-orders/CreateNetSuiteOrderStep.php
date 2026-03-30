<?php

namespace Integrations\DummyJsonNetsuite\Groups\OrdersToNetsuite\Flows\SyncOrders;

use App\Integrations\Contracts\Step;
use App\Integrations\DiskFlowContext;
use App\Integrations\StepResult;
use RuntimeException;

final class CreateNetSuiteOrderStep implements Step
{
    public const NETSUITE_CONNECTOR_KEY = 'netsuite-sb-1';

    public function run(DiskFlowContext $context): StepResult
    {
        $transformOutput = $this->resolveTransformOutput($context->context());
        $payload = $transformOutput['netsuite_sales_order_payload'] ?? null;

        if (! is_array($payload)) {
            throw new RuntimeException('Missing NetSuite payload from TransformOrderStep output.');
        }

        $connectorKey = self::NETSUITE_CONNECTOR_KEY;
        $created = $context->connectors
            ->netsuite($connectorKey)
            ->createOrder($payload);

        $context->logs->info('Created NetSuite sales order.', [
            'connector_key' => $connectorKey,
            'dummy_order_id' => $transformOutput['dummy_order_id'] ?? null,
            'netsuite_id' => $created['id'] ?? null,
            'netsuite_tran_id' => $created['tranId'] ?? null,
        ]);

        return new StepResult([
            'dummy_order_id' => $transformOutput['dummy_order_id'] ?? null,
            'netsuite_response' => [
                'id' => $created['id'] ?? null,
                'tranId' => $created['tranId'] ?? null,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $contextData
     * @return array<string, mixed>
     */
    private function resolveTransformOutput(array $contextData): array
    {
        foreach ($contextData as $key => $value) {
            if (! is_string($key) || ! str_ends_with($key, '_TransformOrderStep') || ! is_array($value)) {
                continue;
            }

            return $value;
        }

        return [];
    }
}
