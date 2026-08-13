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
    
    // Leer datos JSON del request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['codigo_unico'])) {
        throw new Exception("Código no proporcionado");
    }
    
    $codigo_unico = strtoupper(trim($data['codigo_unico']));
    
    if (empty($codigo_unico)) {
        throw new Exception("Código vacío");
    }
    
    // Actualizar el estatus del boleto a 0 (usado)
    $stmt = $conn->prepare("
        UPDATE boletos 
        SET estatus = 0 
        WHERE codigo_unico = ? AND estatus = 1
    ");
    
    if (!$stmt) {
        throw new Exception("Error al preparar consulta: " . $conn->error);
    }
    
    $stmt->bind_param("s", $codigo_unico);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar consulta: " . $stmt->error);
    }
    
    if ($stmt->affected_rows > 0) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Entrada confirmada exitosamente'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'El boleto ya fue usado o no existe'
        ], JSON_UNESCAPED_UNICODE);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

ob_end_flush();
?>
