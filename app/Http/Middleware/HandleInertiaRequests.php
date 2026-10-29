<?php

namespace App\Http\Middleware;

use App\Models\Categoria;
use App\Services\CarritoService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'carrito' => fn () => [
                'cantidad' => app(CarritoService::class)->cantidadLibros(),
            ],
            'categorias' => fn () => Categoria::query()
                ->select(['id_categoria', 'nombre'])
                ->orderBy('nombre')
                ->get()
                ->map(fn (Categoria $categoria) => [
                    'id' => $categoria->id_categoria,
                    'nombre' => $categoria->nombre,
                ])
                ->values(),
            'flash' => fn () => [
                'exito' => $request->session()->get('exito'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }
}
