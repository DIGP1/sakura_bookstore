<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Oferta extends LibreriaModel
{
    protected $table = 'tboferta';

    protected $primaryKey = 'id_oferta';

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'estado' => 'boolean',
            'valor_descuento' => 'decimal:2',
            'monto_minimo' => 'decimal:2',
        ];
    }

    public function tipoDescuento(): BelongsTo
    {
        return $this->belongsTo(TipoDescuento::class, 'id_tipo_descuento', 'id_tipo_descuento');
    }

    /** Libros a los que aplica la oferta. */
    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'tboferta_producto', 'id_oferta', 'id_producto');
    }

    /** Categorías completas a las que aplica la oferta. */
    public function categorias(): BelongsToMany
    {
        return $this->belongsToMany(Categoria::class, 'tboferta_categoria', 'id_oferta', 'id_categoria');
    }

    /** Ofertas activas cuya vigencia incluye el día de hoy. */
    public function scopeVigentes(Builder $query): void
    {
        $hoy = today()->toDateString();

        $query->where('estado', true)
            ->whereDate('fecha_inicio', '<=', $hoy)
            ->whereDate('fecha_fin', '>=', $hoy);
    }

    public function esPorcentaje(): bool
    {
        return strtoupper($this->tipoDescuento?->nombre ?? '') === 'PORCENTAJE';
    }
}