-- Registro de correos enviados por orden online (confirmación de compra con boletos).
-- UNIQUE(id_orden, tipo) garantiza un solo envío aunque varios procesos emitan la orden.
-- Requiere migracion_ordenes_online.sql. La app también crea la tabla si falta
-- (includes/correo/CorreoService.php).
-- Uso: mysql -u root trt_25 < sql/migracion_orden_notificaciones.sql
-- Reversible: DROP TABLE orden_notificaciones;  (solo se pierde el historial de envíos)

CREATE TABLE IF NOT EXISTS orden_notificaciones (
  id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
  id_orden INT NOT NULL,
  tipo VARCHAR(40) NOT NULL,
  estado ENUM('pendiente','enviando','enviado','fallido') NOT NULL DEFAULT 'pendiente',
  proveedor VARCHAR(20) NULL,
  destinatario VARCHAR(180) NULL,
  intentos INT NOT NULL DEFAULT 0,
  ultimo_error VARCHAR(500) NULL,
  message_id VARCHAR(190) NULL,
  enviado_en DATETIME NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_notif_orden_tipo (id_orden, tipo),
  KEY idx_notif_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
