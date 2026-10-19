<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OfertaRequest;
use App\Models\Categoria;
use App\Models\Oferta;
use App\Models\Producto;
use App\Models\TipoDescuento;
use App\Services\SincronizarNeo4jService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class OfertasController extends Controller
{
    /** En cuántas líneas de venta y ventas completas se usó la oferta. */
    private const USOS = '((SELECT COUNT(*) FROM tbdetalle_venta d WHERE d.id_oferta = tboferta.id_oferta)
                         + (SELECT COUNT(*) FROM tbventas v WHERE v.id_oferta = tboferta.id_oferta))';

    public function index(Request $request): Response
    {
        $texto = trim((string) $request->query('q', ''));

        $ofertas = Oferta::query()
            ->select('tboferta.*')
            ->selectRaw(self::USOS.' AS usos')
            ->with('tipoDescuento')
            ->withCount(['productos', 'categorias'])
            ->when($texto !== '', fn ($q) => $q->whereRaw('LOWER(nombre_oferta) LIKE ?', ['%'.mb_strtolower($texto).'%']))
            ->orderByDesc('fecha_inicio')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Oferta $oferta) => [
                'id_oferta' => $oferta->id_oferta,
                'nombre' => $oferta->nombre_oferta,
                'descuento' => $this->textoDescuento($oferta),
                'alcance' => match (true) {
                    $oferta->productos_count > 0 => "{$oferta->productos_count} libro(s)",
                    $oferta->categorias_count > 0 => "{$oferta->categorias_count} categoría(s)",
                    default => 'Toda la compra'.($oferta->monto_minimo ? ' desde $'.$oferta->monto_minimo : ''),
                },
                'fechas' => $oferta->fecha_inicio?->format('d/m/Y').' – '.$oferta->fecha_fin?->format('d/m/Y'),
                'situacion' => $this->situacion($oferta),
                'activa' => (bool) $oferta->estado,
                'usos' => (int) $oferta->usos,
            ]);

        return Inertia::render('admin/ofertas/Index', [
            'ofertas' => $ofertas,
            'filtros' => ['q' => $texto],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/ofertas/Form', [...$this->opciones(), 'oferta' => null]);
    }

    public function store(OfertaRequest $request, SincronizarNeo4jService $neo4j): RedirectResponse
    {
        $datos = $request->validated();
        $ofertaId = null;
        $neo4jSynced = false;

        try {
            DB::transaction(function () use ($datos, $neo4j, &$ofertaId, &$neo4jSynced): void {
                $oferta = Oferta::create($this->campos($datos));
                $ofertaId = (int) $oferta->id_oferta;
                $this->sincronizar($oferta, $datos);
                $neo4j->oferta($oferta->fresh());
                $neo4jSynced = true;
            });
        } catch (Throwable $exception) {
            if ($neo4jSynced && $ofertaId !== null) {
                try {
                    $neo4j->eliminarOferta($ofertaId);
                } catch (Throwable $compensationException) {
                    report($compensationException);
                }
            }

            throw $exception;
        }

        return to_route('admin.ofertas.index')->with('exito', "Oferta \"{$datos['nombre_oferta']}\" creada.");
    }

    public function edit(Oferta $oferta): Response
    {
        $oferta->load(['productos', 'categorias']);

        return Inertia::render('admin/ofertas/Form', [
            ...$this->opciones(),
            'oferta' => [
                'id_oferta' => $oferta->id_oferta,
                'nombre_oferta' => $oferta->nombre_oferta,
                'id_tipo_descuento' => $oferta->id_tipo_descuento,
                'valor_descuento' => (float) $oferta->valor_descuento,
                'fecha_inicio' => $oferta->fecha_inicio?->toDateString(),
                'fecha_fin' => $oferta->fecha_fin?->toDateString(),
                'estado' => (bool) $oferta->estado,
                'monto_minimo' => $oferta->monto_minimo !== null ? (float) $oferta->monto_minimo : null,
                'alcance' => $oferta->productos->isNotEmpty() ? 'libros' : ($oferta->categorias->isNotEmpty() ? 'categorias' : 'global'),
                'productos' => $oferta->productos->pluck('id_producto')->values(),
                'categorias' => $oferta->categorias->pluck('id_categoria')->values(),
            ],
        ]);
    }

    public function update(
        OfertaRequest $request,
        Oferta $oferta,
        SincronizarNeo4jService $neo4j,
    ): RedirectResponse {
        $datos = $request->validated();
        $ofertaAnterior = $oferta->load(['tipoDescuento', 'productos', 'categorias'])->replicate();
        $ofertaAnterior->id_oferta = $oferta->id_oferta;
        $ofertaAnterior->setRelation('tipoDescuento', $oferta->tipoDescuento);
        $ofertaAnterior->setRelation('productos', $oferta->productos);
        $ofertaAnterior->setRelation('categorias', $oferta->categorias);
        $neo4jSynced = false;

        try {
            DB::transaction(function () use ($oferta, $datos, $neo4j, &$neo4jSynced): void {
                $oferta->update($this->campos($datos));
                $this->sincronizar($oferta, $datos);
                $neo4j->oferta($oferta->fresh());
                $neo4jSynced = true;
            });
        } catch (Throwable $exception) {
            if ($neo4jSynced) {
                try {
                    $neo4j->oferta($ofertaAnterior);
                } catch (Throwable $compensationException) {
                    report($compensationException);
                }
            }

            throw $exception;
        }

        return to_route('admin.ofertas.index')->with('exito', "Oferta \"{$datos['nombre_oferta']}\" actualizada.");
    }

    /**
     * Activa o desactiva la oferta. La tienda la deja de aplicar al instante.
     */
    public function alternar(Oferta $oferta, SincronizarNeo4jService $neo4j): RedirectResponse
    {
        $ofertaAnterior = $oferta->load(['tipoDescuento', 'productos', 'categorias'])->replicate();
        $ofertaAnterior->id_oferta = $oferta->id_oferta;
        $ofertaAnterior->setRelation('tipoDescuento', $oferta->tipoDescuento);
        $ofertaAnterior->setRelation('productos', $oferta->productos);
        $ofertaAnterior->setRelation('categorias', $oferta->categorias);
        $neo4jSynced = false;

        try {
            DB::transaction(function () use ($oferta, $neo4j, &$neo4jSynced): void {
                $oferta->update(['estado' => ! $oferta->estado]);
                $neo4j->oferta($oferta->fresh());
                $neo4jSynced = true;
            });
        } catch (Throwable $exception) {
            if ($neo4jSynced) {
                try {
                    $neo4j->oferta($ofertaAnterior);
                } catch (Throwable $compensationException) {
                    report($compensationException);
                }
            }

            throw $exception;
        }

        return back()->with('exito', "Oferta \"{$oferta->nombre_oferta}\" ".($oferta->estado ? 'activada.' : 'desactivada.'));
    }

    /**
     * Solo se borra una oferta que nunca se usó; si ya se usó, se desactiva para no perder el historial.
     */
    public function destroy(Oferta $oferta, SincronizarNeo4jService $neo4j): RedirectResponse
    {
        $usos = (int) DB::table('tboferta')->where('id_oferta', $oferta->id_oferta)->selectRaw(self::USOS.' AS usos')->value('usos');

        if ($usos > 0) {
            return back()->with('error', "\"{$oferta->nombre_oferta}\" ya se usó en ventas. Desactivala en vez de borrarla.");
        }

        $ofertaAnterior = $oferta->load(['tipoDescuento', 'productos', 'categorias']);
        $neo4jSynced = false;

        try {
            DB::transaction(function () use ($oferta, $neo4j, &$neo4jSynced): void {
                $oferta->delete();
                $neo4j->eliminarOferta((int) $oferta->id_oferta);
                $neo4jSynced = true;
            });
        } catch (Throwable $exception) {
            if ($neo4jSynced) {
                try {
                    $neo4j->oferta($ofertaAnterior);
                } catch (Throwable $compensationException) {
                    report($compensationException);
                }
            }

            throw $exception;
        }

        return back()->with('exito', "Oferta \"{$oferta->nombre_oferta}\" eliminada.");
    }

    private function opciones(): array
    {
        return [
            'tipos' => TipoDescuento::orderBy('nombre')->get()
                ->map(fn ($t) => ['id' => $t->id_tipo_descuento, 'nombre' => $t->nombre]),
            'libros' => Producto::orderBy('nombre')->get(['id_producto', 'nombre'])
                ->map(fn ($p) => ['id' => $p->id_producto, 'nombre' => $p->nombre]),
            'categorias' => Categoria::orderBy('nombre')->get()
                ->map(fn ($c) => ['id' => $c->id_categoria, 'nombre' => $c->nombre]),
        ];
    }

    private function campos(array $datos): array
    {
        return [
            'nombre_oferta' => $datos['nombre_oferta'],
            'id_tipo_descuento' => $datos['id_tipo_descuento'],
            'valor_descuento' => $datos['valor_descuento'],
            'fecha_inicio' => $datos['fecha_inicio'],
            'fecha_fin' => $datos['fecha_fin'],
            'estado' => (bool) ($datos['estado'] ?? false),
            // El monto mínimo solo aplica a las ofertas sobre toda la compra.
            'monto_minimo' => $datos['alcance'] === 'global' ? ($datos['monto_minimo'] ?? null) : null,
        ];
    }

    private function sincronizar(Oferta $oferta, array $datos): void
    {
        $oferta->productos()->sync($datos['alcance'] === 'libros' ? $datos['productos'] : []);
        $oferta->categorias()->sync($datos['alcance'] === 'categorias' ? $datos['categorias'] : []);
    }

    private function textoDescuento(Oferta $oferta): string
    {
        return $oferta->esPorcentaje()
            ? rtrim(rtrim((string) $oferta->valor_descuento, '0'), '.').'%'
            : '$'.$oferta->valor_descuento;
    }

    private function situacion(Oferta $oferta): string
    {
        return match (true) {
            ! $oferta->estado => 'INACTIVA',
            $oferta->fecha_inicio?->isAfter(today()) => 'PROGRAMADA',
            $oferta->fecha_fin?->isBefore(today()) => 'VENCIDA',
            default => 'VIGENTE',
        };
    }
}
