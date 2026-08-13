USE trt_25;

DELETE FROM reservas_temporales WHERE session_id LIKE 'TEST-%';

-- Reserva VIGENTE (no expirada)
INSERT INTO reservas_temporales
    (codigo_asiento, id_evento, id_funcion, origen, session_id, expira_en)
VALUES
    ('TEST-B1', 4, 0, 'online', 'TEST-actual', DATE_ADD(NOW(), INTERVAL 5 MINUTE));

-- Otra sesión intenta reservar el mismo asiento — NO debe sobrescribir
INSERT INTO reservas_temporales
    (codigo_asiento, id_evento, id_funcion, origen, session_id, cliente_info, expira_en)
VALUES
    ('TEST-B1', 4, 0, 'local', 'TEST-intruso', 'Intruso', DATE_ADD(NOW(), INTERVAL 5 MINUTE))
ON DUPLICATE KEY UPDATE
    origen       = IF(expira_en <= NOW(), VALUES(origen),       origen),
    session_id   = IF(expira_en <= NOW(), VALUES(session_id),   session_id),
    cliente_info = IF(expira_en <= NOW(), VALUES(cliente_info), cliente_info),
    expira_en    = IF(expira_en <= NOW(), VALUES(expira_en),    expira_en);

-- Debe mostrar TEST-actual (la original)
SELECT codigo_asiento, session_id, origen
FROM reservas_temporales WHERE codigo_asiento = 'TEST-B1';

DELETE FROM reservas_temporales WHERE session_id LIKE 'TEST-%';
