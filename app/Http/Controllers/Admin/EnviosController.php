<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Envio;
use App\Models\EstadoEnvio;
use App\Models\HistorialEnvio;
use App\Models\ProveedorEnvio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EnviosController extends Controller
{
    /** A qué estados puede pasar un envío desde cada estado. */
    private const TRANSICIONES = [
        'PENDIENTE' => ['EN_CAMINO', 'FALLIDO'],
        'EN_CAMINO' => ['ENTREGADO', 'FALLIDO', 'DEVUELTO'],
        'FALLIDO' => ['EN_CAMINO'],
        'ENTREGADO' => [],
        'DEVUELTO' => [],
    ];

    private const TEXTOS = [
        'EN_CAMINO' => 'El pedido salió de la tienda',
        'ENTREGADO' => 'Pedido entregado',
        'FALLIDO' => 'No se pudo entregar el pedido',
        'DEVUELTO' => 'El pedido regresó a la tienda',
    ];

    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::in(array_keys(self::TRANSICIONES))],
            'proveedor' => ['nullable', 'integer'],
            'atrasados' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $envios = Envio::query()
            ->with(['estado', 'tipoEnvio', 'proveedor', 'distrito.municipio', 'venta.cliente'])
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->whereHas('estado', fn ($e) => $e->where('nombre', $estado)))
            ->when($filtros['proveedor'] ?? null, fn ($q, $id) => $q->where('id_proveedor_envio', $id))
            ->when($request->boolean('atrasados'), fn ($q) => $q
                ->whereDate('fecha_estimada', '<', today())
                ->whereHas('estado', fn ($e) => $e->whereIn('nombre', ['PENDIENTE', 'EN_CAMINO'])))
            ->when(trim($filtros['q'] ?? '') !== '', function ($q) use ($filtros) {
                $texto = trim($filtros['q'], " #\t");
                $q->where(function ($w) use ($texto) {
                    $w->whereHas('venta.cliente', fn ($c) => $c->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($texto).'%']))
                        ->orWhereRaw('LOWER(nombre_receptor) LIKE ?', ['%'.mb_strtolower($texto).'%']);
                    if (ctype_digit($texto)) {
                        $w->orWhere('id_venta', (int) $texto);
                    }
                });
            })
            ->orderByRaw("CASE WHEN id_estado_envio IN (SELECT id_estado_envio FROM tbestado_envio WHERE nombre IN ('PENDIENTE', 'EN_CAMINO')) THEN 0 ELSE 1 END")
            ->orderBy('fecha_estimada')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Envio $envio) => [
                'id_envio' => $envio->id_envio,
                'id_venta' => $envio->id_venta,
                'numero' => str_pad((string) $envio->id_venta, 6, '0', STR_PAD_LEFT),
                'cliente' => $envio->venta?->cliente?->nombre,
                'destino' => collect([$envio->distrito?->nombre, $envio->distrito?->municipio?->nombre])->filter()->join(', '),
                'tipo' => $envio->tipoEnvio?->nombre,
                'proveedor' => $envio->proveedor?->nombre,
                'fecha_estimada' => $envio->fecha_estimada?->format('d/m/Y'),
                'estado' => $envio->estado?->nombre,
                'atrasado' => in_array($envio->estado?->nombre, ['PENDIENTE', 'EN_CAMINO'], true)
                    && $envio->fecha_estimada?->lt(today()),
            ]);

        // Cuántos envíos hay en cada estado, para las tarjetas de arriba.
        $conteo = DB::table('tbenvios as e')
            ->join('tbestado_envio as s', 's.id_estado_envio', '=', 'e.id_estado_envio')
            ->groupBy('s.nombre')
            ->selectRaw('s.nombre, COUNT(*) AS total')
            ->pluck('total', 'nombre');

        return Inertia::render('admin/envios/Index', [
            'envios' => $envios,
            'conteo' => collect(array_keys(self::TRANSICIONES))->mapWithKeys(fn ($e) => [$e => (int) ($conteo[$e] ?? 0)]),
            'filtros' => [
                'estado' => $filtros['estado'] ?? '',
                'proveedor' => $filtros['proveedor'] ?? '',
                'atrasados' => $request->boolean('atrasados'),
                'q' => $filtros['q'] ?? '',
            ],
            'proveedores' => ProveedorEnvio::orderBy('nombre')->get()->map(fn ($p) => ['id' => $p->id_proveedor_envio, 'nombre' => $p->nombre]),
        ]);
    }

    public function show(Envio $envio): Response
    {
        $envio->load([
            'estado', 'tipoEnvio', 'proveedor', 'distrito.municipio.departamento',
            'venta.cliente', 'venta.estado', 'venta.detalles.producto',
            'historial' => fn ($q) => $q->orderBy('fecha')->orderBy('id_historial'),
            'historial.estado',
        ]);

        $estado = $envio->estado?->nombre;
        $ventaAnulada = $envio->venta?->estado?->nombre === 'ANULADA';

        return Inertia::render('admin/envios/Show', [
            'envio' => [
                'id_envio' => $envio->id_envio,
                'id_venta' => $envio->id_venta,
                'numero' => str_pad((string) $envio->id_venta, 6, '0', STR_PAD_LEFT),
                'estado' => $estado,
                'tipo' => $envio->tipoEnvio?->nombre,
                'id_proveedor_envio' => $envio->id_proveedor_envio,
                'proveedor' => $envio->proveedor?->nombre,
                'cliente' => $envio->venta?->cliente?->nombre,
                'receptor' => $envio->nombre_receptor,
                'telefono' => $envio->telefono_receptor,
                'direccion' => $envio->direccion_entrega,
                'ubicacion' => collect([
                    $envio->distrito?->nombre,
                    $envio->distrito?->municipio?->nombre,
                    $envio->distrito?->municipio?->departamento?->nombre,
                ])->filter()->join(', '),
                'costo' => (float) $envio->costo_envio,
                'fecha_estimada' => $envio->fecha_estimada?->format('d/m/Y'),
                'fecha_de_envio' => $envio->fecha_de_envio?->format('d/m/Y H:i'),
                'fecha_entrega' => $envio->fecha_entrega?->format('d/m/Y H:i'),
                'libros' => $envio->venta?->detalles->map(fn ($d) => ['nombre' => $d->producto?->nombre, 'cantidad' => (int) $d->cantidad]) ?? [],
                'historial' => $envio->historial->map(fn ($h) => [
                    'estado' => $h->estado?->nombre,
                    'fecha' => $h->fecha?->format('d/m/Y H:i'),
                    'observacion' => $h->observacion,
                ]),
                'venta_anulada' => $ventaAnulada,
                'siguientes' => $ventaAnulada ? [] : (self::TRANSICIONES[$estado] ?? []),
                'puede_cambiar_proveedor' => ! $ventaAnulada && in_array($estado, ['PENDIENTE', 'FALLIDO'], true),
            ],
            'proveedores' => ProveedorEnvio::where('activo', true)->orderBy('nombre')->get()
                ->map(fn ($p) => ['id' => $p->id_proveedor_envio, 'nombre' => $p->nombre]),
        ]);
    }

    /**
     * Cambia el estado del envío, guarda la fecha que corresponde y deja el movimiento en el historial.
     */
    public function cambiarEstado(Request $request, Envio $envio): RedirectResponse
    {
        $envio->load(['estado', 'venta.estado']);
        $actual = $envio->estado?->nombre;

        if ($envio->venta?->estado?->nombre === 'ANULADA') {
            return back()->with('error', 'Este envío pertenece a una venta anulada.');
        }

        $datos = $request->validate([
            'estado' => ['required', Rule::in(self::TRANSICIONES[$actual] ?? [])],
            'observacion' => ['nullable', 'string', 'max:255'],
            'id_proveedor_envio' => ['nullable', 'integer', Rule::exists('tbproveedores_envios', 'id_proveedor_envio')->where('activo', true)],
        ], [
            'estado.in' => 'Ese cambio de estado no está permitido.',
        ]);

        $nuevo = $datos['estado'];
        $idEstado = EstadoEnvio::query()->where('nombre', $nuevo)->value('id_estado_envio');

        DB::transaction(function () use ($envio, $datos, $nuevo, $idEstado, $actual): void {
            $cambios = ['id_estado_envio' => $idEstado];

            if ($nuevo === 'EN_CAMINO' && $envio->fecha_de_envio === null) {
                $cambios['fecha_de_envio'] = now();
            }
            if ($nuevo === 'ENTREGADO') {
                $cambios['fecha_entrega'] = now();
            }
            if (! empty($datos['id_proveedor_envio']) && in_array($actual, ['PENDIENTE', 'FALLIDO'], true)) {
                $cambios['id_proveedor_envio'] = $datos['id_proveedor_envio'];
            }

            $envio->update($cambios);

            HistorialEnvio::create([
                'id_envio' => $envio->id_envio,
                'id_estado_envio' => $idEstado,
                'observacion' => trim($datos['observacion'] ?? '') ?: self::TEXTOS[$nuevo],
            ]);
        });

        return back()->with('exito', "Envío del pedido #{$envio->id_venta} actualizado.");
    }
}