<?php
require_once 'evt_interfaz/conexion.php';

function describeTable($conn, $table) {
    echo "DESCRIBE $table:\n";
    $result = $conn->query("DESCRIBE $table");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo $row['Field'] . " - " . $row['Type'] . "\n";
        }
    } else {
        echo "Error: " . $conn->error . "\n";
    }
    echo "\n";
}

describeTable($conn, 'evento');
describeTable($conn, 'funciones');
?>
