<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Envio extends LibreriaModel
{
    protected $table = 'tbenvios';

    protected $primaryKey = 'id_envio';

    protected function casts(): array
    {
        return ['fecha_estimada' => 'date', 'fecha_de_envio' => 'datetime', 'fecha_entrega' => 'datetime', 'costo_envio' => 'decimal:2'];
    }

    
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'id_venta', 'id_venta');
    }

    public function tipoEnvio(): BelongsTo
    {
        return $this->belongsTo(TipoEnvio::class, 'id_tipo_envio', 'id_tipo_envio');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(ProveedorEnvio::class, 'id_proveedor_envio', 'id_proveedor_envio');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoEnvio::class, 'id_estado_envio', 'id_estado_envio');
    }

    public function distrito(): BelongsTo
    {
        return $this->belongsTo(Distrito::class, 'id_distrito', 'id_distrito');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialEnvio::class, 'id_envio', 'id_envio');
    }
}
