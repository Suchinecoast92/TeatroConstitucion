<?php
require_once '../../evt_interfaz/conexion.php';

header('Content-Type: text/plain');

echo "Debug Statistics\n";
echo "================\n";

// Check Databases
echo "Checking 'trt_25' (Actual):\n";
$res = $conn->query("SHOW TABLES FROM trt_25 LIKE 'boletos'");
if ($res && $res->num_rows > 0) {
    echo " - Table 'boletos' exists.\n";
    $count = $conn->query("SELECT COUNT(*) as c FROM trt_25.boletos")->fetch_assoc()['c'];
    echo " - Total rows in 'boletos': $count\n";
    
    $active = $conn->query("SELECT COUNT(*) as c FROM trt_25.boletos WHERE estatus = 1")->fetch_assoc()['c'];
    echo " - Active rows (estatus=1): $active\n";
} else {
    echo " - Table 'boletos' DOES NOT exist!\n";
}

echo "\nChecking 'trt_historico_evento' (Historico):\n";
$res = $conn->query("SHOW TABLES FROM trt_historico_evento LIKE 'boletos'");
if ($res && $res->num_rows > 0) {
    echo " - Table 'boletos' exists.\n";
    $count = $conn->query("SELECT COUNT(*) as c FROM trt_historico_evento.boletos")->fetch_assoc()['c'];
    echo " - Total rows in 'boletos': $count\n";
} else {
    echo " - Table 'boletos' DOES NOT exist!\n";
}

echo "\nLatest 5 entries in trt_25.boletos:\n";
$res = $conn->query("SELECT id_boleto, estatus, precio_final, fecha_compra FROM trt_25.boletos ORDER BY id_boleto DESC LIMIT 5");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
}
?>
