<?php
header('Content-Type: application/json');
include "../conexion.php";

try {
    $res_eventos = $conn->query("SELECT id_evento, titulo, tipo FROM evento WHERE finalizado = 0 ORDER BY titulo ASC");
    
    if ($res_eventos) {
        $eventos = [];
        while ($row = $res_eventos->fetch_assoc()) {
            $eventos[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'eventos' => $eventos
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener eventos'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
