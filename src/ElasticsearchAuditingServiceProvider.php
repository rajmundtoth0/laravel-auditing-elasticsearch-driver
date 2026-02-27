<?php

namespace rajmundtoth0\AuditDriver;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use rajmundtoth0\AuditDriver\Console\ElasticsearchSetupCommand;
use rajmundtoth0\AuditDriver\Services\AuditServiceConfig;
use rajmundtoth0\AuditDriver\Services\AuditServiceConfigResolver;
use rajmundtoth0\AuditDriver\Services\ElasticsearchAuditService;

class ElasticsearchAuditingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditServiceConfig::class, fn (Container $app): AuditServiceConfig => $app->make(AuditServiceConfigResolver::class)->resolve());

        $this->app->singleton(ElasticsearchAuditService::class);
        if ($this->app->runningInConsole()) {
            $this->commands([
                ElasticsearchSetupCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/audit.php' => config_path('audit.php'),
            ]);
        }
    }
}
