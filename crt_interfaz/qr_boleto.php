<?php
/**
 * Sirve el PNG del QR de un boleto existente, regenerándolo si el archivo no está
 * (disco efímero en App Platform). ?c=CODIGO_UNICO
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/qr_helper.php';

$codigo = isset($_GET['c']) ? trim((string) $_GET['c']) : '';
if (!teatro_qr_codigo_valido($codigo)) {
    http_response_code(400);
    exit;
}

$path = teatro_qr_dir() . $codigo . '.png';
if (!is_file($path)) {
    $conn = getLocalConnection();
    if (!$conn) {
        http_response_code(503);
        exit;
    }
    $st = $conn->prepare('SELECT 1 FROM boletos WHERE codigo_unico = ? LIMIT 1');
    $st->bind_param('s', $codigo);
    $st->execute();
    $existe = (bool) $st->get_result()->fetch_row();
    $st->close();
    if (!$existe) {
        http_response_code(404);
        exit;
    }
    $path = teatro_qr_asegurado($codigo);
    if ($path === null) {
        http_response_code(500);
        exit;
    }
}

header('Content-Type: image/png');
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($path));
readfile($path);
