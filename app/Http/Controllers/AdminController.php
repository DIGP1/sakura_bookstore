<?php

namespace App\Http\Controllers;

use App\Models\Devolucion;
use App\Models\Envio;
use App\Models\Producto;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    /**
     * Inicio del panel: bienvenida y accesos rápidos.
     * Solo se mandan los contadores de pendientes para mostrarlos como avisos en los accesos.
     * El análisis de ventas vive en el dashboard de BI (Streamlit).
     */
    public function index(): Response
    {
        return Inertia::render('admin/Dashboard', [
            'pendientes' => [
                'envios' => Envio::query()
                    ->whereHas('estado', fn ($estado) => $estado->whereIn('nombre', ['PENDIENTE', 'EN_CAMINO']))
                    ->count(),
                'devoluciones' => Devolucion::query()
                    ->whereHas('estado', fn ($estado) => $estado->where('nombre', 'SOLICITADA'))
                    ->count(),
                'stock_bajo' => Producto::query()
                    ->whereColumn('stock', '<=', 'stock_minimo')
                    ->count(),
            ],
        ]);
    }
}