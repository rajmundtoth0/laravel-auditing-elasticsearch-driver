<?php

namespace rajmundtoth0\AuditDriver\Console;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Illuminate\Console\Command;
use InvalidArgumentException;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverException;
use rajmundtoth0\AuditDriver\Services\ElasticsearchAuditService;
use RuntimeException;

class ElasticsearchSetupCommand extends Command
{
    protected $signature = 'es-audit-log:setup';

    protected $description = 'Ensures connection to Elasticsearch and prepares index storage or data stream template/policy.';

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function handle(ElasticsearchAuditService $elasticsearchService): void
    {
        $elasticsearchService->createIndex();
        $this->info("Storage target: {$elasticsearchService->index} is ready!");
    }
}
