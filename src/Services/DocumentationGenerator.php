<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator\Services;

class DocumentationGenerator
{
    public function __construct(
        private RouteParser $routeParser,
        private ValidationParser $validationParser,
        private ResponseParser $responseParser
    ) {}

    /**
     * Generate documentation data structure.
     */
    public function generate(): array
    {
        $config = config('hercules-api-generator');
        $docConfig = $config['documentation'] ?? [];
        $groupedRoutes = $this->routeParser->parseRoutes();
        $baseUrl = $config['collection']['base_url'] ?? url('/');

        $groups = [];

        foreach ($groupedRoutes as $groupName => $routes) {
            $endpoints = [];

            foreach ($routes as $route) {
                $endpoints[] = $this->buildEndpoint($route, $baseUrl);
            }

            $groups[$groupName] = $endpoints;
        }

        return [
            'title' => $docConfig['title'] ?? config('app.name', 'Laravel').' API Documentation',
            'description' => $docConfig['description'] ?? '',
            'base_url' => $baseUrl,
            'groups' => $groups,
        ];
    }

    /**
     * Build endpoint data from a parsed route.
     */
    private function buildEndpoint(array $route, string $baseUrl): array
    {
        $method = $this->getPrimaryMethod($route['methods']);
        $fields = [];
        $requestBody = [];

        // Parse FormRequest fields if available
        $requestClass = $this->routeParser->getRequestClass($route);
        if ($requestClass) {
            $fields = $this->validationParser->parseValidationRules($requestClass);
            $requestBody = $this->validationParser->generateRequestBody($fields);
        }

        // Parse response annotations
        $responses = [];
        if ($route['controller'] && $route['method']) {
            $responses = $this->responseParser->parseResponses($route['controller'], $route['method']);
        }

        $requiresAuth = $this->requiresAuth($route['middleware']);
        $fullUrl = rtrim($baseUrl, '/').'/'.ltrim($route['uri'], '/');

        return [
            'uri' => $route['uri'],
            'full_url' => $fullUrl,
            'method' => $method,
            'name' => $route['name'] ?? '',
            'parameters' => $route['parameters'],
            'fields' => $fields,
            'request_body' => $requestBody,
            'responses' => $responses,
            'curl' => $this->generateCurlExample($method, $fullUrl, $requestBody, $requiresAuth),
            'requires_auth' => $requiresAuth,
        ];
    }

    /**
     * Get primary HTTP method for route.
     */
    private function getPrimaryMethod(array $methods): string
    {
        $methods = array_diff($methods, ['HEAD', 'OPTIONS']);

        return reset($methods) ?: 'GET';
    }

    /**
     * Check if route requires authentication based on middleware.
     */
    private function requiresAuth(array $middleware): bool
    {
        foreach ($middleware as $mw) {
            if (str_contains((string) $mw, 'auth') || str_contains((string) $mw, 'sanctum')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a curl example for an endpoint.
     */
    private function generateCurlExample(string $method, string $url, array $requestBody, bool $requiresAuth): string
    {
        $parts = ['curl'];

        if ($method !== 'GET') {
            $parts[] = '-X '.$method;
        }

        $parts[] = '"'.$url.'"';
        $parts[] = '-H "Accept: application/json"';

        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $parts[] = '-H "Content-Type: application/json"';
        }

        if ($requiresAuth) {
            $parts[] = '-H "Authorization: Bearer {token}"';
        }

        if (! empty($requestBody) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $json = json_encode($requestBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $parts[] = "-d '".$json."'";
        }

        return implode(" \\\n  ", $parts);
    }
}
