[![Version](https://poser.pugx.org/rajmundtoth0/laravel-auditing-elasticsearch-driver/version)](https://packagist.org/packages/rajmundtoth0/laravel-auditing-elasticsearch-driver)
[![codecov](https://codecov.io/gh/rajmundtoth0/laravel-auditing-elasticsearch-driver/graph/badge.svg?token=X6X3UEP77B)](https://codecov.io/gh/rajmundtoth0/laravel-auditing-elasticsearch-driver)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%209-brightgreen.svg?style=flat&logo=php)](https://shields.io/#/)
[![Build](https://github.com/rajmundtoth0/laravel-auditing-elasticsearch-driver/actions/workflows/php.yml/badge.svg)](https://github.com/rajmundtoth0/laravel-auditing-elasticsearch-driver/actions/workflows/php.yml)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/16db5d58566c47ad99bac0bc1373997d)](https://app.codacy.com?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
[![PHP Version Require](https://poser.pugx.org/rajmundtoth0/laravel-auditing-elasticsearch-driver/require/php)](https://packagist.org/packages/rajmundtoth0/laravel-auditing-elasticsearch-driver)
[![License](https://poser.pugx.org/rajmundtoth0/laravel-auditing-elasticsearch-driver/license)](https://packagist.org/packages/rajmundtoth0/laravel-auditing-elasticsearch-driver)
[![Total Downloads](https://poser.pugx.org/rajmundtoth0/laravel-auditing-elasticsearch-driver/downloads)](https://packagist.org/packages/rajmundtoth0/laravel-auditing-elasticsearch-driver)

# Laravel Auditing Elasticsearch Driver

This is a community elasticsearch driver for [Laravel Auditing](https://laravel-auditing.com/).

## Contents

* [Requirements](#requirements)
* [Installation](#installation)
* [Config](#config)
* [Setup](#setup)
* [Usage](#usage)
* [Contribution](#contribution)

## Requirements

|   | Version |
| ------------- | ------------- |
| `php`  |  `>= 8.2`  |
| `Laravel`  | `^10\|^11`  |
| `elasticsearch/elasticsearch`  | `^8.0`  |
| `owen-it/laravel-auditing`  | `^13.0`  |

## Installation

```
composer require rajmundtoth0/laravel-auditing-elasticsearch-driver
```

## Config

The `driver` key of the config file should look like so:

```
    ...
    'driver' => rajmundtoth0\AuditDriver\Services\ElasticsearchAuditService::class,
    ...
```

The `drivers` key of the config file should look like so:

```
    ...
    'drivers' => [
        'database' => [
            'table'      => 'audits',
            'connection' => null,
        ],
        'elastic' => [
            'hosts' => [
                env('AUDIT_HOST', 'https://0.0.0.0:9200')
            ],
            'userName'     => env('ELASTIC_AUDIT_USER', 'elastic'),
            'password'     => env('ELASTIC_AUDIT_PASSWORD', 'a_very_strong_password'),
            'useBasicAuth' => env('AUDIT_BASIC_AUTH', true),
            'useCaCert'    => env('AUDIT_USE_CERT', true),
            'certPath'     => env('AUDIT_CERT_PATH', false),
            'index'        => env('AUDIT_INDEX', 'laravel_auditing'),
            'type'         => env('AUDIT_TYPE', 'audits'),
        ],
        'queue' => [
            'enabled'    => env('AUDIT_QUEUE_ENABLED', false),
            'connection' => env('AUDIT_QUEUE_CONNECTION', false),
            'name'       => env('AUDIT_QUEUE_NAME', 'audits'),
        ],
    ],
    ...
```

## Setup

Run the following artisan command after installation  to create the Elasticsearch index:
  
`php artisan es-audit-log:setup` 

## Usage

The following structure ensures to store the audits in Elasticsearch:

```
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class SomeModel extends Model implements AuditableContract
{
    use Auditable;
    // ...
}
```

And provides the following ways to retrieve the logs related to the given model:
  
`$someModel->audit_log`
  
`$someModel->elasticsearchAuditLog()`

Located in the `ElasticsearchAuditable` trait.
```
trait ElasticSearchAuditable
{
    /**
     * @return Collection<int, mixed>
     */
    public function elasticsearchAuditLog(int $page = 1, int $pageSize = 10, string $sort = 'desc'): Collection
    {
        /** @var ElasticsearchAuditService */
        $elasticsearchAuditService = resolve(ElasticsearchAuditService::class);
        $result                    = $elasticsearchAuditService->searchAuditDocument(
            model: $this,
            pageSize: $pageSize,
            from: --$page * $pageSize,
            sort: $sort,
        );

        return collect($result->asArray());
    }

    /**
     * @return Collection<int, mixed>
     */
    public function getAuditLogAttribute(): Collection
    {
        return $this->elasticsearchAuditLog();
    }
}
```

Queue:
When the queue configuration is set, the driver will dispatch a job to index each document.

## Contribution

Pull requests has  to be opened against the `master` branch.
