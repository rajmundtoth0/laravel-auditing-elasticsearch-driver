<?php

namespace rajmundtoth0\AuditDriver\Services;

use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use OwenIt\Auditing\Contracts\Audit;
use rajmundtoth0\AuditDriver\Enums\ElasticsearchStorageMode;

final class AuditServiceConfigResolver
{
    private const DEFAULT_DEFINITION_DIRECTORY = __DIR__.'/../../resources/elasticsearch/';

    public function __construct(
        private readonly AuditJsonDefinitionResolver $jsonDefinitionResolver,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function resolve(): AuditServiceConfig
    {
        $retry    = $this->resolveRetryConfig();
        $settings = $this->jsonDefinitionResolver->resolve(
            configKeyPrefix: 'audit.drivers.elastic.definitions.settings',
            defaultPath: self::DEFAULT_DEFINITION_DIRECTORY.'settings.json',
            required: true,
        );
        $mappings = $this->jsonDefinitionResolver->resolve(
            configKeyPrefix: 'audit.drivers.elastic.definitions.mappings',
            defaultPath: self::DEFAULT_DEFINITION_DIRECTORY.'mappings.json',
            required: true,
        );
        assert(is_array($settings));
        assert(is_array($mappings));
        $dataStreamLifecyclePolicy = $this->jsonDefinitionResolver->resolve(
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

        return new AuditServiceConfig(
            hosts: Config::array('audit.drivers.elastic.hosts', ['http://localhost:9200']),
            useBasicAuth: Config::boolean('audit.drivers.elastic.useBasicAuth', false),
            userName: Config::string('audit.drivers.elastic.userName', ''),
            password: Config::string('audit.drivers.elastic.password', ''),
            useCaCert: Config::boolean('audit.drivers.elastic.useCaCert', false),
            certPath: Config::string('audit.drivers.elastic.certPath', ''),
            singleWriteRetryEnabled: Config::boolean('audit.drivers.elastic.singleWriteRetry.enabled', true),
            singleWriteRetryMaxAttempts: $retry['maxAttempts'],
            singleWriteRetryInitialBackoffMs: $retry['initialBackoffMs'],
            singleWriteRetryMaxBackoffMs: $retry['maxBackoffMs'],
            singleWriteRetryBackoffMultiplier: $retry['backoffMultiplier'],
            singleWriteRetryJitterMs: $retry['jitterMs'],
            index: Config::string('audit.drivers.elastic.index', 'laravel_auditing'),
            storageMode: ElasticsearchStorageMode::from(Config::string(
                'audit.drivers.elastic.storageMode',
                ElasticsearchStorageMode::Index->value,
            )),
            settings: $settings,
            mappings: $mappings,
            dataStreamTemplateName: Config::string('audit.drivers.elastic.dataStream.templateName', ''),
            dataStreamIndexPattern: Config::string('audit.drivers.elastic.dataStream.indexPattern', ''),
            dataStreamTemplatePriority: Config::integer('audit.drivers.elastic.dataStream.templatePriority', 100),
            dataStreamLifecyclePolicyName: Config::string('audit.drivers.elastic.dataStream.lifecyclePolicyName', ''),
            dataStreamLifecyclePolicy: $dataStreamLifecyclePolicy,
            dataStreamPipeline: Config::string('audit.drivers.elastic.dataStream.pipeline', ''),
            implementation: Config::string('audit.implementation', Audit::class),
            useQueue: Config::boolean('audit.drivers.queue.enabled', false),
            queueName: Config::string('audit.drivers.queue.name', ''),
            queueConnection: Config::string('audit.drivers.queue.connection', ''),
        );
    }

    /**
     * @param array<mixed> $lifecyclePolicy
     *
     * @throws InvalidArgumentException
     */
    private function validateLifecyclePolicyConfig(array $lifecyclePolicy, string $configKey): void
    {
        if (!array_key_exists('policy', $lifecyclePolicy) || !is_array($lifecyclePolicy['policy'])) {
            throw new InvalidArgumentException(
                sprintf('Configuration value for key [%s] must contain an object at [policy].', $configKey),
            );
        }

        if (
            !array_key_exists('phases', $lifecyclePolicy['policy'])
            || !is_array($lifecyclePolicy['policy']['phases'])
            || [] === $lifecyclePolicy['policy']['phases']
        ) {
            throw new InvalidArgumentException(
                sprintf('Configuration value for key [%s] must contain a non-empty object at [policy.phases].', $configKey),
            );
        }
    }

    /**
     * @param array<mixed> $retry
     *
     * @throws InvalidArgumentException
     */
    private function getRetryFloatConfigValue(
        array $retry,
        string $field,
        string $configKey,
        float $defaultValue,
    ): float {
        if (!array_key_exists($field, $retry)) {
            return $defaultValue;
        }

        $value = $retry[$field];
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        throw new InvalidArgumentException(
            sprintf(
                'Configuration value for key [%s.%s] must be a numeric value, %s given.',
                $configKey,
                $field,
                gettype($value),
            ),
        );
    }

    /**
     * @param array<mixed> $retry
     *
     * @throws InvalidArgumentException
     */
    private function getRetryIntConfigValue(
        array $retry,
        string $field,
        string $configKey,
        int $defaultValue,
    ): int {
        if (!array_key_exists($field, $retry)) {
            return $defaultValue;
        }

        $value = $retry[$field];
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        if (is_string($value) && 1 === preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        throw new InvalidArgumentException(
            sprintf(
                'Configuration value for key [%s.%s] must be an integer value, %s given.',
                $configKey,
                $field,
                gettype($value),
            ),
        );
    }

    /**
     * @return array{
     *     maxAttempts: int,
     *     initialBackoffMs: int,
     *     maxBackoffMs: int,
     *     backoffMultiplier: float,
     *     jitterMs: int,
     * }
     *
     * @throws InvalidArgumentException
     */
    private function resolveRetryConfig(): array
    {
        $configKeyPrefix = 'audit.drivers.elastic.definitions.singleWriteRetry';
        $retry           = $this->jsonDefinitionResolver->resolve(
            configKeyPrefix: $configKeyPrefix,
            defaultPath: self::DEFAULT_DEFINITION_DIRECTORY.'single-write-retry.json',
            required: true,
        );
        assert(is_array($retry));

        $initialBackoffMs = max(
            $this->getRetryIntConfigValue($retry, 'initialBackoffMs', $configKeyPrefix, 100),
            0,
        );
        $maxBackoffMs = max(
            $this->getRetryIntConfigValue($retry, 'maxBackoffMs', $configKeyPrefix, 2000),
            0,
        );
        if ($maxBackoffMs < $initialBackoffMs) {
            $maxBackoffMs = $initialBackoffMs;
        }

        return [
            'maxAttempts' => max(
                $this->getRetryIntConfigValue($retry, 'maxAttempts', $configKeyPrefix, 3),
                1,
            ),
            'initialBackoffMs'  => $initialBackoffMs,
            'maxBackoffMs'      => $maxBackoffMs,
            'backoffMultiplier' => max(
                $this->getRetryFloatConfigValue($retry, 'backoffMultiplier', $configKeyPrefix, 2.0),
                1.0,
            ),
            'jitterMs' => max(
                $this->getRetryIntConfigValue($retry, 'jitterMs', $configKeyPrefix, 25),
                0,
            ),
        ];
    }
}
