<?php

use App\Integrations\FanOut\FanOutCoordinator;
use App\Models\FlowExecution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

function createRunningParentWithFanOutState(int $expected): FlowExecution
{
    return FlowExecution::query()->create([
        'flow_ref' => 'demo/default/hello',
        'integration_key' => 'demo',
        'status' => FlowExecution::STATUS_RUNNING,
        'triggered_by_user_id' => null,
        'triggered_by_type' => 'manual',
        'context' => [
            '_fan_out' => [
                'expected' => $expected,
                'completed' => 0,
                'succeeded' => 0,
                'failed' => 0,
                'errors' => [],
            ],
        ],
        'trigger_payload' => [],
        'error_message' => null,
        'started_at' => now(),
        'finished_at' => null,
    ]);
}

function createChild(FlowExecution $parent, string $reference): FlowExecution
{
    return FlowExecution::query()->create([
        'flow_ref' => $parent->flow_ref,
        'integration_key' => $parent->integration_key,
        'status' => FlowExecution::STATUS_RUNNING,
        'triggered_by_user_id' => $parent->triggered_by_user_id,
        'triggered_by_type' => $parent->triggered_by_type,
        'parent_flow_execution_id' => $parent->id,
        'fan_out_item_reference' => $reference,
        'context' => [],
        'trigger_payload' => [],
        'error_message' => null,
        'started_at' => now(),
        'finished_at' => null,
    ]);
}

it('aggregates mixed child outcomes into parent partial_completed with errors in context', function () {
    $parent = createRunningParentWithFanOutState(2);
    $childOk = createChild($parent, 'a');
    $childFail = createChild($parent, 'b');

    $coordinator = app(FanOutCoordinator::class);
    $coordinator->recordChildTerminal($childOk->fresh(), true);
    $coordinator->recordChildTerminal($childFail->fresh(), false, 'item b failed');

    $parent->refresh();
    expect($parent->status)->toBe(FlowExecution::STATUS_PARTIAL_COMPLETED)
        ->and($parent->finished_at)->not->toBeNull()
        ->and($parent->context['_fan_out']['succeeded'])->toBe(1)
        ->and($parent->context['_fan_out']['failed'])->toBe(1)
        ->and($parent->context['_fan_out']['errors'])->toHaveCount(1)
        ->and($parent->context['_fan_out']['errors'][0])->toMatchArray([
            'reference' => 'b',
            'message' => 'item b failed',
        ]);

    // Failure notifier returns early when the flow_ref cannot be resolved from disk (demo parent run).
    Notification::assertNothingSent();
});

it('marks parent completed when all fan-out children succeed', function () {
    $parent = createRunningParentWithFanOutState(2);
    $c1 = createChild($parent, 'x');
    $c2 = createChild($parent, 'y');

    $coordinator = app(FanOutCoordinator::class);
    $coordinator->recordChildTerminal($c1->fresh(), true);
    $coordinator->recordChildTerminal($c2->fresh(), true);

    $parent->refresh();
    expect($parent->status)->toBe(FlowExecution::STATUS_COMPLETED)
        ->and($parent->context['_fan_out']['failed'])->toBe(0);

    Notification::assertNothingSent();
});

it('does not double-count a child terminal aggregation', function () {
    $parent = createRunningParentWithFanOutState(1);
    $child = createChild($parent, 'only');

    $coordinator = app(FanOutCoordinator::class);
    $coordinator->recordChildTerminal($child->fresh(), true);
    $coordinator->recordChildTerminal($child->fresh(), true);

    $parent->refresh();
    expect($parent->context['_fan_out']['completed'])->toBe(1)
        ->and($parent->status)->toBe(FlowExecution::STATUS_COMPLETED);
});
