<?php
// Borra datos de prueba de la BD de desarrollo:
//  - Órdenes online que nunca tuvieron un pago con proveedor real (solo 'mock' o sin pago), con sus
//    items, pagos y boletos online. No toca boletos ligados a una orden con pago real.
//  - Eventos "[PRUEBA] …" (sql/crear_evento_prueba.php) con todo lo que cuelga de ellos, también en
//    las BD histórica y de respaldo, incluidas ventas de taquilla hechas sobre ese evento.
// Nunca toca ventas de taquilla de eventos reales.
//
// Uso: php sql/limpiar_datos_prueba.php            (solo muestra qué se borraría)
//      php sql/limpiar_datos_prueba.php --aplicar  (respalda en JSON y borra)
// Se niega a correr con APP_ENV=production.

require_once __DIR__ . '/../includes/auth_guard.php';
teatro_require_cli();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/qr_helper.php';

const HIST = 'trt_historico_evento';

if (teatro_es_produccion()) {
    fwrite(STDERR, "No se ejecuta en producción.\n");
    exit(1);
}

$aplicar = in_array('--aplicar', $argv, true);
$conn = getLocalConnection();
if (!$conn) {
    fwrite(STDERR, "Sin conexión a la BD.\n");
    exit(1);
}
$backupDb = (string) teatro_env('DB_BACKUP_NAME', 'trt_25_backup');
$ids = fn(array $rows, string $col) => implode(',', array_map(fn($r) => (int) $r[$col], $rows)) ?: '0';
$fetch = fn(string $sql) => $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
$existe = function (string $db, string $tabla) use ($conn): bool {
    $st = $conn->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $st->bind_param('ss', $db, $tabla);
    $st->execute();
    return (bool) $st->get_result()->fetch_row();
};

// Eventos de prueba (activos o ya archivados).
$eventos = $fetch("SELECT id_evento, titulo FROM evento WHERE titulo LIKE '[PRUEBA]%'");
$eventosHist = $existe(HIST, 'evento') ? $fetch('SELECT id_evento, titulo FROM ' . HIST . ".evento WHERE titulo LIKE '[PRUEBA]%'") : [];
$idsEvt = $ids(array_merge($eventos, $eventosHist), 'id_evento');

$hayOnline = $existe(DB_LOCAL_NAME, 'ordenes') && $existe(DB_LOCAL_NAME, 'orden_items') && $existe(DB_LOCAL_NAME, 'pagos');
$esPrueba = "(NOT EXISTS (SELECT 1 FROM pagos px WHERE px.id_orden = %1\$s.id_orden AND px.proveedor <> 'mock') OR %1\$s.id_evento IN ($idsEvt))";
$ordenes = $hayOnline ? $fetch('SELECT * FROM ordenes o WHERE ' . sprintf($esPrueba, 'o')) : [];
$idsOrden = $ids($ordenes, 'id_orden');
$items = $ordenes ? $fetch("SELECT * FROM orden_items WHERE id_orden IN ($idsOrden)") : [];
$pagos = $ordenes ? $fetch("SELECT * FROM pagos WHERE id_orden IN ($idsOrden)") : [];

$boletosOnline = !$ordenes ? '' : "
       OR (b.origen = 'online'
           AND b.id_boleto IN (SELECT id_boleto FROM orden_items WHERE id_orden IN ($idsOrden) AND id_boleto IS NOT NULL)
           AND NOT EXISTS (
               SELECT 1 FROM orden_items oi2 JOIN ordenes o2 ON o2.id_orden = oi2.id_orden
               WHERE oi2.id_boleto = b.id_boleto AND NOT " . sprintf($esPrueba, 'o2') . "
           ))";
$boletos = $fetch("SELECT b.* FROM boletos b WHERE b.id_evento IN ($idsEvt) $boletosOnline");

// Filas por id_evento del evento de prueba en las 3 BD (sin boletos/ordenes, ya calculados).
$porEvento = [];
$tablasEvento = [
    DB_LOCAL_NAME => ['reservas_temporales', 'cambios_log', 'precios_tipo_boleto', 'promociones', 'categorias', 'funciones', 'evento'],
    HIST => ['boletos', 'precios_tipo_boleto', 'promociones', 'categorias', 'funciones', 'evento'],
];
if ($idsEvt !== '0') {
    foreach ($tablasEvento as $db => $tablas) {
        foreach ($tablas as $t) {
            if ($existe($db, $t)) {
                $porEvento["$db.$t"] = ['where' => "id_evento IN ($idsEvt)", 'rows' => $fetch("SELECT * FROM `$db`.`$t` WHERE id_evento IN ($idsEvt)")];
            }
        }
    }
    foreach (['venta_detallada' => ['id_evento', 'titulo_evento'], 'boleto_backup' => ['id_evento_origen', 'titulo_evento'], 'evento_backup' => ['id_evento_origen', 'titulo']] as $t => [$col, $tit]) {
        if ($existe($backupDb, $t)) {
            $w = "$col IN ($idsEvt) AND $tit LIKE '[PRUEBA]%'";
            $porEvento["$backupDb.$t"] = ['where' => $w, 'rows' => $fetch("SELECT * FROM `$backupDb`.`$t` WHERE $w")];
        }
    }
}

echo 'Eventos [PRUEBA]: ' . count($eventos) . ' activos, ' . count($eventosHist) . " archivados\n";
echo 'Órdenes de prueba: ' . count($ordenes) . ' | Items: ' . count($items) . ' | Pagos: ' . count($pagos) . ' | Boletos: ' . count($boletos) . "\n";
foreach ($porEvento as $t => $d) {
    if ($d['rows']) {
        echo "  $t: " . count($d['rows']) . "\n";
    }
}
$total = count($eventos) + count($eventosHist) + count($ordenes) + count($boletos);
if ($total === 0) {
    echo "No hay datos de prueba.\n";
    exit(0);
}
if (!$aplicar) {
    echo "\nSin cambios. Para borrar: php sql/limpiar_datos_prueba.php --aplicar\n";
    exit(0);
}

$respaldo = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'teatro_limpieza_prueba_' . date('Ymd_His') . '.json';
$json = json_encode(['ordenes' => $ordenes, 'items' => $items, 'pagos' => $pagos, 'boletos' => $boletos, 'por_evento' => $porEvento], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
if ($json === false || file_put_contents($respaldo, $json) === false) {
    fwrite(STDERR, "No se pudo escribir el respaldo; no se borró nada.\n");
    exit(1);
}
echo "\nRespaldo: $respaldo\n";

$idsBoleto = $ids($boletos, 'id_boleto');
$conn->begin_transaction();
try {
    if ($ordenes) {
        $conn->query("DELETE FROM pagos WHERE id_orden IN ($idsOrden)");
        $conn->query("DELETE FROM orden_items WHERE id_orden IN ($idsOrden)");
        $conn->query("DELETE FROM ordenes WHERE id_orden IN ($idsOrden)");
    }
    // Hijos antes que padres (boletos → categorías/funciones/promociones → evento).
    $conn->query("DELETE FROM boletos WHERE id_boleto IN ($idsBoleto)");
    foreach ($porEvento as $t => $d) {
        [$db, $tabla] = explode('.', $t, 2);
        $conn->query("DELETE FROM `$db`.`$tabla` WHERE {$d['where']}");
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, 'Error, no se borró nada: ' . $e->getMessage() . "\n");
    exit(1);
}

$qr = 0;
$codigos = array_column($boletos, 'codigo_unico');
foreach ($porEvento[HIST . '.boletos']['rows'] ?? [] as $b) {
    $codigos[] = $b['codigo_unico'];
}
foreach ($codigos as $cod) {
    $path = teatro_qr_dir() . $cod . '.png';
    if (teatro_qr_codigo_valido((string) $cod) && is_file($path) && @unlink($path)) {
        $qr++;
    }
}
echo "Borrado. QR eliminados: $qr\n";
