[![Version](https://poser.pugx.org/rajmundtoth0/laravel-auditing-elasticsearch-driver/version)](https://packagist.org/packages/rajmundtoth0/laravel-auditing-elasticsearch-driver)
[![codecov](https://codecov.io/gh/rajmundtoth0/laravel-auditing-elasticsearch-driver/graph/badge.svg?token=X6X3UEP77B)](https://codecov.io/gh/rajmundtoth0/laravel-auditing-elasticsearch-driver)
![PHPStan](https://img.shields.io/badge/PHPStan-Level_MAX-brightgreen)
[![Build](https://github.com/rajmundtoth0/laravel-auditing-elasticsearch-driver/actions/workflows/php.yml/badge.svg)](https://github.com/rajmundtoth0/laravel-auditing-elasticsearch-driver/actions/workflows/php.yml)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/16db5d58566c47ad99bac0bc1373997d)](https://app.codacy.com?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
[![PHP Version Require](https://poser.pugx.org/rajmundtoth0/laravel-auditing-elasticsearch-driver/require/php)](https://packagist.org/packages/rajmundtoth0/laravel-auditing-elasticsearch-driver)
[![License](https://poser.pugx.org/rajmundtoth0/laravel-auditing-elasticsearch-driver/license)](https://packagist.org/packages/rajmundtoth0/laravel-auditing-elasticsearch-driver)
[![Total Downloads](https://poser.pugx.org/rajmundtoth0/laravel-auditing-elasticsearch-driver/downloads)](https://packagist.org/packages/rajmundtoth0/laravel-auditing-elasticsearch-driver)

# Laravel Auditing Elasticsearch Driver

Elasticsearch driver for [Laravel Auditing](https://laravel-auditing.com/), with support for both classic indices and Elasticsearch data streams.

## Highlights

- Index or data stream storage mode (`index` / `data_stream`)
- Optional ILM lifecycle policy + index template management for data streams
- Queue-aware indexing
- Typed configuration and strict static analysis support
- CI coverage for feature and integration test suites

## Requirements

| Dependency | Supported |
| --- | --- |
| PHP | `>=8.2` |
| Laravel | `^11 \| ^12` |
| elasticsearch/elasticsearch | `^8.0 \| ^9.0` |
| owen-it/laravel-auditing | `^13.0 \| ^14.0` |

## Installation

```bash
composer require rajmundtoth0/laravel-auditing-elasticsearch-driver
```

If you need to publish package config:

```bash
php artisan vendor:publish --provider="rajmundtoth0\\AuditDriver\\ElasticsearchAuditingServiceProvider"
```

Set Laravel Auditing to use this driver in `config/audit.php`:

```php
'driver' => rajmundtoth0\AuditDriver\Services\ElasticsearchAuditService::class,
```

## Configuration

Use the `drivers.elastic` section in `config/audit.php`:

```php
'elastic' => [
    'hosts' => [env('AUDIT_HOST', 'http://0.0.0.0:9200')],
    'userName' => env('ELASTIC_AUDIT_USER', 'elastic'),
    'password' => env('ELASTIC_AUDIT_PASSWORD', 'a_very_strong_password'),
    'useBasicAuth' => (bool) env('AUDIT_BASIC_AUTH', true),
    'useCaCert' => (bool) env('AUDIT_USE_CERT', true),
    'certPath' => env('AUDIT_CERT_PATH', ''),

    'index' => env('AUDIT_INDEX', 'laravel_auditing'),
    'storageMode' => env('AUDIT_STORAGE_MODE', 'index'), // index|data_stream
    'definitions' => [
        'settings' => [
            'path' => env('AUDIT_SETTINGS_PATH', ''),
            'json' => env('AUDIT_SETTINGS_JSON', ''),
        ],
        'mappings' => [
            'path' => env('AUDIT_MAPPINGS_PATH', ''),
            'json' => env('AUDIT_MAPPINGS_JSON', ''),
        ],
        'lifecyclePolicy' => [
            'path' => env('AUDIT_LIFECYCLE_POLICY_PATH', ''),
            'json' => env('AUDIT_LIFECYCLE_POLICY_JSON', ''),
        ],
        'singleWriteRetry' => [
            'path' => env('AUDIT_SINGLE_WRITE_RETRY_PATH', ''),
            'json' => env('AUDIT_SINGLE_WRITE_RETRY_JSON', ''),
        ],
    ],

    'dataStream' => [
        'templateName' => env('AUDIT_DATA_STREAM_TEMPLATE_NAME', ''),
        'indexPattern' => env('AUDIT_DATA_STREAM_INDEX_PATTERN', ''),
        'templatePriority' => (int) env('AUDIT_DATA_STREAM_TEMPLATE_PRIORITY', 100),
        'lifecyclePolicyName' => env('AUDIT_DATA_STREAM_LIFECYCLE_POLICY', ''),
        'pipeline' => env('AUDIT_DATA_STREAM_PIPELINE', ''),
    ],
    'singleWriteRetry' => [
        'enabled' => (bool) env('AUDIT_SINGLE_WRITE_RETRY_ENABLED', true),
    ],
],
```

### JSON Definitions

Default JSON definitions are stored in:

- `resources/elasticsearch/settings.json`
- `resources/elasticsearch/mappings.json`
- `resources/elasticsearch/lifecycle-policy.json`
- `resources/elasticsearch/single-write-retry.json`

`mappings.json` defines `old_values` and `new_values` as dynamic objects, so model-specific audit keys can be indexed without predefined fields.

The driver resolves each definition in this order:

1. Inline JSON from `definitions.*.json`
2. File path from `definitions.*.path`
3. Package default JSON file in `resources/elasticsearch/`

Inline JSON override example:

```php
'definitions' => [
    'settings' => [
        'json' => '{"number_of_shards":1,"number_of_replicas":0}',
    ],
    'mappings' => [
        'json' => '{"properties":{"created_at":{"type":"date"}}}',
    ],
    'lifecyclePolicy' => [
        'json' => '{"policy":{"phases":{"hot":{"actions":{}}}}}',
    ],
    'singleWriteRetry' => [
        'json' => '{"maxAttempts":5,"initialBackoffMs":50,"maxBackoffMs":1500,"backoffMultiplier":2.0,"jitterMs":20}',
    ],
],
```

File path override example:

```php
'definitions' => [
    'settings' => [
        'path' => base_path('infra/elasticsearch/settings.json'),
    ],
    'mappings' => [
        'path' => base_path('infra/elasticsearch/mappings.json'),
    ],
    'lifecyclePolicy' => [
        'path' => base_path('infra/elasticsearch/lifecycle.json'),
    ],
    'singleWriteRetry' => [
        'path' => base_path('infra/elasticsearch/retry.json'),
    ],
],
```

### Storage Mode Behavior

- `index` mode: setup creates index + write alias.
- `data_stream` mode: setup creates/updates template and optional ILM policy; Elasticsearch auto-creates the data stream on first write.

Note: in `data_stream` mode, the driver auto-populates `@timestamp` if missing.

### Single-Write Retries

Single document writes use retries with exponential backoff for transient failures (`408`, `429`, `5xx`, node-unavailable).

Retry timing values are resolved from `definitions.singleWriteRetry` (inline JSON, file path, or package default), while `singleWriteRetry.enabled` controls whether retries are active.

- `maxAttempts`: total attempts, including the first call
- `initialBackoffMs`: delay before first retry
- `backoffMultiplier`: exponential factor per retry
- `maxBackoffMs`: upper bound for delay
- `jitterMs`: random `+/-` jitter to avoid synchronized retry spikes

In `data_stream` mode, `op_type=create` conflicts (`409`) are treated as success to keep retries idempotent.

## Setup Command

Run once after configuration changes:

```bash
php artisan es-audit-log:setup
```

## Lifecycle Policy Example

If you want the package to create/update an ILM policy, set `lifecyclePolicyName` and provide the policy through `definitions.lifecyclePolicy` (JSON or file path):

```php
'dataStream' => [
    'lifecyclePolicyName' => 'audits-hot-delete',
],
'definitions' => [
    'lifecyclePolicy' => [
        'path' => base_path('infra/elasticsearch/lifecycle.json'),
    ],
],
```

Lifecycle definitions are validated at boot and must contain `policy.phases` as a non-empty object.

## Usage

Add `OwenIt\Auditing\Auditable` to your model as usual.

To read Elasticsearch audit logs from your model:

- `$model->audit_log`
- `$model->elasticsearchAuditLog($page, $pageSize, $sort)`

These methods are provided by [`ElasticSearchAuditable`](src/Traits/ElasticSearchAuditable.php).

## Queue Support

If `drivers.queue.enabled` is `true`, audit documents are queued and indexed asynchronously.

## Testing

```bash
composer test
composer analyse
```

Integration tests (against real Elasticsearch):

```bash
AUDIT_RUN_INTEGRATION_TESTS=true AUDIT_INTEGRATION_HOST=http://localhost:9200 composer test-integration
```

## Contributing

Open pull requests against the `master` branch.
