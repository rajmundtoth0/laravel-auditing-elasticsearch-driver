<?php

namespace rajmundtoth0\AuditDriver\Tests\Feature;

use InvalidArgumentException;
use JsonException;
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
        $this->expectException(JsonException::class);
        $path = sprintf('%s/%s', sys_get_temp_dir(), uniqid('audit-settings-', true).'.json');
        file_put_contents($path, '{invalid');
        config()->set('audit.drivers.elastic.definitions.settings.path', $path);

        try {
            resolve(ElasticsearchAuditService::class);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testInvalidMappingsPathThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'JSON definition file [/tmp/audit-driver-does-not-exist.json] does not exist for key [audit.drivers.elastic.definitions.mappings.path].',
        );

        config()->set('audit.drivers.elastic.definitions.mappings.path', '/tmp/audit-driver-does-not-exist.json');
        resolve(ElasticsearchAuditService::class);
    }

    public function testPathDefinitionsCanOverrideDefaultFiles(): void
    {
        $settingsPath  = sprintf('%s/%s', sys_get_temp_dir(), uniqid('audit-settings-', true).'.json');
        $mappingsPath  = sprintf('%s/%s', sys_get_temp_dir(), uniqid('audit-mappings-', true).'.json');
        $lifecyclePath = sprintf('%s/%s', sys_get_temp_dir(), uniqid('audit-lifecycle-', true).'.json');
        file_put_contents($settingsPath, '{"number_of_shards":1,"number_of_replicas":0}');
        file_put_contents($mappingsPath, '{"properties":{"created_at":{"type":"date"}}}');
        file_put_contents($lifecyclePath, '{"policy":{"phases":{"hot":{"actions":{}}}}}');
        config()->set('audit.drivers.elastic.definitions.settings.path', $settingsPath);
        config()->set('audit.drivers.elastic.definitions.mappings.path', $mappingsPath);
        config()->set('audit.drivers.elastic.definitions.lifecyclePolicy.path', $lifecyclePath);

        try {
            $service = resolve(ElasticsearchAuditService::class);
            static::assertSame('mocked', $service->getIndexName());
        } finally {
            if (is_file($settingsPath)) {
                unlink($settingsPath);
            }
            if (is_file($mappingsPath)) {
                unlink($mappingsPath);
            }
            if (is_file($lifecyclePath)) {
                unlink($lifecyclePath);
            }
        }
    }

    public function testInvalidLifecyclePolicyShapeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Configuration value for key [audit.drivers.elastic.definitions.lifecyclePolicy] must contain a non-empty object at [policy.phases].',
        );
        $path = sprintf('%s/%s', sys_get_temp_dir(), uniqid('audit-lifecycle-', true).'.json');
        file_put_contents($path, '{"policy":{"phases":[]}}');
        config()->set('audit.drivers.elastic.definitions.lifecyclePolicy.path', $path);

        try {
            resolve(ElasticsearchAuditService::class);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

}
