<?php

namespace App\Models;

class Compra extends LibreriaModel
{
    protected $table = 'tbcompras';

    protected $primaryKey = 'id_compra';

    protected function casts(): array
    {
        return ['fecha' => 'datetime'];
    }
}
