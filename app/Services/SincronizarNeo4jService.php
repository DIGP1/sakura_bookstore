<?php

namespace App\Services;

use App\Models\Autor;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Devolucion;
use App\Models\Editorial;
use App\Models\Oferta;
use App\Models\Producto;
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

    public function producto(Producto $producto): void
    {
        $producto->load(['editorial', 'autores', 'categorias']);

        $this->neo4j->writeTransaction(function (TransactionInterface $transaction) use ($producto): void {
            $transaction->run(
                'MERGE (l:Libro {id_producto: $id_producto})
                 SET l.isbn = $isbn, l.nombre = $nombre, l.anio_publicacion = $anio_publicacion,
                     l.idioma = $idioma, l.formato = $formato, l.precio_unitario = $precio_unitario,
                     l.stock = $stock, l.stock_minimo = $stock_minimo, l.costo_promedio = $costo_promedio
                 WITH l
                 OPTIONAL MATCH (l)-[rel:PUBLICADO_POR]->()
                 DELETE rel
                 WITH l
                 MATCH (e:Editorial {id_editorial: $id_editorial})
                 MERGE (l)-[:PUBLICADO_POR]->(e)',
                [
                    'id_producto' => (int) $producto->id_producto,
                    'isbn' => $producto->isbn,
                    'nombre' => $producto->nombre,
                    'anio_publicacion' => $producto->anio_publicacion,
                    'idioma' => $producto->idioma,
                    'formato' => $producto->formato,
                    'precio_unitario' => (float) $producto->precio_unitario,
                    'stock' => (int) $producto->stock,
                    'stock_minimo' => (int) $producto->stock_minimo,
                    'costo_promedio' => (float) $producto->costo_promedio,
                    'id_editorial' => (int) $producto->id_editorial,
                ],
            );

            $transaction->run(
                'MATCH (l:Libro {id_producto: $id_producto})-[rel:ESCRITO_POR]->()
                 DELETE rel',
                ['id_producto' => (int) $producto->id_producto],
            );

            foreach ($producto->autores as $autor) {
                $transaction->run(
                    'MATCH (l:Libro {id_producto: $id_producto})
                     MATCH (a:Autor {id_autor: $id_autor})
                     MERGE (l)-[:ESCRITO_POR {rol: $rol}]->(a)',
                    [
                        'id_producto' => (int) $producto->id_producto,
                        'id_autor' => (int) $autor->id_autor,
                        'rol' => $autor->pivot->rol,
                    ],
                );

            }

            $transaction->run(
                'MATCH (l:Libro {id_producto: $id_producto})-[rel:PERTENECE_A]->()
                 DELETE rel',
                ['id_producto' => (int) $producto->id_producto],
            );
            foreach ($producto->categorias as $categoria) {
                $transaction->run(
                    'MATCH (l:Libro {id_producto: $id_producto})
                     MATCH (cat:Categoria {id_categoria: $id_categoria})
                     MERGE (l)-[:PERTENECE_A]->(cat)',
                    [
                        'id_producto' => (int) $producto->id_producto,
                        'id_categoria' => (int) $categoria->id_categoria,
                    ],
                );
            }
        });
    }

    public function autor(Autor $autor): void
    {
        $this->neo4j->writeTransaction(fn (TransactionInterface $transaction) => $transaction->run(
            'MERGE (a:Autor {id_autor: $id_autor})
             SET a.nombre = $nombre, a.nacionalidad = $nacionalidad',
            [
                'id_autor' => (int) $autor->id_autor,
                'nombre' => $autor->nombre,
                'nacionalidad' => $autor->nacionalidad,
            ],
        ));
    }

    public function editorial(Editorial $editorial): void
    {
        $this->neo4j->writeTransaction(fn (TransactionInterface $transaction) => $transaction->run(
            'MERGE (e:Editorial {id_editorial: $id_editorial})
             SET e.nombre = $nombre, e.pais = $pais',
            [
                'id_editorial' => (int) $editorial->id_editorial,
                'nombre' => $editorial->nombre,
                'pais' => $editorial->pais,
            ],
        ));
    }

    public function categoria(Categoria $categoria): void
    {
        $this->neo4j->writeTransaction(fn (TransactionInterface $transaction) => $transaction->run(
            'MERGE (cat:Categoria {id_categoria: $id_categoria})
             SET cat.nombre = $nombre',
            [
                'id_categoria' => (int) $categoria->id_categoria,
                'nombre' => $categoria->nombre,
            ],
        ));
    }

    public function oferta(Oferta $oferta): void
    {
        $oferta->load(['tipoDescuento', 'productos', 'categorias']);

        $this->neo4j->writeTransaction(function (TransactionInterface $transaction) use ($oferta): void {
            $transaction->run(
                'MERGE (o:Oferta {id_oferta: $id_oferta})
                 SET o.nombre_oferta = $nombre_oferta, o.tipo_descuento = $tipo_descuento,
                     o.valor_descuento = $valor_descuento, o.fecha_inicio = date($fecha_inicio),
                     o.fecha_fin = date($fecha_fin), o.estado = $estado, o.monto_minimo = $monto_minimo',
                [
                    'id_oferta' => (int) $oferta->id_oferta,
                    'nombre_oferta' => $oferta->nombre_oferta,
                    'tipo_descuento' => $oferta->tipoDescuento?->nombre,
                    'valor_descuento' => (float) $oferta->valor_descuento,
                    'fecha_inicio' => $oferta->fecha_inicio?->toDateString(),
                    'fecha_fin' => $oferta->fecha_fin?->toDateString(),
                    'estado' => (bool) $oferta->estado,
                    'monto_minimo' => $oferta->monto_minimo === null ? null : (float) $oferta->monto_minimo,
                ],
            );

            $transaction->run(
                'MATCH (o:Oferta {id_oferta: $id_oferta})-[rel:APLICA_A_PRODUCTO|APLICA_A_CATEGORIA]->()
                 DELETE rel',
                ['id_oferta' => (int) $oferta->id_oferta],
            );

            foreach ($oferta->productos as $producto) {
                $transaction->run(
                    'MATCH (o:Oferta {id_oferta: $id_oferta})
                     MATCH (l:Libro {id_producto: $id_producto})
                     MERGE (o)-[:APLICA_A_PRODUCTO]->(l)',
                    [
                        'id_oferta' => (int) $oferta->id_oferta,
                        'id_producto' => (int) $producto->id_producto,
                    ],
                );
            }

            foreach ($oferta->categorias as $categoria) {
                $transaction->run(
                    'MATCH (o:Oferta {id_oferta: $id_oferta})
                     MATCH (cat:Categoria {id_categoria: $id_categoria})
                     MERGE (o)-[:APLICA_A_CATEGORIA]->(cat)',
                    [
                        'id_oferta' => (int) $oferta->id_oferta,
                        'id_categoria' => (int) $categoria->id_categoria,
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

    public function eliminarProducto(int $productoId): void
    {
        $this->neo4j->writeTransaction(fn (TransactionInterface $transaction) => $transaction->run(
            'MATCH (l:Libro {id_producto: $id_producto}) DETACH DELETE l',
            ['id_producto' => $productoId],
        ));
    }

    public function eliminarAutor(int $autorId): void
    {
        $this->neo4j->writeTransaction(fn (TransactionInterface $transaction) => $transaction->run(
            'MATCH (a:Autor {id_autor: $id_autor}) DETACH DELETE a',
            ['id_autor' => $autorId],
        ));
    }

    public function eliminarEditorial(int $editorialId): void
    {
        $this->neo4j->writeTransaction(fn (TransactionInterface $transaction) => $transaction->run(
            'MATCH (e:Editorial {id_editorial: $id_editorial}) DETACH DELETE e',
            ['id_editorial' => $editorialId],
        ));
    }

    public function eliminarCategoria(int $categoriaId): void
    {
        $this->neo4j->writeTransaction(fn (TransactionInterface $transaction) => $transaction->run(
            'MATCH (cat:Categoria {id_categoria: $id_categoria}) DETACH DELETE cat',
            ['id_categoria' => $categoriaId],
        ));
    }

    public function eliminarOferta(int $ofertaId): void
    {
        $this->neo4j->writeTransaction(fn (TransactionInterface $transaction) => $transaction->run(
            'MATCH (o:Oferta {id_oferta: $id_oferta}) DETACH DELETE o',
            ['id_oferta' => $ofertaId],
        ));
    }
}
