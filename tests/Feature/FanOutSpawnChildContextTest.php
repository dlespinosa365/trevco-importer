<?php

use App\Integrations\DiskFlowDefinition;
use App\Integrations\FanOut\FanOutConfig;
use App\Integrations\FanOut\FanOutCoordinator;
use App\Models\FlowExecution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Integrations\DummyJsonNetsuite\Groups\OrdersToNetsuite\Flows\SyncOrders\FetchDummyOrdersStep;
use Integrations\DummyJsonNetsuite\Groups\OrdersToNetsuite\Flows\SyncOrders\TransformOrderStep;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

it('narrows fan-out step output to a single-item list in each child context', function () {
    Queue::fake();

    $definition = new DiskFlowDefinition(
        flowRef: 'dummy-json-netsuite/groups/orders-to-netsuite/flows/sync-orders',
        integrationKey: 'dummy-json-netsuite',
        name: 'Sync orders',
        isActive: true,
        entryClasses: [FetchDummyOrdersStep::class, TransformOrderStep::class],
        integrationConfig: [],
        groupExtraConfig: [],
        flowExtraConfig: [],
        failureNotifications: [],
    );

    $items = [
        ['id' => 1, 'k' => 'a'],
        ['id' => 2, 'k' => 'b'],
    ];

    $parent = FlowExecution::query()->create([
        'flow_ref' => $definition->flowRef,
        'integration_key' => $definition->integrationKey,
        'status' => FlowExecution::STATUS_RUNNING,
        'triggered_by_user_id' => null,
        'triggered_by_type' => 'manual',
        'parent_flow_execution_id' => null,
        'fan_out_item_reference' => null,
        'context' => [
            '0_'.class_basename(FetchDummyOrdersStep::class) => [
                'items' => $items,
                'total' => 2,
                'source' => 'dummy-json',
            ],
        ],
        'trigger_payload' => [],
        'error_message' => null,
        'started_at' => now(),
        'finished_at' => null,
    ]);

    $config = new FanOutConfig(itemsPath: 'items', itemReferenceKey: 'id');

    app(FanOutCoordinator::class)->spawnChildRuns(
        $parent->fresh(),
        $definition,
        $parent->context,
        [],
        $items,
        $config,
        TransformOrderStep::class,
        1,
    );

    $children = FlowExecution::query()
        ->where('parent_flow_execution_id', $parent->id)
        ->orderBy('id')
        ->get();

    expect($children)->toHaveCount(2);

    $stepKey = '0_'.class_basename(FetchDummyOrdersStep::class);

    expect($children[0]->context[$stepKey]['items'])->toHaveCount(1)
        ->and($children[0]->context[$stepKey]['items'][0])->toBe($items[0])
        ->and($children[0]->context['_fan_out_item'])->toBe($items[0]);

    expect($children[1]->context[$stepKey]['items'])->toHaveCount(1)
        ->and($children[1]->context[$stepKey]['items'][0])->toBe($items[1])
        ->and($children[1]->context['_fan_out_item'])->toBe($items[1]);
});
