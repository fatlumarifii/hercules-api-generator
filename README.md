# Hercules API Generator

[![Tests](https://github.com/itslh/hercules-api-generator/actions/workflows/tests.yml/badge.svg)](https://github.com/itslh/hercules-api-generator/actions/workflows/tests.yml)

Automatic Postman collection generator for Laravel microservices with git hooks integration and smart merging.

## Features

- 🚀 **Automatic Generation** - Generates Postman collections from Laravel routes
- 🔄 **Git Hooks Integration** - Auto-updates on route changes via pre-push/post-commit hooks
- 🔗 **Postman API Sync** - Pushes collections directly to Postman Cloud (syncs to desktop app)
- 🧠 **Smart Merging** - Preserves manual changes in Postman when updating
- 📝 **Validation Parsing** - Generates request bodies from FormRequest validation rules
- 🎯 **Configurable** - Extensive configuration options for filtering and customization
- 📦 **Reusable** - Works across multiple Laravel microservices
- 💾 **Local Storage** - Optionally save collections to files with version history

## Quick Start

### 1. Install via Composer

```bash
composer require itslh/hercules-api-generator
```

### 2. Run Interactive Setup

```bash
php artisan api:setup
```

The setup wizard will guide you through:

1. **Choose your API client**
   - Postman (with cloud sync)
   - Other (Insomnia, Bruno, HTTPie - file export)

2. **For Postman users:**
   - Select usage mode (shared team collection or individual)
   - Enter your Postman API key
   - Configure collection ID (optional)
   - Auto-install appropriate git hooks

3. **For other clients:**
   - Select export format (Postman v2.1 or OpenAPI)
   - Choose export path
   - Optional git hooks for auto-generation

**That's it!** Your API collection is generated and will auto-update based on your git workflow.

---

## Detailed Configuration

### Optional Environment Variables

```env
# Optional: Collection ID (for updating existing collection)
POSTMAN_COLLECTION_ID=your-collection-id

# Optional: Workspace ID
POSTMAN_WORKSPACE_ID=your-workspace-id

# Optional: Disable auto-push
POSTMAN_AUTO_PUSH=true

# Optional: Enable/disable git hooks
POSTMAN_GIT_HOOKS_ENABLED=true
```

### Publish Configuration File (Optional)

```bash
php artisan vendor:publish --tag=hercules-api-generator-config
```

This creates `config/hercules-api-generator.php` with all available options.

## Usage

### Interactive Setup (Recommended)

```bash
php artisan api:setup
```

The interactive wizard will ask you questions and configure everything automatically.

### Non-Interactive Mode

For CI/CD or automated workflows, use non-interactive mode:

```bash
php artisan api:setup --non-interactive
```

This uses existing configuration from `.env` without prompting.

## How It Works

### Route Parsing

The package automatically:
1. Scans your `routes/api.php` file
2. Extracts all routes matching your configuration
3. Groups routes by controller or prefix
4. Detects FormRequest classes for each route
5. Parses validation rules from FormRequests
6. Generates example request bodies

### Request Body Generation

For routes with FormRequest validation, the package:
- Analyzes validation rules (required, email, integer, etc.)
- Generates appropriate example values
- Handles nested fields with dot notation
- Supports arrays and complex structures

Example:

```php
// In PagePostRequest.php
public function rules(): array
{
    return [
        'title' => 'required|string|max:255',
        'email' => 'required|email',
        'age' => 'integer|min:18',
        'tags' => 'array',
        'tags.*' => 'string',
    ];
}
```

Generates:

```json
{
    "title": "",
    "email": "user@example.com",
    "age": 18,
    "tags": []
}
```

### Git Hooks Workflow

**Pre-Push Hook** (when you push code):
1. Hook detects if `routes/api.php` changed
2. Generates updated Postman collection
3. Saves locally to `storage/postman`
4. Pushes to Postman Cloud via API (updates shared collection)
5. Postman desktop app automatically syncs

**Post-Merge Hook** (when you pull code):
1. Hook detects if `routes/api.php` changed in pulled commits
2. Generates updated Postman collection from the new routes
3. Saves locally to `storage/postman`
4. Pushes to Postman Cloud via API (syncs your local Postman with latest routes)
5. Postman desktop app automatically syncs

**Installing Multiple Hooks:**
You can install both hooks! When you run `api:setup` multiple times with different `--hook-type`, each hook is installed separately. They work together:
- `pre-push` → syncs YOUR changes when you push
- `post-merge` → syncs OTHERS' changes when you pull

### Smart Merging

When enabled, the package:
- Downloads existing collection from Postman
- Matches requests by name
- Preserves manual changes (descriptions, auth, pre-request scripts, tests)
- Adds new routes
- Updates changed routes (URL, method, body)
- Keeps removed routes (you can manually delete later)

## Configuration

### Route Filtering

```php
// config/hercules-api-generator.php

'routes' => [
    // Only include routes with this prefix
    'prefix' => 'v0.1',

    // Exclude routes matching these patterns
    'exclude' => [
        'sanctum/*',
        'telescope/*',
        'horizon/*',
    ],

    // Only include routes with specific middleware
    'middleware' => [],

    // Group by: 'controller', 'prefix', or 'none'
    'group_by' => 'controller',
],
```

### Collection Customization

```php
'collection' => [
    'name' => env('APP_NAME', 'Laravel') . ' API Collection',
    'description' => 'Auto-generated API collection',
    'base_url' => env('APP_URL', 'http://localhost'),
],
```

### Request Body Options

```php
'request_body' => [
    // Generate example values
    'generate_examples' => true,

    // Only include required fields
    'required_only' => false,

    // Custom example values
    'example_values' => [
        'email' => 'user@example.com',
        'url' => 'https://example.com',
        'uuid' => '123e4567-e89b-12d3-a456-426614174000',
        'date' => '2024-01-01',
        'integer' => 1,
        'string' => '',
    ],
],
```

### Merge Configuration

```php
'merge' => [
    'enabled' => true,

    // Fields to preserve from existing collection
    'preserve_fields' => [
        'request.description',
        'request.headers',
        'request.auth',
        'event', // Pre-request scripts and tests
    ],
],
```

### Git Hooks

```php
'git_hooks' => [
    'enabled' => env('POSTMAN_GIT_HOOKS_ENABLED', true),
    'hook_type' => 'pre-push',
    'auto_push' => env('POSTMAN_AUTO_PUSH', true),

    'watch_files' => [
        'routes/api.php',
        'app/Http/Requests',
    ],
],
```

### File Storage

```php
'file' => [
    'storage_path' => 'storage/postman',
    'filename_pattern' => '{name}.postman_collection.json',
    'keep_history' => true,
    'history_limit' => 10,
],
```

## Workflow Examples

### Workflow 1: Shared Team Collection (Recommended)

**Setup (once):**
```bash
# First team member
php artisan api:setup
# → Select "Postman"
# → Select "Shared Team Collection"
# → Enter API key
# → Gets collection ID (e.g., 12345-abcde)

# Share collection ID with team (add to .env.example)
POSTMAN_COLLECTION_ID=12345-abcde

# Other team members
php artisan api:setup
# → Select "Postman"
# → Select "Shared Team Collection"
# → Enter their own API key
# → Enter the shared collection ID
```

**Daily workflow:**
```bash
# Developer A adds endpoints
vim routes/api.php
git commit -m "Add user endpoints"
git push
# → pre-push hook updates shared collection
# → All teammates see changes in Postman automatically (Postman native sync)

# Developer B just opens Postman
# → Sees the new endpoints automatically (no git pull needed for Postman sync)
```

**How it works:**
```
Developer A                    Shared Postman Collection                Developer B
────────────                   ─────────────────────────                ────────────
1. Edit routes
2. git push
   └─> pre-push hook
       └─> Update collection ────────────> Collection Updated
                                            (Postman syncs to all users automatically)
                                                                         Sees updates
                                                                         in Postman
```

### Workflow 2: Individual Collections

**Setup:**
```bash
php artisan api:setup
# → Select "Postman"
# → Select "Individual Collections"
# → Enter API key
# → Both pre-push AND post-merge hooks installed
```

**Daily workflow:**
```bash
# When YOU add endpoints
vim routes/api.php
git push
# → pre-push hook updates YOUR collection

# When OTHERS add endpoints
git pull
# → post-merge hook detects route changes
# → Updates YOUR collection with latest routes from codebase
```

**How it works:**
```
Developer A                                                               Developer B
────────────                                                              ────────────
1. Edit routes
2. git push                                                               3. git pull
   └─> pre-push hook                                                         └─> post-merge hook
       └─> Update A's collection                                                 └─> Rebuild from routes
                                                                                     └─> Update B's collection

Each developer maintains their own collection with their own changes
```

### Workflow 3: File Export (Insomnia, Bruno, HTTPie)

**Setup:**
```bash
php artisan api:setup
# → Select "Other"
# → Select export format
# → Choose save path
```

**Daily workflow:**
```bash
# Routes change
git push
# → pre-push hook regenerates files in storage/api-collections/

# Import manually into your client
# - Insomnia: File → Import → storage/api-collections/postman-collection.json
# - Bruno: Import Collection → Select file
# - HTTPie: Import → Choose file
```

### CI/CD Integration

Add to your GitLab CI pipeline:

```yaml
update-api-collection:
  stage: deploy
  only:
    changes:
      - routes/api.php
  script:
    - php artisan api:setup --non-interactive
  environment:
    name: production
```

Make sure to set these environment variables in your CI/CD settings:
- `POSTMAN_API_KEY`
- `POSTMAN_COLLECTION_ID` (if using shared collection)
- `API_CLIENT_TYPE` (postman or file-export)

## Advanced Usage

### Custom Example Values

You can customize the example values used in generated request bodies by publishing and editing the config file:

```php
// config/hercules-api-generator.php

'request_body' => [
    'example_values' => [
        'email' => 'developer@mycompany.com',
        'phone' => '+1-555-0123',
        'country_code' => 'US',
        'currency' => 'USD',
    ],
],
```

## Troubleshooting

### Invalid API Key

```bash
# Test API key
php artisan api:setup

# If it fails, verify:
# 1. Key is set in .env
# 2. Key is valid (not revoked)
# 3. You have network access to api.getpostman.com
```

### Collection Not Syncing

1. Check Postman desktop app is running
2. Verify you're logged in to same account as API key
3. Check workspace matches (if using `POSTMAN_WORKSPACE_ID`)
4. Try manually refreshing Postman

### Git Hook Not Working

```bash
# Check if hook is installed
ls -la .git/hooks/pre-push

# Verify hook is executable
chmod +x .git/hooks/pre-push

# Test hook manually
.git/hooks/pre-push

# Check hook output
git push --verbose
```

### Routes Not Appearing

Check your configuration:
- `prefix` setting matches your routes
- Routes not in `exclude` list
- `middleware` filter allows your routes

## Requirements

- PHP 8.0 - 8.4
- Laravel 9.x, 10.x, 11.x, or 12.x
- Git (for hooks)
- Postman account with API key

## Development

### Running Tests

```bash
# Run all tests
composer test

# Run with coverage report
composer test-coverage

# Run specific test suite
vendor/bin/phpunit --testsuite=Unit
vendor/bin/phpunit --testsuite=Feature
```

### Code Formatting

This package uses Laravel Pint for code formatting:

```bash
# Format code automatically
composer format

# Check formatting without making changes
composer format-check
```

**Pre-commit Hook:** Pint runs automatically on git commit.

**CI/CD:** GitHub Actions automatically runs code style checks and tests on every push to main/develop branches.

### Test Structure

- **Unit Tests** (`tests/Unit/`) - Test individual service classes
  - `RouteParserTest` - Route parsing and filtering
  - `ValidationParserTest` - Request validation parsing
  - `PostmanCollectionBuilderTest` - Collection building logic

- **Feature Tests** (`tests/Feature/`) - Test artisan commands
  - `SetupCommandTest` - Interactive setup command

## License

MIT

## Support

For issues and feature requests, please open an issue on GitHub.
