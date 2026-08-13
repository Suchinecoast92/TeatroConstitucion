<?php
// API para buscando en tiempo real de transacciones
header('Content-Type: application/json');
require_once '../../evt_interfaz/conexion.php';

session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    $q = $_GET['q'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 100; // 100 resultados por página
    $offset = ($page - 1) * $limit;

    $params = [];
    $types = "";
    
    // Consulta base
    $sql_base = "SELECT t.id_transaccion, t.accion, t.descripcion, t.fecha_hora, t.datos_json, u.nombre, u.apellido
                 FROM transacciones t
                 JOIN usuarios u ON t.id_usuario = u.id_usuario";

    $where = [];

    // Lógica de Búsqueda
    $fecha_desde = $_GET['fecha_desde'] ?? '';
    $fecha_hasta = $_GET['fecha_hasta'] ?? '';

    if (!empty($q)) {
        $term = "%$q%";
        $where[] = "(u.nombre LIKE ? OR u.apellido LIKE ? OR t.accion LIKE ? OR t.descripcion LIKE ? OR t.datos_json LIKE ?)";
        $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
        $types .= "sssss";
    }

    if (!empty($fecha_desde)) {
        $where[] = "t.fecha_hora >= ?";
        $params[] = $fecha_desde . ' 00:00:00';
        $types .= "s";
    }

    if (!empty($fecha_hasta)) {
        $where[] = "t.fecha_hora <= ?";
        $params[] = $fecha_hasta . ' 23:59:59';
        $types .= "s";
    }

    $where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

    // Count
    $sql_count = "SELECT COUNT(*) as total 
                  FROM transacciones t 
                  JOIN usuarios u ON t.id_usuario = u.id_usuario 
                  $where_sql";
    
    $stmt_count = $conn->prepare($sql_count);
    if ($params) $stmt_count->bind_param($types, ...$params);
    $stmt_count->execute();
    $total_rows = $stmt_count->get_result()->fetch_assoc()['total'];
    $stmt_count->close();

    // Fetch
    $sql = "$sql_base $where_sql ORDER BY t.fecha_hora DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transacciones = [];
    while ($row = $result->fetch_assoc()) {
        // Parseamos el JSON para el frontend
        $extra = [];
        if ($row['datos_json']) {
            $decoded = json_decode($row['datos_json'], true);
            if ($decoded) {
                // Extraer datos clave para la tabla
                if (isset($decoded['cliente'])) $extra['cliente'] = $decoded['cliente'];
                if (isset($decoded['evento']['titulo'])) $extra['evento'] = $decoded['evento']['titulo'];
                if (isset($decoded['total'])) $extra['total'] = $decoded['total'];
                if (isset($decoded['boletos'])) {
                     $extra['cantidad'] = count($decoded['boletos']);
                     $extra['boletos_detalle'] = $decoded['boletos'];
                }
            }
        }
        $row['extra'] = $extra;
        // Limpiamos datos_json raw para no enviar demasiado texto si no se usa
        // $row['datos_json'] = null; 
        $transacciones[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'data' => $transacciones,
        'pagination' => [
            'total' => $total_rows,
            'page' => $page,
            'pages' => ceil($total_rows / $limit)
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
