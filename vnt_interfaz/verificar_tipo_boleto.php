<?php
/**
 * Script de verificación para el campo tipo_boleto
 * Ejecuta este archivo en el navegador para verificar que la actualización se aplicó correctamente
 */

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Verificación: Tipo de Boleto</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { padding: 40px; background: #f8f9fa; }
        .card { max-width: 800px; margin: 0 auto; }
        .status-ok { color: #28a745; }
        .status-error { color: #dc3545; }
        .status-warning { color: #ffc107; }
    </style>
</head>
<body>
    <div class='card'>
        <div class='card-header bg-primary text-white'>
            <h3 class='mb-0'>🔍 Verificación: Campo tipo_boleto</h3>
        </div>
        <div class='card-body'>";

try {
    // Incluir conexión
    include '../conexion.php';
    
    if (!isset($conn) || !$conn) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    echo "<h5>✅ Conexión a la base de datos exitosa</h5><hr>";
    
    // Verificar si existe la columna tipo_boleto
    $result = $conn->query("DESCRIBE boletos");
    
    if (!$result) {
        throw new Exception("Error al consultar la estructura de la tabla: " . $conn->error);
    }
    
    $columnas = [];
    $tipo_boleto_existe = false;
    
    while ($row = $result->fetch_assoc()) {
        $columnas[] = $row;
        if ($row['Field'] === 'tipo_boleto') {
            $tipo_boleto_existe = true;
        }
    }
    
    if ($tipo_boleto_existe) {
        echo "<div class='alert alert-success'>
                <h5 class='status-ok'>✅ La columna 'tipo_boleto' existe en la tabla 'boletos'</h5>
              </div>";
        
        // Mostrar información de la columna
        foreach ($columnas as $col) {
            if ($col['Field'] === 'tipo_boleto') {
                echo "<div class='card mb-3'>
                        <div class='card-body'>
                            <h6>Detalles de la columna:</h6>
                            <ul class='list-unstyled'>
                                <li><strong>Campo:</strong> {$col['Field']}</li>
                                <li><strong>Tipo:</strong> {$col['Type']}</li>
                                <li><strong>Nulo:</strong> {$col['Null']}</li>
                                <li><strong>Default:</strong> " . ($col['Default'] ?? 'NULL') . "</li>
                            </ul>
                        </div>
                      </div>";
            }
        }
        
        // Verificar boletos con tipo_boleto
        $result = $conn->query("SELECT tipo_boleto, COUNT(*) as cantidad FROM boletos GROUP BY tipo_boleto");
        
        if ($result && $result->num_rows > 0) {
            echo "<div class='card mb-3'>
                    <div class='card-body'>
                        <h6>Distribución de tipos de boleto:</h6>
                        <table class='table table-sm'>
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>";
            
            while ($row = $result->fetch_assoc()) {
                $tipo = $row['tipo_boleto'] ?? 'NULL';
                $cantidad = $row['cantidad'];
                
                $nombre_tipo = match($tipo) {
                    'adulto' => 'Adulto',
                    'nino' => 'Niño',
                    'adulto_mayor' => 'Adulto Mayor',
                    'discapacitado' => 'Discapacitado',
                    'cortesia' => 'Cortesía',
                    default => $tipo
                };
                
                echo "<tr>
                        <td>{$nombre_tipo}</td>
                        <td>{$cantidad}</td>
                      </tr>";
            }
            
            echo "      </tbody>
                        </table>
                    </div>
                  </div>";
        }
        
        // Verificar campo id_usuario
        $result_usuario = $conn->query("SHOW COLUMNS FROM boletos LIKE 'id_usuario'");
        if ($result_usuario && $result_usuario->num_rows > 0) {
            echo "<div class='alert alert-success'>
                    <h6 class='status-ok'>✅ La columna 'id_usuario' existe en la tabla 'boletos'</h6>
                  </div>";
        } else {
            echo "<div class='alert alert-warning'>
                    <h6 class='status-warning'>⚠️ La columna 'id_usuario' NO existe</h6>
                    <p>Ejecuta el script SQL completo para agregar este campo.</p>
                  </div>";
        }
        
        // Verificar boletos NULL
        $result = $conn->query("SELECT COUNT(*) as cantidad FROM boletos WHERE tipo_boleto IS NULL");
        if ($result) {
            $row = $result->fetch_assoc();
            if ($row['cantidad'] > 0) {
                echo "<div class='alert alert-warning'>
                        <h6 class='status-warning'>⚠️ Hay {$row['cantidad']} boleto(s) sin tipo asignado</h6>
                        <p class='mb-0'>Ejecuta este comando para corregirlo:</p>
                        <code>UPDATE boletos SET tipo_boleto = 'normal' WHERE tipo_boleto IS NULL;</code>
                      </div>";
            } else {
                echo "<div class='alert alert-info'>
                        <h6>ℹ️ Todos los boletos tienen un tipo asignado</h6>
                      </div>";
            }
        }
        
        echo "<div class='alert alert-success'>
                <h5>🎉 ¡Todo está configurado correctamente!</h5>
                <p class='mb-0'>Puedes empezar a usar la funcionalidad de tipo de boleto.</p>
              </div>";
        
    } else {
        echo "<div class='alert alert-danger'>
                <h5 class='status-error'>❌ La columna 'tipo_boleto' NO existe en la tabla 'boletos'</h5>
                <p>Debes ejecutar el script SQL de actualización:</p>
                <pre class='bg-light p-3 rounded'>ALTER TABLE boletos 
ADD COLUMN tipo_boleto VARCHAR(20) DEFAULT 'normal' AFTER precio_final;

UPDATE boletos SET tipo_boleto = 'normal' WHERE tipo_boleto IS NULL;</pre>
              </div>";
        
        echo "<div class='card'>
                <div class='card-body'>
                    <h6>Columnas actuales en la tabla 'boletos':</h6>
                    <ul>";
        
        foreach ($columnas as $col) {
            echo "<li>{$col['Field']} ({$col['Type']})</li>";
        }
        
        echo "    </ul>
                </div>
              </div>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>
            <h5 class='status-error'>❌ Error</h5>
            <p>{$e->getMessage()}</p>
          </div>";
}

echo "      </div>
        <div class='card-footer text-muted'>
            <small>Verificación realizada el " . date('Y-m-d H:i:s') . "</small>
        </div>
    </div>
</body>
</html>";
?>
