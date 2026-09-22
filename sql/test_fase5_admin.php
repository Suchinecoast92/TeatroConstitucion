<?php
/**
 * Prueba Fase 5: origen online + listado admin + reembolso mock.
 * Uso: php sql/test_fase5_admin.php
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/reembolso_helper.php';
require_once dirname(__DIR__) . '/sync/reservas_helper.php';
require_once __DIR__ . '/test_helpers.php';

function fail(string $m): void
{
    fwrite(STDERR, "FAIL: $m\n");
    exit(1);
}

$conn = getLocalConnection();
if (!$conn) {
    fail('sin conexión');
}
asegurarTablaPagos($conn);
asegurar_origen_boletos($conn);

$ctx = test_contexto_orden($conn, 'E', 'test_f5_');
$idEvento = $ctx['id_evento'];
$idFuncion = $ctx['id_funcion'];
$asiento = $ctx['asiento'];
$session = $ctx['session'];
$cliente = $ctx['cliente'];

$hold = reservarAsientos($idEvento, $idFuncion, [$asiento], $session, 'online', 'fase5');
if (!$hold['success']) {
    fail('hold');
}

$ord = crearOrdenOnline($conn, [
    'id_evento' => $idEvento,
    'id_funcion' => $idFuncion,
    'session_id' => $session,
    'email' => $cliente['email'],
    'nombre' => $cliente['nombre'],
    'telefono' => $cliente['telefono'],
    'asientos' => [['asiento' => $asiento, 'tipo_boleto' => 'adulto']],
]);
if (!$ord['success']) {
    liberarReservasSesion($session);
    fail('orden');
}
$codigo = $ord['codigo_publico'];
echo "orden=$codigo asiento=$asiento\n";

$pay = payment_crear_para_orden($conn, $codigo);
if (!$pay['success']) {
    fail('pago');
}
$wh = payment_procesar_webhook($conn, ['mock' => '1', 'codigo' => $codigo, 'result' => 'approved'], []);
if (($wh['estado'] ?? '') !== 'PAID') {
    fail('no PAID');
}

$boletos = emision_listar_boletos_orden($conn, (int) $ord['id_orden']);
if (count($boletos) !== 1) {
    fail('sin boleto emitido');
}
$idBol = (int) $boletos[0]['id_boleto'];

$og = $conn->query("SELECT origen FROM boletos WHERE id_boleto = $idBol")->fetch_assoc();
if (($og['origen'] ?? '') !== 'online') {
    fail('origen esperado online, got ' . ($og['origen'] ?? 'null'));
}
echo "  OK  origen=online\n";

$lista = admin_listar_ordenes($conn, ['q' => $codigo]);
if (!$lista || $lista[0]['codigo_publico'] !== $codigo) {
    fail('listar no encuentra orden');
}
echo "  OK  listado admin\n";

$ref = reembolsar_orden_online($conn, $codigo, 'test fase5');
echo 'reembolso=' . json_encode($ref, JSON_UNESCAPED_UNICODE) . "\n";
if (!$ref['success']) {
    fail('reembolso: ' . ($ref['error'] ?? ''));
}
if ((int) ($ref['boletos_cancelados'] ?? 0) < 1) {
    fail('no canceló boletos');
}

$got = obtenerOrdenPorCodigo($conn, $codigo);
if (($got['estado'] ?? '') !== 'reembolsada') {
    fail('orden no reembolsada');
}
$stB = $conn->prepare('SELECT estatus FROM boletos WHERE id_boleto = ?');
$stB->bind_param('i', $idBol);
$stB->execute();
$est = (int) $stB->get_result()->fetch_assoc()['estatus'];
$stB->close();
if ($est !== 2) {
    fail('boleto no cancelado (estatus=' . $est . ')');
}
echo "  OK  reembolso + boleto cancelado\n";

$again = reembolsar_orden_online($conn, $codigo, 'otra vez');
if ($again['success']) {
    fail('segundo reembolso debió fallar');
}
echo "  OK  reembolso idempotente (rechaza duplicado)\n";

echo "PASS fase5 admin\n";
