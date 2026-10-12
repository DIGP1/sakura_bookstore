<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends LibreriaModel
{
    protected $table = 'tbdepartamentos';

    protected $primaryKey = 'id_departamento';

    public function municipios(): HasMany
    {
        return $this->hasMany(Municipio::class, 'id_departamento', 'id_departamento');
    }
}
