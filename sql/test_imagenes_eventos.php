<?php
// Prueba de carteles en BD (disco efímero). Uso: php sql/test_imagenes_eventos.php [http://localhost/TeatroConstitucion]
// Mueve temporalmente archivos de evt_interfaz/imagenes y siempre los devuelve al terminar.

require_once __DIR__ . '/../includes/auth_guard.php';
teatro_require_cli();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/evento_imagen_helper.php';

$base = rtrim($argv[1] ?? 'http://localhost/TeatroConstitucion', '/');
$conn = getLocalConnection();
$fallos = 0;
$check = function (string $nombre, bool $ok) use (&$fallos) {
    echo ($ok ? '  OK  ' : '  FALLA ') . $nombre . "\n";
    if (!$ok) {
        $fallos++;
    }
};

$rutas = [];
$q = $conn->query("SELECT DISTINCT e.imagen FROM evento e JOIN evento_imagenes i ON i.ruta = e.imagen LIMIT 2");
while ($r = $q->fetch_row()) {
    $rutas[] = $r[0];
}
if (count($rutas) < 2) {
    fwrite(STDERR, "Se necesitan 2 eventos con imagen en BD (correr sql/sincronizar_imagenes_eventos.php)\n");
    exit(1);
}

$dir = teatro_evt_img_base_dir();
$respaldo = sys_get_temp_dir() . '/teatro_img_test_' . bin2hex(random_bytes(3));
mkdir($respaldo);
$originales = [];
foreach ($rutas as $ruta) {
    $originales[$ruta] = hash_file('sha256', $dir . $ruta);
    copy($dir . $ruta, $respaldo . '/' . basename($ruta));
}

try {
    echo "Validación de rutas\n";
    $check('ruta normal', teatro_evt_img_ruta_valida('imagenes/evt_1_ab.png'));
    $check('rechaza ../', !teatro_evt_img_ruta_valida('imagenes/../conexion.php'));
    $check('rechaza otra carpeta', !teatro_evt_img_ruta_valida('../includes/x.png'));
    $check('rechaza extensión php', !teatro_evt_img_ruta_valida('imagenes/x.php'));
    $check('nombre nuevo válido y único', teatro_evt_img_ruta_valida(teatro_evt_img_ruta_nueva('jpg'))
        && teatro_evt_img_ruta_nueva('jpg') !== teatro_evt_img_ruta_nueva('jpg'));

    echo "Petición HTTP de cartel ausente en disco\n";
    [$r1, $r2] = $rutas;
    unlink($dir . $r1);
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 15]]);
    $body = @file_get_contents($base . '/evt_interfaz/' . $r1, false, $ctx);
    $status = isset($http_response_header[0]) ? $http_response_header[0] : '';
    $check("200 servido desde BD ($status)", strpos($status, ' 200') !== false);
    $check('bytes idénticos al original', $body !== false && hash('sha256', $body) === $originales[$r1]);
    $check('archivo restaurado en disco', is_file($dir . $r1) && hash_file('sha256', $dir . $r1) === $originales[$r1]);

    $body = @file_get_contents($base . '/evt_interfaz/imagenes/evt_no_existe_zz.png', false, $ctx);
    $status = $http_response_header[0] ?? '';
    $check("inexistente → 404 ($status)", strpos($status, ' 404') !== false);

    echo "Sincronización al arrancar\n";
    unlink($dir . $r1);
    unlink($dir . $r2);
    $res = teatro_evt_img_sincronizar($conn);
    $check('restauró 2', $res['restauradas'] === 2 && !$res['errores']);
    foreach ($rutas as $ruta) {
        $check("idéntico: $ruta", is_file($dir . $ruta) && hash_file('sha256', $dir . $ruta) === $originales[$ruta]);
    }

    echo "Borrado solo de huérfanas\n";
    teatro_evt_img_eliminar_si_huerfana($conn, $r1);
    $check('en uso: se conserva en disco y BD', is_file($dir . $r1) && teatro_evt_img_obtener($conn, $r1) !== null);

    $tmp = teatro_evt_img_ruta_nueva('png');
    copy($dir . $r1, $dir . $tmp);
    $check('guardar nueva', teatro_evt_img_guardar($conn, $tmp) && teatro_evt_img_obtener($conn, $tmp) !== null);
    teatro_evt_img_eliminar_si_huerfana($conn, $tmp);
    $check('huérfana: se borra de disco y BD', !is_file($dir . $tmp) && teatro_evt_img_obtener($conn, $tmp) === null);
} finally {
    foreach ($rutas as $ruta) {
        if (!is_file($dir . $ruta) || hash_file('sha256', $dir . $ruta) !== $originales[$ruta]) {
            copy($respaldo . '/' . basename($ruta), $dir . $ruta);
        }
        @unlink($respaldo . '/' . basename($ruta));
    }
    @rmdir($respaldo);
}

echo $fallos === 0 ? "TODO OK\n" : "$fallos FALLA(S)\n";
exit($fallos === 0 ? 0 : 1);
