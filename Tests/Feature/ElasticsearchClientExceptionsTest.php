<?php

namespace rajmundtoth0\AuditDriver\Tests\Feature;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use rajmundtoth0\AuditDriver\Client\ElasticsearchClient;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverMissingCaCertException;
use rajmundtoth0\AuditDriver\Tests\TestCase;

/**
 * @internal
 */
class ElasticsearchClientExceptionsTest extends TestCase
{
    /**
     * @throws Exception
     */
    #[DataProvider('provideConfigNotSetExceptionCases')]
    public function testConfigNotSetException(string $key, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        Config::set('audit.drivers.elastic.useBasicAuth', true);
        Config::set('audit.drivers.elastic.useCaCert', true);
        Config::set($key, null);
        resolve(ElasticsearchClient::class)
            ->setClient();
    }

    /**
     * @throws Exception
     */
    public function testCaCertMissingException(): void
    {
        Storage::fake();
        Storage::shouldReceive('path')
            ->andReturn(false);
        $this->expectException(AuditDriverMissingCaCertException::class);
        Config::set('audit.drivers.elastic.useCaCert', true);
        Config::set('audit.drivers.elastic.certPath', '/dont-find-me');
        resolve(ElasticsearchClient::class)
            ->setClient();
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function provideConfigNotSetExceptionCases(): iterable
    {
        return [
            [
                'key'     => 'audit.drivers.elastic.hosts',
                'message' => 'Configuration value for key [audit.drivers.elastic.hosts] must be an array, NULL given.',
            ],
            [
                'key'     => 'audit.drivers.elastic.userName',
                'message' => 'Configuration value for key [audit.drivers.elastic.userName] must be a string, NULL given.',
            ],
            [
                'key'     => 'audit.drivers.elastic.password',
                'message' => 'Configuration value for key [audit.drivers.elastic.password] must be a string, NULL given.',
            ],
            [
                'key'     => 'audit.drivers.elastic.certPath',
                'message' => 'Configuration value for key [audit.drivers.elastic.certPath] must be a string, NULL given.',
            ],
        ];
    }
}
