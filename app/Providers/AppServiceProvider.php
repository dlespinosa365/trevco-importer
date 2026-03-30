<?php

namespace App\Providers;

use App\Integrations\FlowDefinitionRegistry;
use App\Support\IntegrationsAutoload;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FlowDefinitionRegistry::class, function () {
            return new FlowDefinitionRegistry(
                (string) config('flows.integrations_path', base_path('integrations')),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        IntegrationsAutoload::register((string) config('flows.integrations_path', base_path('integrations')));
    }
}
