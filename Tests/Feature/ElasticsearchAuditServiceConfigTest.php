<?php

namespace rajmundtoth0\AuditDriver\Tests\Feature;

use InvalidArgumentException;
use rajmundtoth0\AuditDriver\Services\ElasticsearchAuditService;
use rajmundtoth0\AuditDriver\Tests\TestCase;

/**
 * @internal
 */
class ElasticsearchAuditServiceConfigTest extends TestCase
{
    public function testInvalidStorageModeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value [invalid-mode] for [audit.drivers.elastic.storageMode]');

        config()->set('audit.drivers.elastic.storageMode', 'invalid-mode');
        resolve(ElasticsearchAuditService::class);
    }

    public function testLegacyLifecyclePolicyConfigThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Configuration value for key [audit.drivers.elastic.dataStream.lifecyclePolicy] is no longer supported. Use [audit.drivers.elastic.definitions.lifecyclePolicy] instead.',
        );

        config()->set('audit.drivers.elastic.storageMode', 'data_stream');
        config()->set('audit.drivers.elastic.dataStream.lifecyclePolicy', [
            'policy' => [
                'phases' => [],
            ],
        ]);
        resolve(ElasticsearchAuditService::class);
    }

    public function testInvalidSettingsJsonThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Configuration value for key [audit.drivers.elastic.definitions.settings.json] must contain valid JSON.',
        );

        config()->set('audit.drivers.elastic.definitions.settings.json', '{invalid');
        resolve(ElasticsearchAuditService::class);
    }

    public function testInvalidMappingsPathThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'JSON definition file [/tmp/audit-driver-does-not-exist.json] does not exist for key [audit.drivers.elastic.definitions.mappings.path].',
        );

        config()->set('audit.drivers.elastic.definitions.mappings.json', '');
        config()->set('audit.drivers.elastic.definitions.mappings.path', '/tmp/audit-driver-does-not-exist.json');
        resolve(ElasticsearchAuditService::class);
    }

    public function testInlineJsonDefinitionsCanOverrideFiles(): void
    {
        config()->set('audit.drivers.elastic.definitions.settings.json', '{"number_of_shards":1,"number_of_replicas":0}');
        config()->set('audit.drivers.elastic.definitions.mappings.json', '{"properties":{"created_at":{"type":"date"}}}');
        config()->set('audit.drivers.elastic.definitions.lifecyclePolicy.json', '{"policy":{"phases":{"hot":{"actions":{}}}}}');

        $service = resolve(ElasticsearchAuditService::class);

        static::assertSame('mocked', $service->getIndexName());
    }

    public function testInvalidLifecyclePolicyShapeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Configuration value for key [audit.drivers.elastic.definitions.lifecyclePolicy] must contain a non-empty object at [policy.phases].',
        );

        config()->set('audit.drivers.elastic.definitions.lifecyclePolicy.json', '{"policy":{"phases":[]}}');
        resolve(ElasticsearchAuditService::class);
    }

    public function testInvalidSingleWriteRetryJsonThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Configuration value for key [audit.drivers.elastic.definitions.singleWriteRetry.json] must contain valid JSON.',
        );

        config()->set('audit.drivers.elastic.definitions.singleWriteRetry.json', '{invalid');
        resolve(ElasticsearchAuditService::class);
    }
}
