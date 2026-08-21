-- Migración reversible Fase 2: órdenes online
-- Uso: mysql -u root trt_25 < sql/migracion_ordenes_online.sql

CREATE TABLE IF NOT EXISTS ordenes (
    id_orden INT AUTO_INCREMENT PRIMARY KEY,
    codigo_publico VARCHAR(24) NOT NULL,
    id_evento INT NOT NULL,
    id_funcion INT NOT NULL,
    session_id VARCHAR(100) NOT NULL,
    email VARCHAR(180) NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    telefono VARCHAR(40) NULL,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    estado ENUM('pendiente','pagada','fallida','expirada','cancelada','reembolsada') NOT NULL DEFAULT 'pendiente',
    expira_en DATETIME NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_codigo_publico (codigo_publico),
    KEY idx_orden_evento_funcion (id_evento, id_funcion),
    KEY idx_orden_session (session_id),
    KEY idx_orden_estado_expira (estado, expira_en),
    CONSTRAINT fk_orden_evento FOREIGN KEY (id_evento) REFERENCES evento (id_evento),
    CONSTRAINT fk_orden_funcion FOREIGN KEY (id_funcion) REFERENCES funciones (id_funcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orden_items (
    id_item INT AUTO_INCREMENT PRIMARY KEY,
    id_orden INT NOT NULL,
    codigo_asiento VARCHAR(20) NOT NULL,
    id_categoria INT NULL,
    tipo_boleto VARCHAR(30) NOT NULL DEFAULT 'adulto',
    precio_base DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    descuento_aplicado DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    precio_final DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    id_promocion INT NULL,
    id_boleto INT NULL,
    KEY idx_item_orden (id_orden),
    KEY idx_item_asiento (codigo_asiento),
    CONSTRAINT fk_item_orden FOREIGN KEY (id_orden) REFERENCES ordenes (id_orden) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rollback (ejecutar manualmente si hace falta):
-- DROP TABLE IF EXISTS orden_items;
-- DROP TABLE IF EXISTS ordenes;
