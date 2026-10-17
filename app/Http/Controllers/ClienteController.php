<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    public function checkout(): Response
    {
        return Inertia::render('cliente/Checkout');
    }

    public function orders(): Response
    {
        return Inertia::render('cliente/Pedidos');
    }

    public function returns(): Response
    {
        return Inertia::render('cliente/Devoluciones');
    }
}
