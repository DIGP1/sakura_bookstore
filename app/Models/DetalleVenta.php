<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleVenta extends LibreriaModel
{
    protected $table = 'tbdetalle_venta';

    protected $primaryKey = 'id_detalle_venta';

    protected function casts(): array
    {
        return ['precio_unitario' => 'decimal:2', 'descuento_aplicado' => 'decimal:2', 'subtotal' => 'decimal:2'];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'id_venta', 'id_venta');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function oferta(): BelongsTo
    {
        return $this->belongsTo(Oferta::class, 'id_oferta', 'id_oferta');
    }
}
