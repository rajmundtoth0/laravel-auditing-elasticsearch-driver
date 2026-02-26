<?php

namespace rajmundtoth0\AuditDriver\Tests\Integration;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Elastic\Transport\Exception\UnknownContentTypeException;
use Exception;
use Illuminate\Support\Facades\Config;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverException;
use rajmundtoth0\AuditDriver\Services\ElasticsearchAuditService;
use rajmundtoth0\AuditDriver\Tests\TestCase;

/**
 * @internal
 */
class ElasticsearchIntegrationTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testIndexModeAgainstRealElasticsearch(): void
    {
        $this->guardIntegrationMode();
        $index = 'audit-it-index-'.bin2hex(random_bytes(6));
        $this->configureForIntegration(index: $index, storageMode: 'index');
        /** @var ElasticsearchAuditService $service */
        $service = resolve(ElasticsearchAuditService::class);

        $service->createIndex();
        $service->indexDocument([
            'auditable_id'   => 1001,
            'auditable_type' => 'integration.user',
            'event'          => 'updated',
        ]);

        $count = $this->waitForCount(
            service: $service,
            query: ['term' => ['auditable_id' => 1001]],
        );

        static::assertGreaterThanOrEqual(1, $count);
        $service->deleteIndex();
    }

    /**
     * @throws Exception
     */
    public function testDataStreamModeAgainstRealElasticsearch(): void
    {
        $this->guardIntegrationMode();
        $dataStream = 'audit-it-stream-'.bin2hex(random_bytes(6));
        $this->configureForIntegration(
            index: $dataStream,
            storageMode: 'data_stream',
            dataStreamTemplateName: $dataStream.'-template',
            dataStreamIndexPattern: $dataStream,
        );
        /** @var ElasticsearchAuditService $service */
        $service = resolve(ElasticsearchAuditService::class);

        $service->createIndex();
        $service->indexDocument([
            'auditable_id'   => 2002,
            'auditable_type' => 'integration.user',
            'event'          => 'created',
        ]);

        $count = $this->waitForCount(
            service: $service,
            query: ['term' => ['auditable_id' => 2002]],
        );

        static::assertGreaterThanOrEqual(1, $count);
        $service->deleteIndex();
    }

    private function guardIntegrationMode(): void
    {
        $rawValue  = getenv('AUDIT_RUN_INTEGRATION_TESTS');
        $shouldRun = false !== $rawValue
            && filter_var($rawValue, FILTER_VALIDATE_BOOL);

        if (!$shouldRun) {
            $this->markTestSkipped('Integration tests are disabled. Set AUDIT_RUN_INTEGRATION_TESTS=true.');
        }
    }

    private function configureForIntegration(
        string $index,
        string $storageMode,
        string $dataStreamTemplateName = '',
        string $dataStreamIndexPattern = '',
    ): void {
        $integrationHost = getenv('AUDIT_INTEGRATION_HOST') ?: 'http://localhost:9200';

        Config::set('audit.drivers.elastic.hosts', [$integrationHost]);
        Config::set('audit.drivers.elastic.useBasicAuth', false);
        Config::set('audit.drivers.elastic.useCaCert', false);
        Config::set('audit.drivers.elastic.index', $index);
        Config::set('audit.drivers.elastic.storageMode', $storageMode);
        Config::set('audit.drivers.elastic.dataStream.templateName', $dataStreamTemplateName);
        Config::set('audit.drivers.elastic.dataStream.indexPattern', $dataStreamIndexPattern);
        Config::set('audit.drivers.elastic.dataStream.templatePriority', 100);
        Config::set('audit.drivers.elastic.dataStream.lifecyclePolicyName', '');
        Config::set('audit.drivers.elastic.dataStream.pipeline', '');
    }

    /**
     * @param array<string, mixed> $query
     *
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     * @throws UnknownContentTypeException
     */
    private function waitForCount(ElasticsearchAuditService $service, array $query): int
    {
        $maxAttempts = 10;
        $sleepMicros = 300_000;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $result = $service->count([
                'index' => $service->getIndexName(),
                'body'  => [
                    'query' => $query,
                ],
            ])->asObject();
            assert(property_exists($result, 'count'));
            $count = $result->count;

            if (is_numeric($count) && (int) $count > 0) {
                return (int) $count;
            }

            usleep($sleepMicros);
        }

        return 0;
    }
}
