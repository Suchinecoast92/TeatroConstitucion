<?php
/**
 * Guards de autenticación / entorno para endpoints sensibles.
 */

/**
 * Exige sesión de usuario autenticado.
 */
function teatro_require_login(bool $asJson = false): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (function_exists('teatro_harden_session_cookie')) {
            teatro_harden_session_cookie();
        } elseif (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_start();
    }
    if (!empty($_SESSION['usuario_id'])) {
        return;
    }
    if ($asJson) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'ok' => false, 'error' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(403);
    die('Acceso denegado');
}

/**
 * Exige sesión con rol admin.
 */
function teatro_require_admin(bool $asJson = false): void
{
    teatro_require_login($asJson);
    if (($_SESSION['usuario_rol'] ?? '') === 'admin') {
        return;
    }
    if ($asJson) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'ok' => false, 'error' => 'Solo administradores'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(403);
    die('Solo administradores');
}

/**
 * Bloquea ejecución vía HTTP (scripts de mantenimiento / debug).
 */
function teatro_require_cli(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden: solo CLI\n";
    exit;
}
