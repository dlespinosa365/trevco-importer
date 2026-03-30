<?php

use App\Filament\Pages\FlowErrorLogsPage;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate(Roles::ADMIN, 'web');
});

it('renders the flow error logs page for a valid flow ref', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    $flowRef = 'dummy-json-netsuite/orders-to-netsuite/sync-orders';

    $url = FlowErrorLogsPage::getUrl([
        'integrationSlug' => 'dummy-json-netsuite',
        'flow_ref' => $flowRef,
    ]);

    $this->actingAs($user)
        ->get($url)
        ->assertOk()
        ->assertSee('No error logs for this flow yet.', false);
});

it('returns 404 when flow_ref is missing', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    $base = FlowErrorLogsPage::getUrl(['integrationSlug' => 'dummy-json-netsuite']);

    $this->actingAs($user)
        ->get($base)
        ->assertNotFound();
});

it('returns 404 when flow_ref does not belong to the integration', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    $url = FlowErrorLogsPage::getUrl([
        'integrationSlug' => 'dummy-json-netsuite',
        'flow_ref' => 'other-integration/orders-to-netsuite/sync-orders',
    ]);

    $this->actingAs($user)
        ->get($url)
        ->assertNotFound();
});

it('returns 404 when flow_ref is not a known flow definition', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    $url = FlowErrorLogsPage::getUrl([
        'integrationSlug' => 'dummy-json-netsuite',
        'flow_ref' => 'dummy-json-netsuite/fake-group/fake-flow',
    ]);

    $this->actingAs($user)
        ->get($url)
        ->assertNotFound();
});
