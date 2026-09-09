<?php
/**
 * Servicio de pagos: orquesta gateway + tabla `pagos` + estado de orden.
 * Al confirmar PAID emite boletos/QR (Fase 4) de forma idempotente.
 */

if (defined('PAYMENT_SERVICE_INCLUDED')) {
    return;
}
define('PAYMENT_SERVICE_INCLUDED', true);

require_once dirname(__DIR__, 2) . '/config/env.php';
require_once dirname(__DIR__, 2) . '/includes/ordenes_helper.php';
require_once dirname(__DIR__, 2) . '/includes/emision_helper.php';
require_once __DIR__ . '/PaymentGatewayInterface.php';
require_once __DIR__ . '/MercadoPagoGateway.php';
require_once __DIR__ . '/MockPaymentGateway.php';

function asegurarTablaPagos(mysqli $conn): void
{
    static $ok = false;
    if ($ok) {
        return;
    }
    asegurarTablasOrdenes($conn);
    $conn->query("
        CREATE TABLE IF NOT EXISTS pagos (
            id_pago INT AUTO_INCREMENT PRIMARY KEY,
            id_orden INT NOT NULL,
            proveedor VARCHAR(40) NOT NULL DEFAULT 'mercadopago',
            ref_externa VARCHAR(120) NOT NULL,
            ref_pago_proveedor VARCHAR(120) NULL,
            estado_interno ENUM('PENDING','PAID','FAILED','REFUNDED') NOT NULL DEFAULT 'PENDING',
            monto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            moneda VARCHAR(8) NOT NULL DEFAULT 'MXN',
            init_point TEXT NULL,
            payload_resumen TEXT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_ref_externa (ref_externa),
            KEY idx_pago_orden (id_orden),
            KEY idx_pago_estado (estado_interno),
            KEY idx_pago_proveedor_ref (ref_pago_proveedor)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $ok = true;
}

function payment_app_base_url(): string
{
    $configured = rtrim((string) teatro_env('APP_URL', ''), '/');
    if ($configured !== '') {
        return $configured;
    }

    // Fallback HTTP
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Detectar carpeta del proyecto desde SCRIPT_NAME
    // ej. /TeatroConstitucion/api/online/pagos.php → /TeatroConstitucion
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = '';
    if (preg_match('#^(/.+?)/(?:api|crt_interfaz|vnt_interfaz)/#', $script, $m)) {
        $basePath = $m[1];
    } elseif (preg_match('#^(/[^/]+)/#', $script, $m)) {
        $basePath = $m[1];
    }

    return $scheme . '://' . $host . $basePath;
}

function payment_resolve_gateway(): PaymentGatewayInterface
{
    $mode = strtolower((string) teatro_env('MP_MODE', ''));
    $token = (string) teatro_env('MP_ACCESS_TOKEN', '');
    if ($mode === 'mock' || $token === '') {
        return new MockPaymentGateway();
    }
    return new MercadoPagoGateway($token);
}

/**
 * Mock solo en desarrollo: nunca con token real / modo live.
 */
function payment_mock_permitido(): bool
{
    $env = strtolower((string) teatro_env('APP_ENV', 'local'));
    $mode = strtolower((string) teatro_env('MP_MODE', ''));
    $token = (string) teatro_env('MP_ACCESS_TOKEN', '');
    if ($mode === 'live' || ($token !== '' && $mode !== 'mock')) {
        return false;
    }
    if ($env === 'production' || $env === 'prod') {
        return false;
    }
    return $mode === 'mock' || $token === '';
}

/**
 * Extiende expira_en de la orden (reloj MySQL).
 */
function payment_extender_expira_orden(mysqli $conn, int $idOrden, int $ttlSeg): void
{
    $ttlSeg = max(60, $ttlSeg);
    $st = $conn->prepare('UPDATE ordenes SET expira_en = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id_orden = ? AND estado = \'pendiente\'');
    $st->bind_param('ii', $ttlSeg, $idOrden);
    $st->execute();
    $st->close();
}

/**
 * Crea (o reutiliza) preferencia de pago para una orden pendiente.
 */
function payment_ttl_pasarela(): int
{
    return max((int) ORDEN_TTL_SEG, (int) PAGO_EN_CURSO_TTL_SEG);
}

function payment_crear_para_orden(mysqli $conn, string $codigoPublico): array
{
    asegurarTablaPagos($conn);

    $orden = obtenerOrdenPorCodigo($conn, $codigoPublico);
    if (!$orden) {
        return ['success' => false, 'error' => 'Orden no encontrada'];
    }
    if ($orden['estado'] === 'pagada') {
        return ['success' => false, 'error' => 'La orden ya está pagada'];
    }
    // Tras rechazo de pasarela la orden queda 'fallida'; permitir reintento.
    if (!in_array($orden['estado'], ['pendiente', 'fallida'], true)) {
        return ['success' => false, 'error' => 'La orden no está pendiente de pago'];
    }

    $idOrden = (int) $orden['id_orden'];
    $ttlPago = payment_ttl_pasarela();

    if ($orden['estado'] === 'fallida') {
        $rst = $conn->prepare("UPDATE ordenes SET estado = 'pendiente' WHERE id_orden = ? AND estado = 'fallida'");
        $rst->bind_param('i', $idOrden);
        $rst->execute();
        $rst->close();
        $orden = obtenerOrdenPorCodigo($conn, $codigoPublico);
        if (!$orden || $orden['estado'] !== 'pendiente') {
            return ['success' => false, 'error' => 'No se pudo reabrir la orden para pago'];
        }
    }

    // Extender ANTES de expirar otras órdenes: evita carrera timer→Pagar
    renovarReservasSesion($orden['session_id'], (int) $orden['id_evento'], (int) $orden['id_funcion'], $ttlPago);
    payment_extender_expira_orden($conn, $idOrden, $ttlPago);

    expirarOrdenesPendientes($conn);
    $orden = obtenerOrdenPorCodigo($conn, $codigoPublico);
    if (!$orden || $orden['estado'] !== 'pendiente') {
        return ['success' => false, 'error' => 'La orden no está pendiente de pago'];
    }

    // Reutilizar pago PENDING existente
    $st = $conn->prepare("
        SELECT * FROM pagos
        WHERE id_orden = ? AND estado_interno = 'PENDING'
        ORDER BY id_pago DESC LIMIT 1
    ");
    $st->bind_param('i', $idOrden);
    $st->execute();
    $existente = $st->get_result()->fetch_assoc();
    $st->close();
    if ($existente && !empty($existente['init_point'])) {
        renovarReservasSesion($orden['session_id'], (int) $orden['id_evento'], (int) $orden['id_funcion'], $ttlPago);
        payment_extender_expira_orden($conn, $idOrden, $ttlPago);
        return [
            'success' => true,
            'reused' => true,
            'id_pago' => (int) $existente['id_pago'],
            'ref_externa' => $existente['ref_externa'],
            'init_point' => $existente['init_point'],
            'proveedor' => $existente['proveedor'],
            'codigo_publico' => $codigoPublico,
            'ttl_pasarela_seg' => $ttlPago,
        ];
    }

    $stmt = $conn->prepare('SELECT titulo FROM evento WHERE id_evento = ?');
    $eid = (int) $orden['id_evento'];
    $stmt->bind_param('i', $eid);
    $stmt->execute();
    $evt = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $orden['titulo_evento'] = $evt['titulo'] ?? 'Teatro Constitución';

    $base = payment_app_base_url();
    $notif = $base . '/api/online/webhook_pagos.php';
    $whSecret = (string) teatro_env('MP_WEBHOOK_SECRET', '');
    if ($whSecret !== '') {
        $notif .= (strpos($notif, '?') === false ? '?' : '&') . 'secret=' . rawurlencode($whSecret);
    }
    $urls = [
        'success' => $base . '/crt_interfaz/pago_retorno.php?status=success&codigo=' . rawurlencode($codigoPublico),
        'failure' => $base . '/crt_interfaz/pago_retorno.php?status=failure&codigo=' . rawurlencode($codigoPublico),
        'pending' => $base . '/crt_interfaz/pago_retorno.php?status=pending&codigo=' . rawurlencode($codigoPublico),
        'notification' => $notif,
    ];

    $gateway = payment_resolve_gateway();
    $pref = $gateway->crearPreferencia($orden, $urls);
    if (!$pref['success']) {
        return $pref;
    }

    $ref = $pref['ref_externa'];
    $init = $pref['sandbox_init_point'] ?: ($pref['init_point'] ?? '');
    if ($ref === '' || $init === '') {
        return ['success' => false, 'error' => 'Preferencia incompleta del proveedor'];
    }

    $proveedor = $gateway->nombreProveedor();
    $monto = (float) $orden['total'];
    $resumen = json_encode($pref['raw'] ?? [], JSON_UNESCAPED_UNICODE);

    $ins = $conn->prepare("
        INSERT INTO pagos (id_orden, proveedor, ref_externa, estado_interno, monto, moneda, init_point, payload_resumen)
        VALUES (?, ?, ?, 'PENDING', ?, 'MXN', ?, ?)
    ");
    $ins->bind_param('issdss', $idOrden, $proveedor, $ref, $monto, $init, $resumen);
    if (!$ins->execute()) {
        // Si choca UNIQUE, reintentar leer
        $ins->close();
        return ['success' => false, 'error' => 'No se pudo registrar el pago: ' . $conn->error];
    }
    $idPago = (int) $conn->insert_id;
    $ins->close();

    // Extender holds + ventana de orden mientras paga en la pasarela
    renovarReservasSesion($orden['session_id'], (int) $orden['id_evento'], (int) $orden['id_funcion'], $ttlPago);
    payment_extender_expira_orden($conn, $idOrden, $ttlPago);

    return [
        'success' => true,
        'reused' => false,
        'id_pago' => $idPago,
        'ref_externa' => $ref,
        'init_point' => $init,
        'proveedor' => $proveedor,
        'codigo_publico' => $codigoPublico,
        'mode' => $proveedor === 'mock' ? 'mock' : 'mercadopago',
        'ttl_pasarela_seg' => $ttlPago,
    ];
}

/**
 * Aplica un estado de pago de forma idempotente.
 * @return array{success:bool,changed?:bool,estado?:string,error?:string}
 */
function payment_aplicar_estado(
    mysqli $conn,
    string $refExterna,
    string $estadoInterno,
    ?string $refPagoProveedor = null,
    ?array $payloadResumen = null
): array {
    asegurarTablaPagos($conn);
    $estadosOk = ['PENDING', 'PAID', 'FAILED', 'REFUNDED'];
    if (!in_array($estadoInterno, $estadosOk, true)) {
        return ['success' => false, 'error' => 'Estado inválido'];
    }

    $st = $conn->prepare('SELECT * FROM pagos WHERE ref_externa = ? LIMIT 1');
    $st->bind_param('s', $refExterna);
    $st->execute();
    $pago = $st->get_result()->fetch_assoc();
    $st->close();

    // También buscar por payment id
    if (!$pago && $refPagoProveedor) {
        $st = $conn->prepare('SELECT * FROM pagos WHERE ref_pago_proveedor = ? LIMIT 1');
        $st->bind_param('s', $refPagoProveedor);
        $st->execute();
        $pago = $st->get_result()->fetch_assoc();
        $st->close();
    }
    if (!$pago) {
        return ['success' => false, 'error' => 'Pago no encontrado'];
    }

    $prev = $pago['estado_interno'];
    if ($prev === 'PAID' && $estadoInterno === 'PAID') {
        // Reintento seguro: completar emisión si quedó a medias
        emitir_boletos_orden_pagada($conn, (int) $pago['id_orden']);
        return ['success' => true, 'changed' => false, 'estado' => 'PAID', 'idempotent' => true];
    }
    if ($prev === 'PAID' && $estadoInterno !== 'REFUNDED') {
        // No degradar un pago ya confirmado
        emitir_boletos_orden_pagada($conn, (int) $pago['id_orden']);
        return ['success' => true, 'changed' => false, 'estado' => 'PAID', 'idempotent' => true];
    }

    $resumenJson = $payloadResumen ? json_encode($payloadResumen, JSON_UNESCAPED_UNICODE) : $pago['payload_resumen'];
    $refPay = $refPagoProveedor ?: $pago['ref_pago_proveedor'];
    $idPago = (int) $pago['id_pago'];
    $idOrden = (int) $pago['id_orden'];

    $up = $conn->prepare("
        UPDATE pagos
        SET estado_interno = ?, ref_pago_proveedor = ?, payload_resumen = ?
        WHERE id_pago = ?
    ");
    $up->bind_param('sssi', $estadoInterno, $refPay, $resumenJson, $idPago);
    $up->execute();
    $up->close();

    if ($estadoInterno === 'PAID') {
        // Incluye 'expirada'/'fallida': si el dinero llegó, marcar pagada e intentar emitir
        $uo = $conn->prepare("UPDATE ordenes SET estado = 'pagada' WHERE id_orden = ? AND estado IN ('pendiente','pagada','expirada','fallida')");
        $uo->bind_param('i', $idOrden);
        $uo->execute();
        $uo->close();

        // Emite boletos/QR y libera holds (idempotente)
        $emi = emitir_boletos_orden_pagada($conn, $idOrden);
        if (empty($emi['success'])) {
            $prot = proteger_asientos_orden_sin_boleto($conn, $idOrden, 86400);
            error_log(
                '[payment] emisión fallida orden ' . $idOrden . ': ' . ($emi['error'] ?? '')
                . ' holds_protegidos=' . (int) ($prot['protegidos'] ?? 0)
            );
            return [
                'success' => true,
                'changed' => $prev !== $estadoInterno,
                'estado' => 'PAID',
                'emision_ok' => false,
                'emision_error' => $emi['error'] ?? 'emision fallida',
                'holds_protegidos' => (int) ($prot['protegidos'] ?? 0),
                'holds_conflictos' => $prot['conflictos'] ?? [],
            ];
        }
        return [
            'success' => true,
            'changed' => $prev !== $estadoInterno,
            'estado' => 'PAID',
            'emision_ok' => true,
            'emitidos' => (int) ($emi['emitidos'] ?? 0),
        ];
    } elseif ($estadoInterno === 'FAILED') {
        $uo = $conn->prepare("UPDATE ordenes SET estado = 'fallida' WHERE id_orden = ? AND estado = 'pendiente'");
        $uo->bind_param('i', $idOrden);
        $uo->execute();
        $uo->close();
    } elseif ($estadoInterno === 'REFUNDED') {
        $uo = $conn->prepare("UPDATE ordenes SET estado = 'reembolsada' WHERE id_orden = ?");
        $uo->bind_param('i', $idOrden);
        $uo->execute();
        $uo->close();
        // Cancelar boletos si el reembolso llegó por webhook (no solo por admin)
        require_once dirname(__DIR__) . '/reembolso_helper.php';
        if (function_exists('cancelar_boletos_de_orden')) {
            cancelar_boletos_de_orden($conn, $idOrden, 'reembolso_webhook');
        }
    }

    return ['success' => true, 'changed' => $prev !== $estadoInterno, 'estado' => $estadoInterno];
}

/**
 * Procesa notificación MP (topic/id o body JSON).
 */
function payment_procesar_webhook(mysqli $conn, array $query, array $body): array
{
    asegurarTablaPagos($conn);
    $gateway = payment_resolve_gateway();

    // Mock: solo si payment_mock_permitido()
    if (!empty($query['mock']) || ($gateway instanceof MockPaymentGateway && !empty($query['codigo']))) {
        if (!payment_mock_permitido()) {
            return ['success' => false, 'error' => 'Mock deshabilitado en este entorno'];
        }
        $codigo = trim((string) ($query['codigo'] ?? $body['codigo'] ?? ''));
        $result = strtolower((string) ($query['result'] ?? $body['result'] ?? 'approved'));
        if ($codigo === '') {
            return ['success' => false, 'error' => 'codigo requerido en mock'];
        }
        $orden = obtenerOrdenPorCodigo($conn, $codigo);
        if (!$orden) {
            return ['success' => false, 'error' => 'Orden no encontrada'];
        }
        $idOrden = (int) $orden['id_orden'];
        $st = $conn->prepare("SELECT ref_externa FROM pagos WHERE id_orden = ? ORDER BY id_pago DESC LIMIT 1");
        $st->bind_param('i', $idOrden);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) {
            return ['success' => false, 'error' => 'Pago no encontrado para la orden'];
        }
        $estado = $result === 'approved' || $result === 'paid' ? 'PAID' : 'FAILED';
        $payId = 'MOCK-PAY-' . strtoupper(bin2hex(random_bytes(4)));
        return payment_aplicar_estado($conn, $row['ref_externa'], $estado, $payId, ['mock' => true, 'result' => $result]);
    }

    $topic = $query['topic'] ?? $query['type'] ?? ($body['type'] ?? null);
    $id = $query['id'] ?? $query['data.id'] ?? null;
    if (!$id && isset($body['data']['id'])) {
        $id = $body['data']['id'];
    }
    if (!$id && isset($body['resource'])) {
        // resource URL .../payments/123
        if (preg_match('/payments\/(\d+)/', (string) $body['resource'], $m)) {
            $id = $m[1];
            $topic = 'payment';
        }
    }

    if (!$id) {
        return ['success' => true, 'ignored' => true, 'reason' => 'sin id de pago'];
    }

    $topic = strtolower((string) $topic);
    if ($topic && !in_array($topic, ['payment', 'payments'], true) && $topic !== 'merchant_order') {
        // Intentar igual si parece payment id numérico
        if (!is_numeric($id)) {
            return ['success' => true, 'ignored' => true, 'reason' => 'topic no manejado: ' . $topic];
        }
    }

    if (!($gateway instanceof MercadoPagoGateway)) {
        // Si estamos en mock pero llega webhook real, ignorar
        return ['success' => true, 'ignored' => true, 'reason' => 'gateway mock'];
    }

    $info = $gateway->consultarPago((string) $id);
    if (!$info['success']) {
        return $info;
    }

    $extRef = $info['external_reference'] ?? null;
    $refExterna = null;
    if ($extRef) {
        // Buscar pago por orden codigo
        $orden = obtenerOrdenPorCodigo($conn, (string) $extRef);
        if ($orden) {
            $idOrden = (int) $orden['id_orden'];
            $st = $conn->prepare("SELECT ref_externa FROM pagos WHERE id_orden = ? ORDER BY id_pago DESC LIMIT 1");
            $st->bind_param('i', $idOrden);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            $refExterna = $row['ref_externa'] ?? null;
        }
    }
    if (!$refExterna) {
        // Intentar por payment id ya guardado
        $st = $conn->prepare('SELECT ref_externa FROM pagos WHERE ref_pago_proveedor = ? LIMIT 1');
        $pid = (string) $id;
        $st->bind_param('s', $pid);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        $refExterna = $row['ref_externa'] ?? null;
    }
    if (!$refExterna) {
        return ['success' => false, 'error' => 'No se pudo relacionar el pago con una orden'];
    }

    return payment_aplicar_estado(
        $conn,
        $refExterna,
        $info['estado_interno'],
        $info['ref_pago'] ?? (string) $id,
        $info['raw'] ?? null
    );
}
