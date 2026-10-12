<?php

namespace App\Models;

class MovimientoInventario extends LibreriaModel
{
    protected $table = 'tbmovimientos_inventario';

    protected $primaryKey = 'id_movimiento';

    protected function casts(): array
    {
        return ['fecha' => 'datetime', 'cantidad' => 'integer', 'costo_unitario' => 'decimal:2', 'stock_resultante' => 'integer'];
    }
}
