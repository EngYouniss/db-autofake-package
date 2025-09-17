<?php

namespace VendorOrg\DbAutofake;

use Illuminate\Support\ServiceProvider;
use VendorOrg\DbAutofake\Console\TableFakeCommand;

class DbAutofakeServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/db_autofake.php', 'db_autofake');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/db_autofake.php' => config_path('db_autofake.php'),
            ], 'db-autofake-config');

            $this->commands([TableFakeCommand::class]);
        }
    }
}
