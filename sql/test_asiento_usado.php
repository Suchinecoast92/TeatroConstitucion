<?php
/**
 * Prueba: un boleto ya escaneado en la entrada (estatus = 0) mantiene ocupado su asiento.
 * Mapa, apartado, candado de venta y emisión online lo tratan como vendido; un cancelado
 * (estatus = 2) sí se puede revender. El evento con boletos usados no se puede borrar.
 * Uso: php sql/test_asiento_usado.php   (limpieza: php sql/limpiar_datos_prueba.php --aplicar)
 */
putenv('MAIL_MODE=off');

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/pagos/PaymentService.php';
require_once dirname(__DIR__) . '/sync/backup_helper.php';
require_once __DIR__ . '/test_helpers.php';

function fail(string $msg): void
{
    fwrite(STDERR, "FAIL: $msg\n");
    exit(1);
}

function ok_line(string $m): void
{
    echo "  OK  $m\n";
}

$conn = getLocalConnection();
if (!$conn) {
    fail('sin conexión');
}
asegurarTablaPagos($conn);

$ctx = test_contexto_orden($conn, 'F', 'test_usado_');
$idEvento = $ctx['id_evento'];
$idFuncion = $ctx['id_funcion'];
$asiento = $ctx['asiento'];

$hold = reservarAsientos($idEvento, $idFuncion, [$asiento], $ctx['session'], 'online', 'test-usado');
if (!$hold['success']) {
    fail('hold: ' . json_encode($hold));
}
$ord = crearOrdenOnline($conn, [
    'id_evento' => $idEvento,
    'id_funcion' => $idFuncion,
    'session_id' => $ctx['session'],
    'email' => $ctx['cliente']['email'],
    'nombre' => $ctx['cliente']['nombre'],
    'telefono' => $ctx['cliente']['telefono'],
    'asientos' => [['asiento' => $asiento, 'tipo_boleto' => 'adulto']],
]);
if (!$ord['success']) {
    fail('orden: ' . json_encode($ord));
}
payment_crear_para_orden($conn, $ord['codigo_publico']);
$wh = payment_procesar_webhook($conn, ['mock' => '1', 'codigo' => $ord['codigo_publico'], 'result' => 'approved'], []);
if (($wh['estado'] ?? '') !== 'PAID') {
    fail('webhook: ' . json_encode($wh));
}
$bol = emision_listar_boletos_orden($conn, (int) $ord['id_orden'])[0] ?? null;
if (!$bol) {
    fail('sin boleto emitido');
}
echo "asiento=$asiento boleto={$bol['id_boleto']}\n";

// Escaneo en la entrada (misma sentencia que escanear_qr.php / confirmar_entrada.php)
$st = $conn->prepare('UPDATE boletos SET estatus = 0 WHERE codigo_unico = ? AND estatus = 1');
$st->bind_param('s', $bol['codigo_unico']);
$st->execute();
if ($st->affected_rows !== 1) {
    fail('no se marcó como usado');
}
$st->close();

$disp = obtenerDisponibilidadFuncion($idEvento, $idFuncion, null, $conn);
if (!in_array($asiento, $disp['vendidos'], true) || !in_array($asiento, $disp['ocupados'], true)) {
    fail('el mapa muestra libre un asiento usado');
}
ok_line('el mapa sigue mostrando ocupado el asiento usado');

$r = reservarAsientos($idEvento, $idFuncion, [$asiento], 'taq_test_usado', 'local', 'test');
if ($r['success'] || ($r['conflictos'][$asiento] ?? '') !== 'vendido') {
    liberarReservasSesion('taq_test_usado');
    fail('se pudo apartar un asiento usado: ' . json_encode($r));
}
ok_line('no se puede apartar');

$conn->begin_transaction();
try {
    verificarVentaAtomica($conn, $idEvento, $idFuncion, [$asiento], 'taq_test_usado', 'local');
    $conn->rollback();
    fail('el candado de venta dejó pasar un asiento usado');
} catch (Exception $e) {
    $conn->rollback();
}
ok_line('el candado de venta de taquilla lo rechaza');

$ord2 = crearOrdenOnline($conn, [
    'id_evento' => $idEvento,
    'id_funcion' => $idFuncion,
    'session_id' => 'test_usado_2_' . bin2hex(random_bytes(3)),
    'email' => $ctx['cliente']['email'],
    'nombre' => $ctx['cliente']['nombre'],
    'telefono' => $ctx['cliente']['telefono'],
    'asientos' => [['asiento' => $asiento, 'tipo_boleto' => 'adulto']],
]);
if (!empty($ord2['success'])) {
    fail('se creó una orden online para un asiento usado');
}
ok_line('no se puede comprar online');

$chk = verificarPuedeBorrarEvento($conn, $idEvento);
if (!empty($chk['puede'])) {
    fail('se permitiría borrar un evento con boletos usados');
}
ok_line('un evento con boletos usados no se puede borrar');

// Cancelado sí se revende
$conn->query('UPDATE boletos SET estatus = 2 WHERE id_boleto = ' . (int) $bol['id_boleto']);
$r2 = reservarAsientos($idEvento, $idFuncion, [$asiento], 'taq_test_usado', 'local', 'test');
liberarReservasSesion('taq_test_usado');
if (!$r2['success']) {
    fail('un cancelado debería poder apartarse: ' . json_encode($r2));
}
ok_line('un boleto cancelado sí libera el asiento');

echo "PASS asiento usado\n";
