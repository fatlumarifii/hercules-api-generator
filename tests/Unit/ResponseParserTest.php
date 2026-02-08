<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator\Tests\Unit;

use Hercules\ApiGenerator\Services\ResponseParser;
use Hercules\ApiGenerator\Tests\Fixtures\StubController;
use Hercules\ApiGenerator\Tests\TestCase;

class ResponseParserTest extends TestCase
{
    /** @test */
    public function it_returns_empty_array_for_non_existent_class()
    {
        $parser = new ResponseParser;

        $result = $parser->parseResponses('NonExistentClass', 'show');

        $this->assertSame([], $result);
    }

    /** @test */
    public function it_returns_empty_array_for_non_existent_method()
    {
        $parser = new ResponseParser;

        $result = $parser->parseResponses(StubController::class, 'nonExistentMethod');

        $this->assertSame([], $result);
    }

    /** @test */
    public function it_returns_empty_array_for_method_without_docblock()
    {
        $parser = new ResponseParser;

        $result = $parser->parseResponses(StubController::class, 'noDocBlock');

        $this->assertSame([], $result);
    }

    /** @test */
    public function it_returns_empty_array_for_method_without_response_annotations()
    {
        $parser = new ResponseParser;

        $result = $parser->parseResponses(StubController::class, 'noResponses');

        $this->assertSame([], $result);
    }

    /** @test */
    public function it_parses_single_response_annotation()
    {
        $parser = new ResponseParser;

        $result = $parser->parseResponses(StubController::class, 'show');

        $this->assertCount(1, $result);
        $this->assertSame(200, $result[0]['status']);
        $this->assertSame('Successful response', $result[0]['description']);
        $this->assertSame(1, $result[0]['content']['id']);
        $this->assertSame('Example', $result[0]['content']['name']);
        $this->assertSame('user@example.com', $result[0]['content']['email']);
    }

    /** @test */
    public function it_parses_multiple_response_annotations()
    {
        $parser = new ResponseParser;

        $result = $parser->parseResponses(StubController::class, 'store');

        $this->assertCount(2, $result);
        $this->assertSame(201, $result[0]['status']);
        $this->assertSame('Resource created successfully', $result[0]['description']);
        $this->assertSame(422, $result[1]['status']);
        $this->assertSame('Validation error', $result[1]['description']);
    }

    /** @test */
    public function it_parses_array_response()
    {
        $parser = new ResponseParser;

        $result = $parser->parseResponses(StubController::class, 'index');

        $this->assertCount(1, $result);
        $this->assertSame(200, $result[0]['status']);
        // Array responses decode to an indexed array, which is still an array
        $this->assertIsArray($result[0]['content']);
    }
}
