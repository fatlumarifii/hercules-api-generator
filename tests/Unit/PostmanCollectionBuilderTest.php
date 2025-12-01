<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator\Tests\Unit;

use Hercules\ApiGenerator\Services\PostmanCollectionBuilder;
use Hercules\ApiGenerator\Services\RouteParser;
use Hercules\ApiGenerator\Services\ValidationParser;
use Hercules\ApiGenerator\Tests\TestCase;

class PostmanCollectionBuilderTest extends TestCase
{
    /** @test */
    public function it_can_build_collection()
    {
        $routeParser = new RouteParser;
        $validationParser = new ValidationParser;
        $builder = new PostmanCollectionBuilder($routeParser, $validationParser);

        $collection = $builder->build();

        $this->assertIsArray($collection);
        $this->assertArrayHasKey('info', $collection);
        $this->assertArrayHasKey('item', $collection);
        $this->assertArrayHasKey('variable', $collection);
    }

    /** @test */
    public function it_includes_collection_info()
    {
        $routeParser = new RouteParser;
        $validationParser = new ValidationParser;
        $builder = new PostmanCollectionBuilder($routeParser, $validationParser);

        $collection = $builder->build();

        $this->assertEquals('Test API Collection', $collection['info']['name']);
        $this->assertArrayHasKey('_postman_id', $collection['info']);
        $this->assertArrayHasKey('schema', $collection['info']);
    }

    /** @test */
    public function it_includes_base_url_variable()
    {
        $routeParser = new RouteParser;
        $validationParser = new ValidationParser;
        $builder = new PostmanCollectionBuilder($routeParser, $validationParser);

        $collection = $builder->build();

        $this->assertNotEmpty($collection['variable']);
        $this->assertEquals('base_url', $collection['variable'][0]['key']);
        $this->assertEquals('http://localhost', $collection['variable'][0]['value']);
    }

    /** @test */
    public function it_converts_collection_to_json()
    {
        $routeParser = new RouteParser;
        $validationParser = new ValidationParser;
        $builder = new PostmanCollectionBuilder($routeParser, $validationParser);

        $collection = $builder->build();
        $json = $builder->toJson($collection);

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertEquals($collection, $decoded);
    }

    /** @test */
    public function it_saves_collection_to_file()
    {
        $routeParser = new RouteParser;
        $validationParser = new ValidationParser;
        $builder = new PostmanCollectionBuilder($routeParser, $validationParser);

        $collection = $builder->build();
        $filepath = $builder->saveToFile($collection, 'test-collection.json');

        $this->assertFileExists($filepath);
        $this->assertJson(file_get_contents($filepath));

        // Cleanup
        unlink($filepath);
    }

    /** @test */
    public function it_groups_routes_into_folders()
    {
        $routeParser = new RouteParser;
        $validationParser = new ValidationParser;
        $builder = new PostmanCollectionBuilder($routeParser, $validationParser);

        $collection = $builder->build();

        $this->assertIsArray($collection['item']);
        $this->assertNotEmpty($collection['item']);

        // Each item should be a folder with name and item array
        foreach ($collection['item'] as $folder) {
            $this->assertArrayHasKey('name', $folder);
            $this->assertArrayHasKey('item', $folder);
        }
    }
}
