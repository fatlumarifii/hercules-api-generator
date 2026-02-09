<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator\Tests\Feature;

use Hercules\ApiGenerator\Tests\TestCase;

class DocsCommandTest extends TestCase
{
    /** @test */
    public function it_runs_successfully()
    {
        $this->artisan('api:docs')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_endpoint_summary()
    {
        $this->artisan('api:docs')
            ->expectsOutputToContain('api/test')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_outputs_json_with_flag()
    {
        $this->artisan('api:docs', ['--json' => true])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_shows_documentation_route_status_when_disabled()
    {
        config()->set('hercules-api-generator.documentation.enabled', false);

        $this->artisan('api:docs')
            ->expectsOutputToContain('disabled')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_shows_documentation_route_status_when_enabled()
    {
        config()->set('hercules-api-generator.documentation.enabled', true);

        $this->artisan('api:docs')
            ->expectsOutputToContain('enabled')
            ->assertExitCode(0);
    }
}
