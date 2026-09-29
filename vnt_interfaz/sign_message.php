<?php
// vnt_interfaz/sign_message.php
// Firma mensajes de QZ Tray para permitir "Remember this decision".
// Solo para personal con sesión: sin esto cualquiera podría usar la llave como oráculo de firma.

require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth_guard.php';
teatro_require_login(false);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

// Fuera del repositorio en producción: QZ_PRIVATE_KEY_PATH (archivo) o QZ_PRIVATE_KEY (PEM en variable de entorno).
$pem = (string) teatro_env('QZ_PRIVATE_KEY', '');
if ($pem === '') {
    $keyPath = (string) teatro_env('QZ_PRIVATE_KEY_PATH', __DIR__ . '/utils/qz_private.key');
    $pem = is_readable($keyPath) ? (string) file_get_contents($keyPath) : '';
}
$pem = str_replace('\n', "\n", $pem);

$req = isset($_GET['request']) ? (string) $_GET['request'] : '';
if ($req === '' || strlen($req) > 8192) {
    http_response_code(400);
    echo "Error: No request provided";
    exit;
}

if ($pem === '') {
    http_response_code(503);
    echo "Error: Private key not found";
    exit;
}

$privateKey = openssl_pkey_get_private($pem);
if (!$privateKey) {
    http_response_code(503);
    echo "Error: Invalid private key";
    exit;
}

$signature = null;
if (openssl_sign($req, $signature, $privateKey, "sha512")) { // QZ 2.x usa SHA512
    echo base64_encode($signature);
} else {
    http_response_code(500);
    echo "Error: Signing failed";
}
