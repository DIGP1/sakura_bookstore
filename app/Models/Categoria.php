<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Categoria extends LibreriaModel
{
    protected $table = 'tbcategoria';

    protected $primaryKey = 'id_categoria';

    /** Libros que pertenecen a esta categoría. */
    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'producto_categoria', 'id_categoria', 'id_producto');
    }
}