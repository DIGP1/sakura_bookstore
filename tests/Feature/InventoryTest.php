<?php

use App\Models\MovimientoInventario;
use App\Services\InventarioService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function inventoryFixture(int $stock = 5): array
{
    $editorial = DB::table('tbeditoriales')->insertGetId(['nombre' => 'Editorial de prueba'], 'id_editorial');
    $department = DB::table('tbdepartamentos')->insertGetId(['nombre' => 'Departamento de prueba'], 'id_departamento');
    $municipality = DB::table('tbmunicipios')->insertGetId([
        'id_departamento' => $department,
        'nombre' => 'Municipio de prueba',
    ], 'id_municipio');
    $district = DB::table('tbdistritos')->insertGetId([
        'id_municipio' => $municipality,
        'nombre' => 'Distrito de prueba',
    ], 'id_distrito');
    $client = DB::table('tbclientes')->insertGetId([
        'nombre' => 'Cliente de prueba',
        'id_distrito' => $district,
    ], 'id_cliente');
    $product = DB::table('tbproductos')->insertGetId([
        'isbn' => (string) random_int(1000000000000, 9999999999999),
        'nombre' => 'Libro de prueba',
        'id_editorial' => $editorial,
        'stock' => $stock,
        'precio_unitario' => 25,
        'costo_promedio' => 10,
    ], 'id_producto');
    $activeStatus = DB::table('tbestado_ventas')->insertGetId(['nombre' => 'PENDIENTE'], 'id_estado_ventas');
    $cancelledStatus = DB::table('tbestado_ventas')->insertGetId(['nombre' => 'ANULADA'], 'id_estado_ventas');
    $saleType = DB::table('tbtipo_movimiento')->insertGetId(['nombre' => 'SALIDA_VENTA'], 'id_tipo_movimiento');
    $cancelType = DB::table('tbtipo_movimiento')->insertGetId(['nombre' => 'ENTRADA_ANULACION'], 'id_tipo_movimiento');

    return compact('client', 'product', 'activeStatus', 'cancelledStatus', 'saleType', 'cancelType');
}

test('a sale deducts stock and cancellation restores it in the kardex', function () {
    $fixture = inventoryFixture();

    $sale = app(InventarioService::class)->createSale([
        'id_cliente' => $fixture['client'],
        'id_estado_ventas' => $fixture['activeStatus'],
    ], [[
        'id_producto' => $fixture['product'],
        'cantidad' => 2,
        'precio_unitario' => 25,
    ]]);

    expect(DB::table('tbproductos')->where('id_producto', $fixture['product'])->value('stock'))->toBe(3);
    $movement = MovimientoInventario::query()->where('id_detalle_venta', $sale->detalles->first()->getKey())->firstOrFail();
    expect($movement->cantidad)->toBe(-2);
    expect(fn () => $movement->update(['observacion' => 'modificado']))->toThrow(LogicException::class);
    expect(fn () => $movement->delete())->toThrow(LogicException::class);

    app(InventarioService::class)->cancelSale($sale);

    expect(DB::table('tbproductos')->where('id_producto', $fixture['product'])->value('stock'))->toBe(5);
    expect(MovimientoInventario::query()->where('id_detalle_venta', $sale->detalles->first()->getKey())->count())->toBe(2);
});

test('a sale with insufficient stock is rolled back completely', function () {
    $fixture = inventoryFixture(1);

    expect(fn () => app(InventarioService::class)->createSale([
        'id_cliente' => $fixture['client'],
        'id_estado_ventas' => $fixture['activeStatus'],
    ], [[
        'id_producto' => $fixture['product'],
        'cantidad' => 2,
        'precio_unitario' => 25,
    ]]))->toThrow(ValidationException::class);

    expect(DB::table('tbventas')->count())->toBe(0);
    expect(DB::table('tbmovimientos_inventario')->count())->toBe(0);
    expect(DB::table('tbproductos')->where('id_producto', $fixture['product'])->value('stock'))->toBe(1);
});
