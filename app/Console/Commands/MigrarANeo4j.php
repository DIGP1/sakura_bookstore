<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;

class MigrarANeo4j extends Command
{
    /**
     * Nombre del comando para ejecutar en consola.
     */
    protected $signature = 'app:migrar-neo4j {--chunk=1000 : Cantidad de registros por lote}';

    /**
     * Descripción del comando.
     */
    protected $description = 'Migra los datos transaccionales de PostgreSQL a nodos y relaciones en Neo4j por lotes (UNWIND)';

    private ClientInterface $neo4j;

    private int $chunkSize;

    public function handle(): int
    {
        // Evitar que el registro de consultas de Laravel consuma memoria RAM en migraciones masivas
        DB::disableQueryLog();
        ini_set('memory_limit', '512M');

        $this->chunkSize = (int) $this->option('chunk');

        // Inicializar conexión con Neo4j usando los valores definidos en la configuración.
        $neo4jConfig = config('database.connections.neo4j');
        $uri = sprintf(
            '%s://%s:%s',
            $neo4jConfig['protocol'],
            $neo4jConfig['host'],
            $neo4jConfig['port'],
        );
        $user = $neo4jConfig['username'];
        $password = $neo4jConfig['password'];

        $this->info("Conectando a Neo4j en {$uri}...");

        $this->neo4j = ClientBuilder::create()
            ->withDriver('bolt', $uri, Authenticate::basic($user, $password))
            ->withDefaultDriver('bolt')
            ->build();

        $startTime = microtime(true);

        // Orden lógico de ejecución
        $this->crearRestricciones();
        $this->migrarGeografia();
        $this->migrarCatalogosBase();
        $this->migrarProductosYRelaciones();
        $this->migrarClientes();
        $this->migrarVentas();
        $this->migrarDetalleVentas();
        $this->migrarDevoluciones();
        $this->migrarDetalleDevoluciones();

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->newLine();
        $this->info("¡Migración completada con éxito en {$elapsed} segundos!");

        return Command::SUCCESS;
    }

    /**
     * 1. Crear restricciones de unicidad (vital para que MERGE sea instantáneo con miles de datos)
     */
    private function crearRestricciones(): void
    {
        $this->info('1/9 Creando restricciones e índices en Neo4j...');

        $constraints = [
            'CREATE CONSTRAINT departamento_id IF NOT EXISTS FOR (d:Departamento) REQUIRE d.id_departamento IS UNIQUE',
            'CREATE CONSTRAINT municipio_id IF NOT EXISTS FOR (m:Municipio) REQUIRE m.id_municipio IS UNIQUE',
            'CREATE CONSTRAINT distrito_id IF NOT EXISTS FOR (dis:Distrito) REQUIRE dis.id_distrito IS UNIQUE',
            'CREATE CONSTRAINT cliente_id IF NOT EXISTS FOR (c:Cliente) REQUIRE c.id_cliente IS UNIQUE',
            'CREATE CONSTRAINT editorial_id IF NOT EXISTS FOR (e:Editorial) REQUIRE e.id_editorial IS UNIQUE',
            'CREATE CONSTRAINT autor_id IF NOT EXISTS FOR (a:Autor) REQUIRE a.id_autor IS UNIQUE',
            'CREATE CONSTRAINT categoria_id IF NOT EXISTS FOR (cat:Categoria) REQUIRE cat.id_categoria IS UNIQUE',
            'CREATE CONSTRAINT libro_id IF NOT EXISTS FOR (l:Libro) REQUIRE l.id_producto IS UNIQUE',
            'CREATE CONSTRAINT oferta_id IF NOT EXISTS FOR (o:Oferta) REQUIRE o.id_oferta IS UNIQUE',
            'CREATE CONSTRAINT venta_id IF NOT EXISTS FOR (v:Venta) REQUIRE v.id_venta IS UNIQUE',
            'CREATE CONSTRAINT devolucion_id IF NOT EXISTS FOR (dev:Devolucion) REQUIRE dev.id_devolucion IS UNIQUE',
            'CREATE CONSTRAINT motivo_id IF NOT EXISTS FOR (mot:MotivoDevolucion) REQUIRE mot.id_motivo_devolucion IS UNIQUE',
        ];

        foreach ($constraints as $cypher) {
            $this->neo4j->run($cypher);
        }
    }

    /**
     * 2. Migrar Departamentos, Municipios y Distritos
     */
    private function migrarGeografia(): void
    {
        $this->info('2/9 Migrando geografía (Departamentos, Municipios y Distritos)...');

        // Departamentos
        DB::table('tbdepartamentos')->orderBy('id_departamento')->chunk($this->chunkSize, function ($rows) {
            $batch = $rows->map(fn ($r) => [
                'id_departamento' => (int) $r->id_departamento,
                'nombre' => $r->nombre,
            ])->toArray();

            $this->neo4j->run('
                UNWIND $batch AS row
                MERGE (d:Departamento {id_departamento: row.id_departamento})
                SET d.nombre = row.nombre
            ', ['batch' => $batch]);
        });

        // Municipios -> Departamentos
        DB::table('tbmunicipios')->orderBy('id_municipio')->chunk($this->chunkSize, function ($rows) {
            $batch = $rows->map(fn ($r) => [
                'id_municipio' => (int) $r->id_municipio,
                'id_departamento' => (int) $r->id_departamento,
                'nombre' => $r->nombre,
            ])->toArray();

            $this->neo4j->run('
                UNWIND $batch AS row
                MERGE (m:Municipio {id_municipio: row.id_municipio})
                SET m.nombre = row.nombre
                WITH m, row
                MATCH (d:Departamento {id_departamento: row.id_departamento})
                MERGE (m)-[:PERTENECE_A]->(d)
            ', ['batch' => $batch]);
        });

        // Distritos -> Municipios
        DB::table('tbdistritos')->orderBy('id_distrito')->chunk($this->chunkSize, function ($rows) {
            $batch = $rows->map(fn ($r) => [
                'id_distrito' => (int) $r->id_distrito,
                'id_municipio' => (int) $r->id_municipio,
                'nombre' => $r->nombre,
            ])->toArray();

            $this->neo4j->run('
                UNWIND $batch AS row
                MERGE (dis:Distrito {id_distrito: row.id_distrito})
                SET dis.nombre = row.nombre
                WITH dis, row
                MATCH (m:Municipio {id_municipio: row.id_municipio})
                MERGE (dis)-[:PERTENECE_A]->(m)
            ', ['batch' => $batch]);
        });
    }

    /**
     * 3. Migrar Editoriales, Autores, Categorías, Ofertas y Motivos de Devolución
     */
    private function migrarCatalogosBase(): void
    {
        $this->info('3/9 Migrando catálogos (Editoriales, Autores, Categorías, Ofertas, Motivos)...');

        // Editoriales
        DB::table('tbeditoriales')->orderBy('id_editorial')->chunk($this->chunkSize, function ($rows) {
            $batch = $rows->map(fn ($r) => [
                'id_editorial' => (int) $r->id_editorial,
                'nombre' => $r->nombre,
                'pais' => $r->pais ?? 'Desconocido',
            ])->toArray();

            $this->neo4j->run('
                UNWIND $batch AS row
                MERGE (e:Editorial {id_editorial: row.id_editorial})
                SET e.nombre = row.nombre, e.pais = row.pais
            ', ['batch' => $batch]);
        });

        // Autores
        DB::table('tbautores')->orderBy('id_autor')->chunk($this->chunkSize, function ($rows) {
            $batch = $rows->map(fn ($r) => [
                'id_autor' => (int) $r->id_autor,
                'nombre' => $r->nombre,
                'nacionalidad' => $r->nacionalidad ?? 'Desconocida',
            ])->toArray();

            $this->neo4j->run('
                UNWIND $batch AS row
                MERGE (a:Autor {id_autor: row.id_autor})
                SET a.nombre = row.nombre, a.nacionalidad = row.nacionalidad
            ', ['batch' => $batch]);
        });

        // Categorías
        DB::table('tbcategoria')->orderBy('id_categoria')->chunk($this->chunkSize, function ($rows) {
            $batch = $rows->map(fn ($r) => [
                'id_categoria' => (int) $r->id_categoria,
                'nombre' => $r->nombre,
            ])->toArray();

            $this->neo4j->run('
                UNWIND $batch AS row
                MERGE (cat:Categoria {id_categoria: row.id_categoria})
                SET cat.nombre = row.nombre
            ', ['batch' => $batch]);
        });

        // Ofertas (desnormalizando tbtipo_descuento)
        DB::table('tboferta as o')
            ->join('tbtipo_descuento as td', 'o.id_tipo_descuento', '=', 'td.id_tipo_descuento')
            ->select('o.*', 'td.nombre as tipo_descuento')
            ->orderBy('o.id_oferta')
            ->chunk($this->chunkSize, function ($rows) {
                $batch = $rows->map(fn ($r) => [
                    'id_oferta' => (int) $r->id_oferta,
                    'nombre_oferta' => $r->nombre_oferta,
                    'tipo_descuento' => $r->tipo_descuento,
                    'valor_descuento' => (float) $r->valor_descuento,
                    'fecha_inicio' => $r->fecha_inicio,
                    'fecha_fin' => $r->fecha_fin,
                    'estado' => (bool) $r->estado,
                ])->toArray();

                $this->neo4j->run('
                    UNWIND $batch AS row
                    MERGE (o:Oferta {id_oferta: row.id_oferta})
                    SET o.nombre_oferta = row.nombre_oferta,
                        o.tipo_descuento = row.tipo_descuento,
                        o.valor_descuento = row.valor_descuento,
                        o.fecha_inicio = date(row.fecha_inicio),
                        o.fecha_fin = date(row.fecha_fin),
                        o.estado = row.estado
                ', ['batch' => $batch]);
            });

        // Motivos de devolución
        DB::table('tbmotivos_devolucion')->orderBy('id_motivo_devolucion')->chunk($this->chunkSize, function ($rows) {
            $batch = $rows->map(fn ($r) => [
                'id_motivo_devolucion' => (int) $r->id_motivo_devolucion,
                'nombre' => $r->nombre,
            ])->toArray();

            $this->neo4j->run('
                UNWIND $batch AS row
                MERGE (mot:MotivoDevolucion {id_motivo_devolucion: row.id_motivo_devolucion})
                SET mot.nombre = row.nombre
            ', ['batch' => $batch]);
        });
    }

    /**
     * 4. Migrar Libros (tbproductos) y sus tablas pivote (producto_autor, producto_categoria)
     */
    private function migrarProductosYRelaciones(): void
    {
        $this->info('4/9 Migrando Libros y sus vínculos con Autores, Editoriales y Categorías...');

        // Productos -> Editorial
        DB::table('tbproductos')->orderBy('id_producto')->chunk($this->chunkSize, function ($rows) {
            $batch = $rows->map(fn ($r) => [
                'id_producto' => (int) $r->id_producto,
                'isbn' => $r->isbn,
                'nombre' => $r->nombre,
                'id_editorial' => (int) $r->id_editorial,
                'anio_publicacion' => $r->anio_publicacion ? (int) $r->anio_publicacion : null,
                'idioma' => $r->idioma,
                'formato' => $r->formato,
                'precio_unitario' => (float) $r->precio_unitario,
                'costo_promedio' => (float) $r->costo_promedio,
            ])->toArray();

            $this->neo4j->run('
                UNWIND $batch AS row
                MERGE (l:Libro {id_producto: row.id_producto})
                SET l.isbn = row.isbn,
                    l.nombre = row.nombre,
                    l.anio_publicacion = row.anio_publicacion,
                    l.idioma = row.idioma,
                    l.formato = row.formato,
                    l.precio_unitario = row.precio_unitario,
                    l.costo_promedio = row.costo_promedio
                WITH l, row
                MATCH (e:Editorial {id_editorial: row.id_editorial})
                MERGE (l)-[:PUBLICADO_POR]->(e)
            ', ['batch' => $batch]);
        });

        // Relación Libro -> Autor
        DB::table('producto_autor')->orderBy('id_producto')->orderBy('id_autor')->chunk($this->chunkSize, function ($rows) {
            $batch = $rows->map(fn ($r) => [
                'id_producto' => (int) $r->id_producto,
                'id_autor' => (int) $r->id_autor,
                'rol' => $r->rol,
            ])->toArray();

            $this->neo4j->run('
                UNWIND $batch AS row
                MATCH (l:Libro {id_producto: row.id_producto})
                MATCH (a:Autor {id_autor: row.id_autor})
                MERGE (l)-[:ESCRITO_POR {rol: row.rol}]->(a)
            ', ['batch' => $batch]);
        });

        // Relación Libro -> Categoría
        DB::table('producto_categoria')->orderBy('id_producto')->orderBy('id_categoria')->chunk($this->chunkSize, function ($rows) {
            $batch = $rows->map(fn ($r) => [
                'id_producto' => (int) $r->id_producto,
                'id_categoria' => (int) $r->id_categoria,
            ])->toArray();

            $this->neo4j->run('
                UNWIND $batch AS row
                MATCH (l:Libro {id_producto: row.id_producto})
                MATCH (cat:Categoria {id_categoria: row.id_categoria})
                MERGE (l)-[:PERTENECE_A]->(cat)
            ', ['batch' => $batch]);
        });
    }

    /**
     * 5. Migrar Clientes
     */
    private function migrarClientes(): void
    {
        $this->info('5/9 Migrando Clientes...');

        DB::table('tbclientes')->orderBy('id_cliente')->chunk($this->chunkSize, function ($rows) {
            $batch = $rows->map(fn ($r) => [
                'id_cliente' => (int) $r->id_cliente,
                'nombre' => $r->nombre,
                'email' => $r->email,
                'id_distrito' => (int) $r->id_distrito,
                'fecha_registro' => $r->fecha_registro,
            ])->toArray();

            $this->neo4j->run('
                UNWIND $batch AS row
                MERGE (c:Cliente {id_cliente: row.id_cliente})
                SET c.nombre = row.nombre,
                    c.email = row.email,
                    c.fecha_registro = date(row.fecha_registro)
                WITH c, row
                MATCH (dis:Distrito {id_distrito: row.id_distrito})
                MERGE (c)-[:RESIDE_EN]->(dis)
            ', ['batch' => $batch]);
        });
    }

    /**
     * 6. Migrar Ventas (tbventas + tbestado_ventas)
     */
    private function migrarVentas(): void
    {
        $this->info('6/9 Migrando Ventas...');

        DB::table('tbventas as v')
            ->join('tbestado_ventas as ev', 'v.id_estado_ventas', '=', 'ev.id_estado_ventas')
            ->select('v.*', 'ev.nombre as estado_nombre')
            ->orderBy('v.id_venta')
            ->chunk($this->chunkSize, function ($rows) {
                $batch = $rows->map(fn ($r) => [
                    'id_venta' => (int) $r->id_venta,
                    'id_cliente' => (int) $r->id_cliente,
                    'id_oferta' => $r->id_oferta ? (int) $r->id_oferta : null,
                    // Convertir formato SQL "YYYY-MM-DD HH:MM:SS" a ISO-8601 "YYYY-MM-DDTHH:MM:SS"
                    'fecha' => str_replace(' ', 'T', $r->fecha),
                    'subtotal_general' => (float) $r->subtotal_general,
                    'descuento_global' => (float) $r->descuento_global,
                    'total' => (float) $r->total,
                    'estado' => strtoupper(trim($r->estado_nombre)),
                ])->toArray();

                $this->neo4j->run('
                    UNWIND $batch AS row
                    MERGE (v:Venta {id_venta: row.id_venta})
                    SET v.fecha = datetime(row.fecha),
                        v.subtotal_general = row.subtotal_general,
                        v.descuento_global = row.descuento_global,
                        v.total = row.total,
                        v.estado = row.estado
                    WITH v, row
                    MATCH (c:Cliente {id_cliente: row.id_cliente})
                    MERGE (c)-[:REALIZO]->(v)
                    WITH v, row
                    WHERE row.id_oferta IS NOT NULL
                    MATCH (o:Oferta {id_oferta: row.id_oferta})
                    MERGE (v)-[:APLICO_OFERTA]->(o)
                ', ['batch' => $batch]);
            });
    }

    /**
     * 7. Migrar Detalle de Ventas (tbdetalle_venta)
     */
    private function migrarDetalleVentas(): void
    {
        $this->info('7/9 Migrando Detalle de Ventas (relación INCLUYE_LIBRO)...');

        DB::table('tbdetalle_venta')->orderBy('id_detalle_venta')->chunk($this->chunkSize, function ($rows) {
            $batch = $rows->map(fn ($r) => [
                'id_detalle_venta' => (int) $r->id_detalle_venta,
                'id_venta' => (int) $r->id_venta,
                'id_producto' => (int) $r->id_producto,
                'id_oferta' => $r->id_oferta ? (int) $r->id_oferta : null,
                'cantidad' => (int) $r->cantidad,
                'precio_unitario' => (float) $r->precio_unitario,
                'descuento_aplicado' => (float) $r->descuento_aplicado,
                'subtotal' => (float) $r->subtotal,
            ])->toArray();

            $this->neo4j->run('
                UNWIND $batch AS row
                MATCH (v:Venta {id_venta: row.id_venta})
                MATCH (l:Libro {id_producto: row.id_producto})
                MERGE (v)-[det:INCLUYE_LIBRO {id_detalle_venta: row.id_detalle_venta}]->(l)
                SET det.cantidad = row.cantidad,
                    det.precio_unitario = row.precio_unitario,
                    det.descuento_aplicado = row.descuento_aplicado,
                    det.subtotal = row.subtotal,
                    det.id_oferta = row.id_oferta
            ', ['batch' => $batch]);
        });
    }

    /**
     * 8. Migrar Devoluciones (tbdevoluciones + tbestado_devolucion + tbtipo_reembolso)
     */
    private function migrarDevoluciones(): void
    {
        $this->info('8/9 Migrando Devoluciones...');

        DB::table('tbdevoluciones as d')
            ->join('tbestado_devolucion as ed', 'd.id_estado_devolucion', '=', 'ed.id_estado_devolucion')
            ->join('tbtipo_reembolso as tr', 'd.id_tipo_reembolso', '=', 'tr.id_tipo_reembolso')
            ->select('d.*', 'ed.nombre as estado_nombre', 'tr.nombre as reembolso_nombre')
            ->orderBy('d.id_devolucion')
            ->chunk($this->chunkSize, function ($rows) {
                $batch = $rows->map(fn ($r) => [
                    'id_devolucion' => (int) $r->id_devolucion,
                    'id_venta' => (int) $r->id_venta,
                    'id_motivo_devolucion' => (int) $r->id_motivo_devolucion,
                    'fecha_devolucion' => str_replace(' ', 'T', $r->fecha_devolucion),
                    'estado' => strtoupper(trim($r->estado_nombre)),
                    'tipo_reembolso' => $r->reembolso_nombre,
                ])->toArray();

                $this->neo4j->run('
                    UNWIND $batch AS row
                    MERGE (dev:Devolucion {id_devolucion: row.id_devolucion})
                    SET dev.fecha_devolucion = datetime(row.fecha_devolucion),
                        dev.estado = row.estado,
                        dev.tipo_reembolso = row.tipo_reembolso
                    WITH dev, row
                    MATCH (v:Venta {id_venta: row.id_venta})
                    MATCH (mot:MotivoDevolucion {id_motivo_devolucion: row.id_motivo_devolucion})
                    MERGE (v)-[:TIENE_DEVOLUCION]->(dev)
                    MERGE (dev)-[:POR_MOTIVO]->(mot)
                ', ['batch' => $batch]);
            });
    }

    /**
     * 9. Migrar Detalle de Devoluciones (tbdetalle_devolucion + JOIN con tbdetalle_venta para obtener id_producto)
     */
    private function migrarDetalleDevoluciones(): void
    {
        $this->info('9/9 Migrando Detalle de Devoluciones (relación DEVOLVIO_ITEM)...');

        DB::table('tbdetalle_devolucion as dd')
            ->join('tbdetalle_venta as dv', 'dd.id_detalle_venta', '=', 'dv.id_detalle_venta')
            ->select('dd.*', 'dv.id_producto')
            ->orderBy('dd.id_detalle_devolucion')
            ->chunk($this->chunkSize, function ($rows) {
                $batch = $rows->map(fn ($r) => [
                    'id_detalle_devolucion' => (int) $r->id_detalle_devolucion,
                    'id_devolucion' => (int) $r->id_devolucion,
                    'id_detalle_venta' => (int) $r->id_detalle_venta,
                    'id_producto' => (int) $r->id_producto,
                    'cantidad' => (int) $r->cantidad,
                    'monto_reembolso' => (float) $r->monto_reembolso,
                    'reingresa_stock' => (bool) $r->reingresa_stock,
                ])->toArray();

                $this->neo4j->run('
                    UNWIND $batch AS row
                    MATCH (dev:Devolucion {id_devolucion: row.id_devolucion})
                    MATCH (l:Libro {id_producto: row.id_producto})
                    MERGE (dev)-[r:DEVOLVIO_ITEM {id_detalle_devolucion: row.id_detalle_devolucion}]->(l)
                    SET r.id_detalle_venta = row.id_detalle_venta,
                        r.cantidad = row.cantidad,
                        r.monto_reembolso = row.monto_reembolso,
                        r.reingresa_stock = row.reingresa_stock
                ', ['batch' => $batch]);
            });
    }
}
