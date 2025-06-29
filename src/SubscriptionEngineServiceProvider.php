<?php

declare(strict_types=1);

namespace EKvedaras\SubscriptionEngineIlluminate;

use Illuminate\Support\ServiceProvider;

final class SubscriptionEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/subscription_engine.php', 'subscription_engine');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/../config/subscription_engine.php' => config_path('subscription_engine.php')], 'config');
        $this->publishesMigrations([__DIR__ . '/../database/migrations' => database_path('migrations')], 'migrations');
    }
}
