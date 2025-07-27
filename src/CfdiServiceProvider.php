<?php

namespace App\Providers;

use App\Services\CfdiService;
use Illuminate\Support\ServiceProvider;

class CfdiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(CfdiService::class, function ($app) {
            return new CfdiService();
        });

        $this->app->alias(CfdiService::class, 'cfdi');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/cfdi.php' => config_path('cfdi.php'),
        ], 'cfdi-config');
    }
} 