<?php

namespace App\Policies;

use App\Models\NotificationChannelSetting;
use App\Models\User;
use App\Support\Roles;

class NotificationChannelSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Roles::ADMIN);
    }

    public function view(User $user, NotificationChannelSetting $notificationChannelSetting): bool
    {
        return $user->hasRole(Roles::ADMIN);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, NotificationChannelSetting $notificationChannelSetting): bool
    {
        return $user->hasRole(Roles::ADMIN);
    }

    public function delete(User $user, NotificationChannelSetting $notificationChannelSetting): bool
    {
        return false;
    }

    public function restore(User $user, NotificationChannelSetting $notificationChannelSetting): bool
    {
        return false;
    }

    public function forceDelete(User $user, NotificationChannelSetting $notificationChannelSetting): bool
    {
        return false;
    }
}
