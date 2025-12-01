<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator;

use Hercules\ApiGenerator\Commands\SetupCommand;
use Illuminate\Support\ServiceProvider;

class HerculesApiGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge package config with application config
        $this->mergeConfigFrom(
            __DIR__.'/../config/hercules-api-generator.php',
            'hercules-api-generator'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish configuration file
        $this->publishes([
            __DIR__.'/../config/hercules-api-generator.php' => config_path('hercules-api-generator.php'),
        ], 'hercules-api-generator-config');

        // Register artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                SetupCommand::class,
            ]);
        }
    }
}
