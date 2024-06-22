<?php

namespace rajmundtoth0\AuditDriver\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use OwenIt\Auditing\Contracts\Audit;
use OwenIt\Auditing\Resolvers\UserResolver;
use rajmundtoth0\AuditDriver\Tests\Model\User;
use rajmundtoth0\AuditDriver\Tests\TestCase;

/**
 * @internal
 */
class ElasticsearchAuditServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @dataProvider provideCreateIndexCases
     */
    public function testCreateIndex(int $firstStatus): void
    {
        $service = $this->getService(
            statuses: [$firstStatus, 200, 200],
            bodies: [],
            shouldBind: true,
            shouldThrowException: false,
        );

        $result = $service->createIndex();

        $this->assertSame('mocked', $result);
    }

    /**
     * @dataProvider provideDeleteIndexCases
     */
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

        $this->assertSame($expectedResult, $result);
    }

    /**
     * @dataProvider provideIndexDocumentCases
     */
    public function testIndexDocument(bool $shouldReturnResult, bool $expectedResult): void
    {
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

        $this->assertSame($expectedResult, $result);
    }

    public function testSearchDocument(): void
    {
        $user    = $this->getUser();
        $service = $this->getService(
            statuses: [200, 200, 200],
            bodies: [],
            shouldBind: true,
        );
        $service->indexDocument($user->toArray());
        $service->indexDocument(['name' => 'Not John Doe']);

        $result = $service->searchAuditDocument($user);

        $this->assertTrue($result->asBool());
        $this->assertSame($result->asArray(), $result->asArray());
    }

    public function testDeleteDocument(): void
    {
        $user    = $this->getUser();
        $service = $this->getService(
            statuses: [200, 200, 200],
            bodies: [],
            shouldBind: true,
        );

        $service->indexDocument($user->toArray());
        $service->indexDocument(['name' => 'Not John Doe']);

        $result = $service->deleteAuditDocument($user->id, true);

        $this->assertTrue($result);
    }

    /** @dataProvider providePruneDocumentCases */
    public function testPruneDocument(int $threshold, bool $expectedResult): void
    {
        Config::set('audit.threshold', $threshold);
        $service = $this->getService(
            statuses: [200],
            bodies: [],
            shouldBind: true,
        );
        $result = $service->prune($this->getUser(), true);

        $this->assertSame($expectedResult, $result);
    }

    public function testAudit() : void
    {
        Config::set('audit.user.resolver', UserResolver::class);
        $service = $this->getService(
            statuses: [200, 200],
            bodies: [],
            shouldBind: true,
        );
        $user = User::create([
            'name'     => 'test',
            'email'    => 'test@test.test',
            'password' => Hash::make('a_very_strong_password'),
        ]);
        $user->isCustomEvent = true;
        $user->setAuditEvent('saving');
        $result = $service->audit($user);

        $this->assertInstanceOf(Audit::class, $result);

        $searchResult = $service->searchAuditDocument($user);
        $this->assertTrue($searchResult->asBool());
        $this->assertSame($searchResult->asArray(), $searchResult->asArray());
    }

    public function testIsAsync(): void
    {
        $service = $this->getService(
            statuses: [200],
            bodies: [],
            shouldBind: true,
        );

        $this->assertFalse($service->isAsync());
    }

    /** @return array<int, array<string, int>> */
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

    /** @return array<int, array<string, bool>> */
    public static function provideIndexDocumentCases(): iterable
    {
        return [
            [
                'shouldReturnResult' => false,
                'expectedResult'     => false,
            ],
            [
                'shouldReturnResult' => true,
                'expectedResult'     => true,
            ],
        ];
    }

    /** @return array<int, array<string, null|bool>> */
    public static function provideDeleteIndexCases(): iterable
    {
        return  [
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

    /** @return array<int, array<string, bool|int>> */
    public static function providePruneDocumentCases(): iterable
    {
        return[
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
