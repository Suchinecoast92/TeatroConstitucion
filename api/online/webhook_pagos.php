<?php
/**
 * Webhook / IPN de Mercado Pago.
 * La confirmación real del pago ocurre aquí, no en el return URL del navegador.
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

// Validar secreto si está configurado (debe ir en notification_url ?secret=)
$secret = (string) teatro_env('MP_WEBHOOK_SECRET', '');
if ($secret !== '') {
    $notifSecret = (string) ($_GET['secret'] ?? '');
    if ($notifSecret === '' || !hash_equals($secret, $notifSecret)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'unauthorized']);
        exit;
    }
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
