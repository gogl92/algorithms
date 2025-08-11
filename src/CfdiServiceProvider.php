<?php

namespace Gogl92\CfdiSat;

use Gogl92\CfdiSat\CfdiService;
use Illuminate\Support\ServiceProvider;

class CfdiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/cfdi.php', 'cfdi');

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
            __DIR__ . '/../config/cfdi.php' => config_path('cfdi.php'),
        ], 'cfdi-config');
    }
}
