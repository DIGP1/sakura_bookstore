<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmarCompraRequest;
use App\Models\Departamento;
use App\Models\TipoEnvio;
use App\Models\Venta;
use App\Services\CarritoService;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function returns(): Response
    {
        return Inertia::render('cliente/Devoluciones');
    }
}