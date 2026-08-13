<?php
// Buscar boleto por código
header('Content-Type: application/json');

// Log de depuración (comentar en producción)
error_log("=== BUSCAR BOLETO ===");
error_log("POST data: " . print_r($_POST, true));

include "../conexion.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$codigo = $_POST['codigo'] ?? '';
$id_evento = $_POST['id_evento'] ?? 0;

error_log("Código: $codigo");
error_log("ID Evento: $id_evento");

if (empty($codigo)) {
    echo json_encode(['success' => false, 'message' => 'Código de boleto requerido']);
    exit;
}

if (empty($id_evento)) {
    echo json_encode(['success' => false, 'message' => 'Evento no seleccionado']);
    exit;
}

// Buscar el boleto en la base de datos
// Nota: La tabla se llama 'boletos' (plural) y el campo de asiento está en la tabla 'asientos'
$stmt = $conn->prepare("
    SELECT 
        b.id_boleto,
        a.codigo_asiento as asiento,
        b.precio_final,
        b.fecha_compra,
        c.nombre_categoria,
        b.estatus
    FROM boletos b
    LEFT JOIN asientos a ON b.id_asiento = a.id_asiento
    LEFT JOIN categorias c ON b.id_categoria = c.id_categoria
    WHERE b.codigo_unico = ? 
    AND b.id_evento = ?
    AND b.estatus = 1
");

$stmt->bind_param("si", $codigo, $id_evento);
$stmt->execute();
$result = $stmt->get_result();

error_log("Filas encontradas: " . $result->num_rows);

if ($result->num_rows > 0) {
    $boleto = $result->fetch_assoc();
    error_log("Boleto encontrado: " . print_r($boleto, true));
    
    echo json_encode([
        'success' => true,
        'boleto' => [
            'id_boleto' => $boleto['id_boleto'],
            'asiento' => $boleto['asiento'],
            'categoria' => $boleto['nombre_categoria'] ?? 'Sin categoría',
            'precio' => $boleto['precio_final'],
            'fecha_compra' => date('d/m/Y H:i', strtotime($boleto['fecha_compra']))
        ]
    ]);
} else {
    error_log("No se encontró el boleto con estatus = 1");
    // Verificar si el boleto existe pero ya fue usado
    $stmt2 = $conn->prepare("
        SELECT estatus 
        FROM boletos 
        WHERE codigo_unico = ? AND id_evento = ?
    ");
    $stmt2->bind_param("si", $codigo, $id_evento);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    
    if ($result2->num_rows > 0) {
        $boleto2 = $result2->fetch_assoc();
        if ($boleto2['estatus'] == 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Este boleto ya fue usado'
            ]);
        } elseif ($boleto2['estatus'] == 2) {
            echo json_encode([
                'success' => false,
                'message' => 'Este boleto fue cancelado'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Boleto no encontrado'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Boleto no encontrado para este evento'
        ]);
    }
    $stmt2->close();
}

$stmt->close();
$conn->close();
?>
