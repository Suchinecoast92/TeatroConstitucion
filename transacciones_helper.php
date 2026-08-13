<?php
require_once __DIR__ . '/conexion.php';

// Crear tabla transacciones si no existe (sin FK para evitar errores si usuarios no existe)
try {
    $conn->query("CREATE TABLE IF NOT EXISTS transacciones (
        id_transaccion INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT(11) NOT NULL,
        accion VARCHAR(100) NOT NULL,
        descripcion TEXT NULL,
        datos_json LONGTEXT NULL,
        fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_usuario_fecha (id_usuario, fecha_hora)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Agregar columna datos_json si no existe
    $result = $conn->query("SHOW COLUMNS FROM transacciones LIKE 'datos_json'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE transacciones ADD COLUMN datos_json LONGTEXT NULL AFTER descripcion");
    }
} catch (Exception $e) {
    // Log error but don't crash - transacciones is optional functionality
    error_log("Error creando tabla transacciones: " . $e->getMessage());
}

function registrar_transaccion($accion, $descripcion = null) {
    if (!isset($_SESSION['usuario_id'])) {
        return;
    }

    $id_usuario = (int)$_SESSION['usuario_id'];

    global $conn;

    $stmt = $conn->prepare("INSERT INTO transacciones (id_usuario, accion, descripcion) VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $id_usuario, $accion, $descripcion);
    $stmt->execute();
    $stmt->close();
}

function registrar_transaccion_con_datos($accion, $descripcion = null, $datos_json = null) {
    if (!isset($_SESSION['usuario_id'])) {
        return;
    }

    $id_usuario = (int)$_SESSION['usuario_id'];

    global $conn;

    $stmt = $conn->prepare("INSERT INTO transacciones (id_usuario, accion, descripcion, datos_json) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('isss', $id_usuario, $accion, $descripcion, $datos_json);
    $stmt->execute();
    $stmt->close();
}
