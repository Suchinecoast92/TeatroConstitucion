<?php
/**
 * API: iniciar pago de una orden.
 *
 * POST ?action=iniciar  {codigo_publico}  → init_point
 * POST ?action=simular  {codigo_publico, result=approved|rejected}  (solo mock / APP_ENV=local)
 * GET  ?action=estado&codigo=
 */

require_once __DIR__ . '/_bootstrap.php';
api_online_json_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!api_online_rate_limit('pagos_online', 40, 60)) {
    api_online_respond(['success' => false, 'error' => 'Demasiadas solicitudes'], 429);
}

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/pagos/PaymentService.php';

$conn = getLocalConnection();
if (!$conn) {
    api_online_respond(['success' => false, 'error' => 'Sin conexión'], 503);
}

$action = $_GET['action'] ?? '';
$data = api_online_input();

try {
    if ($action === 'iniciar') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            api_online_respond(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        $codigo = trim((string) ($data['codigo_publico'] ?? $data['codigo'] ?? ''));
        if ($codigo === '') {
            api_online_respond(['success' => false, 'error' => 'codigo_publico requerido'], 400);
        }
        $r = payment_crear_para_orden($conn, $codigo);
        api_online_respond($r, $r['success'] ? 200 : 400);
    }

    if ($action === 'simular') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            api_online_respond(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        $env = strtolower((string) teatro_env('APP_ENV', 'local'));
        $mode = strtolower((string) teatro_env('MP_MODE', ''));
        $token = (string) teatro_env('MP_ACCESS_TOKEN', '');
        if ($env === 'production' && $token !== '' && $mode !== 'mock') {
            api_online_respond(['success' => false, 'error' => 'Simulación no permitida en producción'], 403);
        }
        $codigo = trim((string) ($data['codigo_publico'] ?? $data['codigo'] ?? ''));
        $result = strtolower((string) ($data['result'] ?? 'approved'));
        $r = payment_procesar_webhook($conn, ['mock' => '1', 'codigo' => $codigo, 'result' => $result], []);
        api_online_respond($r, $r['success'] ? 200 : 400);
    }

    if ($action === 'estado') {
        $codigo = trim((string) ($data['codigo'] ?? $data['codigo_publico'] ?? ''));
        if ($codigo === '') {
            api_online_respond(['success' => false, 'error' => 'codigo requerido'], 400);
        }
        $orden = obtenerOrdenPorCodigo($conn, $codigo);
        if (!$orden) {
            api_online_respond(['success' => false, 'error' => 'Orden no encontrada'], 404);
        }
        $idOrden = (int) $orden['id_orden'];
        $st = $conn->prepare('SELECT id_pago, proveedor, ref_externa, estado_interno, monto, actualizado_en FROM pagos WHERE id_orden = ? ORDER BY id_pago DESC');
        $st->bind_param('i', $idOrden);
        $st->execute();
        $pagos = [];
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $pagos[] = $row;
        }
        $st->close();
        api_online_respond([
            'success' => true,
            'orden' => [
                'codigo_publico' => $orden['codigo_publico'],
                'estado' => $orden['estado'],
                'total' => $orden['total'],
            ],
            'pagos' => $pagos,
        ]);
    }

    api_online_respond(['success' => false, 'error' => 'Acción inválida'], 400);
} catch (Throwable $e) {
    error_log('[api/online/pagos] ' . $e->getMessage());
    api_online_respond(['success' => false, 'error' => 'Error interno'], 500);
}
