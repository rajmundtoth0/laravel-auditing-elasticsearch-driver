<?php

namespace rajmundtoth0\AuditDriver\Tests\Feature;

use InvalidArgumentException;
use JsonException;
use rajmundtoth0\AuditDriver\Services\AuditServiceConfig;
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
        $path = __DIR__.'/../Fixtures/elasticsearch/invalid-json.json';
        config()->set('audit.drivers.elastic.definitions.settings.path', $path);

        resolve(ElasticsearchAuditService::class);
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
        $settingsPath  = __DIR__.'/../Fixtures/elasticsearch/custom-settings.json';
        $mappingsPath  = __DIR__.'/../Fixtures/elasticsearch/custom-mappings.json';
        $lifecyclePath = __DIR__.'/../Fixtures/elasticsearch/custom-lifecycle-policy.json';
        config()->set('audit.drivers.elastic.definitions.settings.path', $settingsPath);
        config()->set('audit.drivers.elastic.definitions.mappings.path', $mappingsPath);
        config()->set('audit.drivers.elastic.definitions.lifecyclePolicy.path', $lifecyclePath);

        $service = resolve(ElasticsearchAuditService::class);
        static::assertSame('mocked', $service->getIndexName());
    }

    public function testInvalidLifecyclePolicyShapeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Configuration value for key [audit.drivers.elastic.definitions.lifecyclePolicy] must contain a non-empty object at [policy.phases].',
        );
        $path = __DIR__.'/../Fixtures/elasticsearch/invalid-lifecycle-policy.json';
        config()->set('audit.drivers.elastic.definitions.lifecyclePolicy.path', $path);

        resolve(ElasticsearchAuditService::class);
    }

    public function testInvalidImplementationThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Configuration value for key [audit.implementation] must be a class-string implementing',
        );
        config()->set('audit.implementation', \stdClass::class);

        resolve(ElasticsearchAuditService::class);
    }

    public function testRetryConfigNumericStringsAreNormalized(): void
    {
        config()->set('audit.drivers.elastic.singleWriteRetry.maxAttempts', 0);
        config()->set('audit.drivers.elastic.singleWriteRetry.initialBackoffMs', -25);
        config()->set('audit.drivers.elastic.singleWriteRetry.maxBackoffMs', -1);
        config()->set('audit.drivers.elastic.singleWriteRetry.backoffMultiplier', '3.5');
        config()->set('audit.drivers.elastic.singleWriteRetry.jitterMs', -10);
        /** @var AuditServiceConfig $config */
        $config = resolve(AuditServiceConfig::class);

        static::assertSame(1, $config->singleWriteRetryMaxAttempts);
        static::assertSame(0, $config->singleWriteRetryInitialBackoffMs);
        static::assertSame(0, $config->singleWriteRetryMaxBackoffMs);
        static::assertSame(3.5, $config->singleWriteRetryBackoffMultiplier);
        static::assertSame(0, $config->singleWriteRetryJitterMs);
    }

    public function testRetryBackoffMultiplierMustBeNumeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Configuration value for key [audit.drivers.elastic.singleWriteRetry.backoffMultiplier] must be a numeric value, array given.',
        );
        config()->set('audit.drivers.elastic.singleWriteRetry.backoffMultiplier', []);

        resolve(ElasticsearchAuditService::class);
    }

    public function testTemplateNameAndIndexPatternFallbackToIndex(): void
    {
        config()->set('audit.drivers.elastic.index', 'audit-fallback');
        config()->set('audit.drivers.elastic.dataStream.templateName', '');
        config()->set('audit.drivers.elastic.dataStream.indexPattern', '');
        /** @var AuditServiceConfig $config */
        $config = resolve(AuditServiceConfig::class);

        static::assertSame('audit-fallback_template', $config->dataStreamTemplateName);
        static::assertSame('audit-fallback*', $config->dataStreamIndexPattern);
    }

    public function testSettingsJsonMustDecodeToArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Configuration value for key [audit.drivers.elastic.definitions.settings.path] must decode to a JSON object/array.',
        );
        $path = __DIR__.'/../Fixtures/elasticsearch/invalid-non-array.json';
        config()->set('audit.drivers.elastic.definitions.settings.path', $path);

        resolve(ElasticsearchAuditService::class);
    }
}
