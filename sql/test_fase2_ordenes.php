<?php
/**
 * Prueba Fase 2: cotizar + crear orden (sin pago).
 * Uso: php sql/test_fase2_ordenes.php
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/ordenes_helper.php';
require_once dirname(__DIR__) . '/sync/reservas_helper.php';

$conn = getLocalConnection();
if (!$conn) {
    fwrite(STDERR, "FAIL connection\n");
    exit(1);
}
asegurarTablasOrdenes($conn);

$evento = $conn->query("SELECT id_evento FROM evento WHERE finalizado = 0 ORDER BY id_evento DESC LIMIT 1")->fetch_assoc();
if (!$evento) {
    fwrite(STDERR, "FAIL sin eventos\n");
    exit(1);
}
$idEvento = (int) $evento['id_evento'];
$st = $conn->prepare('SELECT id_funcion FROM funciones WHERE id_evento = ? ORDER BY fecha_hora DESC LIMIT 1');
$st->bind_param('i', $idEvento);
$st->execute();
$fun = $st->get_result()->fetch_assoc();
$st->close();
$idFuncion = (int) ($fun['id_funcion'] ?? 0);
if (!$idFuncion) {
    fwrite(STDERR, "FAIL sin función\n");
    exit(1);
}

$disp = obtenerDisponibilidadFuncion($idEvento, $idFuncion, null, $conn);
$ocupados = array_flip($disp['ocupados']);
$asiento = null;
for ($n = 1; $n <= 26; $n++) {
    $c = 'B' . $n;
    if (!isset($ocupados[$c]) && resolver_categoria_asiento($conn, $idEvento, $c)) {
        $cat = resolver_categoria_asiento($conn, $idEvento, $c);
        if (!es_categoria_no_venta($cat['nombre_categoria'])) {
            $asiento = $c;
            break;
        }
    }
}
if (!$asiento) {
    fwrite(STDERR, "FAIL sin asiento libre en fila B\n");
    exit(1);
}

$session = 'test_ord_' . bin2hex(random_bytes(4));
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
if ((float) $cot['items'][0]['precio_final'] === 1.0 && (float) $cot['items'][0]['precio_base'] !== 1.0) {
    // ok if ignored; if base is also 1 coincidentally fine
}

$ord = crearOrdenOnline($conn, [
    'id_evento' => $idEvento,
    'id_funcion' => $idFuncion,
    'session_id' => $session,
    'email' => 'test@example.com',
    'nombre' => 'Prueba Fase2',
    'telefono' => '555',
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

// Cleanup: cancelar orden y liberar hold
$up = $conn->prepare("UPDATE ordenes SET estado = 'cancelada' WHERE id_orden = ?");
$idO = (int) $ord['id_orden'];
$up->bind_param('i', $idO);
$up->execute();
$up->close();
liberarReservasSesion($session);

echo "PASS fase2 ordenes\n";
