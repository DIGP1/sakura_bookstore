<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Distrito extends LibreriaModel
{
    protected $table = 'tbdistritos';

    protected $primaryKey = 'id_distrito';

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'id_municipio', 'id_municipio');
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'id_distrito', 'id_distrito');
    }
}
