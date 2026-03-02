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

    /**
     * @throws Exception
     */
    public function testElasticsearchAuditLogBuildsPagingAndSortArguments(): void
    {
        $this->getServiceWithMockedClient(function (ElasticsearchClient&MockObject $client): void {
            $client->expects($this->once())
                ->method('search')
                ->with($this->callback(function (array $params): bool {
                    if ('mocked' !== ($params['index'] ?? null) || 5 !== ($params['size'] ?? null) || 10 !== ($params['from'] ?? null)) {
                        return false;
                    }

                    $body = $params['body'] ?? null;
                    if (!is_array($body)) {
                        return false;
                    }

                    $sort = $body['sort'] ?? null;
                    if (!is_array($sort)) {
                        return false;
                    }

                    $createdAtSort = $sort['created_at'] ?? null;
                    if (!is_array($createdAtSort)) {
                        return false;
                    }

                    return 'asc' === ($createdAtSort['order'] ?? null);
                }))
                ->willReturn($this->getElasticResponse(body: ['hits' => ['hits' => []]]));
        },
            shouldBind: true,
        );

        $user = $this->getUser();
        $logs = $user->elasticsearchAuditLog(page: 3, pageSize: 5, sort: 'asc');

        static::assertSame(['hits' => ['hits' => []]], $logs->toArray());
    }
}
