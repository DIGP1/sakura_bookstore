<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Devolucion extends LibreriaModel
{
    protected $table = 'tbdevoluciones';

    protected $primaryKey = 'id_devolucion';

    protected function casts(): array
    {
        return ['fecha_devolucion' => 'datetime'];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleDevolucion::class, 'id_devolucion', 'id_devolucion');
    }

    
      public function venta(): BelongsTo
      {
          return $this->belongsTo(Venta::class, 'id_venta', 'id_venta');
      }

      public function estado(): BelongsTo
      {
          return $this->belongsTo(EstadoDevolucion::class, 'id_estado_devolucion', 'id_estado_devolucion');
      }

      public function motivo(): BelongsTo
      {
          return $this->belongsTo(MotivoDevolucion::class, 'id_motivo_devolucion', 'id_motivo_devolucion');
      }

      public function tipoReembolso(): BelongsTo
      {
          return $this->belongsTo(TipoReembolso::class, 'id_tipo_reembolso', 'id_tipo_reembolso');
      }
}
