USE trt_25;

-- Limpiar tests previos
DELETE FROM reservas_temporales WHERE session_id LIKE 'TEST-%';

-- Insertar una reserva ya expirada hace 5 minutos
INSERT INTO reservas_temporales
    (codigo_asiento, id_evento, id_funcion, origen, session_id, expira_en)
VALUES
    ('TEST-A1', 4, 0, 'online', 'TEST-vieja', DATE_SUB(NOW(), INTERVAL 5 MINUTE));

-- Verificar que existe
SELECT id_reserva, codigo_asiento, session_id, expira_en,
       (expira_en < NOW()) AS expirada
FROM reservas_temporales WHERE codigo_asiento = 'TEST-A1';

-- Ahora simular una NUEVA reserva del mismo asiento por OTRA sesión
-- usando ON DUPLICATE KEY UPDATE (lo que hace el helper)
INSERT INTO reservas_temporales
    (codigo_asiento, id_evento, id_funcion, origen, session_id, cliente_info, expira_en)
VALUES
    ('TEST-A1', 4, 0, 'local', 'TEST-nueva', 'Cliente nuevo', DATE_ADD(NOW(), INTERVAL 5 MINUTE))
ON DUPLICATE KEY UPDATE
    origen       = IF(expira_en <= NOW(), VALUES(origen),       origen),
    session_id   = IF(expira_en <= NOW(), VALUES(session_id),   session_id),
    cliente_info = IF(expira_en <= NOW(), VALUES(cliente_info), cliente_info),
    expira_en    = IF(expira_en <= NOW(), VALUES(expira_en),    expira_en);

-- Debería mostrar la NUEVA sesión (la vieja fue sobrescrita por estar expirada)
SELECT id_reserva, codigo_asiento, session_id, expira_en, origen
FROM reservas_temporales WHERE codigo_asiento = 'TEST-A1';

-- Limpiar
DELETE FROM reservas_temporales WHERE session_id LIKE 'TEST-%';
