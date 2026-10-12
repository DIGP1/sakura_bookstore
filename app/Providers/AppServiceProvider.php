<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laudis\Neo4j\ClientBuilder;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('neo4j', function ($app) {
            $host = env('NEO4J_HOST');
            $port = env('NEO4J_PORT');
            $user = env('NEO4J_USERNAME');
            $pass = env('NEO4J_PASSWORD');

            return ClientBuilder::create()
                ->withDriver('default', "bolt://{$user}:{$pass}@{$host}:{$port}")
                ->build();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
