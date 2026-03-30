<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->registerHorizonLongWaitNotifications();
    }

    /**
     * Route Horizon long-wait alerts (see config/horizon.php "waits" and "notifications").
     */
    protected function registerHorizonLongWaitNotifications(): void
    {
        $mail = config('horizon.notifications.mail');
        if (is_string($mail)) {
            $mail = trim($mail);
            if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                Horizon::routeMailNotificationsTo($mail);
            }
        }

        $slackUrl = config('horizon.notifications.slack_webhook');
        if (is_string($slackUrl)) {
            $slackUrl = trim($slackUrl);
            if ($slackUrl !== '') {
                $channel = config('horizon.notifications.slack_channel');
                $channel = is_string($channel) ? trim($channel) : null;
                $channel = ($channel !== null && $channel !== '') ? $channel : null;

                Horizon::routeSlackNotificationsTo($slackUrl, $channel);
            }
        }

        $sms = config('horizon.notifications.sms');
        if (is_string($sms)) {
            $sms = trim($sms);
            if ($sms !== '') {
                Horizon::routeSmsNotificationsTo($sms);
            }
        }
    }

    /**
     * Register the Horizon gate — Filament admins (Spatie role "admin") may view the dashboard.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null): bool {
            if (app()->environment('local')) {
                return true;
            }

            return $user instanceof User && $user->hasRole(Roles::ADMIN);
        });
    }
}
