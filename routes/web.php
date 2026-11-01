<?php

use App\Http\Controllers\Admin\CatalogosController;
use App\Http\Controllers\Admin\ClientesController;
use App\Http\Controllers\Admin\ComprasController;
use App\Http\Controllers\Admin\DevolucionesController;
use App\Http\Controllers\Admin\EnviosController;
use App\Http\Controllers\Admin\InventarioController;
use App\Http\Controllers\Admin\LibroController;
use App\Http\Controllers\Admin\OfertasController;
use App\Http\Controllers\Admin\VentasController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CarritoController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\UbicacionController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tienda pública
|--------------------------------------------------------------------------
*/

Route::get('/', InicioController::class)->name('home');
Route::get('catalogo', [CatalogoController::class, 'index'])->name('catalogo.index');
Route::get('catalogo/{producto}/portada', [CatalogoController::class, 'portada'])->name('catalogo.portada');
Route::get('catalogo/{producto}', [CatalogoController::class, 'show'])->name('catalogo.show');

Route::get('ubicaciones/departamentos/{departamento}/municipios', [UbicacionController::class, 'municipios'])
    ->name('ubicaciones.municipios');

Route::get('ubicaciones/municipios/{municipio}/distritos', [UbicacionController::class, 'distritos'])
    ->name('ubicaciones.distritos');

// El inicio del panel vive en /admin; /dashboard queda como atajo.
Route::get('dashboard', fn () => to_route('admin.dashboard'))
    ->middleware(['auth', 'role:admin'])
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| Carrito (funciona sin iniciar sesión)
|--------------------------------------------------------------------------
*/

Route::prefix('carrito')->name('carrito.')->group(function (): void {
    Route::get('/', [CarritoController::class, 'index'])->name('index');
    Route::post('items', [CarritoController::class, 'store'])->name('items.store');
    Route::patch('items/{idProducto}', [CarritoController::class, 'update'])->name('items.update');
    Route::delete('items/{idProducto}', [CarritoController::class, 'destroy'])->name('items.destroy');
});

/*
|--------------------------------------------------------------------------
| Área del cliente
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:cliente'])->group(function (): void {
    Route::get('checkout', [ClienteController::class, 'checkout'])->name('checkout');
    Route::post('checkout', [ClienteController::class, 'confirmar'])->name('checkout.store');
    Route::get('pedidos/{venta}/confirmacion', [ClienteController::class, 'confirmacion'])->name('checkout.confirmacion');
    Route::get('mis-pedidos', [ClienteController::class, 'orders'])->name('cliente.pedidos');
    Route::get('mis-pedidos/{venta}', [ClienteController::class, 'order'])->name('cliente.pedidos.show');
    Route::get('mis-pedidos/{venta}/devolucion', [ClienteController::class, 'createReturn'])->name('cliente.devoluciones.create');
    Route::post('mis-pedidos/{venta}/devolucion', [ClienteController::class, 'storeReturn'])->name('cliente.devoluciones.store');
    Route::get('devoluciones', [ClienteController::class, 'returns'])->name('cliente.devoluciones');
    Route::get('mis-datos', [ClienteController::class, 'editProfile'])->name('cliente.datos');
    Route::patch('mis-datos', [ClienteController::class, 'updateProfile'])->name('cliente.datos.update');
});

/*
|--------------------------------------------------------------------------
| Panel de administración
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function (): void {
    Route::get('/', [AdminController::class, 'index'])->name('dashboard');

    // Libros
    Route::resource('libros', LibroController::class)->except('show')->parameters(['libros' => 'producto']);

    // Ofertas
    Route::resource('ofertas', OfertasController::class)->except('show');
    Route::post('ofertas/{oferta}/estado', [OfertasController::class, 'alternar'])->whereNumber('oferta')->name('ofertas.toggle');

    // Autores, editoriales, categorías y proveedores
    foreach (CatalogosController::TIPOS as $tipo) {
        Route::get($tipo, [CatalogosController::class, 'index'])->defaults('tipo', $tipo)->name("{$tipo}.index");
        Route::post($tipo, [CatalogosController::class, 'store'])->defaults('tipo', $tipo)->name("{$tipo}.store");
        Route::put("{$tipo}/{id}", [CatalogosController::class, 'update'])->defaults('tipo', $tipo)->whereNumber('id')->name("{$tipo}.update");
        Route::delete("{$tipo}/{id}", [CatalogosController::class, 'destroy'])->defaults('tipo', $tipo)->whereNumber('id')->name("{$tipo}.destroy");
    }

    // Compras a proveedores
    Route::get('compras', [ComprasController::class, 'index'])->name('compras.index');
    Route::get('compras/crear', [ComprasController::class, 'create'])->name('compras.create');
    Route::post('compras', [ComprasController::class, 'store'])->name('compras.store');
    Route::get('compras/{compra}', [ComprasController::class, 'show'])->whereNumber('compra')->name('compras.show');
    Route::post('compras/{compra}/recibir', [ComprasController::class, 'recibir'])->whereNumber('compra')->name('compras.receive');
    Route::post('compras/{compra}/anular', [ComprasController::class, 'anular'])->whereNumber('compra')->name('compras.cancel');

    // Inventario
    Route::get('inventario', [InventarioController::class, 'index'])->name('inventario.index');
    Route::get('inventario/{producto}', [InventarioController::class, 'show'])->whereNumber('producto')->name('inventario.show');
    Route::post('inventario/{producto}/ajustes', [InventarioController::class, 'ajustar'])->whereNumber('producto')->name('inventario.ajustar');

    // Ventas y devoluciones
    Route::post('ventas', [VentaController::class, 'store'])->name('ventas.store');
    Route::get('ventas', [VentasController::class, 'index'])->name('ventas.index');
    Route::get('ventas/{venta}', [VentasController::class, 'show'])->whereNumber('venta')->name('ventas.show');
    Route::post('ventas/{venta}/anular', [VentasController::class, 'anular'])->whereNumber('venta')->name('ventas.cancel');

    // Devoluciones
    Route::get('devoluciones', [DevolucionesController::class, 'index'])->name('devoluciones.index');
    Route::get('devoluciones/{devolucion}', [DevolucionesController::class, 'show'])->whereNumber('devolucion')->name('devoluciones.show');
    Route::post('devoluciones/{devolucion}/aprobar', [DevolucionesController::class, 'aprobar'])->whereNumber('devolucion')->name('devoluciones.approve');
    Route::post('devoluciones/{devolucion}/rechazar', [DevolucionesController::class, 'rechazar'])->whereNumber('devolucion')->name('devoluciones.reject');

    // Envíos
    Route::get('envios', [EnviosController::class, 'index'])->name('envios.index');
    Route::get('envios/{envio}', [EnviosController::class, 'show'])->whereNumber('envio')->name('envios.show');
    Route::post('envios/{envio}/estado', [EnviosController::class, 'cambiarEstado'])->whereNumber('envio')->name('envios.estado');

    // Clientes
    Route::get('clientes', [ClientesController::class, 'index'])->name('clientes.index');
    Route::get('clientes/{cliente}', [ClientesController::class, 'show'])->whereNumber('cliente')->name('clientes.show');

});

/*
|--------------------------------------------------------------------------
| Prueba de conexión a Neo4j
|--------------------------------------------------------------------------
*/

// Route::get('/test-neo4j', function () {
//     $client = app('neo4j');

//     // Crea un nodo de prueba
//     $client->run('CREATE (n:Prueba {mensaje: "Conexión exitosa desde Laravel"}) RETURN n');

//     // Lee el nodo
//     $resultado = $client->run('MATCH (n:Prueba) RETURN n.mensaje AS mensaje');

//     return $resultado->first()->get('mensaje');
// });

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
