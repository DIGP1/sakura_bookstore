<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Compra extends LibreriaModel
{
    protected $table = 'tbcompras';

    protected $primaryKey = 'id_compra';

    protected function casts(): array
    {
        return ['fecha' => 'datetime'];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleCompra::class, 'id_compra', 'id_compra');
    }

    
    public function proveedor(): BelongsTo
      {
          return $this->belongsTo(Proveedor::class, 'id_proveedor', 'id_proveedor');
      }

    public function estado(): BelongsTo
      {
          return $this->belongsTo(EstadoCompra::class, 'id_estado_compra', 'id_estado_compra');
      }
}
