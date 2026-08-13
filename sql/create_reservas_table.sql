-- ===========================================================================
-- Tabla de Reservas Temporales (Holds) - Sistema Anti Doble-Venta
-- ===========================================================================
-- Almacena los asientos que un usuario está intentando comprar (online o local)
-- y que NO deben venderse a otra persona durante el TTL configurado.
--
-- Vive en la BD `trt_25` y es consultada por:
--   - vnt_interfaz/procesar_compra.php
--   - api/reservas.php
--   - teatro_online/api/comprar.php
--   - teatro_online/api/reservas.php
--
-- TTL por defecto: 5 minutos. Las reservas expiradas se ignoran y se limpian
-- automáticamente en cada llamada a la API.
--
-- Uso: mysql -u root trt_25 < create_reservas_table.sql
-- ===========================================================================

CREATE TABLE IF NOT EXISTS reservas_temporales (
    id_reserva INT AUTO_INCREMENT PRIMARY KEY,
    codigo_asiento VARCHAR(20) NOT NULL,
    id_evento INT NOT NULL,
    id_funcion INT NULL,
    origen ENUM('local','online') NOT NULL,
    session_id VARCHAR(100) NOT NULL COMMENT 'ID de sesión / token único del cliente',
    cliente_info VARCHAR(150) NULL COMMENT 'Nombre del vendedor o cliente (informativo)',
    fecha_reserva TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expira_en TIMESTAMP NOT NULL,
    UNIQUE KEY uk_asiento_funcion (codigo_asiento, id_evento, id_funcion),
    INDEX idx_expira (expira_en),
    INDEX idx_session (session_id),
    INDEX idx_evento_funcion (id_evento, id_funcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Limpiar reservas existentes que ya hayan expirado
DELETE FROM reservas_temporales WHERE expira_en < NOW();
