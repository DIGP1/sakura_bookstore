<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE tbproductos ADD CONSTRAINT ck_productos_stock_no_negativo CHECK (stock >= 0)');
        DB::statement('ALTER TABLE tbproductos ADD CONSTRAINT ck_productos_stock_minimo_no_negativo CHECK (stock_minimo >= 0)');
        DB::statement('ALTER TABLE tbproductos ADD CONSTRAINT ck_productos_precio_positivo CHECK (precio_unitario > 0)');
        DB::statement('ALTER TABLE tbproductos ADD CONSTRAINT ck_productos_costo_no_negativo CHECK (costo_promedio >= 0)');
        DB::statement('ALTER TABLE tbdetalle_venta ADD CONSTRAINT ck_detalle_venta_cantidad_positiva CHECK (cantidad > 0)');
        DB::statement('ALTER TABLE tbdetalle_venta ADD CONSTRAINT ck_detalle_venta_precio_positivo CHECK (precio_unitario > 0)');
        DB::statement('ALTER TABLE tbdetalle_compras ADD CONSTRAINT ck_detalle_compra_cantidad_positiva CHECK (cantidad > 0)');
        DB::statement('ALTER TABLE tbdetalle_compras ADD CONSTRAINT ck_detalle_compra_costo_positivo CHECK (costo_unitario > 0)');
        DB::statement('ALTER TABLE tbdetalle_devolucion ADD CONSTRAINT ck_detalle_devolucion_cantidad_positiva CHECK (cantidad > 0)');
        DB::statement('ALTER TABLE tbmovimientos_inventario ADD CONSTRAINT ck_movimiento_cantidad_no_cero CHECK (cantidad <> 0)');
        DB::statement('ALTER TABLE tbmovimientos_inventario ADD CONSTRAINT ck_movimiento_stock_no_negativo CHECK (stock_resultante >= 0)');
        DB::statement('ALTER TABLE tbmovimientos_inventario ADD CONSTRAINT ck_movimiento_costo_positivo CHECK (costo_unitario > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ([
            'ck_productos_stock_no_negativo', 'ck_productos_stock_minimo_no_negativo',
            'ck_productos_precio_positivo', 'ck_productos_costo_no_negativo',
        ] as $constraint) {
            DB::statement("ALTER TABLE tbproductos DROP CONSTRAINT {$constraint}");
        }

        foreach ([
            ['tbdetalle_venta', 'ck_detalle_venta_cantidad_positiva'],
            ['tbdetalle_venta', 'ck_detalle_venta_precio_positivo'],
            ['tbdetalle_compras', 'ck_detalle_compra_cantidad_positiva'],
            ['tbdetalle_compras', 'ck_detalle_compra_costo_positivo'],
            ['tbdetalle_devolucion', 'ck_detalle_devolucion_cantidad_positiva'],
            ['tbmovimientos_inventario', 'ck_movimiento_cantidad_no_cero'],
            ['tbmovimientos_inventario', 'ck_movimiento_stock_no_negativo'],
            ['tbmovimientos_inventario', 'ck_movimiento_costo_positivo'],
        ] as [$table, $constraint]) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
        }
    }
};
