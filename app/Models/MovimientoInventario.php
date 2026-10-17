<?php

namespace App\Models;

use LogicException;

class MovimientoInventario extends LibreriaModel
{
    protected $table = 'tbmovimientos_inventario';

    protected $primaryKey = 'id_movimiento';

    protected function casts(): array
    {
        return ['fecha' => 'datetime', 'cantidad' => 'integer', 'costo_unitario' => 'decimal:2', 'stock_resultante' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Los movimientos de inventario son inmutables.');
        });

        static::deleting(function (): void {
            throw new LogicException('Los movimientos de inventario no se pueden eliminar.');
        });
    }
}
