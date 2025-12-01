<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator\Tests;

use Hercules\ApiGenerator\HerculesApiGeneratorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            HerculesApiGeneratorServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default configuration
        config()->set('hercules-api-generator.collection.name', 'Test API Collection');
        config()->set('hercules-api-generator.collection.base_url', 'http://localhost');
        config()->set('hercules-api-generator.routes.prefix', 'api');
        config()->set('hercules-api-generator.postman.api_key', 'test-api-key');
    }

    protected function defineDatabaseMigrations()
    {
        // If needed for testing
    }

    protected function defineRoutes($router)
    {
        // Define test routes
        $router->get('api/test', function () {
            return response()->json(['message' => 'test']);
        })->name('test.index');

        $router->post('api/test', function () {
            return response()->json(['message' => 'created']);
        })->name('test.store');

        $router->get('api/test/{id}', function ($id) {
            return response()->json(['id' => $id]);
        })->name('test.show');
    }
}
