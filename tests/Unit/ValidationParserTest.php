<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator\Tests\Unit;

use Hercules\ApiGenerator\Services\ValidationParser;
use Hercules\ApiGenerator\Tests\TestCase;

class ValidationParserTest extends TestCase
{
    /** @test */
    public function it_returns_empty_array_for_non_existent_class()
    {
        $parser = new ValidationParser;
        $rules = $parser->parseValidationRules('NonExistentClass');

        $this->assertIsArray($rules);
        $this->assertEmpty($rules);
    }

    /** @test */
    public function it_generates_request_body_from_fields()
    {
        $parser = new ValidationParser;

        $fields = [
            'name' => [
                'name' => 'name',
                'required' => true,
                'type' => 'string',
                'example' => '',
                'rules' => ['required', 'string'],
            ],
            'email' => [
                'name' => 'email',
                'required' => true,
                'type' => 'email',
                'example' => 'user@example.com',
                'rules' => ['required', 'email'],
            ],
            'age' => [
                'name' => 'age',
                'required' => false,
                'type' => 'integer',
                'example' => 1,
                'rules' => ['integer'],
            ],
        ];

        $body = $parser->generateRequestBody($fields);

        $this->assertArrayHasKey('name', $body);
        $this->assertArrayHasKey('email', $body);
        $this->assertArrayHasKey('age', $body);
        $this->assertEquals('user@example.com', $body['email']);
        $this->assertEquals(1, $body['age']);
    }

    /** @test */
    public function it_generates_request_body_with_required_only()
    {
        config()->set('hercules-api-generator.request_body.required_only', true);

        $parser = new ValidationParser;

        $fields = [
            'name' => [
                'name' => 'name',
                'required' => true,
                'type' => 'string',
                'example' => '',
                'rules' => ['required', 'string'],
            ],
            'age' => [
                'name' => 'age',
                'required' => false,
                'type' => 'integer',
                'example' => 1,
                'rules' => ['integer'],
            ],
        ];

        $body = $parser->generateRequestBody($fields);

        $this->assertArrayHasKey('name', $body);
        $this->assertArrayNotHasKey('age', $body);
    }
}
