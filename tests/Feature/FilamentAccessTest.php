<?php

use App\Models\User;
use App\Support\Roles;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate(Roles::ADMIN, 'web');
    Role::findOrCreate(Roles::USER, 'web');
});

it('denies Filament panel access without the admin role', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::USER);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('allows Filament panel access with the admin role', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});
