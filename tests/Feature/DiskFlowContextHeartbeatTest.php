<?php

use App\Integrations\ConnectorsHelper;
use App\Integrations\DiskFlowContext;
use App\Integrations\StepLogCollector;
use App\Models\FlowExecution;
use App\Models\StepExecution;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeRunningFlowWithStep(): array
{
    $execution = FlowExecution::query()->create([
        'flow_ref' => 'test/integration/flow',
        'integration_key' => 'test',
        'status' => FlowExecution::STATUS_RUNNING,
        'triggered_by_user_id' => null,
        'triggered_by_type' => 'manual',
        'context' => [],
        'trigger_payload' => [],
        'error_message' => null,
        'started_at' => now(),
        'finished_at' => null,
    ]);

    $step = StepExecution::query()->create([
        'flow_execution_id' => $execution->id,
        'flow_step_id' => null,
        'step_class' => 'App\\Steps\\ExampleStep',
        'step_index' => 0,
        'step_type' => StepExecution::STEP_TYPE_INTEGRATION_DISK,
        'status' => StepExecution::STATUS_RUNNING,
        'input' => [],
        'output' => null,
        'logs' => null,
        'error_message' => null,
        'duration_ms' => null,
        'started_at' => now(),
        'finished_at' => null,
    ]);

    return [$execution, $step];
}

it('bumps flow and step updated_at when heartbeat runs', function (): void {
    [$execution, $step] = makeRunningFlowWithStep();

    $this->travel(-5)->minutes();

    $execution->touch();
    $step->touch();
    $execution->refresh();
    $step->refresh();
    $beforeFlow = $execution->updated_at;
    $beforeStep = $step->updated_at;

    $this->travelBack();

    $context = new DiskFlowContext(
        execution: $execution,
        contextSnapshot: [],
        triggerPayload: [],
        mergedConfig: [],
        connectors: new ConnectorsHelper(null),
        logs: new StepLogCollector,
        stepExecutionId: $step->id,
    );

    expect($context->heartbeat(0))->toBeTrue();

    $execution->refresh();
    $step->refresh();

    expect($execution->updated_at->greaterThan($beforeFlow))->toBeTrue()
        ->and($step->updated_at->greaterThan($beforeStep))->toBeTrue();
});

it('throttles heartbeats using the minimum interval', function (): void {
    config()->set('flows.heartbeat_interval_seconds', 120);

    [$execution, $step] = makeRunningFlowWithStep();

    $context = new DiskFlowContext(
        execution: $execution,
        contextSnapshot: [],
        triggerPayload: [],
        mergedConfig: [],
        connectors: new ConnectorsHelper(null),
        logs: new StepLogCollector,
        stepExecutionId: $step->id,
    );

    expect($context->heartbeat())->toBeTrue();
    expect($context->heartbeat())->toBeFalse();
});

it('does not touch rows when flow is not running', function (): void {
    [$execution, $step] = makeRunningFlowWithStep();

    $execution->forceFill([
        'status' => FlowExecution::STATUS_FAILED,
        'finished_at' => now(),
    ])->save();

    $beforeFlow = $execution->fresh()->updated_at;
    $beforeStep = $step->fresh()->updated_at;

    $context = new DiskFlowContext(
        execution: $execution,
        contextSnapshot: [],
        triggerPayload: [],
        mergedConfig: [],
        connectors: new ConnectorsHelper(null),
        logs: new StepLogCollector,
        stepExecutionId: $step->id,
    );

    expect($context->heartbeat(0))->toBeFalse();

    expect($execution->fresh()->updated_at)->toEqual($beforeFlow)
        ->and($step->fresh()->updated_at)->toEqual($beforeStep);
});
