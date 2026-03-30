<?php

use App\Enums\ConnectorType;
use App\Integrations\FlowDefinitionRegistry;
use App\Jobs\ExecuteIntegrationFlowJob;
use App\Models\Connector;
use App\Models\FlowExecution;
use App\Models\StepExecution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Integrations\DummyJsonNetsuite\Groups\OrdersToNetsuite\Flows\SyncOrders\CreateNetSuiteOrderStep;
use Integrations\DummyJsonNetsuite\Groups\OrdersToNetsuite\Flows\SyncOrders\FetchDummyOrdersStep;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    Connector::query()->create([
        'user_id' => null,
        'key' => 'dummy-json',
        'name' => 'Dummy Source',
        'connector_type' => ConnectorType::DummyJson,
        'credentials' => [
            'base_url' => 'https://dummyjson.com',
        ],
    ]);

    Connector::query()->create([
        'user_id' => null,
        'key' => 'netsuite-sb-1',
        'name' => 'NetSuite Target',
        'connector_type' => ConnectorType::NetSuite,
        'credentials' => [
            'account_id' => '3521821_SB1',
            'client_id' => 'ck',
            'client_secret' => 'cs',
            'token_id' => 'tid',
            'token_secret' => 'ts',
            'restlet_url' => 'https://3521821-sb1.restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=100&deploy=1',
        ],
    ]);
});

it('resolves the dummy-json-netsuite sync-orders flow from disk', function () {
    $registry = app(FlowDefinitionRegistry::class);
    $def = $registry->resolve('dummy-json-netsuite/orders-to-netsuite/sync-orders');

    expect($def->flowRef)->toBe('dummy-json-netsuite/orders-to-netsuite/sync-orders')
        ->and($def->integrationKey)->toBe('dummy-json-netsuite')
        ->and($def->isActive)->toBeTrue()
        ->and($def->name)->toBe('Dummy JSON orders to NetSuite')
        ->and($def->firstEntry())->toBe(FetchDummyOrdersStep::class)
        ->and($def->entryClasses)->toBe([FetchDummyOrdersStep::class]);
});

it('runs sync-orders flow with fan-out and creates one NetSuite order per cart', function () {
    Http::fake(function (Request $request) {
        $url = $request->url();

        if (str_contains($url, 'dummyjson.com/carts')) {
            return Http::response([
                'carts' => [
                    [
                        'id' => 101,
                        'total' => 20,
                        'products' => [
                            ['title' => 'Item 1', 'quantity' => 2, 'price' => 10],
                        ],
                    ],
                    [
                        'id' => 202,
                        'total' => 15,
                        'products' => [
                            ['title' => 'Item 2', 'quantity' => 1, 'price' => 15],
                        ],
                    ],
                ],
                'total' => 2,
            ], 200);
        }

        if (str_contains($url, 'restlets.api.netsuite.com/app/site/hosting/restlet.nl')) {
            return Http::response([
                'id' => 'SO-100',
                'tranId' => 'SO100',
            ], 201);
        }

        return Http::response(['unexpected' => $url], 500);
    });

    ExecuteIntegrationFlowJob::dispatchSync(
        'dummy-json-netsuite/orders-to-netsuite/sync-orders',
        null,
        'manual',
        ['source' => 'pest'],
    );

    $root = FlowExecution::query()->whereNull('parent_flow_execution_id')->first();
    expect($root)->not->toBeNull()
        ->and($root->status)->toBe(FlowExecution::STATUS_COMPLETED);

    expect(FlowExecution::query()->where('parent_flow_execution_id', $root->id)->count())->toBe(2);

    expect(StepExecution::query()
        ->where('step_class', CreateNetSuiteOrderStep::class)
        ->where('status', StepExecution::STATUS_COMPLETED)
        ->count())->toBe(2);

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'dummyjson.com/carts'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'restlets.api.netsuite.com/app/site/hosting/restlet.nl'));

    Notification::assertNothingSent();
});

it('completes sync-orders when using flows:run --sync', function () {
    Http::fake([
        'dummyjson.com/carts*' => Http::response([
            'carts' => [
                [
                    'id' => 303,
                    'total' => 5,
                    'products' => [
                        ['title' => 'Item 3', 'quantity' => 1, 'price' => 5],
                    ],
                ],
            ],
            'total' => 1,
        ], 200),
        '*.restlets.api.netsuite.com/*' => Http::response(['id' => 'SO-101', 'tranId' => 'SO101'], 201),
    ]);

    $this->artisan('flows:run', [
        'flow_ref' => 'dummy-json-netsuite/orders-to-netsuite/sync-orders',
        '--sync' => true,
    ])->assertSuccessful();

    $execution = FlowExecution::query()->first();
    expect($execution)->not->toBeNull()
        ->and($execution->status)->toBe(FlowExecution::STATUS_COMPLETED);
});

it('creates a pending execution when flow is dispatched to queue', function () {
    Http::fake();
    Bus::fake();

    $flowExecution = ExecuteIntegrationFlowJob::dispatchQueued(
        'dummy-json-netsuite/orders-to-netsuite/sync-orders',
        null,
        'manual',
        ['source' => 'queue-test'],
    );

    $flowExecution->refresh();

    expect($flowExecution->status)->toBe(FlowExecution::STATUS_PENDING)
        ->and($flowExecution->started_at)->toBeNull()
        ->and($flowExecution->finished_at)->toBeNull();
});

it('transitions pending flow to failed when execution errors', function () {
    Http::fake([
        'dummyjson.com/carts*' => Http::response(['message' => 'boom'], 500),
    ]);

    $flowExecution = FlowExecution::query()->create([
        'flow_ref' => 'dummy-json-netsuite/orders-to-netsuite/sync-orders',
        'integration_key' => 'dummy-json-netsuite',
        'status' => FlowExecution::STATUS_PENDING,
        'triggered_by_user_id' => null,
        'triggered_by_type' => 'manual',
        'context' => [],
        'trigger_payload' => [],
        'error_message' => null,
        'started_at' => null,
        'finished_at' => null,
    ]);

    try {
        ExecuteIntegrationFlowJob::dispatchSync(
            'dummy-json-netsuite/orders-to-netsuite/sync-orders',
            null,
            'manual',
            [],
            null,
            $flowExecution->id,
        );
    } catch (Throwable) {
        // Expected: failed HTTP request bubbles after flow failure is persisted.
    }

    $flowExecution->refresh();

    expect($flowExecution->status)->toBe(FlowExecution::STATUS_FAILED)
        ->and($flowExecution->started_at)->not->toBeNull()
        ->and($flowExecution->finished_at)->not->toBeNull()
        ->and($flowExecution->error_message)->not->toBeNull();
});
