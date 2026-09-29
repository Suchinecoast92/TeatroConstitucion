<?php
/**
 * Carteles de eventos: evento.imagen guarda "imagenes/<archivo>" relativo a evt_interfaz/.
 *
 * La copia autoritativa vive en la tabla evento_imagenes (BD central, entra en los backups);
 * el archivo en disco es caché. En App Platform el disco se borra en cada despliegue, así que
 * al arrancar se restauran (sql/sincronizar_imagenes_eventos.php) y, si aun así falta alguno,
 * evt_interfaz/imagen_evento.php lo sirve desde la BD.
 */

if (defined('TEATRO_EVENTO_IMAGEN_HELPER_INCLUDED')) {
    return;
}
define('TEATRO_EVENTO_IMAGEN_HELPER_INCLUDED', true);

const TEATRO_EVT_IMG_MAX_BYTES = 12 * 1024 * 1024;

function teatro_evt_img_base_dir(): string
{
    return dirname(__DIR__) . '/evt_interfaz/';
}

function teatro_evt_img_ruta_valida(string $ruta): bool
{
    return (bool) preg_match('~^imagenes/[A-Za-z0-9_-]{1,100}\.(jpe?g|png|gif)$~i', $ruta);
}

function teatro_evt_img_ruta_nueva(string $ext): string
{
    return 'imagenes/evt_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
}

function teatro_evt_img_asegurar_tabla(mysqli $conn): void
{
    static $ok = false;
    if ($ok) {
        return;
    }
    // CREATE TABLE hace commit implícito: llamar antes de abrir una transacción.
    $conn->query("
        CREATE TABLE IF NOT EXISTS evento_imagenes (
            ruta VARCHAR(255) NOT NULL,
            mime VARCHAR(50) NOT NULL,
            bytes INT UNSIGNED NOT NULL,
            sha256 CHAR(64) NOT NULL,
            datos MEDIUMBLOB NOT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (ruta)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    $ok = true;
}

/**
 * Copia a la BD el archivo ya guardado en disco para $ruta.
 */
function teatro_evt_img_guardar(mysqli $conn, string $ruta): bool
{
    if (!teatro_evt_img_ruta_valida($ruta)) {
        return false;
    }
    $path = teatro_evt_img_base_dir() . $ruta;
    $size = is_file($path) ? (int) filesize($path) : 0;
    if ($size <= 0 || $size > TEATRO_EVT_IMG_MAX_BYTES) {
        return false;
    }
    $info = @getimagesize($path);
    $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
    // WebP: hay carteles antiguos guardados con extensión .jpg antes de validar el tipo real.
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
        return false;
    }
    $datos = file_get_contents($path);
    if ($datos === false) {
        return false;
    }
    try {
        teatro_evt_img_asegurar_tabla($conn);
        $sha = hash('sha256', $datos);
        $stmt = $conn->prepare("
            INSERT INTO evento_imagenes (ruta, mime, bytes, sha256, datos) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE mime = VALUES(mime), bytes = VALUES(bytes), sha256 = VALUES(sha256), datos = VALUES(datos)
        ");
        $stmt->bind_param('ssiss', $ruta, $mime, $size, $sha, $datos);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool) $ok;
    } catch (Throwable $e) {
        error_log('[evento_imagen] no se pudo guardar ' . $ruta . ': ' . $e->getMessage());
        return false;
    }
}

/**
 * @return array{mime:string,sha256:string,datos:string}|null
 */
function teatro_evt_img_obtener(mysqli $conn, string $ruta): ?array
{
    if (!teatro_evt_img_ruta_valida($ruta)) {
        return null;
    }
    try {
        teatro_evt_img_asegurar_tabla($conn);
        $stmt = $conn->prepare('SELECT mime, sha256, datos FROM evento_imagenes WHERE ruta = ?');
        $stmt->bind_param('s', $ruta);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    } catch (Throwable $e) {
        error_log('[evento_imagen] no se pudo leer ' . $ruta . ': ' . $e->getMessage());
        return null;
    }
}

/**
 * Escribe en disco la copia de la BD si el archivo falta.
 */
function teatro_evt_img_restaurar(mysqli $conn, string $ruta, ?array $fila = null): bool
{
    if (!teatro_evt_img_ruta_valida($ruta)) {
        return false;
    }
    $path = teatro_evt_img_base_dir() . $ruta;
    if (is_file($path)) {
        return true;
    }
    $fila = $fila ?? teatro_evt_img_obtener($conn, $ruta);
    if ($fila === null) {
        return false;
    }
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $fila['datos']) === false) {
        return false;
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return is_file($path);
    }
    return true;
}

/**
 * Borra archivo y copia en BD solo si ningún evento (activo o archivado) la usa.
 */
function teatro_evt_img_eliminar_si_huerfana(mysqli $conn, string $ruta): void
{
    if (!teatro_evt_img_ruta_valida($ruta)) {
        return;
    }
    try {
        $stmt = $conn->prepare('SELECT 1 FROM evento WHERE imagen = ? LIMIT 1');
        $stmt->bind_param('s', $ruta);
        $stmt->execute();
        $enUso = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();
        if (!$enUso) {
            $stmt = $conn->prepare('SELECT 1 FROM trt_historico_evento.evento WHERE imagen = ? LIMIT 1');
            $stmt->bind_param('s', $ruta);
            $stmt->execute();
            $enUso = (bool) $stmt->get_result()->fetch_row();
            $stmt->close();
        }
    } catch (Throwable $e) {
        // Sin poder confirmar que está libre, se conserva.
        error_log('[evento_imagen] no se pudo verificar uso de ' . $ruta . ': ' . $e->getMessage());
        return;
    }
    if ($enUso) {
        return;
    }
    try {
        teatro_evt_img_asegurar_tabla($conn);
        $stmt = $conn->prepare('DELETE FROM evento_imagenes WHERE ruta = ?');
        $stmt->bind_param('s', $ruta);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('[evento_imagen] no se pudo borrar ' . $ruta . ' de la BD: ' . $e->getMessage());
        return;
    }
    $path = teatro_evt_img_base_dir() . $ruta;
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * Disco → BD para archivos sin copia, y BD → disco para los que faltan.
 *
 * @return array{importadas:int,restauradas:int,errores:string[],sin_origen:string[]}
 */
function teatro_evt_img_sincronizar(mysqli $conn): array
{
    teatro_evt_img_asegurar_tabla($conn);
    $res = ['importadas' => 0, 'restauradas' => 0, 'errores' => [], 'sin_origen' => []];

    $enBd = [];
    $q = $conn->query('SELECT ruta FROM evento_imagenes');
    while ($row = $q->fetch_row()) {
        $enBd[$row[0]] = true;
    }
    $q->free();

    $dir = teatro_evt_img_base_dir() . 'imagenes/';
    foreach (is_dir($dir) ? scandir($dir) : [] as $archivo) {
        $ruta = 'imagenes/' . $archivo;
        if (!teatro_evt_img_ruta_valida($ruta) || isset($enBd[$ruta])) {
            continue;
        }
        if (teatro_evt_img_guardar($conn, $ruta)) {
            $enBd[$ruta] = true;
            $res['importadas']++;
        } else {
            $res['errores'][] = 'importar ' . $ruta;
        }
    }

    foreach (array_keys($enBd) as $ruta) {
        if (is_file(teatro_evt_img_base_dir() . $ruta)) {
            continue;
        }
        if (teatro_evt_img_restaurar($conn, $ruta)) {
            $res['restauradas']++;
        } else {
            $res['errores'][] = 'restaurar ' . $ruta;
        }
    }

    $q = $conn->query("SELECT DISTINCT imagen FROM evento WHERE imagen IS NOT NULL AND imagen <> ''");
    while ($row = $q->fetch_row()) {
        if (!isset($enBd[$row[0]])) {
            $res['sin_origen'][] = $row[0];
        }
    }
    $q->free();

    return $res;
}
