<?php
session_start();
require_once '../conexion.php';
require_once '../transacciones_helper.php';

// Verificar que hay sesión activa
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}

// Verificar que es admin
if ($_SESSION['usuario_rol'] !== 'admin') {
    die("Acceso denegado. Solo administradores pueden registrar empleados.");
}

$mensaje = '';
$tipo_mensaje = '';

// Función para verificar si un usuario es el admin principal (ID = 1 o primer admin)
function esAdminPrincipal($conn, $id)
{
    $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE rol = 'admin' ORDER BY id_usuario ASC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        return $admin['id_usuario'] == $id;
    }
    return false;
}

// API PARA AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    // VERIFICAR CONTRASEÑA ADMIN
    if ($_POST['ajax'] === 'verificar_password') {
        $password = $_POST['password'] ?? '';
        $id_admin = $_SESSION['usuario_id'];

        $stmt = $conn->prepare("SELECT password FROM usuarios WHERE id_usuario = ?");
        $stmt->bind_param("i", $id_admin);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $admin = $result->fetch_assoc();
            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_verificado_tiempo'] = time();
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error de autenticación']);
        }
        exit;
    }

    // OBTENER DATOS DE USUARIO PARA EDITAR
    if ($_POST['ajax'] === 'obtener_usuario') {
        $id = (int) $_POST['id'];
        $stmt = $conn->prepare("SELECT id_usuario, nombre, apellido, rol, activo FROM usuarios WHERE id_usuario = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $usuario = $result->fetch_assoc();
            $usuario['es_admin_principal'] = esAdminPrincipal($conn, $id);
            echo json_encode(['success' => true, 'usuario' => $usuario]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        }
        exit;
    }

    // GUARDAR EDICIÓN
    if ($_POST['ajax'] === 'guardar_usuario') {
        $id = (int) $_POST['id'];
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $password = trim($_POST['password'] ?? '');
        $rol = $_POST['rol'];

        // Proteger al admin principal: no puede cambiarle el rol
        if (esAdminPrincipal($conn, $id) && $rol !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'No se puede cambiar el rol del administrador principal']);
            exit;
        }

        // Verificar nombre duplicado
        $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE nombre = ? AND id_usuario != ?");
        $stmt->bind_param("si", $nombre, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'El nombre de usuario ya existe']);
            exit;
        }

        // Obtener datos anteriores para comparar
        $stmt_ant = $conn->prepare("SELECT nombre, apellido, rol FROM usuarios WHERE id_usuario = ?");
        $stmt_ant->bind_param("i", $id);
        $stmt_ant->execute();
        $datos_anteriores = $stmt_ant->get_result()->fetch_assoc();

        if (!empty($password)) {
            // Validar longitud mínima de contraseña
            if (strlen($password) < 6) {
                echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
                exit;
            }
            if (!preg_match('/^[A-Za-z0-9]+$/', $password)) {
                echo json_encode(['success' => false, 'message' => 'La contraseña solo puede contener letras y números, sin espacios ni símbolos']);
                exit;
            }
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, apellido = ?, password = ?, rol = ? WHERE id_usuario = ?");
            $stmt->bind_param("ssssi", $nombre, $apellido, $password_hash, $rol, $id);
        } else {
            $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, apellido = ?, rol = ? WHERE id_usuario = ?");
            $stmt->bind_param("sssi", $nombre, $apellido, $rol, $id);
        }

        if ($stmt->execute()) {
            // Registrar transacción con los cambios realizados
            $cambios = [];
            if ($datos_anteriores['nombre'] !== $nombre) {
                $cambios[] = "nombre: '{$datos_anteriores['nombre']}' → '$nombre'";
            }
            if ($datos_anteriores['apellido'] !== $apellido) {
                $cambios[] = "apellido: '{$datos_anteriores['apellido']}' → '$apellido'";
            }
            if ($datos_anteriores['rol'] !== $rol) {
                $cambios[] = "rol: '{$datos_anteriores['rol']}' → '$rol'";
            }
            if (!empty($password)) {
                $cambios[] = "contraseña actualizada";
            }

            $descripcion_cambios = !empty($cambios) ? implode(', ', $cambios) : 'sin cambios';
            registrar_transaccion('usuario_editar', "Usuario editado: {$datos_anteriores['nombre']} {$datos_anteriores['apellido']} (ID: $id). Cambios: $descripcion_cambios");

            echo json_encode(['success' => true, 'message' => 'Usuario actualizado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
        }
        exit;
    }

    // TOGGLE ESTADO
    if ($_POST['ajax'] === 'toggle_estado') {
        $id = (int) $_POST['id'];

        // Proteger al admin principal
        if (esAdminPrincipal($conn, $id)) {
            echo json_encode(['success' => false, 'message' => 'No se puede desactivar al administrador principal']);
            exit;
        }

        // Obtener datos del usuario antes del cambio
        $stmt_usuario = $conn->prepare("SELECT nombre, apellido, activo FROM usuarios WHERE id_usuario = ?");
        $stmt_usuario->bind_param("i", $id);
        $stmt_usuario->execute();
        $usuario_info = $stmt_usuario->get_result()->fetch_assoc();

        $stmt = $conn->prepare("UPDATE usuarios SET activo = NOT activo WHERE id_usuario = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $stmt2 = $conn->prepare("SELECT activo FROM usuarios WHERE id_usuario = ?");
            $stmt2->bind_param("i", $id);
            $stmt2->execute();
            $result = $stmt2->get_result()->fetch_assoc();

            // Registrar transacción del cambio de estado
            $nuevo_estado = $result['activo'] ? 'ACTIVO' : 'INACTIVO';
            registrar_transaccion('usuario_estado', "Estado de usuario cambiado: {$usuario_info['nombre']} {$usuario_info['apellido']} (ID: $id) → $nuevo_estado");

            echo json_encode(['success' => true, 'activo' => $result['activo']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al cambiar estado']);
        }
        exit;
    }

    // ELIMINAR USUARIO
    if ($_POST['ajax'] === 'eliminar_usuario') {
        $id = (int) $_POST['id'];

        // Proteger al admin principal
        if (esAdminPrincipal($conn, $id)) {
            echo json_encode(['success' => false, 'message' => 'No se puede eliminar al administrador principal']);
            exit;
        }

        if ($id == $_SESSION['usuario_id']) {
            echo json_encode(['success' => false, 'message' => 'No puedes eliminar tu propia cuenta']);
            exit;
        }

        // Obtener datos del usuario antes de eliminar
        $stmt_usuario = $conn->prepare("SELECT nombre, apellido, rol FROM usuarios WHERE id_usuario = ?");
        $stmt_usuario->bind_param("i", $id);
        $stmt_usuario->execute();
        $usuario_info = $stmt_usuario->get_result()->fetch_assoc();

        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            // Registrar transacción de eliminación
            registrar_transaccion('usuario_eliminar', "Usuario eliminado: {$usuario_info['nombre']} {$usuario_info['apellido']} (ID: $id, Rol: {$usuario_info['rol']})");

            echo json_encode(['success' => true, 'message' => 'Usuario eliminado']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
        }
        exit;
    }
}

// PROCESAR REGISTRO NUEVO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_nuevo'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $password_confirm = trim($_POST['password_confirm'] ?? '');
    $rol = $_POST['rol'] ?? 'empleado';

    if (empty($nombre) || empty($apellido) || empty($password)) {
        $mensaje = 'Todos los campos son obligatorios';
        $tipo_mensaje = 'error';
    } elseif ($password !== $password_confirm) {
        $mensaje = 'Las contraseñas no coinciden';
        $tipo_mensaje = 'error';
    } elseif (strlen($password) < 6) {
        $mensaje = 'La contraseña debe tener al menos 6 caracteres';
        $tipo_mensaje = 'error';
    } elseif (!preg_match('/^[A-Za-z0-9]+$/', $password)) {
        $mensaje = 'La contraseña solo puede contener letras y números, sin espacios ni símbolos';
        $tipo_mensaje = 'error';
    } else {
        $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE nombre = ?");
        $stmt->bind_param("s", $nombre);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $mensaje = 'El nombre de usuario ya existe';
            $tipo_mensaje = 'error';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO usuarios (nombre, apellido, password, rol, activo) VALUES (?, ?, ?, ?, 1)");
            $stmt->bind_param("ssss", $nombre, $apellido, $password_hash, $rol);

            if ($stmt->execute()) {
                $nuevo_id = $conn->insert_id;
                // Registrar transacción de creación de usuario
                registrar_transaccion('usuario_crear', "Nuevo usuario creado: $nombre $apellido (ID: $nuevo_id, Rol: $rol)");

                $mensaje = 'Empleado registrado exitosamente';
                $tipo_mensaje = 'success';
                $_POST = array();
            } else {
                $mensaje = 'Error al registrar el empleado';
                $tipo_mensaje = 'error';
            }
        }
        $stmt->close();
    }
}

// Obtener lista de empleados - Admin principal primero, luego por fecha
$query = "SELECT id_usuario, nombre, apellido, rol, activo, fecha_registro FROM usuarios 
          ORDER BY 
            CASE WHEN rol = 'admin' THEN 0 ELSE 1 END,
            id_usuario ASC";
$empleados = $conn->query($query);
$usuario_actual_id = $_SESSION['usuario_id'];

// Obtener ID del admin principal
$admin_principal_id = 0;
$stmt_admin = $conn->prepare("SELECT id_usuario FROM usuarios WHERE rol = 'admin' ORDER BY id_usuario ASC LIMIT 1");
$stmt_admin->execute();
$res_admin = $stmt_admin->get_result();
if ($res_admin->num_rows > 0) {
    $admin_principal_id = $res_admin->fetch_assoc()['id_usuario'];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #131313;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            background: #1c1c1e;
            padding: 25px 30px;
            border-radius: 16px;
            margin-bottom: 25px;
            border: 1px solid #3a3a3c;
        }

        .page-header h1 {
            color: #fff;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 1.8em;
        }

        .page-header h1 i {
            color: #1561f0;
        }

        .grid-layout {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 25px;
        }

        .card {
            background: #1c1c1e;
            padding: 25px;
            border-radius: 16px;
            border: 1px solid #3a3a3c;
        }

        .card-title {
            font-size: 1.2em;
            color: #fff;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #1561f0;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #a0aec0;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            font-size: 1em;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #1561f0;
            background: rgba(255, 255, 255, 0.1);
        }

        .form-group input::placeholder {
            color: #666;
        }

        .form-group select option {
            background: #1a1a2e;
            color: #fff;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #1561f0;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.05em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(21, 97, 240, 0.4);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .tabla-usuarios {
            width: 100%;
            border-collapse: collapse;
        }

        .tabla-usuarios th,
        .tabla-usuarios td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .tabla-usuarios th {
            color: #a0aec0;
            font-weight: 600;
            font-size: 0.9em;
            text-transform: uppercase;
        }

        .tabla-usuarios td {
            color: #fff;
        }

        .tabla-usuarios tbody tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        /* Fila del admin principal */
        .tabla-usuarios tbody tr.admin-principal {
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
            border-left: 3px solid #667eea;
        }

        .tabla-usuarios tbody tr.admin-principal td:first-child {
            position: relative;
        }

        .admin-crown {
            color: #fbbf24;
            font-size: 1.2em;
            margin-left: 8px;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .badge-admin {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
        }

        .badge-empleado {
            background: rgba(107, 114, 128, 0.3);
            color: #9ca3af;
        }

        /* TOGGLE SWITCH ANIMADO - ROJO/VERDE */
        .toggle-switch {
            position: relative;
            width: 56px;
            height: 28px;
            cursor: pointer;
        }

        .toggle-switch.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border-radius: 28px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 0 12px rgba(239, 68, 68, 0.5);
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background: #fff;
            border-radius: 50%;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .toggle-slider:after {
            content: "✕";
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
            font-weight: bold;
            color: rgba(255, 255, 255, 0.9);
            transition: all 0.3s;
        }

        .toggle-switch input:checked+.toggle-slider {
            background: linear-gradient(135deg, #10b981, #059669);
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.6);
        }

        .toggle-switch input:checked+.toggle-slider:before {
            transform: translateX(28px);
        }

        .toggle-switch input:checked+.toggle-slider:after {
            content: "✓";
            right: auto;
            left: 10px;
        }

        .toggle-switch:not(.disabled):hover .toggle-slider {
            transform: scale(1.05);
        }

        .toggle-switch:not(.disabled):active .toggle-slider {
            transform: scale(0.95);
        }
        
        /* Animación de cambio de estado */
        @keyframes toggleActivate {
            0% { transform: scale(1); }
            25% { transform: scale(1.15); box-shadow: 0 0 25px rgba(16, 185, 129, 0.8); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        @keyframes toggleDeactivate {
            0% { transform: scale(1); }
            25% { transform: scale(1.15); box-shadow: 0 0 25px rgba(239, 68, 68, 0.8); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .toggle-slider.activating {
            animation: toggleActivate 0.6s ease;
        }
        
        .toggle-slider.deactivating {
            animation: toggleDeactivate 0.6s ease;
        }
        
        /* Indicador de estado con texto */
        .estado-label {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            margin-left: 10px;
            transition: all 0.3s ease;
        }
        
        .estado-label.activo {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        
        .estado-label.inactivo {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        
        /* Toggle Password Visibility */
        .password-wrapper {
            position: relative;
        }
        
        .password-wrapper input {
            padding-right: 45px;
        }
        
        .btn-toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 5px;
            font-size: 1.1em;
            transition: all 0.3s;
        }
        
        .btn-toggle-password:hover {
            color: #667eea;
        }
        
        .password-info {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .password-info i {
            color: #f59e0b;
        }

        .acciones-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            font-size: 1em;
        }

        .btn-editar {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .btn-editar:hover {
            background: #3b82f6;
            color: #fff;
            transform: scale(1.1);
        }

        .btn-eliminar {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .btn-eliminar:hover {
            background: #ef4444;
            color: #fff;
            transform: scale(1.1);
        }

        .btn-eliminar.disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .btn-eliminar.disabled:hover {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            transform: none;
        }

        /* MODAL */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-radius: 20px;
            width: 90%;
            max-width: 450px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: modalIn 0.3s ease;
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            color: #fff;
            font-size: 1.3em;
        }

        .modal-close {
            background: none;
            border: none;
            color: #9ca3af;
            font-size: 1.5em;
            cursor: pointer;
            transition: all 0.3s;
        }

        .modal-close:hover {
            color: #ef4444;
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 25px;
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn-modal {
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }

        .btn-guardar {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
        }

        .btn-guardar:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-cancelar {
            background: rgba(107, 114, 128, 0.3);
            color: #9ca3af;
        }

        .btn-cancelar:hover {
            background: rgba(107, 114, 128, 0.5);
        }

        /* Modal de seguridad */
        .security-icon {
            font-size: 4em;
            color: #667eea;
            margin-bottom: 20px;
            display: block;
            text-align: center;
        }

        .security-text {
            color: #a0aec0;
            text-align: center;
            margin-bottom: 20px;
        }

        .password-input-security {
            width: 100%;
            padding: 15px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-size: 1.1em;
            text-align: center;
            letter-spacing: 5px;
        }

        .password-input-security:focus {
            outline: none;
            border-color: #667eea;
        }

        .page-bloqueada {
            filter: blur(10px);
            pointer-events: none;
            user-select: none;
        }

        @media (max-width: 1024px) {
            .grid-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>


    <div class="container" id="contenidoPrincipal">
        <div class="page-header">
            <h1><i class="bi bi-people-fill"></i> Gestión de Usuarios</h1>
        </div>

        <div class="grid-layout">
            <div class="card">
                <h2 class="card-title">Nuevo Usuario</h2>

                <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                        <i
                            class="bi bi-<?php echo $tipo_mensaje === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?>"></i>
                        <span><?php echo htmlspecialchars($mensaje); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" id="formRegistro" onsubmit="return solicitarRegistro(event)">
                    <input type="hidden" name="registrar_nuevo" value="1">

                    <div class="form-group">
                        <label>Nombre de usuario</label>
                        <input type="text" name="nombre" id="reg_nombre" required placeholder="Ej: juan.perez">
                    </div>

                    <div class="form-group">
                        <label>Apellido</label>
                        <input type="text" name="apellido" id="reg_apellido" required placeholder="Ej: Pérez García">
                    </div>

                    <div class="form-group">
                        <label>Contraseña (mín. 6 caracteres)</label>
                        <input type="password" name="password" id="reg_password" required placeholder="••••••" pattern="[A-Za-z0-9]{6,}"
                            title="Solo letras y números, mínimo 6 caracteres" autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label>Confirmar contraseña</label>
                        <input type="password" name="password_confirm" id="reg_password_confirm" required placeholder="••••••" pattern="[A-Za-z0-9]{6,}"
                            title="Debe coincidir y solo letras y números" autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label>Rol</label>
                        <select name="rol" id="reg_rol" required>
                            <option value="empleado">Empleado</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="bi bi-person-plus-fill"></i>
                        Registrar Usuario
                    </button>
                </form>
            </div>

            <div class="card">
                <h2 class="card-title">Lista de Usuarios</h2>

                <table class="tabla-usuarios">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($emp = $empleados->fetch_assoc()):
                            $es_admin_principal = ($emp['id_usuario'] == $admin_principal_id);
                            ?>
                            <tr id="fila-<?php echo $emp['id_usuario']; ?>"
                                class="<?php echo $es_admin_principal ? 'admin-principal' : ''; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($emp['nombre']); ?></strong>
                                    <?php if ($es_admin_principal): ?>
                                        <i class="bi bi-shield-fill-check admin-crown" title="Administrador Principal"></i>
                                    <?php endif; ?>
                                    <div style="color: #9ca3af; font-size: 0.85em;">
                                        <?php echo htmlspecialchars($emp['apellido']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $emp['rol']; ?>">
                                        <?php echo strtoupper($emp['rol']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($es_admin_principal): ?>
                                        <label class="toggle-switch disabled">
                                            <input type="checkbox" checked disabled>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    <?php else: ?>
                                        <label class="toggle-switch">
                                            <input type="checkbox" <?php echo $emp['activo'] ? 'checked' : ''; ?>
                                                onchange="toggleEstado(<?php echo $emp['id_usuario']; ?>, this)">
                                            <span class="toggle-slider"></span>
                                        </label>
                                    <?php endif; ?>
                                </td>
                                <td class="acciones-cell">
                                    <button class="btn-icon btn-editar"
                                        onclick="solicitarEditar(<?php echo $emp['id_usuario']; ?>)" title="Editar">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <?php if (!$es_admin_principal && $emp['id_usuario'] != $usuario_actual_id): ?>
                                        <button class="btn-icon btn-eliminar"
                                            onclick="eliminarUsuario(<?php echo $emp['id_usuario']; ?>, '<?php echo htmlspecialchars($emp['nombre']); ?>')"
                                            title="Eliminar">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    <?php elseif ($es_admin_principal): ?>
                                        <button class="btn-icon btn-eliminar disabled"
                                            title="No se puede eliminar al admin principal" disabled>
                                            <i class="bi bi-shield-lock-fill"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL VERIFICAR CONTRASEÑA -->
    <div class="modal-overlay" id="modalSeguridad">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="bi bi-shield-lock"></i> Verificación de Seguridad</h3>
                <button class="modal-close" onclick="cerrarModalSeguridad()">&times;</button>
            </div>
            <div class="modal-body">
                <i class="bi bi-key-fill security-icon"></i>
                <p class="security-text">Ingresa tu contraseña de administrador para continuar</p>
                <input type="password" id="passwordSeguridad" class="password-input-security" placeholder="••••••"
                    maxlength="20" onkeypress="if(event.key==='Enter') verificarPassword()">
                <div id="errorSeguridad" style="color: #ef4444; text-align: center; margin-top: 15px; display: none;">
                    <i class="bi bi-exclamation-triangle"></i> <span id="errorSeguridadTexto">Contraseña
                        incorrecta</span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal btn-cancelar" onclick="cerrarModalSeguridad()">Cancelar</button>
                <button class="btn-modal btn-guardar" onclick="verificarPassword()">
                    <i class="bi bi-unlock-fill"></i> Verificar
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL EDITAR -->
    <div class="modal-overlay" id="modalEditar">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="bi bi-pencil-square"></i> Editar Usuario</h3>
                <button class="modal-close" onclick="cerrarModal()">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editar_id">
                <input type="hidden" id="editar_es_admin_principal">

                <!-- Alerta de error del modal -->
                <div class="alert alert-error" id="errorEditar" style="display: none;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span id="errorEditarTexto"></span>
                </div>

                <div class="form-group">
                    <label>Nombre de usuario</label>
                    <input type="text" id="editar_nombre" required>
                </div>

                <div class="form-group">
                    <label>Apellido</label>
                    <input type="text" id="editar_apellido" required>
                </div>

                <div class="form-group">
                    <label>Nueva contraseña (dejar vacío para no cambiar)</label>
                    <div class="password-wrapper">
                        <input type="password" id="editar_password" placeholder="Dejar vacío para mantener" pattern="[A-Za-z0-9]{6,}"
                            title="Solo letras y números, mínimo 6 caracteres" autocomplete="new-password">
                        <button type="button" class="btn-toggle-password" onclick="togglePasswordVisibility('editar_password', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="password-info">
                        <i class="bi bi-shield-lock"></i>
                        <span>La contraseña actual está encriptada y no puede mostrarse por seguridad</span>
                    </div>
                </div>

                <div class="form-group" id="grupo_rol">
                    <label>Rol</label>
                    <select id="editar_rol">
                        <option value="empleado">Empleado</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal btn-cancelar" onclick="cerrarModal()">Cancelar</button>
                <button class="btn-modal btn-guardar" onclick="guardarEdicion()">
                    <i class="bi bi-check-lg"></i> Guardar
                </button>
            </div>
        </div>
    </div>

    <script>
        let accionPendiente = null;

        // Mostrar error en modal de seguridad
        function mostrarErrorSeguridad(mensaje) {
            const errorDiv = document.getElementById('errorSeguridad');
            document.getElementById('errorSeguridadTexto').textContent = mensaje;
            errorDiv.style.display = 'block';
        }

        // Ocultar error de seguridad
        function ocultarErrorSeguridad() {
            document.getElementById('errorSeguridad').style.display = 'none';
        }

        // Toggle password visibility
        function togglePasswordVisibility(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }

        // Mostrar notificación flotante
        function mostrarNotificacion(mensaje, tipo) {
            const notif = document.createElement('div');
            notif.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 25px;
                border-radius: 10px;
                z-index: 9999;
                animation: slideIn 0.3s ease;
                display: flex;
                align-items: center;
                gap: 10px;
                font-weight: 500;
                ${tipo === 'error'
                    ? 'background: rgba(239, 68, 68, 0.9); color: white; border: 1px solid #ef4444;'
                    : 'background: rgba(16, 185, 129, 0.9); color: white; border: 1px solid #10b981;'}
            `;
            notif.innerHTML = `<i class="bi bi-${tipo === 'error' ? 'exclamation-triangle' : 'check-circle'}"></i> ${mensaje}`;
            document.body.appendChild(notif);
            setTimeout(() => notif.remove(), 4000);
        }

        // Helper para mostrar modal de seguridad
        function mostrarModalSeguridad() {
            document.getElementById('passwordSeguridad').value = '';
            ocultarErrorSeguridad();
            document.getElementById('modalSeguridad').classList.add('active');
            document.getElementById('passwordSeguridad').focus();
        }

        // Cerrar modal de seguridad
        function cerrarModalSeguridad() {
            document.getElementById('modalSeguridad').classList.remove('active');
            accionPendiente = null;
        }

        // Solicitar edición (primero pide contraseña)
        function solicitarEditar(id) {
            accionPendiente = { tipo: 'editar', id: id };
            mostrarModalSeguridad();
        }

        // Verificar contraseña de admin
        async function verificarPassword() {
            const password = document.getElementById('passwordSeguridad').value;

            if (!password) {
                mostrarErrorSeguridad('Ingresa tu contraseña');
                return;
            }

            const formData = new FormData();
            formData.append('ajax', 'verificar_password');
            formData.append('password', password);

            const response = await fetch('', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                const accion = accionPendiente;
                cerrarModalSeguridad();
                
                if (accion) {
                    if (accion.tipo === 'editar') {
                        abrirModalEditar(accion.id);
                    } else if (accion.tipo === 'toggle') {
                        realizarToggle(accion.id, accion.element);
                    } else if (accion.tipo === 'eliminar') {
                        realizarEliminacion(accion.id);
                    } else if (accion.tipo === 'registro') {
                        // Enviar el formulario de registro
                        document.getElementById('formRegistro').submit();
                    }
                }
            } else {
                mostrarErrorSeguridad('Contraseña incorrecta');
                document.getElementById('passwordSeguridad').value = '';
                document.getElementById('passwordSeguridad').focus();
            }
        }

        async function realizarToggle(id, checkbox) {
             const slider = checkbox.parentElement.querySelector('.toggle-slider');
             const wasChecked = !checkbox.checked;
             
             const formData = new FormData();
             formData.append('ajax', 'toggle_estado');
             formData.append('id', id);
             const response = await fetch('', { method: 'POST', body: formData });
             const data = await response.json();
             
             if (data.success) {
                 checkbox.checked = data.activo;
                 
                 if (data.activo) {
                     slider.classList.add('activating');
                     mostrarNotificacion('Usuario ACTIVADO correctamente', 'success');
                 } else {
                     slider.classList.add('deactivating');
                     mostrarNotificacion('Usuario DESACTIVADO', 'error');
                 }
                 
                 setTimeout(() => {
                     slider.classList.remove('activating', 'deactivating');
                 }, 600);
             } else {
                 mostrarNotificacion('Error: ' + data.message, 'error');
                 checkbox.checked = wasChecked;
             }
        }

        async function realizarEliminacion(id) {
            const formData = new FormData();
            formData.append('ajax', 'eliminar_usuario');
            formData.append('id', id);
            const response = await fetch('', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                location.reload();
            } else {
                mostrarNotificacion('Error: ' + data.message, 'error');
            }
        }

        // Abrir modal de edición
        async function abrirModalEditar(id) {
            const formData = new FormData();
            formData.append('ajax', 'obtener_usuario');
            formData.append('id', id);

            const response = await fetch('', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                document.getElementById('editar_id').value = data.usuario.id_usuario;
                document.getElementById('editar_es_admin_principal').value = data.usuario.es_admin_principal ? '1' : '0';
                document.getElementById('editar_nombre').value = data.usuario.nombre;
                document.getElementById('editar_apellido').value = data.usuario.apellido;
                document.getElementById('editar_password').value = '';
                document.getElementById('editar_rol').value = data.usuario.rol;

                // Si es admin principal, deshabilitar cambio de rol
                const grupoRol = document.getElementById('grupo_rol');
                const selectRol = document.getElementById('editar_rol');
                if (data.usuario.es_admin_principal) {
                    selectRol.disabled = true;
                    grupoRol.style.opacity = '0.5';
                    grupoRol.title = 'No se puede cambiar el rol del administrador principal';
                } else {
                    selectRol.disabled = false;
                    grupoRol.style.opacity = '1';
                    grupoRol.title = '';
                }

                document.getElementById('modalEditar').classList.add('active');
            } else {
                mostrarErrorSeguridad('Error: ' + data.message);
            }
        }

        // Cerrar modal
        function cerrarModal() {
            document.getElementById('modalEditar').classList.remove('active');
        }

        // Guardar edición
        async function guardarEdicion() {
            // Ocultar error previo
            document.getElementById('errorEditar').style.display = 'none';

            const formData = new FormData();
            formData.append('ajax', 'guardar_usuario');
            formData.append('id', document.getElementById('editar_id').value);
            formData.append('nombre', document.getElementById('editar_nombre').value);
            formData.append('apellido', document.getElementById('editar_apellido').value);
            formData.append('password', document.getElementById('editar_password').value);
            formData.append('rol', document.getElementById('editar_rol').value);

            const response = await fetch('', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                cerrarModal();
                location.reload();
            } else {
                // Mostrar error estilizado en el modal
                const errorDiv = document.getElementById('errorEditar');
                document.getElementById('errorEditarTexto').textContent = data.message;
                errorDiv.style.display = 'flex';
                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        // Toggle estado activo/inactivo (con seguridad)
        function toggleEstado(id, checkbox) {
            checkbox.checked = !checkbox.checked; // Revertir visualmente
            accionPendiente = { tipo: 'toggle', id: id, element: checkbox };
            mostrarModalSeguridad();
        }

        // Eliminar usuario (con seguridad)
        function eliminarUsuario(id, nombre) {
            if (!confirm(`¿Estás seguro de ELIMINAR al usuario "${nombre}"?\n\nEsta acción no se puede deshacer.`)) {
                return;
            }
            accionPendiente = { tipo: 'eliminar', id: id };
            mostrarModalSeguridad();
        }

        // Solicitar registro (pide contraseña primero)
        function solicitarRegistro(event) {
            event.preventDefault();
            
            // Validar campos del formulario
            const nombre = document.getElementById('reg_nombre').value.trim();
            const apellido = document.getElementById('reg_apellido').value.trim();
            const password = document.getElementById('reg_password').value;
            const passwordConfirm = document.getElementById('reg_password_confirm').value;
            
            if (!nombre || !apellido || !password || !passwordConfirm) {
                mostrarNotificacion('Todos los campos son obligatorios', 'error');
                return false;
            }
            
            if (password !== passwordConfirm) {
                mostrarNotificacion('Las contraseñas no coinciden', 'error');
                return false;
            }
            
            if (password.length < 6) {
                mostrarNotificacion('La contraseña debe tener al menos 6 caracteres', 'error');
                return false;
            }
            
            if (!/^[A-Za-z0-9]+$/.test(password)) {
                mostrarNotificacion('La contraseña solo puede contener letras y números', 'error');
                return false;
            }
            
            // Pedir contraseña de admin
            accionPendiente = { tipo: 'registro' };
            mostrarModalSeguridad();
            return false;
        }

        // Cerrar modales con ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                cerrarModal();
                cerrarModalSeguridad();
            }
        });

        // Cerrar modal al hacer clic fuera
        document.getElementById('modalEditar').addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) cerrarModal();
        });
        document.getElementById('modalSeguridad').addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) cerrarModalSeguridad();
        });
    </script>
</body>

</html>