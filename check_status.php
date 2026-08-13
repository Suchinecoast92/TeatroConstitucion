<?php
require_once 'evt_interfaz/conexion.php';

echo "=== ESTADO DE FUNCIONES ===\n\n";

// Ver funciones
$r = $conn->query("SELECT f.id_funcion, f.id_evento, f.fecha_hora, f.estado, e.titulo 
                   FROM funciones f 
                   JOIN evento e ON f.id_evento = e.id_evento 
                   ORDER BY f.id_funcion DESC LIMIT 20");

echo "Funciones recientes:\n";
while($row = $r->fetch_assoc()) {
    $estado_txt = $row['estado'] == 1 ? 'FINALIZADO' : 'ACTIVO';
    echo "ID: {$row['id_funcion']} | Evento: {$row['titulo']} | Fecha: {$row['fecha_hora']} | Estado: {$row['estado']} ($estado_txt)\n";
}

echo "\n\n=== ESTADO DE BOLETOS ===\n\n";

// Ver boletos
$r = $conn->query("SELECT estatus, COUNT(*) as cantidad FROM boletos GROUP BY estatus");
while($row = $r->fetch_assoc()) {
    $estado_txt = $row['estatus'] == 1 ? 'VENDIDO' : 'OTRO';
    echo "Estatus {$row['estatus']} ($estado_txt): {$row['cantidad']} boletos\n";
}

echo "\n\n=== EVENTOS ACTIVOS ===\n\n";

// Ver eventos
$r = $conn->query("SELECT id_evento, titulo, finalizado FROM evento WHERE finalizado = 0");
while($row = $r->fetch_assoc()) {
    echo "ID: {$row['id_evento']} | Título: {$row['titulo']} | Finalizado: {$row['finalizado']}\n";
}
