<?php
// Deshabilitar salida de errores HTML
error_reporting(0);
ini_set('display_errors', 0);

// Limpiar cualquier salida previa
ob_start();

// Establecer header JSON
header('Content-Type: application/json');

try {
    // Incluir conexión
    if (!file_exists("conexion_api.php")) {
        throw new Exception("Archivo de conexión no encontrado");
    }
    
    include "conexion_api.php";
    
    if (!isset($conn) || !$conn) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    $codigo = isset($_GET['codigo']) ? strtoupper(trim($_GET['codigo'])) : '';
    
    if (empty($codigo)) {
        throw new Exception("Código no proporcionado");
    }
    
    // Verificar si la columna id_funcion existe en boletos
    $check_column = $conn->query("SHOW COLUMNS FROM boletos LIKE 'id_funcion'");
    $has_id_funcion = ($check_column && $check_column->num_rows > 0);
    
    // Buscar boleto con toda su información
    if ($has_id_funcion) {
        // Si existe id_funcion, hacer JOIN con funciones
        $stmt = $conn->prepare("
            SELECT 
                b.id_boleto,
                b.codigo_unico,
                b.precio_final,
                b.estatus,
                a.codigo_asiento,
                c.nombre_categoria,
                e.titulo as evento_titulo,
                e.tipo as evento_tipo,
                f.fecha_hora
            FROM boletos b
            INNER JOIN asientos a ON b.id_asiento = a.id_asiento
            INNER JOIN evento e ON b.id_evento = e.id_evento
            LEFT JOIN categorias c ON b.id_categoria = c.id_categoria
            LEFT JOIN funciones f ON b.id_funcion = f.id_funcion
            WHERE b.codigo_unico = ?
        ");
    } else {
        // Si no existe id_funcion, obtener la primera función del evento
        $stmt = $conn->prepare("
            SELECT 
                b.id_boleto,
                b.codigo_unico,
                b.precio_final,
                b.estatus,
                a.codigo_asiento,
                c.nombre_categoria,
                e.titulo as evento_titulo,
                e.tipo as evento_tipo,
                (SELECT fecha_hora FROM funciones WHERE id_evento = b.id_evento ORDER BY fecha_hora ASC LIMIT 1) as fecha_hora
            FROM boletos b
            INNER JOIN asientos a ON b.id_asiento = a.id_asiento
            INNER JOIN evento e ON b.id_evento = e.id_evento
            LEFT JOIN categorias c ON b.id_categoria = c.id_categoria
            WHERE b.codigo_unico = ?
        ");
    }
    
    if (!$stmt) {
        throw new Exception("Error al preparar consulta: " . $conn->error);
    }
    
    $stmt->bind_param("s", $codigo);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar consulta: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $boleto = $result->fetch_assoc();
        
        // Limpiar buffer y enviar respuesta
        ob_clean();
        echo json_encode([
            'success' => true,
            'boleto' => $boleto
        ], JSON_UNESCAPED_UNICODE);
    } else {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Boleto no encontrado'
        ], JSON_UNESCAPED_UNICODE);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    // Limpiar buffer y enviar error
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

ob_end_flush();
?>
