<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator\Services;

use Illuminate\Support\Str;

class ValidationParser
{
    /**
     * Parse validation rules from a FormRequest class.
     */
    public function parseValidationRules(string $requestClass): array
    {
        if (! class_exists($requestClass)) {
            return [];
        }

        try {
            // Try to get rules without instantiating (using reflection)
            $rules = $this->extractRulesFromClass($requestClass);

            return $this->processRules($rules);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Extract rules from FormRequest class without instantiation.
     */
    private function extractRulesFromClass(string $requestClass): array
    {
        try {
            $reflection = new \ReflectionClass($requestClass);

            if (! $reflection->hasMethod('rules')) {
                return [];
            }

            $rulesMethod = $reflection->getMethod('rules');

            // Try to create instance with Laravel's container (handles dependencies)
            try {
                $request = app($requestClass);

                return $this->safelyInvokeRulesMethod($rulesMethod, $request);
            } catch (\TypeError|\Exception $e) {
                // If container fails, try to manually resolve constructor dependencies
                try {
                    $request = $this->createInstanceWithMockedDependencies($reflection);

                    return $this->safelyInvokeRulesMethod($rulesMethod, $request);
                } catch (\TypeError|\Exception $e2) {
                    // Final fallback: return empty (can't parse safely)
                    return [];
                }
            }
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create instance with mocked/empty dependencies.
     */
    private function createInstanceWithMockedDependencies(\ReflectionClass $reflection): object
    {
        $constructor = $reflection->getConstructor();

        if (! $constructor) {
            return $reflection->newInstance();
        }

        $params = [];

        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            if (! $type || $type->isBuiltin()) {
                // For built-in types, use default or null
                $params[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;

                continue;
            }

            $className = $type->getName();

            // Handle Eloquent Models
            if (class_exists($className) && is_subclass_of($className, 'Illuminate\Database\Eloquent\Model')) {
                $params[] = new $className; // Create empty model instance

                continue;
            }

            // Handle other classes via container
            try {
                $params[] = app($className);
            } catch (\Exception $e) {
                // If all else fails, try to create empty instance
                try {
                    $params[] = new $className;
                } catch (\Exception $e2) {
                    $params[] = null;
                }
            }
        }

        return $reflection->newInstanceArgs($params);
    }

    /**
     * Safely invoke rules method and convert rule objects to strings.
     */
    private function safelyInvokeRulesMethod(\ReflectionMethod $method, object $request): array
    {
        try {
            $rules = $method->invoke($request);

            if (! is_array($rules)) {
                return [];
            }

            // Convert any rule objects to string representations
            return $this->normalizeRules($rules);
        } catch (\TypeError|\Exception $e) {
            // If rules() method throws any exception (TypeError, etc), return empty
            // This happens when FormRequests have complex dependencies on routes, models, etc.
            return [];
        }
    }

    /**
     * Normalize rules by converting objects to strings.
     */
    private function normalizeRules(array $rules): array
    {
        $normalized = [];

        foreach ($rules as $field => $fieldRules) {
            if (! is_array($fieldRules)) {
                $normalized[$field] = $fieldRules;

                continue;
            }

            $normalizedFieldRules = [];
            foreach ($fieldRules as $rule) {
                if (is_string($rule)) {
                    $normalizedFieldRules[] = $rule;
                } elseif (is_object($rule)) {
                    // Skip rule objects that can't be instantiated
                    // We'll just ignore them for the API collection
                    continue;
                }
            }

            if (! empty($normalizedFieldRules)) {
                $normalized[$field] = $normalizedFieldRules;
            }
        }

        return $normalized;
    }

    /**
     * Process validation rules and extract field information.
     */
    private function processRules(array $rules): array
    {
        $fields = [];

        foreach ($rules as $field => $rule) {
            // Skip numeric keys (malformed rules)
            if (! is_string($field)) {
                continue;
            }

            $fields[$field] = $this->parseField($field, $rule);
        }

        return $fields;
    }

    /**
     * Parse individual field and its rules.
     */
    private function parseField(string $field, $rule): array
    {
        $rules = is_string($rule) ? explode('|', $rule) : $rule;
        $fieldData = [
            'name' => $field,
            'required' => false,
            'type' => 'string',
            'example' => '',
            'rules' => [],
        ];

        foreach ($rules as $singleRule) {
            if (is_string($singleRule)) {
                $this->parseStringRule($singleRule, $fieldData);
            } elseif (is_object($singleRule)) {
                $this->parseObjectRule($singleRule, $fieldData);
            }
        }

        // Generate example value based on field type and rules
        $fieldData['example'] = $this->generateExampleValue($fieldData);

        return $fieldData;
    }

    /**
     * Parse string-based validation rule.
     */
    private function parseStringRule(string $rule, array &$fieldData): void
    {
        $fieldData['rules'][] = $rule;

        // Extract rule name and parameters
        [$ruleName, $parameters] = $this->extractRuleNameAndParameters($rule);

        switch ($ruleName) {
            case 'required':
                $fieldData['required'] = true;
                break;

            case 'email':
                $fieldData['type'] = 'email';
                break;

            case 'url':
                $fieldData['type'] = 'url';
                break;

            case 'uuid':
                $fieldData['type'] = 'uuid';
                break;

            case 'date':
            case 'date_format':
                $fieldData['type'] = 'date';
                if ($parameters) {
                    $fieldData['format'] = $parameters[0];
                }
                break;

            case 'integer':
            case 'numeric':
                $fieldData['type'] = $ruleName;
                break;

            case 'boolean':
                $fieldData['type'] = 'boolean';
                break;

            case 'array':
                $fieldData['type'] = 'array';
                break;

            case 'json':
                $fieldData['type'] = 'json';
                break;

            case 'string':
                $fieldData['type'] = 'string';
                break;

            case 'in':
                $fieldData['enum'] = $parameters;
                break;

            case 'min':
                $fieldData['min'] = $parameters[0] ?? null;
                break;

            case 'max':
                $fieldData['max'] = $parameters[0] ?? null;
                break;
        }
    }

    /**
     * Parse object-based validation rule.
     */
    private function parseObjectRule(object $rule, array &$fieldData): void
    {
        $ruleClass = get_class($rule);
        $fieldData['rules'][] = $ruleClass;

        // Handle common rule objects
        if (Str::contains($ruleClass, 'Required')) {
            $fieldData['required'] = true;
        }
    }

    /**
     * Extract rule name and parameters from rule string.
     */
    private function extractRuleNameAndParameters(string $rule): array
    {
        if (! Str::contains($rule, ':')) {
            return [$rule, []];
        }

        [$ruleName, $paramString] = explode(':', $rule, 2);
        $parameters = explode(',', $paramString);

        return [$ruleName, $parameters];
    }

    /**
     * Generate example value based on field data.
     */
    private function generateExampleValue(array $fieldData): mixed
    {
        $config = config('hercules-api-generator.request_body.example_values', []);

        // Check if enum is present
        if (isset($fieldData['enum']) && ! empty($fieldData['enum'])) {
            return $fieldData['enum'][0];
        }

        // Generate based on type
        switch ($fieldData['type']) {
            case 'email':
                return $config['email'] ?? 'user@example.com';

            case 'url':
                return $config['url'] ?? 'https://example.com';

            case 'uuid':
                return $config['uuid'] ?? '123e4567-e89b-12d3-a456-426614174000';

            case 'date':
                if (isset($fieldData['format'])) {
                    try {
                        return now()->format($fieldData['format']);
                    } catch (\Exception $e) {
                        return $config['date'] ?? '2024-01-01';
                    }
                }

                return $config['date'] ?? '2024-01-01';

            case 'boolean':
                return $config['boolean'] ?? true;

            case 'integer':
                $value = $config['integer'] ?? 1;
                if (isset($fieldData['min'])) {
                    $value = max($value, (int) $fieldData['min']);
                }
                if (isset($fieldData['max'])) {
                    $value = min($value, (int) $fieldData['max']);
                }

                return $value;

            case 'numeric':
                $value = $config['numeric'] ?? 1.0;
                if (isset($fieldData['min'])) {
                    $value = max($value, (float) $fieldData['min']);
                }
                if (isset($fieldData['max'])) {
                    $value = min($value, (float) $fieldData['max']);
                }

                return $value;

            case 'array':
                return $config['array'] ?? [];

            case 'json':
                return json_encode($config['array'] ?? []);

            case 'string':
            default:
                return $config['string'] ?? '';
        }
    }

    /**
     * Generate request body structure from parsed fields.
     */
    public function generateRequestBody(array $fields): array
    {
        $body = [];
        $requiredOnly = config('hercules-api-generator.request_body.required_only', false);

        foreach ($fields as $field) {
            if ($requiredOnly && ! $field['required']) {
                continue;
            }

            // Handle nested fields (dot notation)
            if (Str::contains($field['name'], '.')) {
                $this->setNestedValue($body, $field['name'], $field['example']);
            } else {
                $body[$field['name']] = $field['example'];
            }
        }

        return $body;
    }

    /**
     * Set nested value in array using dot notation.
     */
    private function setNestedValue(array &$array, string $key, $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $segment) {
            // Handle array notation (e.g., items.0.name or items.*.name)
            if ($segment === '*' || is_numeric($segment)) {
                $segment = '0'; // Use index 0 for examples
            }

            if ($i === count($keys) - 1) {
                $current[$segment] = $value;
            } else {
                if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }
}
