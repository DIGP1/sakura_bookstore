<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Devolucion;
use App\Models\EstadoDevolucion;
use App\Services\InventarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\SincronizarNeo4jService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DevolucionesController extends Controller
{
    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'estado' => ['nullable', 'in:SOLICITADA,APROBADA,RECHAZADA,TODAS'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $estado = $filtros['estado'] ?? 'SOLICITADA';

        $devoluciones = Devolucion::query()
            ->with(['estado', 'motivo', 'venta.cliente'])
            ->withSum('detalles', 'cantidad')
            ->withSum('detalles', 'monto_reembolso')
            ->when($estado !== 'TODAS', fn ($q) => $q->whereHas('estado', fn ($e) => $e->where('nombre', $estado)))
            ->when(trim($filtros['q'] ?? '') !== '', function ($q) use ($filtros) {
                $texto = trim($filtros['q'], " #\t");
                $q->where(function ($w) use ($texto) {
                    $w->whereHas('venta.cliente', fn ($c) => $c->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($texto).'%']));
                    if (ctype_digit($texto)) {
                        $w->orWhere('id_venta', (int) $texto)->orWhere('id_devolucion', (int) $texto);
                    }
                });
            })
            // Las más viejas primero cuando son por revisar; las más nuevas primero en lo demás.
            ->orderBy('fecha_devolucion', $estado === 'SOLICITADA' ? 'asc' : 'desc')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Devolucion $d) => [
                'id_devolucion' => $d->id_devolucion,
                'fecha' => $d->fecha_devolucion?->format('d/m/Y'),
                'numero_pedido' => str_pad((string) $d->id_venta, 6, '0', STR_PAD_LEFT),
                'cliente' => $d->venta?->cliente?->nombre,
                'motivo' => $this->formato($d->motivo?->nombre),
                'libros' => (int) $d->detalles_sum_cantidad,
                'monto' => round((float) $d->detalles_sum_monto_reembolso, 2),
                'estado' => $d->estado?->nombre,
            ]);

        $conteo = DB::table('tbdevoluciones as d')
            ->join('tbestado_devolucion as e', 'e.id_estado_devolucion', '=', 'd.id_estado_devolucion')
            ->groupBy('e.nombre')
            ->selectRaw('e.nombre, COUNT(*) AS total')
            ->pluck('total', 'nombre');

        return Inertia::render('admin/devoluciones/Index', [
            'devoluciones' => $devoluciones,
            'conteo' => [
                'SOLICITADA' => (int) ($conteo['SOLICITADA'] ?? 0),
                'APROBADA' => (int) ($conteo['APROBADA'] ?? 0),
                'RECHAZADA' => (int) ($conteo['RECHAZADA'] ?? 0),
            ],
            'filtros' => ['estado' => $estado, 'q' => $filtros['q'] ?? ''],
        ]);
    }

    public function show(Devolucion $devolucion): Response
    {
        $devolucion->load([
            'estado', 'motivo', 'tipoReembolso',
            'venta.cliente', 'venta.envio.estado',
            'detalles.detalleVenta.producto',
        ]);

        return Inertia::render('admin/devoluciones/Show', [
            'devolucion' => [
                'id_devolucion' => $devolucion->id_devolucion,
                'fecha' => $devolucion->fecha_devolucion?->format('d/m/Y H:i'),
                'estado' => $devolucion->estado?->nombre,
                'motivo' => $this->formato($devolucion->motivo?->nombre),
                'tipo_reembolso' => $this->formato($devolucion->tipoReembolso?->nombre),
                'id_venta' => $devolucion->id_venta,
                'numero_pedido' => str_pad((string) $devolucion->id_venta, 6, '0', STR_PAD_LEFT),
                'fecha_pedido' => $devolucion->venta?->fecha?->format('d/m/Y'),
                'cliente' => [
                    'nombre' => $devolucion->venta?->cliente?->nombre,
                    'email' => $devolucion->venta?->cliente?->email,
                    'telefono' => $devolucion->venta?->cliente?->telefono,
                ],
                'entrega' => $devolucion->venta?->envio ? 'Envío a domicilio' : 'Retiro en tienda',
                'lineas' => $devolucion->detalles->map(fn ($d) => [
                    'id_detalle_devolucion' => $d->id_detalle_devolucion,
                    'nombre' => $d->detalleVenta?->producto?->nombre,
                    'cantidad' => (int) $d->cantidad,
                    'comprada' => (int) $d->detalleVenta?->cantidad,
                    'monto' => (float) $d->monto_reembolso,
                    'reingresa_stock' => (bool) $d->reingresa_stock,
                ]),
                'total' => round($devolucion->detalles->sum(fn ($d) => (float) $d->monto_reembolso), 2),
            ],
        ]);
    }

    /**
     * Aprueba la devolución. Antes se guarda qué libros vuelven al stock (los dañados no),
     * y después el InventarioService los reingresa y registra en el kardex.
     */
    public function aprobar(Request $request, Devolucion $devolucion, InventarioService $inventario): RedirectResponse
    {
        if ($devolucion->estado?->nombre !== 'SOLICITADA') {
            return back()->with('error', 'Solo se pueden aprobar devoluciones por revisar.');
        }

        $datos = $request->validate([
            'reingresa' => ['array'],
            'reingresa.*' => ['boolean'],
        ]);

        DB::transaction(function () use ($devolucion, $datos, $inventario): void {
            foreach ($devolucion->detalles as $detalle) {
                $detalle->update([
                    'reingresa_stock' => (bool) ($datos['reingresa'][$detalle->id_detalle_devolucion] ?? true),
                ]);
            }

            $inventario->approveReturn($devolucion);
        });

        return back()->with('exito', "Devolución #{$devolucion->id_devolucion} aprobada. Los libros en buen estado regresaron al stock.");
    }

    public function rechazar(Devolucion $devolucion, SincronizarNeo4jService $neo4j):
            RedirectResponse
            {
            if ($devolucion->estado?->nombre !== 'SOLICITADA') {
            return back()->with('error', 'Solo se pueden rechazar devoluciones por revisar.');
            }
            // Se cambia el estado y se refleja en Neo4j en la misma transacción:
            DB::transaction(function () use ($devolucion, $neo4j): void {
            $devolucion->update([
            'id_estado_devolucion' => EstadoDevolucion::query()->where('nombre', 'RECHAZADA')-
            >value('id_estado_devolucion'),
            ]);
            $neo4j->devolucion($devolucion->fresh(['detalles.detalleVenta', 'estado', 'motivo',
            'tipoReembolso']));
            });
            return back()->with('exito', "Devolución #{$devolucion->id_devolucion} rechazada.");
            }

    private function formato(?string $nombre): ?string
    {
        return $nombre ? ucfirst(mb_strtolower(str_replace('_', ' ', $nombre))) : null;
    }
}