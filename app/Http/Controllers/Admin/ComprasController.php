<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompraRequest;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\EstadoCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\InventarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ComprasController extends Controller
{
    private const TOTAL = '(SELECT COALESCE(SUM(d.cantidad * d.costo_unitario), 0) FROM tbdetalle_compras d WHERE d.id_compra = tbcompras.id_compra)';

    private const UNIDADES = '(SELECT COALESCE(SUM(d.cantidad), 0) FROM tbdetalle_compras d WHERE d.id_compra = tbcompras.id_compra)';

    /**
     * Listado de compras con filtros por estado, proveedor y factura.
     */
    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'estado' => ['nullable', 'in:PENDIENTE,RECIBIDA,ANULADA'],
            'proveedor' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:30'],
        ]);

        $compras = Compra::query()
            ->select('tbcompras.*')
            ->selectRaw(self::TOTAL.' AS total')
            ->selectRaw(self::UNIDADES.' AS unidades')
            ->with(['proveedor', 'estado'])
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->whereHas('estado', fn ($e) => $e->where('nombre', $estado)))
            ->when($filtros['proveedor'] ?? null, fn ($q, $id) => $q->where('id_proveedor', $id))
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->where('numero_factura', 'like', "%{$texto}%"))
            ->orderByDesc('fecha')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Compra $compra) => [
                'id_compra' => $compra->id_compra,
                'fecha' => $compra->fecha?->format('d/m/Y'),
                'proveedor' => $compra->proveedor?->nombre,
                'numero_factura' => $compra->numero_factura,
                'unidades' => (int) $compra->unidades,
                'total' => round((float) $compra->total, 2),
                'estado' => $compra->estado?->nombre,
            ]);

        return Inertia::render('admin/compras/Index', [
            'compras' => $compras,
            'filtros' => [
                'estado' => $filtros['estado'] ?? '',
                'proveedor' => $filtros['proveedor'] ?? '',
                'q' => $filtros['q'] ?? '',
            ],
            'proveedores' => Proveedor::orderBy('nombre')->get()->map(fn ($p) => ['id' => $p->id_proveedor, 'nombre' => $p->nombre]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/compras/Create', [
            'proveedores' => Proveedor::orderBy('nombre')->get()->map(fn ($p) => ['id' => $p->id_proveedor, 'nombre' => $p->nombre]),
            'libros' => Producto::orderBy('nombre')->get(['id_producto', 'nombre', 'isbn', 'stock', 'costo_promedio'])
                ->map(fn (Producto $libro) => [
                    'id' => $libro->id_producto,
                    'nombre' => $libro->nombre,
                    'isbn' => $libro->isbn,
                    'stock' => (int) $libro->stock,
                    'costo' => (float) $libro->costo_promedio,
                ]),
            // Lista de precios de cada proveedor, para prellenar el costo: { id_proveedor: { id_producto: precio } }.
            'precios' => DB::table('producto_proveedor')->get()
                ->groupBy('id_proveedor')
                ->map(fn ($filas) => $filas->mapWithKeys(fn ($f) => [$f->id_producto => (float) $f->precio_costo])),
        ]);
    }

    /**
     * Registra la compra como PENDIENTE. El stock no se mueve hasta que se reciba.
     */
    public function store(CompraRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $compra = DB::transaction(function () use ($datos): Compra {
            $compra = Compra::create([
                'id_proveedor' => $datos['id_proveedor'],
                'numero_factura' => trim($datos['numero_factura']),
                'fecha' => now(),
                'id_estado_compra' => $this->idEstado('PENDIENTE'),
            ]);

            foreach ($datos['lineas'] as $linea) {
                DetalleCompra::create([
                    'id_compra' => $compra->id_compra,
                    'id_producto' => $linea['id_producto'],
                    'cantidad' => $linea['cantidad'],
                    'costo_unitario' => $linea['costo_unitario'],
                ]);
            }

            // La lista de precios del proveedor queda con el último costo.
            DB::table('producto_proveedor')->upsert(
                collect($datos['lineas'])->map(fn ($linea) => [
                    'id_producto' => $linea['id_producto'],
                    'id_proveedor' => $datos['id_proveedor'],
                    'precio_costo' => $linea['costo_unitario'],
                ])->all(),
                ['id_producto', 'id_proveedor'],
                ['precio_costo'],
            );

            return $compra;
        });

        return to_route('admin.compras.show', $compra)
            ->with('exito', 'Compra registrada como pendiente. Cuando llegue la mercadería, marcala como recibida.');
    }

    public function show(Compra $compra): Response
    {
        $compra->load(['proveedor', 'estado', 'detalles.producto']);

        return Inertia::render('admin/compras/Show', [
            'compra' => [
                'id_compra' => $compra->id_compra,
                'fecha' => $compra->fecha?->format('d/m/Y H:i'),
                'proveedor' => $compra->proveedor?->nombre,
                'numero_factura' => $compra->numero_factura,
                'estado' => $compra->estado?->nombre,
                'lineas' => $compra->detalles->map(fn ($d) => [
                    'id_producto' => $d->id_producto,
                    'nombre' => $d->producto?->nombre,
                    'isbn' => $d->producto?->isbn,
                    'cantidad' => (int) $d->cantidad,
                    'costo_unitario' => (float) $d->costo_unitario,
                    'subtotal' => round((int) $d->cantidad * (float) $d->costo_unitario, 2),
                    'stock_actual' => (int) $d->producto?->stock,
                ]),
                'total' => round($compra->detalles->sum(fn ($d) => (int) $d->cantidad * (float) $d->costo_unitario), 2),
            ],
        ]);
    }

    /**
     * La mercadería llegó: suma el stock, recalcula el costo promedio y registra el kardex (InventarioService).
     */
    public function recibir(Compra $compra, InventarioService $inventario): RedirectResponse
    {
        if ($compra->estado?->nombre !== 'PENDIENTE') {
            return back()->with('error', 'Solo se pueden recibir compras pendientes.');
        }

        $inventario->receivePurchase($compra);

        return back()->with('exito', 'Compra recibida: el stock de los libros ya se actualizó y quedó en el kardex.');
    }

    /**
     * Anula una compra que todavía no se recibió (el stock nunca se movió).
     */
    public function anular(Compra $compra): RedirectResponse
    {
        if ($compra->estado?->nombre !== 'PENDIENTE') {
            return back()->with('error', 'Solo se pueden anular compras pendientes. Una compra recibida se corrige con un ajuste de inventario.');
        }

        $compra->update(['id_estado_compra' => $this->idEstado('ANULADA')]);

        return back()->with('exito', 'Compra anulada.');
    }

    private function idEstado(string $nombre): int
    {
        return (int) (EstadoCompra::query()->where('nombre', $nombre)->value('id_estado_compra')
            ?? throw new RuntimeException("No existe el estado de compra {$nombre}."));
    }
}