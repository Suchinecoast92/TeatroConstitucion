<?php
/**
 * Prueba rápida Fase 1: hold online bloquea taquilla (y viceversa).
 * Uso: php sql/test_fase1_holds.php
 * No deja holds permanentes: limpia al final.
 */
require_once dirname(__DIR__) . '/sync/reservas_helper.php';

$conn = getReservasConnection();
if (!$conn) {
    fwrite(STDERR, "FAIL: sin conexión\n");
    exit(1);
}

$evento = $conn->query("SELECT id_evento FROM evento WHERE finalizado = 0 ORDER BY id_evento DESC LIMIT 1");
$rowE = $evento ? $evento->fetch_assoc() : null;
if (!$rowE) {
    fwrite(STDERR, "FAIL: no hay eventos activos para prueba\n");
    exit(1);
}
$idEvento = (int) $rowE['id_evento'];

$fun = $conn->prepare("SELECT id_funcion FROM funciones WHERE id_evento = ? ORDER BY fecha_hora DESC LIMIT 1");
$fun->bind_param('i', $idEvento);
$fun->execute();
$rowF = $fun->get_result()->fetch_assoc();
$fun->close();
$idFuncion = $rowF ? (int) $rowF['id_funcion'] : null;

// Elegir un asiento no vendido
$disp = obtenerDisponibilidadFuncion($idEvento, $idFuncion);
$ocupados = array_flip($disp['ocupados']);
$candidato = null;
for ($n = 1; $n <= 20; $n++) {
    $codigo = 'A' . $n;
    if (!isset($ocupados[$codigo])) {
        $candidato = $codigo;
        break;
    }
}
if (!$candidato) {
    fwrite(STDERR, "FAIL: no hay asiento libre A1-A20 para prueba\n");
    exit(1);
}

$sessionOnline = 'test_online_' . bin2hex(random_bytes(4));
$sessionLocal = 'test_local_' . bin2hex(random_bytes(4));

echo "Evento=$idEvento funcion=" . ($idFuncion ?: 0) . " asiento=$candidato\n";

$r1 = reservarAsientos($idEvento, $idFuncion, [$candidato], $sessionOnline, 'online', 'test-online');
echo 'online reservar: ' . json_encode($r1, JSON_UNESCAPED_UNICODE) . "\n";
if (!$r1['success']) {
    fwrite(STDERR, "FAIL: online no pudo reservar\n");
    exit(1);
}

$r2 = reservarAsientos($idEvento, $idFuncion, [$candidato], $sessionLocal, 'local', 'test-taquilla');
echo 'local intenta robar: ' . json_encode($r2, JSON_UNESCAPED_UNICODE) . "\n";
if ($r2['success'] || (($r2['conflictos'][$candidato] ?? '') !== 'online')) {
    liberarReservasSesion($sessionOnline);
    liberarReservasSesion($sessionLocal);
    fwrite(STDERR, "FAIL: taquilla no debió poder tomar hold online\n");
    exit(1);
}

try {
    $conn->begin_transaction();
    verificarVentaAtomica($conn, $idEvento, $idFuncion, [$candidato], $sessionLocal, 'local');
    $conn->rollback();
    liberarReservasSesion($sessionOnline);
    fwrite(STDERR, "FAIL: verificarVentaAtomica debió fallar\n");
    exit(1);
} catch (Throwable $e) {
    $conn->rollback();
    echo 'venta bloqueada OK: ' . $e->getMessage() . "\n";
}

$disp2 = obtenerDisponibilidadFuncion($idEvento, $idFuncion, $sessionLocal);
if (!in_array($candidato, $disp2['reservados'], true)) {
    liberarReservasSesion($sessionOnline);
    fwrite(STDERR, "FAIL: disponibilidad no lista el hold\n");
    exit(1);
}
echo "disponibilidad incluye hold OK\n";

liberarReservasSesion($sessionOnline);
liberarReservasSesion($sessionLocal);
echo "PASS fase1 holds\n";
