<?php
// vnt_interfaz/api_buscador.php
session_start();
header('Content-Type: application/json');

// Permitir acceso a empleados y admins
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

require_once '../conexion.php';

$action = $_GET['action'] ?? '';
$id_evento = $_GET['id_evento'] ?? null;
$id_funcion = $_GET['id_funcion'] ?? null;
$query_text = $_GET['q'] ?? '';

try {
    switch ($action) {
        case 'buscar':
            // Validación básica: Si es empleado, quizás restringir algo, pero por ahora abierto
            
            $sql = "
                SELECT 
                    b.id_boleto,
                    b.codigo_unico,
                    b.precio_final,
                    b.fecha_compra,
                    b.estatus,
                    b.id_evento,
                    b.id_categoria,
                    a.codigo_asiento,
                    e.titulo as evento_titulo,
                    c.nombre_categoria,
                    f.fecha_hora as funcion_fecha,
                    u.nombre AS vendedor_nombre
                FROM boletos b
                LEFT JOIN asientos a ON b.id_asiento = a.id_asiento
                LEFT JOIN evento e ON b.id_evento = e.id_evento
                LEFT JOIN categorias c ON b.id_categoria = c.id_categoria
                LEFT JOIN funciones f ON b.id_funcion = f.id_funcion
                LEFT JOIN usuarios u ON b.id_usuario = u.id_usuario
                WHERE 1=1
            ";
            
            $types = "";
            $params = [];
            
            // Filtro por Evento (Opcional pero recomendado por el usuario)
            if (!empty($id_evento)) {
                $sql .= " AND b.id_evento = ?";
                $types .= "i";
                $params[] = $id_evento;
            }
            
            // Filtro por Función (Opcional)
            if (!empty($id_funcion)) {
                $sql .= " AND b.id_funcion = ?";
                $types .= "i";
                $params[] = $id_funcion;
            }
            
            // Búsqueda por Texto (Código, Asiento)
            if (!empty($query_text)) {
                $sql .= " AND (b.codigo_unico LIKE ? OR a.codigo_asiento LIKE ?)";
                $searchTerm = "%$query_text%";
                $types .= "ss";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " ORDER BY b.fecha_compra DESC LIMIT 50";
            
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $boletos = [];
            while ($row = $result->fetch_assoc()) {
                // Formatear fecha para el frontend
                if ($row['funcion_fecha']) {
                    $date = new DateTime($row['funcion_fecha']);
                    $row['funcion_fecha_fmt'] = $date->format('d/m/Y h:i A');
                    // Campos para impresión qz
                    $row['fecha_simple'] = $date->format('d/m/Y');
                    $row['hora_simple'] = $date->format('H:i') . ' hrs';
                } else {
                    $row['funcion_fecha_fmt'] = 'N/A';
                    $row['fecha_simple'] = '';
                    $row['hora_simple'] = '';
                }
                
                // Formatear estado
                $row['estado_texto'] = match((int)$row['estatus']) {
                    1 => 'Activo',
                    0 => 'Usado',
                    2 => 'Cancelado',
                    default => 'Desconocido'
                };
                
                $boletos[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $boletos]);
            break;
            
        default:
            echo json_encode(['error' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
