<?php
/**
 * API online de órdenes.
 *
 * POST ?action=cotizar  — precios desde BD (sin crear orden)
 * POST ?action=crear    — crea orden pendiente (requiere holds online)
 * GET  ?action=obtener&codigo=ORD...
 * POST ?action=limpiar  — expira pendientes vencidas
 */

require_once __DIR__ . '/_bootstrap.php';
api_online_json_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!api_online_rate_limit('ordenes_online', 60, 60)) {
    api_online_respond(['success' => false, 'error' => 'Demasiadas solicitudes'], 429);
}

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/ordenes_helper.php';

$conn = getLocalConnection();
if (!$conn) {
    api_online_respond(['success' => false, 'error' => 'Sin conexión a BD'], 503);
}

asegurarTablasOrdenes($conn);
$action = $_GET['action'] ?? '';
$data = api_online_input();

try {
    if ($action === 'cotizar') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            api_online_respond(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        $idEvento = (int) ($data['id_evento'] ?? 0);
        $asientos = $data['asientos'] ?? [];
        if ($idEvento <= 0) {
            api_online_respond(['success' => false, 'error' => 'id_evento requerido'], 400);
        }
        $r = calcular_cotizacion_online($conn, $idEvento, $asientos);
        api_online_respond($r, $r['success'] ? 200 : 400);
    }

    if ($action === 'crear') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            api_online_respond(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        $r = crearOrdenOnline($conn, $data);
        api_online_respond($r, $r['success'] ? 201 : 400);
    }

    if ($action === 'obtener') {
        $codigo = trim((string) ($data['codigo'] ?? $data['codigo_publico'] ?? ''));
        if ($codigo === '') {
            api_online_respond(['success' => false, 'error' => 'codigo requerido'], 400);
        }
        expirarOrdenesPendientes($conn);
        $orden = obtenerOrdenPorCodigo($conn, $codigo);
        if (!$orden) {
            api_online_respond(['success' => false, 'error' => 'Orden no encontrada'], 404);
        }
        // No exponer session_id completo al público si no es necesario: sí se necesita para UX propia
        api_online_respond(['success' => true, 'orden' => $orden]);
    }

    if ($action === 'limpiar') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
            api_online_respond(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        $n = expirarOrdenesPendientes($conn);
        api_online_respond(['success' => true, 'expiradas' => $n]);
    }

    api_online_respond(['success' => false, 'error' => 'Acción inválida'], 400);
} catch (Throwable $e) {
    error_log('[api/online/ordenes] ' . $e->getMessage());
    api_online_respond(['success' => false, 'error' => 'Error interno'], 500);
}
