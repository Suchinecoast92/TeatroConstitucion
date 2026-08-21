<?php
// Conexión específica para APIs que devuelven JSON
// No genera salida HTML en caso de error
// Usa la misma config central (.env opcional) que el resto del sistema.

require_once __DIR__ . '/../config/database.php';

$conn = getLocalConnection();
?>
