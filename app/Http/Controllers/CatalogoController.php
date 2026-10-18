<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Editorial;
use App\Models\Producto;
use App\Services\CatalogoService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogoController extends Controller
{
    public function __construct(private CatalogoService $catalogo) {}

    /**
     * Catálogo paginado con búsqueda (título, autor o ISBN) y filtros.
     */
    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'categoria' => ['nullable', 'integer'],
            'editorial' => ['nullable', 'integer'],
            'precio_min' => ['nullable', 'numeric', 'min:0'],
            'precio_max' => ['nullable', 'numeric', 'min:0'],
            'disponibles' => ['nullable', 'boolean'],
            'orden' => ['nullable', 'in:titulo,precio_asc,precio_desc,recientes'],
        ]);

        $consulta = Producto::with(CatalogoService::RELACIONES);

        if ($texto = trim($filtros['q'] ?? '')) {
            $patron = '%'.mb_strtolower($texto).'%';
            $isbn = preg_replace('/\D/', '', $texto);

            $consulta->where(function ($q) use ($patron, $isbn) {
                $q->whereRaw('LOWER(nombre) LIKE ?', [$patron])
                    ->orWhereHas('autores', fn ($a) => $a->whereRaw('LOWER(tbautores.nombre) LIKE ?', [$patron]));

                if ($isbn !== '') {
                    $q->orWhere('isbn', 'like', "%{$isbn}%");
                }
            });
        }

        if (! empty($filtros['categoria'])) {
            $consulta->whereHas('categorias', fn ($c) => $c->where('tbcategoria.id_categoria', $filtros['categoria']));
        }

        if (! empty($filtros['editorial'])) {
            $consulta->where('id_editorial', $filtros['editorial']);
        }

        if (isset($filtros['precio_min'])) {
            $consulta->where('precio_unitario', '>=', $filtros['precio_min']);
        }

        if (isset($filtros['precio_max'])) {
            $consulta->where('precio_unitario', '<=', $filtros['precio_max']);
        }

        if ($request->boolean('disponibles')) {
            $consulta->where('stock', '>', 0);
        }

        match ($filtros['orden'] ?? 'titulo') {
            'precio_asc' => $consulta->orderBy('precio_unitario'),
            'precio_desc' => $consulta->orderByDesc('precio_unitario'),
            'recientes' => $consulta->orderByDesc('anio_publicacion')->orderBy('nombre'),
            default => $consulta->orderBy('nombre'),
        };

        return Inertia::render('catalogo/Index', [
            'productos' => $consulta->paginate(12)->withQueryString()
                ->through(fn (Producto $producto) => $this->catalogo->tarjeta($producto)),
            'filtros' => [
                'q' => $filtros['q'] ?? '',
                'categoria' => $filtros['categoria'] ?? '',
                'editorial' => $filtros['editorial'] ?? '',
                'precio_min' => $filtros['precio_min'] ?? '',
                'precio_max' => $filtros['precio_max'] ?? '',
                'disponibles' => $request->boolean('disponibles'),
                'orden' => $filtros['orden'] ?? 'titulo',
            ],
            'categorias' => Categoria::orderBy('nombre')->get()
                ->map(fn ($c) => ['id' => $c->id_categoria, 'nombre' => $c->nombre]),
            'editoriales' => Editorial::orderBy('nombre')->get()
                ->map(fn ($e) => ['id' => $e->id_editorial, 'nombre' => $e->nombre]),
        ]);
    }

    /**
     * Detalle del libro con libros del mismo autor y de la misma categoría.
     */
    public function show(Producto $producto): Response
    {
        $producto->load(CatalogoService::RELACIONES);

        $idsAutores = $producto->autores->pluck('id_autor');
        $idsCategorias = $producto->categorias->pluck('id_categoria');

        $mismoAutor = $idsAutores->isEmpty() ? collect() : Producto::with(CatalogoService::RELACIONES)
            ->where('id_producto', '!=', $producto->id_producto)
            ->whereHas('autores', fn ($a) => $a->whereIn('tbautores.id_autor', $idsAutores))
            ->orderByDesc('stock')
            ->limit(4)
            ->get();

        $excluir = $mismoAutor->pluck('id_producto')->push($producto->id_producto);

        $mismaCategoria = $idsCategorias->isEmpty() ? collect() : Producto::with(CatalogoService::RELACIONES)
            ->whereNotIn('id_producto', $excluir)
            ->whereHas('categorias', fn ($c) => $c->whereIn('tbcategoria.id_categoria', $idsCategorias))
            ->where('stock', '>', 0)
            ->inRandomOrder()
            ->limit(4)
            ->get();

        return Inertia::render('catalogo/Show', [
            'producto' => $this->catalogo->ficha($producto),
            'mismoAutor' => $mismoAutor->map(fn ($p) => $this->catalogo->tarjeta($p))->values(),
            'mismaCategoria' => $mismaCategoria->map(fn ($p) => $this->catalogo->tarjeta($p))->values(),
        ]);
    }
}