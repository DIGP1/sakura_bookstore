<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Devolucion;
use App\Models\Venta;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use RuntimeException;

class SincronizarNeo4jService
{
    public function __construct(private readonly ClientInterface $neo4j) {}

    public function cliente(Cliente $cliente): void
    {
        $this->neo4j->writeTransaction(function (TransactionInterface $transaction) use ($cliente): void {
            $distrito = $transaction->run(
                'MATCH (d:Distrito {id_distrito: $id_distrito}) RETURN d.id_distrito',
                ['id_distrito' => (int) $cliente->id_distrito],
            );

            if ($distrito->count() === 0) {
                throw new RuntimeException('El distrito no existe en Neo4j.');
            }

            $transaction->run(
                'MERGE (c:Cliente {id_cliente: $id_cliente})
                 SET c.nombre = $nombre, c.email = $email, c.fecha_registro = date($fecha_registro)
                 WITH c
                 OPTIONAL MATCH (c)-[anterior:RESIDE_EN]->()
                 DELETE anterior
                 WITH c
                 MATCH (d:Distrito {id_distrito: $id_distrito})
                 MERGE (c)-[:RESIDE_EN]->(d)',
                [
                    'id_cliente' => (int) $cliente->id_cliente,
                    'nombre' => $cliente->nombre,
                    'email' => $cliente->email,
                    'fecha_registro' => $cliente->fecha_registro->toDateString(),
                    'id_distrito' => (int) $cliente->id_distrito,
                ],
            );
        });
    }

    public function venta(Venta $venta): void
    {
        $venta->loadMissing(['cliente', 'estado', 'detalles']);

        $this->neo4j->writeTransaction(function (TransactionInterface $transaction) use ($venta): void {
            $transaction->run(
                'MERGE (v:Venta {id_venta: $id_venta})
                 SET v.fecha = datetime($fecha), v.subtotal_general = $subtotal_general,
                     v.descuento_global = $descuento_global, v.total = $total, v.estado = $estado
                 WITH v
                 MATCH (c:Cliente {id_cliente: $id_cliente})
                 MERGE (c)-[:REALIZO]->(v)',
                [
                    'id_venta' => (int) $venta->id_venta,
                    'fecha' => $venta->fecha?->format('Y-m-d\TH:i:s'),
                    'subtotal_general' => (float) $venta->subtotal_general,
                    'descuento_global' => (float) $venta->descuento_global,
                    'total' => (float) $venta->total,
                    'estado' => $venta->estado?->nombre,
                    'id_cliente' => (int) $venta->id_cliente,
                ],
            );

            $transaction->run(
                'MATCH (v:Venta {id_venta: $id_venta})-[r:APLICO_OFERTA]->() DELETE r',
                ['id_venta' => (int) $venta->id_venta],
            );

            if ($venta->id_oferta !== null) {
                $transaction->run(
                    'MATCH (v:Venta {id_venta: $id_venta})
                     MATCH (o:Oferta {id_oferta: $id_oferta})
                     MERGE (v)-[:APLICO_OFERTA]->(o)',
                    ['id_venta' => (int) $venta->id_venta, 'id_oferta' => (int) $venta->id_oferta],
                );
            }

            foreach ($venta->detalles as $detalle) {
                $transaction->run(
                    'MATCH (v:Venta {id_venta: $id_venta})
                     MATCH (l:Libro {id_producto: $id_producto})
                     MERGE (v)-[r:INCLUYE_LIBRO {id_detalle_venta: $id_detalle_venta}]->(l)
                     SET r.cantidad = $cantidad, r.precio_unitario = $precio_unitario,
                         r.descuento_aplicado = $descuento_aplicado, r.subtotal = $subtotal,
                         r.id_oferta = $id_oferta',
                    [
                        'id_venta' => (int) $venta->id_venta,
                        'id_producto' => (int) $detalle->id_producto,
                        'id_detalle_venta' => (int) $detalle->id_detalle_venta,
                        'cantidad' => (int) $detalle->cantidad,
                        'precio_unitario' => (float) $detalle->precio_unitario,
                        'descuento_aplicado' => (float) $detalle->descuento_aplicado,
                        'subtotal' => (float) $detalle->subtotal,
                        'id_oferta' => $detalle->id_oferta === null ? null : (int) $detalle->id_oferta,
                    ],
                );
            }
        });
    }

    public function devolucion(Devolucion $devolucion): void
    {
        $devolucion->loadMissing(['estado', 'motivo', 'tipoReembolso', 'detalles.detalleVenta']);

        $this->neo4j->writeTransaction(function (TransactionInterface $transaction) use ($devolucion): void {
            $transaction->run(
                'MERGE (dev:Devolucion {id_devolucion: $id_devolucion})
                 SET dev.fecha_devolucion = datetime($fecha_devolucion),
                     dev.estado = $estado, dev.tipo_reembolso = $tipo_reembolso
                 WITH dev
                 MATCH (v:Venta {id_venta: $id_venta})
                 MATCH (mot:MotivoDevolucion {id_motivo_devolucion: $id_motivo_devolucion})
                 MERGE (v)-[:TIENE_DEVOLUCION]->(dev)
                 MERGE (dev)-[:POR_MOTIVO]->(mot)',
                [
                    'id_devolucion' => (int) $devolucion->id_devolucion,
                    'fecha_devolucion' => $devolucion->fecha_devolucion?->format('Y-m-d\TH:i:s'),
                    'estado' => $devolucion->estado?->nombre,
                    'tipo_reembolso' => $devolucion->tipoReembolso?->nombre,
                    'id_venta' => (int) $devolucion->id_venta,
                    'id_motivo_devolucion' => (int) $devolucion->id_motivo_devolucion,
                ],
            );

            foreach ($devolucion->detalles as $detalle) {
                $transaction->run(
                    'MATCH (dev:Devolucion {id_devolucion: $id_devolucion})
                     MATCH (l:Libro {id_producto: $id_producto})
                     MERGE (dev)-[r:DEVOLVIO_ITEM {id_detalle_devolucion: $id_detalle_devolucion}]->(l)
                     SET r.id_detalle_venta = $id_detalle_venta, r.cantidad = $cantidad,
                         r.monto_reembolso = $monto_reembolso, r.reingresa_stock = $reingresa_stock',
                    [
                        'id_devolucion' => (int) $devolucion->id_devolucion,
                        'id_producto' => (int) $detalle->detalleVenta->id_producto,
                        'id_detalle_devolucion' => (int) $detalle->id_detalle_devolucion,
                        'id_detalle_venta' => (int) $detalle->id_detalle_venta,
                        'cantidad' => (int) $detalle->cantidad,
                        'monto_reembolso' => (float) $detalle->monto_reembolso,
                        'reingresa_stock' => (bool) $detalle->reingresa_stock,
                    ],
                );
            }
        });
    }

    public function eliminarCliente(int $clienteId): void
    {
        $this->neo4j->writeTransaction(fn (TransactionInterface $transaction) => $transaction->run(
            'MATCH (c:Cliente {id_cliente: $id_cliente}) DETACH DELETE c',
            ['id_cliente' => $clienteId],
        ));
    }

    public function eliminarVenta(int $ventaId): void
    {
        $this->neo4j->writeTransaction(fn (TransactionInterface $transaction) => $transaction->run(
            'MATCH (v:Venta {id_venta: $id_venta}) DETACH DELETE v',
            ['id_venta' => $ventaId],
        ));
    }

    public function eliminarDevolucion(int $devolucionId): void
    {
        $this->neo4j->writeTransaction(fn (TransactionInterface $transaction) => $transaction->run(
            'MATCH (d:Devolucion {id_devolucion: $id_devolucion}) DETACH DELETE d',
            ['id_devolucion' => $devolucionId],
        ));
    }
}
