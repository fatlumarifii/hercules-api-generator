<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator\Http\Controllers;

use Hercules\ApiGenerator\Services\DocumentationGenerator;

class DocumentationController
{
    /**
     * Serve the API documentation page.
     */
    public function __invoke(DocumentationGenerator $generator)
    {
        if (! config('hercules-api-generator.documentation.enabled', false)) {
            abort(404);
        }

        $data = $generator->generate();

        return response()->view('hercules-api-generator::documentation', $data);
    }
}
