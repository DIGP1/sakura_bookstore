-- =====================================================================
--  SISTEMA DE ANÁLISIS DE VENTAS Y COMPORTAMIENTO DE CLIENTES
--  Base de datos operacional (OLTP) - Librería
--  Tablas según el diagrama del equipo, con tipos y relaciones corregidos
--  Motor: PostgreSQL 16+
--
--  Nota: PostgreSQL guarda en minúsculas los nombres sin comillas
--  (tbVentas se guarda como tbventas). No usar comillas dobles.
-- =====================================================================

BEGIN;

-- =====================================================================
-- 1. GEOGRAFÍA
-- =====================================================================
CREATE TABLE tbDepartamentos (
    id_departamento  SERIAL PRIMARY KEY,
    nombre           VARCHAR(60) NOT NULL UNIQUE
);

CREATE TABLE tbMunicipios (
    id_municipio     SERIAL PRIMARY KEY,
    id_departamento  INTEGER NOT NULL REFERENCES tbDepartamentos (id_departamento),
    nombre           VARCHAR(80) NOT NULL,
    CONSTRAINT uq_municipio UNIQUE (id_departamento, nombre)
);

CREATE TABLE tbDistritos (
    id_distrito   SERIAL PRIMARY KEY,
    id_municipio  INTEGER NOT NULL REFERENCES tbMunicipios (id_municipio),
    nombre        VARCHAR(80) NOT NULL,
    CONSTRAINT uq_distrito UNIQUE (id_municipio, nombre)
);

-- =====================================================================
-- 2. CLIENTES
-- =====================================================================
CREATE TABLE tbClientes (
    id_cliente      SERIAL PRIMARY KEY,
    nombre          VARCHAR(120) NOT NULL,
    telefono        VARCHAR(15),
    email           VARCHAR(120) UNIQUE,                       -- agregado
    id_distrito     INTEGER NOT NULL REFERENCES tbDistritos (id_distrito),
    direccion       VARCHAR(255),
    fecha_registro  DATE NOT NULL DEFAULT CURRENT_DATE,        -- agregado (antigüedad / RFM)
    CONSTRAINT ck_cliente_telefono CHECK (telefono ~ '^[0-9+\- ]{8,15}$'),
    CONSTRAINT ck_cliente_email    CHECK (email ~* '^[^@\s]+@[^@\s]+\.[^@\s]+$')
);

-- =====================================================================
-- 3. PRODUCTOS (LIBROS), EDITORIALES, AUTORES, CATEGORÍAS Y PROVEEDORES
-- =====================================================================
CREATE TABLE tbEditoriales (
    id_editorial  SERIAL PRIMARY KEY,
    nombre        VARCHAR(120) NOT NULL UNIQUE,
    pais          VARCHAR(60)
);

CREATE TABLE tbAutores (
    id_autor      SERIAL PRIMARY KEY,
    nombre        VARCHAR(120) NOT NULL,
    nacionalidad  VARCHAR(60)
);

CREATE TABLE tbProductos (
    id_producto      SERIAL PRIMARY KEY,
    isbn             VARCHAR(13) NOT NULL UNIQUE,
    nombre           VARCHAR(200) NOT NULL,                    -- título del libro
    id_editorial     INTEGER NOT NULL REFERENCES tbEditoriales (id_editorial),
    anio_publicacion SMALLINT,
    idioma           VARCHAR(30) NOT NULL DEFAULT 'Español',
    formato          VARCHAR(15) NOT NULL DEFAULT 'RUSTICA',
    num_paginas      INTEGER,
    stock            INTEGER NOT NULL DEFAULT 0,
    stock_minimo     INTEGER NOT NULL DEFAULT 5,               -- agregado (alertas)
    precio_unitario  NUMERIC(10,2) NOT NULL,
    costo_promedio   NUMERIC(10,2) NOT NULL DEFAULT 0,         -- agregado (margen / rotación en $)
    CONSTRAINT ck_producto_isbn      CHECK (isbn ~ '^[0-9]{13}$' OR isbn ~ '^[0-9]{9}[0-9X]$'),
    CONSTRAINT ck_producto_anio      CHECK (anio_publicacion BETWEEN 1450 AND 2100),
    CONSTRAINT ck_producto_formato   CHECK (formato IN ('TAPA_DURA', 'RUSTICA', 'BOLSILLO')),
    CONSTRAINT ck_producto_paginas   CHECK (num_paginas > 0),
    CONSTRAINT ck_producto_stock     CHECK (stock >= 0),
    CONSTRAINT ck_producto_stock_min CHECK (stock_minimo >= 0),
    CONSTRAINT ck_producto_precio    CHECK (precio_unitario > 0),
    CONSTRAINT ck_producto_costo     CHECK (costo_promedio >= 0)
);

-- Un libro puede tener varios autores y un autor varios libros (N:M)
CREATE TABLE producto_autor (
    id_producto  INTEGER NOT NULL REFERENCES tbProductos (id_producto) ON DELETE CASCADE,
    id_autor     INTEGER NOT NULL REFERENCES tbAutores (id_autor),
    rol          VARCHAR(12) NOT NULL DEFAULT 'AUTOR',
    PRIMARY KEY (id_producto, id_autor, rol),
    CONSTRAINT ck_producto_autor_rol CHECK (rol IN ('AUTOR', 'COAUTOR', 'TRADUCTOR', 'ILUSTRADOR'))
);

CREATE TABLE tbCategoria (
    id_categoria  SERIAL PRIMARY KEY,
    nombre        VARCHAR(60) NOT NULL UNIQUE
);

CREATE TABLE producto_categoria (
    id_producto   INTEGER NOT NULL REFERENCES tbProductos (id_producto) ON DELETE CASCADE,
    id_categoria  INTEGER NOT NULL REFERENCES tbCategoria (id_categoria),
    PRIMARY KEY (id_producto, id_categoria)
);

CREATE TABLE tbProveedor (
    id_proveedor  SERIAL PRIMARY KEY,
    nombre        VARCHAR(120) NOT NULL UNIQUE,
    telefono      VARCHAR(15),
    direccion     VARCHAR(255)
);

CREATE TABLE producto_proveedor (
    id_producto   INTEGER NOT NULL REFERENCES tbProductos (id_producto) ON DELETE CASCADE,
    id_proveedor  INTEGER NOT NULL REFERENCES tbProveedor (id_proveedor),
    precio_costo  NUMERIC(10,2) NOT NULL,
    PRIMARY KEY (id_producto, id_proveedor),
    CONSTRAINT ck_producto_proveedor_costo CHECK (precio_costo > 0)
);

-- =====================================================================
-- 4. OFERTAS
--    oferta_detalle_alcance con id_referenciado se reemplaza por dos
--    tablas puente con FK reales (integridad referencial completa).
-- =====================================================================
CREATE TABLE tbTipo_descuento (
    id_tipo_descuento  SERIAL PRIMARY KEY,
    nombre             VARCHAR(30) NOT NULL UNIQUE,            -- 'PORCENTAJE', 'MONTO'
    descripcion        VARCHAR(255)
);

CREATE TABLE tbOferta (
    id_oferta          SERIAL PRIMARY KEY,
    nombre_oferta      VARCHAR(100) NOT NULL,
    id_tipo_descuento  INTEGER NOT NULL REFERENCES tbTipo_descuento (id_tipo_descuento),
    valor_descuento    NUMERIC(10,2) NOT NULL,
    fecha_inicio       DATE NOT NULL,
    fecha_fin          DATE NOT NULL,
    estado             BOOLEAN NOT NULL DEFAULT TRUE,          -- TRUE = activa
    CONSTRAINT ck_oferta_valor  CHECK (valor_descuento > 0),
    CONSTRAINT ck_oferta_fechas CHECK (fecha_fin >= fecha_inicio)
);

-- La oferta aplica a productos específicos...
CREATE TABLE tbOferta_producto (
    id_oferta    INTEGER NOT NULL REFERENCES tbOferta (id_oferta) ON DELETE CASCADE,
    id_producto  INTEGER NOT NULL REFERENCES tbProductos (id_producto),
    PRIMARY KEY (id_oferta, id_producto)
);

-- ...o a categorías completas
CREATE TABLE tbOferta_categoria (
    id_oferta     INTEGER NOT NULL REFERENCES tbOferta (id_oferta) ON DELETE CASCADE,
    id_categoria  INTEGER NOT NULL REFERENCES tbCategoria (id_categoria),
    PRIMARY KEY (id_oferta, id_categoria)
);

-- =====================================================================
-- 5. VENTAS
-- =====================================================================
CREATE TABLE tbEstado_ventas (
    id_estado_ventas  SERIAL PRIMARY KEY,
    nombre            VARCHAR(30) NOT NULL UNIQUE,             -- 'COMPLETADA', 'ANULADA'
    descripcion       VARCHAR(255)
);

-- id_oferta aquí = oferta aplicada a toda la venta (descuento_global).
-- Las ofertas por producto van en tbDetalle_venta.
CREATE TABLE tbVentas (
    id_venta          SERIAL PRIMARY KEY,
    fecha             TIMESTAMP NOT NULL DEFAULT now(),
    id_cliente        INTEGER NOT NULL REFERENCES tbClientes (id_cliente),
    id_oferta         INTEGER REFERENCES tbOferta (id_oferta),
    subtotal_general  NUMERIC(10,2) NOT NULL DEFAULT 0,
    descuento_global  NUMERIC(10,2) NOT NULL DEFAULT 0,
    total             NUMERIC(10,2) NOT NULL DEFAULT 0,
    id_estado_ventas  INTEGER NOT NULL REFERENCES tbEstado_ventas (id_estado_ventas),
    CONSTRAINT ck_venta_montos    CHECK (subtotal_general >= 0 AND descuento_global >= 0),
    CONSTRAINT ck_venta_descuento CHECK (descuento_global <= subtotal_general),
    CONSTRAINT ck_venta_total     CHECK (total = subtotal_general - descuento_global),
    CONSTRAINT ck_venta_oferta    CHECK (id_oferta IS NOT NULL OR descuento_global = 0)
);

-- precio_unitario es el precio al momento de la venta (dato histórico).
-- subtotal se calcula solo (columna generada).
CREATE TABLE tbDetalle_venta (
    id_detalle_venta    SERIAL PRIMARY KEY,
    id_venta            INTEGER NOT NULL REFERENCES tbVentas (id_venta),
    id_producto         INTEGER NOT NULL REFERENCES tbProductos (id_producto),
    id_oferta           INTEGER REFERENCES tbOferta (id_oferta),
    cantidad            INTEGER NOT NULL,
    precio_unitario     NUMERIC(10,2) NOT NULL,
    descuento_aplicado  NUMERIC(10,2) NOT NULL DEFAULT 0,      -- descuento por unidad
    subtotal            NUMERIC(10,2) GENERATED ALWAYS AS
                        (cantidad * (precio_unitario - descuento_aplicado)) STORED,
    CONSTRAINT uq_detalle_venta      UNIQUE (id_venta, id_producto),
    CONSTRAINT ck_detalle_cantidad   CHECK (cantidad > 0),
    CONSTRAINT ck_detalle_precio     CHECK (precio_unitario > 0),
    CONSTRAINT ck_detalle_descuento  CHECK (descuento_aplicado >= 0
                                            AND descuento_aplicado <= precio_unitario),
    CONSTRAINT ck_detalle_oferta     CHECK (id_oferta IS NOT NULL OR descuento_aplicado = 0)
);

-- =====================================================================
-- 6. COMPRAS (entradas de inventario)
-- =====================================================================
CREATE TABLE tbEstado_compra (
    id_estado_compra  SERIAL PRIMARY KEY,
    nombre            VARCHAR(30) NOT NULL UNIQUE              -- 'PENDIENTE', 'RECIBIDA', 'ANULADA'
);

CREATE TABLE tbCompras (
    id_compra         SERIAL PRIMARY KEY,
    id_proveedor      INTEGER NOT NULL REFERENCES tbProveedor (id_proveedor),
    numero_factura    VARCHAR(30) NOT NULL,
    fecha             TIMESTAMP NOT NULL DEFAULT now(),
    id_estado_compra  INTEGER NOT NULL REFERENCES tbEstado_compra (id_estado_compra),
    CONSTRAINT uq_compra_factura UNIQUE (id_proveedor, numero_factura)
);

CREATE TABLE tbDetalle_compras (
    id_detalle_compra  SERIAL PRIMARY KEY,
    id_compra          INTEGER NOT NULL REFERENCES tbCompras (id_compra) ON DELETE CASCADE,
    id_producto        INTEGER NOT NULL REFERENCES tbProductos (id_producto),
    cantidad           INTEGER NOT NULL,
    costo_unitario     NUMERIC(10,2) NOT NULL,
    CONSTRAINT uq_detalle_compra       UNIQUE (id_compra, id_producto),
    CONSTRAINT ck_detalle_compra_cant  CHECK (cantidad > 0),
    CONSTRAINT ck_detalle_compra_costo CHECK (costo_unitario > 0)
);

-- =====================================================================
-- 7. DEVOLUCIONES
-- =====================================================================
CREATE TABLE tbMotivos_devolucion (
    id_motivo_devolucion  SERIAL PRIMARY KEY,
    nombre                VARCHAR(50) NOT NULL UNIQUE,
    descripcion           VARCHAR(255)
);

CREATE TABLE tbTipo_reembolso (
    id_tipo_reembolso  SERIAL PRIMARY KEY,
    nombre             VARCHAR(50) NOT NULL UNIQUE,
    descripcion        VARCHAR(255)
);

CREATE TABLE tbEstado_devolucion (
    id_estado_devolucion  SERIAL PRIMARY KEY,
    nombre                VARCHAR(30) NOT NULL UNIQUE          -- 'SOLICITADA', 'APROBADA', 'RECHAZADA'
);

CREATE TABLE tbDevoluciones (
    id_devolucion         SERIAL PRIMARY KEY,
    id_venta              INTEGER NOT NULL REFERENCES tbVentas (id_venta),
    id_tipo_reembolso     INTEGER NOT NULL REFERENCES tbTipo_reembolso (id_tipo_reembolso),
    id_motivo_devolucion  INTEGER NOT NULL REFERENCES tbMotivos_devolucion (id_motivo_devolucion),
    fecha_devolucion      TIMESTAMP NOT NULL DEFAULT now(),
    id_estado_devolucion  INTEGER NOT NULL REFERENCES tbEstado_devolucion (id_estado_devolucion)
);

-- Se referencia la línea vendida, no el producto: solo se puede devolver
-- lo que realmente se vendió en esa venta.
CREATE TABLE tbDetalle_devolucion (
    id_detalle_devolucion  SERIAL PRIMARY KEY,
    id_devolucion          INTEGER NOT NULL REFERENCES tbDevoluciones (id_devolucion) ON DELETE CASCADE,
    id_detalle_venta       INTEGER NOT NULL REFERENCES tbDetalle_venta (id_detalle_venta),
    cantidad               INTEGER NOT NULL,
    monto_reembolso        NUMERIC(10,2) NOT NULL,
    reingresa_stock        BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT uq_detalle_devolucion       UNIQUE (id_devolucion, id_detalle_venta),
    CONSTRAINT ck_detalle_devolucion_cant  CHECK (cantidad > 0),
    CONSTRAINT ck_detalle_devolucion_monto CHECK (monto_reembolso >= 0)
);

-- =====================================================================
-- 8. KARDEX (movimientos de inventario)
--    Cada movimiento apunta al documento que lo originó (o a ninguno si
--    es un ajuste). Como máximo uno de los tres puede tener valor.
-- =====================================================================
CREATE TABLE tbTipo_movimiento (
    id_tipo_movimiento  SERIAL PRIMARY KEY,
    nombre              VARCHAR(30) NOT NULL UNIQUE
);

CREATE TABLE tbMovimientos_inventario (
    id_movimiento          SERIAL PRIMARY KEY,
    id_producto            INTEGER NOT NULL REFERENCES tbProductos (id_producto),
    id_tipo_movimiento     INTEGER NOT NULL REFERENCES tbTipo_movimiento (id_tipo_movimiento),
    fecha                  TIMESTAMP NOT NULL DEFAULT now(),
    cantidad               INTEGER NOT NULL,                   -- + entra, - sale
    costo_unitario         NUMERIC(10,2) NOT NULL,
    stock_resultante       INTEGER NOT NULL,
    id_detalle_compra      INTEGER REFERENCES tbDetalle_compras (id_detalle_compra),
    id_detalle_venta       INTEGER REFERENCES tbDetalle_venta (id_detalle_venta),
    id_detalle_devolucion  INTEGER REFERENCES tbDetalle_devolucion (id_detalle_devolucion),
    observacion            VARCHAR(255),
    CONSTRAINT ck_mov_cantidad   CHECK (cantidad <> 0),
    CONSTRAINT ck_mov_costo      CHECK (costo_unitario >= 0),
    CONSTRAINT ck_mov_stock      CHECK (stock_resultante >= 0),
    CONSTRAINT ck_mov_documento  CHECK (num_nonnulls(id_detalle_compra, id_detalle_venta,
                                                     id_detalle_devolucion) <= 1),
    -- un ajuste (sin documento) debe explicar el motivo
    CONSTRAINT ck_mov_ajuste     CHECK (num_nonnulls(id_detalle_compra, id_detalle_venta,
                                                     id_detalle_devolucion) = 1
                                        OR observacion IS NOT NULL)
);

-- =====================================================================
-- 9. ENVÍOS
--    Los catálogos no llevan FK. tbEnvios concentra las relaciones.
-- =====================================================================
CREATE TABLE tbTipos_envios (
    id_tipo_envio   SERIAL PRIMARY KEY,
    nombre          VARCHAR(50) NOT NULL UNIQUE,               -- 'ESTANDAR', 'EXPRESS'
    descripcion     VARCHAR(255),
    dias_estimados  SMALLINT NOT NULL,
    costo           NUMERIC(10,2) NOT NULL,                    -- tarifa vigente
    estado          BOOLEAN NOT NULL DEFAULT TRUE,             -- TRUE = disponible
    CONSTRAINT ck_tipo_envio_dias  CHECK (dias_estimados > 0),
    CONSTRAINT ck_tipo_envio_costo CHECK (costo >= 0)
);

CREATE TABLE tbProveedores_envios (
    id_proveedor_envio  SERIAL PRIMARY KEY,
    nombre              VARCHAR(100) NOT NULL UNIQUE,
    telefono            VARCHAR(15),
    activo              BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE tbEstado_envio (
    id_estado_envio  SERIAL PRIMARY KEY,
    nombre           VARCHAR(30) NOT NULL UNIQUE,              -- 'PENDIENTE', 'EN_CAMINO'...
    descripcion      VARCHAR(255)
);

CREATE TABLE tbEnvios (
    id_envio            SERIAL PRIMARY KEY,
    id_venta            INTEGER NOT NULL UNIQUE REFERENCES tbVentas (id_venta),
    id_proveedor_envio  INTEGER NOT NULL REFERENCES tbProveedores_envios (id_proveedor_envio),
    id_tipo_envio       INTEGER NOT NULL REFERENCES tbTipos_envios (id_tipo_envio),
    id_estado_envio     INTEGER NOT NULL REFERENCES tbEstado_envio (id_estado_envio),
    id_distrito         INTEGER NOT NULL REFERENCES tbDistritos (id_distrito),
    direccion_entrega   VARCHAR(255) NOT NULL,
    nombre_receptor     VARCHAR(120) NOT NULL,
    telefono_receptor   VARCHAR(15)  NOT NULL,
    costo_envio         NUMERIC(10,2) NOT NULL,                -- lo cobrado en este envío
    fecha_estimada      DATE NOT NULL,
    fecha_de_envio      TIMESTAMP,
    fecha_entrega       TIMESTAMP,
    CONSTRAINT ck_envio_costo  CHECK (costo_envio >= 0),
    CONSTRAINT ck_envio_fechas CHECK (fecha_entrega >= fecha_de_envio)
);

CREATE TABLE tbHistorial_envio (
    id_historial     SERIAL PRIMARY KEY,
    id_envio         INTEGER NOT NULL REFERENCES tbEnvios (id_envio) ON DELETE CASCADE,
    id_estado_envio  INTEGER NOT NULL REFERENCES tbEstado_envio (id_estado_envio),
    fecha            TIMESTAMP NOT NULL DEFAULT now(),
    observacion      VARCHAR(255)
);

-- =====================================================================
-- 10. ÍNDICES
--     PostgreSQL indexa solo PK y UNIQUE; las FK se indexan aparte
--     cuando se usan en JOIN o filtros frecuentes.
-- =====================================================================
CREATE INDEX ix_clientes_distrito        ON tbClientes (id_distrito);
CREATE INDEX ix_productos_editorial      ON tbProductos (id_editorial);        -- ventas por editorial
CREATE INDEX ix_productos_nombre         ON tbProductos (lower(nombre) text_pattern_ops); -- búsqueda por título
CREATE INDEX ix_producto_autor_autor     ON producto_autor (id_autor);         -- ventas por autor
CREATE INDEX ix_producto_categoria_cat   ON producto_categoria (id_categoria);
CREATE INDEX ix_producto_proveedor_prov  ON producto_proveedor (id_proveedor);
CREATE INDEX ix_ventas_fecha             ON tbVentas (fecha);                  -- ETL y reportes por período
CREATE INDEX ix_ventas_cliente_fecha     ON tbVentas (id_cliente, fecha DESC); -- historial del cliente (RFM)
CREATE INDEX ix_detalle_venta_producto   ON tbDetalle_venta (id_producto);     -- productos más vendidos
CREATE INDEX ix_detalle_compras_producto ON tbDetalle_compras (id_producto);
CREATE INDEX ix_mov_producto_fecha       ON tbMovimientos_inventario (id_producto, fecha); -- stock a una fecha (rotación)
CREATE INDEX ix_devoluciones_venta       ON tbDevoluciones (id_venta);
CREATE INDEX ix_detalle_devol_det_venta  ON tbDetalle_devolucion (id_detalle_venta);
CREATE INDEX ix_envios_proveedor         ON tbEnvios (id_proveedor_envio);
CREATE INDEX ix_envios_distrito          ON tbEnvios (id_distrito);
CREATE INDEX ix_historial_envio          ON tbHistorial_envio (id_envio, fecha);

-- =====================================================================
-- 11. DATOS DE LOS CATÁLOGOS
-- =====================================================================
INSERT INTO tbEstado_ventas (nombre, descripcion) VALUES
    ('COMPLETADA', 'Venta registrada y cobrada'),
    ('ANULADA',    'Venta cancelada; el stock se repone');

INSERT INTO tbTipo_descuento (nombre, descripcion) VALUES
    ('PORCENTAJE', 'Descuento como porcentaje del precio'),
    ('MONTO',      'Descuento de un monto fijo en dólares');

INSERT INTO tbEstado_compra (nombre) VALUES ('PENDIENTE'), ('RECIBIDA'), ('ANULADA');

INSERT INTO tbTipo_movimiento (nombre) VALUES
    ('COMPRA'), ('VENTA'), ('DEVOLUCION'), ('ANULACION_VENTA'),
    ('AJUSTE_POSITIVO'), ('AJUSTE_NEGATIVO');

INSERT INTO tbMotivos_devolucion (nombre, descripcion) VALUES
    ('DANADO',       'El producto llegó en mal estado'),
    ('ERROR_PEDIDO', 'Se entregó un producto distinto'),
    ('NO_DESEADO',   'El cliente ya no lo quiere'),
    ('OTRO',         'Otro motivo');

INSERT INTO tbTipo_reembolso (nombre) VALUES ('EFECTIVO'), ('CREDITO_TIENDA'), ('CAMBIO');

INSERT INTO tbEstado_devolucion (nombre) VALUES ('SOLICITADA'), ('APROBADA'), ('RECHAZADA');

INSERT INTO tbEstado_envio (nombre, descripcion) VALUES
    ('PENDIENTE', 'Registrado, aún no sale'),
    ('EN_CAMINO', 'En manos del repartidor'),
    ('ENTREGADO', 'Recibido por el cliente'),
    ('FALLIDO',   'No se pudo entregar'),
    ('DEVUELTO',  'Regresó a la tienda');

INSERT INTO tbTipos_envios (nombre, dias_estimados, costo) VALUES
    ('ESTANDAR', 3, 3.00),
    ('EXPRESS',  1, 6.00);

COMMIT;
