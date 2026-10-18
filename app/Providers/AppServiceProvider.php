<?php

namespace App\Providers;

use App\Database\PostgresConnection;
use App\Services\OfertaService;
use Illuminate\Database\Connection;
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

        // Una sola instancia por petición: las ofertas se consultan una vez por página.
        $this->app->scoped(OfertaService::class);

        // Booleanos compatibles con EMULATE_PREPARES (ver App\Database\PostgresConnection).
        Connection::resolverFor('pgsql', fn ($pdo, $database, $prefix, $config) => new PostgresConnection($pdo, $database, $prefix, $config));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}