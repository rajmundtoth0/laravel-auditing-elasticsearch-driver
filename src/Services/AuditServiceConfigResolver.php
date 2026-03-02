<?php

namespace rajmundtoth0\AuditDriver\Services;

use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use JsonException;
use OwenIt\Auditing\Contracts\Audit;
use rajmundtoth0\AuditDriver\Enums\ElasticsearchStorageMode;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverArgumentException;
use Throwable;

final class AuditServiceConfigResolver
{
    private const DEFAULT_DEFINITION_DIRECTORY = __DIR__.'/../../resources/elasticsearch/';

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function resolve(): AuditServiceConfig
    {
        $index          = Config::string('audit.drivers.elastic.index', 'laravel_auditing');
        $storageMode    = $this->resolveStorageMode();
        $implementation = $this->resolveImplementation();
        $this->assertLegacyLifecyclePolicyIsNotConfigured();
        $settings = $this->requiredJsonConfig(
            configKeyPrefix: 'audit.drivers.elastic.definitions.settings',
            defaultPath: self::DEFAULT_DEFINITION_DIRECTORY.'settings.json',
        );
        $mappings = $this->requiredJsonConfig(
            configKeyPrefix: 'audit.drivers.elastic.definitions.mappings',
            defaultPath: self::DEFAULT_DEFINITION_DIRECTORY.'mappings.json',
        );
        $dataStreamLifecyclePolicy = $this->getJsonConfig(
            configKeyPrefix: 'audit.drivers.elastic.definitions.lifecyclePolicy',
            defaultPath: self::DEFAULT_DEFINITION_DIRECTORY.'lifecycle-policy.json',
            required: false,
        );
        if (is_array($dataStreamLifecyclePolicy)) {
            $this->validateLifecyclePolicyConfig(
                lifecyclePolicy: $dataStreamLifecyclePolicy,
                configKey: 'audit.drivers.elastic.definitions.lifecyclePolicy',
            );
        }
        $singleWriteRetryInitialBackoffMs = max(
            Config::integer('audit.drivers.elastic.singleWriteRetry.initialBackoffMs', 100),
            0,
        );

        return new AuditServiceConfig(
            hosts: Config::array('audit.drivers.elastic.hosts', ['http://localhost:9200']),
            useBasicAuth: Config::boolean('audit.drivers.elastic.useBasicAuth', false),
            userName: Config::string('audit.drivers.elastic.userName', ''),
            password: Config::string('audit.drivers.elastic.password', ''),
            useCaCert: Config::boolean('audit.drivers.elastic.useCaCert', false),
            certPath: Config::string('audit.drivers.elastic.certPath', ''),
            singleWriteRetryEnabled: Config::boolean('audit.drivers.elastic.singleWriteRetry.enabled', true),
            singleWriteRetryMaxAttempts: max(
                Config::integer('audit.drivers.elastic.singleWriteRetry.maxAttempts', 3),
                1,
            ),
            singleWriteRetryInitialBackoffMs: $singleWriteRetryInitialBackoffMs,
            singleWriteRetryMaxBackoffMs: max(
                Config::integer('audit.drivers.elastic.singleWriteRetry.maxBackoffMs', 2000),
                $singleWriteRetryInitialBackoffMs,
            ),
            singleWriteRetryBackoffMultiplier: max(
                $this->getFloatConfigValue('audit.drivers.elastic.singleWriteRetry.backoffMultiplier', 2.0),
                1.0,
            ),
            singleWriteRetryJitterMs: max(
                Config::integer('audit.drivers.elastic.singleWriteRetry.jitterMs', 25),
                0,
            ),
            index: $index,
            storageMode: $storageMode,
            settings: $settings,
            mappings: $mappings,
            dataStreamTemplateName: Config::string('audit.drivers.elastic.dataStream.templateName', '') ?: $index.'_template',
            dataStreamIndexPattern: Config::string('audit.drivers.elastic.dataStream.indexPattern', '') ?: $index.'*',
            dataStreamTemplatePriority: Config::integer('audit.drivers.elastic.dataStream.templatePriority', 100),
            dataStreamLifecyclePolicyName: Config::string('audit.drivers.elastic.dataStream.lifecyclePolicyName', ''),
            dataStreamLifecyclePolicy: $dataStreamLifecyclePolicy,
            dataStreamPipeline: Config::string('audit.drivers.elastic.dataStream.pipeline', ''),
            implementation: $implementation,
            useQueue: Config::boolean('audit.drivers.queue.enabled', false),
            queueName: Config::string('audit.drivers.queue.name', ''),
            queueConnection: Config::string('audit.drivers.queue.connection', ''),
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    private function resolveStorageMode(): ElasticsearchStorageMode
    {
        $storageModeValue = Config::string(
            'audit.drivers.elastic.storageMode',
            ElasticsearchStorageMode::Index->value,
        );

        try {
            return ElasticsearchStorageMode::from($storageModeValue);
        } catch (Throwable $exception) {
            throw new AuditDriverArgumentException(
                sprintf(
                    'Invalid value [%s] for [audit.drivers.elastic.storageMode]. Allowed values: %s, %s.',
                    $storageModeValue,
                    ElasticsearchStorageMode::Index->value,
                    ElasticsearchStorageMode::DataStream->value,
                ),
                previous: $exception,
            );
        }
    }

    /**
     * @return class-string<Audit>
     *
     * @throws InvalidArgumentException
     */
    private function resolveImplementation(): string
    {
        $implementation = Config::string('audit.implementation', Audit::class);
        if (!class_exists($implementation) || !is_subclass_of($implementation, Audit::class)) {
            throw new AuditDriverArgumentException(sprintf(
                'Configuration value for key [audit.implementation] must be a class-string implementing [%s], [%s] given.',
                Audit::class,
                $implementation,
            ));
        }

        return $implementation;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertLegacyLifecyclePolicyIsNotConfigured(): void
    {
        if (null !== Config::get('audit.drivers.elastic.dataStream.lifecyclePolicy')) {
            throw new AuditDriverArgumentException(
                'Configuration value for key [audit.drivers.elastic.dataStream.lifecyclePolicy] is no longer supported. Use [audit.drivers.elastic.definitions.lifecyclePolicy] instead.',
            );
        }
    }

    /**
     * @return null|array<mixed>
     *
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function getJsonConfig(string $configKeyPrefix, string $defaultPath, bool $required): ?array
    {
        $configuredPath = trim(Config::string($configKeyPrefix.'.path', ''));
        $path           = $configuredPath ?: $defaultPath;
        if (!$path) {
            if ($required) {
                throw new AuditDriverArgumentException(
                    sprintf('Configuration value for key [%s.path] must be a valid file path.', $configKeyPrefix),
                );
            }

            return null;
        }

        if (!is_file($path)) {
            throw new AuditDriverArgumentException(
                sprintf('JSON definition file [%s] does not exist for key [%s.path].', $path, $configKeyPrefix),
            );
        }

        $fileContent = file_get_contents($path);
        if (false === $fileContent) {
            throw new AuditDriverArgumentException(
                sprintf('Unable to read JSON definition file [%s] for key [%s.path].', $path, $configKeyPrefix),
            );
        }

        $decoded = json_decode($fileContent, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new AuditDriverArgumentException(
                sprintf('Configuration value for key [%s.path] must decode to a JSON object/array.', $configKeyPrefix),
            );
        }

        return $decoded;
    }

    /**
     * @return array<mixed>
     *
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    private function requiredJsonConfig(string $configKeyPrefix, string $defaultPath): array
    {
        $resolved = $this->getJsonConfig(
            configKeyPrefix: $configKeyPrefix,
            defaultPath: $defaultPath,
            required: true,
        );
        assert(is_array($resolved));

        return $resolved;
    }

    /**
     * @param array<mixed> $lifecyclePolicy
     *
     * @throws InvalidArgumentException
     */
    private function validateLifecyclePolicyConfig(array $lifecyclePolicy, string $configKey): void
    {
        if (!array_key_exists('policy', $lifecyclePolicy) || !is_array($lifecyclePolicy['policy'])) {
            throw new AuditDriverArgumentException(
                sprintf('Configuration value for key [%s] must contain an object at [policy].', $configKey),
            );
        }

        if (
            !array_key_exists('phases', $lifecyclePolicy['policy'])
            || !is_array($lifecyclePolicy['policy']['phases'])
            || [] === $lifecyclePolicy['policy']['phases']
        ) {
            throw new AuditDriverArgumentException(
                sprintf('Configuration value for key [%s] must contain a non-empty object at [policy.phases].', $configKey),
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getFloatConfigValue(string $key, float $defaultValue): float
    {
        $value = Config::get($key, $defaultValue);
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        throw new AuditDriverArgumentException(
            sprintf('Configuration value for key [%s] must be a numeric value, %s given.', $key, gettype($value)),
        );
    }
}

