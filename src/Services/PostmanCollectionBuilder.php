<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator\Services;

use Illuminate\Support\Str;

class PostmanCollectionBuilder
{
    public function __construct(
        private RouteParser $routeParser,
        private ValidationParser $validationParser
    ) {}

    /**
     * Build complete Postman collection.
     */
    public function build(): array
    {
        $config = config('hercules-api-generator.collection');
        $groupedRoutes = $this->routeParser->parseRoutes();

        return [
            'info' => $this->buildInfo($config),
            'item' => $this->buildItems($groupedRoutes),
            'variable' => $this->buildVariables($config),
        ];
    }

    /**
     * Build collection info section.
     */
    private function buildInfo(array $config): array
    {
        return [
            'name' => $config['name'],
            'description' => $config['description'],
            'schema' => $config['schema'],
            '_postman_id' => Str::uuid()->toString(),
        ];
    }

    /**
     * Build collection items (folders and requests).
     */
    private function buildItems(array $groupedRoutes): array
    {
        $items = [];

        foreach ($groupedRoutes as $groupName => $routes) {
            $items[] = [
                'name' => $groupName,
                'item' => $this->buildGroupItems($routes),
            ];
        }

        return $items;
    }

    /**
     * Build items for a specific group.
     */
    private function buildGroupItems(array $routes): array
    {
        $items = [];

        foreach ($routes as $route) {
            $items[] = $this->buildRequest($route);
        }

        return $items;
    }

    /**
     * Build individual request item.
     */
    private function buildRequest(array $route): array
    {
        $method = $this->getPrimaryMethod($route['methods']);
        $requestBody = $this->getRequestBody($route, $method);

        return [
            'name' => $this->generateRequestName($route),
            'request' => [
                'method' => $method,
                'header' => $this->buildHeaders($method),
                'url' => $this->buildUrl($route),
                'body' => $requestBody,
            ],
            'response' => [],
        ];
    }

    /**
     * Get primary HTTP method for route.
     */
    private function getPrimaryMethod(array $methods): string
    {
        // Remove HEAD and OPTIONS from methods
        $methods = array_diff($methods, ['HEAD', 'OPTIONS']);

        // Return first remaining method
        return reset($methods) ?: 'GET';
    }

    /**
     * Generate request name from route.
     */
    private function generateRequestName(array $route): string
    {
        if ($route['name']) {
            return Str::title(str_replace('.', ' ', $route['name']));
        }

        // Generate from URI
        $uri = trim($route['uri'], '/');
        $uri = preg_replace('/\{[^}]+\}/', '', $uri); // Remove parameters
        $uri = trim($uri, '/');

        return Str::title(str_replace(['/', '-', '_'], ' ', $uri)) ?: 'Request';
    }

    /**
     * Build request headers.
     */
    private function buildHeaders(string $method): array
    {
        $headers = [
            [
                'key' => 'Accept',
                'value' => 'application/json',
                'type' => 'text',
            ],
        ];

        // Add Content-Type for methods that typically send body
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $headers[] = [
                'key' => 'Content-Type',
                'value' => 'application/json',
                'type' => 'text',
            ];
        }

        return $headers;
    }

    /**
     * Build request URL.
     */
    private function buildUrl(array $route): array
    {
        $uri = $route['uri'];
        $pathSegments = [];
        $variables = [];

        // Split URI into segments
        foreach (explode('/', $uri) as $segment) {
            if (empty($segment)) {
                continue;
            }

            // Check if segment is a parameter
            if (preg_match('/\{([^}]+)\}/', $segment, $matches)) {
                $paramName = rtrim($matches[1], '?');
                $pathSegments[] = ':'.$paramName;

                // Add to variables
                $variables[] = [
                    'key' => $paramName,
                    'value' => $this->getParameterExample($paramName),
                    'description' => '',
                ];
            } else {
                $pathSegments[] = $segment;
            }
        }

        return [
            'raw' => '{{base_url}}/'.implode('/', $pathSegments),
            'host' => ['{{base_url}}'],
            'path' => $pathSegments,
            'variable' => $variables,
        ];
    }

    /**
     * Get example value for route parameter.
     */
    private function getParameterExample(string $paramName): string
    {
        // Generate contextual examples based on parameter name
        if (Str::contains($paramName, 'id')) {
            return '1';
        }

        if (Str::contains($paramName, 'uuid')) {
            return '123e4567-e89b-12d3-a456-426614174000';
        }

        if (Str::contains($paramName, 'slug')) {
            return 'example-slug';
        }

        return 'value';
    }

    /**
     * Get request body for route.
     */
    private function getRequestBody(array $route, string $method): ?array
    {
        // Only add body for methods that typically send data
        if (! in_array($method, ['POST', 'PUT', 'PATCH'])) {
            return null;
        }

        // Try to get FormRequest class
        $requestClass = $this->routeParser->getRequestClass($route);

        if (! $requestClass) {
            return [
                'mode' => 'raw',
                'raw' => '{}',
                'options' => [
                    'raw' => [
                        'language' => 'json',
                    ],
                ],
            ];
        }

        // Parse validation rules and generate body
        $fields = $this->validationParser->parseValidationRules($requestClass);
        $bodyData = $this->validationParser->generateRequestBody($fields);

        return [
            'mode' => 'raw',
            'raw' => json_encode($bodyData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'options' => [
                'raw' => [
                    'language' => 'json',
                ],
            ],
        ];
    }

    /**
     * Build collection variables.
     */
    private function buildVariables(array $config): array
    {
        return [
            [
                'key' => 'base_url',
                'value' => $config['base_url'],
                'type' => 'string',
            ],
        ];
    }

    /**
     * Convert collection to JSON string.
     */
    public function toJson(array $collection): string
    {
        return json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Save collection to file.
     */
    public function saveToFile(array $collection, ?string $filename = null): string
    {
        $config = config('hercules-api-generator.file');
        $storagePath = base_path($config['storage_path']);

        // Create directory if it doesn't exist
        if (! is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        // Generate filename
        if (! $filename) {
            $pattern = $config['filename_pattern'];
            $filename = str_replace(
                ['{name}', '{date}', '{time}'],
                [
                    Str::slug($collection['info']['name']),
                    date('Y-m-d'),
                    date('His'),
                ],
                $pattern
            );
        }

        $filepath = $storagePath.'/'.$filename;

        // Save collection
        file_put_contents($filepath, $this->toJson($collection));

        // Handle history
        if ($config['keep_history']) {
            $this->manageHistory($storagePath, $filename, $config['history_limit']);
        }

        return $filepath;
    }

    /**
     * Manage collection history by keeping only the specified number of versions.
     */
    private function manageHistory(string $storagePath, string $currentFilename, int $limit): void
    {
        // Create history directory
        $historyPath = $storagePath.'/history';
        if (! is_dir($historyPath)) {
            mkdir($historyPath, 0755, true);
        }

        // Copy current file to history with timestamp
        $timestamp = date('Y-m-d_His');
        $historyFilename = pathinfo($currentFilename, PATHINFO_FILENAME)."_{$timestamp}.json";
        copy($storagePath.'/'.$currentFilename, $historyPath.'/'.$historyFilename);

        // Clean up old history files
        $historyFiles = glob($historyPath.'/*.json');
        if (count($historyFiles) > $limit) {
            // Sort by modification time
            usort($historyFiles, fn ($a, $b) => filemtime($a) <=> filemtime($b));

            // Remove oldest files
            $filesToRemove = array_slice($historyFiles, 0, count($historyFiles) - $limit);
            foreach ($filesToRemove as $file) {
                unlink($file);
            }
        }
    }
}
