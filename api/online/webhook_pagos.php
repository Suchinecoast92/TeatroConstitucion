<?php
/**
 * Webhook / IPN de Mercado Pago.
 * La confirmación real del pago ocurre aquí, no en el return URL del navegador.
 *
 * Autenticación: firma HMAC en header x-signature (MP_WEBHOOK_SECRET).
 * No se aceptan secretos en query string.
 */

require_once __DIR__ . '/_bootstrap.php';
// Responder rápido; MP reintenta si falla
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/pagos/PaymentService.php';

$conn = getLocalConnection();
if (!$conn) {
    http_response_code(503);
    echo json_encode(['success' => false]);
    exit;
}

$secret = (string) teatro_env('MP_WEBHOOK_SECRET', '');
$env = strtolower((string) teatro_env('APP_ENV', 'local'));
$isProd = in_array($env, ['production', 'prod'], true);
$isMockHttp = !empty($_GET['mock']);

// Mock por HTTP solo en entornos donde el mock está permitido (sin firma MP).
if ($isMockHttp) {
    if (!payment_mock_permitido()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'mock disabled']);
        exit;
    }
} elseif ($secret === '') {
    if ($isProd) {
        error_log('[webhook_pagos] MP_WEBHOOK_SECRET vacío en producción — rechazando');
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'webhook misconfigured']);
        exit;
    }
    error_log('[webhook_pagos] MP_WEBHOOK_SECRET vacío — aceptando solo en entorno no productivo');
} elseif (!payment_verificar_firma_mp($secret, $_SERVER, $_GET)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$body = $raw ? (json_decode($raw, true) ?: []) : [];
if (!is_array($body)) {
    $body = [];
}

try {
    $r = payment_procesar_webhook($conn, $_GET, $body);
    // Siempre 200 si procesamos o ignoramos a propósito (evitar reintentos infinitos por ignored)
    http_response_code(!empty($r['success']) ? 200 : 400);
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[webhook_pagos] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'internal']);
}
