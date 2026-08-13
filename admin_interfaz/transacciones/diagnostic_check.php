<?php
require_once '../../evt_interfaz/conexion.php';

header('Content-Type: text/plain');

echo "Diagnostic Check\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Check trt_25 connection
echo "Connected to database: " . $conn->host_info . "\n";
echo "Selected DB: " . $database . "\n\n";

// 2. Check boletos table in trt_25
$sql = "SELECT COUNT(*) as total FROM boletos";
$res = $conn->query($sql);
if ($res) {
    $row = $res->fetch_assoc();
    echo "Total rows in 'boletos' (active DB): " . $row['total'] . "\n";
} else {
    echo "Error querying boletos: " . $conn->error . "\n";
}

// 3. Check boletos with status 1
$sql = "SELECT COUNT(*) as active FROM boletos WHERE estatus = 1";
$res = $conn->query($sql);
if ($res) {
    $row = $res->fetch_assoc();
    echo "Active rows (estatus=1) in 'boletos': " . $row['active'] . "\n";
}

// 4. Check historical DB
$res = $conn->query("SHOW DATABASES LIKE 'trt_historico_evento'");
if ($res->num_rows > 0) {
    echo "Historical DB 'trt_historico_evento' EXISTS.\n";
    $res2 = $conn->query("SELECT COUNT(*) as c FROM trt_historico_evento.boletos");
    if ($res2) {
        echo "Rows in historical boletos: " . $res2->fetch_assoc()['c'] . "\n";
    } else {
        echo "Error querying historical boletos: " . $conn->error . "\n";
    }
} else {
    echo "Historical DB 'trt_historico_evento' NOT FOUND.\n";
}

// 5. Check API logic simulation
$queries_resumen = [];
$db = 'trt_25';
$where = "b.estatus = 1";
$sql_resumen = "SELECT id_boleto, precio_final, fecha_compra, id_evento, id_funcion FROM {$db}.boletos b WHERE {$where}";
echo "\nTest Query: $sql_resumen\n";
$res = $conn->query($sql_resumen);
if ($res) {
    echo "Rows returned: " . $res->num_rows . "\n";
} else {
    echo "Query Error: " . $conn->error . "\n";
}
?>
