<?php
/**
 * API ESTADÍSTICAS PREMIUM
 * Proporciona métricas completas para el dashboard
 */

header('Content-Type: application/json');
require_once '../../evt_interfaz/conexion.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    // Parámetros
    $db_mode = $_GET['db'] ?? 'actual';
    $id_evento = isset($_GET['id_evento']) && $_GET['id_evento'] !== '' ? (int)$_GET['id_evento'] : null;
    $id_funcion = isset($_GET['id_funcion']) && $_GET['id_funcion'] !== '' ? (int)$_GET['id_funcion'] : null;
    $fecha_desde = $_GET['fecha_desde'] ?? null;
    $fecha_hasta = $_GET['fecha_hasta'] ?? null;

    $db_actual = 'trt_25';
    $db_historico = 'trt_historico_evento';

    $historico_disponible = false;
    $check_hist = $conn->query("SHOW DATABASES LIKE '{$db_historico}'");
    if ($check_hist && $check_hist->num_rows > 0) {
        $check_boletos = $conn->query("SHOW TABLES FROM {$db_historico} LIKE 'boletos'");
        $historico_disponible = $check_boletos && $check_boletos->num_rows > 0;
    }

    // Determinar qué bases de datos usar
    $databases = [];
    if ($db_mode === 'ambas') {
        $databases[] = $db_actual;
        if ($historico_disponible) {
            $databases[] = $db_historico;
        }
    } elseif ($db_mode === 'historico') {
        if ($historico_disponible) {
            $databases[] = $db_historico;
        }
    } else {
        $databases[] = $db_actual;
    }

    if (empty($databases)) {
        $databases[] = $db_actual;
    }

    // HELPER: Construir WHERE clause para boletos
    $buildWhere = function($prefix = 'b', $statusField = 'estatus', $statusValue = "1") use ($id_evento, $id_funcion, $fecha_desde, $fecha_hasta) {
        $where = ["{$prefix}.{$statusField} = {$statusValue}"];
        if ($id_evento) $where[] = "{$prefix}.id_evento = {$id_evento}";
        if ($id_funcion) $where[] = "{$prefix}.id_funcion = {$id_funcion}";
        if ($fecha_desde) $where[] = "{$prefix}.fecha_compra >= '{$fecha_desde} 00:00:00'";
        if ($fecha_hasta) $where[] = "{$prefix}.fecha_compra <= '{$fecha_hasta} 23:59:59'";
        return implode(' AND ', $where);
    };

    // ============================================
    // 1. RESUMEN GENERAL (KPIs principales)
    // ============================================
    $queries_resumen = [];
    foreach ($databases as $db) {
        $where = $buildWhere('b');
        $queries_resumen[] = "SELECT id_boleto, precio_final, fecha_compra, id_evento, id_funcion, id_categoria FROM {$db}.boletos b WHERE {$where}";
    }
    $union_resumen = implode(" UNION ALL ", $queries_resumen);

    $sql_resumen = "SELECT 
        COUNT(*) as total_boletos,
        COALESCE(SUM(precio_final), 0) as total_ingresos,
        COALESCE(AVG(precio_final), 0) as ticket_promedio,
        COUNT(DISTINCT id_evento) as total_eventos,
        COUNT(DISTINCT id_funcion) as total_funciones
    FROM ({$union_resumen}) as main";
    
    $resumen = $conn->query($sql_resumen)->fetch_assoc();

    // ============================================
    // 2. OCUPACIÓN DEL TEATRO
    // ============================================
    // 2a. Ocupación por función
    $ocupacion_funcion = [];
    foreach ($databases as $db) {
        // En histórico no filtrar por estado ya que todas están finalizadas
        $whereClause = "WHERE 1=1";
        // if ($db !== $db_historico) $whereClause .= " AND f.estado = 1"; // DISABLED as per fix
        
        if ($id_evento) $whereClause .= " AND f.id_evento = $id_evento";
        if ($id_funcion) $whereClause .= " AND f.id_funcion = $id_funcion"; // Filter by Function

        // CORRECCIÓN: Capacidad dinámica basada en tipo de evento (1=420, 2=540)
        $sql_ocupacion = "
            SELECT 
                f.id_funcion,
                e.titulo as evento,
                DATE_FORMAT(f.fecha_hora, '%d/%m/%Y') as fecha,
                TIME_FORMAT(f.fecha_hora, '%H:%i') as hora,
                (SELECT COUNT(*) FROM {$db}.boletos b WHERE b.id_funcion = f.id_funcion AND b.estatus = 1) as vendidos,
                CASE 
                    WHEN e.tipo = 2 THEN 540 
                    ELSE 420 
                END as capacidad
            FROM {$db}.funciones f
            JOIN {$db}.evento e ON f.id_evento = e.id_evento
            {$whereClause}
            ORDER BY f.fecha_hora DESC
            LIMIT 20
        ";
        $res = $conn->query($sql_ocupacion);
        if ($res) while ($row = $res->fetch_assoc()) {
            $capacidad = (int)$row['capacidad'];
            $vendidos = (int)$row['vendidos'];
            $porcentaje = $capacidad > 0 ? ($vendidos / $capacidad) * 100 : 0;
            $row['porcentaje'] = round($porcentaje, 2); // 2 decimales solicitado
            $ocupacion_funcion[] = $row;
        }
    }
    usort($ocupacion_funcion, fn($a, $b) => $b['porcentaje'] <=> $a['porcentaje']);

    // 2b. Ocupación por día de la semana
    $queries_dia = [];
    foreach ($databases as $db) {
        $where = $buildWhere('b');
        $queries_dia[] = "SELECT DAYOFWEEK(fecha_compra) as dia_num, precio_final FROM {$db}.boletos b WHERE {$where}";
    }
    $sql_dia = "SELECT 
        dia_num,
        CASE dia_num 
            WHEN 1 THEN 'Domingo' WHEN 2 THEN 'Lunes' WHEN 3 THEN 'Martes' 
            WHEN 4 THEN 'Miércoles' WHEN 5 THEN 'Jueves' WHEN 6 THEN 'Viernes' WHEN 7 THEN 'Sábado'
        END as dia,
        COUNT(*) as cantidad,
        SUM(precio_final) as ingresos
    FROM (" . implode(" UNION ALL ", $queries_dia) . ") as d
    GROUP BY dia_num ORDER BY dia_num";
    
    $por_dia = [];
    $res = $conn->query($sql_dia);
    if ($res) while ($row = $res->fetch_assoc()) $por_dia[] = $row;

    // 2c. Ocupación por horario (matiné, tarde, noche)
    $queries_horario = [];
    foreach ($databases as $db) {
        $where = $buildWhere('b');
        $queries_horario[] = "SELECT HOUR(fecha_compra) as hora, precio_final FROM {$db}.boletos b WHERE {$where}";
    }
    $sql_horario = "SELECT 
        CASE 
            WHEN hora >= 6 AND hora < 12 THEN 'Mañana (6-12h)'
            WHEN hora >= 12 AND hora < 18 THEN 'Tarde (12-18h)'
            ELSE 'Noche (18-6h)'
        END as franja,
        COUNT(*) as cantidad,
        SUM(precio_final) as ingresos
    FROM (" . implode(" UNION ALL ", $queries_horario) . ") as h
    GROUP BY franja ORDER BY MIN(hora)";
    
    $por_horario = [];
    $res = $conn->query($sql_horario);
    if ($res) while ($row = $res->fetch_assoc()) $por_horario[] = $row;

    // ============================================
    // 3. INGRESOS DETALLADOS
    // ============================================
    // Por mes
    $queries_periodo = [];
    foreach ($databases as $db) {
        $where = $buildWhere('b');
        $queries_periodo[] = "SELECT DATE(fecha_compra) as fecha, precio_final FROM {$db}.boletos b WHERE {$where}";
    }

    $sql_mes_ingresos = "SELECT DATE_FORMAT(fecha, '%Y-%m') as mes, SUM(precio_final) as ingresos, COUNT(*) as boletos
        FROM (" . implode(" UNION ALL ", $queries_periodo) . ") as p
        GROUP BY mes ORDER BY mes DESC LIMIT 12";
    $ingresos_mensuales = [];
    $res = $conn->query($sql_mes_ingresos);
    if ($res) while ($row = $res->fetch_assoc()) $ingresos_mensuales[] = $row;

    // Por tipo de boleto
    $queries_tipo = [];
    foreach ($databases as $db) {
        $where = $buildWhere('b');
        $queries_tipo[] = "SELECT COALESCE(tipo_boleto, 'Normal') as tipo, precio_final FROM {$db}.boletos b WHERE {$where}";
    }
    $sql_tipo = "SELECT tipo, COUNT(*) as cantidad, SUM(precio_final) as ingresos
        FROM (" . implode(" UNION ALL ", $queries_tipo) . ") as t
        GROUP BY tipo ORDER BY ingresos DESC";
    $por_tipo = [];
    $res = $conn->query($sql_tipo);
    if ($res) while ($row = $res->fetch_assoc()) $por_tipo[] = $row;

    // Por categoría
    $por_categoria = [];
    foreach ($databases as $db) {
        $where = $buildWhere('b');
        $sql_cat = "SELECT c.nombre_categoria, COUNT(*) as cantidad, SUM(b.precio_final) as ingresos
            FROM {$db}.boletos b 
            JOIN {$db}.categorias c ON b.id_categoria = c.id_categoria
            WHERE {$where}
            GROUP BY c.nombre_categoria";
        $res = $conn->query($sql_cat);
        if ($res) while ($row = $res->fetch_assoc()) {
            $found = false;
            foreach ($por_categoria as &$existing) {
                if ($existing['nombre_categoria'] === $row['nombre_categoria']) {
                    $existing['cantidad'] += $row['cantidad'];
                    $existing['ingresos'] += $row['ingresos'];
                    $found = true;
                    break;
                }
            }
            if (!$found) $por_categoria[] = $row;
        }
    }
    usort($por_categoria, fn($a, $b) => $b['ingresos'] <=> $a['ingresos']);

    // ============================================
    // 4. RENDIMIENTO DE OBRAS (EVENTOS)
    // ============================================
    $ranking_eventos = [];
    foreach ($databases as $db) {
        $where = $buildWhere('b');
        $sql_eventos = "SELECT e.titulo, COUNT(*) as boletos, SUM(b.precio_final) as ingresos, COUNT(DISTINCT b.id_funcion) as funciones
            FROM {$db}.boletos b 
            JOIN {$db}.evento e ON b.id_evento = e.id_evento
            WHERE {$where}
            GROUP BY e.titulo";
        $res = $conn->query($sql_eventos);
        if ($res) while ($row = $res->fetch_assoc()) {
            $found = false;
            foreach ($ranking_eventos as &$existing) {
                if ($existing['titulo'] === $row['titulo']) {
                    $existing['boletos'] += $row['boletos'];
                    $existing['ingresos'] += $row['ingresos'];
                    $existing['funciones'] = max($existing['funciones'], $row['funciones']);
                    $found = true;
                    break;
                }
            }
            if (!$found) $ranking_eventos[] = $row;
        }
    }
    usort($ranking_eventos, fn($a, $b) => $b['ingresos'] <=> $a['ingresos']);
    $rank = 1;
    foreach ($ranking_eventos as &$ev) {
        $ev['rank'] = $rank++;
    }

    // Alertas: eventos con baja asistencia (< 30% de capacidad)
    $alertas_eventos = array_filter($ocupacion_funcion, fn($f) => $f['porcentaje'] < 30);

    // ============================================
    // 5. TENDENCIAS EN EL TIEMPO
    // ============================================
    $queries_hora = [];
    foreach ($databases as $db) {
        $where = $buildWhere('b');
        $queries_hora[] = "SELECT HOUR(fecha_compra) as hora, precio_final FROM {$db}.boletos b WHERE {$where}";
    }
    $sql_hora = "SELECT hora, COUNT(*) as cantidad, SUM(precio_final) as ingresos
        FROM (" . implode(" UNION ALL ", $queries_hora) . ") as h
        GROUP BY hora ORDER BY hora";
    $por_hora = [];
    $res = $conn->query($sql_hora);
    if ($res) while ($row = $res->fetch_assoc()) $por_hora[] = $row;

    // ============================================
    // 6. VENTAS DE BOLETOS - Ocupación general (REAL)
    // ============================================
    // Calcular capacidad TOTAL de TODAS las funciones (sin LIMIT) para el KPI global
    $capacidad_total_real = 0;
    
    // ============================================
    // 6. VENTAS DE BOLETOS - Ocupación general (REAL)
    // ============================================
    // Calcular capacidad TOTAL de TODAS las funciones (sin LIMIT) para el KPI global
    $capacidad_total_real = 0;
    
    // ============================================
    // 6. VENTAS DE BOLETOS - Ocupación general (REAL)
    // ============================================
    // Calcular capacidad TOTAL de TODAS las funciones (sin LIMIT, sin Filtro Fecha) para el KPI global
    $capacidad_total_real = 0;
    
    // ============================================
    // 6. VENTAS DE BOLETOS - Ocupación general (REAL)
    // ============================================
    // Calcular capacidad TOTAL de TODAS las funciones (sin LIMIT, sin Filtro Fecha) para el KPI global
    $capacidad_total_real = 0;
    
    // Calcular Total Vendidos GLOBAL (sin Filtro Fecha) para que coincida con la capacidad
    $vendidos_total_global = 0;

    foreach ($databases as $db) {
        $qryEvento = "";
        $qryEventoBol = "";
        
        // Filtro por Evento
        if ($id_evento) {
            $qryEvento .= " AND f.id_evento = {$id_evento}";
            $qryEventoBol .= " AND b.id_evento = {$id_evento}";
        }
        // Filtro por Funcion
        if ($id_funcion) {
            $qryEvento .= " AND f.id_funcion = {$id_funcion}";
            $qryEventoBol .= " AND b.id_funcion = {$id_funcion}";
        }
        
        // 1. Capacidad Global
        $sql_cap_global = "
            SELECT SUM(CASE WHEN e.tipo = 2 THEN 540 ELSE 420 END) as cap_total
            FROM {$db}.funciones f
            JOIN {$db}.evento e ON f.id_evento = e.id_evento
            WHERE 1=1 {$qryEvento}
        ";
        $res_cap = $conn->query($sql_cap_global);
        if ($res_cap && $row_cap = $res_cap->fetch_assoc()) {
             $capacidad_total_real += (int)$row_cap['cap_total'];
        }

        // 2. Vendidos Global
        $sql_vend_global = "SELECT COUNT(*) as total_vend FROM {$db}.boletos b WHERE estatus = 1 {$qryEventoBol}";
        $res_vend = $conn->query($sql_vend_global);
        if ($res_vend && $row_vend = $res_vend->fetch_assoc()) {
            $vendidos_total_global += (int)$row_vend['total_vend'];
        }
    }
    
    // FÓRMULA: (100 / Total_Asientos) * Vendidos Globales
    $porcentaje_ocupacion_global = 0;
    if ($capacidad_total_real > 0) {
        $porcentaje_ocupacion_global = ($vendidos_total_global / $capacidad_total_real) * 100;
    }
    
    $porcentaje_ocupacion_global = round($porcentaje_ocupacion_global, 2);

    $ventas_resumen = [
        'vendidos' => (int)$resumen['total_boletos'], // Este respeta filtros de fecha (KPI Boletos)
        'capacidad' => $capacidad_total_real,
        'porcentaje_ocupacion' => $porcentaje_ocupacion_global
    ];

    // ============================================
    // 7. TOP VENDEDORES
    // ============================================
    $top_vendedores = [];
    $usuarios_db = $db_actual;
    foreach ($databases as $db) {
        $where = $buildWhere('b');
        $sql_vendedores = "SELECT COALESCE(CONCAT(u.nombre, ' ', u.apellido), 'Sistema') as vendedor, 
            COUNT(*) as boletos, SUM(b.precio_final) as ingresos
            FROM {$db}.boletos b 
            LEFT JOIN {$usuarios_db}.usuarios u ON b.id_usuario = u.id_usuario
            WHERE {$where}
            GROUP BY vendedor";
        $res = $conn->query($sql_vendedores);
        if ($res) while ($row = $res->fetch_assoc()) {
            $found = false;
            foreach ($top_vendedores as &$existing) {
                if ($existing['vendedor'] === $row['vendedor']) {
                    $existing['boletos'] += $row['boletos'];
                    $existing['ingresos'] += $row['ingresos'];
                    $found = true;
                    break;
                }
            }
            if (!$found) $top_vendedores[] = $row;
        }
    }
    usort($top_vendedores, fn($a, $b) => $b['ingresos'] <=> $a['ingresos']);
    $top_vendedores = array_slice($top_vendedores, 0, 10);

    // ============================================
    // 8. LISTA DE EVENTOS (para el dropdown de filtros)
    // ============================================
    $eventos_lista = [];
    foreach ($databases as $db) {
        $fuente = ($db === $db_historico) ? 'historico' : 'actual';
        
        // Cargar TODOS los eventos de cada base de datos
        $sql_eventos = "SELECT id_evento, titulo, '{$fuente}' as fuente FROM {$db}.evento ORDER BY id_evento DESC";
        $res = $conn->query($sql_eventos);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                // Evitar duplicados
                $exists = false;
                foreach ($eventos_lista as $e) {
                    if ($e['id_evento'] == $row['id_evento'] && $e['titulo'] == $row['titulo']) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) $eventos_lista[] = $row;
            }
        }
    }

    // ============================================
    // RESPUESTA FINAL
    // ============================================
    echo json_encode([
        'success' => true,
        'data' => [
            'resumen' => [
                'total_ingresos' => (float)$resumen['total_ingresos'],
                'total_boletos' => (int)$resumen['total_boletos'],
                'ticket_promedio' => (float)$resumen['ticket_promedio'],
                'total_eventos' => (int)$resumen['total_eventos'],
                'total_funciones' => (int)$resumen['total_funciones']
            ],
            'ocupacion' => [
                'por_funcion' => array_slice($ocupacion_funcion, 0, 10),
                'por_dia' => $por_dia,
                'por_horario' => $por_horario,
                'general' => $ventas_resumen
            ],
            'ingresos' => [
                'mensuales' => array_reverse($ingresos_mensuales),
                'por_tipo' => $por_tipo,
                'por_categoria' => $por_categoria
            ],
            'rendimiento' => [
                'ranking' => array_slice($ranking_eventos, 0, 10),
                'alertas' => array_slice(array_values($alertas_eventos), 0, 5)
            ],
            'tendencias' => [
                'por_hora' => $por_hora
            ],
            'ventas' => [
                'resumen' => $ventas_resumen
            ],
            'vendedores' => $top_vendedores,
            'eventos_filtro' => $eventos_lista
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
