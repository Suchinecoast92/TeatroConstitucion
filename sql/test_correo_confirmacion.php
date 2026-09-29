<?php
/**
 * Prueba: correo de confirmación en modo simulado (no se conecta a ningún servidor real).
 * Pago aprobado → .eml con boletos y QR, un solo envío, reenvío forzado, fallo SMTP registrado
 * y reintento, orden no pagada omitida, envío automático al terminar la petición.
 * Uso: php sql/test_correo_confirmacion.php   (limpieza: php sql/limpiar_datos_prueba.php --aplicar)
 */
$dirMock = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'teatro_correos_test_' . bin2hex(random_bytes(3));
putenv('MAIL_MODE=mock');
putenv('MAIL_MOCK_DIR=' . $dirMock);
putenv('MAIL_FROM=boletos@example.com');

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/pagos/PaymentService.php';
require_once dirname(__DIR__) . '/includes/correo/CorreoService.php';
require_once __DIR__ . '/test_helpers.php';

function fail(string $msg): void
{
    fwrite(STDERR, "FAIL: $msg\n");
    exit(1);
}

function ok_line(string $m): void
{
    echo "  OK  $m\n";
}

function emls(string $dir): array
{
    return is_dir($dir) ? (glob($dir . DIRECTORY_SEPARATOR . '*.eml') ?: []) : [];
}

function notif(mysqli $conn, int $idOrden): ?array
{
    $st = $conn->prepare('SELECT * FROM orden_notificaciones WHERE id_orden = ? AND tipo = ?');
    $tipo = CORREO_TIPO_CONFIRMACION;
    $st->bind_param('is', $idOrden, $tipo);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $st->close();
    return $r ?: null;
}

/** Orden online pagada por el webhook simulado. */
function orden_pagada(mysqli $conn, string $prefijo, bool $pagar = true): array
{
    $ctx = test_contexto_orden($conn, 'E', $prefijo);
    $hold = reservarAsientos($ctx['id_evento'], $ctx['id_funcion'], [$ctx['asiento']], $ctx['session'], 'online', 'test-correo');
    if (!$hold['success']) {
        fail('hold: ' . json_encode($hold, JSON_UNESCAPED_UNICODE));
    }
    $ord = crearOrdenOnline($conn, [
        'id_evento' => $ctx['id_evento'],
        'id_funcion' => $ctx['id_funcion'],
        'session_id' => $ctx['session'],
        'email' => $ctx['cliente']['email'],
        'nombre' => $ctx['cliente']['nombre'],
        'telefono' => $ctx['cliente']['telefono'],
        'asientos' => [['asiento' => $ctx['asiento'], 'tipo_boleto' => 'adulto']],
    ]);
    if (!$ord['success']) {
        liberarReservasSesion($ctx['session']);
        fail('orden: ' . json_encode($ord, JSON_UNESCAPED_UNICODE));
    }
    if ($pagar) {
        payment_crear_para_orden($conn, $ord['codigo_publico']);
        $wh = payment_procesar_webhook($conn, ['mock' => '1', 'codigo' => $ord['codigo_publico'], 'result' => 'approved'], []);
        if (!$wh['success'] || ($wh['estado'] ?? '') !== 'PAID') {
            fail('webhook no PAID: ' . json_encode($wh));
        }
    }
    return ['id_orden' => (int) $ord['id_orden'], 'codigo' => $ord['codigo_publico'], 'asiento' => $ctx['asiento'], 'session' => $ctx['session']];
}

$conn = getLocalConnection();
if (!$conn) {
    fail('sin conexión');
}
asegurarTablaPagos($conn);
asegurarTablaNotificaciones($conn);

if (correo_modo() !== 'mock') {
    fail('se esperaba MAIL_MODE=mock');
}

// 1) Envío tras pago
$o1 = orden_pagada($conn, 'test_mail_');
echo "orden={$o1['codigo']} asiento={$o1['asiento']}\n";
$r = correo_enviar_confirmacion_orden($conn, $o1['id_orden']);
if (!$r['ok'] || empty($r['archivo']) || !is_file($r['archivo'])) {
    fail('primer envío: ' . json_encode($r, JSON_UNESCAPED_UNICODE));
}
$eml = (string) file_get_contents($r['archivo']);
$bol = emision_listar_boletos_orden($conn, $o1['id_orden'])[0];
$decod = quoted_printable_decode($eml);
foreach (['To: ' => 'test_mail_user@example.com', 'Content-ID' => 'qr_' . $bol['codigo_unico']] as $etq => $esperado) {
    if (stripos($eml, $esperado) === false) {
        fail("el .eml no contiene $etq$esperado");
    }
}
$html = '';
if (preg_match_all('~Content-Type: text/html; charset=utf-8\r?\nContent-Transfer-Encoding: base64\r?\n\r?\n([A-Za-z0-9+/=\r\n]+)~i', $eml, $m)) {
    $html = base64_decode(preg_replace('/\s+/', '', $m[1][0]));
}
foreach ([$bol['codigo_unico'], 'cid:qr_' . $bol['codigo_unico'], 'orden.php?codigo=' . $o1['codigo'], 'Asiento ' . $o1['asiento']] as $esperado) {
    if (strpos($html, $esperado) === false) {
        fail("el HTML del correo no contiene: $esperado");
    }
}
$n = notif($conn, $o1['id_orden']);
if (!$n || $n['estado'] !== 'enviado' || (int) $n['intentos'] !== 1 || $n['proveedor'] !== 'mock') {
    fail('registro tras envío: ' . json_encode($n));
}
ok_line('correo generado con asiento, código, QR embebido y enlace a la orden');

// 2) Un solo envío
$antes = count(emls($dirMock));
$r2 = correo_enviar_confirmacion_orden($conn, $o1['id_orden']);
$r3 = emitir_boletos_orden_pagada($conn, $o1['id_orden']);
if ($r2['ok'] || count(emls($dirMock)) !== $antes || !$r3['success']) {
    fail('segundo envío no debió salir: ' . json_encode($r2));
}
ok_line('segundo intento no reenvía (ni por reemisión idempotente)');

// 3) Reclamo concurrente: solo uno gana
$conn->query("UPDATE orden_notificaciones SET estado = 'pendiente' WHERE id_orden = {$o1['id_orden']}");
$c2 = getLocalConnection();
$a = correo_reclamar($conn, $o1['id_orden'], CORREO_TIPO_CONFIRMACION, false);
$b = correo_reclamar($c2, $o1['id_orden'], CORREO_TIPO_CONFIRMACION, false);
if (!$a || $b) {
    fail("reclamo concurrente: a=$a b=$b");
}
$conn->query("UPDATE orden_notificaciones SET estado = 'enviado' WHERE id_orden = {$o1['id_orden']}");
ok_line('dos procesos a la vez: solo uno reclama el envío');

// 4) Reenvío forzado (admin)
$r4 = correo_enviar_confirmacion_orden($conn, $o1['id_orden'], true);
if (!$r4['ok'] || count(emls($dirMock)) !== $antes + 1) {
    fail('reenvío forzado: ' . json_encode($r4));
}
ok_line('reenvío manual funciona');

// 5) Fallo SMTP queda registrado sin exponer la contraseña; el reintento lo envía
putenv('MAIL_MODE=smtp');
putenv('MAIL_HOST=127.0.0.1');
putenv('MAIL_PORT=1');
putenv('MAIL_USER=usuario');
putenv('MAIL_PASS=CLAVE_DE_PRUEBA_NO_REAL');
$o2 = orden_pagada($conn, 'test_mail2_');
$r5 = correo_enviar_confirmacion_orden($conn, $o2['id_orden']);
$n2 = notif($conn, $o2['id_orden']);
if ($r5['ok'] || !$n2 || $n2['estado'] !== 'fallido' || (string) $n2['ultimo_error'] === '') {
    fail('fallo SMTP: ' . json_encode([$r5, $n2]));
}
if (str_contains((string) $n2['ultimo_error'], 'CLAVE_DE_PRUEBA_NO_REAL')) {
    fail('la contraseña apareció en el error guardado');
}
putenv('MAIL_MODE=mock');
putenv('MAIL_PASS');
$p = correo_enviar_pendientes($conn, 1);
$n2 = notif($conn, $o2['id_orden']);
if (!$n2 || $n2['estado'] !== 'enviado' || (int) $n2['intentos'] !== 2) {
    fail('reintento de pendientes: ' . json_encode([$p, $n2]));
}
ok_line('fallo SMTP registrado sin contraseña y reenviado por pendientes');

// 6) Orden no pagada: no se envía
$o3 = orden_pagada($conn, 'test_mail3_', false);
$r6 = correo_enviar_confirmacion_orden($conn, $o3['id_orden']);
if ($r6['ok'] || $r6['estado'] !== 'omitido' || notif($conn, $o3['id_orden'])) {
    fail('orden no pagada: ' . json_encode($r6));
}
liberarReservasSesion($o3['session']);
ok_line('orden no pagada no genera correo');

// 7) Envío automático: el webhook solo lo programa; sale al terminar la petición
$o4 = orden_pagada($conn, 'test_mail4_');
if (notif($conn, $o4['id_orden'])) {
    fail('el correo no debió enviarse durante la petición');
}
register_shutdown_function(static function () use ($o4, $dirMock): void {
    $c = getLocalConnection();
    $n = $c ? notif($c, $o4['id_orden']) : null;
    if (!$n || $n['estado'] !== 'enviado') {
        fwrite(STDERR, 'FAIL: envío automático tras el pago: ' . json_encode($n) . "\n");
        exit(1);
    }
    echo "  OK  envío automático al terminar la petición del webhook\n";
    array_map('unlink', emls($dirMock));
    @rmdir($dirMock);
    echo "PASS correo confirmación\n";
});
