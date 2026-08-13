<?php
header('Content-Type: application/json');
require_once '../../evt_interfaz/conexion.php';

// Validar sesión
session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    // Parámetros
    $db_mode = $_GET['db'] ?? 'actual'; // actual, historico, ambas
    $id_evento = isset($_GET['id_evento']) && $_GET['id_evento'] !== '' ? (int)$_GET['id_evento'] : null;
    $fecha_desde = $_GET['fecha_desde'] ?? null;
    $fecha_hasta = $_GET['fecha_hasta'] ?? null;

    $db_actual = 'trt_25';
    $db_historico = 'trt_historico_evento';

    // Construcción de Query Dinámico
    // Necesitamos consultar 'boletos' y 'transacciones' (para ventas)
    // Para simplificar y rendimiento, en el dashboard nos enfocamos en BOLETOS VENDIDOS (que es lo que genera dinero)
    // Tabla: boletos. Estado: 'vendido'.

    $queries = [];
    
    // Función helper para armar subqueries
    $build_query = function($db_name) use ($id_evento, $fecha_desde, $fecha_hasta) {
        $where = ["estatus = 'vendido'"];
        
        if ($id_evento) {
            $where[] = "id_evento = $id_evento";
        }
        if ($fecha_desde) {
            $where[] = "fecha_compra >= '$fecha_desde 00:00:00'";
        }
        if ($fecha_hasta) {
            $where[] = "fecha_compra <= '$fecha_hasta 23:59:59'";
        }
        
        $where_sql = implode(' AND ', $where);
        return "SELECT id_boleto, precio_final, fecha_compra, id_evento, id_categoria FROM {$db_name}.boletos WHERE $where_sql";
    };

    if ($db_mode === 'actual' || $db_mode === 'ambas') {
        $queries[] = $build_query($db_actual);
    }
    if ($db_mode === 'historico' || $db_mode === 'ambas') {
        $queries[] = $build_query($db_historico);
    }

    $union_sql = implode(" UNION ALL ", $queries);
    
    // --- 1. RESUMEN GENERAL (Total Ingresos, Boletos, Promedio) ---
    // Usamos el UNION como una tabla derivada
    $sql_resumen = "SELECT 
                        COUNT(*) as total_boletos, 
                        SUM(precio_final) as total_ingresos,
                        AVG(precio_final) as ticket_promedio,
                        COUNT(DISTINCT id_evento) as eventos_activos
                    FROM ($union_sql) as alias_main";
    
    $resumen_data = $conn->query($sql_resumen)->fetch_assoc();

    // --- 2. VENTAS POR HORA (Bar Chart) ---
    $sql_hora = "SELECT HOUR(fecha_compra) as hora, SUM(precio_final) as ingresos 
                 FROM ($union_sql) as alias_hora 
                 GROUP BY HOUR(fecha_compra) 
                 ORDER BY hora";
    $res_hora = $conn->query($sql_hora);
    $data_hora = [];
    while($r = $res_hora->fetch_assoc()) $data_hora[] = $r;

    // --- 3. POR CATEGORIA (Doughnut Chart) ---
    // Para obtener nombres de categorías necesitamos unir con la tabla categorías de cada BD...
    // Esto es complejo con UNION. Simplifiquemos: obtenemos IDs y sus conteos, y luego buscamos nombres.
    // O mejor: hacemos un JOIN dentro de cada parte del UNION si fuera necesario, pero 'categorias' también puede variar.
    // ESTRATEGIA: Obtener conteo por id_categoria y luego buscar nombres en la BD actual (asumiendo consistencia) o hacer un map.
    // Para 'ambas', es mejor consultar la categoría.
    
    // Query optimizado:
    $queries_cat = [];
    $build_cat_query = function($db_name) use ($id_evento, $fecha_desde, $fecha_hasta) {
        $where = ["b.estatus = 'vendido'"];
        if ($id_evento) $where[] = "b.id_evento = $id_evento";
        if ($fecha_desde) $where[] = "b.fecha_compra >= '$fecha_desde 00:00:00'";
        if ($fecha_hasta) $where[] = "b.fecha_compra <= '$fecha_hasta 23:59:59'";
        $where_sql = implode(' AND ', $where);

        return "SELECT c.nombre_categoria, b.precio_final 
                FROM {$db_name}.boletos b 
                JOIN {$db_name}.categorias c ON b.id_categoria = c.id_categoria 
                WHERE $where_sql";
    };

    if ($db_mode === 'actual' || $db_mode === 'ambas') $queries_cat[] = $build_cat_query($db_actual);
    if ($db_mode === 'historico' || $db_mode === 'ambas') $queries_cat[] = $build_cat_query($db_historico);

    $sql_cat = "SELECT nombre_categoria, COUNT(*) as cantidad, SUM(precio_final) as total 
                FROM (" . implode(" UNION ALL ", $queries_cat) . ") as alias_cat 
                GROUP BY nombre_categoria 
                ORDER BY cantidad DESC";
    
    $res_cat = $conn->query($sql_cat);
    $data_cat = [];
    while($r = $res_cat->fetch_assoc()) $data_cat[] = $r;

    // --- 4. TOP EVENTOS (Table) ---
    // Similar strategy to categories
    $queries_ev = [];
    $build_ev_query = function($db_name) use ($id_evento, $fecha_desde, $fecha_hasta) {
        $where = ["b.estatus = 'vendido'"];
        if ($id_evento) $where[] = "b.id_evento = $id_evento";
        if ($fecha_desde) $where[] = "b.fecha_compra >= '$fecha_desde 00:00:00'";
        if ($fecha_hasta) $where[] = "b.fecha_compra <= '$fecha_hasta 23:59:59'";
        $where_sql = implode(' AND ', $where);

        return "SELECT e.titulo, b.precio_final 
                FROM {$db_name}.boletos b 
                JOIN {$db_name}.evento e ON b.id_evento = e.id_evento 
                WHERE $where_sql";
    };

    if ($db_mode === 'actual' || $db_mode === 'ambas') $queries_ev[] = $build_ev_query($db_actual);
    if ($db_mode === 'historico' || $db_mode === 'ambas') $queries_ev[] = $build_ev_query($db_historico);

    $sql_ev = "SELECT titulo, COUNT(*) as cantidad, SUM(precio_final) as total 
               FROM (" . implode(" UNION ALL ", $queries_ev) . ") as alias_ev 
               GROUP BY titulo 
               ORDER BY total DESC 
               LIMIT 10";
    
    $res_ev = $conn->query($sql_ev);
    $data_ev = [];
    while($r = $res_ev->fetch_assoc()) $data_ev[] = $r;

    // --- 5. POR TIPO DE BOLETO (Pie Chart) ---
    $queries_type = [];
    $build_type_query = function($db_name) use ($id_evento, $fecha_desde, $fecha_hasta) {
        $where = ["estatus = 'vendido'"];
        if ($id_evento) $where[] = "id_evento = $id_evento";
        if ($fecha_desde) $where[] = "fecha_compra >= '$fecha_desde 00:00:00'";
        if ($fecha_hasta) $where[] = "fecha_compra <= '$fecha_hasta 23:59:59'";
        $where_sql = implode(' AND ', $where);

        return "SELECT tipo_boleto, precio_final FROM {$db_name}.boletos WHERE $where_sql";
    };

    if ($db_mode === 'actual' || $db_mode === 'ambas') $queries_type[] = $build_type_query($db_actual);
    if ($db_mode === 'historico' || $db_mode === 'ambas') $queries_type[] = $build_type_query($db_historico);

    $sql_type = "SELECT tipo_boleto, COUNT(*) as cantidad, SUM(precio_final) as total 
                 FROM (" . implode(" UNION ALL ", $queries_type) . ") as alias_type 
                 GROUP BY tipo_boleto 
                 ORDER BY cantidad DESC";
    
    $res_type = $conn->query($sql_type);
    $data_type = [];
    while($r = $res_type->fetch_assoc()) $data_type[] = $r;


    // OUTPUT
    echo json_encode([
        'success' => true,
        'data' => [
            'resumen' => [
                'total_ingresos' => $resumen_data['total_ingresos'],
                'total_boletos' => $resumen_data['total_boletos'],
                'ticket_promedio' => $resumen_data['ticket_promedio'],
                'eventos_activos' => $resumen_data['eventos_activos']
            ],
            'por_hora' => $data_hora,
            'por_categoria' => $data_cat,
            'por_tipo' => $data_type,
            'por_evento' => $data_ev
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
