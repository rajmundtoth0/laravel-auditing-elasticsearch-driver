<?php

namespace rajmundtoth0\AuditDriver\Tests\Feature;

use rajmundtoth0\AuditDriver\Client\ElasticsearchClient;
use rajmundtoth0\AuditDriver\Tests\TestCase;
use Illuminate\Testing\PendingCommand;

/**
 * @internal
 */
class ElasticsearchSetupCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testSetupCommand(): void
    {
        $client = $this->mock(ElasticsearchClient::class);
        /** @var \Mockery\MockInterface $client*/
        $client->shouldReceive('isIndexExists')
            ->andReturn(true);
        /** @phpstan-ignore-next-line */
        $client
            ->shouldReceive('toggleAsync')
            ->shouldReceive('updateAliases')
            ->shouldReceive('createIndex')
            ->andReturn(true);
        $result = $this->artisan('es-audit-log:setup');
        assert($result instanceof PendingCommand);
        $result->assertExitCode(0);
    }
}