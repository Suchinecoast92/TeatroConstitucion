<?php
/**
 * Prueba Fase 4: pago aprobado → boletos + QR + idempotencia + libera holds.
 * Uso: php sql/test_fase4_emision.php
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/pagos/PaymentService.php';
require_once dirname(__DIR__) . '/includes/emision_helper.php';
require_once dirname(__DIR__) . '/sync/reservas_helper.php';

function fail(string $msg): void
{
    fwrite(STDERR, "FAIL: $msg\n");
    exit(1);
}

$conn = getLocalConnection();
if (!$conn) {
    fail('sin conexión');
}
asegurarTablaPagos($conn);

$evento = $conn->query('SELECT id_evento FROM evento WHERE finalizado = 0 ORDER BY id_evento DESC LIMIT 1')->fetch_assoc();
if (!$evento) {
    fail('sin evento activo');
}
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
    $c = 'D' . $n;
    if (!isset($ocupados[$c])) {
        $cat = resolver_categoria_asiento($conn, $idEvento, $c);
        if ($cat && !es_categoria_no_venta($cat['nombre_categoria'])) {
            $asiento = $c;
            break;
        }
    }
}
if (!$asiento) {
    fail('sin asiento libre en fila D');
}

$session = 'test_emi_' . bin2hex(random_bytes(3));
$hold = reservarAsientos($idEvento, $idFuncion, [$asiento], $session, 'online', 'test-emision');
if (!$hold['success']) {
    fail('hold: ' . json_encode($hold, JSON_UNESCAPED_UNICODE));
}

$ord = crearOrdenOnline($conn, [
    'id_evento' => $idEvento,
    'id_funcion' => $idFuncion,
    'session_id' => $session,
    'email' => 'emision@example.com',
    'nombre' => 'Test Emision',
    'asientos' => [['asiento' => $asiento, 'tipo_boleto' => 'adulto']],
]);
if (!$ord['success']) {
    liberarReservasSesion($session);
    fail('orden: ' . json_encode($ord, JSON_UNESCAPED_UNICODE));
}
$codigo = $ord['codigo_publico'];
$idOrden = (int) $ord['id_orden'];
echo "orden=$codigo asiento=$asiento\n";

$pay = payment_crear_para_orden($conn, $codigo);
if (!$pay['success']) {
    liberarReservasSesion($session);
    fail('iniciar pago');
}

$wh1 = payment_procesar_webhook($conn, ['mock' => '1', 'codigo' => $codigo, 'result' => 'approved'], []);
echo 'webhook1=' . json_encode($wh1) . "\n";
if (!$wh1['success'] || ($wh1['estado'] ?? '') !== 'PAID') {
    fail('webhook1 no PAID');
}

$boletos = emision_listar_boletos_orden($conn, $idOrden);
if (count($boletos) !== 1) {
    fail('se esperaba 1 boleto, hay ' . count($boletos));
}
$b = $boletos[0];
echo 'boleto codigo_unico=' . $b['codigo_unico'] . " asiento={$b['codigo_asiento']}\n";
if ($b['codigo_asiento'] !== $asiento) {
    fail('asiento del boleto no coincide');
}
if (!preg_match('/^[A-F0-9]{16}$/', $b['codigo_unico'])) {
    fail('formato codigo_unico inválido');
}

$qrPath = dirname(__DIR__) . '/boletos_qr/' . $b['codigo_unico'] . '.png';
if (!is_file($qrPath)) {
    fail("QR no generado en $qrPath");
}
ok_line('QR PNG generado');

// Idempotencia: segundo webhook no duplica
$wh2 = payment_procesar_webhook($conn, ['mock' => '1', 'codigo' => $codigo, 'result' => 'approved'], []);
echo 'webhook2=' . json_encode($wh2) . "\n";
if (empty($wh2['idempotent'])) {
    fail('segundo webhook debió ser idempotente');
}
$boletos2 = emision_listar_boletos_orden($conn, $idOrden);
if (count($boletos2) !== 1) {
    fail('webhook repetido duplicó boletos');
}
if ($boletos2[0]['codigo_unico'] !== $b['codigo_unico']) {
    fail('codigo_unico cambió en reintento');
}
ok_line('idempotencia OK (1 boleto)');

// Holds liberados
$dispAfter = obtenerDisponibilidadFuncion($idEvento, $idFuncion, null, $conn);
if (in_array($asiento, $dispAfter['reservados'], true)) {
    fail('hold debió liberarse tras emisión');
}
if (!in_array($asiento, $dispAfter['vendidos'], true) && !in_array($asiento, $dispAfter['ocupados'], true)) {
    // ocupados suele incluir vendidos
    $chk = $conn->prepare('
        SELECT b.estatus FROM boletos b
        INNER JOIN asientos a ON a.id_asiento = b.id_asiento
        WHERE a.codigo_asiento = ? AND b.id_evento = ? AND b.id_funcion = ? AND b.estatus = 1
        LIMIT 1
    ');
    $chk->bind_param('sii', $asiento, $idEvento, $idFuncion);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        $chk->close();
        fail('boleto activo no encontrado en BD');
    }
    $chk->close();
}
ok_line('asiento vendido y hold liberado');

// Entrada podría verificar por codigo_unico
$vb = $conn->prepare('SELECT id_boleto, estatus FROM boletos WHERE codigo_unico = ? LIMIT 1');
$vb->bind_param('s', $b['codigo_unico']);
$vb->execute();
$rowVb = $vb->get_result()->fetch_assoc();
$vb->close();
if (!$rowVb || (int) $rowVb['estatus'] !== 1) {
    fail('lookup por codigo_unico falló (control de entrada)');
}
ok_line('lookup codigo_unico compatible con entrada');

echo "PASS fase4 emision\n";
echo "(Nota: el boleto de prueba queda activo en BD para el asiento $asiento; cancélalo en admin si quieres liberarlo.)\n";

function ok_line(string $m): void
{
    echo "  OK  $m\n";
}
