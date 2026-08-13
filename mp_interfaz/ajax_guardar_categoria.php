<?php
// ajax_guardar_categoria.php

// 1. CONEXIÓN (Asegúrate que la ruta sea correcta)
// Usamos la conexión de la BD trt_25 como solicitaste.
include "../evt_interfaz/conexion.php";
require_once __DIR__ . '/../api/registrar_cambio.php';

// Preparamos una respuesta JSON
header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'Datos incompletos.'];

// 2. VERIFICAR DATOS POST
if (isset($_POST['id_evento']) && isset($_POST['nombre']) && isset($_POST['precio']) && isset($_POST['color'])) {
    
    $id_evento = (int)$_POST['id_evento'];
    $nombre = $_POST['nombre'];
    $precio = (float)$_POST['precio'];
    $color = $_POST['color'];

    if (empty($nombre) || $precio < 0 || $id_evento == 0) {
        $response['message'] = 'Datos no validos. El precio no puede ser negativo.';
    } else {
        
        // 3. INSERTAR EN LA BASE DE DATOS (Tabla 'categorias')
        try {
            $stmt = $conn->prepare("INSERT INTO categorias (id_evento, nombre_categoria, precio, color) VALUES (?, ?, ?, ?)");
            // 'isds' = integer, string, double, string
            $stmt->bind_param("isds", $id_evento, $nombre, $precio, $color);
            
            if ($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Categoría guardada con éxito.';
                $response['id_categoria'] = $conn->insert_id;
                if (function_exists('registrar_cambio')) {
                    registrar_cambio('categoria', $id_evento, null, ['accion' => 'crear', 'nombre' => $nombre, 'precio' => $precio]);
                }
            } else {
                $response['message'] = 'Error al ejecutar la consulta: ' . $stmt->error;
            }
            $stmt->close();
            
        } catch (Exception $e) {
            $response['message'] = 'Error de base de datos: ' . $e->getMessage();
        }
    }
}

$conn->close();

// 4. DEVOLVER RESPUESTA JSON
echo json_encode($response);
?>