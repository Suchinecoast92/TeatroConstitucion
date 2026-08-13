<?php
/* =========================
   ADMIN - ESTADÍSTICAS COMPLETAS
   Soporta BD actual, histórica, o ambas.
   ========================= */

require_once '../../evt_interfaz/conexion.php';

// Detectar DB
$db_mode = $_GET['db'] ?? 'actual'; // actual, historico, ambas
$db_actual = 'trt_25';
$db_historico = 'trt_historico_evento';

// Obtener eventos para el filtro inicial (depende de la DB seleccionada)
$filtro_eventos = [];
try {
    if ($db_mode === 'ambas') {
        $sql = "SELECT id_evento, titulo, 'Actual' as origen FROM {$db_actual}.evento 
                UNION ALL 
                SELECT id_evento, titulo, 'Histórico' as origen FROM {$db_historico}.evento 
                ORDER BY id_evento DESC";
    } else {
        $db = ($db_mode === 'historico') ? $db_historico : $db_actual;
        $sql = "SELECT id_evento, titulo FROM {$db}.evento ORDER BY id_evento DESC";
    }
    
    // Ejecutar query manual si es necesario o usar $conn si es simple
    // Nota: $conn está conectado a la BD por defecto. Para queries cross-db o dinamicos:
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $filtro_eventos[] = $row;
        }
    }
} catch (Exception $e) {
    // Si falla, array vacío
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas Avanzadas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bs-body-bg: #131313;
            --bs-body-color: #f8f9fa;
            --card-bg: #1e1e1e;
            --input-bg: #2b2b2b;
            --border-color: #333;
        }
        body { font-family: 'Inter', system-ui, sans-serif; }
        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
        }
        .form-control, .form-select {
            background-color: var(--input-bg);
            border-color: var(--border-color);
            color: #fff;
        }
        .form-control:focus, .form-select:focus {
            background-color: var(--input-bg);
            border-color: #4f46e5;
            color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(79, 70, 229, 0.25);
        }
        .text-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            font-weight: 600;
        }
        .nav-pills .nav-link {
            color: #aaa;
            border-radius: 8px;
            padding: 8px 16px;
        }
        .nav-pills .nav-link.active {
            background-color: #4f46e5;
            color: #fff;
        }
        .table-custom th {
            background-color: var(--input-bg);
            color: #ccc;
            font-weight: 600;
            border-bottom: 1px solid var(--border-color);
        }
        .table-custom td {
            background-color: transparent;
            color: #eee;
            border-bottom: 1px solid var(--border-color);
        }
        .badge-db {
            font-size: 0.7em;
            padding: 4px 8px;
            border-radius: 4px;
            background: rgba(255,255,255,0.1);
        }
    </style>
</head>
<body class="p-4">

<div class="container-fluid">
    <!-- Encabezado y Selector de DB -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Reportes y Estadísticas</h2>
            <p class="text-secondary mb-0">Análisis detallado de ventas, eventos y asistencia.</p>
        </div>
        
        <div class="d-flex gap-2 bg-dark p-2 rounded-3 border border-secondary">
            <a href="?db=actual" class="btn btn-sm <?php echo $db_mode === 'actual' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                <i class="bi bi-database me-1"></i> Actual
            </a>
            <a href="?db=historico" class="btn btn-sm <?php echo $db_mode === 'historico' ? 'btn-warning' : 'btn-outline-secondary'; ?>">
                <i class="bi bi-archive me-1"></i> Histórico
            </a>
            <a href="?db=ambas" class="btn btn-sm <?php echo $db_mode === 'ambas' ? 'btn-info' : 'btn-outline-secondary'; ?>">
                <i class="bi bi-intersect me-1"></i> Combinado
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card p-3 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="text-label mb-1">Evento</label>
                <select id="filtro_evento" class="form-select">
                    <option value="">-- Todos los Eventos --</option>
                    <?php foreach ($filtro_eventos as $ev): ?>
                        <option value="<?php echo $ev['id_evento']; ?>">
                            <?php echo htmlspecialchars($ev['titulo']); ?>
                            <?php if(isset($ev['origen'])) echo " (" . $ev['origen'] . ")"; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="text-label mb-1">Desde</label>
                <input type="date" id="filtro_desde" class="form-control" value="<?php echo date('Y-m-d', strtotime('-1 month')); ?>">
            </div>
            <div class="col-md-3">
                <label class="text-label mb-1">Hasta</label>
                <input type="date" id="filtro_hasta" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary w-100" onclick="generarReporte()">
                    <i class="bi bi-search me-1"></i> Generar
                </button>
                <button class="btn btn-outline-light" onclick="exportarPDF()" title="Exportar PDF">
                    <i class="bi bi-file-earmark-pdf"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Contenido Principal -->
    <div id="loading-spinner" class="text-center py-5 d-none">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2 text-secondary">Procesando datos...</p>
    </div>

    <div id="report-content" class="d-none">
        <!-- Tabs de Vistas -->
        <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pills-resumen-tab" data-bs-toggle="pill" data-bs-target="#pills-resumen" type="button" role="tab"><i class="bi bi-grid-1x2 me-2"></i>Resumen General</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-eventos-tab" data-bs-toggle="pill" data-bs-target="#pills-eventos" type="button" role="tab"><i class="bi bi-calendar-check me-2"></i>Por Evento</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-categorias-tab" data-bs-toggle="pill" data-bs-target="#pills-categorias" type="button" role="tab"><i class="bi bi-pie-chart me-2"></i>Categorías</button>
            </li>
        </ul>

        <div class="tab-content" id="pills-tabContent">
            
            <!-- VISTA: RESUMEN GENERAL -->
            <div class="tab-pane fade show active" id="pills-resumen" role="tabpanel">
                <!-- KPIs -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card p-3 border-primary border-opacity-25 h-100">
                            <h6 class="text-label text-primary">Ingresos Totales</h6>
                            <h2 class="fw-bold mb-0" id="kpi-ingresos">$0.00</h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3 border-success border-opacity-25 h-100">
                            <h6 class="text-label text-success">Boletos Vendidos</h6>
                            <h2 class="fw-bold mb-0" id="kpi-boletos">0</h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3 border-warning border-opacity-25 h-100">
                            <h6 class="text-label text-warning">Descuentos</h6>
                            <h2 class="fw-bold mb-0" id="kpi-descuentos">$0.00</h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3 border-info border-opacity-25 h-100">
                            <h6 class="text-label text-info">Ticket Promedio</h6>
                            <h2 class="fw-bold mb-0" id="kpi-promedio">$0.00</h2>
                        </div>
                    </div>
                </div>

                <!-- Gráfico Cronología -->
                <div class="card p-4 mb-4">
                    <h5 class="fw-bold mb-3">Tendencia de Ventas (Top Eventos)</h5>
                    <div style="height: 300px;">
                        <canvas id="chartTendencia"></canvas>
                    </div>
                </div>
            </div>

            <!-- VISTA: POR EVENTO -->
            <div class="tab-pane fade" id="pills-eventos" role="tabpanel">
                <div class="card p-4">
                    <div class="table-responsive">
                        <table class="table table-custom align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Evento</th>
                                    <th class="text-end">Boletos</th>
                                    <th class="text-end">Ingresos</th>
                                    <th class="text-end">Promedio</th>
                                </tr>
                            </thead>
                            <tbody id="lista-eventos">
                                <!-- JS Populates this -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- VISTA: CATEGORIAS -->
            <div class="tab-pane fade" id="pills-categorias" role="tabpanel">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card p-4 h-100">
                            <h5 class="fw-bold mb-3">Distribución por Categoría</h5>
                            <div style="height: 300px;">
                                <canvas id="chartCategoriasMain"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card p-4 h-100">
                            <h5 class="fw-bold mb-3">Detalle</h5>
                            <div class="table-responsive">
                                <table class="table table-custom align-middle">
                                    <thead>
                                        <tr>
                                            <th>Categoría</th>
                                            <th>Evento</th>
                                            <th class="text-end">Cant.</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="lista-categorias">
                                        <!-- JS Populates this -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// --- CONFIGURACIÓN ---
const API_URL = 'reportes_api.php';
const DB_MODE = '<?php echo $db_mode; ?>';

// --- ESTADO ---
let chartTendenciaInstance = null;
let chartCategoriasMainInstance = null;

// --- INICIALIZACIÓN ---
document.addEventListener('DOMContentLoaded', () => {
    generarReporte();
});

// --- FUNCIONES ---
async function generarReporte() {
    // UI Loading
    document.getElementById('loading-spinner').classList.remove('d-none');
    document.getElementById('report-content').classList.add('d-none');

    const filtros = {
        action: 'generar',
        db: DB_MODE,
        evento: document.getElementById('filtro_evento').value,
        desde: document.getElementById('filtro_desde').value,
        hasta: document.getElementById('filtro_hasta').value
    };

    const qs = new URLSearchParams(filtros).toString();

    try {
        const res = await fetch(`${API_URL}?${qs}`);
        const result = await res.json();

        if (!result.ok) throw new Error(result.error || 'Error desconocido');
        const data = result.data;

        renderDashboard(data);

        document.getElementById('loading-spinner').classList.add('d-none');
        document.getElementById('report-content').classList.remove('d-none');

    } catch (e) {
        alert("Error al cargar datos: " + e.message);
        document.getElementById('loading-spinner').classList.add('d-none');
    }
}

function renderDashboard(data) {
    const resumen = data.resumen || {};
    
    // 1. KPIs
    document.getElementById('kpi-ingresos').innerText = formatoMoneda(resumen.total_vendido);
    document.getElementById('kpi-boletos').innerText = formatoNumero(resumen.total_boletos);
    document.getElementById('kpi-descuentos').innerText = formatoMoneda(resumen.total_descuentos);
    document.getElementById('kpi-promedio').innerText = formatoMoneda(resumen.promedio_por_boleto);

    // 2. Tabla Eventos
    const tbodyEventos = document.getElementById('lista-eventos');
    tbodyEventos.innerHTML = (data.eventos || []).map(evt => `
        <tr>
            <td><span class="badge bg-secondary">#${evt.id_evento}</span></td>
            <td class="fw-bold text-white">${evt.titulo}</td>
            <td class="text-end">${formatoNumero(evt.total_boletos)}</td>
            <td class="text-end text-success">${formatoMoneda(evt.total_vendido)}</td>
            <td class="text-end text-muted">${formatoMoneda(evt.promedio)}</td>
        </tr>
    `).join('');

    // 3. Tabla Categorías
    const tbodyCats = document.getElementById('lista-categorias');
    tbodyCats.innerHTML = (data.categorias || []).slice(0, 50).map(cat => `
        <tr>
            <td><span class="badge bg-dark border border-secondary">${cat.nombre_categoria}</span></td>
            <td><small>${cat.titulo_evento}</small></td>
            <td class="text-end">${formatoNumero(cat.cantidad)}</td>
            <td class="text-end">${formatoMoneda(cat.total)}</td>
        </tr>
    `).join('');

    // 4. CHART: Tendencia (Usando Eventos Top 10)
    const ctxTend = document.getElementById('chartTendencia');
    if (chartTendenciaInstance) chartTendenciaInstance.destroy();
    
    const topEventos = (data.eventos || []).slice(0, 10);
    chartTendenciaInstance = new Chart(ctxTend, {
        type: 'bar',
        data: {
            labels: topEventos.map(e => e.titulo),
            datasets: [{
                label: 'Ingresos Totales',
                data: topEventos.map(e => e.total_vendido),
                backgroundColor: '#4f46e5',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: '#333' }, ticks: { color: '#aaa' } },
                x: { grid: { display: false }, ticks: { color: '#aaa', display: false } }
            }
        }
    });

    // 5. CHART: Categorías
    const ctxCat = document.getElementById('chartCategoriasMain');
    if (chartCategoriasMainInstance) chartCategoriasMainInstance.destroy();

    // Agrupar categorías por nombre global (sumar todos los eventos)
    const catsMap = {};
    (data.categorias || []).forEach(c => {
        if (!catsMap[c.nombre_categoria]) catsMap[c.nombre_categoria] = 0;
        catsMap[c.nombre_categoria] += parseFloat(c.cantidad);
    });
    
    chartCategoriasMainInstance = new Chart(ctxCat, {
        type: 'doughnut',
        data: {
            labels: Object.keys(catsMap),
            datasets: [{
                data: Object.values(catsMap),
                backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { color: whiteColor() } } }
        }
    });
}

function exportarPDF() {
    const filtros = {
        action: 'export',
        db: DB_MODE,
        evento: document.getElementById('filtro_evento').value,
        desde: document.getElementById('filtro_desde').value,
        hasta: document.getElementById('filtro_hasta').value
    };
    const qs = new URLSearchParams(filtros).toString();
    window.open(`${API_URL}?${qs}`, '_blank');
}

// Helpers
function formatoMoneda(val) {
    return '$' + parseFloat(val || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
function formatoNumero(val) {
    return parseInt(val || 0).toLocaleString('en-US');
}
function whiteColor() { return '#e5e7eb'; }

</script>
</body>
</html>