<?php

namespace App\Models;

class Devolucion extends LibreriaModel
{
    protected $table = 'tbdevoluciones';

    protected $primaryKey = 'id_devolucion';

    protected function casts(): array
    {
        return ['fecha_devolucion' => 'datetime'];
    }
}
