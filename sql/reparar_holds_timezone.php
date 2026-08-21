<?php
/**
 * Libera holds atascados por el bug de zona horaria (PHP UTC vs MySQL local).
 * Uso: php sql/reparar_holds_timezone.php
 */
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../sync/reservas_helper.php';

$c = getLocalConnection();
if (!$c) {
    fwrite(STDERR, "FAIL sin conexión\n");
    exit(1);
}

echo "NOW MySQL: ";
echo $c->query('SELECT NOW() n')->fetch_assoc()['n'] . "\n";
echo "PHP: " . date('Y-m-d H:i:s') . ' (' . date_default_timezone_get() . ")\n\n";

// Holds con expira > 40 min en el futuro no pueden ser TTL válidos (máx API 30 min)
$r = $c->query("
    SELECT codigo_asiento, session_id, expira_en,
           TIMESTAMPDIFF(MINUTE, NOW(), expira_en) AS mins
    FROM reservas_temporales
    WHERE expira_en > DATE_ADD(NOW(), INTERVAL 40 MINUTE)
");
$n = 0;
while ($row = $r->fetch_assoc()) {
    echo "ATASCADO: " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    $n++;
}

$del = $c->query("
    DELETE FROM reservas_temporales
    WHERE expira_en > DATE_ADD(NOW(), INTERVAL 40 MINUTE)
");
echo "\nEliminados por timezone bug: " . $c->affected_rows . "\n";

$normal = limpiarReservasExpiradas($c);
echo "Eliminados por vencidos normales: $normal\n";

// Verificación rápida del cálculo nuevo
$exp = calcularExpiraReserva($c, 300);
echo "\nNuevo cálculo +300s → db={$exp['db']} iso={$exp['iso']}\n";
$st = $c->prepare('SELECT TIMESTAMPDIFF(SECOND, NOW(), ?) d');
$st->bind_param('s', $exp['db']);
$st->execute();
$d = (int) $st->get_result()->fetch_assoc()['d'];
$st->close();
echo "Diff MySQL vs expira db: {$d}s (debe ~300)\n";
if ($d < 290 || $d > 310) {
    fwrite(STDERR, "FAIL diff fuera de rango\n");
    exit(1);
}
echo "PASS reparar holds + timezone\n";
