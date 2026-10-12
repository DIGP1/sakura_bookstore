<?php

namespace App\Models;

class TipoEnvio extends LibreriaModel
{
    protected $table = 'tbtipos_envios';

    protected $primaryKey = 'id_tipo_envio';

    protected function casts(): array
    {
        return ['dias_estimados' => 'integer', 'costo' => 'decimal:2', 'estado' => 'boolean'];
    }
}
