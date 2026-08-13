<?php
header('Content-Type: application/json');
include "../../evt_interfaz/conexion.php";

session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    $response = [];
    
    // Filtros
    $fecha_desde = $_GET['fecha_desde'] ?? null;
    $fecha_hasta = $_GET['fecha_hasta'] ?? null;
    $id_evento = isset($_GET['id_evento']) && $_GET['id_evento'] !== '' ? (int)$_GET['id_evento'] : null;

    $where = "b.estatus = 1"; // Solo boletos vendidos
    $params = [];
    $types = "";

    if ($id_evento) {
        $where .= " AND b.id_evento = ?";
        $params[] = $id_evento;
        $types .= "i";
    }
    if ($fecha_desde) {
        $where .= " AND b.fecha_compra >= ?";
        $params[] = $fecha_desde . " 00:00:00";
        $types .= "s";
    }
    if ($fecha_hasta) {
        $where .= " AND b.fecha_compra <= ?";
        $params[] = $fecha_hasta . " 23:59:59";
        $types .= "s";
    }

    // 1. Resumen General (Cards)
    $sql_resumen = "SELECT 
        COUNT(*) as total_boletos,
        COALESCE(SUM(precio_final), 0) as total_ingresos,
        COALESCE(AVG(precio_final), 0) as ticket_promedio,
        COUNT(DISTINCT id_evento) as eventos_activos
        FROM boletos b WHERE $where";
    
    $stmt = $conn->prepare($sql_resumen);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $response['resumen'] = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 2. Ventas por Hora (Heatmap/Bar chart)
    $sql_horas = "SELECT 
        HOUR(fecha_compra) as hora,
        COUNT(*) as cantidad,
        SUM(precio_final) as ingresos
        FROM boletos b 
        WHERE $where AND fecha_compra IS NOT NULL
        GROUP BY HOUR(fecha_compra)
        ORDER BY hora ASC";
    
    $stmt = $conn->prepare($sql_horas);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res_horas = $stmt->get_result();
    $response['por_hora'] = [];
    while($row = $res_horas->fetch_assoc()) $response['por_hora'][] = $row;
    $stmt->close();

    // 3. Ventas por Evento (Top 5)
    $sql_eventos = "SELECT 
        e.titulo,
        COUNT(b.id_boleto) as cantidad,
        SUM(b.precio_final) as ingresos
        FROM boletos b
        JOIN evento e ON b.id_evento = e.id_evento
        WHERE $where
        GROUP BY e.id_evento, e.titulo
        ORDER BY ingresos DESC
        LIMIT 10";
    
    $stmt = $conn->prepare($sql_eventos);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res_eventos = $stmt->get_result();
    $response['por_evento'] = [];
    while($row = $res_eventos->fetch_assoc()) $response['por_evento'][] = $row;
    $stmt->close();

    // 4. Últimas ventas (Feed en vivo)
    $sql_live = "SELECT 
        b.fecha_compra,
        e.titulo,
        b.precio_final,
        CONCAT(u.nombre, ' ', u.apellido) as vendedor
        FROM boletos b
        JOIN evento e ON b.id_evento = e.id_evento
        LEFT JOIN usuarios u ON b.id_usuario = u.id_usuario
        WHERE $where
        ORDER BY b.fecha_compra DESC
        LIMIT 10";
    
    $stmt = $conn->prepare($sql_live);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res_live = $stmt->get_result();
    $response['ultimas_ventas'] = [];
    while($row = $res_live->fetch_assoc()) $response['ultimas_ventas'][] = $row;
    $stmt->close();

    // 5. Metodos de pago / Categorias (Pie chart)
    $sql_cats = "SELECT 
        c.nombre_categoria,
        COUNT(*) as cantidad
        FROM boletos b
        JOIN categorias c ON b.id_categoria = c.id_categoria
        WHERE $where
        GROUP BY c.nombre_categoria";
        
    $stmt = $conn->prepare($sql_cats);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res_cats = $stmt->get_result();
    $response['por_categoria'] = [];
    while($row = $res_cats->fetch_assoc()) $response['por_categoria'][] = $row;
    $stmt->close();

    echo json_encode(['success' => true, 'data' => $response]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
