<?php
/**
 * Script de migración de contraseñas
 * 
 * Este script convierte las contraseñas en texto plano a hashes seguros.
 * EJECUTAR SOLO UNA VEZ después de actualizar el código.
 * 
 * Acceso: http://localhost/teatro/auth/migrar_contrasenas.php
 */

session_start();
require_once '../conexion.php';

// Solo permitir acceso a admins logueados O si no hay usuarios hasheados aún
$acceso_permitido = false;

// Verificar si hay contraseñas ya hasheadas (empiezan con $2y$)
$check = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE password LIKE '\$2y\$%'");
$hasheadas = $check->fetch_assoc()['total'];

if ($hasheadas > 0) {
    // Ya hay contraseñas hasheadas, requerir login de admin
    if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
        die('<div style="font-family: Arial; text-align: center; margin-top: 50px; color: red;">
            <h1>Acceso Denegado</h1>
            <p>Este script ya fue ejecutado anteriormente.</p>
            <p>Las contraseñas ya están encriptadas.</p>
        </div>');
    }
}

$acceso_permitido = true;

// Modificar la estructura de la tabla para soportar hashes (60+ caracteres)
// Esto es idempotente - si ya es VARCHAR(255), no hace nada dañino
$conn->query("ALTER TABLE usuarios MODIFY COLUMN password VARCHAR(255) NOT NULL");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migración de Contraseñas</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        h1 { color: #2c3e50; margin-bottom: 10px; }
        p.subtitle { color: #666; margin-bottom: 30px; }
        .status { margin: 20px 0; padding: 15px; border-radius: 8px; }
        .status.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status.warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .status.info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .user-row { 
            padding: 10px 15px; 
            background: #f8f9fa; 
            margin: 5px 0; 
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
        }
        .user-row.migrated { background: #d4edda; }
        .user-row.skipped { background: #fff3cd; }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
            border: none;
            cursor: pointer;
            font-size: 1em;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102,126,234,0.3); }
    </style>
</head>
<body>
<div class="container">
    <h1>🔐 Migración de Contraseñas</h1>
    <p class="subtitle">Convertir contraseñas de texto plano a hash seguro</p>

    <?php
    // Obtener todos los usuarios
    $usuarios = $conn->query("SELECT id_usuario, nombre, password FROM usuarios");
    
    $migrados = 0;
    $omitidos = 0;
    $errores = 0;
    
    echo "<h3 style='margin: 20px 0 10px;'>Procesando usuarios...</h3>";
    
    while ($user = $usuarios->fetch_assoc()) {
        $id = $user['id_usuario'];
        $nombre = htmlspecialchars($user['nombre']);
        $password = $user['password'];
        
        // Verificar si ya está hasheada (los hashes bcrypt empiezan con $2y$)
        if (strpos($password, '$2y$') === 0) {
            echo "<div class='user-row skipped'>";
            echo "<span>👤 {$nombre}</span>";
            echo "<span>⏭️ Ya hasheada</span>";
            echo "</div>";
            $omitidos++;
        } else {
            // Hashear la contraseña
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id_usuario = ?");
            $stmt->bind_param("si", $hash, $id);
            
            if ($stmt->execute()) {
                echo "<div class='user-row migrated'>";
                echo "<span>👤 {$nombre}</span>";
                echo "<span>✅ Migrada</span>";
                echo "</div>";
                $migrados++;
            } else {
                echo "<div class='user-row' style='background:#f8d7da;'>";
                echo "<span>👤 {$nombre}</span>";
                echo "<span>❌ Error</span>";
                echo "</div>";
                $errores++;
            }
            $stmt->close();
        }
    }
    
    echo "<div class='status " . ($errores > 0 ? 'warning' : 'success') . "'>";
    echo "<strong>Migración Completada</strong><br>";
    echo "✅ Migradas: {$migrados}<br>";
    echo "⏭️ Omitidas (ya hasheadas): {$omitidos}<br>";
    if ($errores > 0) {
        echo "❌ Errores: {$errores}";
    }
    echo "</div>";
    
    if ($migrados > 0) {
        echo "<div class='status info'>";
        echo "<strong>Importante:</strong> Las contraseñas originales siguen funcionando.<br>";
        echo "Puedes hacer login con las mismas credenciales de siempre.";
        echo "</div>";
    }
    ?>
    
    <a href="../login.php" class="btn">Ir al Login</a>
</div>
</body>
</html>
