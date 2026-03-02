<?php

namespace rajmundtoth0\AuditDriver\Tests\Feature;

use Elastic\Elasticsearch\Response\Elasticsearch;
use Exception;
use Illuminate\Support\Facades\Config;
use rajmundtoth0\AuditDriver\Client\ElasticsearchClient;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverException;
use rajmundtoth0\AuditDriver\Tests\TestCase;

/**
 * @internal
 */
class ElasticsearchClientOperationsTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testIndexOperationsReturnExpectedResultTypes(): void
    {
        $client = resolve(ElasticsearchClient::class)
            ->setClient($this->getMockedElasticClient([200, 200, 200, 200], []));

        $createIndexResult = $client->createIndex(
            index: 'mocked',
            settings: ['number_of_shards' => 1],
            mappings: ['properties' => ['event' => ['type' => 'keyword']]],
        );
        $updateAliasesResult = $client->updateAliases('mocked');
        $indexExists         = $client->isIndexExists('mocked');
        $deleteIndex         = $client->deleteIndex('mocked');

        static::assertInstanceOf(Elasticsearch::class, $createIndexResult);
        static::assertInstanceOf(Elasticsearch::class, $updateAliasesResult);
        static::assertTrue($indexExists);
        static::assertTrue($deleteIndex);
    }

    /**
     * @throws Exception
     */
    public function testDataStreamOperationsReturnExpectedResultTypes(): void
    {
        $client = resolve(ElasticsearchClient::class)
            ->setClient($this->getMockedElasticClient([200, 200, 200], []));

        $lifecycleResult = $client->putLifecyclePolicy([
            'policy' => 'mocked-policy',
            'body'   => [
                'policy' => [
                    'phases' => [
                        'hot' => [
                            'actions' => new \stdClass(),
                        ],
                    ],
                ],
            ],
        ]);
        $templateResult = $client->createDataStreamTemplate(
            templateName: 'mocked-template',
            indexPattern: 'mocked*',
            templatePriority: 200,
            settings: ['number_of_shards' => 1],
            mappings: ['properties' => ['event' => ['type' => 'keyword']]],
            lifecyclePolicyName: 'mocked-policy',
            pipeline: 'mocked-pipeline',
        );
        $deleteResult = $client->deleteDataStream('mocked');

        static::assertInstanceOf(Elasticsearch::class, $lifecycleResult);
        static::assertInstanceOf(Elasticsearch::class, $templateResult);
        static::assertTrue($deleteResult);
    }

    /**
     * @throws Exception
     */
    public function testDeleteDocumentReturnsFalseWhenResultIsNotRequested(): void
    {
        $client = resolve(ElasticsearchClient::class)
            ->setClient($this->getMockedElasticClient([200], []));

        $result = $client->deleteDocument(
            index: 'mocked',
            id: 'doc-1',
            shouldReturnResult: false,
        );

        static::assertFalse($result);
    }

    /**
     * @throws Exception
     */
    public function testSetClientSupportsBasicAuthConfiguration(): void
    {
        Config::set('audit.drivers.elastic.useBasicAuth', true);
        Config::set('audit.drivers.elastic.userName', 'elastic');
        Config::set('audit.drivers.elastic.password', 'secret');

        $client = resolve(ElasticsearchClient::class)
            ->setClient($this->getMockedElasticClient([200], []));

        static::assertFalse($client->isAsync());
    }

    /**
     * @throws AuditDriverException
     * @throws Exception
     */
    public function testSearchThrowsWhenClientIsAsync(): void
    {
        $this->expectException(AuditDriverException::class);
        $this->expectExceptionMessage('Async handler is not implemented!');

        $asyncClient = $this->getMockedElasticClient([200], []);
        $asyncClient->setAsync(true);
        $client = resolve(ElasticsearchClient::class)
            ->setClient($asyncClient);

        $client->search(['index' => 'mocked']);
    }

    /**
     * @throws AuditDriverException
     * @throws Exception
     */
    public function testCountThrowsWhenClientIsAsync(): void
    {
        $this->expectException(AuditDriverException::class);
        $this->expectExceptionMessage('Async handler is not implemented!');

        $asyncClient = $this->getMockedElasticClient([200], []);
        $asyncClient->setAsync(true);
        $client = resolve(ElasticsearchClient::class)
            ->setClient($asyncClient);

        $client->count(['index' => 'mocked']);
    }

    /**
     * @throws AuditDriverException
     * @throws Exception
     */
    public function testDeleteDocumentThrowsWhenClientIsAsyncAndResultIsRequested(): void
    {
        $this->expectException(AuditDriverException::class);
        $this->expectExceptionMessage('Async handler is not implemented!');

        $asyncClient = $this->getMockedElasticClient([200], []);
        $asyncClient->setAsync(true);
        $client = resolve(ElasticsearchClient::class)
            ->setClient($asyncClient);

        $client->deleteDocument(
            index: 'mocked',
            id: 'doc-1',
            shouldReturnResult: true,
        );
    }
}
