<?php
require_once 'evt_interfaz/conexion.php';

// Actualizar funciones futuras a estado = 0 (activo)
$conn->query("UPDATE funciones SET estado = 0 WHERE fecha_hora > NOW()");
echo "Funciones actualizadas a ACTIVO: " . $conn->affected_rows . "\n";

// Verificar
$r = $conn->query("SELECT f.id_funcion, f.fecha_hora, f.estado, e.titulo 
                   FROM funciones f 
                   JOIN evento e ON f.id_evento = e.id_evento 
                   WHERE f.fecha_hora > NOW()
                   ORDER BY f.fecha_hora ASC");

echo "\nFunciones futuras ahora:\n";
while($row = $r->fetch_assoc()) {
    $estado_txt = $row['estado'] == 0 ? 'ACTIVO' : 'FINALIZADO';
    echo "- {$row['titulo']} | {$row['fecha_hora']} | $estado_txt\n";
}
