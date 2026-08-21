<?php
/**
 * Utilidades compartidas para la API pública online (sin CORS abierto).
 */

if (defined('API_ONLINE_BOOTSTRAP_INCLUDED')) {
    return;
}
define('API_ONLINE_BOOTSTRAP_INCLUDED', true);

/**
 * Headers JSON seguros para endpoints públicos.
 */
function api_online_json_headers(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
}

/**
 * Rate limit simple por IP + bucket (archivo temporal).
 * @return bool true si se permite la petición
 */
function api_online_rate_limit(string $bucket, int $maxRequests = 60, int $windowSeconds = 60): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = preg_replace('/[^a-zA-Z0-9._-]/', '_', $bucket . '_' . $ip);
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'teatro_rl_' . $key . '.json';

    $now = time();
    $data = ['start' => $now, 'count' => 0];

    if (is_readable($file)) {
        $raw = file_get_contents($file);
        $parsed = $raw ? json_decode($raw, true) : null;
        if (is_array($parsed) && isset($parsed['start'], $parsed['count'])) {
            $data = $parsed;
        }
    }

    if (($now - (int) $data['start']) >= $windowSeconds) {
        $data = ['start' => $now, 'count' => 0];
    }

    $data['count'] = (int) $data['count'] + 1;
    @file_put_contents($file, json_encode($data), LOCK_EX);

    return $data['count'] <= $maxRequests;
}

/**
 * Respuesta JSON y salida.
 */
function api_online_respond(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Lee body JSON + query/post.
 */
function api_online_input(): array
{
    $rawBody = file_get_contents('php://input');
    $body = $rawBody ? (json_decode($rawBody, true) ?: []) : [];
    if (!is_array($body)) {
        $body = [];
    }
    return array_merge($_GET, $_POST, $body);
}
