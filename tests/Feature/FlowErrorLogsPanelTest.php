<?php

use App\Filament\Pages\IntegrationFlowsPage;
use App\Livewire\FlowErrorLogsPanel;
use App\Models\FlowExecution;
use App\Models\StepExecution;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate(Roles::ADMIN, 'web');
});

it('lists failed step errors for a flow ref', function () {
    $flowRef = 'demo/group/flow';

    $execution = FlowExecution::query()->create([
        'flow_ref' => $flowRef,
        'integration_key' => 'demo',
        'status' => FlowExecution::STATUS_FAILED,
        'triggered_by_user_id' => null,
        'triggered_by_type' => 'manual',
        'context' => [],
        'trigger_payload' => [],
        'error_message' => 'Parent failed',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    $step = StepExecution::query()->create([
        'flow_execution_id' => $execution->id,
        'flow_step_id' => null,
        'step_class' => 'SomeStep',
        'step_index' => 0,
        'step_type' => StepExecution::STEP_TYPE_INTEGRATION_DISK,
        'input' => ['a' => 1],
        'output' => ['b' => 2],
        'logs' => [],
        'status' => StepExecution::STATUS_FAILED,
        'error_message' => 'Step boom',
        'duration_ms' => 10,
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    Livewire::test(FlowErrorLogsPanel::class, ['flowRef' => $flowRef])
        ->assertSee('#'.$execution->id)
        ->assertSee('Step boom')
        ->call('selectRow', 'step:'.$step->id)
        ->assertSet('selectedRowKey', 'step:'.$step->id);
});

it('shows the full error text on the details tab', function () {
    $flowRef = 'demo/group/flow-details';
    $longError = str_repeat('X', 400);

    $execution = FlowExecution::query()->create([
        'flow_ref' => $flowRef,
        'integration_key' => 'demo',
        'status' => FlowExecution::STATUS_FAILED,
        'triggered_by_user_id' => null,
        'triggered_by_type' => 'manual',
        'context' => [],
        'trigger_payload' => [],
        'error_message' => null,
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    $step = StepExecution::query()->create([
        'flow_execution_id' => $execution->id,
        'flow_step_id' => null,
        'step_class' => 'SomeStep',
        'step_index' => 0,
        'step_type' => StepExecution::STEP_TYPE_INTEGRATION_DISK,
        'input' => [],
        'output' => [],
        'logs' => [],
        'status' => StepExecution::STATUS_FAILED,
        'error_message' => $longError,
        'duration_ms' => 10,
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    Livewire::test(FlowErrorLogsPanel::class, ['flowRef' => $flowRef])
        ->call('selectRow', 'step:'.$step->id)
        ->assertSet('activeStepKey', 'step:'.$step->id)
        ->assertSee($longError);
});

it('deletes all error log rows for the flow', function () {
    $flowRef = 'demo/group/flow-delete-all';

    $execution = FlowExecution::query()->create([
        'flow_ref' => $flowRef,
        'integration_key' => 'demo',
        'status' => FlowExecution::STATUS_FAILED,
        'triggered_by_user_id' => null,
        'triggered_by_type' => 'manual',
        'context' => [],
        'trigger_payload' => [],
        'error_message' => null,
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    $step = StepExecution::query()->create([
        'flow_execution_id' => $execution->id,
        'flow_step_id' => null,
        'step_class' => 'SomeStep',
        'step_index' => 0,
        'step_type' => StepExecution::STEP_TYPE_INTEGRATION_DISK,
        'input' => [],
        'output' => null,
        'logs' => null,
        'status' => StepExecution::STATUS_FAILED,
        'error_message' => 'err',
        'duration_ms' => 1,
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    Livewire::test(FlowErrorLogsPanel::class, ['flowRef' => $flowRef])
        ->call('deleteAllErrorLogs');

    expect(StepExecution::query()->whereKey($step->id)->exists())->toBeFalse();
});

it('deletes a step error row', function () {
    $flowRef = 'demo/group/flow2';

    $execution = FlowExecution::query()->create([
        'flow_ref' => $flowRef,
        'integration_key' => 'demo',
        'status' => FlowExecution::STATUS_FAILED,
        'triggered_by_user_id' => null,
        'triggered_by_type' => 'manual',
        'context' => [],
        'trigger_payload' => [],
        'error_message' => null,
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    $step = StepExecution::query()->create([
        'flow_execution_id' => $execution->id,
        'flow_step_id' => null,
        'step_class' => 'SomeStep',
        'step_index' => 0,
        'step_type' => StepExecution::STEP_TYPE_INTEGRATION_DISK,
        'input' => [],
        'output' => null,
        'logs' => null,
        'status' => StepExecution::STATUS_FAILED,
        'error_message' => 'gone',
        'duration_ms' => 1,
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    $key = 'step:'.$step->id;

    Livewire::test(FlowErrorLogsPanel::class, ['flowRef' => $flowRef])
        ->call('deleteRow', $key);

    expect(StepExecution::query()->whereKey($step->id)->exists())->toBeFalse();
});

it('allows admin to see error logs action on integration flows page', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    Livewire::actingAs($user)
        ->test(IntegrationFlowsPage::class, ['integrationSlug' => 'dummy-json-netsuite'])
        ->assertSee('Error logs');
});
