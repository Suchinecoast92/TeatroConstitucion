<?php
include 'conexion.php';

$tables = ['evento', 'funciones', 'categorias', 'promociones', 'boletos'];
$db_principal = 'trt_25';
$db_historico = 'trt_historico_evento';

foreach ($tables as $table) {
    echo "Checking table: $table\n";
    
    // Get columns from principal
    $cols1 = [];
    $res1 = $conn->query("SHOW COLUMNS FROM {$db_principal}.{$table}");
    if ($res1) {
        while ($row = $res1->fetch_assoc()) {
            $cols1[] = $row['Field'];
        }
    } else {
        echo "Error reading {$db_principal}.{$table}: " . $conn->error . "\n";
    }

    // Get columns from historico
    $cols2 = [];
    $res2 = $conn->query("SHOW COLUMNS FROM {$db_historico}.{$table}");
    if ($res2) {
        while ($row = $res2->fetch_assoc()) {
            $cols2[] = $row['Field'];
        }
    } else {
        echo "Error reading {$db_historico}.{$table}: " . $conn->error . "\n";
    }

    $diff1 = array_diff($cols1, $cols2);
    $diff2 = array_diff($cols2, $cols1);

    if (empty($diff1) && empty($diff2) && count($cols1) == count($cols2)) {
        echo "OK: Columns match.\n";
    } else {
        echo "MISMATCH found!\n";
        echo "Columns in $db_principal but NOT in $db_historico:\n";
        print_r($diff1);
        echo "Columns in $db_historico but NOT in $db_principal:\n";
        print_r($diff2);
    }
    echo "------------------------------------------------\n";
}
?>
