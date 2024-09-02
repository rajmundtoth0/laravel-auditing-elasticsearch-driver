<?php

namespace Tests\Feature;

use Exception;
use Illuminate\Support\Collection;
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
        $this->getService(
            statuses: [200],
            bodies: [$body],
            shouldBind: true,
        );

        $user = $this->getUser();
        $user->update([
            'name' => 'Test Doe',
        ]);

        /** @var Collection<int, mixed> $auditLogs */
        $auditLogs = $user->auditLog;

        $this->assertSame($body, $auditLogs->toArray());
    }
}
