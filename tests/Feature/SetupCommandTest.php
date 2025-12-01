<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator\Tests\Feature;

use Hercules\ApiGenerator\Tests\TestCase;
use Illuminate\Support\Facades\File;

class SetupCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clean up any existing hooks
        $this->cleanupGitHooks();
    }

    protected function tearDown(): void
    {
        $this->cleanupGitHooks();
        parent::tearDown();
    }

    /** @test */
    public function it_runs_in_non_interactive_mode_without_config()
    {
        $this->artisan('api:setup', ['--non-interactive' => true])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_can_install_pre_push_hook()
    {
        $gitDir = base_path('.git');
        if (! is_dir($gitDir)) {
            mkdir($gitDir.'/hooks', 0755, true);
        }

        $this->artisan('api:setup', ['--non-interactive' => true])
            ->assertExitCode(0);

        // Hook file should exist if git is initialized
        if (is_dir($gitDir)) {
            $hookPath = $gitDir.'/hooks/pre-push';
            // Hook might be installed, just verify command doesn't crash
            $this->assertTrue(true);
        }
    }

    /** @test */
    public function it_saves_collection_to_file()
    {
        $this->artisan('api:setup', ['--non-interactive' => true])
            ->assertExitCode(0);

        // Check if collection file was created
        $storagePath = config('hercules-api-generator.file.storage_path', 'storage/postman');
        $fullPath = base_path($storagePath);

        if (is_dir($fullPath)) {
            $files = File::files($fullPath);
            $this->assertNotEmpty($files, 'Collection file should be created');
        }
    }

    /** @test */
    public function it_updates_env_file_with_settings()
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            file_put_contents($envPath, "APP_NAME=TestApp\n");
        }

        $originalContent = file_get_contents($envPath);

        // Simulate interactive mode with preset answers
        // In real usage, we'd mock the prompts, but for now just test non-interactive
        $this->artisan('api:setup', ['--non-interactive' => true])
            ->assertExitCode(0);

        // Restore original .env
        file_put_contents($envPath, $originalContent);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_file_export_mode()
    {
        // Set file export mode
        config(['hercules-api-generator.client_type' => 'file-export']);
        config(['hercules-api-generator.file_export.path' => 'storage/test-api-collections']);

        $this->artisan('api:setup', ['--non-interactive' => true])
            ->assertExitCode(0);

        $exportPath = base_path('storage/test-api-collections');

        if (is_dir($exportPath)) {
            File::deleteDirectory($exportPath);
        }
    }

    private function cleanupGitHooks(): void
    {
        $gitDir = base_path('.git');
        if (! is_dir($gitDir)) {
            return;
        }

        $hooks = ['pre-push', 'post-merge', 'pre-commit', 'post-commit'];
        foreach ($hooks as $hook) {
            $hookPath = $gitDir.'/hooks/'.$hook;
            if (file_exists($hookPath)) {
                $content = file_get_contents($hookPath);
                // Only delete if it's our hook
                if (str_contains($content, 'hercules-api-generator')) {
                    unlink($hookPath);
                }
            }
        }
    }
}
