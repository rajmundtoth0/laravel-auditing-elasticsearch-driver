<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Audit Implementation
    |--------------------------------------------------------------------------
    |
    | Define which Audit model implementation should be used.
    |
    */

    'implementation' => OwenIt\Auditing\Models\Audit::class,

    /*
    |--------------------------------------------------------------------------
    | User Keys, Model
    |--------------------------------------------------------------------------
    |
    | Define the User primary key, foreign key and Eloquent model.
    |
    */

    'user' => [
        'primary_key' => 'id',
        'foreign_key' => 'user_id',
        'model'       => App\Models\User::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Resolvers
    |--------------------------------------------------------------------------
    |
    | Define the User, IP Address, User Agent and URL resolver implementations.
    |
    */
    'resolver' => [
        'user'       => OwenIt\Auditing\Resolvers\UserResolver::class,
        'ip_address' => OwenIt\Auditing\Resolvers\IpAddressResolver::class,
        'user_agent' => OwenIt\Auditing\Resolvers\UserAgentResolver::class,
        'url'        => OwenIt\Auditing\Resolvers\UrlResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Events
    |--------------------------------------------------------------------------
    |
    | The Eloquent events that trigger an Audit.
    |
    */

    'events' => [
        'created',
        'updated',
        'deleted',
        'restored',
    ],

    /*
    |--------------------------------------------------------------------------
    | Strict Mode
    |--------------------------------------------------------------------------
    |
    | Enable the strict mode when auditing?
    |
    */

    'strict' => env('AUDIT_STRICT', false),

    /*
    |--------------------------------------------------------------------------
    | Audit Timestamps
    |--------------------------------------------------------------------------
    |
    | Should the created_at, updated_at and deleted_at timestamps be audited?
    |
    */

    'timestamps' => env('AUDIT_TIMESTAMPS', true),

    /*
    |--------------------------------------------------------------------------
    | Audit Threshold
    |--------------------------------------------------------------------------
    |
    | Specify a threshold for the amount of Audit records a model can have.
    | Zero means no limit.
    |
    */

    'threshold' => env('AUDIT_THRESHOLD', 0),

    /*
    |--------------------------------------------------------------------------
    | Audit Driver
    |--------------------------------------------------------------------------
    |
    | The default audit driver used to keep track of changes.
    |
    */

    'driver' => rajmundtoth0\AuditDriver\Services\ElasticsearchAuditService::class,

    /*
    |--------------------------------------------------------------------------
    | Audit Driver Configurations
    |--------------------------------------------------------------------------
    |
    | Available audit drivers and respective configurations.
    |
    */

    'drivers' => [
        'database' => [
            'table'      => 'audits',
            'connection' => null,
        ],
        'elastic' => [
            'hosts' => [
                env('AUDIT_HOST', 'http://0.0.0.0:9200')
            ],
            'userName'     => env('ELASTIC_AUDIT_USER', 'elastic'),
            'password'     => env('ELASTIC_AUDIT_PASSWORD', 'a_very_strong_password'),
            'useBasicAuth' => (bool) env('AUDIT_BASIC_AUTH', true),
            'useCaCert'    => (bool) env('AUDIT_USE_CERT', true),
            'certPath'     => env('AUDIT_CERT_PATH', ''),
            'index'        => env('AUDIT_INDEX', 'laravel_auditing'),
            'type'         => env('AUDIT_TYPE', 'audits'),
            'dateFormat'   => env('AUDIT_DATE_FORMAT', 'yyyy-MM-dd HH:mm:ss'),
        ],
        'queue' => [
            'enabled'    => (bool) env('AUDIT_QUEUE_ENABLED', false),
            'connection' => env('AUDIT_QUEUE_CONNECTION', false),
            'name'       => env('AUDIT_QUEUE_NAME', 'audits'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Console
    |--------------------------------------------------------------------------
    |
    | Whether console events should be audited (eg. php artisan db:seed).
    |
    */

    'console' => false,
];
