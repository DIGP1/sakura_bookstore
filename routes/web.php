<?php

use App\Http\Controllers\UbicacionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('ubicaciones/departamentos/{departamento}/municipios', [UbicacionController::class, 'municipios'])
    ->name('ubicaciones.municipios');

Route::get('ubicaciones/municipios/{municipio}/distritos', [UbicacionController::class, 'distritos'])
    ->name('ubicaciones.distritos');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified', 'role:admin'])->name('dashboard');

Route::middleware(['auth', 'role:cliente'])->group(function () {
    // para los pedidos y devoluciones
});

Route::get('/test-neo4j', function () {
    $client = app('neo4j');

    // Crea un nodo de prueba
    $client->run('CREATE (n:Prueba {mensaje: "Conexión exitosa desde Laravel"}) RETURN n');

    // Lee el nodo
    $resultado = $client->run('MATCH (n:Prueba) RETURN n.mensaje AS mensaje');

    return $resultado->first()->get('mensaje');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
