<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Autor;
use App\Models\Categoria;
use App\Models\Editorial;
use App\Services\SincronizarNeo4jService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Un solo controlador para los catálogos sencillos del panel:
 * autores, editoriales, categorías y proveedores. Cada uno define sus campos aquí.
 */
class CatalogosController extends Controller
{
    public const TIPOS = ['autores', 'editoriales', 'categorias', 'proveedores'];

    private const CATALOGOS = [
        'autores' => [
            'titulo' => 'Autores',
            'singular' => 'autor',
            'tabla' => 'tbautores',
            'llave' => 'id_autor',
            'campos' => [
                ['campo' => 'nombre', 'etiqueta' => 'Nombre', 'reglas' => ['required', 'string', 'max:120'], 'unico' => false],
                ['campo' => 'nacionalidad', 'etiqueta' => 'Nacionalidad', 'reglas' => ['nullable', 'string', 'max:60'], 'unico' => false],
            ],
            'usos' => '(SELECT COUNT(*) FROM producto_autor u WHERE u.id_autor = tbautores.id_autor)',
            'usos_texto' => 'libros',
        ],
        'editoriales' => [
            'titulo' => 'Editoriales',
            'singular' => 'editorial',
            'tabla' => 'tbeditoriales',
            'llave' => 'id_editorial',
            'campos' => [
                ['campo' => 'nombre', 'etiqueta' => 'Nombre', 'reglas' => ['required', 'string', 'max:120'], 'unico' => true],
                ['campo' => 'pais', 'etiqueta' => 'País', 'reglas' => ['nullable', 'string', 'max:60'], 'unico' => false],
            ],
            'usos' => '(SELECT COUNT(*) FROM tbproductos u WHERE u.id_editorial = tbeditoriales.id_editorial)',
            'usos_texto' => 'libros',
        ],
        'categorias' => [
            'titulo' => 'Categorías',
            'singular' => 'categoría',
            'tabla' => 'tbcategoria',
            'llave' => 'id_categoria',
            'campos' => [
                ['campo' => 'nombre', 'etiqueta' => 'Nombre', 'reglas' => ['required', 'string', 'max:60'], 'unico' => true],
            ],
            'usos' => '((SELECT COUNT(*) FROM producto_categoria u WHERE u.id_categoria = tbcategoria.id_categoria)
                      + (SELECT COUNT(*) FROM tboferta_categoria o WHERE o.id_categoria = tbcategoria.id_categoria))',
            'usos_texto' => 'libros y ofertas',
        ],
        'proveedores' => [
            'titulo' => 'Proveedores',
            'singular' => 'proveedor',
            'tabla' => 'tbproveedor',
            'llave' => 'id_proveedor',
            'campos' => [
                ['campo' => 'nombre', 'etiqueta' => 'Nombre', 'reglas' => ['required', 'string', 'max:120'], 'unico' => true],
                ['campo' => 'telefono', 'etiqueta' => 'Teléfono', 'reglas' => ['nullable', 'regex:/^[0-9+\-\s]{7,15}$/'], 'unico' => false],
                ['campo' => 'direccion', 'etiqueta' => 'Dirección', 'reglas' => ['nullable', 'string', 'max:255'], 'unico' => false],
            ],
            'usos' => '((SELECT COUNT(*) FROM tbcompras u WHERE u.id_proveedor = tbproveedor.id_proveedor)
                      + (SELECT COUNT(*) FROM producto_proveedor p WHERE p.id_proveedor = tbproveedor.id_proveedor))',
            'usos_texto' => 'compras y precios',
        ],
    ];

    public function index(Request $request): Response
    {
        [$tipo, $config] = $this->catalogo($request);
        $texto = trim((string) $request->query('q', ''));

        $filas = DB::table($config['tabla'])
            ->select($config['tabla'].'.*')
            ->selectRaw("{$config['usos']} AS usos")
            ->when($texto !== '', fn ($q) => $q->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($texto).'%']))
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($fila) => [...(array) $fila, 'id' => $fila->{$config['llave']}, 'usos' => (int) $fila->usos]);

        return Inertia::render('admin/catalogos/Index', [
            'tipo' => $tipo,
            'titulo' => $config['titulo'],
            'singular' => $config['singular'],
            'usosTexto' => $config['usos_texto'],
            'campos' => collect($config['campos'])->map(fn ($c) => [
                'campo' => $c['campo'],
                'etiqueta' => $c['etiqueta'],
                'requerido' => in_array('required', $c['reglas'], true),
            ]),
            'filas' => $filas,
            'filtros' => ['q' => $texto],
        ]);
    }

    public function store(Request $request, SincronizarNeo4jService $neo4j): RedirectResponse
    {
        [$tipo, $config] = $this->catalogo($request);
        $datos = $request->validate($this->reglas($config), $this->mensajes());

        $id = null;
        $neo4jSynced = false;

        try {
            DB::transaction(function () use ($tipo, $config, $datos, $neo4j, &$id, &$neo4jSynced): void {
                $id = (int) DB::table($config['tabla'])->insertGetId($datos, $config['llave']);

                if ($this->esSincronizable($tipo)) {
                    $this->sincronizarNeo4j($tipo, $this->modelo($tipo, $id), $neo4j);
                    $neo4jSynced = true;
                }
            });
        } catch (Throwable $exception) {
            if ($neo4jSynced && $id !== null && $this->esSincronizable($tipo)) {
                try {
                    $this->eliminarNeo4j($tipo, $id, $neo4j);
                } catch (Throwable $compensationException) {
                    report($compensationException);
                }
            }

            throw $exception;
        }

        return back()->with('exito', ucfirst($config['singular'])." \"{$datos['nombre']}\" creado.");
    }

    public function update(Request $request, SincronizarNeo4jService $neo4j): RedirectResponse
    {
        [$tipo, $config] = $this->catalogo($request);
        $id = (int) $request->route('id');
        $datos = $request->validate($this->reglas($config, $id), $this->mensajes());

        $anterior = $this->esSincronizable($tipo) ? $this->modelo($tipo, $id) : null;
        $neo4jSynced = false;

        try {
            DB::transaction(function () use ($tipo, $config, $id, $datos, $neo4j, &$neo4jSynced): void {
                DB::table($config['tabla'])->where($config['llave'], $id)->update($datos);

                if ($this->esSincronizable($tipo)) {
                    $this->sincronizarNeo4j($tipo, $this->modelo($tipo, $id), $neo4j);
                    $neo4jSynced = true;
                }
            });
        } catch (Throwable $exception) {
            if ($neo4jSynced && $anterior !== null) {
                try {
                    $this->sincronizarNeo4j($tipo, $anterior, $neo4j);
                } catch (Throwable $compensationException) {
                    report($compensationException);
                }
            }

            throw $exception;
        }

        return back()->with('exito', ucfirst($config['singular'])." \"{$datos['nombre']}\" actualizado.");
    }

    public function destroy(Request $request, SincronizarNeo4jService $neo4j): RedirectResponse
    {
        [$tipo, $config] = $this->catalogo($request);
        $id = (int) $request->route('id');

        $fila = DB::table($config['tabla'])
            ->select('nombre')
            ->selectRaw("{$config['usos']} AS usos")
            ->where($config['llave'], $id)
            ->first();

        abort_if($fila === null, 404);

        if ((int) $fila->usos > 0) {
            return back()->with('error', "No se puede eliminar \"{$fila->nombre}\" porque tiene {$config['usos_texto']} asociados.");
        }

        $anterior = $this->esSincronizable($tipo) ? $this->modelo($tipo, $id) : null;
        $neo4jSynced = false;

        try {
            DB::transaction(function () use ($tipo, $config, $id, $neo4j, &$neo4jSynced): void {
                DB::table($config['tabla'])->where($config['llave'], $id)->delete();

                if ($this->esSincronizable($tipo)) {
                    $this->eliminarNeo4j($tipo, $id, $neo4j);
                    $neo4jSynced = true;
                }
            });
        } catch (Throwable $exception) {
            if ($neo4jSynced && $anterior !== null) {
                try {
                    $this->sincronizarNeo4j($tipo, $anterior, $neo4j);
                } catch (Throwable $compensationException) {
                    report($compensationException);
                }
            }

            throw $exception;
        }

        return back()->with('exito', ucfirst($config['singular'])." \"{$fila->nombre}\" eliminado.");
    }

    /**
     * El tipo viene de la ruta (autores, editoriales, categorias o proveedores).
     */
    private function catalogo(Request $request): array
    {
        $tipo = (string) $request->route('tipo');
        abort_unless(isset(self::CATALOGOS[$tipo]), 404);

        return [$tipo, self::CATALOGOS[$tipo]];
    }

    private function reglas(array $config, ?int $id = null): array
    {
        return collect($config['campos'])->mapWithKeys(function (array $campo) use ($config, $id): array {
            $reglas = $campo['reglas'];

            if ($campo['unico']) {
                $reglas[] = Rule::unique($config['tabla'], $campo['campo'])->ignore($id, $config['llave']);
            }

            return [$campo['campo'] => $reglas];
        })->all();
    }

    private function mensajes(): array
    {
        return [
            'nombre.unique' => 'Ya existe un registro con ese nombre.',
            'telefono.regex' => 'El teléfono solo puede tener números, espacios, + y -.',
        ];
    }

    private function esSincronizable(string $tipo): bool
    {
        return in_array($tipo, ['autores', 'editoriales', 'categorias'], true);
    }

    private function modelo(string $tipo, int $id): Autor|Editorial|Categoria
    {
        return match ($tipo) {
            'autores' => Autor::query()->findOrFail($id),
            'editoriales' => Editorial::query()->findOrFail($id),
            'categorias' => Categoria::query()->findOrFail($id),
        };
    }

    private function sincronizarNeo4j(
        string $tipo,
        Autor|Editorial|Categoria $modelo,
        SincronizarNeo4jService $neo4j,
    ): void {
        match ($tipo) {
            'autores' => $neo4j->autor($modelo),
            'editoriales' => $neo4j->editorial($modelo),
            'categorias' => $neo4j->categoria($modelo),
        };
    }

    private function eliminarNeo4j(string $tipo, int $id, SincronizarNeo4jService $neo4j): void
    {
        match ($tipo) {
            'autores' => $neo4j->eliminarAutor($id),
            'editoriales' => $neo4j->eliminarEditorial($id),
            'categorias' => $neo4j->eliminarCategoria($id),
        };
    }
}
