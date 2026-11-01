<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EstadoEnvio;
use App\Models\HistorialEnvio;
use App\Models\Venta;
use App\Services\InventarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class VentasController extends Controller
{
    /**
     * Listado de ventas con filtros por fecha, estado, entrega y cliente.
     */
    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'in:COMPLETADA,ANULADA'],
            'entrega' => ['nullable', 'in:tienda,domicilio'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $consulta = Venta::query()
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->whereHas('estado', fn ($e) => $e->where('nombre', $estado)))
            ->when(($filtros['entrega'] ?? null) === 'domicilio', fn ($q) => $q->has('envio'))
            ->when(($filtros['entrega'] ?? null) === 'tienda', fn ($q) => $q->doesntHave('envio'))
            ->when($filtros['desde'] ?? null, fn ($q, $desde) => $q->whereDate('fecha', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn ($q, $hasta) => $q->whereDate('fecha', '<=', $hasta))
            ->when(trim($filtros['q'] ?? '') !== '', function ($q) use ($filtros) {
                $texto = trim($filtros['q'], " #\t");
                $q->where(function ($w) use ($texto) {
                    $w->whereHas('cliente', fn ($c) => $c->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($texto).'%']));
                    if (ctype_digit($texto)) {
                        $w->orWhere('id_venta', (int) $texto);
                    }
                });
            });

        // Totales con los mismos filtros (solo cuentan las completadas).
        $resumen = (clone $consulta)
            ->whereHas('estado', fn ($e) => $e->where('nombre', 'COMPLETADA'))
            ->selectRaw('COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS monto')
            ->first();

        $ventas = $consulta
            ->with(['cliente', 'estado', 'envio.estado'])
            ->withSum('detalles', 'cantidad')
            ->orderByDesc('fecha')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Venta $venta) => [
                'id_venta' => $venta->id_venta,
                'numero' => str_pad((string) $venta->id_venta, 6, '0', STR_PAD_LEFT),
                'fecha' => $venta->fecha?->format('d/m/Y H:i'),
                'cliente' => $venta->cliente?->nombre,
                'libros' => (int) $venta->detalles_sum_cantidad,
                'total' => round((float) $venta->total + (float) ($venta->envio?->costo_envio ?? 0), 2),
                'estado' => $venta->estado?->nombre,
                'entrega' => $venta->envio ? 'domicilio' : 'tienda',
                'estado_envio' => $venta->envio?->estado?->nombre,
            ]);

        return Inertia::render('admin/ventas/Index', [
            'ventas' => $ventas,
            'resumen' => ['cantidad' => (int) $resumen->cantidad, 'monto' => round((float) $resumen->monto, 2)],
            'filtros' => [
                'q' => $filtros['q'] ?? '',
                'estado' => $filtros['estado'] ?? '',
                'entrega' => $filtros['entrega'] ?? '',
                'desde' => $filtros['desde'] ?? '',
                'hasta' => $filtros['hasta'] ?? '',
            ],
        ]);
    }

    public function show(Venta $venta): Response
    {
        $venta->load([
            'cliente.distrito.municipio.departamento',
            'estado',
            'oferta',
            'detalles.producto',
            'detalles.oferta',
            'envio.estado',
            'envio.tipoEnvio',
            'envio.distrito.municipio',
            'devoluciones.estado',
        ]);

        $envio = $venta->envio;
        $costoEnvio = $envio ? (float) $envio->costo_envio : 0.0;
        $noAnulable = $this->motivoNoAnular($venta);

        return Inertia::render('admin/ventas/Show', [
            'venta' => [
                'id_venta' => $venta->id_venta,
                'numero' => str_pad((string) $venta->id_venta, 6, '0', STR_PAD_LEFT),
                'fecha' => $venta->fecha?->format('d/m/Y H:i'),
                'estado' => $venta->estado?->nombre,
                'cliente' => [
                    'nombre' => $venta->cliente?->nombre,
                    'email' => $venta->cliente?->email,
                    'telefono' => $venta->cliente?->telefono,
                    'ubicacion' => collect([
                        $venta->cliente?->distrito?->nombre,
                        $venta->cliente?->distrito?->municipio?->nombre,
                        $venta->cliente?->distrito?->municipio?->departamento?->nombre,
                    ])->filter()->join(', '),
                ],
                'lineas' => $venta->detalles->map(fn ($d) => [
                    'id_producto' => $d->id_producto,
                    'nombre' => $d->producto?->nombre,
                    'cantidad' => (int) $d->cantidad,
                    'precio_unitario' => (float) $d->precio_unitario,
                    'descuento_aplicado' => (float) $d->descuento_aplicado,
                    'oferta' => $d->oferta?->nombre_oferta,
                    'subtotal' => (float) $d->subtotal,
                ]),
                'subtotal' => (float) $venta->subtotal_general,
                'descuento_global' => (float) $venta->descuento_global,
                'oferta_global' => $venta->oferta?->nombre_oferta,
                'total' => (float) $venta->total,
                'costo_envio' => $costoEnvio,
                'total_cobrado' => round((float) $venta->total + $costoEnvio, 2),
                'envio' => $envio ? [
                    'estado' => $envio->estado?->nombre,
                    'tipo' => $envio->tipoEnvio?->nombre,
                    'direccion' => $envio->direccion_entrega,
                    'ubicacion' => collect([$envio->distrito?->nombre, $envio->distrito?->municipio?->nombre])->filter()->join(', '),
                    'receptor' => $envio->nombre_receptor,
                    'telefono' => $envio->telefono_receptor,
                    'fecha_estimada' => $envio->fecha_estimada?->format('d/m/Y'),
                ] : null,
                'devoluciones' => $venta->devoluciones->map(fn ($d) => [
                    'id_devolucion' => $d->id_devolucion,
                    'fecha' => $d->fecha_devolucion?->format('d/m/Y'),
                    'estado' => $d->estado?->nombre,
                ]),
                'puede_anular' => $noAnulable === null,
                'motivo_no_anular' => $noAnulable,
            ],
        ]);
    }

    /**
     * Anula la venta: el InventarioService regresa los libros al stock y lo registra en el kardex.
     */
    public function anular(Venta $venta, InventarioService $inventario): RedirectResponse
    {
        $venta->load(['estado', 'envio.estado', 'devoluciones.estado']);

        if ($motivo = $this->motivoNoAnular($venta)) {
            return back()->with('error', $motivo);
        }

        DB::transaction(function () use ($venta, $inventario): void {
            $inventario->cancelSale($venta);

            // Si tenía un envío pendiente, ya no se despacha.
            if ($venta->envio) {
                $fallido = EstadoEnvio::query()->where('nombre', 'FALLIDO')->value('id_estado_envio');
                $venta->envio->update(['id_estado_envio' => $fallido]);

                HistorialEnvio::create([
                    'id_envio' => $venta->envio->id_envio,
                    'id_estado_envio' => $fallido,
                    'observacion' => 'Venta anulada antes del despacho',
                ]);
            }
        });

        return back()->with('exito', "Venta #{$venta->id_venta} anulada. Los libros regresaron al stock.");
    }

    /**
     * Por qué no se puede anular esta venta, o null si sí se puede.
     */
    private function motivoNoAnular(Venta $venta): ?string
    {
        if ($venta->estado?->nombre !== 'COMPLETADA') {
            return 'Solo se pueden anular ventas completadas.';
        }

        if ($venta->devoluciones->contains(fn ($d) => $d->estado?->nombre !== 'RECHAZADA')) {
            return 'Esta venta tiene devoluciones. Manejala desde Devoluciones.';
        }

        if ($venta->envio && $venta->envio->estado?->nombre !== 'PENDIENTE') {
            return 'El envío ya salió o fue entregado. Para recuperar los libros, se hace una devolución.';
        }

        return null;
    }
}