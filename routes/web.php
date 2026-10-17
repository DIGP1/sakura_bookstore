<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CarritoController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\DevolucionController;
use App\Http\Controllers\UbicacionController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'))->name('home');
Route::get('catalogo', [CatalogoController::class, 'index'])->name('catalogo.index');
Route::get('catalogo/{producto}', [CatalogoController::class, 'show'])->name('catalogo.show');

Route::get('ubicaciones/departamentos/{departamento}/municipios', [UbicacionController::class, 'municipios'])
    ->name('ubicaciones.municipios');

Route::get('ubicaciones/municipios/{municipio}/distritos', [UbicacionController::class, 'distritos'])
    ->name('ubicaciones.distritos');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified', 'role:admin'])->name('dashboard');

Route::prefix('carrito')->name('carrito.')->group(function (): void {
    Route::get('/', [CarritoController::class, 'index'])->name('index');
    Route::post('items', [CarritoController::class, 'store'])->name('items.store');
    Route::patch('items/{idProducto}', [CarritoController::class, 'update'])->name('items.update');
    Route::delete('items/{idProducto}', [CarritoController::class, 'destroy'])->name('items.destroy');
});

Route::middleware(['auth', 'role:cliente'])->group(function (): void {
    Route::get('checkout', [ClienteController::class, 'checkout'])->name('checkout');
    Route::get('mis-pedidos', [ClienteController::class, 'orders'])->name('cliente.pedidos');
    Route::get('devoluciones', [ClienteController::class, 'returns'])->name('cliente.devoluciones');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function (): void {
    Route::get('/', [AdminController::class, 'index'])->name('dashboard');
    Route::post('ventas', [VentaController::class, 'store'])->name('ventas.store');
    Route::post('ventas/{venta}/anular', [VentaController::class, 'cancel'])->name('ventas.cancel');
    Route::post('compras/{compra}/recibir', [CompraController::class, 'receive'])->name('compras.receive');
    Route::post('devoluciones/{devolucion}/aprobar', [DevolucionController::class, 'approve'])->name('devoluciones.approve');
    Route::get('{section}', [AdminController::class, 'section'])
        ->whereIn('section', ['productos', 'ventas', 'compras', 'devoluciones', 'inventario'])
        ->name('section');
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
