<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Venta extends LibreriaModel
{
    protected $table = 'tbventas';

    protected $primaryKey = 'id_venta';

    protected function casts(): array
    {
        return ['fecha' => 'datetime', 'subtotal_general' => 'decimal:2', 'descuento_global' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    public function oferta(): BelongsTo
    {
        return $this->belongsTo(Oferta::class, 'id_oferta', 'id_oferta');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoVenta::class, 'id_estado_ventas', 'id_estado_ventas');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleVenta::class, 'id_venta', 'id_venta');
    }

    public function envio(): HasOne
    {
        return $this->hasOne(Envio::class, 'id_venta', 'id_venta');
    }

    
    public function devoluciones(): HasMany
    {
        return $this->hasMany(Devolucion::class, 'id_venta', 'id_venta');
    }
}
