<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ClientInterface::class, function (): ClientInterface {
            $neo4jConfig = config('database.connections.neo4j');
            $uri = sprintf(
                '%s://%s:%s',
                $neo4jConfig['protocol'],
                $neo4jConfig['host'],
                $neo4jConfig['port'],
            );

            return ClientBuilder::create()
                ->withDriver(
                    'bolt',
                    $uri,
                    Authenticate::basic($neo4jConfig['username'], $neo4jConfig['password']),
                )
                ->withDefaultDriver('bolt')
                ->build();
        });

        $this->app->alias(ClientInterface::class, 'neo4j');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
