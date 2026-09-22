<?php
/**
 * Migración de contraseñas a password_hash().
 * Uso: php auth/migrar_contrasenas.php
 */
require_once __DIR__ . '/../includes/auth_guard.php';
teatro_require_cli();

require_once __DIR__ . '/../conexion.php';

$conn->query('ALTER TABLE usuarios MODIFY COLUMN password VARCHAR(255) NOT NULL');

$usuarios = $conn->query('SELECT id_usuario, nombre, password FROM usuarios');
if (!$usuarios) {
    fwrite(STDERR, "FAIL: no se pudo leer usuarios\n");
    exit(1);
}

$migrados = 0;
$omitidos = 0;
$errores = 0;

while ($user = $usuarios->fetch_assoc()) {
    $id = (int) $user['id_usuario'];
    $nombre = $user['nombre'];
    $password = (string) $user['password'];

    if (strpos($password, '$2y$') === 0 || strpos($password, '$2a$') === 0 || strpos($password, '$2b$') === 0) {
        echo "SKIP  {$nombre} (ya hasheada)\n";
        $omitidos++;
        continue;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('UPDATE usuarios SET password = ? WHERE id_usuario = ?');
    $stmt->bind_param('si', $hash, $id);
    if ($stmt->execute()) {
        echo "OK    {$nombre}\n";
        $migrados++;
    } else {
        echo "ERR   {$nombre}: {$conn->error}\n";
        $errores++;
    }
    $stmt->close();
}

echo "----\nMigradas={$migrados} Omitidas={$omitidos} Errores={$errores}\n";
exit($errores > 0 ? 1 : 0);
