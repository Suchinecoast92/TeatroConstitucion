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

    if (teatro_es_produccion()) {
        error_log('[pagos] APP_URL no configurada en producción; usando Host de la petición');
    }
    $scheme = teatro_request_is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . teatro_app_base_path();
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
 * Modo de checkout para el frontend:
 *  - 'bricks'   → Payment Brick embebido (requiere gateway MP + MP_PUBLIC_KEY)
 *  - 'redirect' → Checkout Pro / mock con redirección (flujo histórico, se conserva)
 * MP_CHECKOUT=redirect fuerza el flujo anterior aunque haya Public Key.
 */
function payment_modo_checkout(): string
{
    $forzado = strtolower((string) teatro_env('MP_CHECKOUT', ''));
    if ($forzado === 'redirect') {
        return 'redirect';
    }
    $gateway = payment_resolve_gateway();
    if ($gateway instanceof MercadoPagoGateway && payment_public_key() !== '') {
        return 'bricks';
    }
    return 'redirect';
}

/** Public Key de MP (única credencial que puede llegar al navegador). */
function payment_public_key(): string
{
    $pk = trim((string) teatro_env('MP_PUBLIC_KEY', ''));
    return preg_match('/^(APP_USR|TEST)-[A-Za-z0-9-]{8,}$/', $pk) ? $pk : '';
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
    if ($env !== 'local') {
        return $mode === 'mock';
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
    // notification_url sin secretos en query (la autenticidad va por x-signature HMAC).
    $notif = $base . '/api/online/webhook_pagos.php';
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
 * Compara lo que reporta el proveedor contra la orden en BD.
 * @return string|null null si es consistente; texto del problema si no.
 */
function payment_validar_contra_orden(array $orden, array $info): ?string
{
    $ext = (string) ($info['external_reference'] ?? '');
    if ($ext === '' || !hash_equals((string) $orden['codigo_publico'], $ext)) {
        return 'external_reference no coincide con la orden';
    }
    if (!isset($info['monto']) || $info['monto'] === null) {
        return 'el proveedor no reportó monto';
    }
    if (abs((float) $info['monto'] - (float) $orden['total']) > 0.009) {
        return 'monto no coincide con el total de la orden';
    }
    $moneda = isset($info['moneda']) && $info['moneda'] !== null ? strtoupper((string) $info['moneda']) : 'MXN';
    if ($moneda !== 'MXN') {
        return 'moneda no coincide';
    }
    return null;
}

/**
 * Deja una orden lista para cobrar: reabre si falló, extiende holds/expiración
 * y confirma que los asientos siguen apartados para la sesión dueña.
 * @return array{success:bool,orden?:array,error?:string,http?:int}
 */
function payment_preparar_orden_para_cobro(mysqli $conn, string $codigoPublico, string $sessionId): array
{
    asegurarTablaPagos($conn);
    $orden = obtenerOrdenPorCodigo($conn, $codigoPublico);
    if (!$orden) {
        return ['success' => false, 'error' => 'Orden no encontrada', 'http' => 404];
    }
    if ($sessionId === '' || !hash_equals((string) $orden['session_id'], $sessionId)) {
        return ['success' => false, 'error' => 'No autorizado', 'http' => 403];
    }
    if ($orden['estado'] === 'pagada') {
        return ['success' => true, 'orden' => $orden, 'ya_pagada' => true];
    }
    if (!in_array($orden['estado'], ['pendiente', 'fallida'], true)) {
        return ['success' => false, 'error' => 'La orden no está pendiente de pago', 'http' => 409];
    }
    if (!teatro_funcion_venta_abierta($conn, (int) $orden['id_evento'], (int) $orden['id_funcion'])) {
        return ['success' => false, 'error' => teatro_mensaje_venta_cerrada(), 'http' => 403];
    }

    $idOrden = (int) $orden['id_orden'];
    $ttlPago = payment_ttl_pasarela();

    if ($orden['estado'] === 'fallida') {
        $rst = $conn->prepare("UPDATE ordenes SET estado = 'pendiente' WHERE id_orden = ? AND estado = 'fallida'");
        $rst->bind_param('i', $idOrden);
        $rst->execute();
        $rst->close();
    }

    renovarReservasSesion($orden['session_id'], (int) $orden['id_evento'], (int) $orden['id_funcion'], $ttlPago);
    payment_extender_expira_orden($conn, $idOrden, $ttlPago);
    expirarOrdenesPendientes($conn);

    $orden = obtenerOrdenPorCodigo($conn, $codigoPublico);
    if (!$orden || $orden['estado'] !== 'pendiente') {
        return ['success' => false, 'error' => 'La orden expiró. Vuelve a seleccionar tus asientos.', 'http' => 409];
    }

    $codigos = array_column($orden['items'] ?? [], 'codigo_asiento');
    $faltantes = verificarHoldsSesionOnline(
        $conn,
        (int) $orden['id_evento'],
        (int) $orden['id_funcion'],
        (string) $orden['session_id'],
        $codigos
    );
    if ($faltantes) {
        return [
            'success' => false,
            'error' => 'Tus asientos ya no están apartados (' . implode(', ', $faltantes) . '). No se realizó ningún cobro.',
            'http' => 409,
        ];
    }

    $st = $conn->prepare('SELECT titulo FROM evento WHERE id_evento = ?');
    $eid = (int) $orden['id_evento'];
    $st->bind_param('i', $eid);
    $st->execute();
    $evt = $st->get_result()->fetch_assoc();
    $st->close();
    $orden['titulo_evento'] = $evt['titulo'] ?? 'Teatro Constitución';

    return ['success' => true, 'orden' => $orden];
}

/**
 * Configuración pública del Payment Brick. El monto sale de la BD, nunca del navegador.
 */
function payment_brick_config(mysqli $conn, string $codigoPublico, string $sessionId): array
{
    if (payment_modo_checkout() !== 'bricks') {
        return ['success' => false, 'error' => 'Checkout Bricks no está habilitado', 'http' => 409];
    }
    $prep = payment_preparar_orden_para_cobro($conn, $codigoPublico, $sessionId);
    if (!$prep['success']) {
        return $prep;
    }
    $orden = $prep['orden'];
    if (!empty($prep['ya_pagada'])) {
        return ['success' => true, 'ya_pagada' => true, 'codigo_publico' => $codigoPublico];
    }

    $metodos = ['creditCard' => 'all', 'debitCard' => 'all'];
    // Efectivo (OXXO, etc.) puede tardar días en acreditarse y los holds duran minutos: desactivado por defecto.
    if ((string) teatro_env('MP_BRICK_TICKET', '0') === '1') {
        $metodos['ticket'] = 'all';
    }
    $metodos['maxInstallments'] = max(1, min(24, (int) teatro_env('MP_MAX_CUOTAS', 1)));

    return [
        'success' => true,
        'public_key' => payment_public_key(),
        'locale' => 'es-MX',
        'amount' => round((float) $orden['total'], 2),
        'payer_email' => (string) $orden['email'],
        'payment_methods' => $metodos,
        'codigo_publico' => $codigoPublico,
        'ttl_pasarela_seg' => payment_ttl_pasarela(),
    ];
}

/**
 * Clave de idempotencia estable para un intento de pago (formato UUID v4).
 * Mismo intento (reintento HTTP / doble envío del mismo token) → misma clave.
 */
function payment_idempotency_key(string $codigoPublico, array $datosPago, int $intentosFallidos): string
{
    $token = (string) ($datosPago['token'] ?? '');
    $semilla = $token !== ''
        ? implode('|', [$codigoPublico, 'tok', $token, (string) ($datosPago['payment_method_id'] ?? ''), (string) ($datosPago['installments'] ?? '1')])
        : implode('|', [$codigoPublico, 'sin_token', (string) ($datosPago['payment_method_id'] ?? ''), (string) $intentosFallidos]);
    $h = hash('sha256', $semilla);
    $h[12] = '4';
    $h[16] = dechex((hexdec($h[16]) & 0x3) | 0x8);
    return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20, 12);
}

/**
 * Procesa el envío del Payment Brick: crea el cobro en el proveedor desde backend.
 * La orden solo pasa a PAID tras validar el pago contra la BD (aquí y/o en el webhook).
 *
 * @return array{success:bool,estado?:string,status_detail?:string,codigo_publico?:string,error?:string,http?:int,idempotent?:bool}
 */
function payment_procesar_brick(mysqli $conn, string $codigoPublico, string $sessionId, array $datosPago): array
{
    asegurarTablaPagos($conn);

    $ordenLock = obtenerOrdenPorCodigo($conn, $codigoPublico);
    if (!$ordenLock) {
        return ['success' => false, 'error' => 'Orden no encontrada', 'http' => 404];
    }
    $idOrden = (int) $ordenLock['id_orden'];

    // Serializa doble clic / pestañas: un solo cobro por orden a la vez
    $lockName = 'teatro_pago_orden_' . $idOrden;
    $lk = $conn->query("SELECT GET_LOCK('" . $conn->real_escape_string($lockName) . "', 10) AS l");
    $got = $lk ? (int) ($lk->fetch_assoc()['l'] ?? 0) : 0;
    if ($got !== 1) {
        return ['success' => false, 'error' => 'Hay un pago en proceso para esta orden. Espera unos segundos.', 'http' => 409];
    }

    try {
        $prep = payment_preparar_orden_para_cobro($conn, $codigoPublico, $sessionId);
        if (!$prep['success']) {
            return $prep;
        }
        if (!empty($prep['ya_pagada'])) {
            return ['success' => true, 'estado' => 'PAID', 'codigo_publico' => $codigoPublico, 'idempotent' => true];
        }
        $orden = $prep['orden'];

        // Monto: el navegador no decide; si manda uno distinto se rechaza (posible manipulación)
        $montoCliente = $datosPago['transaction_amount'] ?? null;
        if ($montoCliente !== null && abs((float) $montoCliente - (float) $orden['total']) > 0.009) {
            return ['success' => false, 'error' => 'El total cambió. Recarga la página.', 'http' => 409];
        }

        $gateway = payment_resolve_gateway();
        $proveedor = $gateway->nombreProveedor();

        $st = $conn->prepare("
            SELECT id_pago, ref_externa, ref_pago_proveedor, estado_interno, creado_en
            FROM pagos
            WHERE id_orden = ? AND ref_externa LIKE 'BRK-%'
            ORDER BY id_pago DESC
        ");
        $st->bind_param('i', $idOrden);
        $st->execute();
        $intentos = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        $fallidos = 0;
        foreach ($intentos as $it) {
            if ($it['estado_interno'] === 'FAILED') {
                $fallidos++;
            }
        }

        $idemKey = payment_idempotency_key($codigoPublico, $datosPago, $fallidos);
        $refExterna = 'BRK-' . $idemKey;

        $existente = null;
        foreach ($intentos as $it) {
            if ($it['ref_externa'] === $refExterna) {
                $existente = $it;
                break;
            }
        }

        if ($existente && !empty($existente['ref_pago_proveedor'])) {
            // Reintento del mismo intento: no volver a cobrar
            if ($existente['estado_interno'] === 'PAID') {
                emitir_boletos_orden_pagada($conn, $idOrden);
            }
            return [
                'success' => true,
                'estado' => $existente['estado_interno'],
                'codigo_publico' => $codigoPublico,
                'idempotent' => true,
            ];
        }

        if (!$existente) {
            foreach ($intentos as $it) {
                if ($it['estado_interno'] === 'PAID') {
                    return ['success' => true, 'estado' => 'PAID', 'codigo_publico' => $codigoPublico, 'idempotent' => true];
                }
                if ($it['estado_interno'] === 'PENDING') {
                    // Otro intento distinto sigue abierto (en revisión o resultado incierto): no arriesgar doble cobro
                    return [
                        'success' => false,
                        'estado' => 'PENDING',
                        'error' => 'Tu pago anterior sigue en verificación. Revisa el estado de tu orden en unos minutos.',
                        'codigo_publico' => $codigoPublico,
                        'http' => 409,
                    ];
                }
            }

            $monto = (float) $orden['total'];
            $resumen = json_encode(['brick' => true, 'payment_method_id' => (string) ($datosPago['payment_method_id'] ?? '')], JSON_UNESCAPED_UNICODE);
            $ins = $conn->prepare("
                INSERT INTO pagos (id_orden, proveedor, ref_externa, estado_interno, monto, moneda, init_point, payload_resumen)
                VALUES (?, ?, ?, 'PENDING', ?, 'MXN', NULL, ?)
            ");
            $ins->bind_param('issds', $idOrden, $proveedor, $refExterna, $monto, $resumen);
            try {
                $ins->execute();
            } catch (mysqli_sql_exception $e) {
                $ins->close();
                // UNIQUE(ref_externa): otra petición registró el mismo intento
                return ['success' => true, 'estado' => 'PENDING', 'codigo_publico' => $codigoPublico, 'idempotent' => true];
            }
            $ins->close();
        }

        $urls = ['notification' => payment_app_base_url() . '/api/online/webhook_pagos.php'];
        $res = $gateway->crearPagoDirecto($orden, $datosPago, $idemKey, $urls);

        if (!$res['success']) {
            if (!empty($res['rechazo_definitivo'])) {
                // El proveedor no creó el cobro: el intento se cierra y se permite reintentar
                payment_aplicar_estado($conn, $refExterna, 'FAILED', null, ['brick' => true, 'error' => mb_substr((string) ($res['error'] ?? ''), 0, 200)]);
                return ['success' => false, 'estado' => 'FAILED', 'error' => 'No se pudo procesar el pago. Verifica los datos e intenta de nuevo.', 'codigo_publico' => $codigoPublico, 'http' => 402];
            }
            // Resultado incierto (red/5xx): el intento queda PENDING; el webhook o un reintento con la misma clave lo resuelven
            error_log('[payment] brick resultado incierto orden ' . $idOrden . ': ' . ($res['error'] ?? ''));
            return ['success' => true, 'estado' => 'PENDING', 'codigo_publico' => $codigoPublico];
        }

        $refPago = (string) ($res['ref_pago'] ?? '');
        $up = $conn->prepare('UPDATE pagos SET ref_pago_proveedor = ? WHERE ref_externa = ?');
        $up->bind_param('ss', $refPago, $refExterna);
        $up->execute();
        $up->close();

        // Verificación backend: con MP se re-consulta el pago por API antes de confiar en un "approved"
        $info = $res;
        if ($gateway instanceof MercadoPagoGateway && ($res['estado_interno'] ?? '') === 'PAID') {
            $info = $gateway->consultarPago($refPago);
            if (!$info['success']) {
                return ['success' => true, 'estado' => 'PENDING', 'codigo_publico' => $codigoPublico];
            }
        }

        $estado = (string) ($info['estado_interno'] ?? 'PENDING');
        if ($estado === 'PAID') {
            $problema = payment_validar_contra_orden($orden, $info);
            if ($problema !== null) {
                payment_registrar_alerta($conn, $refExterna, $problema, $info['raw'] ?? []);
                return ['success' => false, 'estado' => 'PENDING', 'error' => 'Tu pago requiere revisión. Conserva tu número de orden.', 'codigo_publico' => $codigoPublico, 'http' => 409];
            }
        }

        $ap = payment_aplicar_estado($conn, $refExterna, $estado, $refPago, $info['raw'] ?? null);
        return [
            'success' => $estado !== 'FAILED',
            'estado' => $estado,
            'status_detail' => (string) ($info['status_detail'] ?? ''),
            'codigo_publico' => $codigoPublico,
            'emision_ok' => $ap['emision_ok'] ?? null,
            'error' => $estado === 'FAILED' ? 'El pago fue rechazado. Puedes intentar con otro medio de pago.' : null,
            'http' => $estado === 'FAILED' ? 402 : 200,
        ];
    } finally {
        $conn->query("SELECT RELEASE_LOCK('" . $conn->real_escape_string($lockName) . "')");
    }
}

/**
 * Guarda una alerta de validación en el pago (monto/referencia inconsistentes) sin cambiar su estado.
 */
function payment_registrar_alerta(mysqli $conn, string $refExterna, string $problema, array $raw): void
{
    error_log('[payment] ALERTA validación ' . $refExterna . ': ' . $problema);
    $resumen = json_encode(['alerta_validacion' => $problema, 'proveedor' => $raw], JSON_UNESCAPED_UNICODE);
    $st = $conn->prepare('UPDATE pagos SET payload_resumen = ? WHERE ref_externa = ?');
    $st->bind_param('ss', $resumen, $refExterna);
    $st->execute();
    $st->close();
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
 * Verifica la firma HMAC de webhooks de Mercado Pago (header x-signature).
 * @see https://www.mercadopago.com.mx/developers/es/docs/your-integrations/notifications/webhooks
 */
function payment_verificar_firma_mp(string $secret, array $server, array $query): bool
{
    if ($secret === '') {
        return false;
    }

    $xSignature = (string) ($server['HTTP_X_SIGNATURE'] ?? '');
    $xRequestId = (string) ($server['HTTP_X_REQUEST_ID'] ?? '');
    if ($xSignature === '') {
        return false;
    }

    $ts = null;
    $v1 = null;
    foreach (explode(',', $xSignature) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) !== 2) {
            continue;
        }
        $key = trim($kv[0]);
        $val = trim($kv[1]);
        if ($key === 'ts') {
            $ts = $val;
        } elseif ($key === 'v1') {
            $v1 = $val;
        }
    }
    if ($ts === null || $v1 === null || $v1 === '') {
        return false;
    }

    // PHP normaliza data.id → data_id en $_GET
    $dataId = (string) ($query['data.id'] ?? $query['data_id'] ?? '');
    if ($dataId !== '' && preg_match('/[A-Za-z]/', $dataId)) {
        $dataId = strtolower($dataId);
    }

    $manifest = '';
    if ($dataId !== '') {
        $manifest .= 'id:' . $dataId . ';';
    }
    if ($xRequestId !== '') {
        $manifest .= 'request-id:' . $xRequestId . ';';
    }
    $manifest .= 'ts:' . $ts . ';';

    $computed = hash_hmac('sha256', $manifest, $secret);
    return hash_equals($computed, $v1);
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

    $pid = (string) ($info['ref_pago'] ?? $id);
    $extRef = (string) ($info['external_reference'] ?? '');
    $pagoRow = null;

    // 1) Pago ya ligado a este id del proveedor (Bricks lo guarda al crear el cobro)
    $st = $conn->prepare('SELECT * FROM pagos WHERE ref_pago_proveedor = ? LIMIT 1');
    $st->bind_param('s', $pid);
    $st->execute();
    $pagoRow = $st->get_result()->fetch_assoc() ?: null;
    $st->close();

    $orden = $extRef !== '' ? obtenerOrdenPorCodigo($conn, $extRef) : null;

    // 2) Sin liga previa: intento abierto de la orden indicada por external_reference
    if (!$pagoRow && $orden) {
        $idOrden = (int) $orden['id_orden'];
        $st = $conn->prepare("
            SELECT * FROM pagos
            WHERE id_orden = ? AND (ref_pago_proveedor IS NULL OR ref_pago_proveedor = '')
            ORDER BY (estado_interno = 'PENDING') DESC, id_pago DESC
            LIMIT 1
        ");
        $st->bind_param('i', $idOrden);
        $st->execute();
        $pagoRow = $st->get_result()->fetch_assoc() ?: null;
        $st->close();
    }

    if (!$pagoRow) {
        return ['success' => false, 'error' => 'No se pudo relacionar el pago con una orden'];
    }
    if ((string) $pagoRow['proveedor'] !== $gateway->nombreProveedor()) {
        return ['success' => true, 'ignored' => true, 'reason' => 'pago de otro proveedor'];
    }
    $refExterna = (string) $pagoRow['ref_externa'];

    // La orden del registro de pago es la autoridad; el external_reference debe coincidir con ella
    $ordenPago = null;
    $st = $conn->prepare('SELECT codigo_publico FROM ordenes WHERE id_orden = ? LIMIT 1');
    $idOrdenPago = (int) $pagoRow['id_orden'];
    $st->bind_param('i', $idOrdenPago);
    $st->execute();
    $rowOrd = $st->get_result()->fetch_assoc();
    $st->close();
    if ($rowOrd) {
        $ordenPago = obtenerOrdenPorCodigo($conn, (string) $rowOrd['codigo_publico']);
    }
    if (!$ordenPago) {
        return ['success' => false, 'error' => 'Orden del pago no encontrada'];
    }

    if (($info['estado_interno'] ?? '') === 'PAID') {
        $problema = payment_validar_contra_orden($ordenPago, $info);
        if ($problema !== null) {
            payment_registrar_alerta($conn, $refExterna, $problema, $info['raw'] ?? []);
            // 200 para que MP no reintente indefinidamente; queda para revisión/reembolso manual
            return ['success' => true, 'ignored' => true, 'alerta' => $problema];
        }
    }

    return payment_aplicar_estado(
        $conn,
        $refExterna,
        $info['estado_interno'],
        $info['ref_pago'] ?? (string) $id,
        $info['raw'] ?? null
    );
}
