<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleDevolucion extends LibreriaModel
{
    protected $table = 'tbdetalle_devolucion';

    protected $primaryKey = 'id_detalle_devolucion';

    protected function casts(): array
    {
        return ['cantidad' => 'integer', 'monto_reembolso' => 'decimal:2', 'reingresa_stock' => 'boolean'];
    }

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(Devolucion::class, 'id_devolucion', 'id_devolucion');
    }

    public function detalleVenta(): BelongsTo
    {
        return $this->belongsTo(DetalleVenta::class, 'id_detalle_venta', 'id_detalle_venta');
    }
}
