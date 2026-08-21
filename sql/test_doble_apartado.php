<?php
/**
 * Confirma que dos sesiones no pueden apartar el mismo asiento a la vez.
 *
 * Casos:
 *  1) Usuario A online aparta asiento X → Usuario B online NO puede tomarlo
 *  2) Varios asientos: B no puede tomar ninguno de los de A
 *  3) Tras liberar A, B sí puede apartarlos
 *  4) Taquilla tampoco puede quitar el hold online
 *
 * Uso: php sql/test_doble_apartado.php
 * No deja holds permanentes: limpia al final.
 */
require_once dirname(__DIR__) . '/sync/reservas_helper.php';

function fail(string $msg, array $sessions = []): void
{
    foreach ($sessions as $s) {
        if ($s !== '') {
            liberarReservasSesion($s);
        }
    }
    fwrite(STDERR, "FAIL: $msg\n");
    exit(1);
}

function ok(string $msg): void
{
    echo "  OK  $msg\n";
}

$conn = getReservasConnection();
if (!$conn) {
    fail('sin conexión a la BD');
}

$evento = $conn->query('SELECT id_evento, titulo FROM evento WHERE finalizado = 0 ORDER BY id_evento DESC LIMIT 1');
$rowE = $evento ? $evento->fetch_assoc() : null;
if (!$rowE) {
    fail('no hay eventos activos');
}
$idEvento = (int) $rowE['id_evento'];
$titulo = (string) $rowE['titulo'];

$fun = $conn->prepare('SELECT id_funcion FROM funciones WHERE id_evento = ? ORDER BY fecha_hora DESC LIMIT 1');
$fun->bind_param('i', $idEvento);
$fun->execute();
$rowF = $fun->get_result()->fetch_assoc();
$fun->close();
$idFuncion = $rowF ? (int) $rowF['id_funcion'] : null;

$disp = obtenerDisponibilidadFuncion($idEvento, $idFuncion);
$ocupados = array_flip($disp['ocupados']);

$libres = [];
foreach (['A', 'B', 'C'] as $fila) {
    for ($n = 1; $n <= 26; $n++) {
        $codigo = $fila . $n;
        if (!isset($ocupados[$codigo])) {
            $libres[] = $codigo;
            if (count($libres) >= 3) {
                break 2;
            }
        }
    }
}
if (count($libres) < 3) {
    fail('se necesitan al menos 3 asientos libres (A/B/C) para la prueba');
}

[$asiento1, $asiento2, $asiento3] = $libres;
$sessionA = 'test_user_a_' . bin2hex(random_bytes(4));
$sessionB = 'test_user_b_' . bin2hex(random_bytes(4));
$sessionLocal = 'test_taquilla_' . bin2hex(random_bytes(4));
$sessions = [$sessionA, $sessionB, $sessionLocal];

echo "=== Test doble apartado (concurrencia) ===\n";
echo "Evento: $titulo (#$idEvento)  función: " . ($idFuncion ?: 0) . "\n";
echo "Asientos: $asiento1, $asiento2, $asiento3\n";
echo "Sesión A: $sessionA\n";
echo "Sesión B: $sessionB\n\n";

// --- 1) A aparta un asiento; B no puede ---
echo "1) Dos usuarios online, mismo asiento\n";
$rA = reservarAsientos($idEvento, $idFuncion, [$asiento1], $sessionA, 'online', 'user-a');
if (!$rA['success'] || !in_array($asiento1, $rA['reservados'], true)) {
    fail("Usuario A no pudo apartar $asiento1: " . json_encode($rA, JSON_UNESCAPED_UNICODE), $sessions);
}
ok("Usuario A apartó $asiento1");

$rB = reservarAsientos($idEvento, $idFuncion, [$asiento1], $sessionB, 'online', 'user-b');
if ($rB['success']) {
    fail("Usuario B NO debió poder apartar $asiento1 (ya de A)", $sessions);
}
$conflicto = $rB['conflictos'][$asiento1] ?? '';
if ($conflicto !== 'online') {
    fail("Conflicto esperado 'online', recibido '" . $conflicto . "'", $sessions);
}
ok("Usuario B rechazado en $asiento1 (conflicto=online)");

// --- 2) A aparta varios; B intenta los mismos ---
echo "\n2) Varios asientos a la vez\n";
$rA2 = reservarAsientos($idEvento, $idFuncion, [$asiento2, $asiento3], $sessionA, 'online', 'user-a');
if (!$rA2['success'] || count($rA2['reservados']) < 2) {
    fail('Usuario A no pudo apartar el bloque de 2 asientos: ' . json_encode($rA2, JSON_UNESCAPED_UNICODE), $sessions);
}
ok("Usuario A apartó $asiento2 y $asiento3");

$rB2 = reservarAsientos($idEvento, $idFuncion, [$asiento2, $asiento3], $sessionB, 'online', 'user-b');
if ($rB2['success']) {
    fail('Usuario B no debió tomar el bloque de A', $sessions);
}
foreach ([$asiento2, $asiento3] as $c) {
    if (($rB2['conflictos'][$c] ?? '') !== 'online') {
        fail("B debió ver conflicto online en $c", $sessions);
    }
}
ok('Usuario B rechazado en ambos asientos del bloque');

// Disponibilidad: B debe verlos como reservados (ajenos)
$dispB = obtenerDisponibilidadFuncion($idEvento, $idFuncion, $sessionB);
foreach ([$asiento1, $asiento2, $asiento3] as $c) {
    if (!in_array($c, $dispB['reservados'], true)) {
        fail("Disponibilidad para B no marca $c como reservado", $sessions);
    }
}
ok('Disponibilidad unificada marca los 3 asientos como apartados para B');

// --- 3) Taquilla tampoco puede ---
echo "\n3) Taquilla vs hold online\n";
$rLocal = reservarAsientos($idEvento, $idFuncion, [$asiento1], $sessionLocal, 'local', 'taquilla');
if ($rLocal['success'] || (($rLocal['conflictos'][$asiento1] ?? '') !== 'online')) {
    fail('Taquilla no debió poder tomar hold online', $sessions);
}
ok('Taquilla rechazada (conflicto=online)');

// --- 4) Tras liberar A, B sí puede ---
echo "\n4) Liberación y reintento\n";
$liberados = liberarReservasSesion($sessionA);
if ($liberados < 1) {
    fail('No se liberaron holds de A', $sessions);
}
ok("Sesión A liberó $liberados hold(s)");

$rB3 = reservarAsientos($idEvento, $idFuncion, [$asiento1, $asiento2], $sessionB, 'online', 'user-b');
if (!$rB3['success']) {
    fail('Tras liberar A, B debió poder apartar: ' . json_encode($rB3, JSON_UNESCAPED_UNICODE), $sessions);
}
ok("Usuario B apartó $asiento1 y $asiento2 tras la liberación");

// Limpieza final
liberarReservasSesion($sessionA);
liberarReservasSesion($sessionB);
liberarReservasSesion($sessionLocal);

echo "\nPASS test_doble_apartado\n";
