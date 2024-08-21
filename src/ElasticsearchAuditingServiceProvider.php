<?php

namespace rajmundtoth0\AuditDriver;

use Illuminate\Support\ServiceProvider;
use rajmundtoth0\AuditDriver\Console\ElasticsearchSetupCommand;
use rajmundtoth0\AuditDriver\Services\ElasticsearchAuditService;

class ElasticsearchAuditingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
