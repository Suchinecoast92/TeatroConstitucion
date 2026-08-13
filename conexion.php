<?php
/**
 * Conexión a la base de datos (versión STANDALONE / local)
 * ========================================================
 * Sistema independiente: una sola base de datos local (trt_25).
 * Se eliminó la sincronización dual con servidor remoto / online.
 */

// Protección contra inclusión múltiple
if (defined('CONEXION_PHP_INCLUDED')) {
    return;
}
define('CONEXION_PHP_INCLUDED', true);

require_once __DIR__ . '/config/database.php';

// Conexión principal (local)
$conn = getPrimaryConnection();

if ($conn === null || $conn->connect_error) {
    die("Error de conexión: No se pudo conectar a la base de datos local");
}

$conn->set_charset("utf8mb4");
$conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
?>
