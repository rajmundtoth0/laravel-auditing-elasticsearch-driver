<?php

namespace rajmundtoth0\AuditDriver\Console;

use rajmundtoth0\AuditDriver\Services\ElasticsearchAuditService;
use Illuminate\Console\Command;

class ElasticsearchSetupCommand extends Command
{
    protected $signature = 'es-audit-log:setup';

    protected $description = 'Ensures connection to Elasticsearch and creates the index.';

    public function handle(ElasticsearchAuditService $elasticsearchService): void
    {
        $elasticsearchService->createIndex();
        $this->info("Index: {$elasticsearchService->index} created!");
    }
}
