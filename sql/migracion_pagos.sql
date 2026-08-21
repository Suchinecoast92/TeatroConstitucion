-- Migración Fase 3: pagos online
-- mysql -u root trt_25 < sql/migracion_pagos.sql

CREATE TABLE IF NOT EXISTS pagos (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_orden INT NOT NULL,
    proveedor VARCHAR(40) NOT NULL DEFAULT 'mercadopago',
    ref_externa VARCHAR(120) NOT NULL COMMENT 'preference_id o payment_id único',
    ref_pago_proveedor VARCHAR(120) NULL COMMENT 'payment id de MP cuando exista',
    estado_interno ENUM('PENDING','PAID','FAILED','REFUNDED') NOT NULL DEFAULT 'PENDING',
    monto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    moneda VARCHAR(8) NOT NULL DEFAULT 'MXN',
    init_point TEXT NULL,
    payload_resumen TEXT NULL COMMENT 'JSON resumido sin datos de tarjeta',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ref_externa (ref_externa),
    KEY idx_pago_orden (id_orden),
    KEY idx_pago_estado (estado_interno),
    KEY idx_pago_proveedor_ref (ref_pago_proveedor),
    CONSTRAINT fk_pago_orden FOREIGN KEY (id_orden) REFERENCES ordenes (id_orden) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rollback:
-- DROP TABLE IF EXISTS pagos;
