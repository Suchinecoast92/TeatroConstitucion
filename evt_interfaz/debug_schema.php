<?php
// debug_schema.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include "../conexion.php";

$db_main = 'trt_25';
$db_hist = 'trt_historico_evento';

echo "=== DIAGNOSTICO DE ESQUEMA ===\n\n";

// 1. Ver estructura de boletos en MAIN
$res = $conn->query("SHOW CREATE TABLE `$db_main`.`boletos`");
if ($row = $res->fetch_assoc()) {
    echo "--- trt_25.boletos ---\n";
    echo $row['Create Table'] . "\n\n";
}

// 2. Ver estructura de tablas referenciadas en HISTORICO
// Asumimos que boletos referencia a evento, funciones, categorias, promociones
$tablas_ref = ['evento', 'funciones', 'categorias', 'promociones'];
foreach ($tablas_ref as $t) {
    if ($t == 'boletos') continue;
    $res = $conn->query("SHOW CREATE TABLE `$db_hist`.`$t`");
    if ($res && $row = $res->fetch_assoc()) {
        echo "--- $db_hist.$t ---\n";
        echo $row['Create Table'] . "\n\n";
    } else {
        echo "--- $db_hist.$t ---\n";
        echo "NO EXISTE O ERROR: " . $conn->error . "\n\n";
    }
}
?>
