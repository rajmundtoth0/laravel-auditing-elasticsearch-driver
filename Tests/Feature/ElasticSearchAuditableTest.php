<?php

namespace Tests\Feature;

use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use rajmundtoth0\AuditDriver\Client\ElasticsearchClient;
use rajmundtoth0\AuditDriver\Tests\TestCase;

/**
 * @internal
 */
class ElasticSearchAuditableTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testCallGetAuditLogAttribute(): void
    {
        $body = [
            'took' => 94,
            'hits' => [
                'hits' => [
                    'index'   => 'mocked',
                    '_source' => [
                        'old_values' => [
                            'name' => 'Test Doe',
                        ],
                    ],
                ],
            ],
        ];
        $this->getServiceWithMockedClient(function (ElasticsearchClient&MockObject $client) use ($body): void {
            $client->expects($this->once())
                ->method('search')
                ->willReturn($this->getElasticResponse(body: $body));
        },
            shouldBind: true,
        );

        $user = $this->getUser();
        $user->update([
            'name' => 'Test Doe',
        ]);

        $auditLogs = $user->auditLog;

        $this->assertSame($body, $auditLogs->toArray());
    }
}
