<?php
/**
 * Configuración de Base de Datos (versión STANDALONE / local)
 * ===========================================================
 * El sistema funciona de forma independiente contra una sola base de datos
 * local. Se eliminó toda la lógica de servidor remoto / sincronización online.
 * Se conservan los nombres de las funciones para mantener la compatibilidad
 * con el resto del código.
 */

// Protección contra inclusión múltiple
if (defined('DATABASE_CONFIG_INCLUDED')) {
    return;
}
define('DATABASE_CONFIG_INCLUDED', true);

// Configuración del servidor LOCAL (WAMP/XAMPP)
define('DB_LOCAL_HOST', 'localhost');
define('DB_LOCAL_USER', 'root');
define('DB_LOCAL_PASS', '');
define('DB_LOCAL_NAME', 'trt_25');

// Servidor primario (siempre local en modo standalone)
define('PRIMARY_SERVER', 'local');

// Tiempo de espera para conexiones (en segundos)
define('DB_TIMEOUT', 5);

// Modo de sincronización: 'manual' = sin sincronización (standalone)
define('SYNC_MODE', 'manual');

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
ini_set('default_charset', 'UTF-8');

/**
 * Obtener conexión a la base de datos local
 */
function getLocalConnection() {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $conn = new mysqli(DB_LOCAL_HOST, DB_LOCAL_USER, DB_LOCAL_PASS, DB_LOCAL_NAME);
        $conn->set_charset('utf8mb4');
        $conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, DB_TIMEOUT);
        return $conn;
    } catch (Exception $e) {
        error_log("Error conexión LOCAL: " . $e->getMessage());
        return null;
    }
}

/**
 * Conexión al servidor primario (local en modo standalone)
 */
function getPrimaryConnection() {
    return getLocalConnection();
}

/**
 * Conexión secundaria. En modo standalone no existe; devuelve null.
 */
function getSecondaryConnection() {
    return null;
}

/**
 * Verificar estado de la conexión local
 */
function checkConnectionsStatus() {
    $status = [
        'local' => ['connected' => false, 'latency' => 0, 'error' => null],
    ];

    $start = microtime(true);
    $localConn = getLocalConnection();
    if ($localConn) {
        $status['local']['connected'] = true;
        $status['local']['latency'] = round((microtime(true) - $start) * 1000, 2);
        $localConn->close();
    } else {
        $status['local']['error'] = 'No se pudo conectar al servidor local';
    }

    return $status;
}
?>
