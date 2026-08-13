<?php
// vnt_interfaz/sign_message.php
// Firma mensajes de QZ Tray para permitir "Remember this decision"

header('Access-Control-Allow-Origin: *'); // Ajustar según seguridad deseada
header('Content-Type: text/plain');

$KEY = 'utils/qz_private.key'; // Asegurarse que la ruta sea correcta desde este archivo
$req = $_GET['request']; // QZ manda el string a firmar en ?request=

if (empty($req)) {
    echo "Error: No request provided";
    exit;
}

if (!file_exists($KEY)) {
    echo "Error: Private key not found";
    exit;
}

$privateKey = openssl_get_privatekey(file_get_contents($KEY));

if (!$privateKey) {
    echo "Error: Invalid private key";
    exit;
}

$signature = null;
if (openssl_sign($req, $signature, $privateKey, "sha512")) { // QZ usa SHA512 (o SHA1 en versiones viejas, 2.0+ usa SHA512)
    $signed = base64_encode($signature);
    echo $signed;
} else {
    echo "Error: Signing failed";
}

// Liberar clave (opcional en PHP moderno)
// openssl_free_key($privateKey);
