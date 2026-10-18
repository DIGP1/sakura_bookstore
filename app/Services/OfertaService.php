<?php

namespace App\Services;

use App\Models\Oferta;
use App\Models\Producto;
use Illuminate\Support\Collection;

class OfertaService
{
    /** Se guardan en memoria para no consultar la base en cada libro. */
    private ?Collection $ofertasVigentes = null;

    /**
     * Ofertas activas hoy, con sus libros y categorías.
     */
    public function vigentes(): Collection
    {
        return $this->ofertasVigentes ??= Oferta::query()
            ->vigentes()
            ->with(['tipoDescuento', 'productos', 'categorias'])
            ->get();
    }

    /**
     * Ofertas que se aplican al total de la compra (no tienen libros ni categorías).
     */
    public function globales(): Collection
    {
        return $this->vigentes()->filter(
            fn (Oferta $oferta) => $oferta->productos->isEmpty() && $oferta->categorias->isEmpty()
        );
    }

    /**
     * Ofertas que se aplican a libros o categorías.
     */
    public function porLibro(): Collection
    {
        return $this->vigentes()->reject(
            fn (Oferta $oferta) => $oferta->productos->isEmpty() && $oferta->categorias->isEmpty()
        );
    }

    /**
     * La oferta que más descuenta a un libro, ya sea por el libro o por una de sus categorías.
     * El libro debe traer cargadas sus categorías.
     */
    public function mejorOferta(Producto $producto): ?Oferta
    {
        $categoriasDelLibro = $producto->categorias->pluck('id_categoria');

        return $this->porLibro()
            ->filter(fn (Oferta $oferta) => $oferta->productos->contains('id_producto', $producto->id_producto)
                || $oferta->categorias->pluck('id_categoria')->intersect($categoriasDelLibro)->isNotEmpty())
            ->sortByDesc(fn (Oferta $oferta) => $this->calcularDescuento($oferta, (float) $producto->precio_unitario))
            ->first();
    }

    /**
     * Cuánto descuenta una oferta sobre un monto. Nunca más que el monto.
     */
    public function calcularDescuento(Oferta $oferta, float $monto): float
    {
        $valor = (float) $oferta->valor_descuento;
        $descuento = $oferta->esPorcentaje() ? $monto * $valor / 100 : $valor;

        return round(min($descuento, $monto), 2);
    }

    /**
     * Precio de un libro con su mejor oferta aplicada.
     */
    public function precio(Producto $producto): array
    {
        $precio = (float) $producto->precio_unitario;
        $oferta = $this->mejorOferta($producto);
        $descuento = $oferta ? $this->calcularDescuento($oferta, $precio) : 0.0;

        return [
            'precio' => $precio,
            'descuento' => $descuento,
            'precio_final' => round($precio - $descuento, 2),
            'id_oferta' => $oferta?->id_oferta,
            'oferta' => $oferta?->nombre_oferta,
        ];
    }

    /**
     * Mejor descuento global para el subtotal de una compra, respetando el monto mínimo.
     */
    public function descuentoGlobal(float $subtotal): array
    {
        $mejorOferta = null;
        $mejorDescuento = 0.0;

        foreach ($this->globales() as $oferta) {
            if ($oferta->monto_minimo !== null && $subtotal < (float) $oferta->monto_minimo) {
                continue;
            }

            $descuento = $this->calcularDescuento($oferta, $subtotal);

            if ($descuento > $mejorDescuento) {
                $mejorOferta = $oferta;
                $mejorDescuento = $descuento;
            }
        }

        return [
            'id_oferta' => $mejorOferta?->id_oferta,
            'oferta' => $mejorOferta?->nombre_oferta,
            'descuento' => $mejorDescuento,
        ];
    }
}