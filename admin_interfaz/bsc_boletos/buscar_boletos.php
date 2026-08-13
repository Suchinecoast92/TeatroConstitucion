<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

require_once '../../conexion.php';

$action = $_GET['action'] ?? '';
$db = $_GET['db'] ?? 'ambas';

// Función para obtener nombre de la base de datos
function getDBName($tipo, $conn)
{
    $result = $conn->query("SELECT DATABASE() as db");
    $row = $result->fetch_assoc();
    $currentDB = $row['db'];

    if ($tipo === 'actual') {
        return $currentDB;
    } elseif ($tipo === 'historico') {
        // Asumiendo que la base histórica se llama igual pero con sufijo _historico
        return str_replace('trt_25', 'trt_historico_evento', $currentDB);
    }

    return null;
}

try {
    switch ($action) {
        case 'eventos':
            // Obtener lista de eventos (actual + histórico)
            $eventos = [];

            if (in_array($db, ['ambas', 'actual'])) {
                $result = $conn->query("
                    SELECT DISTINCT id_evento, titulo 
                    FROM evento 
                    ORDER BY titulo
                ");
                while ($row = $result->fetch_assoc()) {
                    $eventos[] = $row;
                }
            }

            // Intentar obtener eventos históricos
            if (in_array($db, ['ambas', 'historico'])) {
                try {
                    $result = $conn->query("
                        SELECT DISTINCT id_evento, titulo 
                        FROM trt_historico_evento.evento 
                        ORDER BY titulo
                    ");
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            // Evitar duplicados
                            $exists = false;
                            foreach ($eventos as $e) {
                                if ($e['id_evento'] == $row['id_evento']) {
                                    $exists = true;
                                    break;
                                }
                            }
                            if (!$exists) {
                                $eventos[] = $row;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Base histórica no existe, continuar
                }
            }

            echo json_encode($eventos);
            break;

        case 'categorias':
            // Obtener lista de categorías
            $categorias = [];

            if (in_array($db, ['ambas', 'actual'])) {
                $result = $conn->query("
                    SELECT DISTINCT id_categoria, nombre_categoria 
                    FROM categorias 
                    ORDER BY nombre_categoria
                ");
                while ($row = $result->fetch_assoc()) {
                    $categorias[] = $row;
                }
            }

            // Intentar obtener categorías históricas
            if (in_array($db, ['ambas', 'historico'])) {
                try {
                    $result = $conn->query("
                        SELECT DISTINCT id_categoria, nombre_categoria 
                        FROM trt_historico_evento.categorias 
                        ORDER BY nombre_categoria
                    ");
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            $exists = false;
                            foreach ($categorias as $c) {
                                if ($c['id_categoria'] == $row['id_categoria']) {
                                    $exists = true;
                                    break;
                                }
                            }
                            if (!$exists) {
                                $categorias[] = $row;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Base histórica no existe, continuar
                }
            }

            echo json_encode($categorias);
            break;

        case 'boletos':
            // Obtener todos los boletos
            $boletos = [];

            // Boletos actuales
            if (in_array($db, ['ambas', 'actual'])) {
                $query = "
                    SELECT 
                        b.id_boleto,
                        b.codigo_unico,
                        b.precio_final,
                        b.fecha_compra,
                        b.estatus,
                        b.id_evento,
                        b.id_categoria,
                        a.codigo_asiento,
                        e.titulo as evento_titulo,
                        c.nombre_categoria,
                        f.fecha_hora as funcion_fecha,
                        '' as cliente_nombre,
                        u.nombre AS vendedor_nombre,
                        'actual' as db_source
                    FROM boletos b
                    LEFT JOIN asientos a ON b.id_asiento = a.id_asiento
                    LEFT JOIN evento e ON b.id_evento = e.id_evento
                    LEFT JOIN categorias c ON b.id_categoria = c.id_categoria
                    LEFT JOIN funciones f ON b.id_funcion = f.id_funcion
                    LEFT JOIN usuarios u ON b.id_usuario = u.id_usuario
                    ORDER BY b.fecha_compra DESC
                ";

                $result = $conn->query($query);
                while ($row = $result->fetch_assoc()) {
                    $boletos[] = $row;
                }
            }

            // Boletos históricos
            if (in_array($db, ['ambas', 'historico'])) {
                try {
                    $query = "
                        SELECT 
                            b.id_boleto,
                            b.codigo_unico,
                            b.precio_final,
                            b.fecha_compra,
                            b.estatus,
                            b.id_evento,
                            b.id_categoria,
                            a.codigo_asiento,
                            e.titulo as evento_titulo,
                            c.nombre_categoria,
                            f.fecha_hora as funcion_fecha,
                            '' as cliente_nombre,
                            u.nombre AS vendedor_nombre,
                            'historico' as db_source
                        FROM trt_historico_evento.boletos b
                        LEFT JOIN trt_historico_evento.asientos a ON b.id_asiento = a.id_asiento
                        LEFT JOIN trt_historico_evento.evento e ON b.id_evento = e.id_evento
                        LEFT JOIN trt_historico_evento.categorias c ON b.id_categoria = c.id_categoria
                        LEFT JOIN trt_historico_evento.funciones f ON b.id_funcion = f.id_funcion
                        LEFT JOIN trt_historico_evento.usuarios u ON b.id_usuario = u.id_usuario
                        ORDER BY b.fecha_compra DESC
                    ";

                    $result = $conn->query($query);
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            $boletos[] = $row;
                        }
                    }
                } catch (Exception $e) {
                    // Base histórica no existe, continuar
                }
            }

            echo json_encode($boletos);
            break;

        default:
            echo json_encode(['error' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
