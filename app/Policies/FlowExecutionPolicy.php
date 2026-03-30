<?php

namespace App\Policies;

use App\Models\FlowExecution;
use App\Models\User;
use App\Support\Roles;

class FlowExecutionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Roles::ADMIN);
    }

    public function view(User $user, FlowExecution $flowExecution): bool
    {
        return $user->hasRole(Roles::ADMIN);
    }
}
