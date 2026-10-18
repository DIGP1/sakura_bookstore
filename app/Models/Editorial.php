<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Editorial extends LibreriaModel
{
    protected $table = 'tbeditoriales';

    protected $primaryKey = 'id_editorial';

    /** Libros publicados por esta editorial. */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'id_editorial', 'id_editorial');
    }
}