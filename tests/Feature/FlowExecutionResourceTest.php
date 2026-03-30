<?php

use App\Filament\Resources\FlowExecutions\FlowExecutionResource;
use App\Filament\Resources\FlowExecutions\Pages\ListFlowExecutions;
use App\Models\FlowExecution;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate(Roles::ADMIN, 'web');
});

it('builds flow runs list url with integration filter query', function () {
    $url = FlowExecutionResource::getUrl(parameters: [
        'filters' => [
            'integration_key' => [
                'value' => 'demo',
            ],
        ],
    ]);

    expect($url)->toContain('flow-executions')
        ->and($url)->toContain('filters');
});

it('builds flow runs list url with flow_ref filter query', function () {
    $url = FlowExecutionResource::getUrl(parameters: [
        'filters' => [
            'flow_ref' => [
                'value' => 'dummy-json-netsuite/orders-to-netsuite/sync-orders',
            ],
        ],
    ]);

    expect($url)->toContain('flow-executions')
        ->and($url)->toContain('dummy-json-netsuite%2Forders-to-netsuite%2Fsync-orders');
});

it('allows admin to open flow runs list livewire page', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    Livewire::actingAs($user)
        ->test(ListFlowExecutions::class)
        ->assertSuccessful();
});

it('scopes the flow runs resource to root executions only', function () {
    $parent = FlowExecution::query()->create([
        'flow_ref' => 'demo/default/hello',
        'integration_key' => 'demo',
        'status' => FlowExecution::STATUS_COMPLETED,
        'triggered_by_user_id' => null,
        'triggered_by_type' => 'manual',
        'context' => [],
        'trigger_payload' => [],
        'error_message' => null,
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    FlowExecution::query()->create([
        'flow_ref' => 'demo/default/hello',
        'integration_key' => 'demo',
        'status' => FlowExecution::STATUS_COMPLETED,
        'parent_flow_execution_id' => $parent->id,
        'fan_out_item_reference' => 'child-ref',
        'triggered_by_user_id' => null,
        'triggered_by_type' => 'manual',
        'context' => [],
        'trigger_payload' => [],
        'error_message' => null,
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    expect(FlowExecutionResource::getEloquentQuery()->pluck('id')->all())->toEqual([$parent->id]);
});

it('lists flow executions in the table', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    FlowExecution::query()->create([
        'flow_ref' => 'demo/default/hello',
        'integration_key' => 'demo',
        'status' => FlowExecution::STATUS_COMPLETED,
        'triggered_by_user_id' => null,
        'triggered_by_type' => 'manual',
        'context' => [],
        'trigger_payload' => [],
        'error_message' => null,
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(ListFlowExecutions::class)
        ->assertCanSeeTableRecords(FlowExecution::all());
});

it('shows processing label for running executions', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    FlowExecution::query()->create([
        'flow_ref' => 'demo/default/hello',
        'integration_key' => 'demo',
        'status' => FlowExecution::STATUS_RUNNING,
        'triggered_by_user_id' => null,
        'triggered_by_type' => 'manual',
        'context' => [],
        'trigger_payload' => [],
        'error_message' => null,
        'started_at' => now(),
        'finished_at' => null,
    ]);

    Livewire::actingAs($user)
        ->test(ListFlowExecutions::class)
        ->assertSee('processing');
});
