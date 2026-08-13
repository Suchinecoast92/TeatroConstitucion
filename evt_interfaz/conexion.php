<?php
/**
 * Conexión BD — usa la configuración central con UTF-8 (utf8mb4).
 */
if (defined('EVT_CONEXION_INCLUDED')) {
    return;
}
define('EVT_CONEXION_INCLUDED', true);

require_once __DIR__ . '/../config/database.php';

$conn = getLocalConnection();

if ($conn === null) {
    die('Error de conexión: No se pudo conectar a la base de datos');
}
