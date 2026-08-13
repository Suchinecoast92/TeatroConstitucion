<?php
/**
 * Dashboard de Estadísticas para el Dueño del Negocio
 * Soporta datos actuales (trt_25), históricos (trt_historico_evento), o ambos
 */

include "../../evt_interfaz/conexion.php";

// Detectar qué base de datos usar
$db_mode = $_GET['db'] ?? 'ambas';
$db_actual = 'trt_25';
$db_historico = 'trt_historico_evento';

// Configurar label y modo
if ($db_mode === 'ambas') {
    $db_label = 'Datos Combinados (Actual + Histórico)';
} elseif ($db_mode === 'historico') {
    $db_label = 'Datos Históricos';
} else {
    $db_label = 'Datos Actuales';
}

// Funciones helper
function query_db($conn, $sql) {
    $result = $conn->query($sql);
    if (!$result) return [];
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function query_value($conn, $sql, $default = 0) {
    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) return $default;
    $row = $result->fetch_row();
    return $row[0] ?? $default;
}

// Helper para construir consultas según el modo
function get_boletos_from($db_mode, $db_actual, $db_historico) {
    if ($db_mode === 'ambas') {
        return "(SELECT * FROM {$db_actual}.boletos UNION ALL SELECT * FROM {$db_historico}.boletos)";
    }
    $db = ($db_mode === 'historico') ? $db_historico : $db_actual;
    return "{$db}.boletos";
}

function get_evento_from($db_mode, $db_actual, $db_historico) {
    if ($db_mode === 'ambas') {
        return "(SELECT * FROM {$db_actual}.evento UNION ALL SELECT * FROM {$db_historico}.evento)";
    }
    $db = ($db_mode === 'historico') ? $db_historico : $db_actual;
    return "{$db}.evento";
}

function get_categorias_from($db_mode, $db_actual, $db_historico) {
    if ($db_mode === 'ambas') {
        return "(SELECT * FROM {$db_actual}.categorias UNION SELECT * FROM {$db_historico}.categorias)";
    }
    $db = ($db_mode === 'historico') ? $db_historico : $db_actual;
    return "{$db}.categorias";
}

$tbl_boletos = get_boletos_from($db_mode, $db_actual, $db_historico);
$tbl_evento = get_evento_from($db_mode, $db_actual, $db_historico);
$tbl_categorias = get_categorias_from($db_mode, $db_actual, $db_historico);

// ============= KPIs =============

// 1. Ingresos totales
$total_ingresos = query_value($conn, "
    SELECT COALESCE(SUM(precio_final), 0) FROM {$tbl_boletos} b WHERE estatus = 1
");

// 2. Total boletos vendidos
$total_boletos = query_value($conn, "
    SELECT COUNT(*) FROM {$tbl_boletos} b WHERE estatus = 1
");

// 3. Total eventos
$total_eventos = query_value($conn, "
    SELECT COUNT(*) FROM {$tbl_evento} e
");

// 4. Eventos con ventas
$eventos_con_ventas = query_value($conn, "
    SELECT COUNT(DISTINCT id_evento) FROM {$tbl_boletos} b WHERE estatus = 1
");

// 5. Promedio por evento (solo eventos con ventas)
$promedio_por_evento = ($eventos_con_ventas > 0) 
    ? $total_ingresos / $eventos_con_ventas 
    : 0;

// 6. Total descuentos otorgados
$total_descuentos = query_value($conn, "
    SELECT COALESCE(SUM(descuento_aplicado), 0) FROM {$tbl_boletos} b WHERE estatus = 1
");

// 7. Precio promedio real por boleto
$precio_promedio = ($total_boletos > 0) ? $total_ingresos / $total_boletos : 0;

// 8. Evento más vendido
$evento_top = query_db($conn, "
    SELECT e.titulo, COUNT(b.id_boleto) as ventas, SUM(b.precio_final) as ingresos
    FROM {$tbl_boletos} b
    JOIN {$tbl_evento} e ON b.id_evento = e.id_evento
    WHERE b.estatus = 1
    GROUP BY e.titulo
    ORDER BY ventas DESC
    LIMIT 1
");
$evento_top_nombre = $evento_top[0]['titulo'] ?? 'N/A';
$evento_top_ventas = $evento_top[0]['ventas'] ?? 0;

// 9. Top 5 eventos por ventas (para gráfico)
$top_eventos = query_db($conn, "
    SELECT e.id_evento, e.titulo, COUNT(b.id_boleto) as ventas, SUM(b.precio_final) as ingresos
    FROM {$tbl_boletos} b
    JOIN {$tbl_evento} e ON b.id_evento = e.id_evento
    WHERE b.estatus = 1
    GROUP BY e.id_evento, e.titulo
    ORDER BY ingresos DESC
    LIMIT 5
");

// Asignar colores a los eventos
$event_colors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#0ea5e9', '#ec4899', '#8b5cf6', '#14b8a6', '#f97316', '#06b6d4'];
foreach ($top_eventos as $i => &$evento) {
    $evento['color'] = $event_colors[$i % count($event_colors)];
}
unset($evento);

// 10. Ventas por categoría (para gráfico)
$ventas_categoria = query_db($conn, "
    SELECT c.nombre_categoria, COUNT(b.id_boleto) as ventas, SUM(b.precio_final) as ingresos, c.color
    FROM {$tbl_boletos} b
    JOIN {$tbl_categorias} c ON b.id_categoria = c.id_categoria
    WHERE b.estatus = 1
    GROUP BY c.nombre_categoria, c.color
    ORDER BY ingresos DESC
    LIMIT 6
");

// 11. Ventas por tipo de boleto
$ventas_tipo = query_db($conn, "
    SELECT tipo_boleto, COUNT(*) as cantidad, SUM(precio_final) as ingresos
    FROM {$tbl_boletos} b
    WHERE estatus = 1
    GROUP BY tipo_boleto
    ORDER BY cantidad DESC
");

// 12. Resumen por evento (tabla)
$resumen_eventos = query_db($conn, "
    SELECT e.id_evento, e.titulo, e.finalizado,
           COUNT(b.id_boleto) as boletos,
           COALESCE(SUM(b.precio_final), 0) as ingresos,
           COALESCE(SUM(b.descuento_aplicado), 0) as descuentos
    FROM {$tbl_evento} e
    LEFT JOIN {$tbl_boletos} b ON e.id_evento = b.id_evento AND b.estatus = 1
    GROUP BY e.id_evento, e.titulo, e.finalizado
    ORDER BY ingresos DESC
");

// Asignar colores a resumen_eventos también
foreach ($resumen_eventos as $i => &$evento) {
    $evento['color'] = $event_colors[$i % count($event_colors)];
}
unset($evento);

// Preparar datos para JS
$top_eventos_json = json_encode($top_eventos, JSON_UNESCAPED_UNICODE);
$ventas_categoria_json = json_encode($ventas_categoria, JSON_UNESCAPED_UNICODE);
$ventas_tipo_json = json_encode($ventas_tipo, JSON_UNESCAPED_UNICODE);

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard · Estadísticas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --primary: #1561f0;
            --primary-dark: #0d4fc4;
            --success: #32d74b;
            --danger: #ff453a;
            --warning: #ff9f0a;
            --info: #64d2ff;
            --bg-main: #131313;
            --bg-card: #1c1c1e;
            --bg-input: #2b2b2b;
            --text-primary: #ffffff;
            --text-secondary: #86868b;
            --border: #3a3a3c;
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,.4);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,.5);
            --radius-md: 12px;
            --radius-lg: 16px;
        }
        
        * { box-sizing: border-box; }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg-main);
            color: var(--text-primary);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container-fluid { max-width: 1400px; margin: 0 auto; }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .page-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .page-header h2 i { color: var(--primary); }
        
        .db-badge {
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .db-badge.actual {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }
        
        .db-badge.historico {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning);
        }
        
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            transition: all 0.2s ease;
        }
        
        .card:hover { box-shadow: var(--shadow-lg); }
        
        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .kpi-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 20px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }
        
        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        
        .kpi-content { flex: 1; min-width: 0; }
        
        .kpi-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .kpi-value {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .kpi-detail {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        
        /* Gráficos */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .chart-card {
            padding: 20px;
        }
        
        .chart-card h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #e2e8f0;
        }
        
        .chart-card h3 i { color: var(--primary); }
        
        .chart-container {
            position: relative;
            height: 280px;
        }
        
        canvas {
            background: transparent !important;
            border: none !important;
        }
        
        /* Tabla */
        .table-card { padding: 20px; }
        
        .table-card h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #e2e8f0;
        }
        
        .table-card h3 i { color: var(--primary); }
        
        .table {
            color: var(--text-primary) !important;
            margin: 0;
            background: transparent !important;
        }
        
        .table thead { 
            background: var(--bg-input) !important;
        }
        
        .table th {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--text-secondary) !important;
            border-color: var(--border);
            padding: 10px 12px;
            background: var(--bg-input) !important;
        }
        
        .table td {
            border-color: var(--border);
            padding: 10px 12px;
            vertical-align: middle;
            background: transparent !important;
            color: var(--text-primary) !important;
        }
        
        .table tbody tr {
            background: transparent !important;
        }
        
        .table tbody tr:hover {
            background: var(--bg-input) !important;
        }
        
        /* Event row selection */
        .event-row {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .event-row.selected {
            background: rgba(21, 97, 240, 0.15) !important;
            border-left: 3px solid var(--primary);
        }
        
        .event-row.grayed {
            opacity: 0.4;
        }
        
        .event-row.grayed:hover {
            opacity: 0.6;
        }
        
        .badge-status {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge-status.activo {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }
        
        .badge-status.finalizado {
            background: rgba(100, 116, 139, 0.2);
            color: var(--text-secondary);
        }
        
        /* Zoom controls */
        .zoom-controls {
            display: flex;
            gap: 6px;
        }
        
        .zoom-btn {
            padding: 6px 12px;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text-secondary);
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .zoom-btn:hover {
            border-color: var(--primary);
            color: var(--text-primary);
            background: rgba(21, 97, 240, 0.1);
        }
        
        .zoom-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .chart-container {
            transition: all 0.3s ease;
            overflow-x: auto;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            opacity: 0.3;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    
    <!-- Header -->
    <div class="page-header">
        <h2><i class="bi bi-speedometer2"></i> Dashboard</h2>
        <span class="db-badge <?= $db_mode ?>">
            <i class="bi bi-<?= $db_mode === 'historico' ? 'archive' : 'database' ?>"></i>
            <?= $db_label ?>
        </span>
    </div>
    
    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon" style="background: rgba(16, 185, 129, 0.15); color: var(--success);">
                <i class="bi bi-currency-dollar"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-label">Ingresos Totales</div>
                <div class="kpi-value" id="kpi-ingresos">$<?= number_format($total_ingresos, 2) ?></div>
                <div class="kpi-detail" id="kpi-ingresos-detail"><?= $eventos_con_ventas ?> eventos con ventas</div>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon" style="background: rgba(99, 102, 241, 0.15); color: var(--primary);">
                <i class="bi bi-ticket-perforated"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-label">Boletos Vendidos</div>
                <div class="kpi-value" id="kpi-boletos"><?= number_format($total_boletos) ?></div>
                <div class="kpi-detail" id="kpi-boletos-detail">$<?= number_format($precio_promedio, 2) ?> promedio</div>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon" style="background: rgba(14, 165, 233, 0.15); color: var(--info);">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-label" id="kpi-promedio-label">Promedio por Evento</div>
                <div class="kpi-value" id="kpi-promedio">$<?= number_format($promedio_por_evento, 2) ?></div>
                <div class="kpi-detail" id="kpi-promedio-detail"><?= $total_eventos ?> eventos totales</div>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon" style="background: rgba(245, 158, 11, 0.15); color: var(--warning);">
                <i class="bi bi-tag"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-label">Descuentos Otorgados</div>
                <div class="kpi-value" id="kpi-descuentos">$<?= number_format($total_descuentos, 2) ?></div>
                <div class="kpi-detail" id="kpi-descuentos-detail"><?= $total_boletos > 0 ? round(($total_descuentos / ($total_ingresos + $total_descuentos)) * 100, 1) : 0 ?>% del precio base</div>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon" style="background: rgba(236, 72, 153, 0.15); color: #ec4899;">
                <i class="bi bi-trophy"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-label" id="kpi-evento-label">Evento Más Vendido</div>
                <div class="kpi-value" id="kpi-evento-nombre" style="font-size: 1.1rem; word-break: break-word;"><?= htmlspecialchars($evento_top_nombre) ?></div>
                <div class="kpi-detail" id="kpi-evento-detail"><?= number_format($evento_top_ventas) ?> boletos</div>
            </div>
        </div>
    </div>
    
    <!-- Gráficos -->
    <div class="charts-grid">
        <div class="card chart-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h3 style="margin: 0;"><i class="bi bi-bar-chart"></i> Top Eventos por Ingresos</h3>
                <div class="zoom-controls">
                    <button class="zoom-btn active" onclick="setChartZoom(100)" data-zoom="100">100%</button>
                    <button class="zoom-btn" onclick="setChartZoom(150)" data-zoom="150">150%</button>
                    <button class="zoom-btn" onclick="setChartZoom(200)" data-zoom="200">200%</button>
                </div>
            </div>
            <div class="chart-container" id="chartTopEventosContainer">
                <canvas id="chartTopEventos"></canvas>
            </div>
        </div>
        
        <div class="card chart-card">
            <h3><i class="bi bi-pie-chart"></i> Ventas por Categoría</h3>
            <div class="chart-container">
                <canvas id="chartCategorias"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Tabla resumen -->
    <div class="card table-card">
        <h3><i class="bi bi-table"></i> Resumen por Evento</h3>
        
        <?php if (empty($resumen_eventos)): ?>
        <div class="empty-state">
            <i class="bi bi-inbox d-block"></i>
            <p>No hay datos de eventos</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Evento</th>
                        <th>Estado</th>
                        <th class="text-end">Boletos</th>
                        <th class="text-end">Ingresos</th>
                        <th class="text-end">Descuentos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resumen_eventos as $ev): ?>
                    <tr class="event-row" 
                        data-event-id="<?= $ev['id_evento'] ?>"
                        data-event-name="<?= htmlspecialchars($ev['titulo']) ?>"
                        data-event-ingresos="<?= $ev['ingresos'] ?>"
                        data-event-boletos="<?= $ev['boletos'] ?>"
                        data-event-descuentos="<?= $ev['descuentos'] ?>"
                        data-event-color="<?= $ev['color'] ?>"
                        onclick="selectEvent(this)">
                        <td class="fw-semibold"><?= htmlspecialchars($ev['titulo']) ?></td>
                        <td>
                            <span class="badge-status <?= $ev['finalizado'] ? 'finalizado' : 'activo' ?>">
                                <?= $ev['finalizado'] ? 'Finalizado' : 'Activo' ?>
                            </span>
                        </td>
                        <td class="text-end"><?= number_format($ev['boletos']) ?></td>
                        <td class="text-end text-success fw-bold">$<?= number_format($ev['ingresos'], 2) ?></td>
                        <td class="text-end" style="color: var(--warning);">$<?= number_format($ev['descuentos'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
// Datos desde PHP
const TOP_EVENTOS = <?= $top_eventos_json ?>;
const VENTAS_CATEGORIA = <?= $ventas_categoria_json ?>;

// Colores para gráficos
const CHART_COLORS = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#0ea5e9', '#ec4899'];

// Gráfico Top Eventos
let chartTopEventos = null;
(() => {
    const canvas = document.getElementById('chartTopEventos');
    if (!canvas || !TOP_EVENTOS.length) return;

    chartTopEventos = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: TOP_EVENTOS.map(e => e.titulo.substring(0, 20) + (e.titulo.length > 20 ? '...' : '')),
            datasets: [{
                label: 'Ingresos',
                data: TOP_EVENTOS.map(e => parseFloat(e.ingresos)),
                backgroundColor: CHART_COLORS,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => '$' + ctx.raw.toLocaleString('es-MX', {minimumFractionDigits: 2})
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(71, 85, 105, 0.3)' },
                    ticks: { color: '#94a3b8', callback: v => '$' + (v/1000) + 'k' }
                },
                y: {
                    grid: { display: false },
                    ticks: { color: '#f1f5f9' }
                }
            }
        }
    });
})();

// Función de zoom para el gráfico
function setChartZoom(percentage) {
    if (!chartTopEventos) return;
    
    const container = document.getElementById('chartTopEventosContainer');
    const canvas = document.getElementById('chartTopEventos');
    
    if (!container || !canvas) return;
    
    // Actualizar botones activos
    document.querySelectorAll('.zoom-btn').forEach(btn => {
        btn.classList.remove('active');
        if (parseInt(btn.dataset.zoom) === percentage) {
            btn.classList.add('active');
        }
    });
    
    // Resetear cualquier transformación previa
    canvas.style.transform = 'none';
    canvas.style.height = '';
    
    // Ajustar ANCHO del contenedor basado en el zoom (expansión horizontal)
    const containerWidth = container.parentElement.offsetWidth;
    const newWidth = containerWidth * (percentage / 100);
    
    // Aplicar nuevo ancho al canvas
    canvas.style.width = newWidth + 'px';
    canvas.style.height = '280px'; // Altura fija
    
    // Calcular número de ticks en el eje X basado en el zoom
    // Más zoom = más ticks = más granularidad en los valores
    const baseMaxTicks = 5;
    const newMaxTicks = Math.floor(baseMaxTicks * (percentage / 100));
    
    // Actualizar opciones del gráfico para aumentar granularidad
    chartTopEventos.options.scales.x.ticks.maxTicksLimit = Math.max(newMaxTicks, 5);
    chartTopEventos.options.scales.x.ticks.autoSkip = percentage === 100;
    
    // Si el zoom es mayor, mostrar valores completos en lugar de "k"
    if (percentage > 100) {
        chartTopEventos.options.scales.x.ticks.callback = function(value) {
            return '$' + value.toLocaleString('es-MX', {minimumFractionDigits: 0, maximumFractionDigits: 0});
        };
    } else {
        chartTopEventos.options.scales.x.ticks.callback = v => '$' + (v/1000) + 'k';
    }
    
    // Forzar actualización del gráfico
    chartTopEventos.resize();
    chartTopEventos.update('none');
}

// Función para seleccionar evento y filtrar gráfico
let selectedEventId = null;
const allEventosData = [...TOP_EVENTOS]; // Guardar datos originales

// Guardar valores originales de KPIs
const originalKPIs = {
    ingresos: document.getElementById('kpi-ingresos').textContent,
    ingresosDetail: document.getElementById('kpi-ingresos-detail').textContent,
    boletos: document.getElementById('kpi-boletos').textContent,
    boletosDetail: document.getElementById('kpi-boletos-detail').textContent,
    promedio: document.getElementById('kpi-promedio').textContent,
    promedioDetail: document.getElementById('kpi-promedio-detail').textContent,
    promedioLabel: document.getElementById('kpi-promedio-label').textContent,
    descuentos: document.getElementById('kpi-descuentos').textContent,
    descuentosDetail: document.getElementById('kpi-descuentos-detail').textContent,
    eventoLabel: document.getElementById('kpi-evento-label').textContent,
    eventoNombre: document.getElementById('kpi-evento-nombre').textContent,
    eventoDetail: document.getElementById('kpi-evento-detail').textContent
};

function selectEvent(row) {
    const eventId = row.dataset.eventId;
    const eventName = row.dataset.eventName;
    const eventIngresos = parseFloat(row.dataset.eventIngresos);
    const eventBoletos = parseInt(row.dataset.eventBoletos);
    const eventDescuentos = parseFloat(row.dataset.eventDescuentos);
    const eventColor = row.dataset.eventColor || '#6366f1';
    
    // Si se hace clic en el mismo evento, deseleccionar
    if (selectedEventId === eventId) {
        selectedEventId = null;
        
        // Quitar clases de todas las filas
        document.querySelectorAll('.event-row').forEach(r => {
            r.classList.remove('selected', 'grayed');
        });
        
        // Restaurar gráfico con todos los eventos
        chartTopEventos.data.labels = allEventosData.map(e => e.titulo.substring(0, 20) + (e.titulo.length > 20 ? '...' : ''));
        chartTopEventos.data.datasets[0].data = allEventosData.map(e => parseFloat(e.ingresos));
        chartTopEventos.data.datasets[0].backgroundColor = allEventosData.map(e => e.color || CHART_COLORS[0]);
        chartTopEventos.update();
        
        // Restaurar KPIs originales
        document.getElementById('kpi-ingresos').textContent = originalKPIs.ingresos;
        document.getElementById('kpi-ingresos-detail').textContent = originalKPIs.ingresosDetail;
        document.getElementById('kpi-boletos').textContent = originalKPIs.boletos;
        document.getElementById('kpi-boletos-detail').textContent = originalKPIs.boletosDetail;
        document.getElementById('kpi-promedio-label').textContent = originalKPIs.promedioLabel;
        document.getElementById('kpi-promedio').textContent = originalKPIs.promedio;
        document.getElementById('kpi-promedio-detail').textContent = originalKPIs.promedioDetail;
        document.getElementById('kpi-descuentos').textContent = originalKPIs.descuentos;
        document.getElementById('kpi-descuentos-detail').textContent = originalKPIs.descuentosDetail;
        document.getElementById('kpi-evento-label').textContent = originalKPIs.eventoLabel;
        document.getElementById('kpi-evento-nombre').textContent = originalKPIs.eventoNombre;
        document.getElementById('kpi-evento-detail').textContent = originalKPIs.eventoDetail;
        
        return;
    }
    
    // Seleccionar nuevo evento
    selectedEventId = eventId;
    
    // Actualizar clases de las filas
    document.querySelectorAll('.event-row').forEach(r => {
        if (r.dataset.eventId === eventId) {
            r.classList.add('selected');
            r.classList.remove('grayed');
        } else {
            r.classList.remove('selected');
            r.classList.add('grayed');
        }
    });
    
    // Actualizar gráfico con solo el evento seleccionado usando su color
    chartTopEventos.data.labels = [eventName.substring(0, 20) + (eventName.length > 20 ? '...' : '')];
    chartTopEventos.data.datasets[0].data = [eventIngresos];
    chartTopEventos.data.datasets[0].backgroundColor = [eventColor];
    chartTopEventos.update();
    
    // Actualizar KPIs con datos del evento seleccionado
    const promedioPorBoleto = eventBoletos > 0 ? eventIngresos / eventBoletos : 0;
    const porcentajeDescuento = (eventIngresos + eventDescuentos) > 0 ? (eventDescuentos / (eventIngresos + eventDescuentos)) * 100 : 0;
    
    document.getElementById('kpi-ingresos').textContent = '$' + eventIngresos.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('kpi-ingresos-detail').textContent = 'Evento seleccionado';
    document.getElementById('kpi-boletos').textContent = eventBoletos.toLocaleString('es-MX');
    document.getElementById('kpi-boletos-detail').textContent = '$' + promedioPorBoleto.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' promedio';
    document.getElementById('kpi-promedio-label').textContent = 'Ingresos del Evento';
    document.getElementById('kpi-promedio').textContent = '$' + eventIngresos.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('kpi-promedio-detail').textContent = eventName;
    document.getElementById('kpi-descuentos').textContent = '$' + eventDescuentos.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('kpi-descuentos-detail').textContent = porcentajeDescuento.toFixed(1) + '% del precio base';
    document.getElementById('kpi-evento-label').textContent = 'Evento Seleccionado';
    document.getElementById('kpi-evento-nombre').textContent = eventName;
    document.getElementById('kpi-evento-detail').textContent = eventBoletos.toLocaleString('es-MX') + ' boletos vendidos';
}


// Gráfico Categorías
(() => {
    const canvas = document.getElementById('chartCategorias');
    if (!canvas || !VENTAS_CATEGORIA.length) return;

    const colors = VENTAS_CATEGORIA.map((c, i) => c.color || CHART_COLORS[i % CHART_COLORS.length]);

    new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: VENTAS_CATEGORIA.map(c => c.nombre_categoria),
            datasets: [{
                data: VENTAS_CATEGORIA.map(c => parseFloat(c.ingresos)),
                backgroundColor: colors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: {
                    position: 'right',
                    labels: { color: '#f1f5f9', padding: 12 }
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.label + ': $' + ctx.raw.toLocaleString('es-MX', {minimumFractionDigits: 2})
                    }
                }
            }
        }
    });
})();
</script>
</body>
</html>
