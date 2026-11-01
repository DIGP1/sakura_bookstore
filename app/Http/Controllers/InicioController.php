<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Producto;
use App\Services\CatalogoService;
use App\Services\OfertaService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InicioController extends Controller
{
    /**
     * Página de inicio de la tienda: destacados (más vendidos) y ofertas vigentes.
     */
    public function __invoke(CatalogoService $catalogo, OfertaService $ofertas): Response
    {
        // Más vendidos de los últimos 90 días, solo ventas completadas y con stock.
        $masVendidos = DB::table('tbdetalle_venta as d')
            ->join('tbventas as v', 'v.id_venta', '=', 'd.id_venta')
            ->join('tbestado_ventas as e', 'e.id_estado_ventas', '=', 'v.id_estado_ventas')
            ->where('e.nombre', 'COMPLETADA')
            ->where('v.fecha', '>=', now()->subDays(90))
            ->groupBy('d.id_producto')
            ->orderByRaw('SUM(d.cantidad) DESC')
            ->limit(30)
            ->pluck('d.id_producto');

        $destacados = Producto::with(CatalogoService::RELACIONES)
            ->whereIn('id_producto', $masVendidos)
            ->where('stock', '>', 0)
            ->get()
            ->sortBy(fn (Producto $producto) => $masVendidos->search($producto->id_producto))
            ->take(8);

        // Si todavía no hay ventas, se muestran los más recientes.
        if ($destacados->isEmpty()) {
            $destacados = Producto::with(CatalogoService::RELACIONES)
                ->where('stock', '>', 0)
                ->orderByDesc('id_producto')
                ->limit(8)
                ->get();
        }

        // Libros con oferta vigente, por el libro o por su categoría.
        $porLibro = $ofertas->porLibro();
        $idsEnOferta = $porLibro->flatMap(fn ($oferta) => $oferta->productos->pluck('id_producto'))->unique();
        $categoriasEnOferta = $porLibro->flatMap(fn ($oferta) => $oferta->categorias->pluck('id_categoria'))->unique();

        $enOferta = $porLibro->isEmpty() ? collect() : Producto::with(CatalogoService::RELACIONES)
            ->where('stock', '>', 0)
            ->where(fn ($consulta) => $consulta
                ->whereIn('id_producto', $idsEnOferta)
                ->orWhereHas('categorias', fn ($c) => $c->whereIn('tbcategoria.id_categoria', $categoriasEnOferta)))
            ->orderBy('nombre')
            ->limit(8)
            ->get();

        return Inertia::render('tienda/Inicio', [
            'destacados' => $destacados->map(fn ($p) => $catalogo->tarjeta($p))->values(),
            'enOferta' => $enOferta->map(fn ($p) => $catalogo->tarjeta($p))->values(),
            'ofertas' => $ofertas->vigentes()->map(fn ($oferta) => [
                'id' => $oferta->id_oferta,
                'nombre' => $oferta->nombre_oferta,
                'descripcion' => $oferta->esPorcentaje()
                    ? rtrim(rtrim($oferta->valor_descuento, '0'), '.').'% de descuento'
                    : '$'.$oferta->valor_descuento.' de descuento',
                'fecha_fin' => $oferta->fecha_fin?->format('d/m/Y'),
            ])->values(),
            'categorias' => Categoria::orderBy('nombre')->get()
                ->map(fn ($c) => ['id' => $c->id_categoria, 'nombre' => $c->nombre]),
        ]);
    }
}