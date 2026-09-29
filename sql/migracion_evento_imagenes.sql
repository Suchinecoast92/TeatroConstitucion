-- Carteles de eventos en la BD (el disco de App Platform es efímero).
-- Uso: mysql -u root trt_25 < sql/migracion_evento_imagenes.sql
--      php sql/sincronizar_imagenes_eventos.php   (importa los archivos existentes)
-- La app también crea la tabla si falta (includes/evento_imagen_helper.php).
-- Reversible: DROP TABLE evento_imagenes;  (los archivos en disco no se tocan)

CREATE TABLE IF NOT EXISTS evento_imagenes (
  ruta VARCHAR(255) NOT NULL,
  mime VARCHAR(50) NOT NULL,
  bytes INT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  datos MEDIUMBLOB NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ruta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
