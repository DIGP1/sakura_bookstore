<?php

namespace App\Models;

class Oferta extends LibreriaModel
{
    protected $table = 'tboferta';

    protected $primaryKey = 'id_oferta';

    protected function casts(): array
    {
        return ['fecha_inicio' => 'date', 'fecha_fin' => 'date', 'estado' => 'boolean'];
    }
}
