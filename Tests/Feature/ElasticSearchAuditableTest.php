<?php

namespace Tests\Feature;

use rajmundtoth0\AuditDriver\Tests\Model\User;
use rajmundtoth0\AuditDriver\Tests\TestCase;
use Illuminate\Support\Collection;

/**
 * @internal
 */
class ElasticSearchAuditableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

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
                ]
            ]
        ];
        $service = $this->getService(
            statuses: [200],
            bodies: [$body],
            shouldBind: true,
        );

        /** @var User $user */
        $user = User::factory()->make();
        $user->update([
            'name' => 'Test Doe',
        ]);

        /** @var Collection<int, mixed> $auditLogs */
        $auditLogs = $user->auditLog;

        $this->assertSame($body, $auditLogs->toArray());
    }
}