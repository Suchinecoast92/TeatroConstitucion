-- ============================================================
-- BASE DE DATOS DE RESPALDO - trt_25_backup
-- ============================================================
-- Almacena copias de seguridad inmutables de ventas y datos
-- relevantes provenientes de las bases LOCAL (trt_25) y ONLINE
-- (trt_25_online). Sirve para auditoría y recuperación ante
-- desastres. NUNCA se borra registros de aquí.
-- ============================================================

CREATE DATABASE IF NOT EXISTS trt_25_backup
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE trt_25_backup;

-- ----------------------------------------------------------
-- Eventos respaldados (todos los creados/editados)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS evento_backup (
    id_backup INT AUTO_INCREMENT PRIMARY KEY,
    origen ENUM('local','online') NOT NULL,
    id_evento_origen INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    tipo TINYINT,
    imagen LONGBLOB,
    imagen_mime VARCHAR(50),
    mapa_json LONGTEXT,
    finalizado TINYINT(1) DEFAULT 0,
    snapshot_json LONGTEXT COMMENT 'JSON con datos completos al momento del respaldo',
    fecha_respaldo DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_origen (origen, id_evento_origen),
    INDEX idx_fecha (fecha_respaldo)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Boletos vendidos respaldados (inmutables)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS boleto_backup (
    id_backup INT AUTO_INCREMENT PRIMARY KEY,
    origen ENUM('local','online') NOT NULL,
    id_boleto_origen INT NOT NULL,
    id_evento_origen INT NOT NULL,
    id_funcion_origen INT NULL,
    codigo_unico VARCHAR(50) NOT NULL,
    codigo_asiento VARCHAR(20),
    titulo_evento VARCHAR(255),
    fecha_funcion DATETIME,
    categoria VARCHAR(100),
    precio_base DECIMAL(10,2),
    precio_final DECIMAL(10,2),
    tipo_boleto VARCHAR(30),
    estatus TINYINT,
    qr_code LONGBLOB,
    fecha_compra DATETIME,
    fecha_respaldo DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo_unico),
    INDEX idx_origen (origen, id_boleto_origen),
    INDEX idx_evento (id_evento_origen)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Registro detallado de ventas (lugar, método de pago, etc.)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS venta_detallada (
    id_venta INT AUTO_INCREMENT PRIMARY KEY,
    origen ENUM('local','online') NOT NULL,
    id_evento INT NOT NULL,
    id_funcion INT NULL,
    titulo_evento VARCHAR(255),
    fecha_funcion DATETIME,

    -- Cliente
    cliente_nombre VARCHAR(150),
    cliente_email VARCHAR(150),
    cliente_telefono VARCHAR(30),

    -- Vendedor / lugar
    id_usuario_vendedor INT NULL,
    nombre_vendedor VARCHAR(150),
    lugar_venta VARCHAR(150) DEFAULT 'Taquilla' COMMENT 'Taquilla, Online, Telefono, Caja2, etc.',

    -- Método de pago
    metodo_pago ENUM('efectivo','tarjeta','transferencia','online','cortesia','otro') DEFAULT 'efectivo',
    tarjeta_terminacion VARCHAR(8) NULL COMMENT 'Últimos 4 dígitos',
    referencia_pago VARCHAR(100) NULL COMMENT 'Folio de transacción / autorización',
    cuenta_destino VARCHAR(100) NULL COMMENT 'Cuenta donde se recibió el pago',

    -- Totales
    cantidad_boletos INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    descuento_aplicado DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,

    -- Boletos
    asientos JSON COMMENT 'Lista de asientos vendidos',
    codigos_boletos JSON COMMENT 'Lista de codigos_unicos',
    boletos_ids JSON COMMENT 'Lista de id_boleto en BD origen',

    -- Trazabilidad
    notas TEXT,
    ip_cliente VARCHAR(45) NULL,
    user_agent TEXT NULL,
    fecha_venta DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_respaldo DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_fecha (fecha_venta),
    INDEX idx_evento (id_evento),
    INDEX idx_metodo (metodo_pago),
    INDEX idx_origen (origen),
    INDEX idx_email (cliente_email)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Log de sincronización
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS sync_log (
    id_log INT AUTO_INCREMENT PRIMARY KEY,
    direccion ENUM('local_to_online','online_to_local','to_backup') NOT NULL,
    tipo ENUM('evento','boleto','venta','funcion','categoria','asiento') NOT NULL,
    id_origen INT NULL,
    accion ENUM('insert','update','delete','skip','error') NOT NULL,
    mensaje TEXT,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fecha (fecha),
    INDEX idx_direccion (direccion),
    INDEX idx_accion (accion)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Bloqueos de borrado (boletos protegidos)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS proteccion_borrado (
    id_proteccion INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('evento','boleto','funcion') NOT NULL,
    id_origen INT NOT NULL,
    razon VARCHAR(255) DEFAULT 'Boleto vendido - protegido',
    bloqueado_por INT NULL COMMENT 'id_usuario que bloqueó',
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_tipo_id (tipo, id_origen)
) ENGINE=InnoDB;
