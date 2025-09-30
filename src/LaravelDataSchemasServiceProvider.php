<?php

namespace Rushing\LaravelDataSchemas;

use Illuminate\Support\ServiceProvider;
use Rushing\LaravelDataSchemas\Commands\GenerateJsonSchemaCommand;

class LaravelDataSchemasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/data-schemas.php',
            'data-schemas'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/data-schemas.php' => config_path('data-schemas.php'),
            ], 'data-schemas-config');

            $this->commands([
                GenerateJsonSchemaCommand::class,
            ]);
        }
    }
}