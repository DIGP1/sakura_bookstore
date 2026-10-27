<?php

namespace Database\Seeders;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laudis\Neo4j\Contracts\ClientInterface;
use RuntimeException;

/**
 * Datos de prueba realistas para Sakura Bookstore.
 *
 * QUÉ BORRA: usuarios, clientes, ventas, compras, devoluciones, envíos y kardex.
 * QUÉ CONSERVA: libros, autores, categorías, editoriales, proveedores, ofertas,
 *               geografía y todas las tablas de estados/tipos (y las portadas).
 *
 * QUÉ GENERA (de enero 2025 a ayer, en orden de fecha):
 *   - 1 administrador:  admin@gmail.com / admin123
 *   - 700 clientes, cada uno con su usuario:  clienteN@gmail.com / clienteN  (rol cliente)
 *   - Compras a proveedores que llenan el stock (2 veces por semana)
 *   - Ventas con ofertas, descuentos globales, anulaciones y envíos a domicilio
 *   - Devoluciones dentro de los 30 días (solicitadas, aprobadas y rechazadas)
 *   - Kardex: un movimiento por cada entrada o salida de stock
 *
 * Las reglas son las mismas del sistema (InventarioService, CheckoutService,
 * DevolucionService y OfertaService), así que stock, costo promedio y kardex cuadran.
 *
 * Ejecutar:  php artisan db:seed --class=DatosSakuraSeeder
 */
class DatosSakuraSeeder extends Seeder
{
    private const TOTAL_CLIENTES = 700;
    private const DESDE = '2025-01-06';          // primer día con ventas
    private const VENTAS_BASE_POR_DIA = 8.5;     // promedio antes de temporadas

    private DateTimeZone $local;
    private DateTimeZone $utc;

    // ---- Catálogo (se lee de la base, no se toca) ----
    private array $productos = [];       // id => datos del libro + stock y costo en memoria
    private array $porCategoria = [];    // id_categoria => [id_producto, ...]
    private array $categorias = [];      // lista de id_categoria
    private array $ofertas = [];
    private array $distritos = [];       // [id_distrito, peso]
    private array $proveedoresEnvio = [];
    private array $tiposEnvio = [];      // nombre => [id, dias, costo]
    private array $id = [];              // ids de estados y tipos por nombre

    // ---- Lo que se va generando ----
    private array $filas = [];           // tabla => [filas]
    private array $clientes = [];        // clientes ya registrados (en memoria)
    private array $agenda = [];          // 'Y-m-d' => eventos futuros (devoluciones)
    private array $anulaciones = [];     // ventas a anular al cierre del día
    private array $sigId = [];           // siguiente id por tabla
    private int $facturas = 0;

    public function run(): void
    {
        mt_srand(2026);                  // misma semilla = mismos datos cada vez
        $this->local = new DateTimeZone('America/El_Salvador');
        $this->utc = new DateTimeZone('UTC');

        $this->command->warn('Se borrarán usuarios, clientes, ventas, compras, devoluciones, envíos y kardex.');
        $this->leerCatalogo();

        DB::transaction(function (): void {
            $this->limpiar();
            $this->generar();
            $this->guardar();
        });

        $this->resumen();
        $this->sincronizarNeo4j();
    }

    // =========================================================================
    // 1. LEER EL CATÁLOGO QUE SE CONSERVA
    // =========================================================================
    private function leerCatalogo(): void
    {
        // Ids de estados y tipos (los mismos nombres que usan los servicios)
        $this->id = [
            'venta_completada' => $this->idPorNombre('tbestado_ventas', 'id_estado_ventas', ['COMPLETADA']),
            'venta_anulada' => $this->idPorNombre('tbestado_ventas', 'id_estado_ventas', ['ANULADA', 'ANULADO', 'CANCELADA']),
            'compra_recibida' => $this->idPorNombre('tbestado_compra', 'id_estado_compra', ['RECIBIDA', 'RECIBIDO']),
            'dev_solicitada' => $this->idPorNombre('tbestado_devolucion', 'id_estado_devolucion', ['SOLICITADA']),
            'dev_aprobada' => $this->idPorNombre('tbestado_devolucion', 'id_estado_devolucion', ['APROBADA', 'APROBADO']),
            'dev_rechazada' => $this->idPorNombre('tbestado_devolucion', 'id_estado_devolucion', ['RECHAZADA']),
            'mov_compra' => $this->idPorNombre('tbtipo_movimiento', 'id_tipo_movimiento', ['ENTRADA_COMPRA', 'ENTRADA', 'COMPRA']),
            'mov_venta' => $this->idPorNombre('tbtipo_movimiento', 'id_tipo_movimiento', ['SALIDA_VENTA', 'SALIDA', 'VENTA']),
            'mov_anulacion' => $this->idPorNombre('tbtipo_movimiento', 'id_tipo_movimiento', ['ANULACION_VENTA', 'ENTRADA_ANULACION', 'ENTRADA', 'ANULACION']),
            'mov_devolucion' => $this->idPorNombre('tbtipo_movimiento', 'id_tipo_movimiento', ['ENTRADA_DEVOLUCION', 'ENTRADA', 'DEVOLUCION']),
        ];
        foreach (['PENDIENTE', 'EN_CAMINO', 'ENTREGADO', 'FALLIDO', 'DEVUELTO'] as $estado) {
            $this->id['envio_'.$estado] = $this->idPorNombre('tbestado_envio', 'id_estado_envio', [$estado]);
        }
        $this->id['motivos'] = [];
        foreach (DB::select('SELECT id_motivo_devolucion AS id, nombre FROM tbmotivos_devolucion') as $m) {
            $this->id['motivos'][$m->nombre] = (int) $m->id;
        }
        $this->id['reembolsos'] = array_map(fn ($r) => (int) $r->id,
            DB::select('SELECT id_tipo_reembolso AS id FROM tbtipo_reembolso ORDER BY 1'));

        // Proveedores de libros: si no hay ninguno se crea uno para poder comprar
        if (DB::select('SELECT 1 FROM tbproveedor LIMIT 1') === []) {
            DB::table('tbproveedor')->insert(['nombre' => 'Distribuidora Sakura', 'telefono' => '2222-0000']);
        }
        $proveedores = array_map(fn ($p) => (int) $p->id, DB::select('SELECT id_proveedor AS id FROM tbproveedor ORDER BY 1'));
        $costoProveedor = [];
        foreach (DB::select('SELECT id_producto, id_proveedor, precio_costo FROM producto_proveedor ORDER BY id_producto, precio_costo') as $pp) {
            $costoProveedor[(int) $pp->id_producto] ??= ['prov' => (int) $pp->id_proveedor, 'costo' => (float) $pp->precio_costo];
        }

        // Libros: precio, costo base (de su proveedor) y popularidad
        foreach (DB::select('SELECT id_producto, precio_unitario, costo_promedio, stock_minimo FROM tbproductos ORDER BY id_producto') as $i => $p) {
            $id = (int) $p->id_producto;
            $precio = (float) $p->precio_unitario;
            $costoBase = $costoProveedor[$id]['costo'] ?? ((float) $p->costo_promedio > 0 ? (float) $p->costo_promedio : $precio * 0.65);
            $this->productos[$id] = [
                'precio' => $precio,
                'costo_base' => round(min($costoBase, $precio * 0.9), 2),
                'costo' => 0.0,                          // costo promedio (se calcula con las compras)
                'stock' => 0,                            // se empieza en cero: el stock sale de las compras
                'minimo' => max(3, (int) $p->stock_minimo),
                'prov' => $costoProveedor[$id]['prov'] ?? $proveedores[$i % count($proveedores)],
                'peso' => 0.3 + mt_rand(0, 1000) / 1000 * 2.7,   // unos libros se venden más que otros
                'cats' => [],
            ];
        }
        if ($this->productos === []) {
            throw new RuntimeException('No hay libros en tbproductos: cargá el catálogo antes de correr este seeder.');
        }

        foreach (DB::select('SELECT id_producto, id_categoria FROM producto_categoria') as $pc) {
            if (isset($this->productos[(int) $pc->id_producto])) {
                $this->productos[(int) $pc->id_producto]['cats'][] = (int) $pc->id_categoria;
                $this->porCategoria[(int) $pc->id_categoria][] = (int) $pc->id_producto;
            }
        }
        $this->categorias = array_keys($this->porCategoria);

        // Ofertas vigentes por fechas (se aplican igual que en OfertaService)
        foreach (DB::select('SELECT o.id_oferta, o.valor_descuento, o.monto_minimo, o.fecha_inicio, o.fecha_fin, t.nombre AS tipo
                             FROM tboferta o JOIN tbtipo_descuento t ON t.id_tipo_descuento = o.id_tipo_descuento
                             WHERE o.estado = true') as $o) {
            $libros = array_map(fn ($r) => (int) $r->id_producto,
                DB::select('SELECT id_producto FROM tboferta_producto WHERE id_oferta = ?', [$o->id_oferta]));
            $cats = array_map(fn ($r) => (int) $r->id_categoria,
                DB::select('SELECT id_categoria FROM tboferta_categoria WHERE id_oferta = ?', [$o->id_oferta]));
            $this->ofertas[] = [
                'id' => (int) $o->id_oferta,
                'porcentaje' => strtoupper($o->tipo) === 'PORCENTAJE',
                'valor' => (float) $o->valor_descuento,
                'minimo' => $o->monto_minimo === null ? null : (float) $o->monto_minimo,
                'desde' => substr((string) $o->fecha_inicio, 0, 10),
                'hasta' => substr((string) $o->fecha_fin, 0, 10),
                'libros' => $libros,
                'cats' => $cats,
                'global' => $libros === [] && $cats === [],
            ];
        }

        // Distritos: más clientes en las zonas con más población
        $pesoDepto = ['San Salvador' => 10, 'La Libertad' => 6, 'Santa Ana' => 3, 'San Miguel' => 3, 'Sonsonate' => 2];
        foreach (DB::select('SELECT d.id_distrito, dep.nombre AS depto FROM tbdistritos d
                             JOIN tbmunicipios m ON m.id_municipio = d.id_municipio
                             JOIN tbdepartamentos dep ON dep.id_departamento = m.id_departamento') as $d) {
            $this->distritos[] = [(int) $d->id_distrito, $pesoDepto[$d->depto] ?? 1];
        }
        if ($this->distritos === []) {
            throw new RuntimeException('No hay distritos: cargá la geografía antes de correr este seeder.');
        }

        // Envíos: proveedores y tipos (si no hay proveedores se crean)
        if (DB::select('SELECT 1 FROM tbproveedores_envios LIMIT 1') === []) {
            foreach ([['Correos de El Salvador', '2527-7600'], ['Urbano Express', '2222-1234'], ['C807 Xpress', '2121-0807']] as [$n, $t]) {
                DB::table('tbproveedores_envios')->insert(['nombre' => $n, 'telefono' => $t, 'activo' => 'true']);
            }
        }
        $this->proveedoresEnvio = array_map(fn ($p) => (int) $p->id,
            DB::select('SELECT id_proveedor_envio AS id FROM tbproveedores_envios WHERE activo = true ORDER BY 1'));
        foreach (DB::select('SELECT id_tipo_envio, nombre, dias_estimados, costo FROM tbtipos_envios WHERE estado = true') as $t) {
            $this->tiposEnvio[$t->nombre] = ['id' => (int) $t->id_tipo_envio, 'dias' => (int) $t->dias_estimados, 'costo' => (float) $t->costo];
        }
    }

    // =========================================================================
    // 2. BORRAR LOS DATOS VIEJOS (el catálogo NO se toca)
    // =========================================================================
    private function limpiar(): void
    {
        // TRUNCATE no dispara el trigger del kardex (ese trigger solo bloquea UPDATE y DELETE)
        DB::statement('TRUNCATE TABLE tbhistorial_envio, tbenvios, tbmovimientos_inventario,
                       tbdetalle_devolucion, tbdevoluciones, tbdetalle_venta, tbventas,
                       tbdetalle_compras, tbcompras, tbclientes, sessions, password_reset_tokens, users
                       RESTART IDENTITY CASCADE');
    }

    // =========================================================================
    // 3. GENERAR: se recorre el calendario día por día
    // =========================================================================
    private function generar(): void
    {
        // El administrador
        $this->agregar('users', [
            'id' => $this->nuevoId('users'),
            'name' => 'Administrador Sakura',
            'email' => 'admin@gmail.com',
            'email_verified_at' => '2025-01-01 12:00:00',
            'password' => Hash::make('admin123', ['rounds' => 10]),
            'rol' => 'admin',
            'created_at' => '2025-01-01 12:00:00',
            'updated_at' => '2025-01-01 12:00:00',
        ]);

        $inicio = new DateTimeImmutable(self::DESDE, $this->local);
        $ayer = (new DateTimeImmutable('today', $this->local))->modify('-1 day');

        // Compra inicial: 3 días antes de abrir se llena todo el inventario
        $this->reabastecer($inicio->modify('-3 days'), true);

        for ($dia = $inicio; $dia <= $ayer; $dia = $dia->modify('+1 day')) {
            $diaSemana = (int) $dia->format('N');          // 1 = lunes ... 7 = domingo

            // 07:00 compras a proveedores los lunes y jueves
            if ($diaSemana === 1 || $diaSemana === 4) {
                $this->reabastecer($dia, false);
            }

            // 08:00 eventos pendientes de ese día (solicitudes y aprobaciones de devoluciones)
            foreach ($this->agenda[$dia->format('Y-m-d')] ?? [] as $evento) {
                $this->{$evento['tipo']}($evento);
            }
            unset($this->agenda[$dia->format('Y-m-d')]);

            // 08:00 a 21:00 ventas del día, en orden de hora:
            // las primeras compras de los clientes nuevos + las ventas a clientes ya registrados
            $ventasDelDia = $this->registrarClientesNuevos($dia);
            foreach ($this->horasDeVenta($dia) as $hora) {
                $ventasDelDia[] = [$hora, null];
            }
            usort($ventasDelDia, fn ($a, $b) => $a[0] <=> $b[0]);
            foreach ($ventasDelDia as [$hora, $cliente]) {
                $cliente ??= $this->elegirCliente($hora);
                if ($cliente !== null) {
                    $this->vender($cliente, $hora);
                }
            }

            // 21:30 cierre de caja: se anulan las ventas marcadas (InventarioService::cancelSale)
            foreach ($this->anulaciones as $i => $lineas) {
                $hora = $dia->setTime(21, 30 + $i % 30);
                foreach ($lineas as $id => $linea) {
                    $this->moverStock($id, $linea['cantidad'], $this->productos[$id]['costo'], $hora,
                        'mov_anulacion', ['id_detalle_venta' => $linea['id_detalle_venta']], 'Reingreso por anulación de venta');
                }
            }
            $this->anulaciones = [];
        }
    }

    /** Cuántas ventas hay en el día: temporada × día de la semana × crecimiento. */
    private function horasDeVenta(DateTimeImmutable $dia): array
    {
        $mes = [1 => 1.10, 1.15, 0.95, 1.15, 0.95, 0.90, 0.95, 1.05, 0.95, 1.00, 1.20, 1.45][(int) $dia->format('n')];
        $semana = [1 => 0.85, 0.85, 0.90, 0.95, 1.10, 1.35, 1.00][(int) $dia->format('N')];
        $crecimiento = $dia->format('Y') === '2025' ? 1.0 : 1.15;
        $esperadas = self::VENTAS_BASE_POR_DIA * $mes * $semana * $crecimiento;
        $cantidad = max(0, (int) round($esperadas + $this->normal() * sqrt($esperadas)));

        $horas = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $horas[] = $dia->setTime($this->elegir([9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20],
                [3, 5, 6, 6, 5, 6, 7, 8, 8, 7, 5, 3]), mt_rand(0, 59), mt_rand(0, 59));
        }
        sort($horas);

        return $horas;
    }

    // =========================================================================
    // CLIENTES Y USUARIOS
    // =========================================================================
    /** Registra los clientes nuevos del día y devuelve sus primeras compras: [[hora, cliente], ...]. */
    private function registrarClientesNuevos(DateTimeImmutable $dia): array
    {
        $primerasCompras = [];
        $registrados = count($this->clientes);
        if ($registrados >= self::TOTAL_CLIENTES) {
            return $primerasCompras;
        }
        // El primer mes llegan más clientes (apertura); después, alrededor de 1 por día
        $esperados = $dia < new DateTimeImmutable('2025-02-06', $this->local) ? 3.0 : 0.95;
        $nuevos = min(self::TOTAL_CLIENTES - $registrados, max(0, (int) round($esperados + $this->normal() * sqrt($esperados))));

        for ($i = 0; $i < $nuevos; $i++) {
            $n = $registrados + $i + 1;                    // cliente1, cliente2, ...
            $hora = $dia->setTime(mt_rand(8, 19), mt_rand(0, 59));
            $nombre = $this->nombreSalvadoreno();
            $distrito = $this->elegir(array_column($this->distritos, 0), array_column($this->distritos, 1));
            $telefono = $this->elegir(['7', '6'], [3, 2]).mt_rand(100, 999).'-'.mt_rand(1000, 9999);
            $idUsuario = $this->nuevoId('users');

            // Usuario para iniciar sesión: la contraseña se guarda encriptada con bcrypt (Hash::make)
            $this->agregar('users', [
                'id' => $idUsuario,
                'name' => $nombre,
                'email' => "cliente{$n}@gmail.com",
                'email_verified_at' => $this->utc($hora),
                'password' => Hash::make("cliente{$n}", ['rounds' => 10]),
                'rol' => 'cliente',
                'created_at' => $this->utc($hora),
                'updated_at' => $this->utc($hora),
            ]);
            $this->agregar('tbclientes', [
                'id_cliente' => $this->nuevoId('tbclientes'),
                'id_user' => $idUsuario,
                'nombre' => $nombre,
                'email' => "cliente{$n}@gmail.com",
                'telefono' => $telefono,
                'id_distrito' => $distrito,
                'direccion' => $this->direccion(),
                'fecha_registro' => $dia->format('Y-m-d'),
            ]);

            // Tipo de cliente: unos compran seguido y otros casi nunca; algunos dejan de comprar
            $tipo = $this->elegir(['fiel', 'regular', 'ocasional'], [15, 35, 50]);
            $cliente = [
                'id' => $n,
                'nombre' => $nombre,
                'telefono' => $telefono,
                'distrito' => $distrito,
                'direccion' => end($this->filas['tbclientes'])['direccion'],
                'registro' => $hora,
                'peso' => ['fiel' => 6.0, 'regular' => 2.0, 'ocasional' => 0.6][$tipo],
                'activo_hasta' => mt_rand(1, 100) <= 45 ? $dia->modify('+'.mt_rand(45, 600).' days') : null,
                'favoritas' => $this->categorias === [] ? [] : array_unique([
                    $this->categorias[array_rand($this->categorias)],
                    $this->categorias[array_rand($this->categorias)],
                ]),
            ];
            $this->clientes[] = $cliente;

            // La mayoría compra el mismo día que se registra, unos minutos después
            if (mt_rand(1, 100) <= 85) {
                $primerasCompras[] = [$hora->modify('+'.mt_rand(5, 50).' minutes'), $cliente];
            }
        }

        return $primerasCompras;
    }

    /** Elige un cliente ya registrado a esa hora y que siga activo; los fieles salen más seguido. */
    private function elegirCliente(DateTimeImmutable $hora): ?array
    {
        $candidatos = [];
        $pesos = [];
        foreach ($this->clientes as $c) {
            if ($c['registro'] <= $hora && ($c['activo_hasta'] === null || $c['activo_hasta'] >= $hora)) {
                $candidatos[] = $c;
                $pesos[] = $c['peso'];
            }
        }

        return $candidatos === [] ? null : $this->elegir($candidatos, $pesos);
    }

    // =========================================================================
    // VENTAS (misma lógica de CheckoutService + InventarioService::createSale)
    // =========================================================================
    private function vender(array $cliente, DateTimeImmutable $hora): void
    {
        $fecha = $hora->format('Y-m-d');
        $lineas = [];
        $cuantos = $this->elegir([1, 2, 3, 4], [55, 30, 11, 4]);

        for ($intento = 0; count($lineas) < $cuantos && $intento < 12; $intento++) {
            $id = $this->elegirLibro($cliente);
            if ($id === null || isset($lineas[$id]) || $this->productos[$id]['stock'] === 0) {
                continue;                                 // agotado o repetido: se busca otro
            }
            $cantidad = min($this->elegir([1, 2, 3], [85, 12, 3]), $this->productos[$id]['stock']);
            $precio = $this->productos[$id]['precio'];
            [$idOferta, $descuento] = $this->mejorOfertaLibro($id, $precio, $fecha);
            $lineas[$id] = [
                'id_producto' => $id,
                'id_oferta' => $idOferta,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'descuento_aplicado' => $descuento,
                'subtotal' => round($cantidad * ($precio - $descuento), 2),
            ];
        }
        if ($lineas === []) {
            return;                                       // no había nada disponible
        }

        $subtotal = round(array_sum(array_column($lineas, 'subtotal')), 2);
        [$idOfertaGlobal, $descuentoGlobal] = $this->mejorOfertaGlobal($subtotal, $fecha);
        $anulada = mt_rand(1, 1000) <= 35;                // 3.5 % de las ventas se anulan

        $idVenta = $this->nuevoId('tbventas');
        $this->agregar('tbventas', [
            'id_venta' => $idVenta,
            'fecha' => $this->utc($hora),
            'id_cliente' => $cliente['id'],
            'id_oferta' => $idOfertaGlobal,
            'subtotal_general' => $subtotal,
            'descuento_global' => $descuentoGlobal,
            'total' => round($subtotal - $descuentoGlobal, 2),
            'id_estado_ventas' => $anulada ? $this->id['venta_anulada'] : $this->id['venta_completada'],
        ]);

        // Cada libro: línea de detalle, baja el stock y queda su salida en el kardex
        foreach ($lineas as $id => $linea) {
            $idDetalle = $this->nuevoId('tbdetalle_venta');
            $this->agregar('tbdetalle_venta', ['id_detalle_venta' => $idDetalle, 'id_venta' => $idVenta] + $linea);
            $lineas[$id]['id_detalle_venta'] = $idDetalle;
            $this->moverStock($id, -$linea['cantidad'], $this->productos[$id]['costo'], $hora,
                'mov_venta', ['id_detalle_venta' => $idDetalle], 'Salida por venta');
        }

        if ($anulada) {
            $this->anulaciones[] = $lineas;              // el stock regresa al cierre del día
            return;
        }

        if (mt_rand(1, 100) <= 45) {
            $this->registrarEnvio($idVenta, $cliente, $hora);
        }

        // 3.5 % de las ventas completadas tendrán una solicitud de devolución (máx. 30 días)
        if (mt_rand(1, 1000) <= 35) {
            $solicitud = $hora->modify('+'.mt_rand(1, 25).' days')->setTime(8, mt_rand(0, 29), mt_rand(0, 59));
            $this->agenda[$solicitud->format('Y-m-d')][] = [
                'tipo' => 'solicitarDevolucion',
                'hora' => $solicitud,
                'id_venta' => $idVenta,
                'lineas' => array_values($lineas),
                'factor' => $subtotal > 0 ? round(($subtotal - $descuentoGlobal) / $subtotal, 6) : 1.0,
            ];
        }
    }

    /** 65 % de las veces el cliente elige de sus géneros favoritos. */
    private function elegirLibro(array $cliente): ?int
    {
        $grupo = array_keys($this->productos);
        if ($cliente['favoritas'] !== [] && mt_rand(1, 100) <= 65) {
            $grupo = [];
            foreach ($cliente['favoritas'] as $cat) {
                array_push($grupo, ...($this->porCategoria[$cat] ?? []));
            }
            $grupo = $grupo === [] ? array_keys($this->productos) : array_values(array_unique($grupo));
        }

        return $this->elegir($grupo, array_map(fn ($id) => $this->productos[$id]['peso'], $grupo));
    }

    /** La oferta por libro o por categoría que más descuenta (OfertaService::mejorOferta). */
    private function mejorOfertaLibro(int $idProducto, float $precio, string $fecha): array
    {
        $mejor = [null, 0.0];
        foreach ($this->ofertas as $o) {
            if ($o['global'] || $fecha < $o['desde'] || $fecha > $o['hasta']) {
                continue;
            }
            if (! in_array($idProducto, $o['libros'], true) && array_intersect($o['cats'], $this->productos[$idProducto]['cats']) === []) {
                continue;
            }
            $descuento = $this->calcularDescuento($o, $precio);
            if ($descuento > $mejor[1]) {
                $mejor = [$o['id'], $descuento];
            }
        }

        return $mejor;
    }

    /** La oferta global que más descuenta respetando su monto mínimo (OfertaService::descuentoGlobal). */
    private function mejorOfertaGlobal(float $subtotal, string $fecha): array
    {
        $mejor = [null, 0.0];
        foreach ($this->ofertas as $o) {
            if (! $o['global'] || $fecha < $o['desde'] || $fecha > $o['hasta']) {
                continue;
            }
            if ($o['minimo'] !== null && $subtotal < $o['minimo']) {
                continue;
            }
            $descuento = $this->calcularDescuento($o, $subtotal);
            if ($descuento > $mejor[1]) {
                $mejor = [$o['id'], $descuento];
            }
        }

        return $mejor;
    }

    private function calcularDescuento(array $oferta, float $monto): float
    {
        $descuento = $oferta['porcentaje'] ? $monto * $oferta['valor'] / 100 : $oferta['valor'];

        return round(min($descuento, $monto), 2);
    }

    // =========================================================================
    // ENVÍOS (CheckoutService::registrarEnvio + cambios de estado del panel)
    // =========================================================================
    private function registrarEnvio(int $idVenta, array $cliente, DateTimeImmutable $hora): void
    {
        if ($this->tiposEnvio === [] || $this->proveedoresEnvio === []) {
            return;
        }
        // 75 % envío estándar y 25 % express
        $nombreTipo = isset($this->tiposEnvio['EXPRESS']) && mt_rand(1, 100) <= 25 ? 'EXPRESS'
            : (isset($this->tiposEnvio['ESTANDAR']) ? 'ESTANDAR' : array_key_first($this->tiposEnvio));
        $tipo = $this->tiposEnvio[$nombreTipo];

        // Línea de tiempo del envío: PENDIENTE -> EN_CAMINO -> ENTREGADO (o FALLIDO / DEVUELTO)
        $salida = $tipo['dias'] <= 1 ? $hora->modify('+'.mt_rand(2, 4).' hours') : $hora->modify('+1 day')->setTime(mt_rand(8, 11), mt_rand(0, 59));
        $llegada = $salida->modify('+'.mt_rand(0, $tipo['dias']).' days')->setTime(mt_rand(10, 18), mt_rand(0, 59));
        if ($llegada <= $salida) {
            $llegada = $salida->modify('+'.mt_rand(2, 5).' hours');   // nunca se entrega antes de salir
        }
        $eventos = [[$hora, 'PENDIENTE', 'Pedido registrado en la tienda en línea'], [$salida, 'EN_CAMINO', 'El pedido salió de la tienda']];
        $suerte = mt_rand(1, 100);
        if ($suerte <= 4) {
            $eventos[] = [$llegada, 'FALLIDO', 'No se pudo entregar el pedido'];
            $reintento = $llegada->modify('+1 day')->setTime(9, mt_rand(0, 59));
            $eventos[] = [$reintento, 'EN_CAMINO', 'El pedido salió de la tienda'];
            $eventos[] = [$reintento->modify('+'.mt_rand(3, 7).' hours'), 'ENTREGADO', 'Pedido entregado'];
        } elseif ($suerte <= 6) {
            $eventos[] = [$llegada, 'DEVUELTO', 'El pedido regresó a la tienda'];
        } else {
            $eventos[] = [$llegada, 'ENTREGADO', 'Pedido entregado'];
        }

        // Solo se guarda lo que ya pasó (los envíos de los últimos días siguen en camino)
        $ahora = new DateTimeImmutable('now', $this->local);
        $eventos = array_values(array_filter($eventos, fn ($e) => $e[0] <= $ahora));
        $ultimo = end($eventos);
        $enCamino = array_values(array_filter($eventos, fn ($e) => $e[1] === 'EN_CAMINO'));
        $entregado = array_values(array_filter($eventos, fn ($e) => $e[1] === 'ENTREGADO'));

        $idEnvio = $this->nuevoId('tbenvios');
        $this->agregar('tbenvios', [
            'id_envio' => $idEnvio,
            'id_venta' => $idVenta,
            'id_proveedor_envio' => $this->proveedoresEnvio[array_rand($this->proveedoresEnvio)],
            'id_tipo_envio' => $tipo['id'],
            'id_estado_envio' => $this->id['envio_'.$ultimo[1]],
            'id_distrito' => $cliente['distrito'],
            'direccion_entrega' => $cliente['direccion'],
            'nombre_receptor' => $cliente['nombre'],
            'telefono_receptor' => $cliente['telefono'],
            'costo_envio' => $tipo['costo'],
            'fecha_estimada' => $hora->modify('+'.$tipo['dias'].' days')->format('Y-m-d'),
            'fecha_de_envio' => $enCamino === [] ? null : $this->utc($enCamino[0][0]),
            'fecha_entrega' => $entregado === [] ? null : $this->utc($entregado[0][0]),
        ]);
        foreach ($eventos as [$cuando, $estado, $texto]) {
            $this->agregar('tbhistorial_envio', [
                'id_historial' => $this->nuevoId('tbhistorial_envio'),
                'id_envio' => $idEnvio,
                'id_estado_envio' => $this->id['envio_'.$estado],
                'fecha' => $this->utc($cuando),
                'observacion' => $texto,
            ]);
        }
    }

    // =========================================================================
    // DEVOLUCIONES (DevolucionService::solicitar + InventarioService::approveReturn)
    // =========================================================================
    private function solicitarDevolucion(array $evento): void
    {
        $ayer = (new DateTimeImmutable('today', $this->local))->modify('-1 day');
        $motivo = $this->elegir(array_keys($this->id['motivos']), array_map(
            fn ($m) => ['DANADO' => 30, 'ERROR_PEDIDO' => 25, 'NO_DESEADO' => 35][$m] ?? 10, array_keys($this->id['motivos'])));

        // Solicitudes de los últimos 5 días siguen pendientes; las demás se aprueban (80 %) o rechazan
        $recienteDesde = $ayer->modify('-5 days');
        $estado = $evento['hora'] >= $recienteDesde ? 'dev_solicitada' : (mt_rand(1, 100) <= 80 ? 'dev_aprobada' : 'dev_rechazada');

        $idDevolucion = $this->nuevoId('tbdevoluciones');
        $this->agregar('tbdevoluciones', [
            'id_devolucion' => $idDevolucion,
            'id_venta' => $evento['id_venta'],
            'id_tipo_reembolso' => $this->id['reembolsos'][array_rand($this->id['reembolsos'])],
            'id_motivo_devolucion' => $this->id['motivos'][$motivo],
            'fecha_devolucion' => $this->utc($evento['hora']),
            'id_estado_devolucion' => $this->id[$estado],
        ]);

        // Se devuelve 1 libro de la compra (a veces 2); el reembolso es lo que de verdad se pagó
        shuffle($evento['lineas']);
        $devueltas = [];
        foreach (array_slice($evento['lineas'], 0, mt_rand(1, 100) <= 80 ? 1 : 2) as $linea) {
            $cantidad = mt_rand(1, $linea['cantidad']);
            $pagado = round(($linea['precio_unitario'] - $linea['descuento_aplicado']) * $evento['factor'], 2);
            $idDetalle = $this->nuevoId('tbdetalle_devolucion');
            $reingresa = $motivo !== 'DANADO';            // un libro dañado no vuelve al stock
            $this->agregar('tbdetalle_devolucion', [
                'id_detalle_devolucion' => $idDetalle,
                'id_devolucion' => $idDevolucion,
                'id_detalle_venta' => $linea['id_detalle_venta'],
                'cantidad' => $cantidad,
                'monto_reembolso' => round($cantidad * $pagado, 2),
                'reingresa_stock' => $reingresa ? 'true' : 'false',
            ]);
            if ($reingresa) {
                $devueltas[] = ['id_producto' => $linea['id_producto'], 'cantidad' => $cantidad, 'id_detalle_devolucion' => $idDetalle];
            }
        }

        // La aprobación (y el regreso al stock) ocurre 1 a 3 días después
        if ($estado === 'dev_aprobada' && $devueltas !== []) {
            $aprobacion = $evento['hora']->modify('+'.mt_rand(1, 3).' days')->setTime(8, mt_rand(30, 59));
            $this->agenda[$aprobacion->format('Y-m-d')][] = ['tipo' => 'aprobarDevolucion', 'hora' => $aprobacion, 'libros' => $devueltas];
        }
    }

    private function aprobarDevolucion(array $evento): void
    {
        foreach ($evento['libros'] as $libro) {
            $this->moverStock($libro['id_producto'], $libro['cantidad'], $this->productos[$libro['id_producto']]['costo'],
                $evento['hora'], 'mov_devolucion', ['id_detalle_devolucion' => $libro['id_detalle_devolucion']],
                'Reingreso por devolución aprobada');
        }
    }

    // =========================================================================
    // COMPRAS A PROVEEDORES (InventarioService::receivePurchase)
    // =========================================================================
    private function reabastecer(DateTimeImmutable $dia, bool $inicial): void
    {
        $porProveedor = [];
        foreach ($this->productos as $id => $p) {
            if ($inicial) {
                $porProveedor[$p['prov']][$id] = mt_rand(18, 35);
            } elseif ($p['stock'] <= $p['minimo'] + 3) {
                $porProveedor[$p['prov']][$id] = max(10, mt_rand(22, 38) - $p['stock']);
            }
        }

        foreach ($porProveedor as $idProveedor => $libros) {
            $hora = $dia->setTime(7, mt_rand(0, 45));
            $idCompra = $this->nuevoId('tbcompras');
            $this->agregar('tbcompras', [
                'id_compra' => $idCompra,
                'id_proveedor' => $idProveedor,
                'numero_factura' => sprintf('FAC-%s-%05d', $dia->format('Y'), ++$this->facturas),
                'fecha' => $this->utc($hora),
                'id_estado_compra' => $this->id['compra_recibida'],
            ]);
            foreach ($libros as $id => $cantidad) {
                $costo = round($this->productos[$id]['costo_base'] * (1 + mt_rand(-40, 60) / 1000), 2);
                $idDetalle = $this->nuevoId('tbdetalle_compras');
                $this->agregar('tbdetalle_compras', [
                    'id_detalle_compra' => $idDetalle,
                    'id_compra' => $idCompra,
                    'id_producto' => $id,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costo,
                ]);
                // Costo promedio ponderado, igual que receivePurchase
                $p = $this->productos[$id];
                $this->productos[$id]['costo'] = round(($p['costo'] * $p['stock'] + $costo * $cantidad) / ($p['stock'] + $cantidad), 2);
                $this->moverStock($id, $cantidad, $costo, $hora, 'mov_compra', ['id_detalle_compra' => $idDetalle], 'Entrada por compra recibida');
            }
        }
    }

    // =========================================================================
    // KARDEX: cada cambio de stock deja su movimiento
    // =========================================================================
    private function moverStock(int $idProducto, int $cantidad, float $costo, DateTimeImmutable $hora, string $tipo, array $referencia, string $observacion): void
    {
        $this->productos[$idProducto]['stock'] += $cantidad;
        if ($this->productos[$idProducto]['stock'] < 0) {
            throw new RuntimeException("El stock del libro {$idProducto} quedó negativo.");
        }

        $this->agregar('tbmovimientos_inventario', [
            'id_movimiento' => $this->nuevoId('tbmovimientos_inventario'),
            'id_producto' => $idProducto,
            'id_tipo_movimiento' => $this->id[$tipo],
            'fecha' => $this->utc($hora),
            'cantidad' => $cantidad,
            'costo_unitario' => max(0.01, round($costo, 2)),
            'stock_resultante' => $this->productos[$idProducto]['stock'],
            'id_detalle_compra' => $referencia['id_detalle_compra'] ?? null,
            'id_detalle_venta' => $referencia['id_detalle_venta'] ?? null,
            'id_detalle_devolucion' => $referencia['id_detalle_devolucion'] ?? null,
            'observacion' => $observacion,
        ]);
    }

    // =========================================================================
    // 4. GUARDAR TODO EN LA BASE
    // =========================================================================
    private function guardar(): void
    {
        // El orden respeta las llaves foráneas (primero los padres, después los hijos)
        $orden = ['users', 'tbclientes', 'tbcompras', 'tbdetalle_compras', 'tbventas', 'tbdetalle_venta',
            'tbdevoluciones', 'tbdetalle_devolucion', 'tbmovimientos_inventario', 'tbenvios', 'tbhistorial_envio'];

        foreach ($orden as $tabla) {
            // Columnas calculadas por la base (GENERATED): no se les puede mandar valor
            $calculadas = array_map(fn ($c) => $c->column_name, DB::select(
                "SELECT column_name FROM information_schema.columns
                 WHERE table_schema = current_schema() AND table_name = ? AND is_generated = 'ALWAYS'", [$tabla]));

            // Todas las filas de una tabla deben traer las mismas columnas
            $columnas = [];
            foreach ($this->filas[$tabla] ?? [] as $fila) {
                $columnas += array_fill_keys(array_keys($fila), null);
            }
            foreach ($calculadas as $columna) {
                unset($columnas[$columna]);
                foreach ($this->filas[$tabla] ?? [] as $i => $fila) {
                    unset($this->filas[$tabla][$i][$columna]);
                }
            }
            $filas = array_map(fn ($f) => array_merge($columnas, $f), $this->filas[$tabla] ?? []);
            foreach (array_chunk($filas, 500) as $lote) {
                DB::table($tabla)->insert($lote);
            }
        }

        // Stock y costo promedio finales de cada libro (salen del kardex generado)
        $valores = [];
        foreach ($this->productos as $id => $p) {
            $valores[] = sprintf('(%d, %d, %.2f)', $id, $p['stock'], $p['costo']);
        }
        DB::statement('UPDATE tbproductos p SET stock = v.stock, costo_promedio = v.costo
                       FROM (VALUES '.implode(',', $valores).') AS v(id, stock, costo)
                       WHERE p.id_producto = v.id');

        // Como los ids se pusieron a mano, se ajustan las secuencias para que el sistema siga desde ahí
        $llaves = ['users' => 'id', 'tbclientes' => 'id_cliente', 'tbcompras' => 'id_compra',
            'tbdetalle_compras' => 'id_detalle_compra', 'tbventas' => 'id_venta', 'tbdetalle_venta' => 'id_detalle_venta',
            'tbdevoluciones' => 'id_devolucion', 'tbdetalle_devolucion' => 'id_detalle_devolucion',
            'tbmovimientos_inventario' => 'id_movimiento', 'tbenvios' => 'id_envio', 'tbhistorial_envio' => 'id_historial'];
        foreach ($llaves as $tabla => $llave) {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$tabla}', '{$llave}'),
                           COALESCE((SELECT MAX({$llave}) FROM {$tabla}), 0) + 1, false)");
        }
    }

    // =========================================================================
    // 5. RESUMEN Y NEO4J
    // =========================================================================
    private function resumen(): void
    {
        $this->command->info('Datos generados:');
        foreach (['users', 'tbclientes', 'tbventas', 'tbdetalle_venta', 'tbcompras', 'tbdevoluciones', 'tbenvios', 'tbmovimientos_inventario'] as $tabla) {
            $this->command->line(sprintf('  %-26s %6d', $tabla, count($this->filas[$tabla] ?? [])));
        }
        $this->command->info('Admin: admin@gmail.com / admin123   ·   Clientes: cliente1@gmail.com / cliente1 ... cliente'.count($this->clientes));
    }

    /** Neo4j tenía los datos viejos: se vacía y se vuelve a llenar con el comando del proyecto. */
    private function sincronizarNeo4j(): void
    {
        try {
            $neo4j = app(ClientInterface::class);
            do {
                $borrados = $neo4j->run('MATCH (n) WITH n LIMIT 5000 DETACH DELETE n RETURN count(*) AS total')->first()->get('total');
            } while ($borrados > 0);
            $this->command->info('Neo4j vaciado. Migrando los datos nuevos...');
            Artisan::call('app:migrar-neo4j', [], $this->command->getOutput());
        } catch (\Throwable $e) {
            $this->command->warn('No se pudo actualizar Neo4j: '.$e->getMessage());
            $this->command->warn('Cuando haya conexión, corré:  php artisan app:migrar-neo4j  (después de vaciar Neo4j).');
        }
    }

    // =========================================================================
    // AYUDANTES
    // =========================================================================
    private function idPorNombre(string $tabla, string $llave, array $nombres): int
    {
        foreach ($nombres as $nombre) {
            $fila = DB::select("SELECT {$llave} AS id FROM {$tabla} WHERE nombre = ?", [$nombre]);
            if ($fila !== []) {
                return (int) $fila[0]->id;
            }
        }
        throw new RuntimeException("Falta el registro '{$nombres[0]}' en {$tabla}.");
    }

    private function nuevoId(string $tabla): int
    {
        return $this->sigId[$tabla] = ($this->sigId[$tabla] ?? 0) + 1;
    }

    private function agregar(string $tabla, array $fila): void
    {
        $this->filas[$tabla][] = $fila;
    }

    /** Hora de El Salvador -> texto en UTC, que es como guarda las fechas Laravel (app.timezone = UTC). */
    private function utc(DateTimeImmutable $hora): string
    {
        return $hora->setTimezone($this->utc)->format('Y-m-d H:i:s');
    }

    /** Elige un elemento al azar según su peso. */
    private function elegir(array $elementos, array $pesos): mixed
    {
        $total = array_sum($pesos);
        $r = mt_rand() / mt_getrandmax() * $total;
        foreach ($elementos as $i => $elemento) {
            $r -= $pesos[$i];
            if ($r <= 0) {
                return $elemento;
            }
        }

        return end($elementos);
    }

    /** Número al azar con distribución normal (para que las ventas por día varíen). */
    private function normal(): float
    {
        $u = max(1e-9, mt_rand() / mt_getrandmax());

        return sqrt(-2 * log($u)) * cos(2 * M_PI * mt_rand() / mt_getrandmax());
    }

    private function nombreSalvadoreno(): string
    {
        $nombres = ['José', 'María', 'Juan', 'Ana', 'Carlos', 'Rosa', 'Luis', 'Carmen', 'Jorge', 'Gabriela', 'Mario',
            'Sofía', 'Óscar', 'Fátima', 'Roberto', 'Andrea', 'Francisco', 'Daniela', 'Miguel', 'Karla', 'Javier',
            'Alejandra', 'Ricardo', 'Valeria', 'Fernando', 'Paola', 'Héctor', 'Lucía', 'Rafael', 'Marta', 'Ernesto',
            'Patricia', 'Nelson', 'Claudia', 'René', 'Verónica', 'Douglas', 'Silvia', 'Kevin', 'Tatiana', 'Josué',
            'Ingrid', 'Diego', 'Raquel', 'Ángel', 'Beatriz', 'Wilfredo', 'Jennifer', 'Edwin', 'Katherine', 'Manuel',
            'Natalia', 'Samuel', 'Yesenia', 'Alexander', 'Gloria', 'Isaac', 'Mónica', 'Bryan', 'Elena'];
        $apellidos = ['Hernández', 'García', 'Martínez', 'López', 'Rodríguez', 'González', 'Pérez', 'Ramírez', 'Flores',
            'Rivera', 'Sánchez', 'Cruz', 'Gómez', 'Reyes', 'Díaz', 'Morales', 'Ramos', 'Alvarado', 'Mejía', 'Portillo',
            'Castillo', 'Chávez', 'Aguilar', 'Guevara', 'Romero', 'Ayala', 'Menjívar', 'Escobar', 'Orellana', 'Rivas',
            'Funes', 'Quintanilla', 'Argueta', 'Bonilla', 'Henríquez', 'Iraheta', 'Campos', 'Pimentel', 'Molina',
            'Villalta', 'Fuentes', 'Lemus', 'Zelaya', 'Amaya', 'Ventura', 'Cáceres', 'Palacios', 'Montes', 'Velásquez'];

        return $nombres[array_rand($nombres)].' '.$apellidos[array_rand($apellidos)].' '.$apellidos[array_rand($apellidos)];
    }

    private function direccion(): string
    {
        $lugares = ['Col. Escalón', 'Col. San Benito', 'Res. Santa Elena', 'Col. Miramonte', 'Col. Flor Blanca',
            'Res. Altos de Santa Tecla', 'Col. Las Palmas', 'Barrio El Centro', 'Col. Santa Lucía', 'Res. Los Héroes',
            'Col. San Francisco', 'Urb. La Cima', 'Col. Médica', 'Barrio San Miguelito', 'Res. Jardines de la Sabana'];

        return $lugares[array_rand($lugares)].', '.$this->elegir(['Calle', 'Pasaje', 'Avenida', 'Senda'], [4, 3, 2, 1])
            .' '.mt_rand(1, 30).', casa #'.mt_rand(1, 250);
    }
}