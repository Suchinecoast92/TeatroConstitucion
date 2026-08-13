-- ===========================================================================
-- Limpieza automática de reservas_temporales
-- ===========================================================================
-- Crea un EVENT en MySQL que cada 60 segundos borra las reservas que ya
-- expiraron. Garantiza que aunque NADIE esté conectado al sistema (ni admin
-- ni clientes), la tabla no acumule basura.
-- ===========================================================================

USE trt_25;

-- Asegurar que el scheduler esté activo (sólo se aplica si tienes permisos
-- SUPER. Si el WAMP no lo tiene, los SSE y check_conexion siguen limpiando).
SET GLOBAL event_scheduler = ON;

DROP EVENT IF EXISTS limpiar_reservas_temporales;

CREATE EVENT limpiar_reservas_temporales
    ON SCHEDULE EVERY 1 MINUTE
    STARTS CURRENT_TIMESTAMP
    ON COMPLETION PRESERVE
    DO
        DELETE FROM reservas_temporales WHERE expira_en < NOW();

-- Verificar
SHOW EVENTS LIKE 'limpiar_reservas_temporales';
