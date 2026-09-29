-- Migración Fase 5: origen local/online en boletos
-- Uso: mysql -u root trt_25 < sql/migracion_origen_boletos.sql
-- Requiere antes migracion_ordenes_online.sql (usa orden_items).
-- Aplicar también migracion_origen_boletos_historico.sql en trt_historico_evento.
-- Reversible: ALTER TABLE boletos DROP COLUMN origen;

ALTER TABLE boletos
  ADD COLUMN origen ENUM('local','online') NOT NULL DEFAULT 'local'
  AFTER tipo_boleto;

-- Marcar boletos ya ligados a órdenes online
UPDATE boletos b
INNER JOIN orden_items oi ON oi.id_boleto = b.id_boleto
SET b.origen = 'online'
WHERE b.origen = 'local';
