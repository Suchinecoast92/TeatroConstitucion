<?php
// Aplica las migraciones de estructura sobre una BD importada del teatro, en el orden correcto.
// Idempotente: salta lo que ya está aplicado. Usa la conexión de config/ (.env o variables de
// App Platform), así que en producción se corre desde la consola de la app sin exponer MySQL.
//
// Uso: php sql/aplicar_migraciones.php             aplica y muestra conteos
//      php sql/aplicar_migraciones.php --verificar  solo conteos (comparar dump vs BD importada)

require_once __DIR__ . '/../includes/auth_guard.php';
teatro_require_cli();
require_once __DIR__ . '/../config/database.php';

const HIST_DB = 'trt_historico_evento';

$soloVerificar = in_array('--verificar', $argv, true);

try {
    $main = teatro_db_connect(DB_LOCAL_NAME);
    $hist = teatro_db_connect(HIST_DB);
} catch (Throwable $e) {
    fwrite(STDERR, 'Sin conexión: ' . $e->getMessage() . "\n");
    exit(1);
}
$main->set_charset('utf8mb4');
$hist->set_charset('utf8mb4');

$hayTabla = function (mysqli $c, string $t): bool {
    $st = $c->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $st->bind_param('s', $t);
    $st->execute();
    return (bool) $st->get_result()->fetch_row();
};
$hayColumna = function (mysqli $c, string $t, string $col): bool {
    $st = $c->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->bind_param('ss', $t, $col);
    $st->execute();
    return (bool) $st->get_result()->fetch_row();
};

// Orden obligatorio: origen_boletos usa orden_items.
$pasos = [
    ['migracion_ordenes_online.sql', $main, fn() => $hayTabla($main, 'ordenes') && $hayTabla($main, 'orden_items')],
    ['migracion_pagos.sql', $main, fn() => $hayTabla($main, 'pagos')],
    ['migracion_origen_boletos.sql', $main, fn() => $hayColumna($main, 'boletos', 'origen')],
    ['migracion_evento_imagenes.sql', $main, fn() => $hayTabla($main, 'evento_imagenes')],
    ['migracion_orden_notificaciones.sql', $main, fn() => $hayTabla($main, 'orden_notificaciones')],
    ['migracion_origen_boletos_historico.sql', $hist, fn() => $hayColumna($hist, 'boletos', 'origen')],
];

if (!$soloVerificar) {
    echo "== Migraciones (" . DB_LOCAL_NAME . ', ' . HIST_DB . ") ==\n";
    foreach ($pasos as [$archivo, $conn, $aplicada]) {
        if ($aplicada()) {
            echo "  ya aplicada: $archivo\n";
            continue;
        }
        $sql = file_get_contents(__DIR__ . '/' . $archivo);
        try {
            $conn->multi_query($sql);
            do {
                if ($r = $conn->store_result()) {
                    $r->free();
                }
            } while ($conn->more_results() && $conn->next_result());
        } catch (Throwable $e) {
            fwrite(STDERR, "  ERROR en $archivo: " . $e->getMessage() . "\nSe detiene; lo aplicado antes queda.\n");
            exit(1);
        }
        if (!$aplicada()) {
            fwrite(STDERR, "  ERROR: $archivo no dejó la estructura esperada.\n");
            exit(1);
        }
        echo "  aplicada: $archivo\n";
    }
}

$uno = fn(mysqli $c, string $sql) => $c->query($sql)->fetch_row()[0];
echo "\n== Conteos para comparar con el dump del teatro ==\n";
foreach (['evento', 'funciones', 'categorias', 'boletos', 'usuarios', 'transacciones', 'reservas_temporales'] as $t) {
    echo str_pad("  $t", 24) . $uno($main, "SELECT COUNT(*) FROM `$t`") . "\n";
}
echo '  última venta          ' . ($uno($main, 'SELECT MAX(fecha_compra) FROM boletos') ?? '-') . "\n";
echo '  suma precio_final     ' . $uno($main, 'SELECT COALESCE(SUM(precio_final),0) FROM boletos WHERE estatus = 1') . "\n";
echo '  funciones futuras     ' . $uno($main, 'SELECT COUNT(*) FROM funciones WHERE fecha_hora > NOW()') . "\n";
echo "  histórico: eventos " . $uno($hist, 'SELECT COUNT(*) FROM evento') . ', boletos ' . $uno($hist, 'SELECT COUNT(*) FROM boletos') . "\n";
echo "\n  Boletos por evento:\n";
foreach ($main->query('SELECT e.id_evento, e.titulo, COUNT(b.id_boleto) n FROM evento e LEFT JOIN boletos b ON b.id_evento = e.id_evento GROUP BY e.id_evento, e.titulo ORDER BY e.id_evento')->fetch_all(MYSQLI_ASSOC) as $r) {
    echo str_pad("    #{$r['id_evento']}", 9) . str_pad((string) $r['n'], 7) . mb_substr($r['titulo'], 0, 50) . "\n";
}
