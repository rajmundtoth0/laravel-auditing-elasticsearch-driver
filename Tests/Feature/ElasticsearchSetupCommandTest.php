<?php

namespace rajmundtoth0\AuditDriver\Tests\Feature;

use Exception;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\MockObject\MockObject;
use rajmundtoth0\AuditDriver\Client\ElasticsearchClient;
use rajmundtoth0\AuditDriver\Tests\TestCase;

/**
 * @internal
 */
class ElasticsearchSetupCommandTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testSetupCommand(): void
    {
        $service = $this->getServiceWithMockedClient(function (ElasticsearchClient&MockObject $client): void {
            $client->expects($this->once())
                ->method('isIndexExists')
                ->with('mocked')
                ->willReturn(false);
            $client->expects($this->once())
                ->method('createIndex')
                ->willReturn($this->getElasticResponse());
            $client->expects($this->once())
                ->method('updateAliases')
                ->with('mocked')
                ->willReturn($this->getElasticResponse());
        },
            shouldBind: true,
        );

        $result = $this->artisan('es-audit-log:setup');
        assert($result instanceof PendingCommand);

        $result->assertExitCode(0);
        $this->assertSame('mocked', $service->getIndexName());
    }
}
