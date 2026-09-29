<?php
// Deshabilitar salida de errores HTML
error_reporting(0);
ini_set('display_errors', 0);

// Limpiar cualquier salida previa
ob_start();

// Establecer header JSON
header('Content-Type: application/json');

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once __DIR__ . '/../includes/auth_guard.php';
    require_once __DIR__ . '/../includes/csrf.php';
    teatro_require_login(true);
    teatro_require_csrf(true);

    // Incluir conexión
    if (!file_exists("conexion_api.php")) {
        throw new Exception("Archivo de conexión no encontrado");
    }
    
    include "conexion_api.php";
    
    if (!isset($conn) || !$conn) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // Leer datos JSON del request (CSRF pudo consumir el body)
    $data = teatro_csrf_consumed_json_body();
    if ($data === null) {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
    }
    
    if (!$data || !isset($data['codigo_unico'])) {
        throw new Exception("Código no proporcionado");
    }
    
    $codigo_unico = strtoupper(trim($data['codigo_unico']));
    
    if (empty($codigo_unico)) {
        throw new Exception("Código vacío");
    }
    
    require_once __DIR__ . '/../includes/entrada_helper.php';
    $forzar = !empty($data['forzar']);
    $idUsuario = isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
    $resultado = entrada_confirmar($conn, $codigo_unico, $forzar, $idUsuario);

    ob_clean();
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    $conn->close();
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

ob_end_flush();
