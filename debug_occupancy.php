<?php
require_once 'evt_interfaz/conexion.php';
header('Content-Type: text/plain');

echo "DEBUG INFO\n";

$db_actual = 'trt_25';
$qryEstado = "WHERE f.estado = 1"; // Current logic

// 1. Check Funciones in DB
$sql_check = "SELECT id_funcion, id_evento, estado FROM {$db_actual}.funciones";
echo "All Functions in {$db_actual}:\n";
$res = $conn->query($sql_check);
if ($res) {
    while($r = $res->fetch_assoc()) {
        echo "ID: " . $r['id_funcion'] . " | Evento: " . $r['id_evento'] . " | Estado: " . $r['estado'] . "\n";
    }
} else {
    echo "Error querying functions: " . $conn->error . "\n";
}

// 2. Check Capacity Calculation Query
$sql_cap_global = "
    SELECT SUM(CASE WHEN e.tipo = 2 THEN 540 ELSE 420 END) as cap_total
    FROM {$db_actual}.funciones f
    JOIN {$db_actual}.evento e ON f.id_evento = e.id_evento
    {$qryEstado}
";
echo "\nCapacity Query:\n$sql_cap_global\n";
$res_cap = $conn->query($sql_cap_global);
if ($res_cap) {
    $row = $res_cap->fetch_assoc();
    echo "Result Capacity: " . var_export($row, true) . "\n";
} else {
    echo "Error Calc Capacity: " . $conn->error . "\n";
}

// 3. Check Tickets Count
$sql_tickets = "SELECT COUNT(*) as total FROM {$db_actual}.boletos WHERE estatus = 1";
echo "\nTickets Query:\n$sql_tickets\n";
$res_t = $conn->query($sql_tickets);
if ($res_t) {
    $row = $res_t->fetch_assoc();
    echo "Total Sold Tickets: " . $row['total'] . "\n";
}

?>
