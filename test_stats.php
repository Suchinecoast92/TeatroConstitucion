<?php
// Test del API de estadísticas
require_once 'evt_interfaz/conexion.php';
session_start();
$_SESSION['usuario_id'] = 1;

// Simular la consulta principal de estadísticas
$sql_resumen = "SELECT 
    COUNT(*) as total_boletos,
    COALESCE(SUM(precio_final), 0) as total_ingresos,
    COALESCE(AVG(precio_final), 0) as ticket_promedio,
    COUNT(DISTINCT id_evento) as total_eventos,
    COUNT(DISTINCT id_funcion) as total_funciones
FROM boletos WHERE estatus = 1";

$result = $conn->query($sql_resumen);
if ($result) {
    $resumen = $result->fetch_assoc();
    echo "=== RESUMEN DE ESTADÍSTICAS ===\n\n";
    echo "Total Boletos: " . $resumen['total_boletos'] . "\n";
    echo "Total Ingresos: $" . number_format($resumen['total_ingresos'], 2) . "\n";
    echo "Ticket Promedio: $" . number_format($resumen['ticket_promedio'], 2) . "\n";
    echo "Total Eventos: " . $resumen['total_eventos'] . "\n";
    echo "Total Funciones: " . $resumen['total_funciones'] . "\n";
} else {
    echo "Error en la consulta: " . $conn->error . "\n";
}

// Verificar ranking de eventos
echo "\n=== RANKING DE EVENTOS ===\n\n";
$sql_ranking = "SELECT e.titulo, COUNT(*) as boletos, SUM(b.precio_final) as ingresos
    FROM boletos b 
    JOIN evento e ON b.id_evento = e.id_evento
    WHERE b.estatus = 1
    GROUP BY e.titulo 
    ORDER BY ingresos DESC";

$result = $conn->query($sql_ranking);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['titulo']}: {$row['boletos']} boletos, $" . number_format($row['ingresos'], 2) . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}
