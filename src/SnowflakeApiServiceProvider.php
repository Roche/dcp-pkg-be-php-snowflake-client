<?php

namespace Dcp\SnowflakeClient;

use Illuminate\Support\ServiceProvider;

use Dcp\SnowflakeClient\SnowflakeApiClient;
use Dcp\SnowflakeClient\QueryBuilder\BaseQueryBuilder;

class SnowflakeApiServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Merge the configuration file if it exists
        $this->mergeConfigFrom(
            __DIR__ . '/config/snowflakeapiclient.php',
            'snowflakeapiclient'
        );

        // Register TokenService
        $this->app->singleton(SnowflakeApiClient::class, function ($app) {
            return new SnowflakeApiClient(
                $app->make(BaseQueryBuilder::class)
            );
        });
    }

    public function boot()
    {
        // Publish the configuration file for the user
        $this->publishes([
            __DIR__ . '/config/snowflakeapiclient.php' => config_path('snowflakeapiclient.php'),
        ]);
    }
}
