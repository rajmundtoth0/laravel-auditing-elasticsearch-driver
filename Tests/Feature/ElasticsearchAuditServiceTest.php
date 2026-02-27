<?php

namespace rajmundtoth0\AuditDriver\Tests\Feature;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use OwenIt\Auditing\Models\Audit;
use OwenIt\Auditing\Resolvers\UserResolver;
use PHPUnit\Framework\MockObject\MockObject;
use rajmundtoth0\AuditDriver\Client\ElasticsearchClient;
use rajmundtoth0\AuditDriver\Jobs\IndexAuditDocumentJob;
use rajmundtoth0\AuditDriver\Models\DocumentModel;
use rajmundtoth0\AuditDriver\Tests\Model\User;
use rajmundtoth0\AuditDriver\Tests\TestCase;

/**
 * @internal
 */
class ElasticsearchAuditServiceTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testCreateIndexCreatesAndAliasesWhenMissing(): void
    {
        $service = $this->getServiceWithMockedClient(function (ElasticsearchClient&MockObject $client): void {
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
        });

        $result = $service->createIndex();

        static::assertSame('mocked', $result);
    }

    /**
     * @throws Exception
     */
    public function testCreateIndexSkipsWhenIndexExists(): void
    {
        $service = $this->getServiceWithMockedClient(function (ElasticsearchClient&MockObject $client): void {
            $client->expects($this->once())
                ->method('isIndexExists')
                ->with('mocked')
                ->willReturn(true);
            $client->expects($this->never())->method('createIndex');
            $client->expects($this->never())->method('updateAliases');
        });

        $result = $service->createIndex();

        static::assertSame('mocked', $result);
    }

    /**
     * @throws Exception
     */
    public function testCreateDataStreamTemplateInDataStreamMode(): void
    {
        Config::set('audit.drivers.elastic.storageMode', 'data_stream');
        $service = $this->getServiceWithMockedClient(function (ElasticsearchClient&MockObject $client): void {
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

        $result = $service->createIndex();

        static::assertSame('mocked', $result);
    }

    /**
     * @throws Exception
     */
    public function testCreateDataStreamWithLifecyclePolicy(): void
    {
        Config::set('audit.drivers.elastic.storageMode', 'data_stream');
        Config::set('audit.drivers.elastic.dataStream.lifecyclePolicyName', 'audits-hot-delete');
        $lifecyclePolicy = [
            'policy' => [
                'phases' => [
                    'hot' => [
                        'actions' => [],
                    ],
                ],
            ],
        ];
        $lifecyclePath = sprintf('%s/%s', sys_get_temp_dir(), uniqid('audit-lifecycle-', true).'.json');
        file_put_contents($lifecyclePath, json_encode($lifecyclePolicy, JSON_THROW_ON_ERROR));
        Config::set('audit.drivers.elastic.definitions.lifecyclePolicy.path', $lifecyclePath);

        try {
            $service = $this->getServiceWithMockedClient(function (ElasticsearchClient&MockObject $client) use ($lifecyclePolicy): void {
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

            $result = $service->createIndex();

            static::assertSame('mocked', $result);
        } finally {
            if (is_file($lifecyclePath)) {
                unlink($lifecyclePath);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function testDeleteIndexDelegatesInIndexMode(): void
    {
        $service = $this->getServiceWithMockedClient(function (ElasticsearchClient&MockObject $client): void {
            $client->expects($this->once())
                ->method('deleteIndex')
                ->with('mocked')
                ->willReturn(true);
            $client->expects($this->never())->method('deleteDataStream');
        });

        static::assertTrue($service->deleteIndex());
    }

    /**
     * @throws Exception
     */
    public function testDeleteIndexDelegatesInDataStreamMode(): void
    {
        Config::set('audit.drivers.elastic.storageMode', 'data_stream');
        $service = $this->getServiceWithMockedClient(function (ElasticsearchClient&MockObject $client): void {
            $client->expects($this->once())
                ->method('deleteDataStream')
                ->with('mocked')
                ->willReturn(true);
            $client->expects($this->never())->method('deleteIndex');
        });

        static::assertTrue($service->deleteIndex());
    }

    /**
     * @throws Exception
     */
    public function testIndexDocumentIndexesImmediatelyWhenQueueDisabled(): void
    {
        /** @var array<string, mixed> $user */
        $user = $this->getUser()->toArray();
        $service = $this->getServiceWithMockedClient(function (ElasticsearchClient&MockObject $client): void {
            $client->expects($this->once())
                ->method('index')
                ->with(
                    $this->callback(function (DocumentModel $document): bool {
                        $rawDocument = $document->toArray();

                        return 'mocked' === $rawDocument['index']
                            && is_array($rawDocument['body'])
                            && array_key_exists('created_at', $rawDocument['body']);
                    }),
                    true,
                    false,
                )
                ->willReturn(true);
        });

        $result = $service->indexDocument($user, true);

        static::assertTrue($result);
    }

    /**
     * @throws Exception
     */
    public function testIndexDocumentAddsTimestampInDataStreamMode(): void
    {
        Config::set('audit.drivers.elastic.storageMode', 'data_stream');
        $service = $this->getServiceWithMockedClient(function (ElasticsearchClient&MockObject $client): void {
            $client->expects($this->once())
                ->method('index')
                ->with(
                    $this->callback(function (DocumentModel $document): bool {
                        $rawDocument = $document->toArray();
                        assert(is_array($rawDocument['body']));

                        return array_key_exists('@timestamp', $rawDocument['body']);
                    }),
                    true,
                    true,
                )
                ->willReturn(true);
        });

        $result = $service->indexDocument([
            'auditable_id'   => 1001,
            'auditable_type' => 'user',
            'event'          => 'updated',
        ], true);

        static::assertTrue($result);
    }

    /**
     * @throws Exception
     */
    public function testIndexDocumentDispatchesQueueWhenEnabled(): void
    {
        Config::set('audit.drivers.queue.enabled', true);
        Queue::fake();
        $service = $this->getServiceWithMockedClient(function (ElasticsearchClient&MockObject $client): void {
            $client->expects($this->never())->method('index');
        });

        $result = $service->indexDocument($this->getUser()->toArray(), true);

        static::assertNull($result);
        Queue::assertPushed(IndexAuditDocumentJob::class,
            fn(IndexAuditDocumentJob $job): bool => 'audits' === $job->queue
                && 'redis' === $job->connection
        );
    }

    /**
     * @throws Exception
     */
    public function testSearchAuditDocumentBuildsExpectedQuery(): void
    {
        $user = $this->getUser();
        $user->setAttribute('id', 1001);
        /** @var array<string, mixed> $userArray */
        $userArray = $user->toArray();
        $service = $this->getServiceWithMockedClient(function (ElasticsearchClient&MockObject $client) use ($user, $userArray): void {
            $client->expects($this->once())
                ->method('search')
                ->with($this->callback(function (array $params) use ($user): bool {
                    if ('mocked' !== ($params['index'] ?? null)) {
                        return false;
                    }
                    $body = $params['body'] ?? null;
                    if (!is_array($body)) {
                        return false;
                    }
                    $query = $body['query'] ?? null;
                    if (!is_array($query)) {
                        return false;
                    }
                    $bool = $query['bool'] ?? null;
                    if (!is_array($bool)) {
                        return false;
                    }
                    $must = $bool['must'] ?? null;
                    if (!is_array($must) || !array_key_exists(0, $must) || !array_key_exists(1, $must)) {
                        return false;
                    }
                    $firstMust = $must[0];
                    $secondMust = $must[1];
                    if (!is_array($firstMust) || !is_array($secondMust)) {
                        return false;
                    }
                    $firstTerm = $firstMust['term'] ?? null;
                    $secondTerm = $secondMust['term'] ?? null;
                    if (!is_array($firstTerm) || !is_array($secondTerm)) {
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

                    return ($firstTerm['auditable_id'] ?? null) === $user->id
                        && ($secondTerm['auditable_type'] ?? null) === $user->getMorphClass()
                        && 'created_at' === array_key_first($sort)
                        && 'desc' === ($createdAtSort['order'] ?? null);
                }))
                ->willReturn($this->getElasticResponse(body: $userArray));
        });

        $result = $service->searchAuditDocument($user);

        static::assertTrue($result->asBool());
        static::assertSame($userArray, $result->asArray());
    }

    /**
     * @throws Exception
     */
    public function testSearchDelegatesToClient(): void
    {
        $query = [
            'index' => 'mocked',
            'body'  => [
                'query' => [
                    'term' => [
                        'auditable_id' => 1001,
                    ],
                ],
            ],
        ];
        $service = $this->getServiceWithMockedClient(function (ElasticsearchClient&MockObject $client) use ($query): void {
            $client->expects($this->once())
                ->method('search')
                ->with($query)
                ->willReturn($this->getElasticResponse(body: ['hits' => []]));
        });

        $result = $service->search($query);

        static::assertTrue($result->asBool());
    }

    /**
     * @throws Exception
     */
    public function testCountDelegatesToClient(): void
    {
        $query = [
            'index' => 'mocked',
            'body'  => [
                'query' => [
                    'term' => [
                        'auditable_id' => 1001,
                    ],
                ],
            ],
        ];
        $service = $this->getServiceWithMockedClient(function (ElasticsearchClient&MockObject $client) use ($query): void {
            $client->expects($this->once())
                ->method('count')
                ->with($query)
                ->willReturn($this->getElasticResponse(body: ['count' => 1]));
        });

        $result = $service->count($query)->asObject();
        assert(property_exists($result, 'count'));

        static::assertSame(1, $result->count);
    }

    /**
     * @throws Exception
     */
    public function testDeleteAuditDocumentDelegatesToClient(): void
    {
        $service = $this->getServiceWithMockedClient(function (ElasticsearchClient&MockObject $client): void {
            $client->expects($this->once())
                ->method('deleteDocument')
                ->with('mocked', 1001, true)
                ->willReturn(true);
        });

        static::assertTrue($service->deleteAuditDocument(1001, true));
    }

    /**
     * @throws Exception
     */
    public function testPruneReturnsFalseWhenThresholdIsZero(): void
    {
        Config::set('audit.threshold', 0);
        $service = $this->getServiceWithMockedClient(function (ElasticsearchClient&MockObject $client): void {
            $client->expects($this->never())->method('deleteDocument');
        });

        static::assertFalse($service->prune($this->getUser(), true));
    }

    /**
     * @throws Exception
     */
    public function testPruneDeletesDocumentWhenThresholdIsPositive(): void
    {
        Config::set('audit.threshold', 5);
        $user = $this->getUser();
        $user->setAttribute('id', 1001);
        $service = $this->getServiceWithMockedClient(function (ElasticsearchClient&MockObject $client): void {
            $client->expects($this->once())
                ->method('deleteDocument')
                ->with('mocked', 1001, true)
                ->willReturn(true);
        });

        static::assertTrue($service->prune($user, true));
    }

    /**
     * @throws Exception
     */
    public function testAuditIndexesDocumentAndReturnsImplementation(): void
    {
        Config::set('audit.user.resolver', UserResolver::class);
        $user = User::create([
            'name'     => 'test',
            'email'    => 'test@test.test',
            'password' => Hash::make('a_very_strong_password'),
        ]);
        $service = $this->getServiceWithMockedClient(function (ElasticsearchClient&MockObject $client): void {
            $client->expects($this->once())
                ->method('index')
                ->with(
                    $this->isInstanceOf(DocumentModel::class),
                    false,
                    false,
                )
                ->willReturn(false);
        });

        $user->isCustomEvent = true;
        $user->setAuditEvent('saving');

        $result = $service->audit($user);

        static::assertInstanceOf(Audit::class, $result);
    }

    /**
     * @throws Exception
     */
    public function testIsAsyncDelegatesToClient(): void
    {
        $service = $this->getServiceWithMockedClient(function (ElasticsearchClient&MockObject $client): void {
            $client->expects($this->once())
                ->method('isAsync')
                ->willReturn(false);
        });

        static::assertFalse($service->isAsync());
    }
}
