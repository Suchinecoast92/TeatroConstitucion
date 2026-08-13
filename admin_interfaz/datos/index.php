<?php
/* =========================
   ADMIN - DATOS (Centro Unificado)
   Junta Transacciones, Estadísticas, Reportes y Ajustes.
   ========================= */

session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Accesible para admin o empleados
$es_admin = ($_SESSION['usuario_rol'] === 'admin' || (isset($_SESSION['admin_verificado']) && $_SESSION['admin_verificado']));

require_once '../../transacciones_helper.php';
require_once '../../evt_interfaz/conexion.php'; // Aseguramos conexión para todos los tabs

// --- LOGICA DEL HISTORIAL (Original de Transacciones) ---
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$transacciones = [];

$sql_base = "SELECT t.id_transaccion, t.accion, t.descripcion, t.fecha_hora, u.nombre, u.apellido
             FROM transacciones t
             JOIN usuarios u ON t.id_usuario = u.id_usuario";

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 100;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];
$types = "";

if ($fecha_desde) {
    $where[] = "t.fecha_hora >= ?";
    $params[] = $fecha_desde . ' 00:00:00';
    $types .= "s";
}
if ($fecha_hasta) {
    $where[] = "t.fecha_hora <= ?";
    $params[] = $fecha_hasta . ' 23:59:59';
    $types .= "s";
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Count for pagination
$sql_count = "SELECT COUNT(*) as total FROM transacciones t $where_sql";
$stmt_count = $conn->prepare($sql_count);
if ($params) $stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$total_rows = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
$stmt_count->close();

// Fetch transactions
$sql = "$sql_base $where_sql ORDER BY t.fecha_hora DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $transacciones[] = $row;
$stmt->close();

// --- LOGICA DE ESTADISTICAS (Filtro eventos) ---
$eventos_filtro = [];
$res_eventos = $conn->query("SELECT id_evento, titulo FROM evento ORDER BY id_evento DESC");
if ($res_eventos) {
    while ($row = $res_eventos->fetch_assoc()) $eventos_filtro[] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centro de Datos</title>
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
            --primary: #4f46e5;
            --success: #10b981;
            --warning: #f59e0b;
        }
        body { font-family: 'Inter', system-ui, sans-serif; padding-top: 1rem; }
        .card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; }
        .form-control, .form-select { background-color: var(--input-bg); border-color: var(--border-color); color: #fff; }
        .form-control:focus, .form-select:focus { background-color: var(--input-bg); border-color: var(--primary); color: #fff; box-shadow: none; }
        
        /* Modern Tabs */
        .nav-pills .nav-link { 
            color: #aaa; 
            border-radius: 50rem; 
            padding: 8px 20px; 
            font-weight: 500;
            transition: all 0.2s;
        }
        .nav-pills .nav-link:hover { color: #fff; background: rgba(255,255,255,0.05); }
        .nav-pills .nav-link.active { background-color: var(--primary); color: #fff; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }
        
        .table { color: #fff; }
        .table thead th { background: var(--input-bg); color: #aaa; border-bottom: 2px solid var(--border-color); border-top: none; }
        .table tbody td { border-bottom: 1px solid var(--border-color); }
        .table-hover tbody tr:hover td { background-color: rgba(255,255,255,0.02); }

        /* Ajustes Cards */
        .settings-card {
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
        }
        .settings-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
            background: #252525;
        }
        .settings-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; margin-bottom: 1rem;
        }
        
        /* Iframe para Reportes y Ajustes si necesario, o embed directo */
        .embedded-frame { width: 100%; height: 800px; border: none; border-radius: 12px; background: var(--card-bg); }
    </style>
</head>
<body class="p-3">

<div class="container-fluid">
    <!-- Header with Tabs -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="m-0 fw-bold"><i class="bi bi-database-fill me-2 text-primary"></i>Datos y Configuración</h2>
            <p class="text-secondary mb-0">Centro unificado de monitoreo y control</p>
        </div>
        
        <ul class="nav nav-pills bg-dark rounded-pill p-1 border border-secondary" id="mainTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="historial-tab" data-bs-toggle="pill" data-bs-target="#pills-historial" type="button" role="tab">
                    <i class="bi bi-clock-history me-2"></i>Historial
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="stats-tab" data-bs-toggle="pill" data-bs-target="#pills-stats" type="button" role="tab">
                    <i class="bi bi-graph-up-arrow me-2"></i>Dashboard
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="reportes-tab" data-bs-toggle="pill" data-bs-target="#pills-reportes" type="button" role="tab">
                    <i class="bi bi-file-earmark-text me-2"></i>Reportes Avanzados
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="ajustes-tab" data-bs-toggle="pill" data-bs-target="#pills-ajustes" type="button" role="tab">
                    <i class="bi bi-sliders me-2"></i>Ajustes
                </button>
            </li>
        </ul>
    </div>

    <!-- CONTENT TABS -->
    <div class="tab-content" id="mainTabContent">
        
        <!-- 1. HISTORIAL -->
        <div class="tab-pane fade show active" id="pills-historial" role="tabpanel">
            <div class="card p-4">
                 <div class="d-flex justify-content-between align-items-center mb-3">
                     <h5 class="fw-bold"><i class="bi bi-list-check me-2"></i>Registro de Transacciones</h5>
                     <div class="text-end">
                        <small class="text-secondary d-block">Registros totales</small>
                        <span class="fs-5 fw-bold"><?php echo number_format($total_rows); ?></span>
                     </div>
                 </div>
                 
                 <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-4">
                        <input type="date" name="fecha_desde" class="form-control" value="<?php echo htmlspecialchars($fecha_desde); ?>" placeholder="Desde">
                    </div>
                    <div class="col-md-4">
                        <input type="date" name="fecha_hasta" class="form-control" value="<?php echo htmlspecialchars($fecha_hasta); ?>" placeholder="Hasta">
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">Filtrar</button>
                        <a href="index.php" class="btn btn-outline-secondary">Limpiar</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>Acción</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($transacciones as $t): ?>
                            <tr style="cursor: pointer;" onclick="abrirDetalleTransaccion(<?php echo $t['id_transaccion']; ?>)">
                                <td><?php echo date('d/m/Y H:i', strtotime($t['fecha_hora'])); ?></td>
                                <td><?php echo htmlspecialchars($t['nombre'] . ' ' . $t['apellido']); ?></td>
                                <td><span class="badge bg-opacity-25 bg-info text-info border border-info"><?php echo htmlspecialchars($t['accion']); ?></span></td>
                                <td class="text-truncate" style="max-width: 300px;"><?php echo htmlspecialchars($t['descripcion'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($transacciones)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No se encontraron resultados</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination simple -->
                <div class="d-flex justify-content-center mt-3">
                    <nav>
                        <ul class="pagination pagination-sm">
                            <?php if($page > 1): ?>
                            <li class="page-item"><a class="page-link bg-dark border-secondary text-white" href="?page=<?php echo $page-1; ?>">&laquo;</a></li>
                            <?php endif; ?>
                            <li class="page-item active"><span class="page-link bg-primary border-primary"><?php echo $page; ?></span></li>
                            <?php if($page < $total_pages): ?>
                            <li class="page-item"><a class="page-link bg-dark border-secondary text-white" href="?page=<?php echo $page+1; ?>">&raquo;</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>

        <!-- 2. DASHBOARD (Estadísticas) -->
        <div class="tab-pane fade" id="pills-stats" role="tabpanel">
            <!-- Barra de Filtros Estadísticas -->
            <div class="card p-3 mb-4 border-0 shadow-sm bg-dark bg-gradient">
                <div class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <label class="form-label mb-1 text-secondary small">Filtrar por Evento</label>
                        <select id="filtro_evento_stats" class="form-select form-select-sm" onchange="cargarEstadisticas()">
                            <option value="">Todos los eventos</option>
                            <?php foreach ($eventos_filtro as $ev): ?>
                                <option value="<?php echo $ev['id_evento']; ?>"><?php echo htmlspecialchars($ev['titulo']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card p-3 h-100 bg-gradient border-0" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);">
                        <small class="text-white-50">Total Ingresos</small>
                        <h2 class="fw-bold text-white mb-0" id="card-ingresos">$0.00</h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3 h-100 bg-gradient border-0" style="background: linear-gradient(135deg, #064e3b 0%, #10b981 100%);">
                        <small class="text-white-50">Boletos Vendidos</small>
                        <h2 class="fw-bold text-white mb-0" id="card-boletos">0</h2>
                    </div>
                </div>
                <div class="col-md-3">
                     <div class="card p-3 h-100 bg-gradient border-0" style="background: linear-gradient(135deg, #0e7490 0%, #06b6d4 100%);">
                        <small class="text-white-50">Ticket Promedio</small>
                        <h2 class="fw-bold text-white mb-0" id="card-promedio">$0.00</h2>
                    </div>
                </div>
                <div class="col-md-3">
                     <div class="card p-3 h-100 bg-gradient border-0" style="background: linear-gradient(135deg, #b45309 0%, #f59e0b 100%);">
                        <small class="text-white-50">Eventos Activos</small>
                        <h2 class="fw-bold text-white mb-0" id="card-eventos">0</h2>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-8">
                    <div class="card p-4 h-100">
                        <h5 class="fw-bold mb-4">Ventas por Hora</h5>
                        <div style="height: 300px;"><canvas id="chartVentasHora"></canvas></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-4 h-100">
                        <h5 class="fw-bold mb-4">Por Categoría</h5>
                        <div style="height: 200px; margin-bottom: 2rem;"><canvas id="chartCategorias"></canvas></div>
                         <div class="table-responsive" style="max-height: 150px; overflow-y: auto;">
                            <table class="table table-sm table-dark table-striped mb-0" id="tablaCategorias">
                                <thead><tr><th>Cat</th><th class="text-end">Qty</th></tr></thead><tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. REPORTES AVANZADOS -->
        <div class="tab-pane fade" id="pills-reportes" role="tabpanel">
            <div class="alert alert-info d-flex align-items-center mb-3">
                <i class="bi bi-info-circle-fill me-2"></i>
                <div>
                   Para reportes más complejos con consultas históricas y exportación a PDF.
                </div>
            </div>
            <!-- Incorporamos la página de reportes mediante IFRAME para mantener su complejidad aislada -->
            <iframe src="../../admin_interfaz/rpt_reportes/index.php" class="embedded-frame"></iframe>
        </div>

        <!-- 4. AJUSTES -->
        <div class="tab-pane fade" id="pills-ajustes" role="tabpanel">
            <h4 class="mb-4">Configuración del Sistema</h4>
            <div class="row g-4">
                <div class="col-md-4">
                    <a href="../dsc_boletos/index.php" class="card settings-card p-4 text-center">
                        <div class="settings-icon bg-success bg-opacity-10 text-success mx-auto">
                            <i class="bi bi-percent"></i>
                        </div>
                        <h5>Descuentos</h5>
                        <p class="text-muted small">Gestionar descuentos para niños, 3ra edad y cortesías.</p>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="../ctg_boletos/index.php" class="card settings-card p-4 text-center">
                        <div class="settings-icon bg-primary bg-opacity-10 text-primary mx-auto">
                            <i class="bi bi-tags-fill"></i>
                        </div>
                        <h5>Categorías de Boletos</h5>
                        <p class="text-muted small">Configurar precios, zonas y colores por evento.</p>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="../../mp_interfaz/index.php" class="card settings-card p-4 text-center">
                        <div class="settings-icon bg-info bg-opacity-10 text-info mx-auto">
                             <i class="bi bi-grid-3x3-gap-fill"></i>
                        </div>
                        <h5>Mapeo de Asientos</h5>
                        <p class="text-muted small">Diseñar el mapa del teatro, filas y distribución.</p>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="../limpieza/index.php" class="card settings-card p-4 text-center">
                        <div class="settings-icon bg-danger bg-opacity-10 text-danger mx-auto">
                            <i class="bi bi-trash-fill"></i>
                        </div>
                        <h5>Limpieza de Datos</h5>
                        <p class="text-muted small">Archivar eventos o vaciar bases de datos.</p>
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal Detalle Transacción (reutilizado) -->
<div class="modal fade" id="modalDetalleTransaccion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Detalle Transacción</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleContent">
                <p class="text-center text-muted">Cargando...</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Scripts Lógica -->
<script>
// --- LOGICA DASHBOARD ---
let chartVentasHoraInstance = null;
let chartCategoriasInstance = null;
let statsLoaded = false;

// Cargar stats al abrir tab "Dashboard"
document.getElementById('stats-tab').addEventListener('shown.bs.tab', function () {
    if (!statsLoaded) cargarEstadisticas();
});

async function cargarEstadisticas() {
    try {
        const idEvento = document.getElementById('filtro_evento_stats').value;
        const response = await fetch(idEvento ? `api_estadisticas_dashboard.php?id_evento=${idEvento}` : 'api_estadisticas_dashboard.php');
        const res = await response.json();
        
        if (!res.success) return console.error(res.error);
        const data = res.data;
        const resumen = data.resumen || {};

        document.getElementById('card-ingresos').innerText = '$' + parseFloat(resumen.total_ingresos || 0).toLocaleString('en-US');
        document.getElementById('card-boletos').innerText = parseInt(resumen.total_boletos || 0).toLocaleString();
        document.getElementById('card-promedio').innerText = '$' + parseFloat(resumen.ticket_promedio || 0).toFixed(2);
        document.getElementById('card-eventos').innerText = resumen.eventos_activos || 0;

        // Chart Hora
        const ctxHora = document.getElementById('chartVentasHora');
        const horas = Array.from({length: 24}, (_, i) => i);
        const dataHora = new Array(24).fill(0);
        (data.por_hora || []).forEach(d => dataHora[parseInt(d.hora)] = parseFloat(d.ingresos));
        
        if (chartVentasHoraInstance) chartVentasHoraInstance.destroy();
        chartVentasHoraInstance = new Chart(ctxHora, {
            type: 'bar',
            data: {
                labels: horas.map(h => `${h}:00`),
                datasets: [{ label: 'Ingresos', data: dataHora, backgroundColor: '#4f46e5', borderRadius: 4 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { grid: { color: '#333' } }, x: { grid: { display: false } } } }
        });

        // Chart y Tabla Categorias
        const tbody = document.querySelector('#tablaCategorias tbody');
        tbody.innerHTML = (data.por_categoria || []).map(c => `<tr><td>${c.nombre_categoria}</td><td class="text-end">${c.cantidad}</td></tr>`).join('');

        const ctxCat = document.getElementById('chartCategorias');
        if (chartCategoriasInstance) chartCategoriasInstance.destroy();
        chartCategoriasInstance = new Chart(ctxCat, {
            type: 'doughnut',
            data: {
                labels: (data.por_categoria || []).map(c => c.nombre_categoria),
                datasets: [{ data: (data.por_categoria || []).map(c => c.cantidad), backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'], borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { color: '#fff', boxWidth: 10 } } } }
        });

        statsLoaded = true;
    } catch (e) { console.error(e); }
}

// --- LOGICA DETALLE TRANSACCION ---
async function abrirDetalleTransaccion(id) {
    const modal = new bootstrap.Modal(document.getElementById('modalDetalleTransaccion'));
    modal.show();
    document.getElementById('detalleContent').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    
    try {
        const res = await fetch(`api_detalle_transaccion.php?id=${id}`);
        const data = await res.json();
        if(data.success) {
            const t = data.transaccion;
            document.getElementById('detalleContent').innerHTML = `
                <div class="list-group list-group-flush bg-transparent">
                    <div class="list-group-item bg-transparent text-white"><strong>ID:</strong> #${t.id_transaccion}</div>
                    <div class="list-group-item bg-transparent text-white"><strong>Usuario:</strong> ${t.nombre} ${t.apellido}</div>
                    <div class="list-group-item bg-transparent text-white"><strong>Acción:</strong> ${t.accion}</div>
                    <div class="list-group-item bg-transparent text-white"><strong>Descripción:</strong> <br>${t.descripcion}</div>
                    <div class="list-group-item bg-transparent text-white"><strong>Fecha:</strong> ${t.fecha_hora}</div>
                </div>
            `;
        }
    } catch(e) { 
        document.getElementById('detalleContent').innerHTML = '<p class="text-danger">Error al cargar detalle</p>'; 
    }
}
</script>
</body>
</html>
