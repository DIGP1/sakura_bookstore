<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Services\CarritoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CarritoController extends Controller
{
    public function __construct(private CarritoService $carrito) {}

    public function index(): Response
    {
        return Inertia::render('carrito/Index', [
            'resumen' => $this->carrito->resumen(),
        ]);
    }

    /**
     * Agrega un libro sin pasar el stock disponible.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id_producto' => ['required', 'integer', 'exists:tbproductos,id_producto'],
            'cantidad' => ['required', 'integer', 'min:1'],
        ]);

        $producto = Producto::findOrFail($validated['id_producto']);
        $this->carrito->agregar($producto, $validated['cantidad']);

        return back()->with('exito', "\"{$producto->nombre}\" se agregó al carrito.");
    }

    public function update(Request $request, int $idProducto): RedirectResponse
    {
        $validated = $request->validate(['cantidad' => ['required', 'integer', 'min:1']]);

        $this->carrito->actualizar(Producto::findOrFail($idProducto), $validated['cantidad']);

        return back();
    }

    public function destroy(int $idProducto): RedirectResponse
    {
        $this->carrito->quitar($idProducto);

        return back()->with('exito', 'Libro quitado del carrito.');
    }
}