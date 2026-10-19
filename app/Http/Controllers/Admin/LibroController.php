<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LibroRequest;
use App\Models\Autor;
use App\Models\Categoria;
use App\Models\Editorial;
use App\Models\Producto;
use App\Services\SincronizarNeo4jService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class LibroController extends Controller
{
    public const FORMATOS = ['RUSTICA' => 'Rústica', 'TAPA_DURA' => 'Tapa dura', 'BOLSILLO' => 'Bolsillo'];

    /**
     * Listado de libros con búsqueda y filtros.
     */
    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'categoria' => ['nullable', 'integer'],
            'stock' => ['nullable', 'in:bajo,agotado'],
        ]);

        $consulta = Producto::query()->with(['editorial', 'autores', 'categorias']);

        if ($texto = trim($filtros['q'] ?? '')) {
            $patron = '%'.mb_strtolower($texto).'%';
            $consulta->where(fn ($q) => $q
                ->whereRaw('LOWER(nombre) LIKE ?', [$patron])
                ->orWhere('isbn', 'like', '%'.preg_replace('/\D/', '', $texto).'%'));
        }

        if (! empty($filtros['categoria'])) {
            $consulta->whereHas('categorias', fn ($c) => $c->where('tbcategoria.id_categoria', $filtros['categoria']));
        }

        match ($filtros['stock'] ?? null) {
            'bajo' => $consulta->whereColumn('stock', '<=', 'stock_minimo')->where('stock', '>', 0),
            'agotado' => $consulta->where('stock', 0),
            default => null,
        };

        return Inertia::render('admin/libros/Index', [
            'libros' => $consulta->orderBy('nombre')->paginate(15)->withQueryString()
                ->through(fn (Producto $producto) => [
                    'id_producto' => $producto->id_producto,
                    'isbn' => $producto->isbn,
                    'nombre' => $producto->nombre,
                    'autores' => $producto->autores->pluck('nombre')->unique()->join(', '),
                    'editorial' => $producto->editorial?->nombre,
                    'categorias' => $producto->categorias->pluck('nombre')->join(', '),
                    'precio' => (float) $producto->precio_unitario,
                    'stock' => (int) $producto->stock,
                    'stock_minimo' => (int) $producto->stock_minimo,
                ]),
            'filtros' => [
                'q' => $filtros['q'] ?? '',
                'categoria' => $filtros['categoria'] ?? '',
                'stock' => $filtros['stock'] ?? '',
            ],
            'categorias' => Categoria::orderBy('nombre')->get()
                ->map(fn ($c) => ['id' => $c->id_categoria, 'nombre' => $c->nombre]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/libros/Form', [
            ...$this->opciones(),
            'libro' => null,
        ]);
    }

    public function store(LibroRequest $request, SincronizarNeo4jService $neo4j): RedirectResponse
    {
        $datos = $request->validated();
        $productoId = null;
        $neo4jSynced = false;

        try {
            DB::transaction(function () use ($datos, $neo4j, &$productoId, &$neo4jSynced): void {
                // El stock arranca en 0: solo sube con compras recibidas o ajustes.
                $producto = Producto::create([...$this->campos($datos), 'stock' => 0, 'costo_promedio' => 0]);
                $productoId = (int) $producto->id_producto;
                $this->sincronizar($producto, $datos);
                $neo4j->producto($producto->fresh());
                $neo4jSynced = true;
            });
        } catch (Throwable $exception) {
            if ($neo4jSynced && $productoId !== null) {
                try {
                    $neo4j->eliminarProducto($productoId);
                } catch (Throwable $compensationException) {
                    report($compensationException);
                }
            }

            throw $exception;
        }

        return to_route('admin.libros.index')->with('exito', "Libro \"{$datos['nombre']}\" creado.");
    }

    public function edit(Producto $producto): Response
    {
        $producto->load(['autores', 'categorias']);

        return Inertia::render('admin/libros/Form', [
            ...$this->opciones(),
            'libro' => [
                'id_producto' => $producto->id_producto,
                'isbn' => $producto->isbn,
                'nombre' => $producto->nombre,
                'id_editorial' => $producto->id_editorial,
                'anio_publicacion' => $producto->anio_publicacion,
                'idioma' => $producto->idioma,
                'formato' => $producto->formato,
                'num_paginas' => $producto->num_paginas,
                'precio_unitario' => (float) $producto->precio_unitario,
                'stock_minimo' => (int) $producto->stock_minimo,
                'stock' => (int) $producto->stock,
                'costo_promedio' => (float) $producto->costo_promedio,
                'autores' => $producto->autores->pluck('id_autor')->unique()->values(),
                'categorias' => $producto->categorias->pluck('id_categoria')->values(),
            ],
        ]);
    }

    public function update(
        LibroRequest $request,
        Producto $producto,
        SincronizarNeo4jService $neo4j,
    ): RedirectResponse {
        $datos = $request->validated();
        $productoAnterior = $producto->load(['editorial', 'autores', 'categorias'])->replicate();
        $productoAnterior->id_producto = $producto->id_producto;
        $productoAnterior->setRelation('editorial', $producto->editorial);
        $productoAnterior->setRelation('autores', $producto->autores);
        $productoAnterior->setRelation('categorias', $producto->categorias);
        $neo4jSynced = false;

        try {
            DB::transaction(function () use ($producto, $datos, $neo4j, &$neo4jSynced): void {
                $producto->update($this->campos($datos));
                $this->sincronizar($producto, $datos);
                $neo4j->producto($producto->fresh());
                $neo4jSynced = true;
            });
        } catch (Throwable $exception) {
            if ($neo4jSynced) {
                try {
                    $neo4j->producto($productoAnterior);
                } catch (Throwable $compensationException) {
                    report($compensationException);
                }
            }

            throw $exception;
        }

        return to_route('admin.libros.index')->with('exito', "Libro \"{$datos['nombre']}\" actualizado.");
    }

    /**
     * Solo se elimina un libro sin historial; si ya se vendió o compró, se perdería el kardex.
     */
    public function destroy(Producto $producto, SincronizarNeo4jService $neo4j): RedirectResponse
    {
        $tieneHistorial = $producto->detallesVenta()->exists()
            || DB::table('tbdetalle_compras')->where('id_producto', $producto->id_producto)->exists()
            || DB::table('tbmovimientos_inventario')->where('id_producto', $producto->id_producto)->exists();

        if ($tieneHistorial) {
            return back()->with('error', "No se puede eliminar \"{$producto->nombre}\" porque ya tiene ventas, compras o movimientos de inventario.");
        }

        $productoAnterior = $producto->load(['editorial', 'autores', 'categorias']);
        $neo4jSynced = false;

        try {
            DB::transaction(function () use ($producto, $neo4j, &$neo4jSynced): void {
                DB::table('tboferta_producto')->where('id_producto', $producto->id_producto)->delete();
                $producto->delete();
                $neo4j->eliminarProducto((int) $producto->id_producto);
                $neo4jSynced = true;
            });
        } catch (Throwable $exception) {
            if ($neo4jSynced) {
                try {
                    $neo4j->producto($productoAnterior);
                } catch (Throwable $compensationException) {
                    report($compensationException);
                }
            }

            throw $exception;
        }

        return to_route('admin.libros.index')->with('exito', "Libro \"{$producto->nombre}\" eliminado.");
    }

    /**
     * Listas para los selects del formulario.
     */
    private function opciones(): array
    {
        return [
            'editoriales' => Editorial::orderBy('nombre')->get()
                ->map(fn ($e) => ['id' => $e->id_editorial, 'nombre' => $e->nombre]),
            'autores' => Autor::orderBy('nombre')->get()
                ->map(fn ($a) => ['id' => $a->id_autor, 'nombre' => $a->nombre]),
            'categorias' => Categoria::orderBy('nombre')->get()
                ->map(fn ($c) => ['id' => $c->id_categoria, 'nombre' => $c->nombre]),
            'formatos' => collect(self::FORMATOS)
                ->map(fn ($nombre, $valor) => ['valor' => $valor, 'nombre' => $nombre])
                ->values(),
        ];
    }

    private function campos(array $datos): array
    {
        return [
            'isbn' => $datos['isbn'],
            'nombre' => $datos['nombre'],
            'id_editorial' => $datos['id_editorial'],
            'anio_publicacion' => $datos['anio_publicacion'] ?? null,
            'idioma' => $datos['idioma'],
            'formato' => $datos['formato'],
            'num_paginas' => $datos['num_paginas'] ?? null,
            'precio_unitario' => $datos['precio_unitario'],
            'stock_minimo' => $datos['stock_minimo'],
        ];
    }

    /**
     * Autores y categorías. A los autores que ya estaban se les respeta su rol (autor, traductor...).
     */
    private function sincronizar(Producto $producto, array $datos): void
    {
        $actuales = $producto->autores()->pluck('tbautores.id_autor')->unique()->all();
        $nuevos = array_map('intval', $datos['autores']);

        $producto->autores()->detach(array_diff($actuales, $nuevos));
        foreach (array_diff($nuevos, $actuales) as $idAutor) {
            $producto->autores()->attach($idAutor, ['rol' => 'AUTOR']);
        }

        $producto->categorias()->sync($datos['categorias']);
    }
}
