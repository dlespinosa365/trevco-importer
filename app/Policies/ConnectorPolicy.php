<?php

namespace App\Policies;

use App\Models\Connector;
use App\Models\User;
use App\Support\Roles;

class ConnectorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Roles::ADMIN);
    }

    public function view(User $user, Connector $connector): bool
    {
        return $user->hasRole(Roles::ADMIN);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Roles::ADMIN);
    }

    public function update(User $user, Connector $connector): bool
    {
        return $user->hasRole(Roles::ADMIN);
    }

    public function delete(User $user, Connector $connector): bool
    {
        return $user->hasRole(Roles::ADMIN);
    }

    public function restore(User $user, Connector $connector): bool
    {
        return $user->hasRole(Roles::ADMIN);
    }

    public function forceDelete(User $user, Connector $connector): bool
    {
        return $user->hasRole(Roles::ADMIN);
    }
}
