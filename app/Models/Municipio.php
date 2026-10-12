<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipio extends LibreriaModel
{
    protected $table = 'tbmunicipios';

    protected $primaryKey = 'id_municipio';

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'id_departamento', 'id_departamento');
    }

    public function distritos(): HasMany
    {
        return $this->hasMany(Distrito::class, 'id_municipio', 'id_municipio');
    }
}
