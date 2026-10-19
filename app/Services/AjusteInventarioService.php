<?php

namespace App\Services;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\TipoMovimiento;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Ajustes manuales de inventario (conteo físico, libros dañados o perdidos).
 * Cada ajuste mueve el stock y queda registrado en el kardex, que no se puede editar.
 */
class AjusteInventarioService
{
    /**
     * @param  int  $cantidad  positiva para entrada, negativa para salida
     */
    public function ajustar(Producto $producto, int $cantidad, string $observacion): MovimientoInventario
    {
        if ($cantidad === 0) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad debe ser mayor que cero.']);
        }

        return DB::transaction(function () use ($producto, $cantidad, $observacion): MovimientoInventario {
            // Se bloquea el libro para que una venta al mismo tiempo no deje el stock mal.
            $producto = Producto::query()->whereKey($producto->id_producto)->lockForUpdate()->firstOrFail();

            if ($cantidad < 0 && abs($cantidad) > $producto->stock) {
                throw ValidationException::withMessages([
                    'cantidad' => "No se pueden sacar ".abs($cantidad)." unidades: solo hay {$producto->stock} en stock.",
                ]);
            }

            $tipo = TipoMovimiento::query()
                ->where('nombre', $cantidad > 0 ? 'AJUSTE_POSITIVO' : 'AJUSTE_NEGATIVO')
                ->first() ?? throw new RuntimeException('No existe el tipo de movimiento de ajuste.');

            $producto->stock += $cantidad;
            $producto->save();

            return MovimientoInventario::create([
                'id_producto' => $producto->id_producto,
                'id_tipo_movimiento' => $tipo->id_tipo_movimiento,
                'cantidad' => $cantidad,
                'costo_unitario' => $producto->costo_promedio,
                'stock_resultante' => $producto->stock,
                'observacion' => $observacion,
            ]);
        });
    }
}