<?php

namespace Rushing\LaravelDataSchemas;

use Illuminate\Support\ServiceProvider;
use Rushing\LaravelDataSchemas\Commands\GenerateJsonSchemaCommand;
use Rushing\LaravelDataSchemas\Contracts\SchemaRegistry;
use Rushing\LaravelDataSchemas\Lifecycle\FilesystemSchemaRegistry;

class LaravelDataSchemasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/data-schemas.php',
            'data-schemas'
        );

        // The immutable, $id-keyed registry of frozen schema artifacts. Bound to
        // the filesystem implementation by default; swap via the container.
        $this->app->singleton(SchemaRegistry::class, function ($app) {
            $dir = $app['config']->get('data-schemas.registry_directory')
                ?? storage_path('app/schemas/registry');

            return new FilesystemSchemaRegistry($dir);
        });
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
