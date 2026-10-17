<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\DetalleVenta;
use App\Models\Devolucion;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\TipoMovimiento;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class InventarioService
{
    /**
     * @param  array<string, mixed>  $saleData
     * @param  array<int, array<string, mixed>>  $details
     */
    public function createSale(array $saleData, array $details): Venta
    {
        return DB::transaction(function () use ($saleData, $details): Venta {
            $subtotal = 0.0;
            $discount = 0.0;

            foreach ($details as $index => $detailData) {
                $quantity = (int) $detailData['cantidad'];
                $product = Producto::query()->whereKey($detailData['id_producto'])->lockForUpdate()->firstOrFail();
                $unitPrice = (float) ($detailData['precio_unitario'] ?? $product->precio_unitario);
                $unitDiscount = (float) ($detailData['descuento_aplicado'] ?? 0);

                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages(["detalles.$index.cantidad" => "Stock insuficiente para {$product->nombre}."]);
                }

                if ($unitPrice <= 0) {
                    throw ValidationException::withMessages(["detalles.$index.precio_unitario" => 'El precio unitario debe ser mayor que cero.']);
                }

                if ($unitDiscount < 0 || $unitDiscount > $unitPrice) {
                    throw ValidationException::withMessages(["detalles.$index.descuento_aplicado" => 'El descuento debe estar entre cero y el precio unitario.']);
                }

                $subtotal += round($quantity * ($unitPrice - $unitDiscount), 2);
                $discount += round($quantity * $unitDiscount, 2);
            }

            $sale = new Venta($saleData);
            $sale->subtotal_general = round($subtotal + $discount, 2);
            $sale->descuento_global = round($discount, 2);
            $sale->total = round($subtotal, 2);
            $sale->save();

            foreach ($details as $detailData) {
                $quantity = (int) $detailData['cantidad'];
                $product = Producto::query()->whereKey($detailData['id_producto'])->lockForUpdate()->firstOrFail();
                $unitPrice = (float) ($detailData['precio_unitario'] ?? $product->precio_unitario);
                $unitDiscount = (float) ($detailData['descuento_aplicado'] ?? 0);
                $detail = $sale->detalles()->create([
                    'id_producto' => $product->getKey(),
                    'id_oferta' => $detailData['id_oferta'] ?? $sale->id_oferta,
                    'cantidad' => $quantity,
                    'precio_unitario' => $unitPrice,
                    'descuento_aplicado' => $unitDiscount,
                    'subtotal' => round($quantity * ($unitPrice - $unitDiscount), 2),
                ]);

                $product->decrement('stock', $quantity);
                $this->recordMovement($product->fresh(), -$quantity, (float) $product->costo_promedio, [
                    'id_detalle_venta' => $detail->getKey(),
                    'observacion' => 'Salida por venta',
                ], ['SALIDA_VENTA', 'SALIDA', 'VENTA']);
            }

            return $sale->load('detalles');
        });
    }

    public function cancelSale(Venta $sale): Venta
    {
        return DB::transaction(function () use ($sale): Venta {
            $sale = Venta::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();
            $cancelledStatus = $this->stateId('tbestado_ventas', ['ANULADA', 'ANULADO', 'CANCELADA']);

            if ((int) $sale->id_estado_ventas === $cancelledStatus) {
                return $sale->load('detalles');
            }

            foreach ($sale->detalles as $detail) {
                $product = Producto::query()->whereKey($detail->id_producto)->lockForUpdate()->firstOrFail();
                $product->increment('stock', (int) $detail->cantidad);
                $this->recordMovement($product->fresh(), (int) $detail->cantidad, (float) $product->costo_promedio, [
                    'id_detalle_venta' => $detail->getKey(),
                    'observacion' => 'Reingreso por anulación de venta',
                ], ['ENTRADA_ANULACION', 'ENTRADA', 'ANULACION']);
            }

            $sale->id_estado_ventas = $cancelledStatus;
            $sale->save();

            return $sale->load('detalles');
        });
    }

    public function receivePurchase(Compra $purchase, ?int $receivedStatusId = null): Compra
    {
        return DB::transaction(function () use ($purchase, $receivedStatusId): Compra {
            $purchase = Compra::query()->whereKey($purchase->getKey())->lockForUpdate()->firstOrFail();
            $receivedStatusId ??= $this->stateId('tbestado_compra', ['RECIBIDA', 'RECIBIDO']);

            foreach ($purchase->detalles as $detail) {
                if (MovimientoInventario::query()->where('id_detalle_compra', $detail->getKey())->exists()) {
                    continue;
                }

                $quantity = (int) $detail->cantidad;
                $cost = (float) $detail->costo_unitario;
                $product = Producto::query()->whereKey($detail->id_producto)->lockForUpdate()->firstOrFail();
                $oldStock = (int) $product->stock;
                $newStock = $oldStock + $quantity;
                $product->costo_promedio = round((((float) $product->costo_promedio * $oldStock) + ($cost * $quantity)) / $newStock, 2);
                $product->stock = $newStock;
                $product->save();

                $this->recordMovement($product->fresh(), $quantity, $cost, [
                    'id_detalle_compra' => $detail->getKey(),
                    'observacion' => 'Entrada por compra recibida',
                ], ['ENTRADA_COMPRA', 'ENTRADA', 'COMPRA']);
            }

            $purchase->id_estado_compra = $receivedStatusId;
            $purchase->save();

            return $purchase->load('detalles');
        });
    }

    public function approveReturn(Devolucion $return, ?int $approvedStatusId = null): Devolucion
    {
        return DB::transaction(function () use ($return, $approvedStatusId): Devolucion {
            $return = Devolucion::query()->whereKey($return->getKey())->lockForUpdate()->firstOrFail();
            $approvedStatusId ??= $this->stateId('tbestado_devolucion', ['APROBADA', 'APROBADO']);

            foreach ($return->detalles as $detail) {
                if (! $detail->reingresa_stock || MovimientoInventario::query()->where('id_detalle_devolucion', $detail->getKey())->exists()) {
                    continue;
                }

                $saleDetail = DetalleVenta::query()->whereKey($detail->id_detalle_venta)->firstOrFail();
                if ((int) $detail->cantidad > (int) $saleDetail->cantidad) {
                    throw ValidationException::withMessages(['detalles' => 'La cantidad devuelta no puede superar la cantidad vendida.']);
                }

                $product = Producto::query()->whereKey($saleDetail->id_producto)->lockForUpdate()->firstOrFail();
                $product->increment('stock', (int) $detail->cantidad);
                $this->recordMovement($product->fresh(), (int) $detail->cantidad, (float) $product->costo_promedio, [
                    'id_detalle_devolucion' => $detail->getKey(),
                    'observacion' => 'Reingreso por devolución aprobada',
                ], ['ENTRADA_DEVOLUCION', 'ENTRADA', 'DEVOLUCION']);
            }

            $return->id_estado_devolucion = $approvedStatusId;
            $return->save();

            return $return->load('detalles');
        });
    }

    /**
     * @param  array<string, int|string>  $references
     */
    /**
     * @param  array<string, int|string>  $references
     * @param  array<int, string>  $typeNames
     */
    private function recordMovement(Producto $product, int $quantity, float $cost, array $references, array $typeNames): MovimientoInventario
    {
        if ($quantity === 0) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad debe ser distinta de cero.']);
        }

        $type = TipoMovimiento::query()->whereIn('nombre', $typeNames)->first();

        if ($type === null) {
            throw new RuntimeException('No existe un tipo de movimiento de inventario para esta operación.');
        }

        return MovimientoInventario::create(array_merge([
            'id_producto' => $product->getKey(),
            'id_tipo_movimiento' => $type->getKey(),
            'cantidad' => $quantity,
            'costo_unitario' => $cost,
            'stock_resultante' => (int) $product->stock,
        ], $references));
    }

    private function stateId(string $table, array $names): int
    {
        $primaryKey = match ($table) {
            'tbestado_ventas' => 'id_estado_ventas',
            'tbestado_compra' => 'id_estado_compra',
            'tbestado_devolucion' => 'id_estado_devolucion',
            default => throw new RuntimeException('Tabla de estados no soportada.'),
        };

        foreach ($names as $name) {
            $id = DB::table($table)->where('nombre', $name)->value($primaryKey);

            if ($id !== null) {
                return (int) $id;
            }
        }

        throw new RuntimeException('No existe el estado requerido para completar la operación.');
    }
}
