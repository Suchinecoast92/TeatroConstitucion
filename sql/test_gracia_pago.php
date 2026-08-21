<?php
/**
 * Simula clic en Pagar con hold recién vencido (carrera timer 1–2 s).
 * Uso: php sql/test_gracia_pago.php
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/sync/reservas_helper.php';
require_once dirname(__DIR__) . '/includes/ordenes_helper.php';

$conn = getLocalConnection();
if (!$conn) {
    fwrite(STDERR, "FAIL connection\n");
    exit(1);
}

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
    $c = 'E' . $n;
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

$session = 'test_gracia_' . bin2hex(random_bytes(3));
$hold = reservarAsientos($idEvento, $idFuncion, [$asiento], $session, 'online', 'test');
if (!$hold['success']) {
    fwrite(STDERR, 'FAIL hold ' . json_encode($hold) . "\n");
    exit(1);
}

// Hold vencido hace 5 s (como si el timer del navegador ya hubiera llegado a 0)
$esc = $conn->real_escape_string($session);
$conn->query("UPDATE reservas_temporales SET expira_en = DATE_SUB(NOW(), INTERVAL 5 SECOND) WHERE session_id = '{$esc}'");

$n = renovarReservasSesionConGracia($session, $idEvento, $idFuncion, 2700, 45);
echo "renovados_gracia={$n}\n";
if ($n < 1) {
    fwrite(STDERR, "FAIL no rescató hold con gracia\n");
    exit(1);
}

$ord = crearOrdenOnline($conn, [
    'id_evento' => $idEvento,
    'id_funcion' => $idFuncion,
    'session_id' => $session,
    'email' => 'gracia@example.com',
    'nombre' => 'Test Gracia',
    'asientos' => [['asiento' => $asiento, 'tipo_boleto' => 'adulto']],
]);
echo 'crear=' . json_encode($ord, JSON_UNESCAPED_UNICODE) . "\n";
if (empty($ord['success'])) {
    fwrite(STDERR, "FAIL crear orden tras gracia\n");
    exit(1);
}

$idO = (int) $ord['id_orden'];
$conn->query("UPDATE ordenes SET estado = 'cancelada' WHERE id_orden = {$idO}");
liberarReservasSesion($session);
echo "PASS gracia pago 1-2s\n";
