<?php
// Sincroniza carteles de eventos entre disco (evt_interfaz/imagenes) y la tabla evento_imagenes.
// Uso: php sql/sincronizar_imagenes_eventos.php
// Idempotente. En App Platform se ejecuta al arrancar (bin/iniciar.sh) porque el disco es efímero.

require_once __DIR__ . '/../includes/auth_guard.php';
teatro_require_cli();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/evento_imagen_helper.php';

$conn = getLocalConnection();
if (!$conn) {
    fwrite(STDERR, "[imagenes] sin conexión a la BD\n");
    exit(1);
}

$r = teatro_evt_img_sincronizar($conn);
echo "[imagenes] importadas a BD: {$r['importadas']}, restauradas a disco: {$r['restauradas']}\n";
foreach ($r['errores'] as $e) {
    fwrite(STDERR, "[imagenes] error: $e\n");
}
if ($r['sin_origen']) {
    echo '[imagenes] eventos activos con imagen sin copia en disco ni BD: ' . count($r['sin_origen']) . "\n";
    foreach ($r['sin_origen'] as $ruta) {
        echo "  - $ruta\n";
    }
}
exit($r['errores'] ? 1 : 0);
