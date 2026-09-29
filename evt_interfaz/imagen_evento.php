<?php
// Respaldo de /evt_interfaz/imagenes/<archivo> cuando el archivo no está en disco (.htaccess
// reescribe aquí): lo sirve desde evento_imagenes y lo deja restaurado para las siguientes.
// Los carteles son públicos (cartelera), así que no requiere sesión.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/evento_imagen_helper.php';

$archivo = (string) ($_GET['r'] ?? '');
$ruta = 'imagenes/' . $archivo;
if (!teatro_evt_img_ruta_valida($ruta)) {
    http_response_code(404);
    exit;
}

$conn = getLocalConnection();
$fila = $conn ? teatro_evt_img_obtener($conn, $ruta) : null;
if ($fila === null) {
    http_response_code(404);
    header('Cache-Control: no-store');
    exit;
}

teatro_evt_img_restaurar($conn, $ruta, $fila);
$conn->close();

$etag = '"' . $fila['sha256'] . '"';
header('Content-Type: ' . $fila['mime']);
header('Cache-Control: public, max-age=86400');
header('ETag: ' . $etag);
if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}
header('Content-Length: ' . strlen($fila['datos']));
echo $fila['datos'];
