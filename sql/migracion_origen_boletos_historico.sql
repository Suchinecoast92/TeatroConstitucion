-- boletos.origen en la BD histórica: el archivado copia con INSERT … SELECT * y el panel une
-- con UNION SELECT *, así que debe tener las mismas columnas y en el mismo orden que trt_25.boletos.
-- Uso: mysql -u root trt_historico_evento < sql/migracion_origen_boletos_historico.sql
-- Reversible: ALTER TABLE boletos DROP COLUMN origen;

ALTER TABLE boletos
  ADD COLUMN origen ENUM('local','online') NOT NULL DEFAULT 'local'
  AFTER tipo_boleto;
