<?php
/**
 * API admin: órdenes online / reembolsos.
 * Acciones: listar | detalle | reembolsar | reemitir | reenviar_correo
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/reembolso_helper.php';

$conn = getLocalConnection();
if (!$conn) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Sin BD']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($input)) {
    $input = [];
}

if (in_array($action, ['reembolsar', 'reemitir', 'reenviar_correo'], true)) {
    $tok = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
    if (!teatro_csrf_validate(is_string($tok) ? $tok : '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF inválido']);
        exit;
    }
}

try {
    if ($action === 'listar') {
        $lista = admin_listar_ordenes($conn, [
            'estado' => trim((string) ($_GET['estado'] ?? '')),
            'q' => trim((string) ($_GET['q'] ?? '')),
            'id_evento' => (int) ($_GET['id_evento'] ?? 0) ?: null,
        ]);
        echo json_encode(['success' => true, 'ordenes' => $lista], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'detalle') {
        $codigo = trim((string) ($_GET['codigo'] ?? $input['codigo'] ?? ''));
        if ($codigo === '') {
            echo json_encode(['success' => false, 'error' => 'Falta codigo']);
            exit;
        }
        $det = admin_detalle_orden($conn, $codigo);
        if (!$det) {
            echo json_encode(['success' => false, 'error' => 'No encontrada']);
            exit;
        }
        echo json_encode(['success' => true] + $det, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'reembolsar') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'POST requerido']);
            exit;
        }
        $codigo = trim((string) ($input['codigo'] ?? ''));
        $motivo = trim((string) ($input['motivo'] ?? 'Cancelación administrativa'));
        if ($codigo === '') {
            echo json_encode(['success' => false, 'error' => 'Falta codigo']);
            exit;
        }
        $r = reembolsar_orden_online($conn, $codigo, $motivo !== '' ? $motivo : null);
        echo json_encode($r, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'reemitir') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'POST requerido']);
            exit;
        }
        $codigo = trim((string) ($input['codigo'] ?? ''));
        $orden = obtenerOrdenPorCodigo($conn, $codigo);
        if (!$orden || $orden['estado'] !== 'pagada') {
            echo json_encode(['success' => false, 'error' => 'Orden pagada requerida']);
            exit;
        }
        $emi = emitir_boletos_orden_pagada($conn, (int) $orden['id_orden']);
        echo json_encode($emi, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'reenviar_correo') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'POST requerido']);
            exit;
        }
        require_once dirname(__DIR__, 2) . '/includes/correo/CorreoService.php';
        $codigo = trim((string) ($input['codigo'] ?? ''));
        $orden = obtenerOrdenPorCodigo($conn, $codigo);
        if (!$orden) {
            echo json_encode(['success' => false, 'error' => 'No encontrada']);
            exit;
        }
        $r = correo_enviar_confirmacion_orden($conn, (int) $orden['id_orden'], true);
        echo json_encode([
            'success' => $r['ok'],
            'estado' => $r['estado'],
            'modo' => correo_modo(),
            'error' => $r['error'] ?? null,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Acción inválida']);
} catch (Throwable $e) {
    error_log('[ordenes_online/api] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno']);
}
