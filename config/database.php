<?php
/**
 * Configuración de Base de Datos (versión STANDALONE / local)
 * ===========================================================
 * El sistema funciona de forma independiente contra una sola base de datos
 * local. Se eliminó toda la lógica de servidor remoto / sincronización online.
 * Se conservan los nombres de las funciones para mantener la compatibilidad
 * con el resto del código.
 *
 * Valores por defecto = WAMP local. Si existe `.env` en la raíz, puede
 * sobrescribir host/usuario/clave/nombre de BD sin tocar este archivo.
 */

// Protección contra inclusión múltiple
if (defined('DATABASE_CONFIG_INCLUDED')) {
    return;
}
define('DATABASE_CONFIG_INCLUDED', true);

require_once __DIR__ . '/env.php';

// Configuración del servidor LOCAL (WAMP/XAMPP) — override opcional vía .env
define('DB_LOCAL_HOST', teatro_env('DB_HOST', 'localhost'));
define('DB_LOCAL_USER', teatro_env('DB_USER', 'root'));
define('DB_LOCAL_PASS', teatro_env('DB_PASS', ''));
define('DB_LOCAL_NAME', teatro_env('DB_NAME', 'trt_25'));
define('DB_LOCAL_PORT', (int) teatro_env('DB_PORT', 3306));

// TLS hacia MySQL (obligatorio en DigitalOcean Managed MySQL).
// DB_SSL=1 activa TLS; con DB_SSL_CA (ruta) o DB_SSL_CA_CERT (contenido PEM) además se verifica el certificado.
define('DB_SSL', (string) teatro_env('DB_SSL', '0') === '1');

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
 * Ruta del CA para TLS: DB_SSL_CA (archivo) o DB_SSL_CA_CERT (PEM en variable de entorno,
 * p. ej. ${db.CA_CERT} en App Platform) volcado a un archivo temporal.
 */
function teatro_db_ssl_ca_path(): ?string
{
    $path = (string) teatro_env('DB_SSL_CA', '');
    if ($path !== '' && is_readable($path)) {
        return $path;
    }
    $pem = (string) teatro_env('DB_SSL_CA_CERT', '');
    if ($pem === '' || strpos($pem, 'BEGIN CERTIFICATE') === false) {
        return null;
    }
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'teatro_db_ca_' . substr(hash('sha256', $pem), 0, 16) . '.pem';
    if (!is_file($tmp)) {
        @file_put_contents($tmp, str_replace('\n', "\n", $pem), LOCK_EX);
    }
    return is_readable($tmp) ? $tmp : null;
}

/**
 * NOW() de MySQL con la misma hora que PHP (APP_TIMEZONE). Managed MySQL corre en UTC y
 * fechas de funciones/holds se guardan en hora local de México (DATETIME).
 * Offset numérico: no depende de que el servidor tenga cargadas las tablas de zonas.
 */
function teatro_db_alinear_zona(mysqli $conn): void
{
    if ((string) teatro_env('DB_ALIGN_TIMEZONE', '1') !== '1') {
        return;
    }
    $offset = (new DateTime('now'))->format('P');
    if (preg_match('/^[+-]\d{2}:\d{2}$/', $offset)) {
        $conn->query("SET time_zone = '" . $offset . "'");
    }
}

/**
 * Obtener conexión a la base de datos local
 */
function getLocalConnection() {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        return teatro_db_connect(DB_LOCAL_NAME);
    } catch (Exception $e) {
        error_log("Error conexión LOCAL: " . $e->getMessage());
        return null;
    }
}

/**
 * Conexión a una BD del mismo servidor (trt_25, trt_25_backup, …) con puerto, TLS y zona
 * horaria configurados. Lanza mysqli_sql_exception si falla (con MYSQLI_REPORT_STRICT).
 */
function teatro_db_connect(string $dbName): mysqli {
    $conn = mysqli_init();
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, DB_TIMEOUT);
    $flags = 0;
    if (DB_SSL) {
        $ca = teatro_db_ssl_ca_path();
        $conn->ssl_set(null, null, $ca, null, null);
        if ($ca !== null) {
            $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
            $flags = MYSQLI_CLIENT_SSL;
        } else {
            $flags = MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
        }
    }
    if (!@$conn->real_connect(DB_LOCAL_HOST, DB_LOCAL_USER, DB_LOCAL_PASS, $dbName, DB_LOCAL_PORT, null, $flags)) {
        throw new mysqli_sql_exception('No se pudo conectar a ' . $dbName . ': ' . mysqli_connect_error());
    }
    $conn->set_charset('utf8mb4');
    $conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    teatro_db_alinear_zona($conn);
    // Managed MySQL usa modo estricto + ONLY_FULL_GROUP_BY; el WAMP local, sql_mode vacío.
    // DB_SQL_MODE (definida, aunque sea vacía) fija el modo de la sesión. Sin definir: modo del servidor.
    $sqlMode = getenv('DB_SQL_MODE');
    if ($sqlMode !== false && preg_match('/^[A-Z_,]*$/', $sqlMode)) {
        $conn->query("SET SESSION sql_mode = '" . $sqlMode . "'");
    }
    return $conn;
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
