<?php

namespace rajmundtoth0\AuditDriver\Console;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Illuminate\Console\Command;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverException;
use rajmundtoth0\AuditDriver\Services\ElasticsearchAuditService;
use RuntimeException;

class ElasticsearchSetupCommand extends Command
{
    protected $signature = 'es-audit-log:setup';

    protected $description = 'Ensures connection to Elasticsearch and creates the index.';

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     * @throws RuntimeException
     */
    public function handle(ElasticsearchAuditService $elasticsearchService): void
    {
        $elasticsearchService->createIndex();
        $this->info("Index: {$elasticsearchService->index} created!");
    }
}
