<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClientesController extends Controller
{
    /** Ventas completadas del cliente (las anuladas no cuentan). */
    private const COMPLETADAS = "FROM tbventas v JOIN tbestado_ventas e ON e.id_estado_ventas = v.id_estado_ventas
                                 WHERE v.id_cliente = tbclientes.id_cliente AND e.nombre = 'COMPLETADA'";

    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'departamento' => ['nullable', 'integer'],
            'orden' => ['nullable', 'in:recientes,compras,gasto'],
        ]);

        $clientes = Cliente::query()
            ->select('tbclientes.*')
            ->selectRaw('(SELECT COUNT(*) '.self::COMPLETADAS.') AS compras')
            ->selectRaw('(SELECT COALESCE(SUM(v.total), 0) '.self::COMPLETADAS.') AS gastado')
            ->selectRaw('(SELECT MAX(v.fecha) '.self::COMPLETADAS.') AS ultima_compra')
            ->with('distrito.municipio.departamento')
            ->when(trim($filtros['q'] ?? '') !== '', function ($q) use ($filtros) {
                $patron = '%'.mb_strtolower(trim($filtros['q'])).'%';
                $q->where(fn ($w) => $w
                    ->whereRaw('LOWER(nombre) LIKE ?', [$patron])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$patron])
                    ->orWhere('telefono', 'like', '%'.trim($filtros['q']).'%'));
            })
            ->when($filtros['departamento'] ?? null, fn ($q, $id) => $q->whereHas(
                'distrito.municipio', fn ($m) => $m->where('id_departamento', $id)
            ))
            ->when(($filtros['orden'] ?? 'recientes') === 'compras', fn ($q) => $q->orderByDesc('compras'))
            ->when(($filtros['orden'] ?? 'recientes') === 'gasto', fn ($q) => $q->orderByDesc('gastado'))
            ->when(($filtros['orden'] ?? 'recientes') === 'recientes', fn ($q) => $q->orderByDesc('fecha_registro')->orderByDesc('id_cliente'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Cliente $c) => [
                'id_cliente' => $c->id_cliente,
                'nombre' => $c->nombre,
                'email' => $c->email,
                'telefono' => $c->telefono,
                'ubicacion' => collect([$c->distrito?->municipio?->nombre, $c->distrito?->municipio?->departamento?->nombre])->filter()->join(', '),
                'compras' => (int) $c->compras,
                'gastado' => round((float) $c->gastado, 2),
                'ultima_compra' => $c->ultima_compra ? Carbon::parse($c->ultima_compra)->format('d/m/Y') : null,
                'con_cuenta' => $c->id_user !== null,
            ]);

        return Inertia::render('admin/clientes/Index', [
            'clientes' => $clientes,
            'filtros' => [
                'q' => $filtros['q'] ?? '',
                'departamento' => $filtros['departamento'] ?? '',
                'orden' => $filtros['orden'] ?? 'recientes',
            ],
            'departamentos' => Departamento::orderBy('nombre')->get()
                ->map(fn ($d) => ['id' => $d->id_departamento, 'nombre' => $d->nombre]),
        ]);
    }

    public function show(Cliente $cliente): Response
    {
        $cliente->load('distrito.municipio.departamento');

        $estadisticas = DB::table('tbventas as v')
            ->join('tbestado_ventas as e', 'e.id_estado_ventas', '=', 'v.id_estado_ventas')
            ->where('v.id_cliente', $cliente->id_cliente)
            ->where('e.nombre', 'COMPLETADA')
            ->selectRaw('COUNT(*) AS compras, COALESCE(SUM(v.total), 0) AS gastado, MIN(v.fecha) AS primera, MAX(v.fecha) AS ultima')
            ->first();

        $devoluciones = DB::table('tbdevoluciones as d')
            ->join('tbventas as v', 'v.id_venta', '=', 'd.id_venta')
            ->where('v.id_cliente', $cliente->id_cliente)
            ->count();

        $compras = (int) $estadisticas->compras;

        $ventas = Venta::query()
            ->where('id_cliente', $cliente->id_cliente)
            ->with(['estado', 'envio'])
            ->withSum('detalles', 'cantidad')
            ->orderByDesc('fecha')
            ->paginate(10)
            ->through(fn (Venta $venta) => [
                'id_venta' => $venta->id_venta,
                'numero' => str_pad((string) $venta->id_venta, 6, '0', STR_PAD_LEFT),
                'fecha' => $venta->fecha?->format('d/m/Y'),
                'libros' => (int) $venta->detalles_sum_cantidad,
                'total' => (float) $venta->total,
                'entrega' => $venta->envio ? 'Domicilio' : 'Tienda',
                'estado' => $venta->estado?->nombre,
            ]);

        return Inertia::render('admin/clientes/Show', [
            'cliente' => [
                'id_cliente' => $cliente->id_cliente,
                'nombre' => $cliente->nombre,
                'email' => $cliente->email,
                'telefono' => $cliente->telefono,
                'direccion' => $cliente->direccion,
                'ubicacion' => collect([
                    $cliente->distrito?->nombre,
                    $cliente->distrito?->municipio?->nombre,
                    $cliente->distrito?->municipio?->departamento?->nombre,
                ])->filter()->join(', '),
                'fecha_registro' => $cliente->fecha_registro ? Carbon::parse($cliente->fecha_registro)->format('d/m/Y') : null,
                'con_cuenta' => $cliente->id_user !== null,
            ],
            'estadisticas' => [
                'compras' => $compras,
                'gastado' => round((float) $estadisticas->gastado, 2),
                'ticket_promedio' => $compras > 0 ? round((float) $estadisticas->gastado / $compras, 2) : 0,
                'primera' => $estadisticas->primera ? Carbon::parse($estadisticas->primera)->format('d/m/Y') : null,
                'ultima' => $estadisticas->ultima ? Carbon::parse($estadisticas->ultima)->format('d/m/Y') : null,
                'devoluciones' => $devoluciones,
            ],
            'ventas' => $ventas,
        ]);
    }
}