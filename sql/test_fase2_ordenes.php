<?php
/**
 * Prueba Fase 2: cotizar + crear orden (sin pago).
 * Uso: php sql/test_fase2_ordenes.php
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/ordenes_helper.php';
require_once dirname(__DIR__) . '/sync/reservas_helper.php';
require_once __DIR__ . '/test_helpers.php';

$conn = getLocalConnection();
if (!$conn) {
    fwrite(STDERR, "FAIL connection\n");
    exit(1);
}
asegurarTablasOrdenes($conn);

$ctx = test_contexto_orden($conn, 'B', 'test_ord_');
$idEvento = $ctx['id_evento'];
$idFuncion = $ctx['id_funcion'];
$asiento = $ctx['asiento'];
$session = $ctx['session'];
$cliente = $ctx['cliente'];

echo "Evento=$idEvento funcion=$idFuncion asiento=$asiento\n";

$hold = reservarAsientos($idEvento, $idFuncion, [$asiento], $session, 'online', 'test');
if (!$hold['success']) {
    fwrite(STDERR, 'FAIL hold: ' . json_encode($hold) . "\n");
    exit(1);
}

$cot = calcular_cotizacion_online($conn, $idEvento, [
    ['asiento' => $asiento, 'tipo_boleto' => 'adulto', 'precio_final' => 1], // precio falso debe ignorarse
]);
if (!$cot['success']) {
    liberarReservasSesion($session);
    fwrite(STDERR, 'FAIL cotizar: ' . ($cot['error'] ?? '') . "\n");
    exit(1);
}
echo 'cotizacion total=' . $cot['total'] . ' precio_item=' . $cot['items'][0]['precio_final'] . "\n";

$ord = crearOrdenOnline($conn, [
    'id_evento' => $idEvento,
    'id_funcion' => $idFuncion,
    'session_id' => $session,
    'email' => $cliente['email'],
    'nombre' => $cliente['nombre'],
    'telefono' => $cliente['telefono'],
    'asientos' => [['asiento' => $asiento, 'tipo_boleto' => 'adulto']],
]);
echo 'crear: ' . json_encode($ord, JSON_UNESCAPED_UNICODE) . "\n";
if (!$ord['success']) {
    liberarReservasSesion($session);
    fwrite(STDERR, "FAIL crear orden\n");
    exit(1);
}

$got = obtenerOrdenPorCodigo($conn, $ord['codigo_publico']);
if (!$got || $got['estado'] !== 'pendiente') {
    fwrite(STDERR, "FAIL obtener orden\n");
    exit(1);
}

$up = $conn->prepare("UPDATE ordenes SET estado = 'cancelada' WHERE id_orden = ?");
$idO = (int) $ord['id_orden'];
$up->bind_param('i', $idO);
$up->execute();
$up->close();
liberarReservasSesion($session);

echo "PASS fase2 ordenes\n";
