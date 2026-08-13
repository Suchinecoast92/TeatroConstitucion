<?php
header('Content-Type: application/json');

include "../conexion.php";

if (!isset($_GET['codigos']) || empty($_GET['codigos'])) {
    echo json_encode(['success' => false, 'message' => 'Códigos de boletos no proporcionados']);
    exit;
}

$codigos = explode(',', $_GET['codigos']);

if (empty($codigos)) {
    echo json_encode(['success' => false, 'message' => 'No se proporcionaron códigos válidos']);
    exit;
}

// Crear placeholders para la consulta
$placeholders = implode(',', array_fill(0, count($codigos), '?'));

// Obtener información de los boletos y el evento
$stmt = $conn->prepare("
    SELECT 
        b.codigo_unico,
        a.codigo_asiento,
        e.id_evento,
        e.titulo as evento_titulo,
        e.tipo as evento_tipo,
        (SELECT fecha_hora FROM funciones WHERE id_evento = e.id_evento ORDER BY fecha_hora ASC LIMIT 1) as fecha_hora
    FROM boletos b
    INNER JOIN asientos a ON b.id_asiento = a.id_asiento
    INNER JOIN evento e ON b.id_evento = e.id_evento
    WHERE b.codigo_unico IN ($placeholders) AND b.estatus = 1
    ORDER BY a.codigo_asiento
");

// Bind dinámico de parámetros
$types = str_repeat('s', count($codigos));
$stmt->bind_param($types, ...$codigos);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'No se encontraron boletos']);
    exit;
}

$boletos = [];
$evento_info = null;
$asientos = [];

while ($row = $result->fetch_assoc()) {
    if (!$evento_info) {
        $evento_info = [
            'id_evento' => $row['id_evento'],
            'titulo' => $row['evento_titulo'],
            'tipo' => $row['evento_tipo'],
            'fecha_hora' => $row['fecha_hora']
        ];
    }
    
    $boletos[] = [
        'codigo_unico' => $row['codigo_unico'],
        'asiento' => $row['codigo_asiento']
    ];
    
    $asientos[] = $row['codigo_asiento'];
}

$stmt->close();
$conn->close();

// Formatear tipo de evento
$tipo_evento = '';
if ($evento_info['tipo'] == 1) {
    $tipo_evento = 'Teatro 420';
} elseif ($evento_info['tipo'] == 2) {
    $tipo_evento = 'Pasarela 540';
}

// Formatear fecha y hora
$fecha_formateada = 'No especificada';
$hora_formateada = '';
if ($evento_info['fecha_hora']) {
    $fecha_obj = new DateTime($evento_info['fecha_hora']);
    $fecha_formateada = $fecha_obj->format('d/m/Y');
    $hora_formateada = $fecha_obj->format('H:i');
}

echo json_encode([
    'success' => true,
    'evento' => [
        'titulo' => $evento_info['titulo'],
        'tipo' => $tipo_evento,
        'fecha' => $fecha_formateada,
        'hora' => $hora_formateada
    ],
    'asientos' => $asientos,
    'cantidad' => count($boletos)
]);
?>

