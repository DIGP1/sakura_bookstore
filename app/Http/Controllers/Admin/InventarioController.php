<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AjusteInventarioRequest;
use App\Models\Producto;
use App\Services\AjusteInventarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InventarioController extends Controller
{
    /**
     * Stock y valor del inventario de cada libro.
     */
    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'stock' => ['nullable', 'in:bajo,agotado'],
        ]);

        $libros = Producto::query()
            ->select('tbproductos.*')
            ->selectRaw('(SELECT MAX(m.fecha) FROM tbmovimientos_inventario m WHERE m.id_producto = tbproductos.id_producto) AS ultimo_movimiento')
            ->when(trim($filtros['q'] ?? '') !== '', function ($q) use ($filtros) {
                $texto = trim($filtros['q']);
                $q->where(fn ($w) => $w
                    ->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($texto).'%'])
                    ->orWhere('isbn', 'like', '%'.preg_replace('/\D/', '', $texto).'%'));
            })
            ->when(($filtros['stock'] ?? null) === 'bajo', fn ($q) => $q->whereColumn('stock', '<=', 'stock_minimo')->where('stock', '>', 0))
            ->when(($filtros['stock'] ?? null) === 'agotado', fn ($q) => $q->where('stock', 0))
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Producto $libro) => [
                'id_producto' => $libro->id_producto,
                'nombre' => $libro->nombre,
                'isbn' => $libro->isbn,
                'stock' => (int) $libro->stock,
                'stock_minimo' => (int) $libro->stock_minimo,
                'costo_promedio' => (float) $libro->costo_promedio,
                'valor' => round($libro->stock * (float) $libro->costo_promedio, 2),
                'ultimo_movimiento' => $libro->ultimo_movimiento
                    ? \Illuminate\Support\Carbon::parse($libro->ultimo_movimiento)->format('d/m/Y')
                    : null,
            ]);

        $totales = DB::table('tbproductos')
            ->selectRaw('COALESCE(SUM(stock), 0) AS unidades, COALESCE(SUM(stock * costo_promedio), 0) AS valor')
            ->first();

        return Inertia::render('admin/inventario/Index', [
            'libros' => $libros,
            'filtros' => ['q' => $filtros['q'] ?? '', 'stock' => $filtros['stock'] ?? ''],
            'totales' => ['unidades' => (int) $totales->unidades, 'valor' => round((float) $totales->valor, 2)],
        ]);
    }

    /**
     * Historial de movimientos de un libro (kardex) y formulario de ajuste.
     */
    public function show(Producto $producto): Response
    {
        $movimientos = DB::table('tbmovimientos_inventario as m')
            ->join('tbtipo_movimiento as t', 't.id_tipo_movimiento', '=', 'm.id_tipo_movimiento')
            ->leftJoin('tbdetalle_compras as dc', 'dc.id_detalle_compra', '=', 'm.id_detalle_compra')
            ->leftJoin('tbdetalle_venta as dv', 'dv.id_detalle_venta', '=', 'm.id_detalle_venta')
            ->leftJoin('tbdetalle_devolucion as dd', 'dd.id_detalle_devolucion', '=', 'm.id_detalle_devolucion')
            ->where('m.id_producto', $producto->id_producto)
            ->orderByDesc('m.fecha')
            ->orderByDesc('m.id_movimiento')
            ->select('m.id_movimiento', 'm.fecha', 'm.cantidad', 'm.costo_unitario', 'm.stock_resultante', 'm.observacion',
                't.nombre as tipo', 'dc.id_compra', 'dv.id_venta', 'dd.id_devolucion')
            ->paginate(20)
            ->through(fn ($m) => [
                'id_movimiento' => $m->id_movimiento,
                'fecha' => \Illuminate\Support\Carbon::parse($m->fecha)->format('d/m/Y H:i'),
                'tipo' => $m->tipo,
                'cantidad' => (int) $m->cantidad,
                'costo_unitario' => (float) $m->costo_unitario,
                'stock_resultante' => (int) $m->stock_resultante,
                'observacion' => $m->observacion,
                'origen' => match (true) {
                    $m->id_compra !== null => ['texto' => "Compra #{$m->id_compra}", 'compra' => $m->id_compra],
                    $m->id_venta !== null => ['texto' => 'Venta #'.str_pad((string) $m->id_venta, 6, '0', STR_PAD_LEFT), 'compra' => null],
                    $m->id_devolucion !== null => ['texto' => "Devolución #{$m->id_devolucion}", 'compra' => null],
                    default => ['texto' => 'Ajuste manual', 'compra' => null],
                },
            ]);

        $resumen = DB::table('tbmovimientos_inventario')
            ->where('id_producto', $producto->id_producto)
            ->selectRaw('COALESCE(SUM(CASE WHEN cantidad > 0 THEN cantidad END), 0) AS entradas')
            ->selectRaw('COALESCE(SUM(CASE WHEN cantidad < 0 THEN -cantidad END), 0) AS salidas')
            ->first();

        return Inertia::render('admin/inventario/Show', [
            'libro' => [
                'id_producto' => $producto->id_producto,
                'nombre' => $producto->nombre,
                'isbn' => $producto->isbn,
                'stock' => (int) $producto->stock,
                'stock_minimo' => (int) $producto->stock_minimo,
                'costo_promedio' => (float) $producto->costo_promedio,
                'valor' => round($producto->stock * (float) $producto->costo_promedio, 2),
                'entradas' => (int) $resumen->entradas,
                'salidas' => (int) $resumen->salidas,
            ],
            'movimientos' => $movimientos,
        ]);
    }

    public function ajustar(AjusteInventarioRequest $request, Producto $producto, AjusteInventarioService $ajustes): RedirectResponse
    {
        $datos = $request->validated();
        $cantidad = $datos['tipo'] === 'entrada' ? (int) $datos['cantidad'] : -(int) $datos['cantidad'];

        $movimiento = $ajustes->ajustar($producto, $cantidad, trim($datos['observacion']));

        return back()->with('exito', "Ajuste registrado. Stock actual de \"{$producto->nombre}\": {$movimiento->stock_resultante}.");
    }
}