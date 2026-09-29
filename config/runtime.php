<?php
/**
 * Ajustes de ejecución según entorno. Se carga desde config/env.php.
 *
 * - Producción (APP_ENV=production): sin errores en pantalla, solo en log.
 * - Detrás de proxy (TRUST_PROXY=1: DigitalOcean App Platform / Cloudflare):
 *   HTTPS e IP real del cliente desde cabeceras del proxy. Solo activar cuando
 *   la app SOLO es alcanzable a través del proxy; si no, las cabeceras son falsificables.
 * - Cookies de sesión HttpOnly + SameSite=Lax (+ Secure cuando la petición es HTTPS).
 */

if (defined('TEATRO_RUNTIME_INCLUDED')) {
    return;
}
define('TEATRO_RUNTIME_INCLUDED', true);

function teatro_es_produccion(): bool
{
    $env = strtolower((string) teatro_env('APP_ENV', 'local'));
    return $env === 'production' || $env === 'prod';
}

function teatro_confia_proxy(): bool
{
    return (string) teatro_env('TRUST_PROXY', '0') === '1';
}

function teatro_request_is_https(): bool
{
    return !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
}

/** REMOTE_ADDR tal como llegó al servidor (antes de normalizar por proxy). */
function teatro_remote_addr_original(): string
{
    return (string) ($_SERVER['TEATRO_REMOTE_ADDR_ORIG'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
}

if (!function_exists('teatro_client_ip')) {
    function teatro_client_ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return is_string($ip) && $ip !== '' ? $ip : '0.0.0.0';
    }
}

function teatro_normalizar_proxy(): void
{
    if (PHP_SAPI === 'cli' || !teatro_confia_proxy() || isset($_SERVER['TEATRO_REMOTE_ADDR_ORIG'])) {
        return;
    }
    $_SERVER['TEATRO_REMOTE_ADDR_ORIG'] = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    $ip = '';
    $cf = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
        $ip = $cf;
    } else {
        foreach (explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')) as $cand) {
            $cand = trim($cand);
            if ($cand !== '' && filter_var($cand, FILTER_VALIDATE_IP)) {
                $ip = $cand;
                break;
            }
        }
    }
    if ($ip !== '') {
        $_SERVER['REMOTE_ADDR'] = $ip;
    }

    $proto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    if ($proto === 'https') {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_PORT'] = 443;
        $_SERVER['REQUEST_SCHEME'] = 'https';
    }
}

function teatro_configurar_errores(): void
{
    if (!teatro_es_produccion()) {
        return;
    }
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set('html_errors', '0');
    ini_set('expose_php', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

    set_exception_handler(static function (Throwable $e): void {
        error_log('[teatro] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) {
            http_response_code(500);
            $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
            $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
            if (strpos($accept, 'application/json') !== false || strpos($uri, '/api/') !== false) {
                header('Content-Type: application/json; charset=utf-8');
                echo '{"success":false,"error":"Error interno"}';
                return;
            }
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo 'Ocurrió un error interno. Intenta de nuevo más tarde.';
    });
}

function teatro_configurar_sesion(): void
{
    if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_NONE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (teatro_request_is_https()) {
        ini_set('session.cookie_secure', '1');
    }
}

/**
 * Ruta base de la app en la URL ('' si está en la raíz del dominio,
 * '/TeatroConstitucion' en WAMP).
 */
function teatro_app_base_path(): string
{
    $root = realpath(dirname(__DIR__));
    $doc = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($root && $doc) {
        $root = str_replace('\\', '/', $root);
        $doc = rtrim(str_replace('\\', '/', $doc), '/');
        if (stripos($root, $doc) === 0) {
            return rtrim(substr($root, strlen($doc)), '/');
        }
    }
    return '';
}

/**
 * Separación por dominio (defensa en profundidad; la seguridad real es login + rol + validación backend).
 * Activa solo si están definidos HOST_WWW / HOST_GESTION / HOST_API. Sin ellos (local) no hace nada.
 *   www     → cartelera, compra online y su API pública. Lo demás redirige a gestion.
 *   gestion → login único + paneles por rol (admin, taquilla, control de entrada).
 *   api     → solo /api/ (webhooks e integraciones).
 */
function teatro_host_guard(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $www = strtolower(trim((string) teatro_env('HOST_WWW', '')));
    $gestion = strtolower(trim((string) teatro_env('HOST_GESTION', '')));
    $api = strtolower(trim((string) teatro_env('HOST_API', '')));
    if ($www === '' && $gestion === '' && $api === '') {
        return;
    }

    // Con un proxy propio que reescribe Host (Cloudflare → *.ondigitalocean.app) el dominio original
    // debe llegar en X-Forwarded-Host (Transform Rule en Cloudflare).
    $hostCrudo = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (teatro_confia_proxy() && !empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        $hostCrudo = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_HOST'])[0]);
    }
    $host = strtolower((string) preg_replace('/:\d+$/', '', $hostCrudo));
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $base = teatro_app_base_path();
    if ($base !== '' && stripos($path, $base) === 0) {
        $path = substr($path, strlen($base));
    }
    $path = '/' . ltrim($path, '/');
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    $scheme = teatro_request_is_https() ? 'https' : 'http';

    if ($api !== '' && $host === $api) {
        if (strpos($path, '/api/') !== 0) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo '{"success":false,"error":"No encontrado"}';
            exit;
        }
        return;
    }

    if ($www !== '' && $host === $www) {
        if ($path === '/' || $path === '/index.php') {
            header('Location: ' . $base . '/crt_interfaz/', true, 302);
            exit;
        }
        $publicos = ['/crt_interfaz/', '/api/online/', '/assets/', '/evt_interfaz/imagenes/', '/boletos_qr/'];
        foreach ($publicos as $prefijo) {
            if (strpos($path, $prefijo) === 0) {
                return;
            }
        }
        if (in_array($path, ['/favicon.ico', '/robots.txt', '/evt_interfaz/imagen_evento.php'], true)) {
            return;
        }
        if ($gestion !== '') {
            header('Location: ' . $scheme . '://' . $gestion . $base . $path . ($query !== '' ? '?' . $query : ''), true, 302);
            exit;
        }
        http_response_code(404);
        exit;
    }

    // Host no reconocido (p. ej. *.ondigitalocean.app) → dominio oficial. Opt-in: con un proxy que
    // reescribe Host y no envía X-Forwarded-Host provocaría un bucle de redirecciones.
    $redirigirDesconocidos = (string) teatro_env('HOST_REDIRECT_UNKNOWN', '0') === '1';
    if ($redirigirDesconocidos && $host !== $gestion && $www !== '' && $path !== '/health.php') {
        $metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($metodo === 'GET' || $metodo === 'HEAD') {
            header('Location: https://' . $www . $base . $path . ($query !== '' ? '?' . $query : ''), true, 302);
        } else {
            http_response_code(421);
        }
        exit;
    }
}

teatro_normalizar_proxy();
teatro_configurar_errores();
teatro_configurar_sesion();
teatro_host_guard();
