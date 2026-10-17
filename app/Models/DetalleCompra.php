<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleCompra extends LibreriaModel
{
    protected $table = 'tbdetalle_compras';

    protected $primaryKey = 'id_detalle_compra';

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'id_compra', 'id_compra');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }
}
