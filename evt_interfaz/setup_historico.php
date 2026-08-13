<?php
// setup_historico.php
// Script para inicializar/sincronizar la estructura de la base de datos histórica
// Ejecutar este script cuando se realicen cambios en la estructura de la base de datos principal

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ajustar ruta según ubicación real si es necesario, asumiendo mismo directorio que act_evento.php
include "../conexion.php"; 

$db_principal = 'trt_25';
$db_historico = 'trt_historico_evento';
$tablas = ['evento', 'funciones', 'categorias', 'promociones', 'boletos', 'precios_tipo_boleto'];

echo "<h2>Inicializando configuración de histórico...</h2>";
echo "<pre>";

try {
    // 1. Crear BD histórica
    if ($conn->query("CREATE DATABASE IF NOT EXISTS `$db_historico`")) {
        echo "[OK] Base de datos '$db_historico' verificada.\n";
    } else {
        throw new Exception("Error creando BD: " . $conn->error);
    }

    // 2. ELIMINAR tablas en orden inverso (dependientes primero)
    echo "\n--- PASO 1: Eliminando tablas antiguas ---\n";
    $tablas_invertido = array_reverse($tablas);
    foreach ($tablas_invertido as $tabla) {
        $conn->query("DROP TABLE IF EXISTS `$db_historico`.`$tabla`");
        echo "[DROP] Tabla '$tabla' eliminada (si existía).\n";
    }

    // 3. CREAR tablas en orden correcto (principales primero)
    echo "\n--- PASO 2: Creando tablas con estructura actualizada ---\n";
    foreach ($tablas as $tabla) {
        // Obtener estructura de producción
        $create = $conn->query("SHOW CREATE TABLE `$db_principal`.`$tabla`");
        if ($create && $row = $create->fetch_assoc()) {
            $sql_create = $row['Create Table'];
            
            // Reemplazar nombre de tabla para apuntar a la BD histórica
            $sql_create = preg_replace('/CREATE TABLE `'.$tabla.'`/', "CREATE TABLE `$db_historico`.`$tabla`", $sql_create, 1);
            
            // 2. Eliminar FOREIGN KEYS para evitar errores de dependencia (errno 150)
            // Estrategia: Dividir por líneas, eliminar las que tengan CONSTRAINT ... FOREIGN KEY
            $lines = explode("\n", $sql_create);
            $clean_lines = [];
            foreach ($lines as $line) {
                // Si tiene FOREIGN KEY, la ignoramos
                if (stripos($line, 'FOREIGN KEY') !== false && stripos($line, 'CONSTRAINT') !== false) {
                    continue;
                }
                $clean_lines[] = $line;
            }
            
            // Reconstruir y limpiar la última coma si quedó colgada
            $sql_create = implode("\n", $clean_lines);
            $sql_create = preg_replace('/,(\s*)\)/', '$1)', $sql_create);

            if ($conn->query($sql_create)) {
                echo "[OK] Tabla '$tabla' creada exitosamente.\n";
            } else {
                echo "[ERROR] Falló creación de '$tabla': " . $conn->error . "\n";
                throw new Exception("Error creando tabla $tabla");
            }
        } else {
            echo "[ERROR] No se pudo leer estructura de '$tabla' en principal.\n";
            throw new Exception("Error leyendo estructura de $tabla");
        }
    }
    
    echo "\n✓ Proceso finalizado correctamente. Todas las tablas están sincronizadas.\n";

} catch (Exception $e) {
    echo "\n[FATAL] Excepción: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
