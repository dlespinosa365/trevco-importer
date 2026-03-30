<?php

use App\Integrations\FlowDefinitionRegistry;
use App\Models\User;
use App\Support\Roles;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate(Roles::ADMIN, 'web');
});

it('lists integrations from disk via FlowDefinitionRegistry', function () {
    $registry = app(FlowDefinitionRegistry::class);
    $names = array_column($registry->allIntegrations(), 'name');

    expect($names)->toContain('Dummy JSON to NetSuite');
});

it('counts groups and flows for the dummy-json-netsuite integration folder', function () {
    $registry = app(FlowDefinitionRegistry::class);
    $structure = $registry->integrationGroupsWithFlowCounts('dummy-json-netsuite');

    expect($structure['flow_count'])->toBe(1)
        ->and($structure['groups'])->toHaveCount(1)
        ->and($structure['groups'][0]['slug'])->toBe('orders-to-netsuite')
        ->and($structure['groups'][0]['flow_count'])->toBe(1);
});

it('shows the integrations overview on the Filament dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Dummy JSON to NetSuite')
        ->assertSee('Integrations')
        ->assertSee('orders-to-netsuite')
        ->assertSee('Total flows: 1');
});
