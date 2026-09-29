<?php
/**
 * Fase 6 — Checkout Bricks (pago directo desde backend) con gateway mock.
 * No usa credenciales ni realiza cobros reales.
 *
 * Uso: php sql/test_fase6_bricks.php
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/pagos/PaymentService.php';
require_once dirname(__DIR__) . '/includes/reembolso_helper.php';
require_once dirname(__DIR__) . '/sync/reservas_helper.php';
require_once __DIR__ . '/test_helpers.php';

if (!(payment_resolve_gateway() instanceof MockPaymentGateway)) {
    fwrite(STDERR, "SKIP: este test requiere gateway mock (sin MP_ACCESS_TOKEN o MP_MODE=mock)\n");
    exit(1);
}

$conn = getLocalConnection();
if (!$conn) {
    fwrite(STDERR, "FAIL connection\n");
    exit(1);
}
asegurarTablaPagos($conn);

$pendientesLimpieza = [];
$pagadasLimpieza = [];
$sesionesLimpieza = [];

function t_cleanup(mysqli $conn): void
{
    global $pendientesLimpieza, $pagadasLimpieza, $sesionesLimpieza;
    foreach ($pagadasLimpieza as $codigo) {
        $o = obtenerOrdenPorCodigo($conn, $codigo);
        if ($o && $o['estado'] === 'pagada') {
            reembolsar_orden_online($conn, $codigo, 'cleanup test_fase6');
        }
    }
    foreach ($pendientesLimpieza as $codigo) {
        $o = obtenerOrdenPorCodigo($conn, $codigo);
        if (!$o) {
            continue;
        }
        $id = (int) $o['id_orden'];
        $conn->query("UPDATE pagos SET estado_interno = 'FAILED' WHERE id_orden = {$id} AND estado_interno = 'PENDING'");
        $conn->query("UPDATE ordenes SET estado = 'expirada' WHERE id_orden = {$id} AND estado IN ('pendiente','fallida')");
    }
    foreach ($sesionesLimpieza as $s) {
        liberarReservasSesion($s);
    }
}

function t_fail(mysqli $conn, string $msg): void
{
    t_cleanup($conn);
    fwrite(STDERR, "FAIL: {$msg}\n");
    exit(1);
}

function t_ok(string $msg): void
{
    echo "  OK  {$msg}\n";
}

/** Crea hold + orden pendiente. @return array{codigo:string,session:string,id_orden:int,asiento:string,total:float} */
function t_nueva_orden(mysqli $conn, int $idEvento, int $idFuncion, string $fila): array
{
    global $pendientesLimpieza, $sesionesLimpieza;
    $asiento = test_pick_asiento_libre($conn, $idEvento, $idFuncion, $fila);
    if (!$asiento) {
        t_fail($conn, 'sin asiento libre');
    }
    $session = 'test_brk_' . bin2hex(random_bytes(4));
    $sesionesLimpieza[] = $session;
    $hold = reservarAsientos($idEvento, $idFuncion, [$asiento], $session, 'online', 'test');
    if (!$hold['success']) {
        t_fail($conn, 'hold ' . json_encode($hold));
    }
    $cli = test_cliente_valido('brk' . bin2hex(random_bytes(2)));
    $ord = crearOrdenOnline($conn, [
        'id_evento' => $idEvento,
        'id_funcion' => $idFuncion,
        'session_id' => $session,
        'email' => $cli['email'],
        'nombre' => $cli['nombre'],
        'telefono' => $cli['telefono'],
        'asientos' => [['asiento' => $asiento, 'tipo_boleto' => 'adulto']],
    ]);
    if (!$ord['success']) {
        t_fail($conn, 'orden ' . json_encode($ord));
    }
    $pendientesLimpieza[] = $ord['codigo_publico'];
    $o = obtenerOrdenPorCodigo($conn, $ord['codigo_publico']);
    return [
        'codigo' => $ord['codigo_publico'],
        'session' => $session,
        'id_orden' => (int) $o['id_orden'],
        'asiento' => $asiento,
        'total' => (float) $o['total'],
    ];
}

function t_form(string $token, string $result = 'approved', array $extra = []): array
{
    return array_merge([
        'token' => $token,
        'payment_method_id' => 'visa',
        'installments' => 1,
        'payer' => ['email' => 'comprador@example.com'],
        'mock_result' => $result,
    ], $extra);
}

function t_count_pagos(mysqli $conn, int $idOrden): int
{
    $r = $conn->query("SELECT COUNT(*) AS n FROM pagos WHERE id_orden = {$idOrden}");
    return (int) ($r->fetch_assoc()['n'] ?? 0);
}

function t_estado_orden(mysqli $conn, string $codigo): string
{
    $o = obtenerOrdenPorCodigo($conn, $codigo);
    return (string) ($o['estado'] ?? '');
}

$pick = test_pick_funcion_vendible($conn);
if (!$pick) {
    t_fail($conn, 'sin función con venta abierta');
}
[$idEvento, $idFuncion] = [$pick['id_evento'], $pick['id_funcion']];
echo "=== Fase 6: Checkout Bricks (mock) — evento #{$idEvento} función #{$idFuncion} ===\n";

// 1) Pago aprobado + 17) emisión duplicada
echo "\n1) Pago aprobado / 17) emisión duplicada\n";
$o1 = t_nueva_orden($conn, $idEvento, $idFuncion, 'E');
$r = payment_procesar_brick($conn, $o1['codigo'], $o1['session'], t_form('tokA1234567'));
if (!$r['success'] || ($r['estado'] ?? '') !== 'PAID') {
    t_fail($conn, 'aprobado: ' . json_encode($r));
}
if (t_estado_orden($conn, $o1['codigo']) !== 'pagada') {
    t_fail($conn, 'orden no pagada tras aprobado');
}
$pagadasLimpieza[] = $o1['codigo'];
$bol1 = emision_listar_boletos_orden($conn, $o1['id_orden']);
if (count($bol1) !== 1) {
    t_fail($conn, 'se esperaba 1 boleto, hay ' . count($bol1));
}
t_ok('aprobado → orden pagada + 1 boleto');
$e2 = emitir_boletos_orden_pagada($conn, $o1['id_orden']);
if ((int) ($e2['emitidos'] ?? -1) !== 0 || count(emision_listar_boletos_orden($conn, $o1['id_orden'])) !== 1) {
    t_fail($conn, 'emisión duplicada creó boletos extra');
}
t_ok('re-emitir no duplica boletos');

// 5) Doble clic (mismo token) y 6) reintento HTTP
echo "\n5) Doble clic / 6) reintento HTTP\n";
$r2 = payment_procesar_brick($conn, $o1['codigo'], $o1['session'], t_form('tokA1234567'));
if (!$r2['success'] || ($r2['estado'] ?? '') !== 'PAID' || empty($r2['idempotent'])) {
    t_fail($conn, 'reintento mismo token no idempotente: ' . json_encode($r2));
}
$r3 = payment_procesar_brick($conn, $o1['codigo'], $o1['session'], t_form('tokOTRO99999'));
if (($r3['estado'] ?? '') !== 'PAID' || empty($r3['idempotent'])) {
    t_fail($conn, 'segundo token tras PAID debió ser idempotente: ' . json_encode($r3));
}
if (t_count_pagos($conn, $o1['id_orden']) !== 1) {
    t_fail($conn, 'se crearon pagos duplicados: ' . t_count_pagos($conn, $o1['id_orden']));
}
t_ok('mismo token y token nuevo tras PAID → sin segundo cobro (1 fila en pagos)');

// 7) Webhook duplicado
echo "\n7) Webhook duplicado\n";
$rowP = $conn->query("SELECT ref_externa, ref_pago_proveedor FROM pagos WHERE id_orden = {$o1['id_orden']} LIMIT 1")->fetch_assoc();
$w1 = payment_aplicar_estado($conn, $rowP['ref_externa'], 'PAID', $rowP['ref_pago_proveedor'], ['dup' => 1]);
$w2 = payment_aplicar_estado($conn, $rowP['ref_externa'], 'PAID', $rowP['ref_pago_proveedor'], ['dup' => 2]);
if (empty($w1['idempotent']) || empty($w2['idempotent'])) {
    t_fail($conn, 'webhook duplicado no idempotente');
}
if (count(emision_listar_boletos_orden($conn, $o1['id_orden'])) !== 1) {
    t_fail($conn, 'webhook duplicado duplicó boletos');
}
t_ok('dos notificaciones PAID → sin cambios ni boletos extra');

// 4) Reembolso y 18) cancelación de boletos
echo "\n4) Reembolso / 18) cancelación de boletos\n";
$ref = reembolsar_orden_online($conn, $o1['codigo'], 'test reembolso');
if (!$ref['success'] || t_estado_orden($conn, $o1['codigo']) !== 'reembolsada') {
    t_fail($conn, 'reembolso: ' . json_encode($ref));
}
$activos = array_filter(emision_listar_boletos_orden($conn, $o1['id_orden']), static fn($b) => (int) ($b['estatus'] ?? 0) === 1);
if ($activos) {
    t_fail($conn, 'quedaron boletos activos tras reembolso');
}
$pagadasLimpieza = array_diff($pagadasLimpieza, [$o1['codigo']]);
t_ok('reembolsada + boletos cancelados');

// 2) Rechazado y 13) aprobado después de fallo
echo "\n2) Rechazado / 13) aprobado tras intento fallido\n";
$o2 = t_nueva_orden($conn, $idEvento, $idFuncion, 'E');
$rr = payment_procesar_brick($conn, $o2['codigo'], $o2['session'], t_form('tokRECH00001', 'rejected'));
if ($rr['success'] || ($rr['estado'] ?? '') !== 'FAILED') {
    t_fail($conn, 'rechazo: ' . json_encode($rr));
}
if (t_estado_orden($conn, $o2['codigo']) !== 'fallida') {
    t_fail($conn, 'orden debió quedar fallida');
}
t_ok('rechazado → pago FAILED, orden fallida, asiento sigue apartado');
$ra = payment_procesar_brick($conn, $o2['codigo'], $o2['session'], t_form('tokOK0000002'));
if (!$ra['success'] || ($ra['estado'] ?? '') !== 'PAID' || t_estado_orden($conn, $o2['codigo']) !== 'pagada') {
    t_fail($conn, 'reintento tras rechazo: ' . json_encode($ra));
}
$pagadasLimpieza[] = $o2['codigo'];
t_ok('segundo intento con otra tarjeta → pagada');

// 3) Pendiente + bloqueo de segundo intento distinto
echo "\n3) Pago pendiente\n";
$o3 = t_nueva_orden($conn, $idEvento, $idFuncion, 'E');
$rp = payment_procesar_brick($conn, $o3['codigo'], $o3['session'], t_form('tokPEND00003', 'in_process'));
if (!$rp['success'] || ($rp['estado'] ?? '') !== 'PENDING' || t_estado_orden($conn, $o3['codigo']) !== 'pendiente') {
    t_fail($conn, 'pendiente: ' . json_encode($rp));
}
$rp2 = payment_procesar_brick($conn, $o3['codigo'], $o3['session'], t_form('tokPEND00004'));
if ($rp2['success'] || ($rp2['estado'] ?? '') !== 'PENDING') {
    t_fail($conn, 'con un pago en revisión no debe iniciarse otro cobro: ' . json_encode($rp2));
}
if (!orden_tiene_pago_pendiente_activo($conn, $o3['id_orden'])) {
    t_fail($conn, 'pago pendiente no protege la orden');
}
t_ok('pendiente → orden pendiente, holds protegidos, segundo cobro bloqueado');

// 12) Aprobado cuando la orden ya expiró
echo "\n12) Aprobación tardía con orden expirada\n";
$conn->query("UPDATE ordenes SET estado = 'expirada' WHERE id_orden = {$o3['id_orden']}");
$rowP3 = $conn->query("SELECT ref_externa, ref_pago_proveedor FROM pagos WHERE id_orden = {$o3['id_orden']} ORDER BY id_pago DESC LIMIT 1")->fetch_assoc();
$late = payment_aplicar_estado($conn, $rowP3['ref_externa'], 'PAID', $rowP3['ref_pago_proveedor'], ['late' => 1]);
if (($late['estado'] ?? '') !== 'PAID' || t_estado_orden($conn, $o3['codigo']) !== 'pagada') {
    t_fail($conn, 'aprobación tardía: ' . json_encode($late));
}
$pagadasLimpieza[] = $o3['codigo'];
t_ok('dinero confirmado tras expirar → orden pagada (asiento seguía protegido)');

// 10) Monto incorrecto y 11) referencia de otra orden
echo "\n10) Monto incorrecto / 11) referencia de otra orden\n";
$o4 = t_nueva_orden($conn, $idEvento, $idFuncion, 'E');
$rm = payment_procesar_brick($conn, $o4['codigo'], $o4['session'], t_form('tokMONTO0005', 'approved', ['mock_monto' => $o4['total'] - 50]));
if (($rm['estado'] ?? '') === 'PAID' || t_estado_orden($conn, $o4['codigo']) === 'pagada') {
    t_fail($conn, 'monto incorrecto no debió marcar pagada: ' . json_encode($rm));
}
$alerta = $conn->query("SELECT payload_resumen FROM pagos WHERE id_orden = {$o4['id_orden']} ORDER BY id_pago DESC LIMIT 1")->fetch_assoc();
if (strpos((string) ($alerta['payload_resumen'] ?? ''), 'alerta_validacion') === false) {
    t_fail($conn, 'no se registró alerta de validación');
}
t_ok('monto distinto → NO pagada, alerta registrada');

$o5 = t_nueva_orden($conn, $idEvento, $idFuncion, 'E');
$rx = payment_procesar_brick($conn, $o5['codigo'], $o5['session'], t_form('tokREF000006', 'approved', ['mock_external_reference' => $o4['codigo']]));
if (($rx['estado'] ?? '') === 'PAID' || t_estado_orden($conn, $o5['codigo']) === 'pagada' || t_estado_orden($conn, $o4['codigo']) === 'pagada') {
    t_fail($conn, 'referencia cruzada no debió pagar ninguna orden: ' . json_encode($rx));
}
$v = payment_validar_contra_orden(obtenerOrdenPorCodigo($conn, $o5['codigo']), ['external_reference' => $o5['codigo'], 'monto' => $o5['total'], 'moneda' => 'USD']);
if ($v === null) {
    t_fail($conn, 'moneda distinta debió rechazarse');
}
t_ok('external_reference de otra orden / moneda distinta → rechazados');

// Monto manipulado desde el navegador y asientos perdidos: no se cobra
echo "\nExtra) Manipulación de precio / asientos perdidos\n";
$o6 = t_nueva_orden($conn, $idEvento, $idFuncion, 'E');
$rt = payment_procesar_brick($conn, $o6['codigo'], $o6['session'], t_form('tokTAMP00007', 'approved', ['transaction_amount' => 1]));
if ($rt['success'] || t_count_pagos($conn, $o6['id_orden']) !== 0) {
    t_fail($conn, 'precio del navegador distinto debió rechazarse sin crear pago');
}
t_ok('transaction_amount manipulado → rechazado sin crear cobro');
$sesAjena = payment_procesar_brick($conn, $o6['codigo'], 'otra_sesion_123', t_form('tokSES000008'));
if ($sesAjena['success'] || ($sesAjena['http'] ?? 0) !== 403) {
    t_fail($conn, 'sesión ajena debió ser rechazada');
}
t_ok('sesión que no es dueña de la orden → 403');
liberarReservasSesion($o6['session']);
$rl = payment_procesar_brick($conn, $o6['codigo'], $o6['session'], t_form('tokLOST00009'));
if ($rl['success'] || t_count_pagos($conn, $o6['id_orden']) !== 0) {
    t_fail($conn, 'sin holds no debe cobrarse: ' . json_encode($rl));
}
t_ok('asientos ya no apartados → no se crea cobro');

// 8) y 9) Firma del webhook
echo "\n8) Firma inválida / 9) sin firma\n";
$secret = 'secreto_de_prueba_' . bin2hex(random_bytes(4));
$ts = (string) time();
$reqId = 'req-' . bin2hex(random_bytes(4));
$dataId = '123456789';
$manifest = 'id:' . $dataId . ';request-id:' . $reqId . ';ts:' . $ts . ';';
$firma = hash_hmac('sha256', $manifest, $secret);
$srvOk = ['HTTP_X_SIGNATURE' => "ts={$ts},v1={$firma}", 'HTTP_X_REQUEST_ID' => $reqId];
if (!payment_verificar_firma_mp($secret, $srvOk, ['data_id' => $dataId])) {
    t_fail($conn, 'firma válida rechazada');
}
$srvBad = ['HTTP_X_SIGNATURE' => "ts={$ts},v1=" . str_repeat('0', 64), 'HTTP_X_REQUEST_ID' => $reqId];
if (payment_verificar_firma_mp($secret, $srvBad, ['data_id' => $dataId])) {
    t_fail($conn, 'firma inválida aceptada');
}
if (payment_verificar_firma_mp($secret, ['HTTP_X_REQUEST_ID' => $reqId], ['data_id' => $dataId])) {
    t_fail($conn, 'webhook sin firma aceptado');
}
if (payment_verificar_firma_mp($secret, $srvOk, ['data_id' => '999'])) {
    t_fail($conn, 'firma de otro pago aceptada');
}
t_ok('firma válida OK; inválida, ausente o de otro id → rechazadas');

// 14) 15) 16) Concurrencia de butacas
echo "\n14-16) Concurrencia de butacas\n";
$asX = test_pick_asiento_libre($conn, $idEvento, $idFuncion, 'F');
$sA = 'test_brk_a_' . bin2hex(random_bytes(3));
$sB = 'test_brk_b_' . bin2hex(random_bytes(3));
$sL = 'test_brk_l_' . bin2hex(random_bytes(3));
$sesionesLimpieza = array_merge($sesionesLimpieza, [$sA, $sB, $sL]);
if (!reservarAsientos($idEvento, $idFuncion, [$asX], $sA, 'online', 'a')['success']) {
    t_fail($conn, 'hold inicial');
}
if (reservarAsientos($idEvento, $idFuncion, [$asX], $sB, 'online', 'b')['success']) {
    t_fail($conn, 'dos usuarios online apartaron la misma butaca');
}
t_ok('dos compradores online → solo uno aparta');
$vendioTaquilla = true;
try {
    $conn->begin_transaction();
    verificarVentaAtomica($conn, $idEvento, $idFuncion, [$asX], $sL, 'local');
} catch (Throwable $e) {
    $vendioTaquilla = false;
}
$conn->rollback();
if ($vendioTaquilla) {
    t_fail($conn, 'taquilla pudo vender butaca apartada online');
}
t_ok('taquilla no puede vender butaca apartada online');
liberarReservasSesion($sA);
if (!reservarAsientos($idEvento, $idFuncion, [$asX], $sL, 'local', 'taquilla')['success']) {
    t_fail($conn, 'hold local');
}
if (reservarAsientos($idEvento, $idFuncion, [$asX], $sB, 'online', 'b')['success']) {
    t_fail($conn, 'online tomó butaca apartada en taquilla');
}
t_ok('compra online no puede tomar butaca apartada en taquilla');

t_cleanup($conn);
echo "\nPASS fase6 bricks\n";
