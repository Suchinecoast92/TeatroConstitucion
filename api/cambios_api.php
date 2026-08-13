<?php
/**
 * API de Cambios en Tiempo Real - Server-Sent Events
 * ===================================================
 * Este endpoint envía eventos SSE cuando hay cambios en la base de datos.
 * Los clientes se conectan y reciben notificaciones automáticas.
 */

// Deshabilitar output buffering para SSE
while (ob_get_level()) ob_end_clean();

// Headers para SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('X-Accel-Buffering: no'); // Para nginx

// Desactivar límite de tiempo
set_time_limit(0);

require_once __DIR__ . '/../conexion.php';

// Obtener parámetros
$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
$eventoId = isset($_GET['id_evento']) ? (int)$_GET['id_evento'] : null;
$funcionId = isset($_GET['id_funcion']) ? (int)$_GET['id_funcion'] : null;

// Enviar evento de conexión exitosa
echo "event: connected\n";
echo "data: " . json_encode(['status' => 'ok', 'time' => date('Y-m-d H:i:s')]) . "\n\n";
flush();

// Verificar si la tabla existe, si no crearla
$tableCheck = $conn->query("SHOW TABLES LIKE 'cambios_log'");
if ($tableCheck->num_rows === 0) {
    $conn->query("
        CREATE TABLE cambios_log (
            id_cambio INT AUTO_INCREMENT PRIMARY KEY,
            tipo_cambio ENUM('venta', 'cancelacion', 'evento', 'categoria', 'descuento', 'mapa', 'funcion', 'precio', 'reserva', 'liberacion') NOT NULL,
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
} else {
    @$conn->query("ALTER TABLE cambios_log MODIFY COLUMN tipo_cambio ENUM('venta', 'cancelacion', 'evento', 'categoria', 'descuento', 'mapa', 'funcion', 'precio', 'reserva', 'liberacion') NOT NULL");
}

// Loop principal para mantener conexión SSE
$maxTime = 30; // Máximo 30 segundos de conexión
$startTime = time();
$checkInterval = 1; // Verificar cada 2 segundos
$last_reservas_signature = '';

// Throttle de limpieza de reservas expiradas: cada 30 s
$last_cleanup = 0;

while ((time() - $startTime) < $maxTime) {
    // Verificar si cliente sigue conectado
    if (connection_aborted()) {
        break;
    }

    // Limpieza periódica de reservas expiradas (no más de 1 vez cada 30 s)
    if ((time() - $last_cleanup) >= 30) {
        @$conn->query("DELETE FROM reservas_temporales WHERE expira_en < NOW()");
        $last_cleanup = time();
    }
    
    // Construir query para obtener cambios nuevos
    $sql = "SELECT * FROM cambios_log WHERE id_cambio > ?";
    $params = [$lastId];
    $types = "i";
    
    // Filtrar por evento si se especificó
    if ($eventoId !== null) {
        $sql .= " AND (id_evento = ? OR id_evento IS NULL)";
        $params[] = $eventoId;
        $types .= "i";
    }
    
    $sql .= " ORDER BY id_cambio ASC LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cambios = [];
    while ($row = $result->fetch_assoc()) {
        $cambios[] = [
            'id' => (int)$row['id_cambio'],
            'tipo' => $row['tipo_cambio'],
            'id_evento' => $row['id_evento'] ? (int)$row['id_evento'] : null,
            'id_funcion' => $row['id_funcion'] ? (int)$row['id_funcion'] : null,
            'datos' => json_decode($row['datos'], true),
            'fecha' => $row['fecha_cambio']
        ];
        $lastId = max($lastId, (int)$row['id_cambio']);
    }
    $stmt->close();
    
    // Si hay cambios, enviarlos
    if (!empty($cambios)) {
        foreach ($cambios as $cambio) {
            echo "event: cambio\n";
            echo "data: " . json_encode($cambio) . "\n\n";
        }
        flush();
    }

    // ============================================================
    // Verificar cambios en reservas_temporales (mismo schema, BD local)
    // ============================================================
    if ($eventoId !== null) {
        $idFnLocalNorm = $funcionId ?: 0;
        $sqlR = "
            SELECT codigo_asiento, origen
            FROM reservas_temporales
            WHERE id_evento = ? AND id_funcion = ? AND expira_en > NOW()
            ORDER BY codigo_asiento";
        $params = [$eventoId, $idFnLocalNorm];
        $types  = 'ii';

        $stR = @$conn->prepare($sqlR);
        if ($stR) {
            $stR->bind_param($types, ...$params);
            $stR->execute();
            $rsR = $stR->get_result();
            $reservados = [];
            $detalle = [];
            while ($rowR = $rsR->fetch_assoc()) {
                $reservados[] = $rowR['codigo_asiento'];
                $detalle[] = [
                    'codigo' => $rowR['codigo_asiento'],
                    'origen' => $rowR['origen'] ?? 'online',
                ];
            }
            $stR->close();

            $sig = implode(',', $reservados);
            if ($sig !== $last_reservas_signature) {
                $last_reservas_signature = $sig;
                echo "event: reservas\n";
                echo "data: " . json_encode([
                    'id_evento'  => $eventoId,
                    'id_funcion' => $funcionId,
                    'asientos'   => $reservados,
                    'detalle'    => $detalle,
                ]) . "\n\n";
                flush();
            }
        }
    }

    if (empty($cambios)) {
        echo ": keepalive " . time() . "\n\n";
        flush();
    }

    sleep($checkInterval);
}

// Enviar evento de reconexión requerida
echo "event: reconnect\n";
echo "data: " . json_encode(['last_id' => $lastId]) . "\n\n";
flush();

$conn->close();
?>
