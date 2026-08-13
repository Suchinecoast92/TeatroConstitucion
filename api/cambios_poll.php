<?php
/**
 * API de Polling para Cambios
 * ===========================
 * Endpoint alternativo para navegadores sin soporte SSE.
 * Retorna cambios desde un ID específico.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../conexion.php';

$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
$eventoId = isset($_GET['id_evento']) ? (int)$_GET['id_evento'] : null;

try {
    // Verificar si la tabla existe
    $tableCheck = $conn->query("SHOW TABLES LIKE 'cambios_log'");
    if ($tableCheck->num_rows === 0) {
        echo json_encode([
            'success' => true,
            'cambios' => [],
            'last_id' => 0
        ]);
        exit;
    }
    
    // Construir query
    $sql = "SELECT * FROM cambios_log WHERE id_cambio > ?";
    $params = [$lastId];
    $types = "i";
    
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
    $maxId = $lastId;
    
    while ($row = $result->fetch_assoc()) {
        $cambios[] = [
            'id' => (int)$row['id_cambio'],
            'tipo' => $row['tipo_cambio'],
            'id_evento' => $row['id_evento'] ? (int)$row['id_evento'] : null,
            'id_funcion' => $row['id_funcion'] ? (int)$row['id_funcion'] : null,
            'datos' => json_decode($row['datos'], true),
            'fecha' => $row['fecha_cambio']
        ];
        $maxId = max($maxId, (int)$row['id_cambio']);
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'cambios' => $cambios,
        'last_id' => $maxId,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
