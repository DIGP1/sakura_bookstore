<?php

namespace App\Services;

use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CarritoService
{
    /** Misma llave de sesión que usa el CarritoController: id_producto => cantidad. */
    private const CLAVE = 'carrito';

    public function __construct(
        private OfertaService $ofertas,
        private CatalogoService $catalogo,
    ) {}

    /**
     * @return array<int, int> id_producto => cantidad
     */
    public function contenido(): array
    {
        return collect(session(self::CLAVE, []))
            ->mapWithKeys(fn ($cantidad, $idProducto) => [(int) $idProducto => (int) $cantidad])
            ->all();
    }

    public function cantidadLibros(): int
    {
        return array_sum($this->contenido());
    }

    public function agregar(Producto $producto, int $cantidad): void
    {
        $actual = $this->contenido()[$producto->id_producto] ?? 0;

        $this->guardarCantidad($producto, $actual + $cantidad);
    }

    public function actualizar(Producto $producto, int $cantidad): void
    {
        $this->guardarCantidad($producto, $cantidad);
    }

    public function quitar(int $idProducto): void
    {
        $contenido = $this->contenido();
        unset($contenido[$idProducto]);

        session([self::CLAVE => $contenido]);
    }

    public function vaciar(): void
    {
        session()->forget(self::CLAVE);
    }

    /**
     * Libros del carrito con su precio vigente y subtotal.
     */
    public function lineas(): Collection
    {
        $contenido = $this->contenido();

        if ($contenido === []) {
            return collect();
        }

        return Producto::with(CatalogoService::RELACIONES)
            ->whereIn('id_producto', array_keys($contenido))
            ->orderBy('nombre')
            ->get()
            ->map(function (Producto $producto) use ($contenido): array {
                $cantidad = $contenido[$producto->id_producto];
                $tarjeta = $this->catalogo->tarjeta($producto);

                return [
                    ...$tarjeta,
                    'cantidad' => $cantidad,
                    'ahorro' => round($cantidad * $tarjeta['descuento'], 2),
                    'subtotal' => round($cantidad * $tarjeta['precio_final'], 2),
                ];
            })
            ->values();
    }

    /**
     * Totales: subtotal con las ofertas por libro, descuento global y total.
     */
    public function resumen(): array
    {
        $lineas = $this->lineas();
        $subtotal = round($lineas->sum('subtotal'), 2);
        $global = $this->ofertas->descuentoGlobal($subtotal);

        return [
            'lineas' => $lineas,
            'cantidad_libros' => $lineas->sum('cantidad'),
            'ahorro_ofertas' => round($lineas->sum('ahorro'), 2),
            'subtotal' => $subtotal,
            'descuento_global' => $global['descuento'],
            'oferta_global' => $global['oferta'],
            'id_oferta_global' => $global['id_oferta'],
            'total' => round($subtotal - $global['descuento'], 2),
        ];
    }

    private function guardarCantidad(Producto $producto, int $cantidad): void
    {
        if ($producto->stock <= 0) {
            throw ValidationException::withMessages([
                'cantidad' => "\"{$producto->nombre}\" está agotado.",
            ]);
        }

        if ($cantidad > $producto->stock) {
            throw ValidationException::withMessages([
                'cantidad' => "Solo quedan {$producto->stock} ejemplares de \"{$producto->nombre}\".",
            ]);
        }

        $contenido = $this->contenido();
        $contenido[$producto->id_producto] = $cantidad;

        session([self::CLAVE => $contenido]);
    }
}