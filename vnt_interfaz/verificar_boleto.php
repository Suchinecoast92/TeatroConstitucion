<?php
// Deshabilitar salida de errores HTML
error_reporting(0);
ini_set('display_errors', 0);

// Limpiar cualquier salida previa
ob_start();

// Establecer header JSON
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth_guard.php';
teatro_require_login(true);
require_once __DIR__ . '/../includes/entrada_helper.php';

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
    
    $boleto = entrada_obtener_boleto($conn, $codigo);
    ob_clean();
    if ($boleto) {
        echo json_encode([
            'success' => true,
            'boleto' => $boleto
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Boleto no encontrado'
        ], JSON_UNESCAPED_UNICODE);
    }
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
