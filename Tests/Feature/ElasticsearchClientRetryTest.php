<?php

namespace Tests\Feature;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Exception;
use Illuminate\Support\Facades\Config;
use rajmundtoth0\AuditDriver\Client\ElasticsearchClient;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverException;
use rajmundtoth0\AuditDriver\Models\DocumentModel;
use rajmundtoth0\AuditDriver\Tests\TestCase;
use Ramsey\Uuid\Uuid;

/**
 * @internal
 */
class ElasticsearchClientRetryTest extends TestCase
{
    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws Exception
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function testIndexRetriesAndSucceedsAfterTransientFailure(): void
    {
        $client   = $this->getClientWithStatuses([429, 200]);
        $document = $this->getDocument();

        $result = $client->index($document, true, false);

        static::assertTrue($result);
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws Exception
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function testIndexThrowsWhenRetriesAreExhausted(): void
    {
        $this->expectException(ServerResponseException::class);
        Config::set('audit.drivers.elastic.singleWriteRetry.maxAttempts', 2);
        $client   = $this->getClientWithStatuses([503, 503]);
        $document = $this->getDocument();

        $client->index($document, true, false);
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws Exception
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function testIndexTreatsDataStreamCreateConflictAsSuccess(): void
    {
        $client   = $this->getClientWithStatuses([429, 409]);
        $document = $this->getDocument();

        $result = $client->index($document, true, true);

        static::assertTrue($result);
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws Exception
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function testIndexWithoutRetryFailsImmediately(): void
    {
        $this->expectException(ClientResponseException::class);
        Config::set('audit.drivers.elastic.singleWriteRetry.enabled', false);
        $client   = $this->getClientWithStatuses([429]);
        $document = $this->getDocument();

        $client->index($document, true, false);
    }

    /**
     * @param array<int, int> $statuses
     *
     * @throws Exception
     */
    private function getClientWithStatuses(array $statuses): ElasticsearchClient
    {
        $client = resolve(ElasticsearchClient::class);

        return $client->setClient($this->getMockedElasticClient($statuses, []));
    }

    private function getDocument(): DocumentModel
    {
        return new DocumentModel(
            index: 'mocked',
            id: Uuid::uuid4(),
            body: $this->getUser()->toArray(),
        );
    }
}
