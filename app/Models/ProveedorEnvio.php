<?php

namespace App\Models;

class ProveedorEnvio extends LibreriaModel
{
    protected $table = 'tbproveedores_envios';

    protected $primaryKey = 'id_proveedor_envio';

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
