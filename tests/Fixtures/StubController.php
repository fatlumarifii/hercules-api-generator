<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator\Tests\Fixtures;

class StubController
{
    /**
     * Show a resource.
     *
     * @response 200 {"id": 1, "name": "Example", "email": "user@example.com"}
     *
     * @responseDescription 200 Successful response
     */
    public function show(int $id)
    {
        return ['id' => $id];
    }

    /**
     * Store a new resource.
     *
     * @response 201 {"id": 1, "name": "New Resource"}
     * @response 422 {"message": "Validation failed", "errors": {"name": ["The name field is required."]}}
     *
     * @responseDescription 201 Resource created successfully
     * @responseDescription 422 Validation error
     */
    public function store()
    {
        return ['id' => 1];
    }

    /**
     * List resources.
     *
     * @response 200 [{"id": 1, "name": "First"}, {"id": 2, "name": "Second"}]
     */
    public function index()
    {
        return [];
    }

    /**
     * Method without response annotations.
     */
    public function noResponses()
    {
        return [];
    }

    public function noDocBlock()
    {
        return [];
    }
}
