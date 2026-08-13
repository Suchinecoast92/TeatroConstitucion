<?php
/**
 * SINCRONIZAR ESQUEMA DE BASE DE DATOS HISTÓRICA
 * Este script asegura que las tablas en trt_historico_evento tengan 
 * la misma estructura que las tablas en trt_25
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'conexion.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Sincronizar Esquema</title></head><body>";
echo "<pre style='font-family: Consolas, monospace; background: #1a1a2e; color: #0f9; padding: 20px; margin: 20px; border-radius: 10px;'>";
echo "═══════════════════════════════════════════════════════\n";
echo "   SINCRONIZACIÓN DE ESQUEMA - BASE DE DATOS HISTÓRICA\n";
echo "═══════════════════════════════════════════════════════\n\n";

$db_principal = 'trt_25';
$db_historico = 'trt_historico_evento';

// Tablas a sincronizar
$tablas = ['evento', 'funciones', 'categorias', 'promociones', 'boletos'];

// Asegurar que la base de datos histórica existe
$conn->query("CREATE DATABASE IF NOT EXISTS `$db_historico`");
echo "✅ Base de datos '$db_historico' verificada\n\n";

foreach ($tablas as $tabla) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📋 Procesando tabla: <span style='color: #ff9f43;'>$tabla</span>\n";
    
    // Verificar si la tabla existe en principal
    $check_principal = $conn->query("SHOW TABLES FROM `$db_principal` LIKE '$tabla'");
    if ($check_principal->num_rows == 0) {
        echo "   ⚠️ La tabla no existe en $db_principal, saltando...\n\n";
        continue;
    }
    
    // Verificar si la tabla existe en histórico
    $check = $conn->query("SHOW TABLES FROM `$db_historico` LIKE '$tabla'");
    
    if ($check->num_rows == 0) {
        // La tabla NO existe en histórico - crearla desde cero
        echo "   ℹ️ Tabla no existe en histórico, creándola...\n";
        
        // Obtener el CREATE TABLE de la tabla original
        $create_result = $conn->query("SHOW CREATE TABLE `$db_principal`.`$tabla`");
        if ($create_result && $row = $create_result->fetch_assoc()) {
            $create_sql = $row['Create Table'];
            // Modificar para crear en la base de datos histórica
            $create_sql = preg_replace('/CREATE TABLE `' . $tabla . '`/', "CREATE TABLE `$db_historico`.`$tabla`", $create_sql);
            
            if ($conn->query($create_sql)) {
                echo "   ✅ Tabla CREADA exitosamente en $db_historico\n";
            } else {
                echo "   ❌ Error creando tabla: " . $conn->error . "\n";
            }
        }
    } else {
        // La tabla SÍ existe - comparar y agregar columnas faltantes
        echo "   ℹ️ Tabla existe, verificando columnas...\n";
        
        // Obtener columnas de la tabla principal
        $cols_principal = [];
        $cols_order = [];
        $result = $conn->query("SHOW COLUMNS FROM `$db_principal`.`$tabla`");
        while ($row = $result->fetch_assoc()) {
            $cols_principal[$row['Field']] = $row;
            $cols_order[] = $row['Field'];
        }
        
        // Obtener columnas de la tabla histórica
        $cols_historico = [];
        $result = $conn->query("SHOW COLUMNS FROM `$db_historico`.`$tabla`");
        while ($row = $result->fetch_assoc()) {
            $cols_historico[$row['Field']] = $row;
        }
        
        echo "   📊 Columnas en principal: " . count($cols_principal) . "\n";
        echo "   📊 Columnas en histórico: " . count($cols_historico) . "\n";
        
        // Encontrar columnas faltantes en histórico
        $faltantes = array_diff_key($cols_principal, $cols_historico);
        
        if (!empty($faltantes)) {
            echo "   ⚠️ <span style='color: #ff6b6b;'>Columnas faltantes: " . implode(', ', array_keys($faltantes)) . "</span>\n";
            
            $prev_col = null;
            foreach ($cols_order as $col_name) {
                if (isset($faltantes[$col_name])) {
                    $col_info = $faltantes[$col_name];
                    
                    // Construir la definición de columna
                    $tipo = $col_info['Type'];
                    $null = $col_info['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
                    
                    $default = '';
                    if ($col_info['Default'] !== null) {
                        if (is_numeric($col_info['Default'])) {
                            $default = "DEFAULT " . $col_info['Default'];
                        } else {
                            $default = "DEFAULT '" . $conn->real_escape_string($col_info['Default']) . "'";
                        }
                    } elseif ($col_info['Null'] === 'YES') {
                        $default = "DEFAULT NULL";
                    }
                    
                    // Posición de la columna
                    $position = $prev_col ? "AFTER `$prev_col`" : "FIRST";
                    
                    $sql = "ALTER TABLE `$db_historico`.`$tabla` ADD COLUMN `$col_name` $tipo $null $default $position";
                    
                    if ($conn->query($sql)) {
                        echo "   ✅ Columna '<span style='color: #4ecdc4;'>$col_name</span>' agregada\n";
                    } else {
                        echo "   ❌ Error agregando '$col_name': " . $conn->error . "\n";
                    }
                }
                $prev_col = $col_name;
            }
        } else {
            echo "   ✅ Todas las columnas están sincronizadas\n";
        }
    }
    
    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "   ✅ SINCRONIZACIÓN COMPLETADA\n";
echo "═══════════════════════════════════════════════════════\n";
echo "</pre>";

echo "<div style='margin: 20px;'>";
echo "<a href='evt_interfaz/act_evento.php' style='display: inline-block; padding: 15px 30px; background: #1561f0; color: white; text-decoration: none; border-radius: 8px; font-family: sans-serif; font-weight: bold;'>← Volver a Eventos Activos</a>";
echo "</div>";
echo "</body></html>";
?>
