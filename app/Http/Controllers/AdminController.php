<?php

namespace App\Http\Controllers;

use App\Models\Devolucion;
use App\Models\Envio;
use App\Models\Producto;
use App\Models\Venta;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    /**
     * Inicio del panel: lo que el admin tiene que atender hoy.
     */
    public function index(): Response
    {
        $ventasHoy = Venta::query()
            ->whereDate('fecha', today())
            ->whereHas('estado', fn ($estado) => $estado->where('nombre', 'COMPLETADA'));

        $enviosPendientes = Envio::query()
            ->whereHas('estado', fn ($estado) => $estado->whereIn('nombre', ['PENDIENTE', 'EN_CAMINO']));

        $devolucionesPorRevisar = Devolucion::query()
            ->whereHas('estado', fn ($estado) => $estado->where('nombre', 'SOLICITADA'));

        $stockBajo = Producto::query()->whereColumn('stock', '<=', 'stock_minimo');

        return Inertia::render('admin/Dashboard', [
            'resumen' => [
                'ventas_hoy' => (clone $ventasHoy)->count(),
                'ingresos_hoy' => round((float) (clone $ventasHoy)->sum('total'), 2),
                'envios_pendientes' => (clone $enviosPendientes)->count(),
                'devoluciones_por_revisar' => (clone $devolucionesPorRevisar)->count(),
                'libros_stock_bajo' => (clone $stockBajo)->count(),
            ],
            'envios' => $enviosPendientes
                ->with(['estado', 'distrito.municipio', 'venta.cliente'])
                ->orderBy('fecha_estimada')
                ->limit(5)
                ->get()
                ->map(fn (Envio $envio) => [
                    'id_venta' => $envio->id_venta,
                    'numero' => str_pad((string) $envio->id_venta, 6, '0', STR_PAD_LEFT),
                    'cliente' => $envio->venta?->cliente?->nombre,
                    'destino' => collect([$envio->distrito?->nombre, $envio->distrito?->municipio?->nombre])->filter()->join(', '),
                    'estado' => $envio->estado?->nombre,
                    'fecha_estimada' => $envio->fecha_estimada?->format('d/m/Y'),
                    'atrasado' => $envio->fecha_estimada?->lt(today()) ?? false,
                ]),
            'devoluciones' => $devolucionesPorRevisar
                ->with(['venta.cliente', 'motivo'])
                ->withSum('detalles', 'monto_reembolso')
                ->orderBy('fecha_devolucion')
                ->limit(5)
                ->get()
                ->map(fn (Devolucion $devolucion) => [
                    'id_devolucion' => $devolucion->id_devolucion,
                    'cliente' => $devolucion->venta?->cliente?->nombre,
                    'motivo' => $devolucion->motivo?->nombre,
                    'fecha' => $devolucion->fecha_devolucion?->format('d/m/Y'),
                    'monto' => round((float) $devolucion->detalles_sum_monto_reembolso, 2),
                ]),
            'stockBajo' => $stockBajo
                ->orderBy('stock')
                ->orderBy('nombre')
                ->limit(8)
                ->get(['id_producto', 'nombre', 'stock', 'stock_minimo'])
                ->map(fn (Producto $producto) => [
                    'id_producto' => $producto->id_producto,
                    'nombre' => $producto->nombre,
                    'stock' => (int) $producto->stock,
                    'stock_minimo' => (int) $producto->stock_minimo,
                ]),
        ]);
    }
}