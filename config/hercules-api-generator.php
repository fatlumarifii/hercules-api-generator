<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Client Type Configuration
    |--------------------------------------------------------------------------
    |
    | Specify which API client you're using:
    | - 'postman': Full Postman integration with cloud sync
    | - 'file-export': Export files for manual import (Insomnia, Bruno, HTTPie)
    |
    */

    'client_type' => env('API_CLIENT_TYPE', 'postman'),

    /*
    |--------------------------------------------------------------------------
    | Postman API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Postman API integration. The API key should be set
    | in your .env file as POSTMAN_API_KEY. Get your API key from:
    | https://web.postman.co/settings/me/api-keys
    |
    */

    'postman' => [
        // Your Postman API key
        'api_key' => env('POSTMAN_API_KEY'),

        // Usage mode: 'shared' (team uses same collection) or 'individual' (each has own)
        'usage_mode' => env('POSTMAN_USAGE_MODE', 'shared'),

        // Postman workspace ID (optional, uses default workspace if not set)
        'workspace_id' => env('POSTMAN_WORKSPACE_ID'),

        // Collection ID (if updating existing collection)
        'collection_id' => env('POSTMAN_COLLECTION_ID'),

        // Postman API base URL
        'api_base_url' => 'https://api.getpostman.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | File Export Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for file export mode (Insomnia, Bruno, HTTPie, etc.)
    |
    */

    'file_export' => [
        // Export format: 'postman-v2', 'openapi', or 'both'
        'format' => env('API_EXPORT_FORMAT', 'postman-v2'),

        // Where to save exported files
        'path' => env('API_EXPORT_PATH', 'storage/api-collections'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Collection Configuration
    |--------------------------------------------------------------------------
    |
    | Customize the generated Postman collection metadata
    |
    */

    'collection' => [
        // Collection name (uses Laravel app name by default)
        'name' => env('APP_NAME', 'Laravel').' API Collection',

        // Collection description
        'description' => 'Auto-generated API collection for '.env('APP_NAME', 'Laravel'),

        // Base URL for requests
        'base_url' => env('APP_URL', 'http://localhost'),

        // Schema version
        'schema' => 'https://schema.getpostman.com/json/collection/v2.0.0/collection.json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which routes should be included in the collection
    |
    */

    'routes' => [
        // File path to routes file (relative to Laravel base path)
        'file' => 'routes/api.php',

        // Route prefix to include (e.g., 'api' to only include /api/* routes)
        'prefix' => env('API_PREFIX', 'v0.1'),

        // Route middleware to filter by (empty array means include all)
        'middleware' => [],

        // Exclude routes matching these patterns
        'exclude' => [
            'sanctum/*',
            'telescope/*',
            'horizon/*',
        ],

        // Group routes by this criteria: 'prefix', 'controller', or 'none'
        'group_by' => 'controller',
    ],

    /*
    |--------------------------------------------------------------------------
    | Git Hooks Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic collection generation on git events
    |
    */

    'git_hooks' => [
        // Enable/disable automatic generation on git push
        'enabled' => env('POSTMAN_GIT_HOOKS_ENABLED', true),

        // Which hook to use: 'pre-push', 'post-commit', 'pre-commit', or 'post-merge'
        // - pre-push: Updates collection before pushing (recommended for syncing your changes)
        // - post-merge: Updates collection after git pull (recommended for team members to sync incoming changes)
        // - post-commit: Updates collection after each commit
        // - pre-commit: Updates collection before committing
        'hook_type' => 'pre-push',

        // Automatically push to Postman API after generation
        'auto_push' => env('POSTMAN_AUTO_PUSH', true),

        // Files to watch for changes (relative to Laravel base path)
        'watch_files' => [
            'routes/api.php',
            'app/Http/Requests',
        ],

        // Command executor configuration
        // Options: 'auto', 'docker', 'local', or custom command
        'executor' => env('POSTMAN_HOOK_EXECUTOR', 'auto'),

        // Docker container name (used when executor is 'docker' or 'auto')
        // Supports placeholders: {service} = derived from directory name
        'docker_container' => env('POSTMAN_DOCKER_CONTAINER', 'hercules-{service}'),

        // Custom command template (used when executor is set to a custom value)
        // Supports placeholders: {command} = the artisan command to run
        'custom_command' => env('POSTMAN_CUSTOM_COMMAND'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Collection File Configuration
    |--------------------------------------------------------------------------
    |
    | Configure local file storage for generated collections
    |
    */

    'file' => [
        // Directory to store generated collections (relative to Laravel base path)
        'storage_path' => 'storage/postman',

        // Filename pattern (supports placeholders: {name}, {date}, {time})
        'filename_pattern' => '{name}.postman_collection.json',

        // Keep history of generated collections
        'keep_history' => true,

        // Number of historical versions to keep
        'history_limit' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Body Generation
    |--------------------------------------------------------------------------
    |
    | Configure how request bodies are generated from validation rules
    |
    */

    'request_body' => [
        // Generate example values for request fields
        'generate_examples' => true,

        // Example value generators by validation rule
        'example_values' => [
            'email' => 'user@example.com',
            'url' => 'https://example.com',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'date' => '2024-01-01',
            'datetime' => '2024-01-01 12:00:00',
            'boolean' => true,
            'integer' => 1,
            'numeric' => 1.0,
            'string' => '',
            'array' => [],
        ],

        // Include required fields only or all fields
        'required_only' => false,

        // Include field descriptions from validation rules
        'include_descriptions' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Merging Strategy
    |--------------------------------------------------------------------------
    |
    | Configure how manual changes in Postman are preserved
    |
    */

    'merge' => [
        // Enable smart merging (preserves manual changes)
        'enabled' => true,

        // Fields to preserve from existing collection
        'preserve_fields' => [
            'request.description',
            'request.headers',
            'request.auth',
            'event', // Pre-request scripts and tests
        ],

        // Download existing collection before updating
        'download_before_update' => true,
    ],
];
