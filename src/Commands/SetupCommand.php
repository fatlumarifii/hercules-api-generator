<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator\Commands;

use Hercules\ApiGenerator\Services\PostmanApiService;
use Hercules\ApiGenerator\Services\PostmanCollectionBuilder;
use Illuminate\Console\Command;

class SetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'api:setup
                            {--non-interactive : Run in non-interactive mode (uses existing config)}';

    /**
     * The console command description.
     */
    protected $description = 'Interactive setup: configure API client, generate collection, and install git hooks';

    /**
     * Execute the console command.
     */
    public function handle(PostmanApiService $apiService, PostmanCollectionBuilder $builder): int
    {
        $this->info('🚀 Hercules API Generator Setup');
        $this->newLine();

        // Check if running in non-interactive mode
        if ($this->option('non-interactive')) {
            return $this->runNonInteractive($apiService, $builder);
        }

        // Interactive setup flow
        return $this->runInteractiveSetup($apiService, $builder);
    }

    /**
     * Run interactive setup with user prompts.
     */
    private function runInteractiveSetup(PostmanApiService $apiService, PostmanCollectionBuilder $builder): int
    {
        // Question 1: Which API client?
        $this->info('Step 1: Choose your API client');
        $clientType = $this->choice(
            'Which API client do you use?',
            [
                'postman' => 'Postman (with cloud sync)',
                'file-export' => 'Other (Insomnia, Bruno, HTTPie - we\'ll export files for manual import)',
            ],
            'postman'
        );
        $this->newLine();

        if ($clientType === 'postman') {
            return $this->setupPostman($apiService, $builder);
        } else {
            return $this->setupFileExport($builder);
        }
    }

    /**
     * Setup for Postman with cloud sync.
     */
    private function setupPostman(PostmanApiService $apiService, PostmanCollectionBuilder $builder): int
    {
        // Question 2: Usage mode
        $this->info('Step 2: Collection usage mode');
        $this->line('How will you use this collection?');
        $this->newLine();

        $usageMode = $this->choice(
            'Select usage mode',
            [
                'shared' => 'Shared Team Collection (recommended) - Everyone uses the SAME collection ID',
                'individual' => 'Individual Collections - Each team member has their OWN collection',
            ],
            'shared'
        );
        $this->newLine();

        // Show explanation
        if ($usageMode === 'shared') {
            $this->info('📋 Shared Team Collection Mode:');
            $this->line('  • Everyone on your team uses the same collection ID');
            $this->line('  • Only pre-push hook needed (updates shared collection when you push)');
            $this->line('  • All teammates see updates in Postman automatically');
            $this->newLine();
        } else {
            $this->info('👤 Individual Collections Mode:');
            $this->line('  • Each team member has their own collection');
            $this->line('  • Both hooks needed (pre-push + post-merge) for full sync');
            $this->line('  • When you push → updates your collection');
            $this->line('  • When you pull → syncs your collection with latest routes');
            $this->newLine();
        }

        // Question 3: API Key
        $this->info('Step 3: Postman API Key');
        $apiKey = config('hercules-api-generator.postman.api_key');

        if (! $apiKey) {
            $this->warn('No API key found in .env file');
            $this->info('Get your API key from: https://web.postman.co/settings/me/api-keys');
            $this->newLine();

            $apiKey = $this->ask('Enter your Postman API Key');

            if (! $apiKey) {
                $this->error('API key is required for Postman sync');

                return self::FAILURE;
            }

            // Save to .env
            $this->updateEnvFile('POSTMAN_API_KEY', $apiKey);
            $this->info('✓ API key saved to .env');

            // Reload config
            config(['hercules-api-generator.postman.api_key' => $apiKey]);
            $apiService->reloadConfig();
        } else {
            $this->info('✓ Using existing API key from .env');
        }

        // Validate API key
        $this->info('Validating API key...');
        $validation = $apiService->validateApiKeyWithError();
        if (! $validation['valid']) {
            $this->error('❌ Invalid API key: ' . $validation['error']);
            $this->error('Please check your key and try again.');

            return self::FAILURE;
        }
        $this->info('✓ API key is valid');
        $this->newLine();

        // Save usage mode
        $this->updateEnvFile('POSTMAN_USAGE_MODE', $usageMode);

        // Question 4: Collection ID
        $this->info('Step 4: Collection Configuration');
        $existingCollectionId = config('hercules-api-generator.postman.collection_id');

        $hasCollection = $existingCollectionId
            ? $this->confirm("Found existing collection ID ({$existingCollectionId}). Use it?", true)
            : $this->confirm('Do you have an existing collection ID to use?', false);

        $collectionId = null;
        if ($hasCollection && ! $existingCollectionId) {
            $collectionId = $this->ask('Enter the collection ID');
            if ($collectionId) {
                $this->updateEnvFile('POSTMAN_COLLECTION_ID', $collectionId);
                config(['hercules-api-generator.postman.collection_id' => $collectionId]);
                $this->info('✓ Collection ID saved to .env');
            }
        } elseif ($existingCollectionId) {
            $collectionId = $existingCollectionId;
        }
        $this->newLine();

        // Optional: Workspace ID
        $workspaceId = config('hercules-api-generator.postman.workspace_id');
        if (! $workspaceId && $this->confirm('Do you want to specify a workspace ID?', false)) {
            $workspaceId = $this->ask('Enter Postman Workspace ID');
            if ($workspaceId) {
                $this->updateEnvFile('POSTMAN_WORKSPACE_ID', $workspaceId);
                config(['hercules-api-generator.postman.workspace_id' => $workspaceId]);
                $this->info('✓ Workspace ID saved to .env');
            }
        }
        $this->newLine();

        // Generate and push collection
        $this->info('Step 5: Generating collection...');
        $this->newLine();

        try {
            // Build collection
            $collection = $builder->build();
            $collectionName = $collection['info']['name'];
            $itemCount = $this->countItems($collection['item']);

            $this->info("Collection '{$collectionName}' generated with {$itemCount} requests");

            // Save to file
            $filepath = $builder->saveToFile($collection);
            $this->info("Collection saved to: {$filepath}");

            // Push to Postman
            $this->info('Pushing collection to Postman API...');
            $response = $apiService->syncCollection($collection);

            $collectionId = $response['collection']['id'] ?? $response['collection']['uid'] ?? null;

            $this->newLine();
            $this->info("✓ Collection '{$collectionName}' synced successfully!");

            if ($collectionId && ! config('hercules-api-generator.postman.collection_id')) {
                $this->info("Collection ID: {$collectionId}");
                $this->newLine();
                $this->updateEnvFile('POSTMAN_COLLECTION_ID', $collectionId);
                $this->info('✓ Collection ID saved to .env');

                if ($usageMode === 'shared') {
                    $this->newLine();
                    $this->warn('📢 Share this Collection ID with your team:');
                    $this->line("   POSTMAN_COLLECTION_ID={$collectionId}");
                    $this->line('   (Add it to your .env.example file)');
                }
            }
        } catch (\Exception $e) {
            $this->error('Failed to generate/push collection: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();

        // Step 6: Install git hooks
        $this->info('Step 6: Installing git hooks...');
        $this->newLine();

        if ($usageMode === 'shared') {
            // Install only pre-push hook
            $this->line('Installing pre-push hook (shared collection mode)...');
            $result = $this->installHook('pre-push');

            if ($result !== self::SUCCESS) {
                $this->error('❌ Failed to install git hook');

                return self::FAILURE;
            }
        } else {
            // Install both pre-push and post-merge hooks
            $this->line('Installing pre-push hook...');
            $result = $this->installHook('pre-push');

            if ($result !== self::SUCCESS) {
                $this->error('❌ Failed to install pre-push hook');

                return self::FAILURE;
            }

            $this->newLine();
            $this->line('Installing post-merge hook...');
            $result = $this->installHook('post-merge');

            if ($result !== self::SUCCESS) {
                $this->error('❌ Failed to install post-merge hook');

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->newLine();
        $this->info('🎉 Setup completed successfully!');
        $this->newLine();

        // Show next steps
        $this->showNextSteps($usageMode, $collectionId);

        return self::SUCCESS;
    }

    /**
     * Setup for file export (Insomnia, Bruno, HTTPie, etc.).
     */
    private function setupFileExport(PostmanCollectionBuilder $builder): int
    {
        $this->info('Step 2: Export Configuration');
        $this->newLine();

        // Question: Export format
        $exportFormat = $this->choice(
            'Which export format do you need?',
            [
                'postman-v2' => 'Postman Collection v2.1 (compatible with Insomnia, Bruno, HTTPie)',
                'openapi' => 'OpenAPI 3.0 (for API documentation tools)',
                'both' => 'Both formats',
            ],
            'postman-v2'
        );
        $this->newLine();

        // Question: Export path
        $defaultPath = 'storage/api-collections';
        $exportPath = $this->ask('Where should we save the files?', $defaultPath);
        $this->newLine();

        // Save configuration
        $this->updateEnvFile('API_CLIENT_TYPE', 'file-export');
        $this->updateEnvFile('API_EXPORT_FORMAT', $exportFormat);
        $this->updateEnvFile('API_EXPORT_PATH', $exportPath);

        // Generate collection
        $this->info('Step 3: Generating collection...');
        $this->newLine();

        try {
            // Build collection
            $collection = $builder->build();
            $collectionName = $collection['info']['name'];
            $itemCount = $this->countItems($collection['item']);

            $this->info("Collection '{$collectionName}' generated with {$itemCount} requests");

            // Ensure directory exists
            $fullPath = base_path($exportPath);
            if (! is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
            }

            // Save files based on format
            $savedFiles = [];

            if ($exportFormat === 'postman-v2' || $exportFormat === 'both') {
                $filename = $fullPath.'/postman-collection.json';
                file_put_contents($filename, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $savedFiles[] = $filename;
                $this->info("✓ Postman collection saved to: {$filename}");
            }

            if ($exportFormat === 'openapi' || $exportFormat === 'both') {
                // TODO: Implement OpenAPI export
                $this->warn('OpenAPI export coming soon. For now, use Postman format.');
            }

            $this->newLine();
        } catch (\Exception $e) {
            $this->error('Failed to generate collection: '.$e->getMessage());

            return self::FAILURE;
        }

        // Optional: Install git hooks
        if ($this->confirm('Install git hooks for automatic file generation?', true)) {
            $this->newLine();
            $this->info('Step 4: Installing git hooks...');

            $result = $this->installHook('pre-push');

            if ($result !== self::SUCCESS) {
                $this->error('❌ Failed to install git hook');

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->newLine();
        $this->info('🎉 Setup completed successfully!');
        $this->newLine();

        $this->info('Next steps:');
        $this->line("• Import the collection from: {$exportPath}");
        $this->line('• Files will be auto-generated on git push (if hooks installed)');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Run non-interactive mode using existing configuration.
     */
    private function runNonInteractive(PostmanApiService $apiService, PostmanCollectionBuilder $builder): int
    {
        $clientType = config('hercules-api-generator.client_type', 'postman');

        try {
            // Build collection
            $collection = $builder->build();
            $filepath = $builder->saveToFile($collection);

            if ($clientType === 'postman' && $apiService->validateApiKey()) {
                $apiService->syncCollection($collection);
                $this->info('✓ Collection synced to Postman');
            } else {
                $this->info('✓ Collection saved to file');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Update .env file with a key-value pair.
     */
    private function updateEnvFile(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $this->warn('.env file not found. Skipping .env update.');

            return;
        }

        $envContent = file_get_contents($envPath);
        $pattern = "/^{$key}=.*$/m";
        $replacement = "{$key}={$value}";

        if (preg_match($pattern, $envContent)) {
            // Key exists, update it
            $envContent = preg_replace($pattern, $replacement, $envContent);
        } else {
            // Key doesn't exist, append it
            $envContent .= "\n{$replacement}\n";
        }

        file_put_contents($envPath, $envContent);
    }

    /**
     * Install git hook.
     */
    private function installHook(string $hookType): int
    {
        $gitDir = base_path('.git');

        if (! is_dir($gitDir)) {
            $this->error('Not a git repository. Please initialize git first.');

            return self::FAILURE;
        }

        $hookPath = "{$gitDir}/hooks/{$hookType}";
        $hookScript = $this->generateHookScript($hookType);

        // Check if hook already exists
        if (file_exists($hookPath)) {
            if (! $this->confirm("Hook '{$hookType}' already exists. Overwrite?", true)) {
                $this->info('Skipped.');

                return self::SUCCESS;
            }
        }

        // Write hook script
        file_put_contents($hookPath, $hookScript);
        chmod($hookPath, 0755);

        $this->info("✓ Git hook '{$hookType}' installed successfully!");

        return self::SUCCESS;
    }

    /**
     * Generate hook script based on type.
     */
    private function generateHookScript(string $hookType): string
    {
        $script = <<<'BASH'
#!/bin/bash
#
# hercules-api-generator git hook
# Auto-generated by: php artisan api:setup
#

BASH;

        switch ($hookType) {
            case 'pre-push':
                $script .= <<<'BASH'

# Get list of files that changed
CHANGED_FILES=$(git diff --name-only HEAD origin/$(git rev-parse --abbrev-ref HEAD) 2>/dev/null)

# Check if routes/api.php was modified
if echo "$CHANGED_FILES" | grep -q "routes/api.php"; then
    echo "🔄 Routes changed, updating API collection..."

BASH;
                break;

            case 'post-commit':
                $script .= <<<'BASH'

# Get list of files in last commit
CHANGED_FILES=$(git diff --name-only HEAD~1 HEAD)

# Check if routes/api.php was modified
if echo "$CHANGED_FILES" | grep -q "routes/api.php"; then
    echo "🔄 Routes changed, updating API collection..."

BASH;
                break;

            case 'pre-commit':
                $script .= <<<'BASH'

# Get list of staged files
CHANGED_FILES=$(git diff --name-only --cached)

# Check if routes/api.php was modified
if echo "$CHANGED_FILES" | grep -q "routes/api.php"; then
    echo "🔄 Routes changed, updating API collection..."

BASH;
                break;

            case 'post-merge':
                $script .= <<<'BASH'

# Get list of files changed in the merge
CHANGED_FILES=$(git diff-tree -r --name-only --no-commit-id ORIG_HEAD HEAD)

# Check if routes/api.php was modified
if echo "$CHANGED_FILES" | grep -q "routes/api.php"; then
    echo "🔄 Routes changed after pull/merge, updating API collection..."

BASH;
                break;
        }

        // Add common script body with executor logic
        $executor = config('hercules-api-generator.git_hooks.executor', 'auto');
        $dockerContainer = config('hercules-api-generator.git_hooks.docker_container', 'hercules-{service}');
        $customCommand = config('hercules-api-generator.git_hooks.custom_command');

        $script .= $this->generateExecutorScript($executor, $dockerContainer, $customCommand);

        $script .= <<<'BASH'


    if [ $? -eq 0 ]; then
        echo "✓ API collection updated successfully!"
    else
        echo "⚠ Failed to update API collection"
        # Don't block the git operation
    fi
fi

exit 0

BASH;

        return $script;
    }

    /**
     * Generate executor script based on configuration.
     */
    private function generateExecutorScript(string $executor, string $dockerContainer, ?string $customCommand): string
    {
        $command = 'api:setup --non-interactive';

        switch ($executor) {
            case 'local':
                return <<<BASH

    # Generate and push collection (local PHP)
    php artisan {$command}

BASH;

            case 'docker':
                $containerTemplate = $dockerContainer;

                return <<<BASH

    # Generate and push collection (Docker)
    DIR_NAME=\$(basename "\$PWD" | tr '[:upper:]' '[:lower:]' | sed 's/\.srv$//')
    CONTAINER="{$containerTemplate}"
    CONTAINER=\${CONTAINER//\{service\}/\$DIR_NAME}

    if docker ps --format '{{.Names}}' | grep -q "^\${CONTAINER}\$"; then
        docker exec "\$CONTAINER" php artisan {$command}
    else
        echo "⚠ Container '\$CONTAINER' not found or not running"
        exit 0
    fi

BASH;

            case 'auto':
            default:
                $containerTemplate = $dockerContainer;

                return <<<BASH

    # Generate and push collection (auto-detect)
    if [ -f "/.dockerenv" ] || grep -q docker /proc/1/cgroup 2>/dev/null; then
        # Inside container
        php artisan {$command}
    elif command -v docker &> /dev/null; then
        # On host with Docker available
        DIR_NAME=\$(basename "\$PWD" | tr '[:upper:]' '[:lower:]' | sed 's/\.srv$//')
        CONTAINER="{$containerTemplate}"
        CONTAINER=\${CONTAINER//\{service\}/\$DIR_NAME}

        if docker ps --format '{{.Names}}' | grep -q "^\${CONTAINER}\$"; then
            docker exec "\$CONTAINER" php artisan {$command}
        else
            echo "⚠ Container '\$CONTAINER' not found. Trying local PHP..."
            php artisan {$command} 2>/dev/null || echo "⚠ Failed to run command"
            exit 0
        fi
    else
        # Fallback to local PHP
        php artisan {$command}
    fi

BASH;
        }

        // Custom command
        if ($customCommand) {
            $finalCommand = str_replace('{command}', "artisan {$command}", $customCommand);

            return <<<BASH

    # Generate and push collection (custom command)
    {$finalCommand}

BASH;
        }

        return '';
    }

    /**
     * Count total number of requests in collection.
     */
    private function countItems(array $items): int
    {
        $count = 0;

        foreach ($items as $item) {
            if (isset($item['item']) && is_array($item['item'])) {
                // This is a folder, count recursively
                $count += $this->countItems($item['item']);
            } else {
                // This is a request
                $count++;
            }
        }

        return $count;
    }

    /**
     * Show next steps after setup.
     */
    private function showNextSteps(string $usageMode, ?string $collectionId): void
    {
        $this->info('Next steps:');
        $this->line('');

        if ($usageMode === 'shared') {
            $this->line('📋 Shared Collection Mode:');
            $this->line('  • View your collection in Postman desktop app');
            if ($collectionId) {
                $this->line("  • Share this collection ID with your team: {$collectionId}");
                $this->line('  • Each teammate should run: php artisan api:setup');
            }
            $this->line('  • Make changes to routes/api.php and push');
            $this->line('  • pre-push hook will auto-update the shared collection');
            $this->line('  • All teammates will see updates in their Postman automatically');
        } else {
            $this->line('👤 Individual Collection Mode:');
            $this->line('  • View your collection in Postman desktop app');
            $this->line('  • When YOU push changes → your collection updates');
            $this->line('  • When you PULL changes → your collection syncs with latest routes');
            $this->line('  • Both pre-push and post-merge hooks are active');
        }

        $this->newLine();
    }
}
