<?php
// Capturar todos los errores y warnings
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Función para manejar errores fatales
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error fatal: ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line']
        ]);
    }
});

header('Content-Type: application/json');

// Iniciar sesión para obtener el usuario que está vendiendo
session_start();
require_once __DIR__ . '/../transacciones_helper.php';
require_once __DIR__ . '/../api/registrar_cambio.php';
require_once __DIR__ . '/../sync/reservas_helper.php';
require_once __DIR__ . '/../config/ventas.php';
$horasVentaAbierta = (int) HORAS_CIERRE_VENTAS_POST_FUNCION;

// Obtener ID del usuario logueado
$id_usuario_vendedor = isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;

try {
    require_once __DIR__ . '/vendor/autoload.php';
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error al cargar dependencias: ' . $e->getMessage()]);
    exit;
}

$conexion_path = __DIR__ . '/../conexion.php';
if (!file_exists($conexion_path)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Archivo de conexión no encontrado en: ' . $conexion_path]);
    exit;
}

try {
    include $conexion_path;
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error al incluir conexión: ' . $e->getMessage()]);
    exit;
}

if (!isset($conn) || !$conn) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error: No se pudo establecer conexión a la base de datos']);
    exit;
}

// Importar clases de Endroid QR Code
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;

// Leer datos JSON del request
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['id_evento']) || !isset($data['asientos'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$id_evento = (int) $data['id_evento'];
$id_funcion = isset($data['id_funcion']) ? (int) $data['id_funcion'] : 0;

// VALIDACIÓN SERVIDOR: la venta cierra 24 horas después de la hora de la función.
// La comparación se hace en SQL con NOW() para usar la misma zona horaria que los
// datos (evita desfases de zona entre PHP y MySQL).
if ($id_funcion > 0) {
    $stmt_val = $conn->prepare("SELECT (fecha_hora > (NOW() - INTERVAL {$horasVentaAbierta} HOUR)) AS abierta FROM funciones WHERE id_funcion = ? AND id_evento = ?");
    $stmt_val->bind_param("ii", $id_funcion, $id_evento);
    $stmt_val->execute();
    $row_val = $stmt_val->get_result()->fetch_assoc();
    $stmt_val->close();
    if (!$row_val) {
        echo json_encode(['success' => false, 'message' => 'La función seleccionada no existe.']);
        exit;
    }
    if ((int) $row_val['abierta'] === 0) {
        echo json_encode(['success' => false, 'message' => 'La venta para esta función ya cerró (' . $horasVentaAbierta . ' horas después de la hora de la función).']);
        exit;
    }
}

$asientos = $data['asientos'];
$codigos_tmp = array_map(fn($a) => $a['asiento'], $asientos);
// Debe coincidir con TeatroReservas.sessionId (sessionStorage), no con PHP session_id()
$session_id = !empty($data['session_id'])
    ? (string) $data['session_id']
    : (obtenerSessionReservaLocal($id_evento, $id_funcion ?: null, $codigos_tmp) ?: session_id());

if (empty($asientos)) {
    echo json_encode(['success' => false, 'message' => 'No hay asientos seleccionados']);
    exit;
}

// VERIFICACIÓN ATÓMICA ANTI DOBLE-VENTA
// Bloquea las filas en `boletos` (FOR UPDATE) y verifica que ningún asiento
// esté ya vendido en esta BD ni en la BD online, ni esté reservado por otra sesión.
$conn->begin_transaction();
try {
    $codigos_chk = array_map(fn($a) => $a['asiento'], $asientos);
    verificarVentaAtomica(
        $conn,
        $id_evento,
        $id_funcion ?: null,
        $codigos_chk,
        $session_id,
        'local'
    );
} catch (Throwable $eAtom) {
    $conn->rollback();
    ob_clean();
    echo json_encode(['success' => false, 'message' => $eAtom->getMessage()]);
    exit;
}

try {
    $boletos_generados = [];

    foreach ($asientos as $asiento_data) {
        $codigo_asiento = $asiento_data['asiento'];
        $categoria_id = (int) $asiento_data['categoriaId'];
        $precio = (float) $asiento_data['precio'];
        $descuento_aplicado = isset($asiento_data['descuento_aplicado']) ? (float) $asiento_data['descuento_aplicado'] : 0;
        $precio_final = isset($asiento_data['precio_final']) ? (float) $asiento_data['precio_final'] : $precio;
        $id_promocion = isset($asiento_data['id_promocion']) ? (int) $asiento_data['id_promocion'] : null;
        $tipo_boleto = isset($asiento_data['tipo_boleto']) ? $asiento_data['tipo_boleto'] : 'adulto';

        // Validar que la categoría existe y pertenece al evento
        $stmt = $conn->prepare("SELECT id_categoria FROM categorias WHERE id_categoria = ? AND id_evento = ?");
        $stmt->bind_param("ii", $categoria_id, $id_evento);
        $stmt->execute();
        $result_cat = $stmt->get_result();

        if ($result_cat->num_rows === 0) {
            // La categoría no existe o no pertenece al evento, buscar una categoría por defecto
            $stmt->close();

            // Primero intentar encontrar "General"
            $stmt = $conn->prepare("SELECT id_categoria FROM categorias WHERE id_evento = ? AND LOWER(nombre_categoria) = 'general' LIMIT 1");
            $stmt->bind_param("i", $id_evento);
            $stmt->execute();
            $result_cat = $stmt->get_result();

            if ($result_cat->num_rows > 0) {
                $row_cat = $result_cat->fetch_assoc();
                $categoria_id = (int) $row_cat['id_categoria'];
                $stmt->close();
            } else {
                // Si no hay "General", tomar la primera categoría disponible del evento
                $stmt->close();
                $stmt = $conn->prepare("SELECT id_categoria FROM categorias WHERE id_evento = ? ORDER BY precio ASC LIMIT 1");
                $stmt->bind_param("i", $id_evento);
                $stmt->execute();
                $result_cat = $stmt->get_result();

                if ($result_cat->num_rows > 0) {
                    $row_cat = $result_cat->fetch_assoc();
                    $categoria_id = (int) $row_cat['id_categoria'];
                    $stmt->close();
                } else {
                    // No hay categorías para este evento
                    $stmt->close();
                    throw new Exception("El evento no tiene categorías configuradas. Por favor, configura las categorías antes de vender boletos.");
                }
            }
        } else {
            $stmt->close();
        }

        // Si es cortesía, el precio final es 0
        if ($tipo_boleto === 'cortesia') {
            $precio_final = 0.00;
            $descuento_aplicado = $precio; // El descuento es el precio completo
        }

        // Obtener o crear id_asiento de la tabla asientos
        $stmt = $conn->prepare("SELECT id_asiento FROM asientos WHERE codigo_asiento = ?");
        $stmt->bind_param("s", $codigo_asiento);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // Si el asiento no existe, crearlo
            $stmt->close();

            // Extraer fila y número del código de asiento
            preg_match('/^([A-Z]+\d*)[-]?(\d+)$/', $codigo_asiento, $matches);
            $fila = isset($matches[1]) ? $matches[1] : substr($codigo_asiento, 0, 1);
            $numero = isset($matches[2]) ? (int) $matches[2] : (int) filter_var($codigo_asiento, FILTER_SANITIZE_NUMBER_INT);

            $stmt = $conn->prepare("INSERT INTO asientos (codigo_asiento, fila, numero) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $codigo_asiento, $fila, $numero);
            if (!$stmt->execute()) {
                throw new Exception("Error al crear asiento $codigo_asiento: " . $stmt->error);
            }
            $id_asiento = $conn->insert_id;
            $stmt->close();
        } else {
            $row = $result->fetch_assoc();
            $id_asiento = $row['id_asiento'];
            $stmt->close();
        }

        // Verificar si existe un boleto para este asiento
        $sql = "SELECT id_boleto, estatus FROM boletos WHERE id_evento = ? AND id_asiento = ?";
        $params = [$id_evento, $id_asiento];
        $types = "ii";

        // Si se proporcionó id_funcion, incluirlo en la consulta
        if ($id_funcion > 0) {
            $sql .= " AND id_funcion = ?";
            $params[] = $id_funcion;
            $types .= "i";
        } else {
            // Si no se proporcionó id_funcion, buscar boletos con id_funcion NULL o 0
            $sql .= " AND (id_funcion IS NULL OR id_funcion = 0)";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $boleto_existente = null;
        if ($result->num_rows > 0) {
            $boleto_existente = $result->fetch_assoc();
        }
        $stmt->close();

        // Si existe un boleto activo (estatus = 1), no se puede vender
        if ($boleto_existente && $boleto_existente['estatus'] == 1) {
            throw new Exception("El asiento $codigo_asiento ya está vendido");
        }

        // Generar código único alfanumérico
        $codigo_unico = strtoupper(bin2hex(random_bytes(8)));

        // Si existe un boleto cancelado (estatus = 2) o usado (estatus = 0), reutilizarlo
        if ($boleto_existente) {
            // Actualizar el boleto existente
            if ($id_promocion) {
                $stmt = $conn->prepare("\n                    UPDATE boletos SET\n                        id_funcion = ?,\n                        id_categoria = ?,\n                        id_promocion = ?,\n                        codigo_unico = ?,\n                        precio_base = ?,\n                        descuento_aplicado = ?,\n                        precio_final = ?,\n                        tipo_boleto = ?,\n                        id_usuario = ?,\n                        fecha_compra = NOW(),\n                        estatus = 1\n                    WHERE id_boleto = ?\n                ");

                $stmt->bind_param(
                    "iiisdddsii",
                    $id_funcion,
                    $categoria_id,
                    $id_promocion,
                    $codigo_unico,
                    $precio,
                    $descuento_aplicado,
                    $precio_final,
                    $tipo_boleto,
                    $id_usuario_vendedor,
                    $boleto_existente['id_boleto']
                );
            } else {
                $stmt = $conn->prepare("\n                    UPDATE boletos SET\n                        id_funcion = ?,\n                        id_categoria = ?,\n                        id_promocion = NULL,\n                        codigo_unico = ?,\n                        precio_base = ?,\n                        descuento_aplicado = ?,\n                        precio_final = ?,\n                        tipo_boleto = ?,\n                        id_usuario = ?,\n                        fecha_compra = NOW(),\n                        estatus = 1\n                    WHERE id_boleto = ?\n                ");

                $stmt->bind_param(
                    "iisdddsii",
                    $id_funcion,
                    $categoria_id,
                    $codigo_unico,
                    $precio,
                    $descuento_aplicado,
                    $precio_final,
                    $tipo_boleto,
                    $id_usuario_vendedor,
                    $boleto_existente['id_boleto']
                );
            }

            if (!$stmt->execute()) {
                throw new Exception("Error al actualizar boleto: " . $stmt->error);
            }
            $stmt->close();
        } else {
            // Insertar nuevo boleto si no existe ninguno
            if ($id_promocion) {
                $stmt = $conn->prepare("\n                    INSERT INTO boletos (\n                        id_evento,\n                        id_funcion,\n                        id_asiento,\n                        id_categoria,\n                        id_promocion,\n                        codigo_unico,\n                        precio_base,\n                        descuento_aplicado,\n                        precio_final,\n                        tipo_boleto,\n                        id_usuario,\n                        fecha_compra,\n                        estatus\n                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)\n                ");

                $stmt->bind_param(
                    "iiiiisdddsi",
                    $id_evento,
                    $id_funcion,
                    $id_asiento,
                    $categoria_id,
                    $id_promocion,
                    $codigo_unico,
                    $precio,
                    $descuento_aplicado,
                    $precio_final,
                    $tipo_boleto,
                    $id_usuario_vendedor
                );
            } else {
                $stmt = $conn->prepare("\n                    INSERT INTO boletos (\n                        id_evento,\n                        id_funcion,\n                        id_asiento,\n                        id_categoria,\n                        codigo_unico,\n                        precio_base,\n                        descuento_aplicado,\n                        precio_final,\n                        tipo_boleto,\n                        id_usuario,\n                        fecha_compra,\n                        estatus\n                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)\n                ");

                $stmt->bind_param(
                    "iiiisdddsi",
                    $id_evento,
                    $id_funcion,
                    $id_asiento,
                    $categoria_id,
                    $codigo_unico,
                    $precio,
                    $descuento_aplicado,
                    $precio_final,
                    $tipo_boleto,
                    $id_usuario_vendedor
                );
            }

            if (!$stmt->execute()) {
                throw new Exception("Error al crear boleto: " . $stmt->error);
            }
            $stmt->close();
        }

        // Generar y guardar el código QR físico
        try {
            $qrDir = __DIR__ . '/../boletos_qr/';
            if (!is_dir($qrDir)) {
                mkdir($qrDir, 0777, true);
            }
            
            $qrPath = $qrDir . $codigo_unico . '.png';
            
            $qrCode = QrCode::create($codigo_unico)
                ->setEncoding(new Encoding('UTF-8'))
                ->setErrorCorrectionLevel(new ErrorCorrectionLevelLow())
                ->setSize(300)
                ->setMargin(10)
                ->setRoundBlockSizeMode(new RoundBlockSizeModeMargin());
                
            $writer = new PngWriter();
            $resultQR = $writer->write($qrCode);
            $resultQR->saveToFile($qrPath);
            
        } catch (\Exception $e) {
            error_log("No se pudo generar el QR para $codigo_unico: " . $e->getMessage());
        }

        $boletos_generados[] = [
            'asiento' => $codigo_asiento,
            'codigo_unico' => $codigo_unico,
            'precio' => $precio_final,
            'tipo_boleto' => $tipo_boleto
        ];
    }

    // Calcular totales y datos para la transacción
    $cantidad_boletos = count($boletos_generados);
    $total_venta = array_sum(array_column($boletos_generados, 'precio'));

    // Obtener información del evento y función para el registro
    $evento_info = $conn->query("SELECT titulo FROM evento WHERE id_evento = " . (int) $id_evento)->fetch_assoc();
    $funcion_info = null;
    if ($id_funcion > 0) {
        $funcion_info = $conn->query("SELECT fecha_hora FROM funciones WHERE id_funcion = " . (int) $id_funcion)->fetch_assoc();
    }

    $nombre_cliente = isset($data['nombre_cliente']) ? $data['nombre_cliente'] : 'Cliente General';

    $datos_venta = [
        'evento' => [
            'id' => $id_evento,
            'titulo' => $evento_info['titulo'] ?? 'N/A'
        ],
        'funcion' => [
            'id' => $id_funcion,
            'fecha_hora' => $funcion_info['fecha_hora'] ?? null
        ],
        'cliente' => $nombre_cliente,
        'boletos' => $boletos_generados,
        'total' => $total_venta,
        'usuario_vendedor' => $id_usuario_vendedor
    ];

    // Confirmar transacción
    $conn->commit();

    // Liberar las reservas temporales correspondientes a estos asientos
    try {
        liberarTrasVenta(
            $session_id,
            $id_evento,
            $id_funcion ?: null,
            array_column($boletos_generados, 'asiento')
        );
    } catch (Throwable $eRel) {
        error_log('[Reservas] No se pudieron liberar tras venta: ' . $eRel->getMessage());
    }

    // ===== RESPALDO INMUTABLE A trt_25_backup (no bloqueante) =====
    $boletos_ids_array = [];
    if (file_exists(__DIR__ . '/../sync/backup_helper.php')) {
        require_once __DIR__ . '/../sync/backup_helper.php';
        foreach ($boletos_generados as $bg) {
            $stmt_b = $conn->prepare("SELECT id_boleto FROM boletos WHERE codigo_unico = ? LIMIT 1");
            $stmt_b->bind_param('s', $bg['codigo_unico']);
            $stmt_b->execute();
            $row_b = $stmt_b->get_result()->fetch_assoc();
            $stmt_b->close();
            if ($row_b) {
                $boletos_ids_array[] = (int)$row_b['id_boleto'];
            }
        }
        @respaldarEvento($conn, $id_evento, 'local');
        foreach ($boletos_ids_array as $id_b) {
            @respaldarBoleto($conn, $id_b, 'local');
        }
        // Registrar venta detallada con metadatos extra (lugar, método pago, tarjeta, etc.)
        $datos_venta_extra = [
            'origen' => 'local',
            'id_evento' => (int)$id_evento,
            'id_funcion' => $id_funcion ? (int)$id_funcion : null,
            'titulo_evento' => $evento_info['titulo'] ?? null,
            'fecha_funcion' => $funcion_info['fecha_hora'] ?? null,
            'cliente_nombre' => $nombre_cliente,
            'cliente_email' => $data['email_cliente'] ?? null,
            'cliente_telefono' => $data['telefono_cliente'] ?? null,
            'id_usuario_vendedor' => $id_usuario_vendedor,
            'nombre_vendedor' => isset($_SESSION['usuario_nombre'])
                ? $_SESSION['usuario_nombre'] . ' ' . ($_SESSION['usuario_apellido'] ?? '')
                : null,
            'lugar_venta' => $data['lugar_venta'] ?? 'Taquilla',
            'metodo_pago' => $data['metodo_pago'] ?? 'efectivo',
            'tarjeta_terminacion' => $data['tarjeta_terminacion'] ?? null,
            'referencia_pago' => $data['referencia_pago'] ?? null,
            'cuenta_destino' => $data['cuenta_destino'] ?? null,
            'cantidad_boletos' => $cantidad_boletos,
            'subtotal' => (float)$total_venta,
            'descuento_aplicado' => 0,
            'total' => (float)$total_venta,
            'asientos' => array_column($boletos_generados, 'asiento'),
            'codigos_boletos' => array_column($boletos_generados, 'codigo_unico'),
            'boletos_ids' => $boletos_ids_array,
            'notas' => $data['notas'] ?? null,
        ];
        @registrarVentaDetallada($datos_venta_extra);
    }

    // Descripción clara para la lista
    $descripcion = "Venta de $cantidad_boletos boleto(s) - Evento: " . ($evento_info['titulo'] ?? 'N/A') .
        " - Asientos: " . implode(', ', array_column($boletos_generados, 'asiento')) .
        " - Total: $" . number_format($total_venta, 2);

    registrar_transaccion_con_datos('venta', $descripcion, json_encode($datos_venta));

    // Notificar cambio para auto-actualización en tiempo real
    registrar_cambio('venta', $id_evento, $id_funcion, [
        'asientos' => array_column($boletos_generados, 'asiento'),
        'cantidad' => $cantidad_boletos
    ]);

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Compra procesada exitosamente',
        'boletos' => $boletos_generados
    ]);

} catch (Exception $e) {
    $conn->rollback();
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
ob_end_flush();
?>