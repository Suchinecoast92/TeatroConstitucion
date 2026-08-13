<?php
/**
 * Migra datos desde los dumps en c/ hacia las bases actuales.
 * Uso: php sql/migrar_datos.php
 */
require_once __DIR__ . '/../config/database.php';

$dumpPrincipal = __DIR__ . '/../c/trt_25 (1).sql';
$dumpHistorico = __DIR__ . '/../c/trt_historico_evento.sql';

if (!is_file($dumpPrincipal)) {
    fwrite(STDERR, "No se encontró: $dumpPrincipal\n");
    exit(1);
}

$conn = getLocalConnection();
if (!$conn) {
    fwrite(STDERR, "Error de conexión a MySQL.\n");
    exit(1);
}

function conteos($conn, $db)
{
    $tablas = ['asientos', 'boletos', 'evento', 'funciones', 'categorias', 'usuarios', 'transacciones', 'ventas'];
    $out = [];
    foreach ($tablas as $t) {
        $res = $conn->query("SELECT COUNT(*) c FROM `$db`.`$t`");
        $out[$t] = $res ? (int) $res->fetch_assoc()['c'] : -1;
    }
    return $out;
}

echo "=== Estado ANTES ===\n";
$antes = conteos($conn, DB_LOCAL_NAME);
foreach ($antes as $t => $c) {
    echo "  trt_25.$t: $c\n";
}

$resHist = $conn->query("SHOW DATABASES LIKE 'trt_historico_evento'");
$historicoExiste = $resHist && $resHist->num_rows > 0;
if ($historicoExiste) {
    $histAntes = conteos($conn, 'trt_historico_evento');
    echo "\n  trt_historico_evento:\n";
    foreach ($histAntes as $t => $c) {
        echo "    $t: $c\n";
    }
}

$conn->close();

// Importar dump principal (reemplaza tablas del volcado)
$mysql = 'C:\\wamp64\\bin\\mysql\\mysql8.4.7\\bin\\mysql.exe';
$sql = file_get_contents($dumpPrincipal);
$sql = "SET FOREIGN_KEY_CHECKS=0;\n" . $sql . "\nSET FOREIGN_KEY_CHECKS=1;\n";
$tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'trt25_import_' . uniqid('', true) . '.sql';
file_put_contents($tmpFile, $sql);

$cmd = sprintf(
    '"%s" -u %s %s < "%s"',
    $mysql,
    DB_LOCAL_USER,
    DB_LOCAL_NAME,
    $tmpFile
);

echo "\n=== Importando trt_25 (1).sql ===\n";
passthru($cmd, $codePrincipal);
@unlink($tmpFile);
if ($codePrincipal !== 0) {
    fwrite(STDERR, "Error al importar dump principal (código $codePrincipal).\n");
    exit(1);
}

// Asegurar tablas nuevas que no vienen en el dump antiguo
$conn = getLocalConnection();
$conn->query("CREATE TABLE IF NOT EXISTS reservas_temporales (
    id_reserva INT AUTO_INCREMENT PRIMARY KEY,
    codigo_asiento VARCHAR(20) NOT NULL,
    id_evento INT NOT NULL,
    id_funcion INT NULL,
    origen ENUM('local','online') NOT NULL,
    session_id VARCHAR(100) NOT NULL,
    cliente_info VARCHAR(150) NULL,
    fecha_reserva TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expira_en TIMESTAMP NOT NULL,
    UNIQUE KEY uk_asiento_funcion (codigo_asiento, id_evento, id_funcion),
    INDEX idx_expira (expira_en),
    INDEX idx_session (session_id),
    INDEX idx_evento_funcion (id_evento, id_funcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS conexion_estado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    componente VARCHAR(50) NOT NULL,
    ultimo_check TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    estado ENUM('ok','error','desconocido') DEFAULT 'desconocido',
    mensaje VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uk_componente (componente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Histórico: el dump solo trae estructura (sin INSERT). No lo importamos si ya hay datos.
$histContenido = is_file($dumpHistorico) ? file_get_contents($dumpHistorico) : '';
$histTieneDatos = (bool) preg_match('/^INSERT INTO/m', $histContenido);

echo "\n=== Histórico (trt_historico_evento.sql) ===\n";
if (!$histTieneDatos) {
    echo "  El archivo solo contiene estructura, sin datos INSERT.\n";
    if ($historicoExiste) {
        $cEvt = $conn->query("SELECT COUNT(*) c FROM trt_historico_evento.evento")->fetch_assoc()['c'];
        echo "  Se conserva la base histórica existente ($cEvt eventos).\n";
    } else {
        $conn->query("CREATE DATABASE IF NOT EXISTS trt_historico_evento CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        passthru(sprintf('"%s" -u %s trt_historico_evento < "%s"', $mysql, escapeshellarg(DB_LOCAL_USER), $dumpHistorico), $codeHist);
        echo $codeHist === 0 ? "  Base histórica creada (vacía).\n" : "  Error creando histórico.\n";
    }
} else {
    $conn->query("CREATE DATABASE IF NOT EXISTS trt_historico_evento CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    passthru(sprintf('"%s" -u %s trt_historico_evento < "%s"', $mysql, escapeshellarg(DB_LOCAL_USER), $dumpHistorico), $codeHist);
    echo $codeHist === 0 ? "  Histórico importado.\n" : "  Error importando histórico.\n";
}

echo "\n=== Estado DESPUÉS ===\n";
$despues = conteos($conn, DB_LOCAL_NAME);
foreach ($despues as $t => $c) {
    echo "  trt_25.$t: $c\n";
}

if ($historicoExiste || $conn->query("SHOW DATABASES LIKE 'trt_historico_evento'")->num_rows > 0) {
    $histDespues = conteos($conn, 'trt_historico_evento');
    echo "\n  trt_historico_evento:\n";
    foreach ($histDespues as $t => $c) {
        echo "    $t: $c\n";
    }
}

$usuarios = $conn->query("SELECT id_usuario, nombre, apellido, rol FROM usuarios ORDER BY id_usuario")->fetch_all(MYSQLI_ASSOC);
echo "\nUsuarios migrados (" . count($usuarios) . "):\n";
foreach ($usuarios as $u) {
    echo "  - {$u['nombre']} {$u['apellido']} ({$u['rol']}, id={$u['id_usuario']})\n";
}

$eventos = $conn->query("SELECT id_evento, titulo, finalizado FROM evento ORDER BY id_evento")->fetch_all(MYSQLI_ASSOC);
echo "\nEventos activos (" . count($eventos) . "):\n";
foreach ($eventos as $e) {
    $est = $e['finalizado'] ? 'finalizado' : 'activo';
    echo "  - [{$e['id_evento']}] {$e['titulo']} ($est)\n";
}

$conn->close();
echo "\nMigración completada.\n";
