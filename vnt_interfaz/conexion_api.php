<?php
// Conexión específica para APIs que devuelven JSON
// No genera salida HTML en caso de error

$servername = "localhost";
$username = "root";
$password = "";
$database = "trt_25";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    // No usar die() para evitar salida HTML
    $conn = null;
}

// Configurar charset UTF-8
if ($conn) {
    $conn->set_charset("utf8mb4");
}
?>
