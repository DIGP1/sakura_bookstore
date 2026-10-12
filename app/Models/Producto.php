<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends LibreriaModel
{
    protected $table = 'tbproductos';

    protected $primaryKey = 'id_producto';

    protected function casts(): array
    {
        return [
            'anio_publicacion' => 'integer',
            'num_paginas' => 'integer',
            'stock' => 'integer',
            'stock_minimo' => 'integer',
            'precio_unitario' => 'decimal:2',
            'costo_promedio' => 'decimal:2',
        ];
    }

    public function editorial(): BelongsTo
    {
        return $this->belongsTo(Editorial::class, 'id_editorial', 'id_editorial');
    }

    public function autores(): BelongsToMany
    {
        return $this->belongsToMany(Autor::class, 'producto_autor', 'id_producto', 'id_autor')->withPivot('rol');
    }

    public function categorias(): BelongsToMany
    {
        return $this->belongsToMany(Categoria::class, 'producto_categoria', 'id_producto', 'id_categoria');
    }

    public function proveedores(): BelongsToMany
    {
        return $this->belongsToMany(Proveedor::class, 'producto_proveedor', 'id_producto', 'id_proveedor')
            ->withPivot('precio_costo');
    }

    public function detallesVenta(): HasMany
    {
        return $this->hasMany(DetalleVenta::class, 'id_producto', 'id_producto');
    }
}
