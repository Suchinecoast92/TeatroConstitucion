<?php
// 1. CONEXIÓN
include "../evt_interfaz/conexion.php"; // Asegúrate que esta ruta sea correcta
require_once __DIR__ . "/../api/registrar_cambio.php";

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'Solicitud no válida.'];

// 2. OBTENER DATOS (vienen como JSON)
$data = json_decode(file_get_contents('php://input'), true);

if ($data && isset($data['id_evento']) && isset($data['mapa'])) {
    
    $id_evento = (int)$data['id_evento'];
    $mapa_array = $data['mapa']; // Esto es un array [ {asiento: "A1", cat_id: 5}, ... ]

    // 3. Convertir el array a un objeto JSON simple: { "A1": 5, "PB1-1": 10 }
    $mapa_para_json = [];
    foreach ($mapa_array as $asiento) {
        $id_asiento_str = $asiento['asiento'];
        $id_categoria_int = (int)$asiento['cat_id'];
        
        // Solo guardamos si la categoría NO es 0 (Borrador)
        // Esto hace el JSON mucho más pequeño
        if ($id_categoria_int != 0) {
            $mapa_para_json[$id_asiento_str] = $id_categoria_int;
        }
    }

    // 4. Codificar el objeto como un string JSON
    $mapa_json_string = json_encode($mapa_para_json);

    // 5. Guardar el string en la (ÚNICA) columna de la tabla evento
    $stmt = $conn->prepare("UPDATE evento SET mapa_json = ? WHERE id_evento = ?");
    $stmt->bind_param("si", $mapa_json_string, $id_evento);
    
    if ($stmt->execute()) {
        $response['status'] = 'success';
        $response['message'] = 'Mapa guardado con éxito.';
        $response['notify_change'] = true;
        $response['id_evento'] = $id_evento;
        
        // Notificar cambio para auto-actualización en tiempo real
        registrar_cambio('mapa', $id_evento, null, ['asientos_configurados' => count($mapa_para_json)]);
    } else {
        $response['message'] = 'Error al guardar en la base de datos.';
    }
    $stmt->close();

} else {
    $response['message'] = 'Datos JSON incompletos.';
}

$conn->close();
echo json_encode($response);
?>