<?php
session_start();
require_once '../conexion.php';
require_once '../transacciones_helper.php';

header('Content-Type: application/json');

// Verificar que hay sesión activa
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No hay sesión activa']);
    exit();
}

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);
$password = $data['password'] ?? '';

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Contraseña requerida']);
    exit();
}

// Buscar admin activo
$stmt = $conn->prepare("SELECT id_usuario, password FROM usuarios WHERE rol = 'admin' AND activo = 1 LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $admin = $result->fetch_assoc();
    
    // Verificar contraseña con hash seguro
    if (password_verify($password, $admin['password'])) {
        // Guardar verificación en la sesión (válido por esta sesión)
        $_SESSION['admin_verificado'] = true;
        $_SESSION['admin_verificado_time'] = time();
        registrar_transaccion('verificar_admin', 'Verificación de administrador exitosa');
        
        echo json_encode(['success' => true, 'message' => 'Acceso autorizado']);
    } else {
        registrar_transaccion('verificar_admin_fallida', 'Contraseña de administrador incorrecta');
        echo json_encode(['success' => false, 'message' => 'Contraseña de administrador incorrecta']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No se encontró un administrador activo']);
}

$stmt->close();
$conn->close();
?>
