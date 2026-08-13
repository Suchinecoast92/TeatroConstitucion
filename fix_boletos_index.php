<?php
/**
 * Script para corregir el índice único en la tabla boletos
 * El índice debe permitir el mismo asiento en diferentes funciones
 * Maneja las llaves foráneas correctamente
 */

include "conexion.php";

echo "<h2>Corrigiendo índice de tabla boletos</h2><pre>\n";

try {
    // 1. Mostrar llaves foráneas actuales
    echo "1. Llaves foráneas actuales en la tabla boletos:\n";
    $result = $conn->query("
        SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'boletos'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    $foreign_keys = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "   - {$row['CONSTRAINT_NAME']}: {$row['COLUMN_NAME']} -> {$row['REFERENCED_TABLE_NAME']}.{$row['REFERENCED_COLUMN_NAME']}\n";
            $foreign_keys[] = $row;
        }
    }
    
    if (empty($foreign_keys)) {
        echo "   (No hay llaves foráneas)\n";
    }
    echo "\n";
    
    // 2. Mostrar índices actuales
    echo "2. Índices actuales:\n";
    $result = $conn->query("SHOW INDEX FROM boletos");
    while ($row = $result->fetch_assoc()) {
        $unique = $row['Non_unique'] ? '' : '(UNIQUE)';
        echo "   - {$row['Key_name']}: {$row['Column_name']} $unique\n";
    }
    echo "\n";
    
    // 3. Deshabilitar verificación de llaves foráneas temporalmente
    echo "3. Deshabilitando verificación de llaves foráneas...\n";
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    echo "   ✅ Verificación deshabilitada.\n\n";
    
    // 4. Eliminar el índice problemático
    $indices_a_eliminar = ['idx_evento_asiento', 'unique_evento_asiento', 'idx_unique_evento_asiento'];
    
    foreach ($indices_a_eliminar as $indice) {
        $check = $conn->query("SHOW INDEX FROM boletos WHERE Key_name = '$indice'");
        if ($check && $check->num_rows > 0) {
            echo "4. Eliminando índice '$indice'...\n";
            $conn->query("ALTER TABLE boletos DROP INDEX $indice");
            if ($conn->error) {
                echo "   Error: " . $conn->error . "\n";
            } else {
                echo "   ✅ Índice '$indice' eliminado correctamente.\n";
            }
        }
    }
    echo "\n";
    
    // 5. Verificar si ya existe un índice para (id_evento, id_funcion, id_asiento)
    $check_nuevo = $conn->query("SHOW INDEX FROM boletos WHERE Key_name = 'idx_evento_funcion_asiento'");
    if ($check_nuevo && $check_nuevo->num_rows > 0) {
        echo "5. El índice idx_evento_funcion_asiento ya existe.\n";
    } else {
        // Limpiar duplicados primero
        echo "5. Verificando duplicados...\n";
        $duplicados = $conn->query("
            SELECT id_evento, id_funcion, id_asiento, COUNT(*) as cantidad 
            FROM boletos 
            GROUP BY id_evento, id_funcion, id_asiento 
            HAVING cantidad > 1
        ");
        
        if ($duplicados && $duplicados->num_rows > 0) {
            echo "   ⚠️ Se encontraron registros duplicados. Limpiando...\n";
            while ($dup = $duplicados->fetch_assoc()) {
                echo "   - Evento {$dup['id_evento']}, Función {$dup['id_funcion']}, Asiento {$dup['id_asiento']}: {$dup['cantidad']} registros\n";
                
                // Mantener solo el boleto más reciente
                $conn->query("
                    DELETE b1 FROM boletos b1
                    INNER JOIN boletos b2
                    ON b1.id_evento = b2.id_evento 
                    AND b1.id_funcion = b2.id_funcion 
                    AND b1.id_asiento = b2.id_asiento
                    AND b1.id_boleto < b2.id_boleto
                    WHERE b1.id_evento = {$dup['id_evento']} 
                    AND b1.id_funcion = {$dup['id_funcion']} 
                    AND b1.id_asiento = {$dup['id_asiento']}
                ");
            }
        } else {
            echo "   ✅ No hay duplicados.\n";
        }
        
        // Crear el índice único
        echo "\n6. Creando nuevo índice único (id_evento, id_funcion, id_asiento)...\n";
        $result = $conn->query("ALTER TABLE boletos ADD UNIQUE INDEX idx_evento_funcion_asiento (id_evento, id_funcion, id_asiento)");
        if ($conn->error) {
            echo "   Error al crear índice: " . $conn->error . "\n";
        } else {
            echo "   ✅ Índice creado correctamente.\n";
        }
    }
    
    // 7. Rehabilitar verificación de llaves foráneas
    echo "\n7. Rehabilitando verificación de llaves foráneas...\n";
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    echo "   ✅ Verificación rehabilitada.\n";
    
    // 8. Mostrar índices finales
    echo "\n8. Índices finales en la tabla boletos:\n";
    $result = $conn->query("SHOW INDEX FROM boletos");
    while ($row = $result->fetch_assoc()) {
        $unique = $row['Non_unique'] ? '' : '(UNIQUE)';
        echo "   - {$row['Key_name']}: {$row['Column_name']} $unique\n";
    }
    
    echo "\n<span style='color:green;font-size:1.2em;font-weight:bold;'>✅ Proceso completado exitosamente.</span>\n";
    echo "Ahora puedes vender el mismo asiento para diferentes funciones.\n";
    
} catch (Exception $e) {
    // Rehabilitar llaves foráneas en caso de error
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    echo "❌ Error: " . $e->getMessage() . "\n";
}

$conn->close();
echo "</pre>";
?>
