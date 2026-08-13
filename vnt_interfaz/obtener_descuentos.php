<?php
header('Content-Type: application/json');

include "../conexion.php";

if (!isset($_GET['id_evento'])) {
    echo json_encode(['success' => false, 'message' => 'ID de evento no proporcionado']);
    exit;
}

$id_evento = (int)$_GET['id_evento'];

// Obtener promociones/descuentos del evento activas
// Solo muestra:
// 1. Descuentos ESPECÍFICOS del evento actual (id_evento = ID del evento)
// 2. Descuentos GLOBALES (id_evento = NULL) que aplican a todos los eventos
// Además verifica que si el descuento tiene una categoría, esta pertenezca al evento actual
$stmt = $conn->prepare("
    SELECT 
        p.id_promocion,
        p.nombre,
        p.modo_calculo,
        p.valor,
        p.codigo,
        p.id_categoria,
        p.tipo_regla,
        p.min_cantidad,
        p.condiciones,
        p.id_evento,
        c.nombre_categoria,
        CASE 
            WHEN p.id_evento = ? THEN 'especifico'
            WHEN p.id_evento IS NULL THEN 'global'
            ELSE 'otro'
        END as tipo_descuento
    FROM promociones p
    LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
    WHERE p.activo = 1
    AND (p.fecha_desde IS NULL OR p.fecha_desde <= NOW())
    AND (p.fecha_hasta IS NULL OR p.fecha_hasta >= NOW())
    AND (
        -- Descuentos específicos del evento actual
        p.id_evento = ?
        OR 
        -- Descuentos globales (sin evento asignado)
        (p.id_evento IS NULL)
    )
    -- Si tiene categoría, debe ser del evento actual o el descuento debe ser global
    AND (
        p.id_categoria IS NULL 
        OR c.id_evento = ? 
        OR p.id_evento IS NULL
    )
    ORDER BY 
        CASE WHEN p.id_evento = ? THEN 0 ELSE 1 END, -- Primero los específicos del evento
        p.nombre ASC
");

// Bind: 4 veces el id_evento (para CASE, WHERE evento, WHERE categoria, ORDER BY)
$stmt->bind_param("iiii", $id_evento, $id_evento, $id_evento, $id_evento);
$stmt->execute();
$result = $stmt->get_result();

$descuentos = [];
while ($row = $result->fetch_assoc()) {
    // Extraer tipo_boleto_aplicable de condiciones si existe
    $tipo_boleto_aplicable = null;
    if (!empty($row['condiciones']) && strpos($row['condiciones'], 'TIPO_BOLETO:') === 0) {
        $partes = explode('|', $row['condiciones'], 2);
        $tipo_boleto_aplicable = str_replace('TIPO_BOLETO:', '', $partes[0]);
        // Restaurar condiciones originales
        $row['condiciones'] = isset($partes[1]) ? $partes[1] : '';
    }
    $row['tipo_boleto_aplicable'] = $tipo_boleto_aplicable;
    
    $descuentos[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'descuentos' => $descuentos
]);
?>
