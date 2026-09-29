<?php
/**
 * API de Reservas Temporales (Holds) - Lado LOCAL
 * ================================================
 * Endpoints:
 *   POST  reservas.php?action=reservar     body: {id_evento, id_funcion, asientos[], session_id}
 *   POST  reservas.php?action=liberar      body: {id_evento, id_funcion, asientos[], session_id}
 *   GET   reservas.php?action=activas&id_evento=...&id_funcion=...&session_id=...
 *
 * Delega en el helper compartido de reservas (sync/reservas_helper.php).
 * Solo taquilla con sesión iniciada (mismo origen; sin CORS). Los clientes online usan api/online/reservas.php.
 */

require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/csrf.php';
teatro_require_login(true);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../sync/reservas_helper.php';

const RESERVAS_LOCAL_MAX_RESERVAR = 20;
const RESERVAS_LOCAL_MAX_LIBERAR = 300;

$action = $_GET['action'] ?? '';
$origen = 'local';

if (in_array($action, ['reservar', 'liberar', 'liberar_sesion', 'limpiar'], true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'POST requerido']);
        exit;
    }
    teatro_require_csrf(true);
}

// Aceptar parámetros de body JSON o query string indistintamente
$rawBody = file_get_contents('php://input');
$body = $rawBody ? (json_decode($rawBody, true) ?: []) : [];
$data = array_merge($_GET, $_POST, $body);

$idEvento  = isset($data['id_evento'])  ? (int)$data['id_evento']  : 0;
$idFuncion = isset($data['id_funcion']) && $data['id_funcion'] !== '' && $data['id_funcion'] !== null
    ? (int)$data['id_funcion'] : null;
// Solo sesiones de taquilla (TeatroReservas.sessionId): así no se pueden liberar holds de clientes online.
$sessionId = trim((string) ($data['session_id'] ?? ''));
if (!preg_match('/^taq_[A-Za-z0-9_]{1,80}$/', $sessionId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'session_id inválido']);
    exit;
}
$asientos  = $data['asientos']   ?? [];
if (is_string($asientos)) $asientos = array_filter(array_map('trim', explode(',', $asientos)));
if (!is_array($asientos)) $asientos = [];
$asientos = array_values(array_filter($asientos, static fn($a) => is_string($a) && preg_match('/^[A-Za-z0-9-]{1,20}$/', $a)));
$maxAsientos = $action === 'reservar' ? RESERVAS_LOCAL_MAX_RESERVAR : RESERVAS_LOCAL_MAX_LIBERAR;
if (count($asientos) > $maxAsientos) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Demasiados asientos por solicitud']);
    exit;
}

// Cliente info: si hay sesión, usar nombre del vendedor
$clienteInfo = isset($_SESSION['usuario_nombre'])
    ? ($_SESSION['usuario_nombre'] . ' ' . ($_SESSION['usuario_apellido'] ?? ''))
    : ('usuario #' . (int) $_SESSION['usuario_id']);

try {
    if ($action === 'reservar') {
        if (!$idEvento || empty($asientos)) {
            echo json_encode(['success' => false, 'error' => 'Faltan parámetros']); exit;
        }
        $r = reservarAsientos($idEvento, $idFuncion, $asientos, $sessionId, $origen, $clienteInfo);
        echo json_encode($r);
        exit;
    }

    if ($action === 'liberar') {
        if (!$idEvento) {
            echo json_encode(['success' => false, 'error' => 'Falta id_evento']); exit;
        }
        $n = liberarAsientos($sessionId, $idEvento, $idFuncion, $asientos);
        echo json_encode(['success' => true, 'liberados' => $n]);
        exit;
    }

    if ($action === 'activas') {
        $excluir = $data['exclude_self'] ?? '1';
        $excluirSession = $excluir ? $sessionId : null;
        $list = listarReservasActivas($idEvento, $idFuncion, $excluirSession);
        echo json_encode(['success' => true, 'reservados' => $list]);
        exit;
    }

    if ($action === 'limpiar') {
        $n = limpiarReservasExpiradas();
        echo json_encode(['success' => true, 'expiradas_borradas' => $n]);
        exit;
    }

    if ($action === 'liberar_sesion') {
        if (!$sessionId) {
            echo json_encode(['success' => false, 'error' => 'Falta session_id']); exit;
        }
        $n = liberarReservasSesion($sessionId);
        echo json_encode(['success' => true, 'liberados' => $n]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Acción inválida']);
} catch (Throwable $e) {
    error_log('[api/reservas] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno']);
}
