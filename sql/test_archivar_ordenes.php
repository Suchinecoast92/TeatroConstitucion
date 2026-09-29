<?php
/**
 * Prueba: archivar un evento se bloquea mientras sus órdenes online lo necesitan
 * (pago en curso, pagada sin emitir, pagada con función pendiente). Usa ordenes_bloqueos_archivar().
 * Mueve la fecha de la función del evento [PRUEBA] y la restaura al final.
 * Uso: php sql/crear_evento_prueba.php && php sql/test_archivar_ordenes.php
 */
putenv('MAIL_MODE=off');

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/pagos/PaymentService.php';
require_once __DIR__ . '/test_helpers.php';

$restaurar = null;
function fail(string $msg): void
{
    global $restaurar;
    if (is_callable($restaurar)) {
        $restaurar();
    }
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

$ctx = test_contexto_orden($conn, 'H', 'test_archivar_');
$idEvento = $ctx['id_evento'];
$idFuncion = $ctx['id_funcion'];
$original = $conn->query("SELECT fecha_hora FROM funciones WHERE id_funcion = $idFuncion")->fetch_row()[0];
$restaurar = function () use ($conn, $idFuncion, $original): void {
    $st = $conn->prepare('UPDATE funciones SET fecha_hora = ? WHERE id_funcion = ?');
    $st->bind_param('si', $original, $idFuncion);
    $st->execute();
    $st->close();
};

// Otras órdenes del evento de prueba (de pruebas anteriores) no deben influir
$previas = [];
$res = $conn->query("SELECT id_orden, estado, expira_en FROM ordenes WHERE id_evento = $idEvento");
while ($r = $res->fetch_assoc()) {
    $previas[] = $r;
}
$conn->query("UPDATE ordenes SET estado = 'expirada' WHERE id_evento = $idEvento");
$restaurarOrdenes = function () use ($conn, $previas): void {
    foreach ($previas as $p) {
        $st = $conn->prepare('UPDATE ordenes SET estado = ?, expira_en = ? WHERE id_orden = ?');
        $st->bind_param('ssi', $p['estado'], $p['expira_en'], $p['id_orden']);
        $st->execute();
        $st->close();
    }
};
$restaurarFecha = $restaurar;
$restaurar = function () use ($restaurarFecha, $restaurarOrdenes): void {
    $restaurarFecha();
    $restaurarOrdenes();
};

if (ordenes_bloqueos_archivar($conn, $idEvento)) {
    fail('bloqueado sin órdenes activas: ' . json_encode(ordenes_bloqueos_archivar($conn, $idEvento)));
}
ok_line('sin órdenes activas se puede archivar');

reservarAsientos($idEvento, $idFuncion, [$ctx['asiento']], $ctx['session'], 'online', 'test-archivar');
$ord = crearOrdenOnline($conn, [
    'id_evento' => $idEvento,
    'id_funcion' => $idFuncion,
    'session_id' => $ctx['session'],
    'email' => $ctx['cliente']['email'],
    'nombre' => $ctx['cliente']['nombre'],
    'telefono' => $ctx['cliente']['telefono'],
    'asientos' => [['asiento' => $ctx['asiento'], 'tipo_boleto' => 'adulto']],
]);
if (!$ord['success']) {
    fail('orden: ' . json_encode($ord));
}
$idOrden = (int) $ord['id_orden'];

$b = ordenes_bloqueos_archivar($conn, $idEvento);
if (count($b) !== 1 || strpos($b[0], 'pago en curso') === false) {
    fail('orden pendiente no bloquea: ' . json_encode($b));
}
ok_line('orden pendiente bloquea: ' . $b[0]);

payment_crear_para_orden($conn, $ord['codigo_publico']);
$conn->query("UPDATE ordenes SET expira_en = NOW() - INTERVAL 1 MINUTE WHERE id_orden = $idOrden");
if (!ordenes_bloqueos_archivar($conn, $idEvento)) {
    fail('pago en pasarela con orden vencida no bloquea');
}
ok_line('pago abierto en la pasarela bloquea aunque la orden haya vencido');

$wh = payment_procesar_webhook($conn, ['mock' => '1', 'codigo' => $ord['codigo_publico'], 'result' => 'approved'], []);
if (($wh['estado'] ?? '') !== 'PAID') {
    fail('webhook: ' . json_encode($wh));
}
$b = ordenes_bloqueos_archivar($conn, $idEvento);
if (count($b) !== 1 || strpos($b[0], 'aún no terminan') === false) {
    fail('pagada con función futura no bloquea: ' . json_encode($b));
}
ok_line('orden pagada con función futura bloquea');

$conn->query("UPDATE funciones SET fecha_hora = NOW() - INTERVAL 6 HOUR WHERE id_funcion = $idFuncion");
if ($b = ordenes_bloqueos_archivar($conn, $idEvento)) {
    fail('función terminada sigue bloqueando: ' . json_encode($b));
}
ok_line('cuando la función ya terminó, se puede archivar');

$idBoleto = (int) $conn->query("SELECT id_boleto FROM orden_items WHERE id_orden = $idOrden")->fetch_row()[0];
$conn->query("UPDATE orden_items SET id_boleto = NULL WHERE id_orden = $idOrden");
$b = ordenes_bloqueos_archivar($conn, $idEvento);
$conn->query("UPDATE orden_items SET id_boleto = $idBoleto WHERE id_orden = $idOrden");
if (count($b) !== 1 || strpos($b[0], 'sin emitir') === false) {
    fail('pagada sin emitir no bloquea: ' . json_encode($b));
}
ok_line('orden pagada con boletos sin emitir bloquea');

$restaurar();
echo "PASS archivar con órdenes online\n";
