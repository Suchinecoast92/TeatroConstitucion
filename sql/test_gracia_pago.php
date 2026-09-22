<?php
/**
 * Simula clic en Pagar con hold recién vencido (carrera timer 1–2 s).
 * Uso: php sql/test_gracia_pago.php
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/sync/reservas_helper.php';
require_once dirname(__DIR__) . '/includes/ordenes_helper.php';
require_once __DIR__ . '/test_helpers.php';

$conn = getLocalConnection();
if (!$conn) {
    fwrite(STDERR, "FAIL connection\n");
    exit(1);
}

$ctx = test_contexto_orden($conn, 'E', 'test_gracia_');
$idEvento = $ctx['id_evento'];
$idFuncion = $ctx['id_funcion'];
$asiento = $ctx['asiento'];
$session = $ctx['session'];
$cliente = $ctx['cliente'];

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
    'email' => $cliente['email'],
    'nombre' => $cliente['nombre'],
    'telefono' => $cliente['telefono'],
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
