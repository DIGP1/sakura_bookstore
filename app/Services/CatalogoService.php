<?php

namespace App\Services;

use App\Models\Producto;

class CatalogoService
{
    /** Relaciones que necesita una tarjeta de libro. */
    public const RELACIONES = ['autores', 'categorias', 'editorial'];

    public function __construct(private OfertaService $ofertas) {}

    /**
     * agotado: sin stock · pocos: en o bajo el stock mínimo · disponible: el resto.
     */
    public function disponibilidad(Producto $producto): string
    {
        if ($producto->stock <= 0) {
            return 'agotado';
        }

        if ($producto->stock <= $producto->stock_minimo) {
            return 'pocos';
        }

        return 'disponible';
    }

    /**
     * Datos de un libro para mostrarlo en la tienda.
     * El libro debe venir con Producto::with(CatalogoService::RELACIONES).
     */
    public function tarjeta(Producto $producto): array
    {
        return [
            'id_producto' => $producto->id_producto,
            'nombre' => $producto->nombre,
            'autores' => $producto->autores->pluck('nombre')->unique()->join(', '),
            'stock' => (int) $producto->stock,
            'disponibilidad' => $this->disponibilidad($producto),
            ...$this->ofertas->precio($producto),
        ];
    }

    /**
     * Ficha completa del libro para su página de detalle.
     */
    public function ficha(Producto $producto): array
    {
        $formatos = ['TAPA_DURA' => 'Tapa dura', 'RUSTICA' => 'Rústica', 'BOLSILLO' => 'Bolsillo'];

        return [
            ...$this->tarjeta($producto),
            'isbn' => $producto->isbn,
            'editorial' => $producto->editorial?->nombre,
            'anio_publicacion' => $producto->anio_publicacion,
            'idioma' => $producto->idioma,
            'formato' => $formatos[$producto->formato] ?? $producto->formato,
            'num_paginas' => $producto->num_paginas,
            'categorias' => $producto->categorias
                ->map(fn ($categoria) => ['id' => $categoria->id_categoria, 'nombre' => $categoria->nombre])
                ->values(),
        ];
    }
}