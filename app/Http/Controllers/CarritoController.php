<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CarritoController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('carrito/Index', [
            'carrito' => $request->session()->get('carrito', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id_producto' => ['required', 'integer', 'exists:tbproductos,id_producto'],
            'cantidad' => ['required', 'integer', 'min:1'],
        ]);
        $cart = $request->session()->get('carrito', []);
        $productId = (string) $validated['id_producto'];
        $cart[$productId] = ($cart[$productId] ?? 0) + $validated['cantidad'];
        $request->session()->put('carrito', $cart);

        return back();
    }

    public function update(Request $request, int $idProducto): RedirectResponse
    {
        $validated = $request->validate(['cantidad' => ['required', 'integer', 'min:1']]);
        $cart = $request->session()->get('carrito', []);
        $cart[(string) $idProducto] = $validated['cantidad'];
        $request->session()->put('carrito', $cart);

        return back();
    }

    public function destroy(Request $request, int $idProducto): RedirectResponse
    {
        $cart = $request->session()->get('carrito', []);
        unset($cart[(string) $idProducto]);
        $request->session()->put('carrito', $cart);

        return back();
    }
}
