<?php
/**
 * API pública de reservas temporales (holds) — origen=online
 *
 * Acciones:
 *   POST ?action=reservar     {id_evento, id_funcion, asientos[], session_id}
 *   POST ?action=liberar      {id_evento, id_funcion, asientos[], session_id}
 *   GET  ?action=activas      id_evento, id_funcion, session_id
 *   POST ?action=liberar_sesion {session_id}
 *   POST ?action=renovar      {id_evento, id_funcion, session_id, ttl?}
 *
 * El asiento solo se considera reservado si el servidor responde success
 * (sin hold optimista en el cliente).
 */

require_once __DIR__ . '/_bootstrap.php';
api_online_json_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!api_online_rate_limit('reservas_online', 90, 60)) {
    api_online_respond(['success' => false, 'error' => 'Demasiadas solicitudes'], 429);
}

require_once dirname(__DIR__, 2) . '/sync/reservas_helper.php';

$action = $_GET['action'] ?? '';
$data = api_online_input();
$origen = 'online';

$idEvento = isset($data['id_evento']) ? (int) $data['id_evento'] : 0;
$idFuncion = isset($data['id_funcion']) && $data['id_funcion'] !== '' && $data['id_funcion'] !== null
    ? (int) $data['id_funcion']
    : null;
$sessionId = isset($data['session_id']) ? trim((string) $data['session_id']) : '';
$asientos = $data['asientos'] ?? [];
if (is_string($asientos)) {
    $asientos = array_filter(array_map('trim', explode(',', $asientos)));
}
if (!is_array($asientos)) {
    $asientos = [];
}

$clienteInfo = isset($data['cliente_info']) ? substr(trim((string) $data['cliente_info']), 0, 150) : null;

try {
    if ($action === 'reservar') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            api_online_respond(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        if ($idEvento <= 0 || $sessionId === '' || empty($asientos)) {
            api_online_respond(['success' => false, 'error' => 'Faltan parámetros'], 400);
        }
        if (count($asientos) > 20) {
            api_online_respond(['success' => false, 'error' => 'Máximo 20 asientos por solicitud'], 400);
        }

        $r = reservarAsientos($idEvento, $idFuncion, $asientos, $sessionId, $origen, $clienteInfo);
        api_online_respond($r, $r['success'] ? 200 : 409);
    }

    if ($action === 'liberar') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            api_online_respond(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        if ($idEvento <= 0 || $sessionId === '') {
            api_online_respond(['success' => false, 'error' => 'Faltan parámetros'], 400);
        }
        $n = liberarAsientos($sessionId, $idEvento, $idFuncion, $asientos);
        api_online_respond(['success' => true, 'liberados' => $n]);
    }

    if ($action === 'activas') {
        if ($idEvento <= 0) {
            api_online_respond(['success' => false, 'error' => 'Falta id_evento'], 400);
        }
        $excluir = ($data['exclude_self'] ?? '1') ? $sessionId : null;
        $list = listarReservasActivasDetalle($idEvento, $idFuncion, $excluir ?: null);
        api_online_respond([
            'success' => true,
            'reservados' => array_column($list, 'codigo'),
            'reservados_detalle' => $list,
        ]);
    }

    if ($action === 'liberar_sesion') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            api_online_respond(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        if ($sessionId === '') {
            api_online_respond(['success' => false, 'error' => 'Falta session_id'], 400);
        }
        $n = liberarReservasSesion($sessionId);
        api_online_respond(['success' => true, 'liberados' => $n]);
    }

    if ($action === 'renovar') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            api_online_respond(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        if ($idEvento <= 0 || $sessionId === '') {
            api_online_respond(['success' => false, 'error' => 'Faltan parámetros'], 400);
        }
        $ttl = isset($data['ttl']) ? (int) $data['ttl'] : RESERVA_TTL_SEG;
        if ($ttl < 60) {
            $ttl = 60;
        }
        if ($ttl > 1800) {
            $ttl = 1800;
        }
        $n = renovarReservasSesion($sessionId, $idEvento, $idFuncion, $ttl);
        api_online_respond([
            'success' => true,
            'renovados' => $n,
            'ttl' => $ttl,
            'expira_en' => date('c', time() + $ttl),
        ]);
    }

    api_online_respond(['success' => false, 'error' => 'Acción inválida'], 400);
} catch (Throwable $e) {
    error_log('[api/online/reservas] ' . $e->getMessage());
    api_online_respond(['success' => false, 'error' => 'Error interno'], 500);
}
