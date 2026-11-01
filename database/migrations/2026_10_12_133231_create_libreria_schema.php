<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbdepartamentos', function (Blueprint $table): void {
            $table->increments('id_departamento');
            $table->string('nombre', 60)->unique();
        });

        Schema::create('tbmunicipios', function (Blueprint $table): void {
            $table->increments('id_municipio');
            $table->unsignedInteger('id_departamento');
            $table->string('nombre', 80);
            $table->unique(['id_departamento', 'nombre'], 'uq_municipio');
            $table->foreign('id_departamento')->references('id_departamento')->on('tbdepartamentos');
        });

        Schema::create('tbdistritos', function (Blueprint $table): void {
            $table->increments('id_distrito');
            $table->unsignedInteger('id_municipio');
            $table->string('nombre', 80);
            $table->unique(['id_municipio', 'nombre'], 'uq_distrito');
            $table->foreign('id_municipio')->references('id_municipio')->on('tbmunicipios');
        });

        Schema::create('tbclientes', function (Blueprint $table): void {
            $table->increments('id_cliente');
            $table->string('nombre', 120);
            $table->string('telefono', 15)->nullable();
            $table->string('email', 120)->nullable()->unique();
            $table->unsignedInteger('id_distrito');
            $table->string('direccion', 255)->nullable();
            $table->date('fecha_registro')->useCurrent();
            $table->foreign('id_distrito')->references('id_distrito')->on('tbdistritos');
            $table->index('id_distrito', 'ix_clientes_distrito');
        });

        Schema::create('tbeditoriales', function (Blueprint $table): void {
            $table->increments('id_editorial');
            $table->string('nombre', 120)->unique();
            $table->string('pais', 60)->nullable();
        });

        Schema::create('tbautores', function (Blueprint $table): void {
            $table->increments('id_autor');
            $table->string('nombre', 120);
            $table->string('nacionalidad', 60)->nullable();
        });

        Schema::create('tbproductos', function (Blueprint $table): void {
            $table->increments('id_producto');
            $table->string('isbn', 13)->unique();
            $table->string('nombre', 200);
            $table->unsignedInteger('id_editorial');
            $table->smallInteger('anio_publicacion')->nullable();
            $table->string('idioma', 30)->default('Español');
            $table->string('formato', 15)->default('RUSTICA');
            $table->unsignedInteger('num_paginas')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('stock_minimo')->default(5);
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('costo_promedio', 10, 2)->default(0);
            $table->foreign('id_editorial')->references('id_editorial')->on('tbeditoriales');
            $table->index('id_editorial', 'ix_productos_editorial');
            $table->index('nombre', 'ix_productos_nombre');
        });

        Schema::create('tbcategoria', function (Blueprint $table): void {
            $table->increments('id_categoria');
            $table->string('nombre', 60)->unique();
        });

        Schema::create('tbproveedor', function (Blueprint $table): void {
            $table->increments('id_proveedor');
            $table->string('nombre', 120)->unique();
            $table->string('telefono', 15)->nullable();
            $table->string('direccion', 255)->nullable();
        });

        Schema::create('producto_autor', function (Blueprint $table): void {
            $table->unsignedInteger('id_producto');
            $table->unsignedInteger('id_autor');
            $table->string('rol', 12)->default('AUTOR');
            $table->primary(['id_producto', 'id_autor', 'rol']);
            $table->foreign('id_producto')->references('id_producto')->on('tbproductos')->cascadeOnDelete();
            $table->foreign('id_autor')->references('id_autor')->on('tbautores');
            $table->index('id_autor', 'ix_producto_autor_autor');
        });

        Schema::create('producto_categoria', function (Blueprint $table): void {
            $table->unsignedInteger('id_producto');
            $table->unsignedInteger('id_categoria');
            $table->primary(['id_producto', 'id_categoria']);
            $table->foreign('id_producto')->references('id_producto')->on('tbproductos')->cascadeOnDelete();
            $table->foreign('id_categoria')->references('id_categoria')->on('tbcategoria');
            $table->index('id_categoria', 'ix_producto_categoria_cat');
        });

        Schema::create('producto_proveedor', function (Blueprint $table): void {
            $table->unsignedInteger('id_producto');
            $table->unsignedInteger('id_proveedor');
            $table->decimal('precio_costo', 10, 2);
            $table->primary(['id_producto', 'id_proveedor']);
            $table->foreign('id_producto')->references('id_producto')->on('tbproductos')->cascadeOnDelete();
            $table->foreign('id_proveedor')->references('id_proveedor')->on('tbproveedor');
            $table->index('id_proveedor', 'ix_producto_proveedor_prov');
        });

        Schema::create('tbtipo_descuento', function (Blueprint $table): void {
            $table->increments('id_tipo_descuento');
            $table->string('nombre', 30)->unique();
            $table->string('descripcion', 255)->nullable();
        });

        Schema::create('tboferta', function (Blueprint $table): void {
            $table->increments('id_oferta');
            $table->string('nombre_oferta', 100);
            $table->unsignedInteger('id_tipo_descuento');
            $table->decimal('valor_descuento', 10, 2);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->boolean('estado')->default(true);
            $table->foreign('id_tipo_descuento')->references('id_tipo_descuento')->on('tbtipo_descuento');
        });

        Schema::create('tboferta_producto', function (Blueprint $table): void {
            $table->unsignedInteger('id_oferta');
            $table->unsignedInteger('id_producto');
            $table->primary(['id_oferta', 'id_producto']);
            $table->foreign('id_oferta')->references('id_oferta')->on('tboferta')->cascadeOnDelete();
            $table->foreign('id_producto')->references('id_producto')->on('tbproductos');
        });

        Schema::create('tboferta_categoria', function (Blueprint $table): void {
            $table->unsignedInteger('id_oferta');
            $table->unsignedInteger('id_categoria');
            $table->primary(['id_oferta', 'id_categoria']);
            $table->foreign('id_oferta')->references('id_oferta')->on('tboferta')->cascadeOnDelete();
            $table->foreign('id_categoria')->references('id_categoria')->on('tbcategoria');
        });

        Schema::create('tbestado_ventas', function (Blueprint $table): void {
            $table->increments('id_estado_ventas');
            $table->string('nombre', 30)->unique();
            $table->string('descripcion', 255)->nullable();
        });

        Schema::create('tbventas', function (Blueprint $table): void {
            $table->increments('id_venta');
            $table->timestamp('fecha')->useCurrent();
            $table->unsignedInteger('id_cliente');
            $table->unsignedInteger('id_oferta')->nullable();
            $table->decimal('subtotal_general', 10, 2)->default(0);
            $table->decimal('descuento_global', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->unsignedInteger('id_estado_ventas');
            $table->foreign('id_cliente')->references('id_cliente')->on('tbclientes');
            $table->foreign('id_oferta')->references('id_oferta')->on('tboferta');
            $table->foreign('id_estado_ventas')->references('id_estado_ventas')->on('tbestado_ventas');
            $table->index('fecha', 'ix_ventas_fecha');
            $table->index(['id_cliente', 'fecha'], 'ix_ventas_cliente_fecha');
        });

        Schema::create('tbdetalle_venta', function (Blueprint $table): void {
            $table->increments('id_detalle_venta');
            $table->unsignedInteger('id_venta');
            $table->unsignedInteger('id_producto');
            $table->unsignedInteger('id_oferta')->nullable();
            $table->unsignedInteger('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('descuento_aplicado', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2);
            $table->unique(['id_venta', 'id_producto'], 'uq_detalle_venta');
            $table->foreign('id_venta')->references('id_venta')->on('tbventas');
            $table->foreign('id_producto')->references('id_producto')->on('tbproductos');
            $table->foreign('id_oferta')->references('id_oferta')->on('tboferta');
            $table->index('id_producto', 'ix_detalle_venta_producto');
        });

        Schema::create('tbestado_compra', function (Blueprint $table): void {
            $table->increments('id_estado_compra');
            $table->string('nombre', 30)->unique();
        });

        Schema::create('tbcompras', function (Blueprint $table): void {
            $table->increments('id_compra');
            $table->unsignedInteger('id_proveedor');
            $table->string('numero_factura', 30);
            $table->timestamp('fecha')->useCurrent();
            $table->unsignedInteger('id_estado_compra');
            $table->unique(['id_proveedor', 'numero_factura'], 'uq_compra_factura');
            $table->foreign('id_proveedor')->references('id_proveedor')->on('tbproveedor');
            $table->foreign('id_estado_compra')->references('id_estado_compra')->on('tbestado_compra');
        });

        Schema::create('tbdetalle_compras', function (Blueprint $table): void {
            $table->increments('id_detalle_compra');
            $table->unsignedInteger('id_compra');
            $table->unsignedInteger('id_producto');
            $table->unsignedInteger('cantidad');
            $table->decimal('costo_unitario', 10, 2);
            $table->unique(['id_compra', 'id_producto'], 'uq_detalle_compra');
            $table->foreign('id_compra')->references('id_compra')->on('tbcompras')->cascadeOnDelete();
            $table->foreign('id_producto')->references('id_producto')->on('tbproductos');
            $table->index('id_producto', 'ix_detalle_compras_producto');
        });

        Schema::create('tbmotivos_devolucion', function (Blueprint $table): void {
            $table->increments('id_motivo_devolucion');
            $table->string('nombre', 50)->unique();
            $table->string('descripcion', 255)->nullable();
        });

        Schema::create('tbtipo_reembolso', function (Blueprint $table): void {
            $table->increments('id_tipo_reembolso');
            $table->string('nombre', 50)->unique();
            $table->string('descripcion', 255)->nullable();
        });

        Schema::create('tbestado_devolucion', function (Blueprint $table): void {
            $table->increments('id_estado_devolucion');
            $table->string('nombre', 30)->unique();
        });

        Schema::create('tbdevoluciones', function (Blueprint $table): void {
            $table->increments('id_devolucion');
            $table->unsignedInteger('id_venta');
            $table->unsignedInteger('id_tipo_reembolso');
            $table->unsignedInteger('id_motivo_devolucion');
            $table->timestamp('fecha_devolucion')->useCurrent();
            $table->unsignedInteger('id_estado_devolucion');
            $table->foreign('id_venta')->references('id_venta')->on('tbventas');
            $table->foreign('id_tipo_reembolso')->references('id_tipo_reembolso')->on('tbtipo_reembolso');
            $table->foreign('id_motivo_devolucion')->references('id_motivo_devolucion')->on('tbmotivos_devolucion');
            $table->foreign('id_estado_devolucion')->references('id_estado_devolucion')->on('tbestado_devolucion');
            $table->index('id_venta', 'ix_devoluciones_venta');
        });

        Schema::create('tbdetalle_devolucion', function (Blueprint $table): void {
            $table->increments('id_detalle_devolucion');
            $table->unsignedInteger('id_devolucion');
            $table->unsignedInteger('id_detalle_venta');
            $table->unsignedInteger('cantidad');
            $table->decimal('monto_reembolso', 10, 2);
            $table->boolean('reingresa_stock')->default(true);
            $table->unique(['id_devolucion', 'id_detalle_venta'], 'uq_detalle_devolucion');
            $table->foreign('id_devolucion')->references('id_devolucion')->on('tbdevoluciones')->cascadeOnDelete();
            $table->foreign('id_detalle_venta')->references('id_detalle_venta')->on('tbdetalle_venta');
            $table->index('id_detalle_venta', 'ix_detalle_devol_det_venta');
        });

        Schema::create('tbtipo_movimiento', function (Blueprint $table): void {
            $table->increments('id_tipo_movimiento');
            $table->string('nombre', 30)->unique();
        });

        Schema::create('tbmovimientos_inventario', function (Blueprint $table): void {
            $table->increments('id_movimiento');
            $table->unsignedInteger('id_producto');
            $table->unsignedInteger('id_tipo_movimiento');
            $table->timestamp('fecha')->useCurrent();
            $table->integer('cantidad');
            $table->decimal('costo_unitario', 10, 2);
            $table->unsignedInteger('stock_resultante');
            $table->unsignedInteger('id_detalle_compra')->nullable();
            $table->unsignedInteger('id_detalle_venta')->nullable();
            $table->unsignedInteger('id_detalle_devolucion')->nullable();
            $table->string('observacion', 255)->nullable();
            $table->foreign('id_producto')->references('id_producto')->on('tbproductos');
            $table->foreign('id_tipo_movimiento')->references('id_tipo_movimiento')->on('tbtipo_movimiento');
            $table->foreign('id_detalle_compra')->references('id_detalle_compra')->on('tbdetalle_compras');
            $table->foreign('id_detalle_venta')->references('id_detalle_venta')->on('tbdetalle_venta');
            $table->foreign('id_detalle_devolucion')->references('id_detalle_devolucion')->on('tbdetalle_devolucion');
            $table->index(['id_producto', 'fecha'], 'ix_mov_producto_fecha');
        });

        Schema::create('tbtipos_envios', function (Blueprint $table): void {
            $table->increments('id_tipo_envio');
            $table->string('nombre', 50)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->unsignedSmallInteger('dias_estimados');
            $table->decimal('costo', 10, 2);
            $table->boolean('estado')->default(true);
        });

        Schema::create('tbproveedores_envios', function (Blueprint $table): void {
            $table->increments('id_proveedor_envio');
            $table->string('nombre', 100)->unique();
            $table->string('telefono', 15)->nullable();
            $table->boolean('activo')->default(true);
        });

        Schema::create('tbestado_envio', function (Blueprint $table): void {
            $table->increments('id_estado_envio');
            $table->string('nombre', 30)->unique();
            $table->string('descripcion', 255)->nullable();
        });

        Schema::create('tbenvios', function (Blueprint $table): void {
            $table->increments('id_envio');
            $table->unsignedInteger('id_venta')->unique();
            $table->unsignedInteger('id_proveedor_envio');
            $table->unsignedInteger('id_tipo_envio');
            $table->unsignedInteger('id_estado_envio');
            $table->unsignedInteger('id_distrito');
            $table->string('direccion_entrega', 255);
            $table->string('nombre_receptor', 120);
            $table->string('telefono_receptor', 15);
            $table->decimal('costo_envio', 10, 2);
            $table->date('fecha_estimada');
            $table->timestamp('fecha_de_envio')->nullable();
            $table->timestamp('fecha_entrega')->nullable();
            $table->foreign('id_venta')->references('id_venta')->on('tbventas');
            $table->foreign('id_proveedor_envio')->references('id_proveedor_envio')->on('tbproveedores_envios');
            $table->foreign('id_tipo_envio')->references('id_tipo_envio')->on('tbtipos_envios');
            $table->foreign('id_estado_envio')->references('id_estado_envio')->on('tbestado_envio');
            $table->foreign('id_distrito')->references('id_distrito')->on('tbdistritos');
            $table->index('id_proveedor_envio', 'ix_envios_proveedor');
            $table->index('id_distrito', 'ix_envios_distrito');
        });

        Schema::create('tbhistorial_envio', function (Blueprint $table): void {
            $table->increments('id_historial');
            $table->unsignedInteger('id_envio');
            $table->unsignedInteger('id_estado_envio');
            $table->timestamp('fecha')->useCurrent();
            $table->string('observacion', 255)->nullable();
            $table->foreign('id_envio')->references('id_envio')->on('tbenvios')->cascadeOnDelete();
            $table->foreign('id_estado_envio')->references('id_estado_envio')->on('tbestado_envio');
            $table->index(['id_envio', 'fecha'], 'ix_historial_envio');
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'tbhistorial_envio', 'tbenvios', 'tbestado_envio', 'tbproveedores_envios', 'tbtipos_envios',
            'tbmovimientos_inventario', 'tbtipo_movimiento', 'tbdetalle_devolucion', 'tbdevoluciones',
            'tbestado_devolucion', 'tbtipo_reembolso', 'tbmotivos_devolucion', 'tbdetalle_compras',
            'tbcompras', 'tbestado_compra', 'tbdetalle_venta', 'tbventas', 'tbestado_ventas',
            'tboferta_categoria', 'tboferta_producto', 'tboferta', 'tbtipo_descuento',
            'producto_proveedor', 'producto_categoria', 'producto_autor', 'tbproductos',
            'tbproveedor', 'tbcategoria', 'tbautores', 'tbeditoriales', 'tbclientes',
            'tbdistritos', 'tbmunicipios', 'tbdepartamentos',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }
};
