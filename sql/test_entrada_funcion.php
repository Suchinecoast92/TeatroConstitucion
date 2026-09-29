<?php
/**
 * Prueba: la puerta valida la fecha de la función del boleto (includes/entrada_helper.php).
 * Fuera de la ventana pide autorización explícita; dentro entra normal; usado/cancelado nunca.
 * Mueve la fecha de la función del evento [PRUEBA] y la restaura al final.
 * Uso: php sql/crear_evento_prueba.php && php sql/test_entrada_funcion.php
 */
putenv('MAIL_MODE=off');

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/pagos/PaymentService.php';
require_once dirname(__DIR__) . '/includes/entrada_helper.php';
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

function emitir_boleto(mysqli $conn, string $fila): array
{
    $ctx = test_contexto_orden($conn, $fila, 'test_entrada_');
    $hold = reservarAsientos($ctx['id_evento'], $ctx['id_funcion'], [$ctx['asiento']], $ctx['session'], 'online', 'test-entrada');
    if (!$hold['success']) {
        fail('hold: ' . json_encode($hold));
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
        fail('orden: ' . json_encode($ord));
    }
    payment_crear_para_orden($conn, $ord['codigo_publico']);
    payment_procesar_webhook($conn, ['mock' => '1', 'codigo' => $ord['codigo_publico'], 'result' => 'approved'], []);
    $bol = emision_listar_boletos_orden($conn, (int) $ord['id_orden'])[0] ?? null;
    if (!$bol) {
        fail('sin boleto emitido');
    }
    return ['codigo' => $bol['codigo_unico'], 'id_boleto' => (int) $bol['id_boleto'], 'id_funcion' => $ctx['id_funcion']];
}

$b1 = emitir_boleto($conn, 'G');
$b2 = emitir_boleto($conn, 'G');
$b3 = emitir_boleto($conn, 'G');
$idFuncion = $b1['id_funcion'];
$original = $conn->query("SELECT fecha_hora FROM funciones WHERE id_funcion = $idFuncion")->fetch_row()[0];
$restaurar = function () use ($conn, $idFuncion, $original): void {
    $st = $conn->prepare('UPDATE funciones SET fecha_hora = ? WHERE id_funcion = ?');
    $st->bind_param('si', $original, $idFuncion);
    $st->execute();
    $st->close();
};
$estatus = fn(int $id) => (int) $conn->query("SELECT estatus FROM boletos WHERE id_boleto = $id")->fetch_row()[0];

// Función dentro de varios días: aviso y no entra sin autorización
$conn->query("UPDATE funciones SET fecha_hora = NOW() + INTERVAL 3 DAY WHERE id_funcion = $idFuncion");
$v = entrada_obtener_boleto($conn, $b1['codigo']);
if ($v['entrada']['permitida'] || $v['entrada']['motivo'] !== 'funcion_futura') {
    fail('función futura no detectada: ' . json_encode($v['entrada']));
}
$r = entrada_confirmar($conn, $b1['codigo'], false, 1);
if ($r['success'] || empty($r['requiere_confirmacion']) || $estatus($b1['id_boleto']) !== 1) {
    fail('entró sin autorización: ' . json_encode($r));
}
ok_line('función posterior: aviso "' . $v['entrada']['mensaje'] . '" y no entra sin autorizar');

$r = entrada_confirmar($conn, $b1['codigo'], true, 1);
if (!$r['success'] || $estatus($b1['id_boleto']) !== 0) {
    fail('autorización explícita no funcionó: ' . json_encode($r));
}
ok_line('con autorización explícita sí entra');

// Función en 30 minutos: entra normal
$conn->query("UPDATE funciones SET fecha_hora = NOW() + INTERVAL 30 MINUTE WHERE id_funcion = $idFuncion");
$r = entrada_confirmar($conn, $b2['codigo'], false, 1);
if (!$r['success'] || $estatus($b2['id_boleto']) !== 0) {
    fail('función por empezar rechazada: ' . json_encode($r));
}
ok_line('función que empieza en 30 min: entra normal');

// Ya usado: nunca, ni forzando
$r = entrada_confirmar($conn, $b2['codigo'], true, 1);
if ($r['success'] || !empty($r['requiere_confirmacion'])) {
    fail('boleto usado volvió a entrar: ' . json_encode($r));
}
ok_line('boleto ya usado no entra ni forzando');

// Función de hace 6 horas: aviso de función pasada
$conn->query("UPDATE funciones SET fecha_hora = NOW() - INTERVAL 6 HOUR WHERE id_funcion = $idFuncion");
$v = entrada_obtener_boleto($conn, $b3['codigo']);
if ($v['entrada']['permitida'] || $v['entrada']['motivo'] !== 'funcion_pasada') {
    fail('función pasada no detectada: ' . json_encode($v['entrada']));
}
ok_line('función que ya pasó: aviso "' . $v['entrada']['mensaje'] . '"');

// Cancelado: nunca
$conn->query("UPDATE funciones SET fecha_hora = NOW() + INTERVAL 30 MINUTE WHERE id_funcion = $idFuncion");
$conn->query("UPDATE boletos SET estatus = 2 WHERE id_boleto = {$b3['id_boleto']}");
$r = entrada_confirmar($conn, $b3['codigo'], true, 1);
if ($r['success'] || $estatus($b3['id_boleto']) !== 2) {
    fail('boleto cancelado entró: ' . json_encode($r));
}
ok_line('boleto cancelado no entra ni forzando');

// Boleto antiguo sin función: se valida solo el estatus
$conn->query("UPDATE boletos SET estatus = 1, id_funcion = NULL WHERE id_boleto = {$b3['id_boleto']}");
$v = entrada_obtener_boleto($conn, $b3['codigo']);
$conn->query("UPDATE boletos SET estatus = 2, id_funcion = $idFuncion WHERE id_boleto = {$b3['id_boleto']}");
if (!$v['entrada']['permitida'] || $v['entrada']['motivo'] !== 'sin_funcion') {
    fail('boleto sin función bloqueado: ' . json_encode($v['entrada']));
}
ok_line('boleto antiguo sin función asignada: entra (no hay fecha contra qué validar)');

$restaurar();
echo "PASS entrada por función\n";
