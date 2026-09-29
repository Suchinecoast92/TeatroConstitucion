<?php
// Reintenta correos de confirmación que no salieron (SMTP caído, proceso interrumpido…).
// Uso: php sql/enviar_correos_pendientes.php              pagadas de las últimas 48 h
//      php sql/enviar_correos_pendientes.php --horas=72
//      php sql/enviar_correos_pendientes.php --orden=123  reenvía esa orden aunque ya se haya enviado
// Respeta MAIL_MODE (mock guarda .eml; smtp envía de verdad; off no hace nada).

require_once __DIR__ . '/../includes/auth_guard.php';
teatro_require_cli();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/correo/CorreoService.php';

$opts = getopt('', ['horas::', 'orden::']);
$conn = getLocalConnection();
if (!$conn) {
    fwrite(STDERR, "[correo] sin conexión a la BD\n");
    exit(1);
}

echo '[correo] modo: ' . correo_modo() . "\n";

if (!empty($opts['orden'])) {
    $r = correo_enviar_confirmacion_orden($conn, (int) $opts['orden'], true);
    echo '[correo] orden ' . (int) $opts['orden'] . ': ' . $r['estado']
        . (isset($r['error']) ? ' — ' . $r['error'] : '')
        . (isset($r['archivo']) ? ' — ' . $r['archivo'] : '') . "\n";
    exit($r['ok'] ? 0 : 1);
}

$horas = isset($opts['horas']) ? max(1, (int) $opts['horas']) : 48;
$r = correo_enviar_pendientes($conn, $horas);
echo "[correo] revisadas: {$r['revisadas']}, enviadas: {$r['enviadas']}, fallidas: {$r['fallidas']}\n";
foreach ($r['detalle'] as $d) {
    echo "  orden {$d['id_orden']}: {$d['estado']}" . (isset($d['error']) ? " — {$d['error']}" : '') . "\n";
}
exit($r['fallidas'] > 0 ? 1 : 0);
