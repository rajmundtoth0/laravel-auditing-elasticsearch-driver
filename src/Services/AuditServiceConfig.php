<?php

namespace rajmundtoth0\AuditDriver\Services;

use InvalidArgumentException;
use OwenIt\Auditing\Contracts\Audit;
use rajmundtoth0\AuditDriver\Enums\ElasticsearchStorageMode;

final class AuditServiceConfig
{
    /**
     * @param array<mixed> $hosts
     * @param array<mixed> $settings
     * @param array<mixed> $mappings
     * @param null|array<mixed> $dataStreamLifecyclePolicy
     * @param class-string<Audit> $implementation
     */
    public function __construct(
        public readonly array $hosts,
        public readonly bool $useBasicAuth,
        public readonly string $userName,
        public readonly string $password,
        public readonly bool $useCaCert,
        public readonly string $certPath,
        public readonly bool $singleWriteRetryEnabled,
        public readonly int $singleWriteRetryMaxAttempts,
        public readonly int $singleWriteRetryInitialBackoffMs,
        public readonly int $singleWriteRetryMaxBackoffMs,
        public readonly float $singleWriteRetryBackoffMultiplier,
        public readonly int $singleWriteRetryJitterMs,
        public readonly string $index,
        public readonly ElasticsearchStorageMode $storageMode,
        public readonly array $settings,
        public readonly array $mappings,
        public readonly string $dataStreamTemplateName,
        public readonly string $dataStreamIndexPattern,
        public readonly int $dataStreamTemplatePriority,
        public readonly string $dataStreamLifecyclePolicyName,
        public readonly ?array $dataStreamLifecyclePolicy,
        public readonly string $dataStreamPipeline,
        public readonly string $implementation,
        public readonly bool $useQueue,
        public readonly string $queueName,
        public readonly string $queueConnection,
    ) {
        if (!class_exists($this->implementation) || !is_subclass_of($this->implementation, Audit::class)) {
            throw new InvalidArgumentException(sprintf(
                'Configuration value for key [audit.implementation] must be a class-string implementing [%s], [%s] given.',
                Audit::class,
                $this->implementation,
            ));
        }
    }
}
