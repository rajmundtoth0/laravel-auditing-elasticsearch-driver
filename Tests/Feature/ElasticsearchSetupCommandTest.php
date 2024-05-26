<?php

namespace rajmundtoth0\AuditDriver\Tests\Feature;

use Illuminate\Testing\PendingCommand;
use rajmundtoth0\AuditDriver\Tests\TestCase;

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
        $service = $this->getService(
            statuses: [200, 200, 200],
            bodies: [],
            shouldBind: true,
            shouldThrowException: false,
        );

        $result = $this->artisan('es-audit-log:setup');
        assert($result instanceof PendingCommand);

        $result->assertExitCode(0);
        $this->assertSame('mocked', $service->getIndexName());
    }
}
