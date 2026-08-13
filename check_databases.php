<?php
header('Content-Type: text/plain');
require_once 'evt_interfaz/conexion.php';

echo "=== Verificando Bases de Datos ===\n\n";

// Listar todas las bases de datos
$result = $conn->query("SHOW DATABASES");
echo "Bases de datos disponibles:\n";
while ($row = $result->fetch_array()) {
    if (strpos($row[0], 'trt') !== false || strpos($row[0], 'teatro') !== false) {
        echo "  - " . $row[0] . "\n";
    }
}

echo "\n=== Verificando tablas ===\n";

// Verificar trt_historico_evento
$check1 = $conn->query("SHOW DATABASES LIKE 'trt_historico_evento'");
echo "trt_historico_evento: " . ($check1 && $check1->num_rows > 0 ? "EXISTE" : "NO EXISTE") . "\n";

// Verificar trt_historial_evento
$check2 = $conn->query("SHOW DATABASES LIKE 'trt_historial_evento'");
echo "trt_historial_evento: " . ($check2 && $check2->num_rows > 0 ? "EXISTE" : "NO EXISTE") . "\n";

// Verificar trt_25
$check3 = $conn->query("SHOW DATABASES LIKE 'trt_25'");
echo "trt_25: " . ($check3 && $check3->num_rows > 0 ? "EXISTE" : "NO EXISTE") . "\n";

echo "\n=== Conteo de boletos ===\n";

// Contar boletos en trt_25
$count1 = $conn->query("SELECT COUNT(*) as c FROM trt_25.boletos");
if ($count1) {
    echo "Boletos en trt_25: " . $count1->fetch_assoc()['c'] . "\n";
}

// Intentar contar en historico
$check_hist = $conn->query("SHOW DATABASES LIKE 'trt_historico_evento'");
if ($check_hist && $check_hist->num_rows > 0) {
    $count2 = $conn->query("SELECT COUNT(*) as c FROM trt_historico_evento.boletos");
    if ($count2) {
        echo "Boletos en trt_historico_evento: " . $count2->fetch_assoc()['c'] . "\n";
    }
}

$check_hist2 = $conn->query("SHOW DATABASES LIKE 'trt_historial_evento'");
if ($check_hist2 && $check_hist2->num_rows > 0) {
    $count3 = $conn->query("SELECT COUNT(*) as c FROM trt_historial_evento.boletos");
    if ($count3) {
        echo "Boletos en trt_historial_evento: " . $count3->fetch_assoc()['c'] . "\n";
    }
}

echo "\nFin del diagnóstico.";
?>
