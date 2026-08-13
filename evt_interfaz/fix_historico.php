<?php
// fix_setup_historico.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "../conexion.php";
$db_principal = 'trt_25';
$db_historico = 'trt_historico_evento';

echo "<pre>\n";
echo "=== REPARACIÓN DE TABLAS HISTÓRICAS ===\n\n";

// Asegurar que existe la BD
$conn->query("CREATE DATABASE IF NOT EXISTS `$db_historico`");

$tablas_requeridas = ['evento', 'funciones', 'categorias', 'promociones', 'boletos'];

foreach ($tablas_requeridas as $tabla) {
    echo "Verificando tabla '$tabla'...\n";
    $check = $conn->query("SHOW TABLES FROM `$db_historico` LIKE '$tabla'");
    
    if ($check && $check->num_rows == 1) {
        echo "   [OK] Ya existe.\n";
        continue;
    }

    echo "   [MISSING] No existe. Creando...\n";
    
    // Obtener estructura de la BD principal
    $res = $conn->query("SHOW CREATE TABLE `$db_principal`.`$tabla`");
    if ($res && $row = $res->fetch_assoc()) {
        $create_sql = $row['Create Table'];
        
        // 1. Apuntar a la BD histórica
        $create_sql = preg_replace('/CREATE TABLE `'.$tabla.'`/', "CREATE TABLE `$db_historico`.`$tabla`", $create_sql, 1);
        
        // 2. Eliminar FOREIGN KEYS para evitar errores de dependencia (errno 150)
        // Estrategia: Dividir por líneas, eliminar las que tengan CONSTRAINT ... FOREIGN KEY
        $lines = explode("\n", $create_sql);
        $clean_lines = [];
        foreach ($lines as $line) {
            // Si tiene FOREIGN KEY, la ignoramos
            if (stripos($line, 'FOREIGN KEY') !== false && stripos($line, 'CONSTRAINT') !== false) {
                continue;
            }
            $clean_lines[] = $line;
        }
        
        // Reconstruir y limpiar la última coma si quedó colgada
        // Un CREATE TABLE típico termina con ");". La penúltima línea no debe tener coma si es la última definición.
        // Pero el formato devuelto por MySQL suele ser muy limpio.
        // Verificamos si la línea anterior al cierre tiene coma.
        
        // Encontrar el cierre (última linea debería ser algo como ") ENGINE=...")
        $last_index = count($clean_lines) - 1;
        // La línea "real" de contenido es la $last_index - 1
        
        // Simplemente unimos y usamos regex para quitar la última coma antes del paréntesis de cierre
        $final_sql = implode("\n", $clean_lines);
        
        // Regex para quitar la coma antes del cierre de tabla
        // Busca una coma, seguida de cualquier espacio en blanco o saltos de línea, seguida de un paréntesis de cierre
        $final_sql = preg_replace('/,(\s*)\)/', '$1)', $final_sql);
        
        
        if ($conn->query($final_sql)) {
            echo "   [SUCCESS] Tabla creada exitosamente (sin FKs).\n";
        } else {
            echo "   [ERROR] " . $conn->error . "\n";
            echo "   SQL INTENTADO:\n$final_sql\n";
        }
        
    } else {
        echo "   [ERROR] No se pudo leer la estructura original.\n";
    }
}

echo "\nDiagnostic complete.\n";
echo "</pre>";
?>
