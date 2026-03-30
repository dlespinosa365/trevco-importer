<?php

use App\Jobs\ExecuteIntegrationFlowJob;
use App\Jobs\ExecuteIntegrationStepJob;
use App\Jobs\Middleware\BindIntegrationFlowLogContext;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;

uses(TestCase::class);

it('tags execute integration flow job', function (): void {
    $job = new ExecuteIntegrationFlowJob('my-int/my-group/my-flow', null, 'schedule', [], null, 101);

    expect($job->tags())->toContain('integration:ExecuteIntegrationFlowJob')
        ->and($job->tags())->toContain('flow_ref:my-int/my-group/my-flow')
        ->and($job->tags())->toContain('trigger:schedule')
        ->and($job->tags())->toContain('flow_execution:101');
});

it('omits flow_execution tag when execution id is null', function (): void {
    $job = new ExecuteIntegrationFlowJob('a/b/c', 1, 'manual');

    $hasExecutionTag = collect($job->tags())->contains(fn (string $t): bool => str_starts_with($t, 'flow_execution:'));

    expect($hasExecutionTag)->toBeFalse();
});

it('tags execute integration step job', function (): void {
    $job = new ExecuteIntegrationStepJob(
        55,
        'Integrations\\X\\Groups\\Y\\Flows\\Z\\SampleStep',
        2,
        false,
        'a/b/c',
    );

    expect($job->tags())->toContain('integration:ExecuteIntegrationStepJob')
        ->and($job->tags())->toContain('flow_execution:55')
        ->and($job->tags())->toContain('step:2')
        ->and($job->tags())->toContain('step_class:SampleStep')
        ->and($job->tags())->toContain('flow_ref:a/b/c')
        ->and($job->tags())->not->toContain('fan_out:child');
});

it('tags fan-out child step job', function (): void {
    $job = new ExecuteIntegrationStepJob(1, stdClass::class, 0, true, 'r/e/f');

    expect($job->tags())->toContain('fan_out:child');
});

it('binds log context in middleware and restores after', function (): void {
    Context::add('outer', true);

    $middleware = new BindIntegrationFlowLogContext;
    $job = new ExecuteIntegrationFlowJob('a/b', 1, 'manual', [], null, 9);

    $inner = null;
    $middleware->handle($job, function () use (&$inner): void {
        $inner = Context::all();
    });

    expect($inner['flow_ref'] ?? null)->toBe('a/b')
        ->and($inner['flow_execution_id'] ?? null)->toBe(9)
        ->and($inner['integration_job'] ?? null)->toBe('ExecuteIntegrationFlowJob')
        ->and(Context::get('flow_ref'))->toBeNull()
        ->and(Context::get('outer'))->toBeTrue();
});
