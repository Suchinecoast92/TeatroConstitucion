<?php
header('Content-Type: application/json');
include "../conexion.php";

$id_evento = isset($_GET['id_evento']) ? (int)$_GET['id_evento'] : 0;
$id_funcion = isset($_GET['id_funcion']) ? (int)$_GET['id_funcion'] : 0;

if ($id_evento <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de evento inválido']);
    exit;
}

// Verificar si la columna id_funcion existe en boletos
$check_column = $conn->query("SHOW COLUMNS FROM boletos LIKE 'id_funcion'");
$has_id_funcion = ($check_column && $check_column->num_rows > 0);

// Obtener asientos vendidos (solo los activos, estatus = 1)
if ($has_id_funcion && $id_funcion > 0) {
    // Si existe id_funcion y se proporcionó, filtrar por función
    $stmt = $conn->prepare("
        SELECT a.codigo_asiento 
        FROM boletos b
        INNER JOIN asientos a ON b.id_asiento = a.id_asiento
        WHERE b.id_evento = ? AND b.id_funcion = ? AND b.estatus = 1
    ");
    $stmt->bind_param("ii", $id_evento, $id_funcion);
} else {
    // Si no existe id_funcion o no se proporcionó, filtrar solo por evento
    $stmt = $conn->prepare("
        SELECT a.codigo_asiento 
        FROM boletos b
        INNER JOIN asientos a ON b.id_asiento = a.id_asiento
        WHERE b.id_evento = ? AND b.estatus = 1
    ");
    $stmt->bind_param("i", $id_evento);
}

$stmt->execute();
$result = $stmt->get_result();

$asientos = [];
while ($row = $result->fetch_assoc()) {
    $asientos[] = $row['codigo_asiento'];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'asientos' => $asientos
]);
?>
