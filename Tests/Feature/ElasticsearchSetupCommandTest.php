<?php

namespace rajmundtoth0\AuditDriver\Tests\Feature;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\MockObject\MockObject;
use rajmundtoth0\AuditDriver\Client\ElasticsearchClient;
use rajmundtoth0\AuditDriver\Services\ElasticsearchAuditService;
use rajmundtoth0\AuditDriver\Tests\TestCase;

/**
 * @internal
 */
class ElasticsearchSetupCommandTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testSetupCommandCreatesIndexInIndexMode(): void
    {
        Config::set('audit.drivers.elastic.storageMode', 'index');

        $this->bindMockedClient(function (ElasticsearchClient&MockObject $client): void {
            $client->expects($this->once())
                ->method('isIndexExists')
                ->with('mocked')
                ->willReturn(false);
            $client->expects($this->once())
                ->method('createIndex')
                ->with(
                    'mocked',
                    $this->callback(fn(array $settings): bool => [] !== $settings),
                    $this->callback(fn(array $mappings): bool => [] !== $mappings),
                )
                ->willReturn($this->getElasticResponse());
            $client->expects($this->once())
                ->method('updateAliases')
                ->with('mocked')
                ->willReturn($this->getElasticResponse());
            $client->expects($this->never())->method('createDataStreamTemplate');
        });

        $result = $this->artisan('es-audit-log:setup');
        assert($result instanceof PendingCommand);

        $result->assertExitCode(0);
    }

    /**
     * @throws Exception
     */
    public function testSetupCommandCreatesDataStreamTemplateInDataStreamMode(): void
    {
        Config::set('audit.drivers.elastic.storageMode', 'data_stream');
        Config::set('audit.drivers.elastic.dataStream.lifecyclePolicyName', 'audits-hot-delete');
        $rawLifecyclePolicy = file_get_contents(__DIR__.'/../../resources/elasticsearch/lifecycle-policy.json');
        assert(false !== $rawLifecyclePolicy);
        $lifecyclePolicy = json_decode($rawLifecyclePolicy, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($lifecyclePolicy));

        $this->bindMockedClient(function (ElasticsearchClient&MockObject $client) use ($lifecyclePolicy): void {
            $client->expects($this->never())->method('isIndexExists');
            $client->expects($this->never())->method('createIndex');
            $client->expects($this->never())->method('updateAliases');
            $client->expects($this->once())
                ->method('putLifecyclePolicy')
                ->with([
                    'policy' => 'audits-hot-delete',
                    'body'   => $lifecyclePolicy,
                ])
                ->willReturn($this->getElasticResponse());
            $client->expects($this->once())
                ->method('createDataStreamTemplate')
                ->with(
                    'mocked_template',
                    'mocked*',
                    100,
                    $this->callback(fn(array $settings): bool => [] !== $settings),
                    $this->callback(fn(array $mappings): bool => [] !== $mappings),
                    'audits-hot-delete',
                    '',
                )
                ->willReturn($this->getElasticResponse());
        });

        $result = $this->artisan('es-audit-log:setup');
        assert($result instanceof PendingCommand);

        $result->assertExitCode(0);
    }

    /**
     * @throws Exception
     */
    public function testSetupCommandRefreshesConfigWhenServiceWasResolvedEarlier(): void
    {
        Config::set('audit.drivers.elastic.storageMode', 'index');
        $initialClientMock = $this->createMock(ElasticsearchClient::class);
        $initialClientMock->expects($this->once())
            ->method('setClient')
            ->willReturnSelf();
        $initialClientMock->expects($this->never())->method('createDataStreamTemplate');
        $this->app->instance(ElasticsearchClient::class, $initialClientMock);
        resolve(ElasticsearchAuditService::class);

        Config::set('audit.drivers.elastic.storageMode', 'data_stream');
        Config::set('audit.drivers.elastic.dataStream.lifecyclePolicyName', '');
        $this->bindMockedClient(function (ElasticsearchClient&MockObject $client): void {
            $client->expects($this->never())->method('isIndexExists');
            $client->expects($this->never())->method('createIndex');
            $client->expects($this->never())->method('updateAliases');
            $client->expects($this->never())->method('putLifecyclePolicy');
            $client->expects($this->once())
                ->method('createDataStreamTemplate')
                ->with(
                    'mocked_template',
                    'mocked*',
                    100,
                    $this->callback(fn(array $settings): bool => [] !== $settings),
                    $this->callback(fn(array $mappings): bool => [] !== $mappings),
                    '',
                    '',
                )
                ->willReturn($this->getElasticResponse());
        });

        $result = $this->artisan('es-audit-log:setup');
        assert($result instanceof PendingCommand);

        $result->assertExitCode(0);
    }

    /**
     * @param callable(ElasticsearchClient&MockObject):void $configureMock
     */
    private function bindMockedClient(callable $configureMock): void
    {
        $clientMock = $this->createMock(ElasticsearchClient::class);
        $clientMock->expects($this->once())
            ->method('setClient')
            ->willReturnSelf();
        $configureMock($clientMock);
        $this->app->instance(ElasticsearchClient::class, $clientMock);
    }
}
