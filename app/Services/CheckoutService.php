<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Envio;
use App\Models\EstadoEnvio;
use App\Models\EstadoVenta;
use App\Models\HistorialEnvio;
use App\Models\ProveedorEnvio;
use App\Models\TipoEnvio;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CheckoutService
{
    public function __construct(
        private CarritoService $carrito,
        private InventarioService $inventario,
    ) {}

    /**
     * Registra la compra del carrito con pago simulado.
     * Venta, detalle, kardex, stock y envío se guardan en una sola transacción:
     * si algo falla (por ejemplo, falta de stock) no se guarda nada.
     *
     * @param  array<string, mixed>  $datos  datos validados por ConfirmarCompraRequest
     */
    public function confirmar(Cliente $cliente, array $datos): Venta
    {
        $resumen = $this->carrito->resumen();

        if ($resumen['lineas']->isEmpty()) {
            throw ValidationException::withMessages(['carrito' => 'Tu carrito está vacío.']);
        }

        $venta = DB::transaction(function () use ($cliente, $datos, $resumen): Venta {
            // La venta, su detalle, el stock y el kardex los registra el InventarioService.
            $venta = $this->inventario->createSale(
                [
                    'id_cliente' => $cliente->id_cliente,
                    'id_estado_ventas' => $this->idEstado(EstadoVenta::class, 'id_estado_ventas', 'COMPLETADA'),
                ],
                $resumen['lineas']->map(fn (array $linea) => [
                    'id_producto' => $linea['id_producto'],
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio'],
                    'descuento_aplicado' => $linea['descuento'],
                    'id_oferta' => $linea['id_oferta'],
                ])->all(),
            );

            // Descuento sobre el total de la compra (oferta global con monto mínimo).
            if ($resumen['descuento_global'] > 0) {
                $venta->id_oferta = $resumen['id_oferta_global'];
                $venta->descuento_global = $resumen['descuento_global'];
                $venta->total = round((float) $venta->subtotal_general - $resumen['descuento_global'], 2);
                $venta->save();
            }

            if ($datos['entrega'] === 'domicilio') {
                $this->registrarEnvio($venta, $datos);
            }

            return $venta;
        });

        $this->carrito->vaciar();

        return $venta;
    }

    private function registrarEnvio(Venta $venta, array $datos): void
    {
        $tipo = TipoEnvio::query()->where('estado', true)->findOrFail($datos['id_tipo_envio']);
        $proveedor = ProveedorEnvio::query()->where('activo', true)->orderBy('id_proveedor_envio')->first();

        if ($proveedor === null) {
            throw new RuntimeException('No hay proveedores de envío activos.');
        }

        $pendiente = $this->idEstado(EstadoEnvio::class, 'id_estado_envio', 'PENDIENTE');

        $envio = Envio::create([
            'id_venta' => $venta->id_venta,
            'id_proveedor_envio' => $proveedor->id_proveedor_envio,
            'id_tipo_envio' => $tipo->id_tipo_envio,
            'id_estado_envio' => $pendiente,
            'id_distrito' => $datos['id_distrito'],
            'direccion_entrega' => $datos['direccion_entrega'],
            'nombre_receptor' => $datos['nombre_receptor'],
            'telefono_receptor' => $datos['telefono_receptor'],
            'costo_envio' => $tipo->costo,
            'fecha_estimada' => today()->addDays((int) $tipo->dias_estimados)->toDateString(),
        ]);

        HistorialEnvio::create([
            'id_envio' => $envio->id_envio,
            'id_estado_envio' => $pendiente,
            'observacion' => 'Pedido registrado en la tienda en línea',
        ]);
    }

    /**
     * @param  class-string  $modelo
     */
    private function idEstado(string $modelo, string $columna, string $nombre): int
    {
        $id = $modelo::query()->where('nombre', $nombre)->value($columna);

        if ($id === null) {
            throw new RuntimeException("No existe el estado {$nombre}.");
        }

        return (int) $id;
    }
}