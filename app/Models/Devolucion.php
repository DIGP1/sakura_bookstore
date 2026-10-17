<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
