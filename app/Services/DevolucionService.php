<?php

namespace App\Services;

use App\Models\DetalleDevolucion;
use App\Models\Devolucion;
use App\Models\EstadoDevolucion;
use App\Models\Venta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DevolucionService
{
    public function __construct(private readonly SincronizarNeo4jService $neo4j) {}

    /** Días que tiene el cliente para pedir una devolución. */
    public const DIAS_LIMITE = 30;

    /**
     * Por qué no se puede devolver esta compra, o null si sí se puede.
     */
    public function motivoDeRechazo(Venta $venta): ?string
    {
        $venta->loadMissing('estado');

        if ($venta->estado?->nombre !== 'COMPLETADA') {
            return 'Solo se pueden devolver compras completadas.';
        }

        if ($venta->fecha === null || $venta->fecha->lt(now()->subDays(self::DIAS_LIMITE))) {
            return 'El plazo de '.self::DIAS_LIMITE.' días para devolver esta compra ya venció.';
        }

        if ($this->lineasDevolvibles($venta)->sum('disponible') === 0) {
            return 'Ya pediste la devolución de todos los libros de esta compra.';
        }

        return null;
    }

    /**
     * Libros de la venta con lo comprado, lo ya devuelto y lo que aún se puede devolver.
     */
    public function lineasDevolvibles(Venta $venta): Collection
    {
        $venta->loadMissing('detalles.producto');

        // Lo ya pedido en devoluciones que no fueron rechazadas, por línea de la venta.
        $yaDevuelto = DB::table('tbdetalle_devolucion as dd')
            ->join('tbdevoluciones as d', 'd.id_devolucion', '=', 'dd.id_devolucion')
            ->join('tbestado_devolucion as e', 'e.id_estado_devolucion', '=', 'd.id_estado_devolucion')
            ->where('d.id_venta', $venta->id_venta)
            ->where('e.nombre', '!=', 'RECHAZADA')
            ->groupBy('dd.id_detalle_venta')
            ->selectRaw('dd.id_detalle_venta, SUM(dd.cantidad) AS total')
            ->pluck('total', 'id_detalle_venta');

        // Parte del descuento global que le toca a cada libro: se reembolsa lo que de verdad se pagó.
        $subtotal = (float) $venta->subtotal_general;
        $factor = $subtotal > 0 ? (float) $venta->total / $subtotal : 1.0;

        return $venta->detalles->map(function ($detalle) use ($yaDevuelto, $factor): array {
            $comprada = (int) $detalle->cantidad;
            $devuelta = (int) ($yaDevuelto[$detalle->id_detalle_venta] ?? 0);

            return [
                'id_detalle_venta' => $detalle->id_detalle_venta,
                'id_producto' => $detalle->id_producto,
                'nombre' => $detalle->producto?->nombre,
                'comprada' => $comprada,
                'devuelta' => $devuelta,
                'disponible' => max(0, $comprada - $devuelta),
                'precio_pagado' => round(((float) $detalle->precio_unitario - (float) $detalle->descuento_aplicado) * $factor, 2),
            ];
        })->values();
    }

    /**
     * Registra la solicitud de devolución (estado SOLICITADA). El stock no se mueve
     * hasta que un administrador la apruebe.
     *
     * @param  array<string, mixed>  $datos  datos validados por SolicitarDevolucionRequest
     */
    public function solicitar(Venta $venta, array $datos): Devolucion
    {
        $neo4jSynced = false;
        $devolucionId = null;

        try {
            return DB::transaction(function () use ($venta, $datos, &$neo4jSynced, &$devolucionId): Devolucion {
                // Se bloquea la venta para que dos solicitudes al mismo tiempo no pasen del límite.
                $venta = Venta::query()->whereKey($venta->id_venta)->lockForUpdate()->firstOrFail();

                if ($motivo = $this->motivoDeRechazo($venta)) {
                    throw ValidationException::withMessages(['devolucion' => $motivo]);
                }

                $lineas = $this->lineasDevolvibles($venta)->keyBy('id_detalle_venta');
                $pedidas = collect($datos['lineas'])->filter(fn ($linea) => (int) $linea['cantidad'] > 0);

                if ($pedidas->isEmpty()) {
                    throw ValidationException::withMessages(['lineas' => 'Elegí al menos un libro para devolver.']);
                }

                foreach ($pedidas as $pedida) {
                    $linea = $lineas->get((int) $pedida['id_detalle_venta']);

                    if ($linea === null) {
                        throw ValidationException::withMessages(['lineas' => 'Uno de los libros no pertenece a esta compra.']);
                    }

                    if ((int) $pedida['cantidad'] > $linea['disponible']) {
                        throw ValidationException::withMessages([
                            'lineas' => "Solo podés devolver {$linea['disponible']} de \"{$linea['nombre']}\".",
                        ]);
                    }
                }

                $solicitada = EstadoDevolucion::query()->where('nombre', 'SOLICITADA')->value('id_estado_devolucion')
                    ?? throw new RuntimeException('No existe el estado de devolución SOLICITADA.');

                $devolucion = Devolucion::create([
                    'id_venta' => $venta->id_venta,
                    'id_motivo_devolucion' => $datos['id_motivo_devolucion'],
                    'id_tipo_reembolso' => $datos['id_tipo_reembolso'],
                    'id_estado_devolucion' => $solicitada,
                ]);
                $devolucionId = (int) $devolucion->id_devolucion;

                foreach ($pedidas as $pedida) {
                    $linea = $lineas->get((int) $pedida['id_detalle_venta']);

                    DetalleDevolucion::create([
                        'id_devolucion' => $devolucion->id_devolucion,
                        'id_detalle_venta' => $linea['id_detalle_venta'],
                        'cantidad' => (int) $pedida['cantidad'],
                        'monto_reembolso' => round((int) $pedida['cantidad'] * $linea['precio_pagado'], 2),
                        'reingresa_stock' => true,
                    ]);
                }

                $devolucion = $devolucion->load(['detalles.detalleVenta', 'estado', 'motivo', 'tipoReembolso']);
                $this->neo4j->devolucion($devolucion);
                $neo4jSynced = true;

                return $devolucion;
            });
        } catch (\Throwable $exception) {
            if ($neo4jSynced && $devolucionId !== null) {
                try {
                    $this->neo4j->eliminarDevolucion($devolucionId);
                } catch (\Throwable $compensationException) {
                    report($compensationException);
                }
            }

            throw $exception;
        }
    }
}
