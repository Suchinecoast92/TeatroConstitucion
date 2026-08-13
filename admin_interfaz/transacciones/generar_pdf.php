<?php
/**
 * REPORTE DETALLADO DE ESTADÍSTICAS - VERSIÓN DARK PREMIUM
 * Replica la lógica de api_stats_premium.php y genera un diseño oscuro optimizado para PDF.
 */

// 1. CONFIGURACIÓN Y ACCESO
require_once '../../evt_interfaz/conexion.php';
session_start();
if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado. Por favor inicie sesión.");
}

// 2. PARÁMETROS DE FILTRO
$db_mode = $_GET['db'] ?? 'actual';
$id_evento = isset($_GET['id_evento']) && $_GET['id_evento'] !== '' ? (int)$_GET['id_evento'] : null;
$id_funcion = isset($_GET['id_funcion']) && $_GET['id_funcion'] !== '' ? (int)$_GET['id_funcion'] : null;
$fecha_desde = $_GET['fecha_desde'] ?? null;
$fecha_hasta = $_GET['fecha_hasta'] ?? null;

// 3. TÍTULO Y CONTEXTO
$titulo_reporte = "Reporte General de Ventas";
$nombre_evento = "Todos los Eventos";

if ($id_evento) {
    // Buscar nombre del evento en DB actual o historica
    $stmt = $conn->prepare("SELECT titulo FROM evento WHERE id_evento = ?");
    $stmt->bind_param("i", $id_evento);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $nombre_evento = $row['titulo'];
        $titulo_reporte = "Reporte de Evento";
    } else {
        // Intentar en histórico
        $check_hist = $conn->query("SHOW DATABASES LIKE 'trt_historico_evento'");
        if ($check_hist && $check_hist->num_rows > 0) { 
             $res_h = $conn->query("SELECT titulo FROM trt_historico_evento.evento WHERE id_evento = $id_evento");
             if ($res_h && $r_h = $res_h->fetch_assoc()) {
                 $nombre_evento = $r_h['titulo'];
                 $titulo_reporte = "Reporte de Evento (Histórico)";
             }
        }
    }
}

// 4. LÓGICA DE DATOS
$db_actual = 'trt_25';
$db_historico = 'trt_historico_evento';
$usuarios_db = 'trt_25'; 

// Verificar histórico
$historico_disponible = false;
$check_hist = $conn->query("SHOW DATABASES LIKE '{$db_historico}'");
if ($check_hist && $check_hist->num_rows > 0) {
    $check_boletos = $conn->query("SHOW TABLES FROM {$db_historico} LIKE 'boletos'");
    $historico_disponible = $check_boletos && $check_boletos->num_rows > 0;
}

// Seleccionar DBs
$databases = [];
if ($db_mode === 'ambas') {
    $databases[] = $db_actual;
    if ($historico_disponible) $databases[] = $db_historico;
} elseif ($db_mode === 'historico') {
    if ($historico_disponible) $databases[] = $db_historico;
} else {
    $databases[] = $db_actual;
}
if (empty($databases)) $databases[] = $db_actual;

// Helper WHERE (usando estatus=1)
// Capture $id_funcion in 'use' statement
$buildWhere = function($prefix = 'b', $statusField = 'estatus', $statusValue = "1") use ($id_evento, $id_funcion, $fecha_desde, $fecha_hasta) {
    $where = ["{$prefix}.{$statusField} = {$statusValue}"];
    if ($id_evento) $where[] = "{$prefix}.id_evento = {$id_evento}";
    if ($id_funcion) $where[] = "{$prefix}.id_funcion = {$id_funcion}"; // Add function filter
    if ($fecha_desde) $where[] = "{$prefix}.fecha_compra >= '{$fecha_desde} 00:00:00'";
    if ($fecha_hasta) $where[] = "{$prefix}.fecha_compra <= '{$fecha_hasta} 23:59:59'";
    return implode(' AND ', $where);
};

// --- A. RESUMEN GENERAL (KPIs) ---
$queries_resumen = [];
foreach ($databases as $db) {
    $where = $buildWhere('b');
    $queries_resumen[] = "SELECT precio_final, id_evento, id_funcion FROM {$db}.boletos b WHERE {$where}";
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

// --- B.  POR CATEGORÍA ---
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
                $found = true; break;
            }
        }
        if (!$found) $por_categoria[] = $row;
    }
}
usort($por_categoria, fn($a, $b) => $b['ingresos'] <=> $a['ingresos']);

// --- C. POR TIPO DE BOLETO ---
$queries_tipo = [];
foreach ($databases as $db) {
    $where = $buildWhere('b');
    $queries_tipo[] = "SELECT COALESCE(tipo_boleto, 'Normal') as tipo, precio_final FROM {$db}.boletos b WHERE {$where}";
}
$sql_tipo = "SELECT tipo, COUNT(*) as cantidad, SUM(precio_final) as ingresos
    FROM (" . implode(" UNION ALL ", $queries_tipo) . ") as t
    GROUP BY tipo ORDER BY ingresos DESC";
$res = $conn->query($sql_tipo);
$por_tipo = [];
if ($res) while ($row = $res->fetch_assoc()) $por_tipo[] = $row;

// --- D. TOP VENDEDORES ---
$top_vendedores = [];
foreach ($databases as $db) {
    $where = $buildWhere('b');
    $sql_vend = "SELECT COALESCE(CONCAT(u.nombre, ' ', u.apellido), 'Sistema') as vendedor, 
        COUNT(*) as boletos, SUM(b.precio_final) as ingresos
        FROM {$db}.boletos b 
        LEFT JOIN {$usuarios_db}.usuarios u ON b.id_usuario = u.id_usuario
        WHERE {$where}
        GROUP BY vendedor";
    $res = $conn->query($sql_vend);
    if ($res) while ($row = $res->fetch_assoc()) {
        $found = false;
        foreach ($top_vendedores as &$existing) {
            if ($existing['vendedor'] === $row['vendedor']) {
                $existing['boletos'] += $row['boletos'];
                $existing['ingresos'] += $row['ingresos'];
                $found = true; break;
            }
        }
        if (!$found) $top_vendedores[] = $row;
    }
}
usort($top_vendedores, fn($a, $b) => $b['ingresos'] <=> $a['ingresos']);

// --- E. VENTAS POR DÍA ---
$queries_dia = [];
foreach ($databases as $db) {
    $where = $buildWhere('b');
    $queries_dia[] = "SELECT DAYOFWEEK(fecha_compra) as dia_num, precio_final FROM {$db}.boletos b WHERE {$where}";
}
$sql_dia = "SELECT 
    CASE dia_num 
        WHEN 1 THEN 'Domingo' WHEN 2 THEN 'Lunes' WHEN 3 THEN 'Martes' 
        WHEN 4 THEN 'Miércoles' WHEN 5 THEN 'Jueves' WHEN 6 THEN 'Viernes' WHEN 7 THEN 'Sábado'
    END as dia,
    COUNT(*) as cantidad, SUM(precio_final) as ingresos
FROM (" . implode(" UNION ALL ", $queries_dia) . ") as d
GROUP BY dia_num, dia ORDER BY dia_num"; 
$res = $conn->query($sql_dia);
$por_dia = [];
if ($res) while ($row = $res->fetch_assoc()) $por_dia[] = $row;


$id_funcion = isset($_GET['id_funcion']) && $_GET['id_funcion'] !== '' ? (int)$_GET['id_funcion'] : null;

// ... (existing code) ...

// --- F. OCUPACIÓN REAL (SIN FILTRO DE FECHAS) ---
$ocupacion_real = 0;
$capacidad_total = 0;
$vendidos_total_global = 0; // Para ocupación real global

foreach ($databases as $db) {
    $qryEvento = "";
    $qryEventoBol = "";
    
    if ($id_evento) {
        $qryEvento .= " AND f.id_evento = {$id_evento}";
        $qryEventoBol .= " AND id_evento = {$id_evento}";
    }
    if ($id_funcion) {
        $qryEvento .= " AND f.id_funcion = {$id_funcion}";
        $qryEventoBol .= " AND id_funcion = {$id_funcion}";
    }

    // 1. Capacidad Global (Sin filtro de fecha)
    $sql_cap = "SELECT e.tipo FROM {$db}.funciones f JOIN {$db}.evento e ON f.id_evento = e.id_evento WHERE 1=1 {$qryEvento}";
    $res_cap = $conn->query($sql_cap);
    if ($res_cap) {
        while ($r = $res_cap->fetch_assoc()) {
            $capacidad_total += ($r['tipo'] == 2) ? 540 : 420;
        }
    }
    
    // 2. Vendidos Global (Sin filtro de fecha, solo Evento/Funcion)
    $sql_vend = "SELECT COUNT(*) as total FROM {$db}.boletos WHERE estatus = 1 {$qryEventoBol}";
    $res_vend = $conn->query($sql_vend);
    if ($res_vend && $row = $res_vend->fetch_assoc()) {
        $vendidos_total_global += (int)$row['total'];
    }
}

$ocupacion_real = ($capacidad_total > 0) ? round(($vendidos_total_global / $capacidad_total) * 100, 2) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Dark Mode: <?php echo htmlspecialchars($nombre_evento); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* BASE DARK THEME */
        :root {
            --bg-color: #0f172a; /* Slate 900 */
            --card-color: #1e293b; /* Slate 800 */
            --text-main: #f8fafc; /* Slate 50 */
            --text-muted: #94a3b8; /* Slate 400 */
            --border-color: #334155; /* Slate 700 */
            --accent-color: #6366f1; /* Indigo 500 */
            --success-color: #10b981; /* Emerald 500 */
            --danger-color: #ef4444; /* Red 500 */
        }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg-color); 
            color: var(--text-main); 
            margin: 0;
            padding: 30px;
            font-size: 12px;
            -webkit-print-color-adjust: exact; 
            print-color-adjust: exact;
        }

        h1 { font-size: 24px; font-weight: 800; text-align: center; margin-bottom: 5px; color: var(--text-main); letter-spacing: -0.5px; }
        h2 { font-size: 16px; font-weight: 700; margin-top: 25px; margin-bottom: 15px; color: var(--accent-color); border-bottom: 1px solid var(--border-color); padding-bottom: 5px; }
        
        .header-meta { text-align: center; color: var(--text-muted); font-size: 11px; margin-bottom: 30px; }
        .badge-dark { background: var(--card-color); border: 1px solid var(--border-color); padding: 4px 10px; border-radius: 20px; color: var(--text-main); margin: 0 5px; }

        /* KPIS */
        .kpi-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 30px; }
        .kpi-card { 
            background-color: var(--card-color); 
            border: 1px solid var(--border-color);
            border-radius: 12px; 
            padding: 15px; 
            text-align: center; 
        }
        .kpi-label { font-size: 10px; text-transform: uppercase; color: var(--text-muted); font-weight: 600; letter-spacing: 0.5px; margin-bottom: 6px; }
        .kpi-value { font-size: 22px; font-weight: 700; color: var(--text-main); }
        .text-success { color: var(--success-color); }
        
        /* TABLES */
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; }
        th { 
            background-color: #273548; 
            color: var(--text-muted); 
            text-transform: uppercase; 
            font-size: 10px; 
            font-weight: 600; 
            padding: 12px; 
            text-align: left; 
            border-bottom: 2px solid var(--border-color);
        }
        td { 
            padding: 12px; 
            background-color: var(--card-color); 
            border-bottom: 1px solid var(--border-color); 
            color: var(--text-main);
        }
        tr:last-child td { border-bottom: none; }
        .text-right { text-align: right; }
        
        /* BARS */
        .progress-bg { background: rgba(255,255,255,0.1); border-radius: 4px; height: 6px; width: 80px; display: inline-block; overflow: hidden; vertical-align: middle; }
        .progress-bar { height: 100%; background: var(--accent-color); }
        .progress-bar.green { background: var(--success-color); }
        
        /* LAYOUT */
        .row { display: flex; gap: 20px; }
        .col { flex: 1; }
        
        /* PRINT SPECIFICS */
        @media print {
            @page { margin: 10mm; background-color: #0f172a; }
            body { 
                background-color: #0f172a !important; 
                -webkit-print-color-adjust: exact !important; 
                print-color-adjust: exact !important;
                color: white !important;
            }
            .no-print { display: none !important; }
            .kpi-card, td { 
                background-color: #1e293b !important; 
                border-color: #334155 !important; 
            }
            th { background-color: #273548 !important; color: #94a3b8 !important; }
            /* Force dark mode printing */
        }

        .btn-print {
            position: fixed; bottom: 30px; right: 30px;
            background: var(--accent-color); color: white;
            padding: 12px 24px; border-radius: 50px;
            font-weight: 600; border: none; cursor: pointer;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
            display: flex; align-items: center; gap: 8px;
            z-index: 100; transition: transform 0.2s;
        }
        .btn-print:hover { transform: scale(1.05); }
    </style>
</head>
<body>

    <button onclick="downloadAndPrint()" class="no-print btn-print">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        Descargar / Imprimir
    </button>

    <h1><?php echo htmlspecialchars($titulo_reporte); ?></h1>
    <div class="header-meta">
        <span class="badge-dark">📅 <?php echo $fecha_desde ? date('d/m/Y', strtotime($fecha_desde)) : 'Inicio'; ?> al <?php echo $fecha_hasta ? date('d/m/Y', strtotime($fecha_hasta)) : 'Hoy'; ?></span>
        <span class="badge-dark">🎭 <?php echo htmlspecialchars($nombre_evento); ?></span>
        <span class="badge-dark">💾 <?php echo ucfirst($db_mode); ?> Database</span>
        <span class="badge-dark">⏰ Generado: <?php echo date('d/m/Y H:i'); ?></span>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Ingresos Totales</div>
            <div class="kpi-value text-success">$<?php echo number_format($resumen['total_ingresos'], 2); ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Boletos Vendidos</div>
            <div class="kpi-value"><?php echo number_format($resumen['total_boletos']) . ' / ' . number_format($capacidad_total); ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Ticket Promedio</div>
            <div class="kpi-value">$<?php echo number_format($resumen['ticket_promedio'], 2); ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Ocupación Real</div>
            <div class="kpi-value"><?php echo $ocupacion_real; ?>%</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Funciones</div>
            <div class="kpi-value"><?php echo number_format($resumen['total_funciones']); ?></div>
        </div>
    </div>

    <div class="row">
        <!-- CATEGORÍAS -->
        <div class="col">
            <h2>Ingresos por Categoría</h2>
            <table>
                <thead>
                    <tr>
                        <th>Categoría</th>
                        <th class="text-right">Boletos</th>
                        <th class="text-right">Ingresos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $max_ingreso = 0;
                    foreach ($por_categoria as $c) $max_ingreso = max($max_ingreso, $c['ingresos']);
                    
                    foreach ($por_categoria as $cat): 
                        $pct = $max_ingreso > 0 ? ($cat['ingresos'] / $max_ingreso) * 100 : 0;
                    ?>
                    <tr>
                        <td>
                            <div style="margin-bottom: 4px;"><?php echo htmlspecialchars($cat['nombre_categoria']); ?></div>
                            <div class="progress-bg"><div class="progress-bar" style="width: <?php echo $pct; ?>%;"></div></div>
                        </td>
                        <td class="text-right"><?php echo number_format($cat['cantidad']); ?></td>
                        <td class="text-right text-success">$<?php echo number_format($cat['ingresos'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($por_categoria)): ?>
                    <tr><td colspan="3" style="text-align:center; color: var(--text-muted);">Sin datos</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- TIPO DE BOLETO -->
        <div class="col">
            <h2>Tipo de Boleto y Días</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th class="text-right">Cant.</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($por_tipo as $tipo): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($tipo['tipo']); ?></td>
                        <td class="text-right"><?php echo number_format($tipo['cantidad']); ?></td>
                        <td class="text-right text-success">$<?php echo number_format($tipo['ingresos'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <table style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th>Día Semana</th>
                        <th class="text-right">Ventas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($por_dia as $d): ?>
                    <tr>
                        <td><?php echo $d['dia']; ?></td>
                        <td class="text-right"><?php echo number_format($d['cantidad']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TOP VENDEDORES -->
    <h2>Ranking de Vendedores</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 40px;">#</th>
                <th>Vendedor</th>
                <th class="text-right">Boletos</th>
                <th class="text-right">Ingresos</th>
                <th>Participación</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $i = 1;
            foreach (array_slice($top_vendedores, 0, 10) as $vend): 
                $pct_total = $resumen['total_ingresos'] > 0 ? ($vend['ingresos'] / $resumen['total_ingresos']) * 100 : 0;
            ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo htmlspecialchars($vend['vendedor']); ?></td>
                <td class="text-right"><?php echo number_format($vend['boletos']); ?></td>
                <td class="text-right text-success" style="font-weight: 700;">$<?php echo number_format($vend['ingresos'], 2); ?></td>
                <td>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="font-size:10px; min-width:30px;"><?php echo round($pct_total, 1); ?>%</span>
                        <div class="progress-bg"><div class="progress-bar green" style="width: <?php echo $pct_total; ?>%;"></div></div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid var(--border-color); text-align: center; color: var(--text-muted); font-size: 10px;">
        Generado automáticamente por Sistema Teatro - <?php echo date('Y'); ?>
    </div>

    <!-- html2pdf Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <!-- QZ Tray Libraries -->
    <script src="../../vnt_interfaz/js/qz-tray.js"></script>
    <script src="../../vnt_interfaz/js/qz_interface.js"></script>

    <script>
        // Data from PHP
        const reportData = {
            titulo: <?php echo json_encode($nombre_evento); ?>,
            rango: "<?php echo $fecha_desde ? date('d/m/Y', strtotime($fecha_desde)) : 'Inicio'; ?> al <?php echo $fecha_hasta ? date('d/m/Y', strtotime($fecha_hasta)) : 'Hoy'; ?>",
            generado: "<?php echo date('d/m/Y H:i'); ?>",
            resumen: {
                ingresos: "<?php echo number_format($resumen['total_ingresos'], 2); ?>",
                boletos: "<?php echo number_format($resumen['total_boletos']); ?>",
                capacidad: "<?php echo number_format($capacidad_total); ?>",
                promedio: "<?php echo number_format($resumen['ticket_promedio'], 2); ?>",
                ocupacion: "<?php echo $ocupacion_real; ?>%"
            },
            categorias: <?php echo json_encode($por_categoria); ?>,
            tipos: <?php echo json_encode($por_tipo); ?>,
            dias: <?php echo json_encode($por_dia); ?>,
            vendedores: <?php echo json_encode(array_slice($top_vendedores, 0, 5)); ?>
        };

        async function printReportQZ() {
             if (!await initQZ()) {
                 alert("No se pudo conectar a QZ Tray.");
                 return;
             }
             
             // Construir HTML para ticket (80mm ~ 300-320px safe area)
             // Ajustado a 260px para evitar cortes por márgenes de impresora
             let html = `
                <div style="font-family: Arial, Helvetica, sans-serif; width: 260px; margin: 0 auto; font-size: 10px; color: #000; font-weight: 900 !important; line-height: 1.1;">
                    <!-- HEADER -->
                    <div style="text-align: center; margin-bottom: 8px;">
                        <div style="font-size: 14px; font-weight: 900; text-transform: uppercase;">REPORTE DE VENTAS</div>
                        <div style="font-size: 12px; margin-top: 4px; overflow-wrap: break-word;">${reportData.titulo}</div>
                        <div style="margin-top: 4px; font-size: 10px;">${reportData.rango}</div>
                        <div style="font-size: 9px; margin-top: 2px;">Gen: ${reportData.generado}</div>
                    </div>
                    
                    <div style="border-bottom: 2px solid #000; margin: 8px 0;"></div>
                    
                    <!-- RESUMEN -->
                    <div style="font-size: 12px; margin-bottom: 4px; text-transform: uppercase; border-bottom: 1px dashed #000;">RESUMEN</div>
                    <table style="width: 100%; border-collapse: collapse; font-weight: 900;">
                        <tr><td style="padding: 1px 0;">Ingresos:</td><td align="right" style="padding: 1px 0;">$${reportData.resumen.ingresos}</td></tr>
                        <tr><td style="padding: 1px 0;">Boletos:</td><td align="right" style="padding: 1px 0;">${reportData.resumen.boletos} / ${reportData.resumen.capacidad}</td></tr>
                        <tr><td style="padding: 1px 0;">Promedio:</td><td align="right" style="padding: 1px 0;">$${reportData.resumen.promedio}</td></tr>
                        <tr><td style="padding: 1px 0;">Ocupación:</td><td align="right" style="padding: 1px 0;">${reportData.resumen.ocupacion}</td></tr>
                    </table>
                    
                    <div style="border-bottom: 2px solid #000; margin: 8px 0;"></div>
                    
                    <!-- CATEGORIAS -->
                    <div style="font-size: 12px; margin-bottom: 4px; text-transform: uppercase; border-bottom: 1px dashed #000;">POR CATEGORÍA</div>
                    <table style="width: 100%; font-size: 10px; font-weight: 900;">
                        ${reportData.categorias.map(c => `
                            <tr>
                                <td style="padding: 1px 0; width: 40%; white-space: nowrap; overflow: hidden;">${c.nombre_categoria.substring(0, 13)}</td>
                                <td align="right" style="padding: 1px 0; width: 20%;">${c.cantidad}</td>
                                <td align="right" style="padding: 1px 0; width: 40%;">$${parseFloat(c.ingresos).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                            </tr>
                        `).join('')}
                    </table>

                    <div style="border-bottom: 2px solid #000; margin: 8px 0;"></div>

                    <!-- TIPOS DE BOLETO -->
                    <div style="font-size: 12px; margin-bottom: 4px; text-transform: uppercase; border-bottom: 1px dashed #000;">POR TIPO</div>
                    <table style="width: 100%; font-size: 10px; font-weight: 900;">
                        ${reportData.tipos.map(t => `
                            <tr>
                                <td style="padding: 1px 0; width: 40%; white-space: nowrap; overflow: hidden;">${t.tipo.substring(0, 13)}</td>
                                <td align="right" style="padding: 1px 0; width: 20%;">${t.cantidad}</td>
                                <td align="right" style="padding: 1px 0; width: 40%;">$${parseFloat(t.ingresos).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                            </tr>
                        `).join('')}
                    </table>

                    <div style="border-bottom: 2px solid #000; margin: 8px 0;"></div>

                    <!-- POR DIA -->
                    <div style="font-size: 12px; margin-bottom: 4px; text-transform: uppercase; border-bottom: 1px dashed #000;">POR DÍA</div>
                    <table style="width: 100%; font-size: 10px; font-weight: 900;">
                        ${reportData.dias.map(d => `
                            <tr>
                                <td style="padding: 1px 0;">${d.dia.substring(0, 3)}</td>
                                <td align="right" style="padding: 1px 0;">${d.cantidad}</td>
                                <td align="right" style="padding: 1px 0;">-</td>
                            </tr>
                        `).join('')}
                    </table>

                     <div style="border-bottom: 2px solid #000; margin: 8px 0;"></div>
                    
                    <!-- VENDEDORES -->
                    <div style="font-size: 12px; margin-bottom: 4px; text-transform: uppercase; border-bottom: 1px dashed #000;">VENDEDORES</div>
                    <table style="width: 100%; font-size: 10px; font-weight: 900;">
                        ${reportData.vendedores.map(v => `
                            <tr>
                                <td style="padding: 1px 0; width: 35%; white-space: nowrap; overflow: hidden;">${v.vendedor.substring(0, 10)}</td>
                                <td align="right" style="padding: 1px 0; width: 20%;">${v.boletos}</td>
                                <td align="right" style="padding: 1px 0; width: 45%;">$${parseFloat(v.ingresos).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                            </tr>
                        `).join('')}
                    </table>
                    
                    <div style="margin-top: 15px; text-align: center; font-size: 9px; font-weight: 900;">
                        *** FIN DE REPORTE ***
                    </div>
                     <div style="height: 30px;"></div>
                </div>
             `;
             
             // Enviar a QZ usando lógica similar a printTicketsQZ pero con nuestro HTML
             try {
                // Fallback printer logic reused from qz_interface.js or just calling qz directly
                let printers = await qz.printers.find();
                let printerName = printers.find(p => p.includes('POS') || p.includes('Epson') || p.includes('Thermal')) || printers[0];
                
                let config = qz.configs.create(printerName, { rasterize: true, altPrinting: true });
                let data = [{ type: 'pixel', format: 'html', flavor: 'plain', data: html }];
                
                await qz.print(config, data);
             } catch(e) {
                 console.error(e);
                 alert("Error imprimiendo: " + e);
             }
        }

        function downloadAndPrint() {
            const btn = document.querySelector('.btn-print');
            btn.style.display = 'none'; 
            
            const element = document.body;
            const opt = {
                margin: 5,
                filename: 'Reporte_Ventas_<?php echo date('Ymd_Hi'); ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, backgroundColor: '#0f172a' },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            html2pdf().set(opt).from(element).save().then(() => {
                btn.style.display = 'flex';
                
                setTimeout(() => {
                    // Preguntar por impresión en TICKET (QZ)
                    if (confirm("¿Desea imprimir el reporte en TICKET (impresora térmica)?")) {
                        printReportQZ();
                    } 
                    // Si dice NO, podríamos preguntar por impresión normal, o dejarlo así.
                    // Usuario pidió "QUE NO IMPRIMA SOLO PREGUNTE" (anterior) y "HAZ QUE SI IMPRIMA USANDO QZ" (actual).
                    // Asumimos que la opción de impresión ES la de QZ.
                }, 1000);
            });
        }
    </script>
</body>
</html>
