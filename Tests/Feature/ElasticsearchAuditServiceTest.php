<?php

namespace rajmundtoth0\AuditDriver\Tests\Feature;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use OwenIt\Auditing\Resolvers\UserResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use rajmundtoth0\AuditDriver\Jobs\IndexAuditDocumentJob;
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
    #[DataProvider('provideCreateIndexCases')]
    public function testCreateIndex(int $firstStatus): void
    {
        $service = $this->getService(
            statuses: [$firstStatus, 200, 200],
            bodies: [],
            shouldBind: true,
            shouldThrowException: false,
        );

        $result = $service->createIndex();

        static::assertSame('mocked', $result);
    }

    /**
     * @throws Exception
     */
    #[DataProvider('provideDeleteIndexCases')]
    public function testDeleteIndex(bool $isIndexExists, bool $expectedResult): void
    {
        $service = $this->getService(
            statuses: [200, 200],
            bodies: [],
            shouldBind: true,
        );
        if ($isIndexExists) {
            $service->createIndex();
        }

        $result = $service->deleteIndex();

        static::assertSame($expectedResult, $result);
    }

    /**
     * @throws Exception
     */
    #[DataProvider('provideIndexDocumentCases')]
    public function testIndexDocument(bool $shouldReturnResult, ?bool $expectedResult, bool $shouldUseQueue): void
    {
        Config::set('audit.drivers.queue.enabled', $shouldUseQueue);
        Queue::fake();
        /** @var array<string, mixed> */
        $user    = $this->getUser()->toArray();
        $service = $this->getService(
            statuses: [200],
            bodies: [],
            shouldBind: true,
        );
        $result = $service->indexDocument(
            model: $user,
            shouldReturnResult: $shouldReturnResult,
        );

        if ($shouldUseQueue) {
            Queue::assertPushed(IndexAuditDocumentJob::class,
                fn(IndexAuditDocumentJob $job): bool => 'audits' === $job->queue
                && 'redis' === $job->connection
            );
        }

        static::assertSame($expectedResult, $result);
    }

    /**
     * @throws Exception
     */
    public function testSearchDocument(): void
    {
        $user = $this->getUser();
        /** @var array<string, mixed> */
        $userArray = $user->toArray();
        $service   = $this->getService(
            statuses: [200, 200, 200],
            bodies: [null, null, $userArray],
            shouldBind: true,
        );
        $service->indexDocument($userArray);
        $service->indexDocument(['name' => 'Not John Doe']);

        $result = $service->searchAuditDocument($user);

        static::assertTrue($result->asBool());
        static::assertSame($userArray, $result->asArray());
    }

    /**
     * @throws Exception
     */
    public function testSearch(): void
    {
        $user = $this->getUser();
        /** @var array<string, mixed> */
        $userArray = $user->toArray();
        $service   = $this->getService(
            statuses: [200, 200, 200],
            bodies: [null, null, $userArray],
            shouldBind: true,
        );
        $service->indexDocument($userArray);
        $service->indexDocument([
            'id'   => 314159,
            'name' => 'Not John Doe',
        ]);

        $query = [
            'bool' => [
                'must' => [
                    [
                        'term' => [
                            'auditable_id' => $user->id,
                        ],
                    ],
                ],
            ],
        ];
        $result = $service
            ->search(query: $query);

        static::assertTrue($result->asBool());
        static::assertSame($userArray, $result->asArray());
    }

    /**
     * @throws Exception
     */
    public function testCount(): void
    {
        $user = $this->getUser();
        /** @var array<string, mixed> */
        $userArray = $user->toArray();
        $service   = $this->getService(
            statuses: [200, 200, 200],
            bodies: [null, null, ['count' => 1]],
            shouldBind: true,
        );
        $service->indexDocument($userArray);
        $service->indexDocument([
            'id'   => 314159,
            'name' => 'Not John Doe',
        ]);

        $query = [
            'bool' => [
                'must' => [
                    [
                        'term' => [
                            'auditable_id' => $user->id,
                        ],
                    ],
                ],
            ],
        ];
        $result = $service
            ->count(query: $query);

        $count = $result->asObject();
        assert(property_exists($count, 'count'));

        static::assertTrue($result->asBool());
        static::assertSame(1, $count->count);
    }

    /**
     * @throws Exception
     */
    public function testDeleteDocument(): void
    {
        $user = $this->getUser();
        /** @var array<string, mixed> */
        $userArray = $user->toArray();
        $service   = $this->getService(
            statuses: [200, 200, 200],
            bodies: [],
            shouldBind: true,
        );

        $service->indexDocument($userArray);
        $service->indexDocument(['name' => 'Not John Doe']);

        $result = $service->deleteAuditDocument($user->id, true);

        static::assertTrue($result);
    }

    /**
     * @throws Exception
     */
    #[DataProvider('providePruneDocumentCases')]
    public function testPruneDocument(int $threshold, bool $expectedResult): void
    {
        Config::set('audit.threshold', $threshold);
        $service = $this->getService(
            statuses: [200],
            bodies: [],
            shouldBind: true,
        );
        $result = $service->prune($this->getUser(), true);

        static::assertSame($expectedResult, $result);
    }

    /**
     * @throws Exception
     */
    public function testAudit(): void
    {
        Config::set('audit.user.resolver', UserResolver::class);
        $user = User::create([
            'name'     => 'test',
            'email'    => 'test@test.test',
            'password' => Hash::make('a_very_strong_password'),
        ]);
        $service = $this->getService(
            statuses: [200, 200],
            bodies: [null, $user->toArray()],
            shouldBind: true,
        );

        $user->isCustomEvent = true;
        $user->setAuditEvent('saving');
        $result = $service->audit($user);

        $searchResult = $service->searchAuditDocument($user);
        static::assertTrue($searchResult->asBool());
        static::assertSame($searchResult->asArray(), $user->toArray());
    }

    /**
     * @throws Exception
     */
    public function testIsAsync(): void
    {
        $service = $this->getService(
            statuses: [200],
            bodies: [],
            shouldBind: true,
        );

        static::assertFalse($service->isAsync());
    }

    /**
     * @return array<int, array<string, int>>
     */
    public static function provideCreateIndexCases(): iterable
    {
        return [
            [
                'firstStatus' => 404,
            ],
            [
                'firstStatus' => 200,
            ],
        ];
    }

    /**
     * @return array<int, array<string, null|bool>>
     */
    public static function provideIndexDocumentCases(): iterable
    {
        return [
            [
                'shouldReturnResult' => false,
                'expectedResult'     => false,
                'shouldUseQueue'     => false,
            ],
            [
                'shouldReturnResult' => true,
                'expectedResult'     => true,
                'shouldUseQueue'     => false,
            ],
            [
                'shouldReturnResult' => false,
                'expectedResult'     => null,
                'shouldUseQueue'     => true,
            ],
            [
                'shouldReturnResult' => true,
                'expectedResult'     => null,
                'shouldUseQueue'     => true,
            ],
        ];
    }

    /**
     * @return array<int, array<string, null|bool>>
     */
    public static function provideDeleteIndexCases(): iterable
    {
        return [
            [
                'isIndexExists'  => false,
                'expectedResult' => true,
            ],
            [
                'isIndexExists'  => true,
                'expectedResult' => true,
            ],
        ];
    }

    /**
     * @return array<int, array<string, bool|int>>
     */
    public static function providePruneDocumentCases(): iterable
    {
        return [
            [
                'threshold'      => 0,
                'expectedResult' => false,
            ],
            [
                'threshold'      => 5,
                'expectedResult' => true,
            ],
        ];
    }
}
