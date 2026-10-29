<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LibroRequest;
use App\Models\Autor;
use App\Models\Categoria;
use App\Models\Editorial;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

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
            $isbn = preg_replace('/\D/', '', $texto);

            $consulta->where(function ($q) use ($patron, $isbn): void {
                $q->whereRaw('LOWER(nombre) LIKE ?', [$patron]);

                if ($isbn !== '') {
                    $q->orWhere('isbn', 'like', "%{$isbn}%");
                }
            });
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

    public function store(LibroRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $portada = $request->file('imagen');
        $rutaPortada = null;

        try {
            DB::transaction(function () use ($datos, $portada, &$rutaPortada): void {
                // El stock arranca en 0: solo sube con compras recibidas o ajustes.
                $producto = Producto::create([...$this->campos($datos), 'stock' => 0, 'costo_promedio' => 0]);
                $this->sincronizar($producto, $datos);

                if ($portada instanceof UploadedFile) {
                    $rutaPortada = "portadas/{$producto->id_producto}.jpg";
                    $this->guardarPortada($portada, $rutaPortada);
                }
            });
        } catch (\Throwable $exception) {
            if ($rutaPortada !== null) {
                Storage::disk('public')->delete($rutaPortada);
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

    public function update(LibroRequest $request, Producto $producto): RedirectResponse
    {
        $datos = $request->validated();
        $portada = $request->file('imagen');
        $rutaPortada = "portadas/{$producto->id_producto}.jpg";

        DB::transaction(function () use ($producto, $datos, $portada, $rutaPortada): void {
            $producto->update($this->campos($datos));
            $this->sincronizar($producto, $datos);

            if ($portada instanceof UploadedFile) {
                $this->guardarPortada($portada, $rutaPortada);
            }
        });

        return to_route('admin.libros.index')->with('exito', "Libro \"{$datos['nombre']}\" actualizado.");
    }

    /**
     * Solo se elimina un libro sin historial; si ya se vendió o compró, se perdería el kardex.
     */
    public function destroy(Producto $producto): RedirectResponse
    {
        $tieneHistorial = $producto->detallesVenta()->exists()
            || DB::table('tbdetalle_compras')->where('id_producto', $producto->id_producto)->exists()
            || DB::table('tbmovimientos_inventario')->where('id_producto', $producto->id_producto)->exists();

        if ($tieneHistorial) {
            return back()->with('error', "No se puede eliminar \"{$producto->nombre}\" porque ya tiene ventas, compras o movimientos de inventario.");
        }

        DB::transaction(function () use ($producto): void {
            DB::table('tboferta_producto')->where('id_producto', $producto->id_producto)->delete();
            $producto->delete();
        });

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

    private function guardarPortada(UploadedFile $archivo, string $ruta): void
    {
        if (Storage::disk('public')->putFileAs('portadas', $archivo, basename($ruta)) === false) {
            throw new \RuntimeException('No se pudo guardar la portada.');
        }
    }
}
