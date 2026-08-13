<?php
session_start();

require_once 'transacciones_helper.php';
if (isset($_SESSION['usuario_id'])) {
    registrar_transaccion('logout', 'Cierre de sesión');
}

// Limpiar sesión y cookie para que "atrás" no reutilice la sesión
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
session_destroy();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Location: login.php');
exit();
