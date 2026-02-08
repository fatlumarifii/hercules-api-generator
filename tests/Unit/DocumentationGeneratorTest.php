<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator\Tests\Unit;

use Hercules\ApiGenerator\Services\DocumentationGenerator;
use Hercules\ApiGenerator\Services\ResponseParser;
use Hercules\ApiGenerator\Services\RouteParser;
use Hercules\ApiGenerator\Services\ValidationParser;
use Hercules\ApiGenerator\Tests\TestCase;

class DocumentationGeneratorTest extends TestCase
{
    /** @test */
    public function it_generates_documentation_data()
    {
        $generator = new DocumentationGenerator(
            new RouteParser,
            new ValidationParser,
            new ResponseParser
        );

        $data = $generator->generate();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('base_url', $data);
        $this->assertArrayHasKey('groups', $data);
    }

    /** @test */
    public function it_includes_title_and_base_url()
    {
        config()->set('hercules-api-generator.documentation.title', 'Test API Docs');
        config()->set('hercules-api-generator.collection.base_url', 'http://localhost');

        $generator = new DocumentationGenerator(
            new RouteParser,
            new ValidationParser,
            new ResponseParser
        );

        $data = $generator->generate();

        $this->assertSame('Test API Docs', $data['title']);
        $this->assertSame('http://localhost', $data['base_url']);
    }

    /** @test */
    public function it_groups_endpoints()
    {
        $generator = new DocumentationGenerator(
            new RouteParser,
            new ValidationParser,
            new ResponseParser
        );

        $data = $generator->generate();

        $this->assertNotEmpty($data['groups']);

        foreach ($data['groups'] as $groupName => $endpoints) {
            $this->assertIsString($groupName);
            $this->assertIsArray($endpoints);

            foreach ($endpoints as $endpoint) {
                $this->assertArrayHasKey('uri', $endpoint);
                $this->assertArrayHasKey('method', $endpoint);
                $this->assertArrayHasKey('full_url', $endpoint);
                $this->assertArrayHasKey('curl', $endpoint);
                $this->assertArrayHasKey('parameters', $endpoint);
                $this->assertArrayHasKey('fields', $endpoint);
                $this->assertArrayHasKey('responses', $endpoint);
                $this->assertArrayHasKey('requires_auth', $endpoint);
            }
        }
    }

    /** @test */
    public function it_generates_curl_examples()
    {
        $generator = new DocumentationGenerator(
            new RouteParser,
            new ValidationParser,
            new ResponseParser
        );

        $data = $generator->generate();

        foreach ($data['groups'] as $endpoints) {
            foreach ($endpoints as $endpoint) {
                $this->assertStringStartsWith('curl', $endpoint['curl']);
                $this->assertStringContainsString($endpoint['full_url'], $endpoint['curl']);
            }
        }
    }
}
