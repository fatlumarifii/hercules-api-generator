<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator\Tests\Unit;

use Hercules\ApiGenerator\Services\RouteParser;
use Hercules\ApiGenerator\Tests\TestCase;

class RouteParserTest extends TestCase
{
    /** @test */
    public function it_can_parse_routes()
    {
        $parser = new RouteParser;
        $routes = $parser->parseRoutes();

        $this->assertIsArray($routes);
        $this->assertNotEmpty($routes);
    }

    /** @test */
    public function it_groups_routes_by_controller()
    {
        config()->set('hercules-api-generator.routes.group_by', 'controller');

        $parser = new RouteParser;
        $routes = $parser->parseRoutes();

        $this->assertIsArray($routes);
        foreach ($routes as $groupName => $groupRoutes) {
            $this->assertIsString($groupName);
            $this->assertIsArray($groupRoutes);
        }
    }

    /** @test */
    public function it_extracts_route_parameters()
    {
        $parser = new RouteParser;
        $routes = $parser->parseRoutes();

        // Find route with parameter
        $routeWithParam = null;
        foreach ($routes as $group) {
            foreach ($group as $route) {
                if (str_contains($route['uri'], '{id}')) {
                    $routeWithParam = $route;
                    break 2;
                }
            }
        }

        $this->assertNotNull($routeWithParam);
        $this->assertNotEmpty($routeWithParam['parameters']);
        $this->assertEquals('id', $routeWithParam['parameters'][0]['name']);
        $this->assertTrue($routeWithParam['parameters'][0]['required']);
    }

    /** @test */
    public function it_filters_routes_by_prefix()
    {
        config()->set('hercules-api-generator.routes.prefix', 'api');

        $parser = new RouteParser;
        $routes = $parser->parseRoutes();

        foreach ($routes as $group) {
            foreach ($group as $route) {
                $this->assertStringStartsWith('api', $route['uri']);
            }
        }
    }

    /** @test */
    public function it_excludes_routes_by_pattern()
    {
        config()->set('hercules-api-generator.routes.exclude', ['api/test/*']);

        $parser = new RouteParser;
        $routes = $parser->parseRoutes();

        // Check that routes matching the exclude pattern are not present
        foreach ($routes as $group) {
            foreach ($group as $route) {
                // Routes like api/test/{id} should be excluded
                $this->assertFalse(
                    \Illuminate\Support\Str::is('api/test/*', $route['uri']),
                    "Route {$route['uri']} should be excluded by pattern api/test/*"
                );
            }
        }
    }
}
