<?php
/* =========================
   ADMIN - TRANSACCIONES (Consolidado & Mejorado)
   - Búsqueda en Vivo
   - Alto Contraste
   - Detalle Completo (Cliente, Evento, Asientos)
   ========================= */

session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}
$es_admin = ($_SESSION['usuario_rol'] === 'admin' || (isset($_SESSION['admin_verificado']) && $_SESSION['admin_verificado']));
require_once '../../evt_interfaz/conexion.php';

// Carga inicial de filtros para estadísticas
$eventos_filtro = [];
$res_ev = $conn->query("SELECT id_evento, titulo FROM evento ORDER BY id_evento DESC");
while ($r = $res_ev->fetch_assoc()) $eventos_filtro[] = $r;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Transacciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" integrity="sha512-dPXYcDub/aeb08c63jRq/k6GqJ6SZlWgIz2NNZZiP9RXXpR6+8E/gVBbBQs8rY7xMz5p5yUB78/5Q1xQHcGQ4g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style>
        :root {
            --bs-body-bg: #131313;
            --bs-body-color: #e0e0e0;
            --card-bg: #1e1e1e;
            --input-bg: #2b2b2b;
            --border-color: #444;
            --primary: #6366f1; /* Indigo más brillante */
            --accent: #22d3ee; /* Cian brillante */
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; padding-top: 1rem; }
        
        /* High Contrast Card */
        .card { 
            background-color: var(--card-bg); 
            border: 1px solid var(--border-color); 
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }

        /* Inputs Search */
        .form-control, .form-select { 
            background-color: #000; 
            border: 1px solid #555; 
            color: #fff; 
            font-size: 0.95rem;
        }
        .form-control:focus { 
            background-color: #111; 
            border-color: var(--primary); 
            color: #fff; 
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.3); 
        }

        /* Table Enhanced */
        .table-custom {
            width: 100%;
            border-collapse: separate; 
            border-spacing: 0;
            margin-bottom: 0;
        }
        .table-custom thead th {
            background-color: #2d2d2d;
            color: #fff;
            padding: 12px 15px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--primary);
        }
        .table-custom tbody tr {
            background-color: #1a1a1a;
            color: #ddd;
            transition: all 0.2s;
        }
        .table-custom tbody tr:nth-child(even) {
            background-color: #222;
        }
        .table-custom tbody tr:hover {
            background-color: #333;
            cursor: pointer;
            color: #fff;
        }
        .table-custom td {
            padding: 12px 15px;
            border-bottom: 1px solid #333;
            vertical-align: middle;
            font-size: 0.9rem;
        }

        /* Badges */
        .badge-client {
            background: rgba(34, 211, 238, 0.1);
            color: #22d3ee;
            border: 1px solid rgba(34, 211, 238, 0.3);
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 500;
        }
        .badge-event {
            background: rgba(168, 85, 247, 0.1);
            color: #c084fc;
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 0.8rem;
        }

        /* Modal Detail */
        .detail-row {
            padding: 10px 0;
            border-bottom: 1px solid #333;
            display: flex;
            justify-content: space-between;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #888; }
        .detail-val { color: #fff; font-weight: 600; text-align: right; }

        /* Pagination Links */
        .pagination-link {
            color: #888;
            padding: 5px 10px;
            border: 1px solid #444;
            margin: 0 2px;
            text-decoration: none;
            border-radius: 4px;
        }
        /* Premium Animations & Glassmorphism */
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulseGlow {
            0% { box-shadow: 0 0 5px rgba(99, 102, 241, 0.2); }
            50% { box-shadow: 0 0 20px rgba(99, 102, 241, 0.4); }
            100% { box-shadow: 0 0 5px rgba(99, 102, 241, 0.2); }
        }

        .animate-enter {
            animation: fadeSlideUp 0.5s ease-out forwards;
            opacity: 0; /* Init hidden */
        }

        /* Stagger delays */
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }

        .glass-panel {
            background: rgba(33, 37, 41, 0.85); /* bg-dark with opacity */
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .premium-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.4);
            transition: all 0.3s ease;
            cursor: pointer;
            border-color: var(--primary) !important;
        }

        /* Improved Table */
        .table-custom tr { transition: all 0.2s ease; }
        .table-custom tr:hover td {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            box-shadow: inset 4px 0 0 var(--primary);
        }
        
        /* Stats Cards */
        .kpi-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .kpi-card:hover { transform: scale(1.03); box-shadow: 0 15px 30px rgba(0,0,0,0.5); }

        .pagination-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 0 10px var(--primary);
        }

        /* Live Indicator Styles */
        .live-indicator {
            width: 10px;
            height: 10px;
            background: #22c55e;
            border-radius: 50%;
            animation: liveGlow 1.5s ease-in-out infinite;
            box-shadow: 0 0 8px #22c55e;
        }
        
        @keyframes liveGlow {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }
        
        .live-indicator.paused {
            background: #6b7280;
            animation: none;
            box-shadow: none;
        }
        
        #btnRealTime.btn-success {
            background: linear-gradient(135deg, #059669, #10b981);
            border: none;
            box-shadow: 0 2px 10px rgba(16, 185, 129, 0.3);
        }
        
        #btnRealTime.btn-secondary {
            background: linear-gradient(135deg, #374151, #4b5563);
            border: none;
        }
        
        /* Nueva transacción animación */
        @keyframes newRowHighlight {
            0% { background: rgba(34, 197, 94, 0.4); }
            100% { background: transparent; }
        }
        
        .new-transaction {
            animation: newRowHighlight 2s ease-out forwards;
        }
        
        /* Estilos para pestañas mejoradas */
        .nav-pills .nav-link {
            transition: all 0.3s ease;
        }
        
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, var(--primary), #818cf8) !important;
            color: white !important;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }
        
        .nav-pills .nav-link:not(.active):hover {
            background: rgba(99, 102, 241, 0.2);
            color: white !important;
        }
        
        /* Form controls grandes */
        .form-control-lg, .form-select-lg {
            padding: 0.75rem 1rem;
            font-size: 1rem;
            border-radius: 0.5rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25) !important;
        }
        
        /* Labels de formulario */
        .form-label {
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        /* Botones grandes mejorados */
        .btn-lg {
            padding: 0.75rem 1.25rem;
            font-size: 1rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .btn-lg:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        .btn-lg:active {
            transform: translateY(0);
        }
        
        /* ========= DATE INPUT CON BOTÓN CALENDARIO ========= */
        .date-picker-group {
            display: flex;
            align-items: stretch;
        }
        
        .date-picker-group input[type="date"] {
            color-scheme: dark;
            border-top-right-radius: 0 !important;
            border-bottom-right-radius: 0 !important;
        }
        
        .date-picker-group input[type="date"]::-webkit-calendar-picker-indicator {
            display: none;
        }
        
        .date-picker-group input[type="date"]::-webkit-datetime-edit,
        .date-picker-group input[type="date"]::-webkit-datetime-edit-fields-wrapper,
        .date-picker-group input[type="date"]::-webkit-datetime-edit-text,
        .date-picker-group input[type="date"]::-webkit-datetime-edit-month-field,
        .date-picker-group input[type="date"]::-webkit-datetime-edit-day-field,
        .date-picker-group input[type="date"]::-webkit-datetime-edit-year-field {
            color: #fff !important;
        }
        
        .date-picker-group input[type="date"]:not(:valid)::-webkit-datetime-edit {
            color: #666 !important;
        }
        
        .btn-calendar {
            background: linear-gradient(135deg, #f59e0b, #d97706) !important;
            border: 1px solid #f59e0b !important;
            color: #000 !important;
            font-size: 1.2rem;
            padding: 0 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            border-top-left-radius: 0 !important;
            border-bottom-left-radius: 0 !important;
        }
        
        .btn-calendar:hover {
            background: linear-gradient(135deg, #fbbf24, #f59e0b) !important;
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(245, 158, 11, 0.5);
        }
        
        .btn-calendar:active {
            transform: scale(0.98);
        }
    </style>
</head>
<body class="p-3">
    <div class="container-fluid">
        
        <!-- Header & Tabs -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center">
                <h3 class="fw-bold mb-0 text-white"><i class="bi bi-clock-history me-2" style="color:var(--primary)"></i>Centro de Actividad</h3>
                <span id="headerTotalCount" class="badge bg-primary ms-3 fs-6">0 registros</span>
            </div>
            
            <ul class="nav nav-pills bg-dark rounded-3 border border-secondary p-2 gap-2" id="pills-tab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active rounded-2 px-4 py-3 d-flex align-items-center gap-2 fw-bold" id="pills-historial-tab" data-bs-toggle="pill" data-bs-target="#pills-historial" style="font-size: 1.1rem;">
                        <i class="bi bi-list-ul fs-5"></i> Historial
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link rounded-2 px-4 py-3 d-flex align-items-center gap-2 fw-bold text-secondary" id="pills-stats-tab" data-bs-toggle="pill" data-bs-target="#pills-stats" style="font-size: 1.1rem;">
                        <i class="bi bi-bar-chart-fill fs-5"></i> Estadísticas
                    </button>
                </li>
            </ul>
        </div>

        <div class="tab-content" id="pills-tabContent">
            
            <!-- TAB HISTORIAL (MEJORADO) -->
            <div class="tab-pane fade show active" id="pills-historial">
                <div class="card p-0 overflow-hidden">
                    
                    <!-- Search Bar Toolbar - REDISEÑADO -->
                    <div class="bg-dark p-4 border-bottom border-secondary">
                        <div class="row g-3 align-items-end">
                            <!-- Búsqueda -->
                            <div class="col-lg-4 col-md-12">
                                <label class="form-label text-light fw-bold mb-2">
                                    <i class="bi bi-search me-1" style="color: #6366f1;"></i> Buscar
                                </label>
                                <div class="position-relative">
                                    <i class="bi bi-search position-absolute text-secondary" style="left: 15px; top: 50%; transform: translateY(-50%);"></i>
                                    <input type="text" id="searchInput" class="form-control form-control-lg ps-5 bg-black text-white border-secondary" placeholder="Cliente, vendedor, evento o acción..." autocomplete="off">
                                </div>
                            </div>
                            
                            <!-- Fecha Desde -->
                            <div class="col-lg-2 col-md-4 col-sm-6">
                                <label class="form-label text-light fw-bold mb-2">
                                    <i class="bi bi-calendar-event me-1" style="color: #f59e0b;"></i> Desde
                                </label>
                                <div class="date-picker-group">
                                    <input type="date" id="filterDesde" class="form-control form-control-lg bg-black text-white border-secondary">
                                    <button type="button" class="btn btn-calendar" onclick="document.getElementById('filterDesde').showPicker()" title="Abrir calendario">
                                        📅
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Fecha Hasta -->
                            <div class="col-lg-2 col-md-4 col-sm-6">
                                <label class="form-label text-light fw-bold mb-2">
                                    <i class="bi bi-calendar-check me-1" style="color: #f59e0b;"></i> Hasta
                                </label>
                                <div class="date-picker-group">
                                    <input type="date" id="filterHasta" class="form-control form-control-lg bg-black text-white border-secondary">
                                    <button type="button" class="btn btn-calendar" onclick="document.getElementById('filterHasta').showPicker()" title="Abrir calendario">
                                        📅
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Botones de Acción -->
                            <div class="col-lg-4 col-md-4 col-sm-12">
                                <div class="d-flex gap-2 flex-wrap justify-content-end">
                                    <button class="btn btn-primary btn-lg px-4" onclick="filtrarConFechas()" title="Aplicar Filtros">
                                        <i class="bi bi-funnel-fill me-1"></i> Filtrar
                                    </button>
                                    <button class="btn btn-outline-secondary btn-lg px-3" onclick="limpiarBusqueda()" title="Limpiar filtros">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                    <div class="vr bg-secondary mx-1"></div>
                                    <button id="btnRealTime" class="btn btn-success btn-lg d-flex align-items-center gap-2 px-3" onclick="toggleRealTime()" title="Actualización en tiempo real">
                                        <span class="live-indicator"></span>
                                        <i class="bi bi-broadcast"></i>
                                        <span id="realTimeLabel">LIVE</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced Table -->
                    <div class="table-responsive">
                        <table class="table-custom" id="transactionsTable">
                            <thead>
                                <tr>
                                    <th style="width: 200px;">Fecha/Hora</th>
                                    <th style="width: 150px;">Acción</th>
                                    <th>Descripción / Detalle</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyTransactions">
                                <tr><td colspan="3" class="text-center py-5 text-muted">Cargando transacciones...</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer Pagination -->
                    <div class="p-3 bg-dark border-top border-secondary d-flex justify-content-between align-items-center">
                        <small class="text-muted" id="resultsCount">Mostrando 0 resultados</small>
                        <div id="paginationContainer"></div>
                    </div>
                </div>
            </div>

            <!-- TAB ESTADISTICAS (PREMIUM - IFRAME) -->
            <div class="tab-pane fade" id="pills-stats">
                <div class="card bg-transparent border-0 p-0" style="margin: -1rem;">
                    <iframe id="statsIframe" src="estadisticas.php" style="width: 100%; height: calc(100vh - 100px); border: none; border-radius: 8px;"></iframe>
                </div>
            </div>

        </div>
    </div>

    <!-- Modal Detalle Rico -->
    <div class="modal fade" id="modalDetail" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white border-secondary shadow-lg">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title fw-bold"><i class="bi bi-receipt me-2 text-primary"></i>Detalle de Transacción</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" id="modalDetailBody">
                    <!-- Dynamic Content -->
                </div>
                <div class="modal-footer border-top border-secondary bg-black bg-opacity-25">
                    <button type="button" class="btn btn-sm btn-outline-light" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let debounceTimer;
        let currentPage = 1;
        let chartHoraInstance = null;
        let chartCategoriaInstance = null;
        let chartTipoInstance = null;
        
        // === TIEMPO REAL ===
        let realTimeEnabled = true;
        let realTimeInterval = null;
        let lastTransactionId = 0;
        const POLLING_INTERVAL = 5000; // 5 segundos
        
        // === TIEMPO REAL ESTADÍSTICAS ===
        let realTimeStatsEnabled = true;
        let realTimeStatsInterval = null;
        const POLLING_STATS_INTERVAL = 10000; // 10 segundos para estadísticas

        // Auto load
        document.addEventListener('DOMContentLoaded', () => {
            fetchTransactions();
            startRealTime(); // Iniciar tiempo real por defecto
            
            // El iframe de estadísticas maneja su propia actualización internamente
            // No interferir con él desde el padre
            
            // Detener polling de transacciones cuando se cambia de pestaña (opcional)
            const historialTab = document.getElementById('pills-historial-tab');
            historialTab.addEventListener('shown.bs.tab', () => {
                // Solo limpiar búsqueda de transacciones, no tocar estadísticas
            });
        });
        
        // === FUNCIONES PARA LIMPIAR FILTROS ===
        function limpiarFiltrosStats() {
            document.getElementById('statsFromDB').value = 'actual';
            document.getElementById('statsDesde').value = '';
            document.getElementById('statsHasta').value = '';
            document.getElementById('statsEvento').value = '';
        }
        
        // === FUNCIONES TIEMPO REAL ===
        function toggleRealTime() {
            realTimeEnabled = !realTimeEnabled;
            
            const btn = document.getElementById('btnRealTime');
            const label = document.getElementById('realTimeLabel');
            const indicator = btn.querySelector('.live-indicator');
            
            if (realTimeEnabled) {
                btn.classList.remove('btn-secondary');
                btn.classList.add('btn-success');
                label.textContent = 'LIVE';
                indicator.classList.remove('paused');
                startRealTime();
            } else {
                btn.classList.remove('btn-success');
                btn.classList.add('btn-secondary');
                label.textContent = 'PAUSADO';
                indicator.classList.add('paused');
                stopRealTime();
            }
        }
        
        function startRealTime() {
            if (realTimeInterval) clearInterval(realTimeInterval);
            realTimeInterval = setInterval(checkNewTransactions, POLLING_INTERVAL);
        }
        
        function stopRealTime() {
            if (realTimeInterval) {
                clearInterval(realTimeInterval);
                realTimeInterval = null;
            }
        }
        
        async function checkNewTransactions() {
            // Solo verificar nuevas transacciones si estamos en la página 1 y sin filtros
            const query = document.getElementById('searchInput').value;
            const desde = document.getElementById('filterDesde').value;
            const hasta = document.getElementById('filterHasta').value;
            
            if (currentPage !== 1 || query !== '' || desde !== '' || hasta !== '') {
                return; // No hacer polling si hay filtros activos o no estamos en página 1
            }
            
            try {
                const params = new URLSearchParams({
                    q: '',
                    fecha_desde: '',
                    fecha_hasta: '',
                    page: 1,
                    last_id: lastTransactionId
                });
                
                const res = await fetch(`api_search_transactions.php?${params.toString()}`);
                const data = await res.json();
                
                if (!data.success) return;
                
                // Si hay nuevas transacciones (id mayor al último conocido)
                if (data.data.length > 0 && data.data[0].id_transaccion > lastTransactionId) {
                    // Actualizar la tabla completa
                    fetchTransactions();
                }
            } catch (e) {
                console.error('Error checking new transactions:', e);
            }
        }
        
        // === FUNCIONES TIEMPO REAL ESTADÍSTICAS ===
        // Nota: El iframe de estadísticas maneja su propia actualización cada 30 segundos
        // Estas funciones están vacías para evitar conflictos
        function toggleRealTimeStats() {}
        function startRealTimeStats() {}
        function stopRealTimeStats() {}

        // Search Input Logic (from previous tasks) ...
        document.getElementById('searchInput').addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                currentPage = 1;
                fetchTransactions();
            }, 300);
        });

        function filtrarConFechas() {
            currentPage = 1;
            fetchTransactions();
        }

        function limpiarBusqueda() {
            document.getElementById('searchInput').value = '';
            document.getElementById('filterDesde').value = '';
            document.getElementById('filterHasta').value = '';
            currentPage = 1;
            fetchTransactions();
        }

        async function fetchTransactions() {
             // ... existing logic ...
            const query = document.getElementById('searchInput').value;
            const desde = document.getElementById('filterDesde').value;
            const hasta = document.getElementById('filterHasta').value;
            
            const tbody = document.getElementById('tbodyTransactions');
            const countLabel = document.getElementById('resultsCount');
            const pagContainer = document.getElementById('paginationContainer');

            if(query === '' && desde === '' && hasta === '' && currentPage === 1) tbody.innerHTML = '<tr><td colspan="3" class="text-center py-5 text-muted"><div class="spinner-border text-primary mb-2"></div><br>Cargando...</td></tr>';
            
            const params = new URLSearchParams({
                q: query,
                fecha_desde: desde,
                fecha_hasta: hasta,
                page: currentPage
            });

            try {
                const res = await fetch(`api_search_transactions.php?${params.toString()}`);
                const data = await res.json();
                
                if (!data.success) throw new Error(data.error);

                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center py-5 text-muted">No se encontraron transacciones.</td></tr>';
                    countLabel.innerText = '0 resultados';
                    pagContainer.innerHTML = '';
                    return;
                }

                tbody.innerHTML = data.data.map((t, index) => {
                    const extra = t.extra || {};
                    const dateObj = new Date(t.fecha_hora);
                    const dateStr = dateObj.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + 
                                  dateObj.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });

                    let badgeClass = 'bg-secondary';
                    if(t.accion === 'venta') badgeClass = 'bg-success';
                    if(t.accion === 'login') badgeClass = 'bg-primary';
                    if(t.accion === 'logout') badgeClass = 'bg-danger';

                    return `
                        <tr onclick='openDetail(${JSON.stringify(t)})' class="animate-enter premium-hover" style="animation-delay: ${index * 0.05}s">
                            <td class="text-white fw-medium">${dateStr}</td>
                            <td><span class="badge ${badgeClass} bg-opacity-75 text-white shadow-sm" style="font-weight:400; letter-spacing:0.5px;">${t.accion.toUpperCase()}</span></td>
                            <td>
                                <div class="text-light">${t.descripcion.length > 80 ? t.descripcion.substring(0,80)+'...' : t.descripcion}</div>
                            </td>
                        </tr>
                    `;
                }).join('');

                countLabel.innerText = `Total: ${data.pagination.total} registros | Pág ${data.pagination.page} de ${data.pagination.pages}`;
                document.getElementById('headerTotalCount').innerText = data.pagination.total + ' registros';
                renderPagination(data.pagination.page, data.pagination.pages);
                
                // Guardar el último ID para el polling en tiempo real
                if (data.data.length > 0 && currentPage === 1) {
                    lastTransactionId = data.data[0].id_transaccion;
                }

            } catch (e) {
                console.error(e);
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-5">Error al cargar datos.</td></tr>';
            }
        }

        async function goToPage(p) {
            currentPage = p;
            fetchTransactions();
        }

        function renderPagination(curr, total) {
             // ... existing logic ...
            const container = document.getElementById('paginationContainer');
            let html = '';
            if (total > 1) {
                if (curr > 1) html += `<a href="#" class="pagination-link" onclick="goToPage(${curr-1})">&laquo;</a>`;
                let start = Math.max(1, curr - 2);
                let end = Math.min(total, curr + 2);
                for(let i=start; i<=end; i++) {
                     html += `<a href="#" class="pagination-link ${i===curr?'active':''}" onclick="goToPage(${i})">${i}</a>`;
                }
                if (curr < total) html += `<a href="#" class="pagination-link" onclick="goToPage(${curr+1})">&raquo;</a>`;
            }
            container.innerHTML = html;
        }

        function openDetail(t) {
             // ... existing logic ...
            const extra = t.extra || {};
            const modal = new bootstrap.Modal(document.getElementById('modalDetail'));
            const body = document.getElementById('modalDetailBody');

            let contenidoHTML = `
                <div class="detail-row"><span class="detail-label">ID Transacción</span><span class="detail-val">#${t.id_transaccion}</span></div>
                <div class="detail-row"><span class="detail-label">Fecha</span><span class="detail-val">${t.fecha_hora}</span></div>
                <div class="detail-row"><span class="detail-label">Vendedor</span><span class="detail-val">${t.nombre} ${t.apellido}</span></div>
                <div class="detail-row"><span class="detail-label">Acción</span><span class="detail-val text-uppercase text-primary">${t.accion}</span></div>
                <div class="mt-3 mb-2 border-top border-secondary pt-2 text-muted small">DESCRIPCIÓN</div>
                <p class="text-white small">${t.descripcion}</p>
            `;

            if (t.accion === 'venta' && extra.cantidad) {
                contenidoHTML += `
                    <div class="bg-black bg-opacity-50 p-3 rounded mt-3">
                        <div class="detail-row"><span class="detail-label">Evento</span><span class="detail-val text-warning">${extra.evento || 'N/A'}</span></div>
                        <div class="detail-row"><span class="detail-label">Cliente</span><span class="detail-val text-info">${extra.cliente || 'Anónimo'}</span></div>
                        <div class="detail-row"><span class="detail-label">Boletos</span><span class="detail-val">${extra.cantidad}</span></div>
                        <div class="detail-row mt-2 pt-2 border-top border-secondary"><span class="detail-label">TOTAL COBRADO</span><span class="detail-val text-success fs-5">$${parseFloat(extra.total||0).toFixed(2)}</span></div>
                    </div>
                `;
                
                if (extra.boletos_detalle) {
                    contenidoHTML += `<div class="mt-3"><small class="text-muted">Desglose de Asientos:</small><div class="d-flex flex-wrap gap-2 mt-1">`;
                    extra.boletos_detalle.forEach(b => {
                        contenidoHTML += `<span class="badge bg-secondary border border-secondary">${b.asiento} ($${b.precio})</span>`;
                    });
                    contenidoHTML += `</div></div>`;
                }
            }
            body.innerHTML = contenidoHTML;
            modal.show();
        }

        // === ESTADÍSTICAS - Manejadas completamente por el iframe ===
        // El iframe tiene su propio sistema de actualización cada 30 segundos
        // Estas funciones están vacías para no interferir con los filtros del usuario
        function cargarEstadisticas() {
            // No hacer nada - el iframe se actualiza solo
        }
        
        function limpiarFiltrosStats() {
            // No hacer nada - el iframe tiene su propio botón de limpiar
        }
        
        function toggleRealTimeStats() {
            // Manejado internamente por el iframe
        }
        
        function descargarPDF() {
            // Abrir la ventana de PDF
            window.open('generar_pdf.php', '_blank');
        }
    </script>
</body>
</html>
