<?php
include 'conexion.php';

$missing_cols = [
    'funciones' => 'estado',
    'boletos' => 'id_funcion'
];

foreach ($missing_cols as $table => $col) {
    echo "Getting definition for $table.$col...\n";
    $res = $conn->query("SHOW CREATE TABLE trt_25.$table");
    $row = $res->fetch_assoc();
    $create_sql = $row['Create Table'];
    
    // Extract column definition
    // Very basic regex to find the column line.
    // Example: `estado` int(11) NOT NULL DEFAULT '0'
    if (preg_match("/`$col` (.*?),/", $create_sql, $matches)) {
        echo "Found def: $matches[1]\n";
        
        // Construct ALTER
        $alter = "ALTER TABLE trt_historico_evento.$table ADD COLUMN `$col` $matches[1]";
        echo "Running: $alter\n";
        if ($conn->query($alter)) {
            echo "Success!\n";
        } else {
            echo "Error: " . $conn->error . "\n";
        }
    } else {
        echo "Could not parse definition.\n";
    }
}
?>
