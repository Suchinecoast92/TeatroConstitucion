<?php
/**
 * Asientos vendidos (+ holds ajenos) para el mapa de taquilla.
 * Mantiene compatibilidad: `asientos` = solo vendidos (estatus=1).
 * Campos nuevos: vendidos, reservados, ocupados.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../sync/reservas_helper.php';

$id_evento = isset($_GET['id_evento']) ? (int) $_GET['id_evento'] : 0;
$id_funcion = isset($_GET['id_funcion']) ? (int) $_GET['id_funcion'] : 0;
$session_id = isset($_GET['session_id']) ? trim((string) $_GET['session_id']) : null;

if ($id_evento <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de evento inválido']);
    exit;
}

$idFuncion = $id_funcion > 0 ? $id_funcion : null;
$disp = obtenerDisponibilidadFuncion($id_evento, $idFuncion, $session_id ?: null, $conn);

echo json_encode([
    'success' => true,
    'asientos' => $disp['vendidos'], // compat
    'vendidos' => $disp['vendidos'],
    'reservados' => $disp['reservados'],
    'reservados_detalle' => $disp['reservados_detalle'],
    'ocupados' => $disp['ocupados'],
], JSON_UNESCAPED_UNICODE);

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
