<?php

use App\Models\FlowExecution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

it('does nothing when no stale running executions exist', function () {
    FlowExecution::query()->create([
        'flow_ref' => 'dummy-json-netsuite/orders-to-netsuite/sync-orders',
        'integration_key' => 'dummy-json-netsuite',
        'status' => FlowExecution::STATUS_RUNNING,
        'triggered_by_user_id' => null,
        'triggered_by_type' => 'manual',
        'context' => [],
        'trigger_payload' => [],
        'error_message' => null,
        'started_at' => now(),
        'finished_at' => null,
    ]);

    $this->artisan('flows:reconcile-stale-running', ['--minutes' => 120])
        ->expectsOutput('No stale RUNNING executions found.')
        ->assertOk();

    expect(FlowExecution::query()->first()->status)->toBe(FlowExecution::STATUS_RUNNING);
});

it('marks stale running executions as failed', function () {
    $staleTime = now()->subHours(3);

    $execution = FlowExecution::query()->create([
        'flow_ref' => 'dummy-json-netsuite/orders-to-netsuite/sync-orders',
        'integration_key' => 'dummy-json-netsuite',
        'status' => FlowExecution::STATUS_RUNNING,
        'triggered_by_user_id' => null,
        'triggered_by_type' => 'manual',
        'context' => [],
        'trigger_payload' => [],
        'error_message' => null,
        'started_at' => $staleTime,
        'finished_at' => null,
    ]);
    $execution->forceFill([
        'created_at' => $staleTime,
        'updated_at' => $staleTime,
    ])->saveQuietly();

    $this->artisan('flows:reconcile-stale-running', ['--minutes' => 120])
        ->assertOk();

    $execution->refresh();
    expect($execution->status)->toBe(FlowExecution::STATUS_FAILED)
        ->and($execution->finished_at)->not->toBeNull()
        ->and($execution->error_message)->toContain('Stale run');
});

it('dry run lists stale executions without changing them', function () {
    $staleTime = now()->subHours(3);

    $execution = FlowExecution::query()->create([
        'flow_ref' => 'dummy-json-netsuite/orders-to-netsuite/sync-orders',
        'integration_key' => 'dummy-json-netsuite',
        'status' => FlowExecution::STATUS_RUNNING,
        'triggered_by_user_id' => null,
        'triggered_by_type' => 'manual',
        'context' => [],
        'trigger_payload' => [],
        'error_message' => null,
        'started_at' => $staleTime,
        'finished_at' => null,
    ]);
    $execution->forceFill([
        'created_at' => $staleTime,
        'updated_at' => $staleTime,
    ])->saveQuietly();

    $this->artisan('flows:reconcile-stale-running', ['--minutes' => 120, '--dry-run' => true])
        ->assertOk();

    $execution->refresh();
    expect($execution->status)->toBe(FlowExecution::STATUS_RUNNING);
});
