<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Autor extends LibreriaModel
{
    protected $table = 'tbautores';

    protected $primaryKey = 'id_autor';

    /** Libros escritos por este autor. */
    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'producto_autor', 'id_autor', 'id_producto');
    }
}