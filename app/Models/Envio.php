<?php

namespace App\Models;

class Envio extends LibreriaModel
{
    protected $table = 'tbenvios';

    protected $primaryKey = 'id_envio';

    protected function casts(): array
    {
        return ['fecha_estimada' => 'date', 'fecha_de_envio' => 'datetime', 'fecha_entrega' => 'datetime', 'costo_envio' => 'decimal:2'];
    }
}
