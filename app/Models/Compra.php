<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
