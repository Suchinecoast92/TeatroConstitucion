<?php
/**
 * Prueba: la taquilla recalcula precios en el servidor (calcular_cotizacion_taquilla).
 * Ignora precios del navegador, rechaza precios/descuentos alterados, respeta cortesía,
 * precio por tipo y promociones (porcentaje, fijo repartido, mínimo, vigencia).
 * Crea promociones y precios por tipo temporales en el evento [PRUEBA] y los borra al final.
 * Uso: php sql/crear_evento_prueba.php && php sql/test_precios_taquilla.php
 */
putenv('MAIL_MODE=off');

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/precio_helper.php';

function fail(string $msg): void
{
    global $limpiar;
    if (is_callable($limpiar)) {
        $limpiar();
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

$ev = $conn->query("SELECT id_evento, mapa_json FROM evento WHERE titulo LIKE '[PRUEBA]%' ORDER BY id_evento DESC LIMIT 1")->fetch_assoc();
if (!$ev) {
    fail('no existe el evento [PRUEBA]; ejecuta php sql/crear_evento_prueba.php');
}
$idEvento = (int) $ev['id_evento'];
$mapa = json_decode($ev['mapa_json'] ?? '{}', true) ?: [];

$cats = [];
$res = $conn->query("SELECT id_categoria, nombre_categoria, precio FROM categorias WHERE id_evento = $idEvento");
while ($r = $res->fetch_assoc()) {
    $cats[(int) $r['id_categoria']] = $r;
}
$general = [];
$noVenta = null;
foreach ($mapa as $codigo => $idCat) {
    $nombre = $cats[(int) $idCat]['nombre_categoria'] ?? '';
    if ($nombre === 'General' && count($general) < 3) {
        $general[] = (string) $codigo;
    } elseif (es_categoria_no_venta($nombre) && $noVenta === null) {
        $noVenta = (string) $codigo;
    }
}
if (count($general) < 3) {
    fail('el mapa de prueba no tiene 3 asientos General');
}
$idGeneral = (int) $mapa[$general[0]];
$precioGeneral = (float) $cats[$idGeneral]['precio'];
[$a1, $a2, $a3] = $general;

$promos = [];
$limpiar = function () use ($conn, &$promos, $idEvento): void {
    foreach ($promos as $id) {
        $conn->query('DELETE FROM promociones WHERE id_promocion = ' . (int) $id);
    }
    $promos = [];
    $conn->query("DELETE FROM precios_tipo_boleto WHERE id_evento = $idEvento");
};
$limpiar();

function crear_promo(mysqli $conn, array &$promos, int $idEvento, string $modo, float $valor, array $extra = []): int
{
    $nombre = '[PRUEBA] promo ' . bin2hex(random_bytes(3));
    $minimo = (int) ($extra['min_cantidad'] ?? 1);
    $hasta = $extra['fecha_hasta'] ?? null;
    $cond = $extra['condiciones'] ?? null;
    $st = $conn->prepare('INSERT INTO promociones (nombre, precio, id_evento, min_cantidad, modo_calculo, valor, fecha_hasta, condiciones, activo) VALUES (?, 0, ?, ?, ?, ?, ?, ?, 1)');
    $st->bind_param('siisdss', $nombre, $idEvento, $minimo, $modo, $valor, $hasta, $cond);
    $st->execute();
    $id = (int) $conn->insert_id;
    $st->close();
    $promos[] = $id;
    return $id;
}

function item(string $asiento, array $extra = []): array
{
    return array_merge(['asiento' => $asiento, 'tipo_boleto' => 'adulto'], $extra);
}

$cot = fn(array $asientos) => calcular_cotizacion_taquilla($conn, $idEvento, $asientos);

// 1. Adulto: precio de la categoría, sin importar lo que diga precio_final del navegador
$r = $cot([item($a1, ['precio' => $precioGeneral, 'precio_final' => 1, 'descuento_aplicado' => 0])]);
if (!$r['success'] || $r['items'][0]['precio_final'] != $precioGeneral || $r['items'][0]['id_categoria'] !== $idGeneral) {
    fail('adulto: ' . json_encode($r));
}
ok_line("adulto cobra la categoría (\${$precioGeneral}) aunque el navegador mande precio_final=1");

// 2. Precio de categoría alterado o página desactualizada → rechazo
$r = $cot([item($a1, ['precio' => 1])]);
if ($r['success'] || strpos($r['error'], 'Recarga') === false) {
    fail('precio alterado aceptado: ' . json_encode($r));
}
ok_line('precio de categoría alterado se rechaza');

// 3. Descuento inventado sin promoción → rechazo
$r = $cot([item($a1, ['precio' => $precioGeneral, 'descuento_aplicado' => $precioGeneral])]);
if ($r['success']) {
    fail('descuento sin promoción aceptado');
}
ok_line('descuento sin promoción se rechaza');

// 4. Cortesía: final 0, marcada como cortesía (convención histórica base = descuento)
$r = $cot([item($a1, ['tipo_boleto' => 'cortesia', 'precio' => $precioGeneral])]);
$i = $r['items'][0] ?? [];
if (!$r['success'] || $i['precio_final'] != 0 || $i['tipo_boleto'] !== 'cortesia' || $i['descuento_aplicado'] != $precioGeneral) {
    fail('cortesía: ' . json_encode($r));
}
ok_line('cortesía queda en $0 y marcada como cortesía');

// 5. Tipo desconocido y asiento No Venta
$r = $cot([item($a1, ['tipo_boleto' => 'gratis'])]);
if ($r['success']) {
    fail('tipo desconocido aceptado');
}
if ($noVenta !== null) {
    $r = $cot([item($noVenta)]);
    if ($r['success']) {
        fail('asiento No Venta aceptado');
    }
}
ok_line('tipo de boleto desconocido y asiento "No Venta" se rechazan');

// 6. Precio por tipo: en 0 cae a la categoría; con precio propio lo usa
$r = $cot([item($a1, ['tipo_boleto' => 'nino'])]);
if (!$r['success'] || $r['items'][0]['precio_final'] != $precioGeneral) {
    fail('niño sin precio propio: ' . json_encode($r));
}
$conn->query("INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, activo) VALUES ($idEvento, 'nino', 90, 1)");
$r = $cot([item($a1, ['tipo_boleto' => 'nino', 'precio' => $precioGeneral])]);
if (!$r['success'] || $r['items'][0]['precio_final'] != 90 || $r['items'][0]['precio_base'] != 90) {
    fail('niño con precio propio: ' . json_encode($r));
}
$conn->query("DELETE FROM precios_tipo_boleto WHERE id_evento = $idEvento");
ok_line('niño usa su precio ($90) y, si está en 0, el de la categoría');

// 7. Promoción porcentaje 10%
$p10 = crear_promo($conn, $promos, $idEvento, 'porcentaje', 10);
$desc10 = round($precioGeneral * 0.10, 2);
$r = $cot([
    item($a1, ['id_promocion' => $p10, 'precio' => $precioGeneral, 'descuento_aplicado' => $desc10]),
    item($a2, ['id_promocion' => $p10, 'precio' => $precioGeneral, 'descuento_aplicado' => $desc10]),
]);
if (!$r['success'] || $r['items'][0]['descuento_aplicado'] != $desc10 || $r['items'][0]['id_promocion'] !== $p10
    || $r['total'] != round(2 * ($precioGeneral - $desc10), 2)) {
    fail('promo 10%: ' . json_encode($r));
}
ok_line("promo 10% descuenta \${$desc10} por boleto");

// 8. Promoción fija $40 repartida entre 3 boletos (igual que carrito.js)
$p40 = crear_promo($conn, $promos, $idEvento, 'fijo', 40);
$r = $cot([
    item($a1, ['id_promocion' => $p40, 'descuento_aplicado' => 40 / 3]),
    item($a2, ['id_promocion' => $p40, 'descuento_aplicado' => 40 / 3]),
    item($a3, ['id_promocion' => $p40, 'descuento_aplicado' => 40 / 3]),
]);
if (!$r['success'] || $r['items'][0]['descuento_aplicado'] != 13.33) {
    fail('promo fija: ' . json_encode($r));
}
ok_line('promo fija $40 se reparte: $13.33 por boleto en 3 boletos');

// 9. Descuento del navegador mayor al de la promoción → rechazo
$r = $cot([item($a1, ['id_promocion' => $p10, 'descuento_aplicado' => $precioGeneral])]);
if ($r['success']) {
    fail('descuento inflado aceptado');
}
ok_line('descuento mayor al de la promoción se rechaza');

// 10. Reglas de la promoción: mínimo, cortesía, tipo, vigencia
$pMin = crear_promo($conn, $promos, $idEvento, 'porcentaje', 50, ['min_cantidad' => 3]);
if ($cot([item($a1, ['id_promocion' => $pMin]), item($a2, ['id_promocion' => $pMin])])['success']) {
    fail('promo con mínimo 3 aceptada con 2 boletos');
}
if ($cot([item($a1, ['id_promocion' => $p10, 'tipo_boleto' => 'cortesia'])])['success']) {
    fail('promo + cortesía aceptada');
}
$pTipo = crear_promo($conn, $promos, $idEvento, 'porcentaje', 50, ['condiciones' => 'TIPO_BOLETO:nino']);
if ($cot([item($a1, ['id_promocion' => $pTipo])])['success']) {
    fail('promo de niño aplicada a adulto');
}
$pVieja = crear_promo($conn, $promos, $idEvento, 'porcentaje', 50, ['fecha_hasta' => '2020-01-01 00:00:00']);
if ($cot([item($a1, ['id_promocion' => $pVieja])])['success']) {
    fail('promo vencida aceptada');
}
if ($cot([item($a1, ['id_promocion' => $p10]), item($a2, ['id_promocion' => $p40])])['success']) {
    fail('dos promociones distintas aceptadas');
}
ok_line('promo: mínimo, cortesía, tipo requerido, vencida y promos mezcladas se rechazan');

$limpiar();
echo "PASS precios taquilla\n";
