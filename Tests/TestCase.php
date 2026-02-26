<?php

namespace rajmundtoth0\AuditDriver\Tests;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Exception;
use Http\Mock\Client as MockHttpClient;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Nyholm\Psr7\Response;
use Orchestra\Testbench\TestCase as Orchestra;
use OwenIt\Auditing\Models\Audit;
use PHPUnit\Framework\MockObject\MockObject;
use rajmundtoth0\AuditDriver\Client\ElasticsearchClient;
use rajmundtoth0\AuditDriver\ElasticsearchAuditingServiceProvider;
use rajmundtoth0\AuditDriver\Services\ElasticsearchAuditService;
use rajmundtoth0\AuditDriver\Tests\Model\User;

/**
 * @internal
 */
class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('database.default', 'testing');
        Config::set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        Config::set('audit.drivers.elastic.hosts', ['http://testing:9200']);
        Config::set('audit.drivers.elastic.userName', 'elastic');
        Config::set('audit.drivers.elastic.password', 'mocked');
        Config::set('audit.drivers.elastic.index', 'mocked');
        Config::set('audit.drivers.elastic.storageMode', 'index');
        Config::set('audit.drivers.elastic.definitions.settings.path', __DIR__.'/../resources/elasticsearch/settings.json');
        Config::set('audit.drivers.elastic.definitions.settings.json', '');
        Config::set('audit.drivers.elastic.definitions.mappings.path', __DIR__.'/../resources/elasticsearch/mappings.json');
        Config::set('audit.drivers.elastic.definitions.mappings.json', '');
        Config::set('audit.drivers.elastic.definitions.lifecyclePolicy.path', __DIR__.'/../resources/elasticsearch/lifecycle-policy.json');
        Config::set('audit.drivers.elastic.definitions.lifecyclePolicy.json', '');
        Config::set('audit.drivers.elastic.definitions.singleWriteRetry.path', __DIR__.'/../resources/elasticsearch/single-write-retry.json');
        Config::set(
            'audit.drivers.elastic.definitions.singleWriteRetry.json',
            '{"maxAttempts":3,"initialBackoffMs":0,"maxBackoffMs":0,"backoffMultiplier":2.0,"jitterMs":0}',
        );
        Config::set('audit.drivers.elastic.dataStream.templateName', 'mocked_template');
        Config::set('audit.drivers.elastic.dataStream.indexPattern', 'mocked*');
        Config::set('audit.drivers.elastic.dataStream.templatePriority', 100);
        Config::set('audit.drivers.elastic.dataStream.lifecyclePolicyName', '');
        Config::set('audit.drivers.elastic.dataStream.pipeline', '');
        Config::set('audit.drivers.elastic.singleWriteRetry.enabled', true);
        Config::set('audit.implementation', Audit::class);
        Config::set('audit.drivers.elastic.useCaCert', false);
        Config::set('audit.drivers.elastic.useAsyncClient', false);
        Config::set('audit.drivers.elastic.certPath', 'http_ca.crt');
        Config::set('audit.drivers.queue.enabled', false);
        Config::set('audit.drivers.queue.name', 'audits');
        Config::set('audit.drivers.queue.connection', 'redis');
        $this->loadMigrationsFrom(__DIR__.'/Migration');
    }

    /** @return list<class-string<ServiceProvider>> */
    protected function getPackageProviders($app): array
    {
        return [
            ElasticsearchAuditingServiceProvider::class,
        ];
    }

    /**
     * @param array<int, int> $statuses
     * @param array<int, null|array<mixed>> $bodies
     * @throws Exception
     */
    protected function getService(array $statuses = [200], array $bodies = [], bool $shouldBind = false, bool $shouldThrowException = true): ElasticsearchAuditService
    {
        $mockedElasticClient = $this->getMockedElasticClient(
            statuses: $statuses,
            bodies: $bodies,
            shouldThrowException: $shouldThrowException,
        );
        $service = resolve(ElasticsearchAuditService::class)
            ->setClient($mockedElasticClient);

        if ($shouldBind) {
            assert($this->app instanceof Application);
            $this->app->singleton(ElasticsearchAuditService::class, fn (): ElasticsearchAuditService => $service);
        }

        return $service;
    }

    /**
     * @param callable(ElasticsearchClient&MockObject):void $configureMock
     */
    protected function getServiceWithMockedClient(callable $configureMock, bool $shouldBind = false): ElasticsearchAuditService
    {
        $clientMock = $this->createMock(ElasticsearchClient::class);
        $clientMock->expects($this->once())
            ->method('setClient')
            ->willReturnSelf();
        $configureMock($clientMock);
        assert($this->app instanceof Application);
        $this->app->instance(ElasticsearchClient::class, $clientMock);

        $service = resolve(ElasticsearchAuditService::class);

        if ($shouldBind) {
            $this->app->singleton(ElasticsearchAuditService::class, fn (): ElasticsearchAuditService => $service);
        }

        return $service;
    }

    /**
     * @param array<int, int> $statuses
     * @param array<int, null|array<mixed>> $bodies
     * @throws Exception
     */
    protected function getMockedElasticClient(array $statuses, array $bodies, bool $shouldThrowException = true): Client
    {
        $mockHttpClient = new MockHttpClient();
        foreach ($statuses as $index => $status) {
            $body        = $bodies[$index] ?? [];
            $rawResponse = new Response(
                status: $status,
                headers: [
                    Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME,
                    'Content-type'              => 'application/json',
                ],
                body: json_encode($body, JSON_THROW_ON_ERROR),
            );

            if ($shouldThrowException) {
                $mockHttpClient->addResponse($rawResponse);

                continue;
            }

            $elasticResponse = new Elasticsearch();
            $elasticResponse->setResponse($rawResponse, false);
            $mockHttpClient->addResponse($elasticResponse);
        }

        return ClientBuilder::create()
            ->setHttpClient($mockHttpClient)
            ->setHosts(['https://test:9200'])
            ->build();
    }

    /**
     * @param array<int|string, mixed> $body
     * @throws Exception
     */
    protected function getElasticResponse(int $status = 200, array $body = []): Elasticsearch
    {
        $rawResponse = new Response(
            status: $status,
            headers: [
                Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME,
                'Content-type'              => 'application/json',
            ],
            body: json_encode($body, JSON_THROW_ON_ERROR),
        );

        $elasticResponse = new Elasticsearch();
        $elasticResponse->setResponse($rawResponse, false);

        return $elasticResponse;
    }

    protected function getUser(): User
    {
        $user = User::factory()->make();

        return $user;
    }
}
