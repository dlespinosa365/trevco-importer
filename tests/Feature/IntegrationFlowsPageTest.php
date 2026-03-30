<?php

use App\Filament\Pages\AllFlowsPage;
use App\Filament\Pages\IntegrationFlowsPage;
use App\Jobs\ExecuteIntegrationFlowJob;
use App\Models\FlowExecution;
use App\Models\FlowSchedule;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate(Roles::ADMIN, 'web');
});

it('shows all flows page with integration column and filters', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    Livewire::actingAs($user)
        ->test(AllFlowsPage::class)
        ->assertSee('Dummy JSON to NetSuite')
        ->assertSee('sync-orders')
        ->assertSee('orders-to-netsuite')
        ->assertSee('Notification channels');
});

it('shows flows list for an integration', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    Livewire::actingAs($user)
        ->test(IntegrationFlowsPage::class, ['integrationSlug' => 'dummy-json-netsuite'])
        ->assertSee('sync-orders')
        ->assertSee('orders-to-netsuite')
        ->assertSee('Notification channels')
        ->assertSee('Change schedule')
        ->assertSee('View runs')
        ->assertSee('Run now');
});

it('shows schedule summary and next run in flows table', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    FlowSchedule::query()->create([
        'flow_ref' => 'dummy-json-netsuite/orders-to-netsuite/sync-orders',
        'is_active' => true,
        'timezone' => 'UTC',
        'schedule_type' => FlowSchedule::TYPE_EVERY_MINUTES,
        'every_minutes' => 15,
        'next_run_at' => now()->addMinutes(15),
    ]);

    Livewire::actingAs($user)
        ->test(IntegrationFlowsPage::class, ['integrationSlug' => 'dummy-json-netsuite'])
        ->assertSee('Every 15 minute(s)')
        ->assertSee('minute');
});

it('dispatches flow execution from run now action', function () {
    Bus::fake();

    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    Livewire::actingAs($user)
        ->test(IntegrationFlowsPage::class, ['integrationSlug' => 'dummy-json-netsuite'])
        ->callTableAction('runNow', 'dummy-json-netsuite/orders-to-netsuite/sync-orders');

    Bus::assertDispatched(ExecuteIntegrationFlowJob::class, function (ExecuteIntegrationFlowJob $job) use ($user): bool {
        return $job->flowRef === 'dummy-json-netsuite/orders-to-netsuite/sync-orders'
            && $job->triggeredByUserId === $user->id
            && $job->triggeredByType === 'manual';
    });

    expect(FlowExecution::query()->where('flow_ref', 'dummy-json-netsuite/orders-to-netsuite/sync-orders')->exists())->toBeTrue();
});

it('shows in queue label when latest run is pending', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    FlowExecution::query()->create([
        'flow_ref' => 'dummy-json-netsuite/orders-to-netsuite/sync-orders',
        'integration_key' => 'dummy-json-netsuite',
        'status' => FlowExecution::STATUS_PENDING,
        'triggered_by_user_id' => $user->id,
        'triggered_by_type' => 'manual',
        'context' => [],
        'trigger_payload' => [],
        'error_message' => null,
        'started_at' => null,
        'finished_at' => null,
    ]);

    Livewire::actingAs($user)
        ->test(IntegrationFlowsPage::class, ['integrationSlug' => 'dummy-json-netsuite'])
        ->assertSee('in queue');
});
