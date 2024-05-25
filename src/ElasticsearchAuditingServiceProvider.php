<?php

namespace rajmundtoth0\AuditDriver;

use Illuminate\Support\ServiceProvider;
use rajmundtoth0\AuditDriver\Console\ElasticsearchSetupCommand;
use rajmundtoth0\AuditDriver\Services\ElasticsearchAuditService;

class ElasticsearchAuditingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (!$this->app->runningInConsole()) {
            $this->app->singleton(ElasticsearchAuditService::class);
        } else {
            $this->commands([
                ElasticsearchSetupCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/audit.php' => config_path('audit.php'),
            ]);
        }
    }
}
