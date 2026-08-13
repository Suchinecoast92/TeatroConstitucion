<?php
session_start();
header('Content-Type: application/json');

// Verificar autenticación - Accesible para todos los usuarios logueados
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'No autenticado']);
    exit();
}

require_once __DIR__ . '/../../transacciones_helper.php';

$id_transaccion = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_transaccion <= 0) {
    echo json_encode(['error' => 'ID de transacción inválido']);
    exit();
}

$sql = "SELECT t.id_transaccion, t.accion, t.descripcion, t.fecha_hora, t.datos_json, u.nombre, u.apellido
        FROM transacciones t
        JOIN usuarios u ON t.id_usuario = u.id_usuario
        WHERE t.id_transaccion = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id_transaccion);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Transacción no encontrada']);
    $stmt->close();
    $conn->close();
    exit();
}

$transaccion = $result->fetch_assoc();
$stmt->close();

// Parsear datos JSON si existen
$datos_adicionales = [];
if (!empty($transaccion['datos_json'])) {
    $datos_adicionales = json_decode($transaccion['datos_json'], true);
}

// Si es evento_crear, obtener detalles del evento
$evento_detalles = null;
if ($transaccion['accion'] === 'evento_crear' && !empty($datos_adicionales['id_evento'])) {
    $id_evento = (int)$datos_adicionales['id_evento'];
    
    $sql_evento = "SELECT id_evento, titulo, descripcion, tipo, inicio_venta, cierre_venta, imagen, finalizado
                   FROM evento WHERE id_evento = ?";
    
    $stmt_evento = $conn->prepare($sql_evento);
    $stmt_evento->bind_param('i', $id_evento);
    $stmt_evento->execute();
    $result_evento = $stmt_evento->get_result();
    
    if ($result_evento->num_rows > 0) {
        $evento_detalles = $result_evento->fetch_assoc();
    }
    
    $stmt_evento->close();
}

$conn->close();

echo json_encode([
    'success' => true,
    'transaccion' => $transaccion,
    'datos_adicionales' => $datos_adicionales,
    'evento_detalles' => $evento_detalles
]);
?>
