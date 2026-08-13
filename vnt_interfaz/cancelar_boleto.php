<?php
// Cancelar boleto y liberar asiento
header('Content-Type: application/json');

include "../conexion.php";
session_start();

// Verificar si existen los helpers
if (file_exists("../transacciones_helper.php")) {
    require_once "../transacciones_helper.php";
}
if (file_exists("../api/registrar_cambio.php")) {
    require_once "../api/registrar_cambio.php";
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos del body JSON
$input = json_decode(file_get_contents('php://input'), true);

// Aceptar tanto id_boleto como codigo_unico
$id_boleto = $input['id_boleto'] ?? $_POST['id_boleto'] ?? 0;
$codigo_unico = $input['codigo_unico'] ?? $_POST['codigo_unico'] ?? '';

if (empty($id_boleto) && empty($codigo_unico)) {
    echo json_encode(['success' => false, 'message' => 'ID de boleto o código único requerido']);
    exit;
}

// Iniciar transacción
$conn->begin_transaction();

try {
    // Verificar que el boleto existe y está activo (estatus = 1)
    if (!empty($codigo_unico)) {
        // Buscar por código único
        $stmt = $conn->prepare("SELECT b.id_boleto, b.estatus, b.id_evento, b.id_funcion, a.codigo_asiento 
                               FROM boletos b 
                               LEFT JOIN asientos a ON b.id_asiento = a.id_asiento 
                               WHERE b.codigo_unico = ?");
        $stmt->bind_param("s", $codigo_unico);
    } else {
        // Buscar por ID
        $stmt = $conn->prepare("SELECT b.id_boleto, b.estatus, b.id_evento, b.id_funcion, a.codigo_asiento 
                               FROM boletos b 
                               LEFT JOIN asientos a ON b.id_asiento = a.id_asiento 
                               WHERE b.id_boleto = ?");
        $stmt->bind_param("i", $id_boleto);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Boleto no encontrado');
    }
    
    $boleto = $result->fetch_assoc();
    $id_boleto = $boleto['id_boleto']; // Asegurar que tenemos el ID
    
    if ($boleto['estatus'] == 0) {
        throw new Exception('El boleto ya fue usado y no puede ser cancelado');
    } elseif ($boleto['estatus'] == 2) {
        throw new Exception('El boleto ya fue cancelado previamente');
    } elseif ($boleto['estatus'] != 1) {
        throw new Exception('El boleto no está en estado válido para cancelación');
    }
    
    $stmt->close();
    
    // Actualizar el estado del boleto a 2 (cancelado)
    $stmt = $conn->prepare("UPDATE boletos SET estatus = 2 WHERE id_boleto = ?");
    $stmt->bind_param("i", $id_boleto);
    
    if (!$stmt->execute()) {
        throw new Exception('Error al cancelar el boleto');
    }
    
    $stmt->close();
    
    // Confirmar transacción
    $conn->commit();

    // Obtener información adicional para el registro detallado
    $evento_info = $conn->query("SELECT titulo FROM evento WHERE id_evento = " . $boleto['id_evento'])->fetch_assoc();
    $funcion_info = $boleto['id_funcion'] > 0 ? $conn->query("SELECT fecha_hora FROM funciones WHERE id_funcion = " . $boleto['id_funcion'])->fetch_assoc() : null;
    
    // Preparar datos detallados de la cancelación
    $datos_cancelacion = [
        'boleto' => [
            'id' => $id_boleto,
            'codigo_unico' => $codigo_unico ?: 'N/A',
            'asiento' => $boleto['codigo_asiento']
        ],
        'evento' => [
            'id' => $boleto['id_evento'],
            'titulo' => $evento_info['titulo'] ?? 'N/A'
        ],
        'funcion' => [
            'id' => $boleto['id_funcion'],
            'fecha_hora' => $funcion_info['fecha_hora'] ?? null
        ]
    ];
    
    $descripcion = "Cancelación de boleto - Asiento: " . $boleto['codigo_asiento'] . 
                   " - Evento: " . ($evento_info['titulo'] ?? 'N/A');

    if (function_exists('registrar_transaccion_con_datos')) {
        registrar_transaccion_con_datos('boleto_cancelar', $descripcion, json_encode($datos_cancelacion));
    } elseif (function_exists('registrar_transaccion')) {
        registrar_transaccion('boleto_cancelar', $descripcion);
    }
    
    // Notificar cambio para auto-actualización en tiempo real
    if (function_exists('registrar_cambio')) {
        registrar_cambio('cancelacion', $boleto['id_evento'], $boleto['id_funcion'], [
            'asiento' => $boleto['codigo_asiento'],
            'id_boleto' => $id_boleto
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Boleto cancelado exitosamente',
        'asiento' => $boleto['codigo_asiento']
    ]);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
