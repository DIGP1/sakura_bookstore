<?php

namespace App\Models;

class HistorialEnvio extends LibreriaModel
{
    protected $table = 'tbhistorial_envio';

    protected $primaryKey = 'id_historial';

    protected function casts(): array
    {
        return ['fecha' => 'datetime'];
    }
}
