<?php

use App\Jobs\ExecuteIntegrationFlowJob;
use App\Models\FlowSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

it('dispatches due schedules and updates execution metadata', function () {
    Bus::fake();

    $schedule = FlowSchedule::query()->create([
        'flow_ref' => 'dummy-json-netsuite/orders-to-netsuite/sync-orders',
        'is_active' => true,
        'timezone' => 'UTC',
        'schedule_type' => FlowSchedule::TYPE_EVERY_MINUTES,
        'every_minutes' => 5,
        'trigger_payload' => ['source' => 'scheduler-test'],
        'next_run_at' => now()->subMinute(),
    ]);

    $this->artisan('flows:schedule-runner')->assertSuccessful();

    Bus::assertDispatched(ExecuteIntegrationFlowJob::class, function (ExecuteIntegrationFlowJob $job): bool {
        return $job->flowRef === 'dummy-json-netsuite/orders-to-netsuite/sync-orders'
            && $job->triggeredByType === 'schedule'
            && $job->triggerPayload === ['source' => 'scheduler-test'];
    });

    $schedule->refresh();

    expect($schedule->last_status)->toBe('queued')
        ->and($schedule->last_error)->toBeNull()
        ->and($schedule->last_run_at)->not->toBeNull()
        ->and($schedule->next_run_at)->not->toBeNull()
        ->and($schedule->next_run_at->isFuture())->toBeTrue();
});

it('marks schedule as failed when flow_ref cannot be resolved', function () {
    Bus::fake();

    $schedule = FlowSchedule::query()->create([
        'flow_ref' => 'unknown/group/flow',
        'is_active' => true,
        'timezone' => 'UTC',
        'schedule_type' => FlowSchedule::TYPE_EVERY_MINUTES,
        'every_minutes' => 2,
        'next_run_at' => now()->subMinute(),
    ]);

    $this->artisan('flows:schedule-runner')->assertSuccessful();

    Bus::assertNotDispatched(ExecuteIntegrationFlowJob::class);

    $schedule->refresh();

    expect($schedule->last_status)->toBe('failed')
        ->and($schedule->last_error)->not->toBeNull()
        ->and($schedule->next_run_at)->not->toBeNull();
});
