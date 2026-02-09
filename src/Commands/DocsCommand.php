<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator\Commands;

use Hercules\ApiGenerator\Services\DocumentationGenerator;
use Illuminate\Console\Command;

class DocsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'api:docs {--json : Output raw JSON data}';

    /**
     * The console command description.
     */
    protected $description = 'Preview and debug API documentation data';

    /**
     * Execute the console command.
     */
    public function handle(DocumentationGenerator $generator): int
    {
        $data = $generator->generate();

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info($data['title']);
        $this->line('Base URL: '.$data['base_url']);
        $this->newLine();

        // Documentation route status
        $enabled = config('hercules-api-generator.documentation.enabled', false);
        $path = config('hercules-api-generator.documentation.path', 'docs/api');

        if ($enabled) {
            $this->info('Documentation route: enabled at /'.$path);
        } else {
            $this->warn('Documentation route: disabled (set API_DOCS_ENABLED=true to enable)');
        }

        $this->newLine();

        foreach ($data['groups'] as $groupName => $endpoints) {
            $this->line('<comment>'.$groupName.'</comment> ('.count($endpoints).' endpoints)');

            foreach ($endpoints as $endpoint) {
                $this->line('  '.str_pad($endpoint['method'], 7).' '.$endpoint['uri']);
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
