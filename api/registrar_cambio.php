<?php
/**
 * Helper para Registrar Cambios en Tiempo Real
 * =============================================
 * Incluir este archivo y usar registrar_cambio() para notificar cambios.
 */

if (!function_exists('registrar_cambio')) {
    /**
     * Registra un cambio en la tabla cambios_log para notificación en tiempo real
     * 
     * @param string $tipo Tipo de cambio: venta, cancelacion, evento, categoria, descuento, mapa, funcion, precio
     * @param int|null $id_evento ID del evento afectado
     * @param int|null $id_funcion ID de la función afectada
     * @param array $datos Datos adicionales del cambio
     * @return bool True si se registró correctamente
     */
    function registrar_cambio($tipo, $id_evento = null, $id_funcion = null, $datos = []) {
        global $conn;
        
        // Verificar que la conexión existe
        if (!$conn || $conn->connect_error) {
            return false;
        }
        
        // Verificar si la tabla existe
        $tableCheck = $conn->query("SHOW TABLES LIKE 'cambios_log'");
        if ($tableCheck->num_rows === 0) {
            // Crear tabla si no existe
            $conn->query("
                CREATE TABLE IF NOT EXISTS cambios_log (
                    id_cambio INT AUTO_INCREMENT PRIMARY KEY,
                    tipo_cambio ENUM('venta', 'cancelacion', 'evento', 'categoria', 'descuento', 'mapa', 'funcion', 'precio') NOT NULL,
                    id_evento INT NULL,
                    id_funcion INT NULL,
                    datos JSON NULL,
                    fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    procesado TINYINT(1) DEFAULT 0,
                    INDEX idx_fecha (fecha_cambio),
                    INDEX idx_tipo (tipo_cambio),
                    INDEX idx_evento (id_evento)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        // Preparar datos JSON
        $datos_json = !empty($datos) ? json_encode($datos, JSON_UNESCAPED_UNICODE) : null;
        
        // Convertir nulls a valores apropiados para bind_param
        $id_evento_val = $id_evento !== null ? (int)$id_evento : null;
        $id_funcion_val = $id_funcion !== null ? (int)$id_funcion : null;
        
        // Insertar cambio - usando query directa para manejar NULLs
        $tipo_escaped = $conn->real_escape_string($tipo);
        $id_evento_sql = $id_evento_val !== null ? $id_evento_val : 'NULL';
        $id_funcion_sql = $id_funcion_val !== null ? $id_funcion_val : 'NULL';
        $datos_sql = $datos_json !== null ? "'" . $conn->real_escape_string($datos_json) . "'" : 'NULL';
        
        $sql = "INSERT INTO cambios_log (tipo_cambio, id_evento, id_funcion, datos) 
                VALUES ('$tipo_escaped', $id_evento_sql, $id_funcion_sql, $datos_sql)";
        
        $result = $conn->query($sql);

        // Limpiar registros antiguos (más de 1 hora)
        $conn->query("DELETE FROM cambios_log WHERE fecha_cambio < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        
        return $result ? true : false;
    }
    
    /**
     * Emitir cambio también via localStorage para pestañas locales
     * Útil cuando se quiere notificar inmediatamente sin esperar SSE
     */
    function emitir_cambio_js($tipo, $id_evento = null, $datos = []) {
        $payload = [
            'type' => $tipo,
            'id_evento' => $id_evento,
            'datos' => $datos,
            'timestamp' => time() * 1000 // JavaScript timestamp
        ];
        
        // Retornar script para inyectar en respuesta
        return '<script>
            localStorage.setItem("teatro_sync_' . $tipo . '", JSON.stringify(' . json_encode($payload) . '));
            setTimeout(function() { localStorage.removeItem("teatro_sync_' . $tipo . '"); }, 1000);
        </script>';
    }
}
?>
