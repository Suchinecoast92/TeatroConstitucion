<?php
/**
 * Prueba Fase 3: orden + preferencia mock + webhook simulado idempotente.
 * Uso: php sql/test_fase3_pagos.php
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/pagos/PaymentService.php';
require_once dirname(__DIR__) . '/sync/reservas_helper.php';

$conn = getLocalConnection();
if (!$conn) {
    fwrite(STDERR, "FAIL connection\n");
    exit(1);
}
asegurarTablaPagos($conn);

$evento = $conn->query("SELECT id_evento FROM evento WHERE finalizado = 0 ORDER BY id_evento DESC LIMIT 1")->fetch_assoc();
$idEvento = (int) $evento['id_evento'];
$st = $conn->prepare('SELECT id_funcion FROM funciones WHERE id_evento = ? ORDER BY fecha_hora DESC LIMIT 1');
$st->bind_param('i', $idEvento);
$st->execute();
$idFuncion = (int) $st->get_result()->fetch_assoc()['id_funcion'];
$st->close();

$disp = obtenerDisponibilidadFuncion($idEvento, $idFuncion, null, $conn);
$ocupados = array_flip($disp['ocupados']);
$asiento = null;
for ($n = 1; $n <= 26; $n++) {
    $c = 'C' . $n;
    if (!isset($ocupados[$c])) {
        $cat = resolver_categoria_asiento($conn, $idEvento, $c);
        if ($cat && !es_categoria_no_venta($cat['nombre_categoria'])) {
            $asiento = $c;
            break;
        }
    }
}
if (!$asiento) {
    fwrite(STDERR, "FAIL sin asiento\n");
    exit(1);
}

$session = 'test_pay_' . bin2hex(random_bytes(3));
$hold = reservarAsientos($idEvento, $idFuncion, [$asiento], $session, 'online', 'test');
if (!$hold['success']) {
    fwrite(STDERR, 'FAIL hold ' . json_encode($hold) . "\n");
    exit(1);
}

$ord = crearOrdenOnline($conn, [
    'id_evento' => $idEvento,
    'id_funcion' => $idFuncion,
    'session_id' => $session,
    'email' => 'pago@example.com',
    'nombre' => 'Test Pago',
    'asientos' => [['asiento' => $asiento, 'tipo_boleto' => 'adulto']],
]);
if (!$ord['success']) {
    liberarReservasSesion($session);
    fwrite(STDERR, 'FAIL orden ' . json_encode($ord) . "\n");
    exit(1);
}
echo 'orden=' . $ord['codigo_publico'] . "\n";

$pay = payment_crear_para_orden($conn, $ord['codigo_publico']);
echo 'iniciar=' . json_encode($pay, JSON_UNESCAPED_UNICODE) . "\n";
if (!$pay['success']) {
    fwrite(STDERR, "FAIL iniciar pago\n");
    exit(1);
}

$wh1 = payment_procesar_webhook($conn, ['mock' => '1', 'codigo' => $ord['codigo_publico'], 'result' => 'approved'], []);
echo 'webhook1=' . json_encode($wh1) . "\n";
if (!$wh1['success'] || ($wh1['estado'] ?? '') !== 'PAID') {
    fwrite(STDERR, "FAIL webhook1\n");
    exit(1);
}

$wh2 = payment_procesar_webhook($conn, ['mock' => '1', 'codigo' => $ord['codigo_publico'], 'result' => 'approved'], []);
echo 'webhook2=' . json_encode($wh2) . "\n";
if (!$wh2['success'] || empty($wh2['idempotent'])) {
    fwrite(STDERR, "FAIL idempotencia\n");
    exit(1);
}

$got = obtenerOrdenPorCodigo($conn, $ord['codigo_publico']);
if (($got['estado'] ?? '') !== 'pagada') {
    fwrite(STDERR, "FAIL orden no pagada\n");
    exit(1);
}

// cleanup soft
$up = $conn->prepare("UPDATE ordenes SET estado = 'cancelada' WHERE id_orden = ?");
$idO = (int) $ord['id_orden'];
$up->bind_param('i', $idO);
$up->execute();
$up->close();
liberarReservasSesion($session);

// --- Protección: no expirar/liberar con pago PENDING activo ---
echo "--- pago_en_curso ---\n";
$session2 = 'test_paycurso_' . bin2hex(random_bytes(3));
$disp2 = obtenerDisponibilidadFuncion($idEvento, $idFuncion, null, $conn);
$ocupados2 = array_flip($disp2['ocupados']);
$asiento2 = null;
for ($n = 1; $n <= 26; $n++) {
    $c = 'D' . $n;
    if (!isset($ocupados2[$c])) {
        $cat = resolver_categoria_asiento($conn, $idEvento, $c);
        if ($cat && !es_categoria_no_venta($cat['nombre_categoria'])) {
            $asiento2 = $c;
            break;
        }
    }
}
if (!$asiento2) {
    fwrite(STDERR, "FAIL sin asiento2\n");
    exit(1);
}
$hold2 = reservarAsientos($idEvento, $idFuncion, [$asiento2], $session2, 'online', 'test');
if (!$hold2['success']) {
    fwrite(STDERR, 'FAIL hold2 ' . json_encode($hold2) . "\n");
    exit(1);
}
$ord2 = crearOrdenOnline($conn, [
    'id_evento' => $idEvento,
    'id_funcion' => $idFuncion,
    'session_id' => $session2,
    'email' => 'pagocurso@example.com',
    'nombre' => 'Test Curso',
    'asientos' => [['asiento' => $asiento2, 'tipo_boleto' => 'adulto']],
]);
if (!$ord2['success']) {
    liberarReservasSesion($session2);
    fwrite(STDERR, 'FAIL orden2 ' . json_encode($ord2) . "\n");
    exit(1);
}
$pay2 = payment_crear_para_orden($conn, $ord2['codigo_publico']);
if (!$pay2['success']) {
    fwrite(STDERR, "FAIL iniciar pago2\n");
    exit(1);
}
$idO2 = (int) $ord2['id_orden'];
$conn->query("UPDATE ordenes SET expira_en = DATE_SUB(NOW(), INTERVAL 10 MINUTE) WHERE id_orden = {$idO2}");
$nExp = expirarOrdenesPendientes($conn);
$got2 = obtenerOrdenPorCodigo($conn, $ord2['codigo_publico']);
if (($got2['estado'] ?? '') !== 'pendiente') {
    fwrite(STDERR, 'FAIL orden2 expiró con PENDING: ' . ($got2['estado'] ?? '?') . " (expiradas={$nExp})\n");
    exit(1);
}
if (!orden_tiene_pago_pendiente_activo($conn, $idO2)) {
    fwrite(STDERR, "FAIL no detecta pago pendiente activo\n");
    exit(1);
}
if (!sesion_tiene_pago_pendiente_activo($conn, $session2)) {
    fwrite(STDERR, "FAIL sesión no protegida\n");
    exit(1);
}
$whCurso = payment_procesar_webhook($conn, ['mock' => '1', 'codigo' => $ord2['codigo_publico'], 'result' => 'approved'], []);
if (!$whCurso['success'] || ($whCurso['estado'] ?? '') !== 'PAID') {
    fwrite(STDERR, "FAIL webhook tras ventana vencida en UI\n");
    exit(1);
}
$got2b = obtenerOrdenPorCodigo($conn, $ord2['codigo_publico']);
if (($got2b['estado'] ?? '') !== 'pagada') {
    fwrite(STDERR, "FAIL no pagó tras protección\n");
    exit(1);
}
$up2 = $conn->prepare("UPDATE ordenes SET estado = 'cancelada' WHERE id_orden = ?");
$up2->bind_param('i', $idO2);
$up2->execute();
$up2->close();
liberarReservasSesion($session2);
echo "pago_en_curso OK\n";

echo "PASS fase3 pagos\n";
