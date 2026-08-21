<?php
/**
 * API pública de disponibilidad (vendidos + holds).
 * GET ?id_evento=&id_funcion=&session_id= (session_id opcional para excluir holds propios)
 */

require_once __DIR__ . '/_bootstrap.php';
api_online_json_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_online_respond(['success' => false, 'error' => 'Método no permitido'], 405);
}

if (!api_online_rate_limit('disponibilidad', 120, 60)) {
    api_online_respond(['success' => false, 'error' => 'Demasiadas solicitudes'], 429);
}

require_once dirname(__DIR__, 2) . '/sync/reservas_helper.php';

$data = api_online_input();
$idEvento = isset($data['id_evento']) ? (int) $data['id_evento'] : 0;
$idFuncion = isset($data['id_funcion']) && $data['id_funcion'] !== '' && $data['id_funcion'] !== null
    ? (int) $data['id_funcion']
    : null;
$sessionId = isset($data['session_id']) ? trim((string) $data['session_id']) : null;

if ($idEvento <= 0) {
    api_online_respond(['success' => false, 'error' => 'id_evento inválido'], 400);
}

$disp = obtenerDisponibilidadFuncion($idEvento, $idFuncion, $sessionId ?: null);

api_online_respond([
    'success' => true,
    'id_evento' => $idEvento,
    'id_funcion' => $idFuncion,
    'vendidos' => $disp['vendidos'],
    'reservados' => $disp['reservados'],
    'reservados_detalle' => $disp['reservados_detalle'],
    'ocupados' => $disp['ocupados'],
    // Compatibilidad con clientes que esperaban solo "asientos" = vendidos
    'asientos' => $disp['vendidos'],
]);
