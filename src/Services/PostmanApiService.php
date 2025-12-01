<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator\Services;

use Illuminate\Support\Facades\Http;

class PostmanApiService
{
    private ?string $apiKey;

    private ?string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('hercules-api-generator.postman.api_key');
        $this->baseUrl = config('hercules-api-generator.postman.api_base_url');
    }

    /**
     * Reload configuration (useful after updating .env).
     */
    public function reloadConfig(): void
    {
        $this->apiKey = config('hercules-api-generator.postman.api_key');
        $this->baseUrl = config('hercules-api-generator.postman.api_base_url');
    }

    /**
     * Create a new collection in Postman.
     */
    public function createCollection(array $collection): array
    {
        $workspaceId = config('hercules-api-generator.postman.workspace_id');
        $url = $workspaceId
            ? "{$this->baseUrl}/collections?workspace={$workspaceId}"
            : "{$this->baseUrl}/collections";

        $response = Http::withHeaders([
            'X-Api-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'collection' => $collection,
        ]);

        if ($response->failed()) {
            throw new \Exception(
                "Failed to create collection in Postman: {$response->body()}"
            );
        }

        return $response->json();
    }

    /**
     * Update an existing collection in Postman.
     */
    public function updateCollection(string $collectionId, array $collection): array
    {
        $url = "{$this->baseUrl}/collections/{$collectionId}";

        $response = Http::withHeaders([
            'X-Api-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->put($url, [
            'collection' => $collection,
        ]);

        if ($response->failed()) {
            throw new \Exception(
                "Failed to update collection in Postman: {$response->body()}"
            );
        }

        return $response->json();
    }

    /**
     * Get collection from Postman.
     */
    public function getCollection(string $collectionId): ?array
    {
        $url = "{$this->baseUrl}/collections/{$collectionId}";

        $response = Http::withHeaders([
            'X-Api-Key' => $this->apiKey,
        ])->get($url);

        if ($response->failed()) {
            return null;
        }

        return $response->json('collection');
    }

    /**
     * Delete collection from Postman.
     */
    public function deleteCollection(string $collectionId): bool
    {
        $url = "{$this->baseUrl}/collections/{$collectionId}";

        $response = Http::withHeaders([
            'X-Api-Key' => $this->apiKey,
        ])->delete($url);

        return $response->successful();
    }

    /**
     * List all collections.
     */
    public function listCollections(): array
    {
        $workspaceId = config('hercules-api-generator.postman.workspace_id');
        $url = $workspaceId
            ? "{$this->baseUrl}/collections?workspace={$workspaceId}"
            : "{$this->baseUrl}/collections";

        $response = Http::withHeaders([
            'X-Api-Key' => $this->apiKey,
        ])->get($url);

        if ($response->failed()) {
            throw new \Exception(
                "Failed to list collections from Postman: {$response->body()}"
            );
        }

        return $response->json('collections', []);
    }

    /**
     * Find collection by name.
     */
    public function findCollectionByName(string $name): ?array
    {
        $collections = $this->listCollections();

        foreach ($collections as $collection) {
            if ($collection['name'] === $name) {
                return $collection;
            }
        }

        return null;
    }

    /**
     * Create or update collection (smart sync).
     */
    public function syncCollection(array $collection): array
    {
        $collectionId = config('hercules-api-generator.postman.collection_id');

        // If collection ID is configured, try to update
        if ($collectionId) {
            $existing = $this->getCollection($collectionId);

            if ($existing) {
                return $this->updateCollection($collectionId, $collection);
            }
        }

        // Otherwise, try to find by name
        $collectionName = $collection['info']['name'];
        $existing = $this->findCollectionByName($collectionName);

        if ($existing) {
            return $this->updateCollection($existing['uid'], $collection);
        }

        // Create new collection
        return $this->createCollection($collection);
    }

    /**
     * Merge new collection with existing one.
     */
    public function mergeWithExisting(array $newCollection, array $existingCollection): array
    {
        $mergeConfig = config('hercules-api-generator.merge');

        if (! $mergeConfig['enabled']) {
            return $newCollection;
        }

        $preserveFields = $mergeConfig['preserve_fields'] ?? [];

        // Merge items (folders and requests)
        $merged = $newCollection;
        $merged['item'] = $this->mergeItems(
            $newCollection['item'],
            $existingCollection['item'] ?? [],
            $preserveFields
        );

        return $merged;
    }

    /**
     * Recursively merge collection items.
     */
    private function mergeItems(array $newItems, array $existingItems, array $preserveFields): array
    {
        $merged = [];
        $existingMap = $this->createItemMap($existingItems);

        foreach ($newItems as $newItem) {
            $itemName = $newItem['name'];

            // Find matching existing item
            if (isset($existingMap[$itemName])) {
                $existingItem = $existingMap[$itemName];

                // Merge the items
                $mergedItem = $this->mergeItem($newItem, $existingItem, $preserveFields);
                $merged[] = $mergedItem;

                // Remove from map
                unset($existingMap[$itemName]);
            } else {
                // New item, add as-is
                $merged[] = $newItem;
            }
        }

        // Add remaining existing items that weren't in new collection
        foreach ($existingMap as $remainingItem) {
            $merged[] = $remainingItem;
        }

        return $merged;
    }

    /**
     * Create a map of items by name.
     */
    private function createItemMap(array $items): array
    {
        $map = [];

        foreach ($items as $item) {
            $map[$item['name']] = $item;
        }

        return $map;
    }

    /**
     * Merge individual item preserving specified fields.
     */
    private function mergeItem(array $newItem, array $existingItem, array $preserveFields): array
    {
        $merged = $newItem;

        // If item is a folder, recursively merge its items
        if (isset($newItem['item']) && is_array($newItem['item'])) {
            $merged['item'] = $this->mergeItems(
                $newItem['item'],
                $existingItem['item'] ?? [],
                $preserveFields
            );
        }

        // Preserve specified fields from existing item
        foreach ($preserveFields as $field) {
            $value = $this->getNestedValue($existingItem, $field);
            if ($value !== null) {
                $this->setNestedValue($merged, $field, $value);
            }
        }

        return $merged;
    }

    /**
     * Get nested value using dot notation.
     */
    private function getNestedValue(array $array, string $key)
    {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $segment) {
            if (! is_array($value) || ! isset($value[$segment])) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set nested value using dot notation.
     */
    private function setNestedValue(array &$array, string $key, $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $segment) {
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

    /**
     * Validate API key is configured.
     */
    public function validateApiKey(): bool
    {
        if (! $this->apiKey) {
            return false;
        }

        try {
            $this->listCollections();

            return true;
        } catch (\Exception $e) {
            // Store the error for debugging
            logger()->error('Postman API key validation failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Validate API key and return error message if invalid.
     */
    public function validateApiKeyWithError(): array
    {
        if (! $this->apiKey) {
            return ['valid' => false, 'error' => 'API key is not configured'];
        }

        try {
            $this->listCollections();

            return ['valid' => true, 'error' => null];
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }
}
