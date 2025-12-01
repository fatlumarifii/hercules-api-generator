<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator\Services;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

class RouteParser
{
    /**
     * Parse all routes and return structured data.
     */
    public function parseRoutes(): array
    {
        $routes = RouteFacade::getRoutes();
        $parsedRoutes = [];
        $config = config('hercules-api-generator.routes');

        foreach ($routes as $route) {
            if ($this->shouldIncludeRoute($route, $config)) {
                $parsedRoutes[] = $this->parseRoute($route);
            }
        }

        return $this->groupRoutes($parsedRoutes, $config['group_by'] ?? 'controller');
    }

    /**
     * Check if route should be included based on configuration.
     */
    private function shouldIncludeRoute(Route $route, array $config): bool
    {
        $uri = $route->uri();
        $prefix = $config['prefix'] ?? '';

        // Check if route has the required prefix
        if ($prefix && ! Str::startsWith($uri, $prefix)) {
            return false;
        }

        // Check exclusion patterns
        foreach ($config['exclude'] ?? [] as $pattern) {
            if (Str::is($pattern, $uri)) {
                return false;
            }
        }

        // Check middleware filter
        if (! empty($config['middleware'])) {
            $routeMiddleware = $route->gatherMiddleware();
            $hasRequiredMiddleware = false;

            foreach ($config['middleware'] as $middleware) {
                if (in_array($middleware, $routeMiddleware)) {
                    $hasRequiredMiddleware = true;
                    break;
                }
            }

            if (! $hasRequiredMiddleware) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse individual route into structured format.
     */
    private function parseRoute(Route $route): array
    {
        $action = $route->getAction();
        $controller = $action['controller'] ?? null;

        [$controllerClass, $method] = $this->parseController($controller);

        return [
            'uri' => $route->uri(),
            'methods' => $route->methods(),
            'name' => $route->getName(),
            'controller' => $controllerClass,
            'method' => $method,
            'middleware' => $route->gatherMiddleware(),
            'parameters' => $this->extractRouteParameters($route),
            'action' => $action,
        ];
    }

    /**
     * Parse controller string into class and method.
     */
    private function parseController(?string $controller): array
    {
        if (! $controller) {
            return [null, null];
        }

        if (Str::contains($controller, '@')) {
            return explode('@', $controller);
        }

        // Handle invokable controllers
        if (class_exists($controller)) {
            return [$controller, '__invoke'];
        }

        return [$controller, null];
    }

    /**
     * Extract route parameters from URI.
     */
    private function extractRouteParameters(Route $route): array
    {
        $parameters = [];

        preg_match_all('/\{([^}]+)\}/', $route->uri(), $matches);

        if (! empty($matches[1])) {
            foreach ($matches[1] as $parameter) {
                $isOptional = Str::endsWith($parameter, '?');
                $paramName = rtrim($parameter, '?');

                $parameters[] = [
                    'name' => $paramName,
                    'required' => ! $isOptional,
                ];
            }
        }

        return $parameters;
    }

    /**
     * Group routes by specified criteria.
     */
    private function groupRoutes(array $routes, string $groupBy): array
    {
        if ($groupBy === 'none') {
            return ['All Routes' => $routes];
        }

        $grouped = [];

        foreach ($routes as $route) {
            $groupKey = $this->getGroupKey($route, $groupBy);

            if (! isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [];
            }

            $grouped[$groupKey][] = $route;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * Get group key for a route based on grouping criteria.
     */
    private function getGroupKey(array $route, string $groupBy): string
    {
        switch ($groupBy) {
            case 'controller':
                if ($route['controller']) {
                    $parts = explode('\\', $route['controller']);

                    return end($parts);
                }

                return 'Other';

            case 'prefix':
                $uri = $route['uri'];
                $parts = explode('/', $uri);

                return ucfirst($parts[0] ?? 'Root');

            default:
                return 'All Routes';
        }
    }

    /**
     * Get request class for a route if it exists.
     */
    public function getRequestClass(array $route): ?string
    {
        if (! $route['controller'] || ! $route['method']) {
            return null;
        }

        try {
            $reflection = new \ReflectionMethod($route['controller'], $route['method']);
            $parameters = $reflection->getParameters();

            foreach ($parameters as $parameter) {
                $type = $parameter->getType();

                if ($type && ! $type->isBuiltin()) {
                    $className = $type->getName();

                    // Check if this is a FormRequest
                    if (is_subclass_of($className, 'Illuminate\Foundation\Http\FormRequest')) {
                        return $className;
                    }
                }
            }
        } catch (\ReflectionException $e) {
            // Controller method doesn't exist or can't be reflected
            return null;
        }

        return null;
    }
}
