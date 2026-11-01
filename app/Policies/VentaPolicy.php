<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venta;

class VentaPolicy
{
    /**
     * Un pedido solo lo ve su dueño o un administrador.
     */
    public function view(User $user, Venta $venta): bool
    {
        if ($user->esAdmin()) {
            return true;
        }

        return $user->cliente !== null
            && (int) $user->cliente->id_cliente === (int) $venta->id_cliente;
    }
}