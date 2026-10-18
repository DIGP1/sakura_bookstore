<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarDatosClienteRequest;
use App\Http\Requests\ConfirmarCompraRequest;
use App\Http\Requests\SolicitarDevolucionRequest;
use App\Models\Departamento;
use App\Models\Devolucion;
use App\Models\MotivoDevolucion;
use App\Models\TipoEnvio;
use App\Models\TipoReembolso;
use App\Models\Venta;
use App\Services\CarritoService;
use App\Services\CheckoutService;
use App\Services\DevolucionService;
use App\Services\SincronizarNeo4jService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ClienteController extends Controller
{
    /**
     * Checkout: forma de entrega, datos de envío y resumen final.
     */
    public function checkout(Request $request, CarritoService $carrito): Response|RedirectResponse
    {
        $resumen = $carrito->resumen();

        if ($resumen['lineas']->isEmpty()) {
            return to_route('carrito.index')->with('error', 'Tu carrito está vacío.');
        }

        $cliente = $request->user()->cliente?->load('distrito.municipio');

        return Inertia::render('cliente/Checkout', [
            'resumen' => $resumen,
            'cliente' => [
                'nombre' => $cliente?->nombre ?? $request->user()->name,
                'telefono' => $cliente?->telefono,
                'direccion' => $cliente?->direccion,
                'id_departamento' => $cliente?->distrito?->municipio?->id_departamento,
                'id_municipio' => $cliente?->distrito?->id_municipio,
                'id_distrito' => $cliente?->id_distrito,
            ],
            'departamentos' => Departamento::orderBy('nombre')->get(['id_departamento', 'nombre']),
            'tiposEnvio' => TipoEnvio::where('estado', true)->orderBy('costo')->get()
                ->map(fn (TipoEnvio $tipo) => [
                    'id_tipo_envio' => $tipo->id_tipo_envio,
                    'nombre' => $tipo->nombre,
                    'descripcion' => $tipo->descripcion,
                    'dias_estimados' => $tipo->dias_estimados,
                    'costo' => (float) $tipo->costo,
                ]),
        ]);
    }

    /**
     * Confirma la compra con pago simulado. Si algo falla no se guarda nada.
     */
    public function confirmar(ConfirmarCompraRequest $request, CheckoutService $checkout): RedirectResponse
    {
        $cliente = $request->user()->cliente;

        if ($cliente === null) {
            return to_route('carrito.index')->with('error', 'Tu usuario no tiene un perfil de cliente.');
        }

        try {
            $venta = $checkout->confirmar($cliente, $request->validated());
        } catch (ValidationException $e) {
            return to_route('carrito.index')
                ->with('error', 'No se pudo completar la compra: '.collect($e->errors())->flatten()->first());
        } catch (Throwable $e) {
            report($e);

            return to_route('carrito.index')
                ->with('error', 'No se pudo completar la compra. No se realizó ningún cobro, intentá de nuevo.');
        }

        return to_route('checkout.confirmacion', $venta);
    }

    /**
     * Pantalla de "compra realizada" con el número de pedido.
     */
    public function confirmacion(Request $request, Venta $venta): Response
    {
        abort_unless((int) $venta->id_cliente === (int) $request->user()->cliente?->id_cliente, 403);

        $venta->load(['detalles.producto', 'envio.tipoEnvio', 'envio.distrito.municipio.departamento', 'oferta']);
        $envio = $venta->envio;
        $costoEnvio = $envio ? (float) $envio->costo_envio : 0.0;

        return Inertia::render('cliente/Confirmacion', [
            'venta' => [
                'numero' => str_pad((string) $venta->id_venta, 6, '0', STR_PAD_LEFT),
                'fecha' => $venta->fecha?->format('d/m/Y H:i'),
                'lineas' => $venta->detalles->map(fn ($d) => [
                    'nombre' => $d->producto?->nombre,
                    'cantidad' => (int) $d->cantidad,
                    'precio_unitario' => (float) $d->precio_unitario,
                    'descuento_aplicado' => (float) $d->descuento_aplicado,
                    'subtotal' => (float) $d->subtotal,
                ]),
                'subtotal' => (float) $venta->subtotal_general,
                'descuento_global' => (float) $venta->descuento_global,
                'oferta_global' => $venta->oferta?->nombre_oferta,
                'total' => (float) $venta->total,
                'costo_envio' => $costoEnvio,
                'total_a_pagar' => round((float) $venta->total + $costoEnvio, 2),
                'envio' => $envio ? [
                    'tipo' => $envio->tipoEnvio?->nombre,
                    'direccion' => $envio->direccion_entrega,
                    'ubicacion' => collect([
                        $envio->distrito?->nombre,
                        $envio->distrito?->municipio?->nombre,
                        $envio->distrito?->municipio?->departamento?->nombre,
                    ])->filter()->join(', '),
                    'receptor' => $envio->nombre_receptor,
                    'telefono' => $envio->telefono_receptor,
                    'fecha_estimada' => $envio->fecha_estimada?->format('d/m/Y'),
                ] : null,
            ],
        ]);
    }

    /**
     * Mis pedidos: compras del cliente con el estado de la venta y del envío.
     */
    public function orders(Request $request): Response
    {
        $idCliente = $request->user()->cliente?->id_cliente ?? 0;

        $pedidos = Venta::query()
            ->where('id_cliente', $idCliente)
            ->with(['estado', 'envio.estado'])
            ->withSum('detalles', 'cantidad')
            ->orderByDesc('fecha')
            ->paginate(10)
            ->through(fn (Venta $venta) => [
                'id_venta' => $venta->id_venta,
                'numero' => str_pad((string) $venta->id_venta, 6, '0', STR_PAD_LEFT),
                'fecha' => $venta->fecha?->format('d/m/Y'),
                'libros' => (int) $venta->detalles_sum_cantidad,
                'total' => round((float) $venta->total + (float) ($venta->envio?->costo_envio ?? 0), 2),
                'estado' => $venta->estado?->nombre,
                'entrega' => $venta->envio ? 'domicilio' : 'tienda',
                'estado_envio' => $venta->envio?->estado?->nombre,
            ]);

        return Inertia::render('cliente/Pedidos', [
            'pedidos' => $pedidos,
        ]);
    }

    /**
     * Detalle de un pedido con el seguimiento del envío. Solo lo ve su dueño.
     */
    public function order(Venta $venta, DevolucionService $devoluciones): Response
    {
        Gate::authorize('view', $venta);

        $venta->load([
            'estado',
            'oferta',
            'detalles.producto',
            'envio.tipoEnvio',
            'envio.proveedor',
            'envio.estado',
            'envio.distrito.municipio.departamento',
            'envio.historial' => fn ($consulta) => $consulta->orderBy('fecha')->orderBy('id_historial'),
            'envio.historial.estado',
        ]);

        $envio = $venta->envio;
        $costoEnvio = $envio ? (float) $envio->costo_envio : 0.0;

        return Inertia::render('cliente/PedidoDetalle', [
            'pedido' => [
                'id_venta' => $venta->id_venta,
                'numero' => str_pad((string) $venta->id_venta, 6, '0', STR_PAD_LEFT),
                'fecha' => $venta->fecha?->format('d/m/Y H:i'),
                'estado' => $venta->estado?->nombre,
                'puede_devolver' => $devoluciones->motivoDeRechazo($venta) === null,
                'lineas' => $venta->detalles->map(fn ($d) => [
                    'id_producto' => $d->id_producto,
                    'nombre' => $d->producto?->nombre,
                    'cantidad' => (int) $d->cantidad,
                    'precio_unitario' => (float) $d->precio_unitario,
                    'descuento_aplicado' => (float) $d->descuento_aplicado,
                    'subtotal' => (float) $d->subtotal,
                ]),
                'subtotal' => (float) $venta->subtotal_general,
                'descuento_global' => (float) $venta->descuento_global,
                'oferta_global' => $venta->oferta?->nombre_oferta,
                'costo_envio' => $costoEnvio,
                'total_a_pagar' => round((float) $venta->total + $costoEnvio, 2),
                'envio' => $envio ? [
                    'estado' => $envio->estado?->nombre,
                    'tipo' => $envio->tipoEnvio?->nombre,
                    'proveedor' => $envio->proveedor?->nombre,
                    'direccion' => $envio->direccion_entrega,
                    'ubicacion' => collect([
                        $envio->distrito?->nombre,
                        $envio->distrito?->municipio?->nombre,
                        $envio->distrito?->municipio?->departamento?->nombre,
                    ])->filter()->join(', '),
                    'receptor' => $envio->nombre_receptor,
                    'telefono' => $envio->telefono_receptor,
                    'fecha_estimada' => $envio->fecha_estimada?->format('d/m/Y'),
                    'fecha_entrega' => $envio->fecha_entrega?->format('d/m/Y H:i'),
                    'historial' => $envio->historial->map(fn ($h) => [
                        'estado' => $h->estado?->nombre,
                        'fecha' => $h->fecha?->format('d/m/Y H:i'),
                        'observacion' => $h->observacion,
                    ]),
                ] : null,
            ],
        ]);
    }

    /**
     * Formulario para solicitar la devolución de una compra.
     */
    public function createReturn(Venta $venta, DevolucionService $devoluciones): Response|RedirectResponse
    {
        Gate::authorize('view', $venta);

        if ($motivo = $devoluciones->motivoDeRechazo($venta)) {
            return to_route('cliente.pedidos.show', $venta)->with('error', $motivo);
        }

        $formato = fn (string $nombre) => ucfirst(mb_strtolower(str_replace('_', ' ', $nombre)));

        return Inertia::render('cliente/SolicitarDevolucion', [
            'pedido' => [
                'id_venta' => $venta->id_venta,
                'numero' => str_pad((string) $venta->id_venta, 6, '0', STR_PAD_LEFT),
                'fecha' => $venta->fecha?->format('d/m/Y'),
                'dias_restantes' => max(0, DevolucionService::DIAS_LIMITE - (int) $venta->fecha->diffInDays(now())),
            ],
            'lineas' => $devoluciones->lineasDevolvibles($venta),
            'motivos' => MotivoDevolucion::orderBy('nombre')->get()
                ->map(fn ($m) => ['id' => $m->id_motivo_devolucion, 'nombre' => $formato($m->nombre)]),
            'tiposReembolso' => TipoReembolso::orderBy('nombre')->get()
                ->map(fn ($t) => ['id' => $t->id_tipo_reembolso, 'nombre' => $formato($t->nombre)]),
        ]);
    }

    /**
     * Guarda la solicitud de devolución. Si algo no cuadra, regresa al formulario con el error.
     */
    public function storeReturn(SolicitarDevolucionRequest $request, Venta $venta, DevolucionService $devoluciones): RedirectResponse
    {
        Gate::authorize('view', $venta);

        $devolucion = $devoluciones->solicitar($venta, $request->validated());

        return to_route('cliente.pedidos.show', $venta)
            ->with('exito', "Solicitud de devolución #{$devolucion->id_devolucion} enviada. La revisaremos pronto.");
    }

    /**
     * Mis devoluciones: solicitudes del cliente con su estado y el monto a reembolsar.
     */
    public function returns(Request $request): Response
    {
        $idCliente = $request->user()->cliente?->id_cliente ?? 0;
        $formato = fn (?string $nombre) => $nombre ? ucfirst(mb_strtolower(str_replace('_', ' ', $nombre))) : null;

        $devoluciones = Devolucion::query()
            ->whereHas('venta', fn ($venta) => $venta->where('id_cliente', $idCliente))
            ->with(['estado', 'motivo', 'tipoReembolso', 'detalles.detalleVenta.producto'])
            ->orderByDesc('fecha_devolucion')
            ->paginate(10)
            ->through(fn (Devolucion $devolucion) => [
                'id_devolucion' => $devolucion->id_devolucion,
                'id_venta' => $devolucion->id_venta,
                'numero_pedido' => str_pad((string) $devolucion->id_venta, 6, '0', STR_PAD_LEFT),
                'fecha' => $devolucion->fecha_devolucion?->format('d/m/Y'),
                'estado' => $devolucion->estado?->nombre,
                'motivo' => $formato($devolucion->motivo?->nombre),
                'tipo_reembolso' => $formato($devolucion->tipoReembolso?->nombre),
                'libros' => $devolucion->detalles->map(fn ($detalle) => [
                    'nombre' => $detalle->detalleVenta?->producto?->nombre,
                    'cantidad' => (int) $detalle->cantidad,
                ]),
                'reembolso' => round($devolucion->detalles->sum(fn ($detalle) => (float) $detalle->monto_reembolso), 2),
            ]);

        return Inertia::render('cliente/Devoluciones', [
            'devoluciones' => $devoluciones,
        ]);
    }

    /**
     * Mis datos: nombre, teléfono y dirección del cliente.
     */
    public function editProfile(Request $request): Response|RedirectResponse
    {
        $cliente = $request->user()->cliente?->load('distrito.municipio');

        if ($cliente === null) {
            return to_route('home')->with('error', 'Tu usuario no tiene un perfil de cliente.');
        }

        return Inertia::render('cliente/MisDatos', [
            'cliente' => [
                'nombre' => $cliente->nombre,
                'email' => $cliente->email ?? $request->user()->email,
                'telefono' => $cliente->telefono,
                'direccion' => $cliente->direccion,
                'id_departamento' => $cliente->distrito?->municipio?->id_departamento,
                'id_municipio' => $cliente->distrito?->id_municipio,
                'id_distrito' => $cliente->id_distrito,
            ],
            'departamentos' => Departamento::orderBy('nombre')->get(['id_departamento', 'nombre']),
        ]);
    }

    /**
     * Guarda los datos del cliente y mantiene el nombre igual en su usuario.
     */
    public function updateProfile(
        ActualizarDatosClienteRequest $request,
        SincronizarNeo4jService $neo4j,
    ): RedirectResponse {
        $cliente = $request->user()->cliente;

        if ($cliente === null) {
            return to_route('home')->with('error', 'Tu usuario no tiene un perfil de cliente.');
        }

        $datos = $request->validated();
        $clienteAnterior = clone $cliente;
        $neo4jSynced = false;

        try {
            DB::transaction(function () use ($cliente, $datos, $request, $neo4j, &$neo4jSynced): void {
                $cliente->update([
                    'nombre' => $datos['nombre'],
                    'telefono' => $datos['telefono'] ?? null,
                    'id_distrito' => $datos['id_distrito'],
                    'direccion' => $datos['direccion'] ?? null,
                ]);

                $request->user()->update(['name' => $datos['nombre']]);
                $neo4j->cliente($cliente->fresh());
                $neo4jSynced = true;
            });
        } catch (Throwable $exception) {
            if ($neo4jSynced) {
                try {
                    $neo4j->cliente($clienteAnterior);
                } catch (Throwable $compensationException) {
                    report($compensationException);
                }
            }

            throw $exception;
        }

        return to_route('cliente.datos')->with('exito', 'Tus datos se guardaron.');
    }
}
