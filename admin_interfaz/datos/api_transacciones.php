<?php
session_start();
header('Content-Type: application/json');

// Verificar autenticación - Accesible para todos los usuarios logueados
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'No autenticado']);
    exit();
}

require_once __DIR__ . '/../../transacciones_helper.php';

$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$ultima_id = isset($_GET['ultima_id']) ? (int)$_GET['ultima_id'] : 0;

$sql_base = "SELECT t.id_transaccion, t.accion, t.descripcion, t.fecha_hora, u.nombre, u.apellido
             FROM transacciones t
             JOIN usuarios u ON t.id_usuario = u.id_usuario";

$conditions = [];
$params = [];
$types = '';

// Filtro por ID (para obtener solo las nuevas)
if ($ultima_id > 0) {
    $conditions[] = "t.id_transaccion > ?";
    $params[] = $ultima_id;
    $types .= 'i';
}

// Filtros de fecha
if ($fecha_desde && $fecha_hasta) {
    $conditions[] = "t.fecha_hora >= ?";
    $conditions[] = "t.fecha_hora <= ?";
    $params[] = $fecha_desde . ' 00:00:00';
    $params[] = $fecha_hasta . ' 23:59:59';
    $types .= 'ss';
} elseif ($fecha_desde) {
    $conditions[] = "t.fecha_hora >= ?";
    $params[] = $fecha_desde . ' 00:00:00';
    $types .= 's';
} elseif ($fecha_hasta) {
    $conditions[] = "t.fecha_hora <= ?";
    $params[] = $fecha_hasta . ' 23:59:59';
    $types .= 's';
}

$sql = $sql_base;
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY t.fecha_hora DESC LIMIT 500";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$transacciones = [];
while ($row = $result->fetch_assoc()) {
    $transacciones[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'transacciones' => $transacciones,
    'count' => count($transacciones)
]);
?>
