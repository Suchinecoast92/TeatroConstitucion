<?php
/**
 * Protección CSRF basada en token de sesión.
 *
 * Uso:
 *   require_once __DIR__ . '/csrf.php';
 *   // En HTML: <?= teatro_csrf_field() ?>  o  <?= teatro_csrf_meta() ?>
 *   // En endpoints que mutan: teatro_require_csrf($asJson);
 */

if (defined('TEATRO_CSRF_INCLUDED')) {
    return;
}
define('TEATRO_CSRF_INCLUDED', true);

/**
 * Endurece cookie de sesión (llamar ANTES de session_start si es posible).
 */
function teatro_harden_session_cookie(): void
{
    if (session_status() !== PHP_SESSION_NONE || headers_sent()) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function teatro_csrf_ensure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        teatro_harden_session_cookie();
        session_start();
    }
}

function teatro_csrf_token(): string
{
    teatro_csrf_ensure_session();
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function teatro_csrf_field(): string
{
    $t = htmlspecialchars(teatro_csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $t . '">';
}

function teatro_csrf_meta(): string
{
    $t = htmlspecialchars(teatro_csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<meta name="csrf-token" content="' . $t . '">';
}

/**
 * Lee el token enviado por formulario, header o JSON body.
 */
function teatro_csrf_token_from_request(): string
{
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRFTOKEN'] ?? '';
    if (is_string($header) && $header !== '') {
        return $header;
    }
    if (isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])) {
        return $_POST['csrf_token'];
    }
    $raw = file_get_contents('php://input');
    if ($raw) {
        $json = json_decode($raw, true);
        if (is_array($json) && isset($json['csrf_token']) && is_string($json['csrf_token'])) {
            // php://input solo se puede leer una vez; guardar para reuso
            $GLOBALS['_teatro_csrf_json_body'] = $json;
            return $json['csrf_token'];
        }
    }
    return '';
}

/**
 * Body JSON ya parseado (si teatro_csrf_token_from_request lo leyó).
 */
function teatro_csrf_consumed_json_body(): ?array
{
    return isset($GLOBALS['_teatro_csrf_json_body']) && is_array($GLOBALS['_teatro_csrf_json_body'])
        ? $GLOBALS['_teatro_csrf_json_body']
        : null;
}

function teatro_csrf_validate(?string $token = null): bool
{
    teatro_csrf_ensure_session();
    $expected = $_SESSION['_csrf_token'] ?? '';
    if (!is_string($expected) || $expected === '') {
        return false;
    }
    $got = $token ?? teatro_csrf_token_from_request();
    return is_string($got) && $got !== '' && hash_equals($expected, $got);
}

/**
 * Aborta con 403 si el token CSRF no es válido.
 */
function teatro_require_csrf(bool $asJson = false): void
{
    if (teatro_csrf_validate()) {
        return;
    }
    if ($asJson) {
        http_response_code(403);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'ok' => false,
            'error' => 'CSRF inválido',
            'message' => 'CSRF inválido',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(403);
    die('Solicitud rechazada (CSRF). Recarga la página e intenta de nuevo.');
}

/**
 * Helper JS mínimo para fetch (usar en páginas con meta csrf-token).
 */
function teatro_csrf_js_snippet(): string
{
    return <<<'JS'
window.teatroCsrfToken = function () {
  var m = document.querySelector('meta[name="csrf-token"]');
  return m ? m.getAttribute('content') : '';
};
window.teatroCsrfHeaders = function (extra) {
  var h = Object.assign({'X-CSRF-Token': window.teatroCsrfToken()}, extra || {});
  return h;
};
window.teatroCsrfAppend = function (fd) {
  if (fd && typeof fd.append === 'function') {
    fd.append('csrf_token', window.teatroCsrfToken());
  }
  return fd;
};
JS;
}
