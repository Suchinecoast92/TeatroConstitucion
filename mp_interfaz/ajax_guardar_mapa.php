<?php
session_start();
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/csrf.php';
teatro_require_admin(true);
teatro_require_csrf(true);

include "../evt_interfaz/conexion.php";
require_once __DIR__ . "/../api/registrar_cambio.php";

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'Solicitud no válida.'];

$data = teatro_csrf_consumed_json_body();
if ($data === null) {
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : null;
}

if (is_array($data) && isset($data['id_evento'], $data['mapa'])) {
    $id_evento = (int) $data['id_evento'];
    $mapa_array = $data['mapa'];

    $mapa_para_json = [];
    if (is_array($mapa_array)) {
        foreach ($mapa_array as $asiento) {
            if (!is_array($asiento)) {
                continue;
            }
            $id_asiento_str = (string) ($asiento['asiento'] ?? '');
            $id_categoria_int = (int) ($asiento['cat_id'] ?? 0);
            if ($id_asiento_str !== '' && $id_categoria_int != 0) {
                $mapa_para_json[$id_asiento_str] = $id_categoria_int;
            }
        }
    }

    $mapa_json_string = json_encode($mapa_para_json);
    $stmt = $conn->prepare("UPDATE evento SET mapa_json = ? WHERE id_evento = ?");
    $stmt->bind_param("si", $mapa_json_string, $id_evento);

    if ($stmt->execute()) {
        $response['status'] = 'success';
        $response['message'] = 'Mapa guardado con éxito.';
        $response['notify_change'] = true;
        $response['id_evento'] = $id_evento;
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
