<?php
// INICIO: Capturar TODO el output desde el principio con callback
$captured_errors = '';
$action = $_GET['action'] ?? 'generar';

// Callback para interceptar TODO el output
function capture_all_output($buffer) {
    global $captured_errors;
    
    // Si estamos exportando PDF, no interferir
    $action = $_GET['action'] ?? 'generar';
    if ($action === 'export') {
        return $buffer; // Dejar pasar el output para PDF
    }
    
    // Si el buffer contiene errores de Xdebug o HTML, capturarlo
    if (!empty($buffer) && (
        stripos($buffer, 'xdebug') !== false || 
        stripos($buffer, 'fatal-error') !== false
    )) {
        $captured_errors .= $buffer;
        return ''; // No devolver el buffer, lo capturamos
    }
    
    return $buffer;
}

// Solo iniciar output buffering con callback si NO estamos exportando
if ($action !== 'export') {
    ob_start('capture_all_output', 0, PHP_OUTPUT_HANDLER_STDFLAGS);
} else {
    // Para export, solo iniciar buffer simple sin callback
    ob_start();
}

// Deshabilitar display de errores para evitar output antes del JSON
error_reporting(0); // Deshabilitar completamente para evitar que Xdebug muestre errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Deshabilitar Xdebug completamente - múltiples métodos
if (function_exists('xdebug_disable')) {
    @xdebug_disable();
}
@ini_set('xdebug.overload_var_dump', 0);
@ini_set('xdebug.mode', 'off');
@ini_set('xdebug.start_with_request', 0);
@ini_set('xdebug.var_display_max_children', 0);
@ini_set('xdebug.var_display_max_data', 0);
@ini_set('xdebug.var_display_max_depth', 0);
ini_set('html_errors', 0);

// Manejador de errores personalizado para capturar errores fatales
function errorHandler($errno, $errstr, $errfile, $errline) {
    // No mostrar errores, solo loguearlos
    @error_log("Error [$errno]: $errstr en $errfile línea $errline");
    return true; // Suprimir el error por defecto
}

function fatalErrorHandler() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Limpiar TODO el output
        while (@ob_get_level()) {
            @ob_end_clean();
        }
        
        // Enviar JSON con el error
        @header('Content-Type: application/json; charset=utf-8');
        @header('X-Content-Type-Options: nosniff');
        echo @json_encode([
            'ok' => false,
            'error' => 'Error fatal: ' . $error['message'] . ' en ' . basename($error['file']) . ' línea ' . $error['line']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Registrar manejadores
@set_error_handler('errorHandler');
@register_shutdown_function('fatalErrorHandler');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Conexión - capturar cualquier output
try {
    include_once __DIR__ . '/../../evt_interfaz/conexion.php';
} catch (Exception $e) {
    ob_clean();
    throw new Exception('Error al incluir archivo de conexión: ' . $e->getMessage());
} catch (Error $e) {
    ob_clean();
    throw new Exception('Error fatal al incluir archivo de conexión: ' . $e->getMessage());
}
$output = ob_get_contents();
ob_clean();

// Si hay output del include, es un error
if (!empty($output) && $output !== '') {
    // Si el output contiene "Error de conexión", lanzar excepción
    if (stripos($output, 'Error de conexión') !== false) {
        throw new Exception('Error de conexión a la base de datos');
    }
    // Limpiar cualquier otro output
    if (ob_get_level()) {
        ob_clean();
    }
}

// Detectar conexión disponible
$mysqli = null;
$pdo    = null;

foreach (['conn','conexion','mysqli'] as $m) {
    if (isset($GLOBALS[$m]) && $GLOBALS[$m] instanceof mysqli) {
        $mysqli = $GLOBALS[$m];
        // Verificar que la conexión esté activa
        if ($mysqli->connect_error) {
            throw new Exception('Error de conexión: ' . $mysqli->connect_error);
        }
    }
}
if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    $pdo = $GLOBALS['pdo'];
}

// Si no hay conexión disponible, lanzar excepción
if (!$mysqli && !$pdo) {
    throw new Exception('No se pudo establecer conexión a la base de datos');
}

// ============= CONFIGURACIÓN BD HISTÓRICA =============
$db_mode = $_GET['db'] ?? 'actual';
$db_actual = 'trt_25';
$db_historico = 'trt_historico_evento';

// Función para agregar prefijo de BD a una tabla con columnas específicas
function db_table($table, $columns = '*') {
    global $db_mode, $db_actual, $db_historico;
    
    if ($db_mode === 'ambas') {
        // Usar UNION para combinar ambas BDs - IMPORTANTE: especificar columnas explícitas
        return "(SELECT {$columns} FROM {$db_actual}.{$table} UNION ALL SELECT {$columns} FROM {$db_historico}.{$table})";
    }
    
    $db = ($db_mode === 'historico') ? $db_historico : $db_actual;
    return "{$db}.{$table}";
}

// Helpers ==================================================
function respond($ok, $data = []) {
    // Obtener todo el output capturado
    $captured_output = '';
    while (@ob_get_level() > 0) {
        $captured_output .= @ob_get_contents();
        @ob_end_clean();
    }
    
    // Si hay output capturado que no es JSON, es un error
    if (!empty($captured_output)) {
        // Limpiar cualquier HTML/error de Xdebug
        $cleaned = strip_tags($captured_output);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        
        // Si parece un error, incluirlo en la respuesta
        if (stripos($captured_output, 'error') !== false || 
            stripos($captured_output, 'fatal') !== false ||
            stripos($captured_output, 'xdebug') !== false) {
            $data['debug_info'] = 'Output capturado: ' . substr($cleaned, 0, 200);
        }
    }
    
    // Establecer header JSON
    @header('Content-Type: application/json; charset=utf-8');
    @header('X-Content-Type-Options: nosniff');
    
    $response = $ok ? array_merge(['ok'=>true], $data) : array_merge(['ok'=>false], $data);
    
    $json = @json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    
    if ($json === false) {
        // Si hay error al codificar JSON, devolver error simple
        $error_response = @json_encode([
            'ok' => false,
            'error' => 'Error al generar respuesta JSON: ' . @json_last_error_msg()
        ], JSON_UNESCAPED_UNICODE);
        echo $error_response;
    } else {
        echo $json;
    }
    
    exit;
}

function fetch_all_assoc_mysqli($res){
    $rows=[];
    while($row=$res->fetch_assoc()) $rows[]=$row;
    return $rows;
}

function exec_query($sql, $params = []) {
    global $mysqli, $pdo;
    
    // mysqli
    if ($mysqli instanceof mysqli) {
        if (empty($params)) {
            $res = $mysqli->query($sql);
            if ($res === false) throw new Exception($mysqli->error);
            $out = [];
            while ($row = $res->fetch_assoc()) $out[] = $row;
            return $out;
        } else {
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) throw new Exception($mysqli->error);
            if (!empty($params)) {
                // Determinar tipos de parámetros automáticamente
                $types = '';
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_float($param)) {
                        $types .= 'd';
                    } else {
                        $types .= 's';
                    }
                }
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            $out = [];
            while ($row = $res->fetch_assoc()) $out[] = $row;
            $stmt->close();
            return $out;
        }
    }
    
    // PDO
    if ($pdo instanceof PDO) {
        $st = $pdo->prepare($sql);
        if ($st === false) {
            $err = $pdo->errorInfo();
            throw new Exception($err[2] ?? 'PDO error');
        }
        if (!empty($params)) {
            $st->execute($params);
        } else {
            $st->execute();
        }
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    
    throw new Exception('No se detectó conexión');
}

// Normalizadores de fecha =================================
function dt_start($d){
    $d = trim((string)$d);
    if($d==='') return null;
    return $d.' 00:00:00';
}
function dt_end($d){
    $d = trim((string)$d);
    if($d==='') return null;
    return $d.' 23:59:59';
}

$action = $_GET['action'] ?? 'generar';

// ======================= GENERAR REPORTE =======================
if ($action === 'generar') {
    // Verificar si hay errores capturados
    if (!empty($captured_errors)) {
        // Limpiar todo el output
        while (@ob_get_level() > 0) {
            @ob_end_flush();
            @ob_end_clean();
        }
        
        // Convertir error a JSON
        @header('Content-Type: application/json; charset=utf-8');
        $cleaned_error = strip_tags($captured_errors);
        $cleaned_error = preg_replace('/\s+/', ' ', $cleaned_error);
        $cleaned_error = html_entity_decode($cleaned_error, ENT_QUOTES, 'UTF-8');
        
        echo @json_encode([
            'ok' => false,
            'error' => 'Error detectado en el servidor: ' . substr($cleaned_error, 0, 500)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Limpiar output buffer antes de continuar
    @ob_clean();
    
    // Establecer header JSON solo para esta acción
    @header('Content-Type: application/json; charset=utf-8');
    @header('X-Content-Type-Options: nosniff');
    
    try {
        $filtro_evento = isset($_GET['evento']) && $_GET['evento'] !== '' ? (int)$_GET['evento'] : null;
        $filtro_desde = isset($_GET['desde']) && $_GET['desde'] !== '' ? dt_start($_GET['desde']) : null;
        $filtro_hasta = isset($_GET['hasta']) && $_GET['hasta'] !== '' ? dt_end($_GET['hasta']) : null;

        $where_conditions = [];
        $params = [];

        // Construir WHERE para boletos
        $where_boletos = "b.estatus = 1"; // Solo boletos activos/vendidos
        
        if ($filtro_evento) {
            $where_boletos .= " AND b.id_evento = ?";
            $params[] = $filtro_evento;
        }

        // Para fechas, necesitamos buscar en la tabla de funciones o eventos
        // Asumimos que hay una relación con fecha de venta o similar
        // Por ahora, filtramos por eventos que estén en el rango

        // ====== RESUMEN GENERAL ======
        $tbl_boletos = db_table('boletos');
        $sql_resumen = "
            SELECT 
                COUNT(*) as total_boletos,
                COALESCE(SUM(b.precio_final), 0) as total_vendido,
                COALESCE(SUM(b.descuento_aplicado), 0) as total_descuentos,
                COALESCE(AVG(b.precio_final), 0) as promedio_por_boleto
            FROM {$tbl_boletos} b
            WHERE $where_boletos
        ";
        
        $resumen = exec_query($sql_resumen, $params);
        $resumen = $resumen[0] ?? [
            'total_boletos' => 0,
            'total_vendido' => 0,
            'total_descuentos' => 0,
            'promedio_por_boleto' => 0
        ];

        // ====== VENTAS POR EVENTO ======
        // Verificar si existe columna fecha_compra
        $has_fecha_compra = false;
        try {
            $tbl_boletos_check = db_table('boletos');
            $check_fecha = exec_query("SHOW COLUMNS FROM {$tbl_boletos_check} LIKE 'fecha_compra'");
            $has_fecha_compra = !empty($check_fecha);
        } catch (Exception $e) {
            // Si falla la consulta, asumir que no existe la columna
            $has_fecha_compra = false;
        }
        
        $tbl_evento = db_table('evento');
        if ($has_fecha_compra) {
            $sql_ventas = "
                SELECT 
                    e.id_evento,
                    e.titulo,
                    DATE(b.fecha_compra) as fecha,
                    COUNT(*) as cantidad,
                    SUM(b.precio_final) as total,
                    SUM(b.descuento_aplicado) as descuentos
                FROM {$tbl_boletos} b
                INNER JOIN {$tbl_evento} e ON b.id_evento = e.id_evento
                WHERE $where_boletos
                GROUP BY e.id_evento, e.titulo, DATE(b.fecha_compra)
                ORDER BY fecha DESC, e.titulo ASC
            ";
        } else {
            // Si no hay fecha_compra, agrupar solo por evento
            $sql_ventas = "
                SELECT 
                    e.id_evento,
                    e.titulo,
                    e.inicio_venta as fecha,
                    COUNT(*) as cantidad,
                    SUM(b.precio_final) as total,
                    SUM(b.descuento_aplicado) as descuentos
                FROM {$tbl_boletos} b
                INNER JOIN {$tbl_evento} e ON b.id_evento = e.id_evento
                WHERE $where_boletos
                GROUP BY e.id_evento, e.titulo, e.inicio_venta
                ORDER BY e.inicio_venta DESC, e.titulo ASC
            ";
        }
        
        $ventas = exec_query($sql_ventas, $params);

        // ====== VENTAS POR CATEGORÍA ======
        $tbl_categorias = db_table('categorias');
        $sql_categorias = "
            SELECT 
                c.nombre_categoria,
                e.titulo as titulo_evento,
                COUNT(*) as cantidad,
                SUM(b.precio_final) as total
            FROM {$tbl_boletos} b
            INNER JOIN {$tbl_categorias} c ON b.id_categoria = c.id_categoria
            INNER JOIN {$tbl_evento} e ON b.id_evento = e.id_evento
            WHERE $where_boletos
            GROUP BY c.id_categoria, c.nombre_categoria, e.titulo
            ORDER BY total DESC
        ";
        
        $categorias = exec_query($sql_categorias, $params);

        // ====== DESCUENTOS APLICADOS ======
        $tbl_promociones = db_table('promociones');
        $sql_descuentos = "
            SELECT 
                p.nombre as nombre_promocion,
                e.titulo as titulo_evento,
                COUNT(*) as cantidad,
                SUM(b.descuento_aplicado) as total_descuento,
                AVG(b.descuento_aplicado) as promedio
            FROM {$tbl_boletos} b
            INNER JOIN {$tbl_promociones} p ON b.id_promocion = p.id_promocion
            INNER JOIN {$tbl_evento} e ON b.id_evento = e.id_evento
            WHERE $where_boletos AND b.id_promocion IS NOT NULL
            GROUP BY p.id_promocion, p.nombre, e.titulo
            ORDER BY total_descuento DESC
        ";
        
        $descuentos = exec_query($sql_descuentos, $params);

        // ====== OCUPACIÓN DE ASIENTOS ======
        // Contar asientos únicos por evento (basado en boletos vendidos)
        $sql_asientos = "
            SELECT 
                e.id_evento,
                e.titulo,
                COUNT(DISTINCT b.id_asiento) as total_asientos,
                COUNT(DISTINCT CASE WHEN b.estatus = 1 THEN b.id_asiento END) as vendidos
            FROM {$tbl_evento} e
            LEFT JOIN {$tbl_boletos} b ON b.id_evento = e.id_evento
        ";
        
        $params_asientos = [];
        if ($filtro_evento) {
            $sql_asientos .= " WHERE e.id_evento = ?";
            $params_asientos[] = $filtro_evento;
        }
        
        $sql_asientos .= " GROUP BY e.id_evento, e.titulo ORDER BY e.titulo ASC";
        
        $asientos = exec_query($sql_asientos, $params_asientos);
        
        // Si no hay asientos vendidos, mostrar total de asientos del mapa del evento
        // Esto requiere acceso al mapa_json, pero por ahora mostramos solo los vendidos

        // ====== EVENTOS PARA RESUMEN ======
        $sql_eventos = "
            SELECT 
                e.id_evento,
                e.titulo,
                COUNT(b.id_boleto) as total_boletos,
                COALESCE(SUM(b.precio_final), 0) as total_vendido,
                COALESCE(AVG(b.precio_final), 0) as promedio
            FROM {$tbl_evento} e
            LEFT JOIN {$tbl_boletos} b ON b.id_evento = e.id_evento AND b.estatus = 1
        ";
        
        $params_eventos = [];
        if ($filtro_evento) {
            $sql_eventos .= " WHERE e.id_evento = ?";
            $params_eventos[] = $filtro_evento;
        }
        
        $sql_eventos .= " GROUP BY e.id_evento, e.titulo ORDER BY total_vendido DESC";
        
        $eventos = exec_query($sql_eventos, $params_eventos);

        respond(true, [
            'data' => [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'ventas' => $ventas,
                'categorias' => $categorias,
                'descuentos' => $descuentos,
                'asientos' => $asientos
            ]
        ]);

    } catch (Exception $e) {
        // Limpiar cualquier output antes de responder
        if (ob_get_level()) {
            ob_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        respond(false, ['error' => $e->getMessage()]);
    } catch (Error $e) {
        // Capturar errores fatales de PHP 7+
        if (ob_get_level()) {
            ob_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        respond(false, ['error' => 'Error fatal: ' . $e->getMessage()]);
    }
}

// ======================= EXPORTAR PDF =======================
if ($action === 'export') {
    // Limpiar output buffer para exportación PDF
    while (@ob_get_level() > 0) {
        @ob_end_clean();
    }
    
    try {
        $filtro_evento = isset($_GET['evento']) && $_GET['evento'] !== '' ? (int)$_GET['evento'] : null;
        $filtro_desde = isset($_GET['desde']) && $_GET['desde'] !== '' ? dt_start($_GET['desde']) : null;
        $filtro_hasta = isset($_GET['hasta']) && $_GET['hasta'] !== '' ? dt_end($_GET['hasta']) : null;

        $where_conditions = "b.estatus = 1";
        $params = [];

        if ($filtro_evento) {
            $where_conditions .= " AND b.id_evento = ?";
            $params[] = $filtro_evento;
        }

        // Verificar si existe columna fecha_compra
        $has_fecha_compra = false;
        try {
            $check_fecha = exec_query("SHOW COLUMNS FROM boletos LIKE 'fecha_compra'");
            $has_fecha_compra = !empty($check_fecha);
        } catch (Exception $e) {
            // Si falla la consulta, asumir que no existe la columna
            $has_fecha_compra = false;
        }
        
        // Obtener datos del reporte completo
        $sql_detalle = "
            SELECT 
                e.titulo as evento,
                c.nombre_categoria as categoria,
                a.codigo_asiento as asiento,
                b.codigo_unico as codigo_boleto,
                b.precio_base,
                b.descuento_aplicado,
                b.precio_final,
                p.nombre as promocion,
                CONCAT(u.nombre, ' ', u.apellido) as vendedor,
                " . ($has_fecha_compra ? "DATE(b.fecha_compra)" : "DATE(e.inicio_venta)") . " as fecha_venta
            FROM boletos b
            INNER JOIN evento e ON b.id_evento = e.id_evento
            LEFT JOIN categorias c ON b.id_categoria = c.id_categoria
            LEFT JOIN asientos a ON b.id_asiento = a.id_asiento
            LEFT JOIN promociones p ON b.id_promocion = p.id_promocion
            LEFT JOIN usuarios u ON b.id_usuario = u.id_usuario
            WHERE $where_conditions
            ORDER BY fecha_venta DESC, e.titulo ASC
        ";

        $rows = exec_query($sql_detalle, $params);

        // Obtener resumen
        $sql_resumen = "
            SELECT 
                COUNT(*) as total_boletos,
                COALESCE(SUM(b.precio_final), 0) as total_vendido,
                COALESCE(SUM(b.descuento_aplicado), 0) as total_descuentos,
                COALESCE(AVG(b.precio_final), 0) as promedio_por_boleto
            FROM boletos b
            WHERE $where_conditions
        ";
        
        $resumen = exec_query($sql_resumen, $params);
        $resumen = $resumen[0] ?? [
            'total_boletos' => 0,
            'total_vendido' => 0,
            'total_descuentos' => 0,
            'promedio_por_boleto' => 0
        ];

        // Obtener nombre del evento si está filtrado
        $nombre_evento = "Todos los eventos";
        if ($filtro_evento) {
            $sql_evento = "SELECT titulo FROM evento WHERE id_evento = ?";
            $evento_data = exec_query($sql_evento, [$filtro_evento]);
            if (!empty($evento_data)) {
                $nombre_evento = $evento_data[0]['titulo'] ?? $nombre_evento;
            }
        }

        // Generar PDF usando TCPDF o alternativa simple
        generarPDF($rows, $resumen, $nombre_evento, $filtro_desde, $filtro_hasta);

    } catch (Exception $e) {
        // Si hay error al generar PDF, devolver JSON
        header('Content-Type: application/json; charset=utf-8');
        respond(false, ['error' => $e->getMessage()]);
    }
}

// ======================= FUNCIÓN GENERAR PDF =======================
function generarPDF($rows, $resumen, $nombre_evento, $fecha_desde, $fecha_hasta) {
    // Intentar usar TCPDF si está disponible
    $tcpdf_path = __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php';
    if (file_exists($tcpdf_path)) {
        require_once($tcpdf_path);
        generarPDF_TCPDF($rows, $resumen, $nombre_evento, $fecha_desde, $fecha_hasta);
        return;
    }
    
    // Intentar usar mPDF
    $mpdf_path = __DIR__ . '/../../vendor/mpdf/mpdf/src/Mpdf.php';
    if (file_exists($mpdf_path)) {
        require_once($mpdf_path);
        generarPDF_mPDF($rows, $resumen, $nombre_evento, $fecha_desde, $fecha_hasta);
        return;
    }
    
    // Intentar usar DomPDF
    $dompdf_path = __DIR__ . '/../../vendor/dompdf/dompdf/autoload.inc.php';
    if (file_exists($dompdf_path)) {
        require_once($dompdf_path);
        generarPDF_DomPDF($rows, $resumen, $nombre_evento, $fecha_desde, $fecha_hasta);
        return;
    }
    
    // Usar alternativa simple (HTML para imprimir)
    generarPDF_Simple($rows, $resumen, $nombre_evento, $fecha_desde, $fecha_hasta);
}

function generarPDF_TCPDF($rows, $resumen, $nombre_evento, $fecha_desde, $fecha_hasta) {
    // Limpiar cualquier output buffer antes de generar PDF
    while (@ob_get_level() > 0) {
        @ob_end_clean();
    }
    
    // Definir constantes si no existen
    if (!defined('PDF_PAGE_ORIENTATION')) define('PDF_PAGE_ORIENTATION', 'P');
    if (!defined('PDF_UNIT')) define('PDF_UNIT', 'mm');
    if (!defined('PDF_PAGE_FORMAT')) define('PDF_PAGE_FORMAT', 'A4');
    
    // Crear instancia de TCPDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Configuración del documento
    $pdf->SetCreator('Sistema de Teatro');
    $pdf->SetAuthor('Sistema de Teatro');
    $pdf->SetTitle('Reporte de Ventas');
    $pdf->SetSubject('Reporte de Ventas');
    
    // Eliminar header y footer por defecto
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Agregar página
    $pdf->AddPage();
    
    // Estilos
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'REPORTE DE VENTAS', 0, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Ln(5);
    $pdf->Cell(0, 5, 'Evento: ' . $nombre_evento, 0, 1);
    if ($fecha_desde) {
        $pdf->Cell(0, 5, 'Desde: ' . date('d/m/Y', strtotime($fecha_desde)), 0, 1);
    }
    if ($fecha_hasta) {
        $pdf->Cell(0, 5, 'Hasta: ' . date('d/m/Y', strtotime($fecha_hasta)), 0, 1);
    }
    $pdf->Cell(0, 5, 'Fecha de generación: ' . date('d/m/Y H:i:s'), 0, 1);
    
    $pdf->Ln(5);
    
    // Resumen
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'RESUMEN', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    
    $pdf->Cell(0, 6, 'Total de boletos vendidos: ' . number_format($resumen['total_boletos'], 0), 0, 1);
    $pdf->Cell(0, 6, 'Total vendido: $' . number_format($resumen['total_vendido'], 2), 0, 1);
    $pdf->Cell(0, 6, 'Total descuentos: $' . number_format($resumen['total_descuentos'], 2), 0, 1);
    $pdf->Cell(0, 6, 'Promedio por boleto: $' . number_format($resumen['promedio_por_boleto'], 2), 0, 1);
    
    $pdf->Ln(5);
    
    // Tabla de detalles
    if (!empty($rows)) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'DETALLE DE VENTAS', 0, 1);
        $pdf->SetFont('helvetica', '', 8);
        
        // Encabezados de tabla
        $pdf->SetFillColor(200, 200, 200);
        $pdf->Cell(35, 6, 'Evento', 1, 0, 'L', true);
        $pdf->Cell(25, 6, 'Categoría', 1, 0, 'L', true);
        $pdf->Cell(18, 6, 'Asiento', 1, 0, 'C', true);
        $pdf->Cell(25, 6, 'Código', 1, 0, 'L', true);
        $pdf->Cell(22, 6, 'Precio', 1, 0, 'R', true);
        $pdf->Cell(35, 6, 'Vendedor', 1, 0, 'L', true);
        $pdf->Cell(20, 6, 'Fecha', 1, 1, 'C', true);
        
        $pdf->SetFillColor(245, 245, 245);
        $fill = false;
        
        foreach ($rows as $row) {
            $pdf->Cell(35, 6, substr($row['evento'] ?? '', 0, 18), 1, 0, 'L', $fill);
            $pdf->Cell(25, 6, substr($row['categoria'] ?? '—', 0, 12), 1, 0, 'L', $fill);
            $pdf->Cell(18, 6, $row['asiento'] ?? '—', 1, 0, 'C', $fill);
            $pdf->Cell(25, 6, substr($row['codigo_boleto'] ?? '', 0, 10), 1, 0, 'L', $fill);
            $pdf->Cell(22, 6, '$' . number_format($row['precio_final'] ?? 0, 2), 1, 0, 'R', $fill);
            $pdf->Cell(35, 6, substr($row['vendedor'] ?? 'Sin asignar', 0, 18), 1, 0, 'L', $fill);
            $pdf->Cell(20, 6, date('d/m/y', strtotime($row['fecha_venta'] ?? 'now')), 1, 1, 'C', $fill);
            $fill = !$fill;
        }
    } else {
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 10, 'No hay datos para mostrar', 0, 1, 'C');
    }
    
    // Salida del PDF
    $pdf->Output('reporte_ventas_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

function generarPDF_Simple($rows, $resumen, $nombre_evento, $fecha_desde, $fecha_hasta) {
    // Limpiar cualquier output buffer antes de generar HTML
    while (@ob_get_level() > 0) {
        @ob_end_clean();
    }
    
    // Generar HTML para imprimir como PDF
    $html = generarHTMLReporte($rows, $resumen, $nombre_evento, $fecha_desde, $fecha_hasta);
    
    // Mostrar HTML con opción de imprimir como PDF
    @header('Content-Type: text/html; charset=utf-8');
    echo $html;
    echo '<script>window.print();</script>';
    exit;
}

function generarHTMLReporte($rows, $resumen, $nombre_evento, $fecha_desde, $fecha_hasta) {
    // Crear contenido HTML para el PDF con diseño mejorado
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte de Ventas</title>
    <style>
        @page {
            margin: 15mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: #2c3e50;
            line-height: 1.6;
            font-size: 11pt;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            margin: -15mm -15mm 20px -15mm;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 28pt;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .header .subtitle {
            font-size: 12pt;
            opacity: 0.95;
            font-weight: 300;
        }
        
        .info-section {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 8px;
        }
        
        .info-section p {
            margin: 5px 0;
            font-size: 10pt;
        }
        
        .info-section strong {
            color: #667eea;
            font-weight: 600;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .stat-label {
            font-size: 9pt;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .stat-value {
            font-size: 20pt;
            font-weight: 700;
            color: #667eea;
            line-height: 1.2;
        }
        
        .stat-value.success {
            color: #28a745;
        }
        
        .stat-value.warning {
            color: #ffc107;
        }
        
        .stat-value.info {
            color: #17a2b8;
        }
        
        .section-title {
            font-size: 16pt;
            font-weight: 700;
            color: #2c3e50;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
        }
        
        table { 
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 20px 0;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        th { 
            color: white;
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 10pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td { 
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            font-size: 10pt;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        tbody tr:nth-child(even) { 
            background: #f8f9fa;
        }
        
        tbody tr:hover {
            background: #e7f1ff;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 9pt;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
            text-align: center;
            font-size: 9pt;
            color: #6c757d;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }
        
        @media print {
            .header {
                margin: 0;
                page-break-after: avoid;
            }
            
            .stat-card {
                page-break-inside: avoid;
            }
            
            table {
                page-break-inside: auto;
            }
            
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            
            thead {
                display: table-header-group;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>REPORTE DE VENTAS</h1>
        <div class="subtitle">Sistema de Gestión de Teatro</div>
    </div>
    
    <div class="info-section">
        <p><strong>Evento:</strong> ' . htmlspecialchars($nombre_evento) . '</p>';
    
    if ($fecha_desde) {
        $html .= '<p><strong>Período desde:</strong> ' . date('d/m/Y', strtotime($fecha_desde)) . '</p>';
    }
    if ($fecha_hasta) {
        $html .= '<p><strong>Período hasta:</strong> ' . date('d/m/Y', strtotime($fecha_hasta)) . '</p>';
    }
    
    $html .= '<p><strong>Fecha de generación:</strong> ' . date('d/m/Y H:i:s') . '</p>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Boletos Vendidos</div>
            <div class="stat-value success">' . number_format($resumen['total_boletos'], 0) . '</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">$' . number_format($resumen['total_vendido'], 2) . '</div>
            <div class="stat-label">Total Vendido</div>
        </div>
        <div class="stat-card">
            <div class="stat-value warning">$' . number_format($resumen['total_descuentos'], 2) . '</div>
            <div class="stat-label">Descuentos</div>
        </div>
        <div class="stat-card">
            <div class="stat-value info">$' . number_format($resumen['promedio_por_boleto'], 2) . '</div>
            <div class="stat-label">Promedio/Boleto</div>
        </div>
    </div>
    
    <h2 class="section-title">Detalle de Ventas</h2>
    <table>
        <thead>
            <tr>
                <th>Evento</th>
                <th>Categoría</th>
                <th class="text-center">Asiento</th>
                <th>Código Boleto</th>
                <th class="text-right">Precio Final</th>
                <th>Vendedor</th>
                <th class="text-center">Fecha</th>
            </tr>
        </thead>
        <tbody>';
    
    if (!empty($rows)) {
        $total_general = 0;
        foreach ($rows as $row) {
            $precio = $row['precio_final'] ?? 0;
            $total_general += $precio;
            
            $html .= '<tr>
                <td><strong>' . htmlspecialchars(substr($row['evento'] ?? '—', 0, 30)) . '</strong></td>
                <td><span class="badge badge-info">' . htmlspecialchars($row['categoria'] ?? '—') . '</span></td>
                <td class="text-center"><strong>' . htmlspecialchars($row['asiento'] ?? '—') . '</strong></td>
                <td style="font-family: monospace; font-size: 9pt;">' . htmlspecialchars($row['codigo_boleto'] ?? '—') . '</td>
                <td class="text-right"><strong>$' . number_format($precio, 2) . '</strong></td>
                <td>' . htmlspecialchars($row['vendedor'] ?? 'Sin asignar') . '</td>
                <td class="text-center">' . (isset($row['fecha_venta']) ? date('d/m/Y', strtotime($row['fecha_venta'])) : '—') . '</td>
            </tr>';
        }
        
        // Fila de total
        $html .= '<tr style="background: #e7f1ff; font-weight: bold;">
            <td colspan="5" class="text-right" style="padding: 15px;">TOTAL GENERAL:</td>
            <td class="text-right" style="font-size: 12pt; color: #667eea; padding: 15px;">$' . number_format($total_general, 2) . '</td>
            <td></td>
        </tr>';
    } else {
        $html .= '<tr><td colspan="7" class="empty-state">📭 No hay datos para mostrar en este período</td></tr>';
    }
    
    $html .= '</tbody>
    </table>
    
    <div class="footer">
        <p>Reporte generado automáticamente por el Sistema de Gestión de Teatro</p>
        <p>© ' . date('Y') . ' - Todos los derechos reservados</p>
    </div>
</body>
</html>';
    
    return $html;
}

function generarPDF_mPDF($rows, $resumen, $nombre_evento, $fecha_desde, $fecha_hasta) {
    // Limpiar cualquier output buffer antes de generar PDF
    while (@ob_get_level() > 0) {
        @ob_end_clean();
    }
    
    $html = generarHTMLReporte($rows, $resumen, $nombre_evento, $fecha_desde, $fecha_hasta);
    
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15
    ]);
    
    $mpdf->WriteHTML($html);
    $mpdf->Output('reporte_ventas_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

function generarPDF_DomPDF($rows, $resumen, $nombre_evento, $fecha_desde, $fecha_hasta) {
    // Limpiar cualquier output buffer antes de generar PDF
    while (@ob_get_level() > 0) {
        @ob_end_clean();
    }
    
    $html = generarHTMLReporte($rows, $resumen, $nombre_evento, $fecha_desde, $fecha_hasta);
    
    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream('reporte_ventas_' . date('Y-m-d') . '.pdf', ['Attachment' => true]);
    exit;
}

// Si llegamos aquí, la acción no es válida
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
if (ob_get_level()) {
    ob_clean();
}
respond(false, ['error' => 'Acción no válida']);