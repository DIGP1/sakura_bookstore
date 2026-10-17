<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Inertia\Inertia;
use Inertia\Response;

class CatalogoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('catalogo/Index', [
            'productos' => Producto::query()->orderBy('nombre')->paginate(12),
        ]);
    }

    public function show(Producto $producto): Response
    {
        return Inertia::render('catalogo/Show', [
            'producto' => $producto->load(['autores', 'categorias', 'editorial']),
        ]);
    }
}
