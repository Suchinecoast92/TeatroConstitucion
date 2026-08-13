<?php
/**
 * Trunca todas las tablas de trt_25 preservando usuarios con rol = 'admin'.
 * Uso: php sql/truncar_datos.php
 */
require_once __DIR__ . '/../config/database.php';

$conn = getLocalConnection();
if (!$conn) {
    fwrite(STDERR, "Error: no se pudo conectar a la base de datos.\n");
    exit(1);
}

$conn->query('SET FOREIGN_KEY_CHECKS = 0');

$admins = $conn->query("SELECT * FROM usuarios WHERE rol = 'admin'")->fetch_all(MYSQLI_ASSOC);

$tables = [];
$res = $conn->query('SHOW TABLES');
while ($row = $res->fetch_array()) {
    $tables[] = $row[0];
}

$truncated = [];
foreach ($tables as $table) {
    if ($table === 'usuarios') {
        continue;
    }
    $conn->query("TRUNCATE TABLE `$table`");
    $truncated[] = $table;
}

$conn->query('TRUNCATE TABLE usuarios');

foreach ($admins as $admin) {
    $stmt = $conn->prepare(
        'INSERT INTO usuarios (id_usuario, nombre, apellido, password, rol, activo, fecha_registro)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'issssis',
        $admin['id_usuario'],
        $admin['nombre'],
        $admin['apellido'],
        $admin['password'],
        $admin['rol'],
        $admin['activo'],
        $admin['fecha_registro']
    );
    $stmt->execute();
    $stmt->close();
}

$conn->query('SET FOREIGN_KEY_CHECKS = 1');

echo "Tablas truncadas (" . count($truncated) . "):\n";
echo "  " . implode(', ', $truncated) . "\n\n";

$users = $conn->query("SELECT id_usuario, nombre, apellido, rol, activo FROM usuarios")->fetch_all(MYSQLI_ASSOC);
echo "Usuarios conservados (" . count($users) . "):\n";
foreach ($users as $user) {
    echo "  - {$user['nombre']} {$user['apellido']} ({$user['rol']}, id={$user['id_usuario']})\n";
}

$conn->close();
