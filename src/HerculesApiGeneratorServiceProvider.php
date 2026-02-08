<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator;

use Hercules\ApiGenerator\Commands\DocsCommand;
use Hercules\ApiGenerator\Commands\SetupCommand;
use Hercules\ApiGenerator\Http\Controllers\DocumentationController;
use Illuminate\Support\Facades\Route;
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

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'hercules-api-generator');

        // Publish views
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/hercules-api-generator'),
        ], 'hercules-api-generator-views');

        // Register documentation route
        $this->registerDocumentationRoute();

        // Register artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                SetupCommand::class,
                DocsCommand::class,
            ]);
        }
    }

    /**
     * Register the documentation route.
     */
    private function registerDocumentationRoute(): void
    {
        $config = $this->app['config']->get('hercules-api-generator.documentation', []);
        $path = $config['path'] ?? 'docs/api';
        $middleware = $config['middleware'] ?? ['web'];

        Route::middleware($middleware)
            ->get($path, DocumentationController::class)
            ->name('hercules.api.documentation');
    }
}
