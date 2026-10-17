<?php

namespace App\Providers;


use Illuminate\Support\ServiceProvider;
use Laudis\Neo4j\ClientBuilder;
use App\Services\OfertaService;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
