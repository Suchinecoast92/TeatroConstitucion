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
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../sync/reservas_helper.php';

$action = $_GET['action'] ?? '';
$origen = 'local';

// Aceptar parámetros de body JSON o query string indistintamente
$rawBody = file_get_contents('php://input');
$body = $rawBody ? (json_decode($rawBody, true) ?: []) : [];
$data = array_merge($_GET, $_POST, $body);

$idEvento  = isset($data['id_evento'])  ? (int)$data['id_evento']  : 0;
$idFuncion = isset($data['id_funcion']) && $data['id_funcion'] !== '' && $data['id_funcion'] !== null
    ? (int)$data['id_funcion'] : null;
$sessionId = $data['session_id'] ?? session_id();
$asientos  = $data['asientos']   ?? [];
if (is_string($asientos)) $asientos = array_filter(array_map('trim', explode(',', $asientos)));

// Cliente info: si hay sesión, usar nombre del vendedor
$clienteInfo = isset($_SESSION['usuario_nombre'])
    ? ($_SESSION['usuario_nombre'] . ' ' . ($_SESSION['usuario_apellido'] ?? ''))
    : ($data['cliente_info'] ?? null);

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
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
