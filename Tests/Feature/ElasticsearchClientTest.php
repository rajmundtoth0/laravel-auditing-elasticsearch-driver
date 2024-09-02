<?php

namespace Tests\Feature;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use rajmundtoth0\AuditDriver\Client\ElasticsearchClient;
use rajmundtoth0\AuditDriver\Tests\TestCase;

/**
 * @internal
 */
class ElasticsearchClientTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testSetClient(): void
    {
        $client = resolve(ElasticsearchClient::class)
            ->setClient();

        static::assertInstanceOf(ElasticsearchClient::class, $client);
    }

    /**
     * @throws Exception
     */
    public function testSetCaBundle(): void
    {
        Storage::fake();
        Storage::shouldReceive('path')
            ->andReturn('/find-me');
        Config::set('audit.drivers.elastic.useCaCert', true);
        Config::set('audit.drivers.elastic.certPath', '/find-me');
        $client = resolve(ElasticsearchClient::class)
            ->setClient();

        $caBundlePath = $client->getCaBundlePath();

        static::assertSame($caBundlePath, '/find-me');
        static::assertInstanceOf(ElasticsearchClient::class, $client);
    }

    /**
     * @throws Exception
     */
    public function testBasicAuth(): void
    {
        Config::set('audit.drivers.elastic.useBasicAuth', false);
        $client = resolve(ElasticsearchClient::class)
            ->setClient();

        static::assertInstanceOf(ElasticsearchClient::class, $client);
    }
}