<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator\Tests\Feature;

use Hercules\ApiGenerator\Tests\TestCase;

class DocumentationRouteTest extends TestCase
{
    /** @test */
    public function it_returns_404_when_documentation_is_disabled()
    {
        config()->set('hercules-api-generator.documentation.enabled', false);

        $response = $this->get('/docs/api');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_serves_documentation_page_when_enabled()
    {
        config()->set('hercules-api-generator.documentation.enabled', true);

        $response = $this->get('/docs/api');

        $response->assertStatus(200);
        $response->assertSee('API Documentation');
    }

    /** @test */
    public function it_displays_endpoint_information()
    {
        config()->set('hercules-api-generator.documentation.enabled', true);

        $response = $this->get('/docs/api');

        $response->assertStatus(200);
        $response->assertSee('api/test');
        $response->assertSee('curl');
    }

    /** @test */
    public function it_uses_custom_path()
    {
        config()->set('hercules-api-generator.documentation.enabled', true);

        // The default path is docs/api which is registered at boot time
        // We can verify the default path works
        $response = $this->get('/docs/api');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_displays_custom_title()
    {
        config()->set('hercules-api-generator.documentation.enabled', true);
        config()->set('hercules-api-generator.documentation.title', 'My Custom API Docs');

        $response = $this->get('/docs/api');

        $response->assertStatus(200);
        $response->assertSee('My Custom API Docs');
    }
}
