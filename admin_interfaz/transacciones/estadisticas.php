<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}
require_once '../../evt_interfaz/conexion.php';

// Obtener TODOS los eventos de la base actual (trt_25)
$eventos_actual = [];
$res = $conn->query("SELECT id_evento, titulo FROM evento ORDER BY id_evento DESC");
if ($res) while ($r = $res->fetch_assoc()) $eventos_actual[] = $r;

// Intentar obtener eventos de la base histórica
$eventos_historico = [];
$check_hist = $conn->query("SHOW DATABASES LIKE 'trt_historico_evento'");
if ($check_hist && $check_hist->num_rows > 0) {
    $res_hist = $conn->query("SELECT id_evento, titulo FROM trt_historico_evento.evento ORDER BY id_evento DESC");
    if ($res_hist) while ($r = $res_hist->fetch_assoc()) $eventos_historico[] = $r;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Premium - Teatro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" integrity="sha512-dPXYcDub/aeb08c63jRq/k6GqJ6SZlWgIz2NNZZiP9RXXpR6+8E/gVBbBQs8rY7xMz5p5yUB78/5Q1xQHcGQ4g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="premium_dashboard.css">
    <style>
        /* Estilos adicionales para la barra de filtros */
        .filter-bar {
            background: linear-gradient(145deg, rgba(30, 30, 42, 0.95) 0%, rgba(20, 20, 30, 0.98) 100%);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .filter-label {
            color: #f8fafc;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .filter-label i {
            font-size: 1rem;
        }
        .filter-select, .filter-input {
            background: rgba(0, 0, 0, 0.5) !important;
            border: 2px solid rgba(99, 102, 241, 0.3) !important;
            color: #fff !important;
            border-radius: 10px !important;
            padding: 0.75rem 1rem !important;
            font-size: 1rem !important;
            transition: all 0.3s ease !important;
        }
        .filter-select:focus, .filter-input:focus {
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25) !important;
            outline: none !important;
        }
        .filter-select option {
            background: #1a1a24;
            color: #fff;
        }
        .btn-filter {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-filter-clear {
            background: rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .btn-filter-clear:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
        }
        .filter-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #10b981;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .filter-status .spinner-border {
            width: 1rem;
            height: 1rem;
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
            color: #64748b !important;
        }
        
        .btn-calendar {
            background: linear-gradient(135deg, #22d3ee, #0891b2) !important;
            border: 1px solid #22d3ee !important;
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
            background: linear-gradient(135deg, #67e8f9, #22d3ee) !important;
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(34, 211, 238, 0.5);
        }
        
        .btn-calendar:active {
            transform: scale(0.98);
        }
    </style>
</head>
<body class="p-4">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1" style="letter-spacing: -0.5px;">
                    <i class="bi bi-graph-up-arrow me-2" style="color: var(--primary-light);"></i>
                    Centro de Estadísticas
                </h2>
                <p class="text-secondary mb-0">Análisis completo de rendimiento del teatro</p>
            </div>
            <div class="d-flex gap-3 align-items-center">
                <span class="live-badge" id="liveBadge">
                    <span class="live-dot"></span>
                    EN VIVO
                </span>
                <button class="btn-premium danger" onclick="descargarPDF()">
                    <i class="bi bi-file-pdf"></i> PDF
                </button>
            </div>
        </div>

        <!-- BARRA DE FILTROS REDISEÑADA -->
        <div class="filter-bar">
            <div class="row g-3 align-items-end">
                <!-- Base de Datos -->
                <div class="col-lg-2 col-md-4">
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="bi bi-database-fill" style="color: #6366f1;"></i>
                            Base de Datos
                        </label>
                        <select id="statsDB" class="filter-select" onchange="onCambioBaseDatos()">
                            <option value="actual">📊 Actual</option>
                            <option value="historico">📁 Histórico</option>
                            <option value="ambas">📦 Ambas</option>
                        </select>
                    </div>
                </div>
                
                <!-- Evento -->
                <div class="col-lg-3 col-md-4">
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="bi bi-film" style="color: #a855f7;"></i>
                            Evento
                        </label>
                        <select id="statsEvento" class="filter-select" onchange="onEventoChange()">
                            <option value="">🎭 Todos los Eventos</option>
                            <?php foreach($eventos_actual as $ev): ?>
                                <option value="<?= $ev['id_evento'] ?>"><?= htmlspecialchars($ev['titulo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Función (NUEVO) -->
                <div class="col-lg-3 col-md-4">
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="bi bi-clock" style="color: #f43f5e;"></i>
                            Función
                        </label>
                        <select id="statsFuncion" class="filter-select" onchange="cargarEstadisticas()">
                            <option value="">🕒 Todas las Funciones</option>
                        </select>
                    </div>
                </div>
                
                <!-- Fecha Desde -->
                <div class="col-lg-2 col-md-4">
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="bi bi-calendar-event" style="color: #22d3ee;"></i>
                            Desde
                        </label>
                        <div class="date-picker-group">
                            <input type="date" id="statsDesde" class="filter-input" onchange="cargarEstadisticas()">
                            <button type="button" class="btn btn-calendar" onclick="document.getElementById('statsDesde').showPicker()" title="Abrir calendario">
                                📅
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Fecha Hasta -->
                <div class="col-lg-2 col-md-4">
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="bi bi-calendar-check" style="color: #22d3ee;"></i>
                            Hasta
                        </label>
                        <div class="date-picker-group">
                            <input type="date" id="statsHasta" class="filter-input" onchange="cargarEstadisticas()">
                            <button type="button" class="btn btn-calendar" onclick="document.getElementById('statsHasta').showPicker()" title="Abrir calendario">
                                📅
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Botones y Estado -->
                <div class="col-lg-3 col-md-8">
                    <div class="d-flex gap-2 align-items-center justify-content-end">
                        <span id="filterStatus" class="filter-status">
                            <i class="bi bi-check-circle-fill"></i> Listo
                        </span>
                        <button class="btn-filter btn-filter-clear" onclick="limpiarFiltros()">
                            <i class="bi bi-x-circle"></i> Limpiar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Almacenar eventos para JavaScript -->
        <script>
            const eventosActual = <?= json_encode($eventos_actual) ?>;
            const eventosHistorico = <?= json_encode($eventos_historico) ?>;
            console.log('Eventos Actual:', eventosActual.length, 'Eventos Histórico:', eventosHistorico.length);
        </script>

        <!-- KPIs Row 1 -->
        <div class="row g-4 mb-4">
            <div class="col-md-2">
                <div class="kpi-card kpi-ingresos animate-in delay-1">
                    <div class="kpi-label">Ingresos Totales</div>
                    <div class="kpi-value text-white" id="kpiIngresos">$0</div>
                    <i class="bi bi-currency-dollar kpi-icon"></i>
                </div>
            </div>
            <div class="col-md-2">
                <div class="kpi-card kpi-boletos animate-in delay-2">
                    <div class="kpi-label">Boletos Vendidos</div>
                    <div class="kpi-value text-white" id="kpiBoletos">0</div>
                    <i class="bi bi-ticket-perforated kpi-icon"></i>
                </div>
            </div>
            <div class="col-md-2">
                <div class="kpi-card kpi-promedio animate-in delay-3">
                    <div class="kpi-label">Ticket Promedio</div>
                    <div class="kpi-value text-white" id="kpiPromedio">$0</div>
                    <i class="bi bi-graph-up kpi-icon"></i>
                </div>
            </div>
            <div class="col-md-2">
                <div class="kpi-card kpi-ocupacion animate-in delay-4">
                    <div class="kpi-label">Ocupación</div>
                    <div class="kpi-value text-white" id="kpiOcupacion">0%</div>
                    <i class="bi bi-people-fill kpi-icon"></i>
                </div>
            </div>
            <div class="col-md-2">
                <div class="kpi-card kpi-eventos animate-in delay-5">
                    <div class="kpi-label">Eventos Activos</div>
                    <div class="kpi-value text-white" id="kpiEventos">0</div>
                    <i class="bi bi-calendar-star kpi-icon"></i>
                </div>
            </div>
            <div class="col-md-2">
                <div class="kpi-card kpi-funciones animate-in delay-5">
                    <div class="kpi-label">Funciones</div>
                    <div class="kpi-value text-white" id="kpiFunciones">0</div>
                    <i class="bi bi-collection-play kpi-icon"></i>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <!-- Ventas por Hora -->
            <div class="col-lg-5">
                <div class="glass-card h-100">
                    <div class="section-header">
                        <i class="bi bi-clock-history"></i>
                        <h6>Actividad por Hora</h6>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartHora"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Por Día de Semana -->
            <div class="col-lg-4">
                <div class="glass-card h-100">
                    <div class="section-header">
                        <i class="bi bi-calendar-week"></i>
                        <h6>Ventas por Día</h6>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartDia"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Por Horario -->
            <div class="col-lg-3">
                <div class="glass-card h-100">
                    <div class="section-header">
                        <i class="bi bi-sun"></i>
                        <h6>Por Franja Horaria</h6>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartHorario"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Second Row -->
        <div class="row g-4 mb-4">
            <!-- Categorías -->
            <div class="col-lg-3">
                <div class="glass-card h-100">
                    <div class="section-header">
                        <i class="bi bi-pie-chart-fill"></i>
                        <h6>Por Categoría</h6>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartCategoria"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Tipo Boleto -->
            <div class="col-lg-3">
                <div class="glass-card h-100">
                    <div class="section-header">
                        <i class="bi bi-tags-fill"></i>
                        <h6>Por Tipo de Boleto</h6>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartTipo"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Tendencia Mensual -->
            <div class="col-lg-6">
                <div class="glass-card h-100">
                    <div class="section-header">
                        <i class="bi bi-graph-up-arrow"></i>
                        <h6>Tendencia de Ingresos</h6>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartTendencia"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Third Row - Tables -->
        <div class="row g-4 mb-4">
            <!-- Ranking Eventos -->
            <div class="col-lg-6">
                <div class="glass-card h-100">
                    <div class="section-header">
                        <i class="bi bi-trophy-fill" style="color: #fbbf24;"></i>
                        <h6>Ranking de Obras</h6>
                    </div>
                    <div class="p-0">
                        <table class="ranking-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Evento</th>
                                    <th class="text-center">Boletos</th>
                                    <th class="text-center">Funciones</th>
                                    <th class="text-end">Ingresos</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyRanking"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Top Vendedores -->
            <div class="col-lg-3">
                <div class="glass-card h-100">
                    <div class="section-header">
                        <i class="bi bi-person-badge-fill" style="color: #10b981;"></i>
                        <h6>Top Vendedores</h6>
                    </div>
                    <div class="p-3" id="containerVendedores"></div>
                </div>
            </div>
            
            <!-- Alertas y Ocupación -->
            <div class="col-lg-3">
                <div class="glass-card h-100">
                    <div class="section-header">
                        <i class="bi bi-exclamation-triangle-fill" style="color: #f59e0b;"></i>
                        <h6>Alertas</h6>
                    </div>
                    <div class="p-3" id="containerAlertas">
                        <p class="text-muted text-center small">Sin alertas</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ocupación por Función -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="glass-card">
                    <div class="section-header">
                        <i class="bi bi-person-lines-fill" style="color: #22d3ee;"></i>
                        <h6>Ocupación por Función</h6>
                    </div>
                    <div class="p-3">
                        <div class="row g-3" id="containerOcupacion"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Chart instances
        let charts = {};
        const colors = {
            primary: '#6366f1', accent: '#22d3ee', purple: '#a855f7',
            pink: '#ec4899', emerald: '#10b981', amber: '#f59e0b',
            rose: '#f43f5e', blue: '#3b82f6'
        };

        let debounceTimer;
        
        // Cargar estadísticas al iniciar
        document.addEventListener('DOMContentLoaded', () => {
            cargarEstadisticas();
            // Actualizar automáticamente cada 2 minutos
            setInterval(cargarEstadisticas, 120000);
        });
        
        // Función para cambiar la base de datos
        function onCambioBaseDatos() {
            const db = document.getElementById('statsDB').value;
            const select = document.getElementById('statsEvento');
            
            console.log('Cambiando a base de datos:', db);
            
            // Limpiar y repoblar el dropdown de eventos
            select.innerHTML = '<option value="">🎭 Todos los Eventos</option>';
            document.getElementById('statsFuncion').innerHTML = '<option value="">🕒 Todas las Funciones</option>';
            
            let eventos = [];
            if (db === 'actual') {
                eventos = eventosActual || [];
            } else if (db === 'historico') {
                eventos = eventosHistorico || [];
                if (eventos.length === 0) {
                    select.innerHTML += '<option disabled>-- No hay eventos históricos --</option>';
                }
            } else { // ambas
                eventos = [...(eventosActual || [])];
                // Agregar históricos que no estén ya
                (eventosHistorico || []).forEach(evH => {
                    const existe = eventos.some(e => e.id_evento == evH.id_evento && e.titulo == evH.titulo);
                    if (!existe) eventos.push({...evH, esHistorico: true});
                });
            }
            
            console.log('Eventos disponibles:', eventos.length);
            
            // Agregar opciones al select
            eventos.forEach(ev => {
                const sufijo = ev.esHistorico ? ' (Hist.)' : '';
                select.innerHTML += `<option value="${ev.id_evento}">${ev.titulo}${sufijo}</option>`;
            });
            
            // Cargar estadísticas con el nuevo filtro
            cargarEstadisticas();
        }
        
        // Función para limpiar todos los filtros
        function limpiarFiltros() {
            document.getElementById('statsDB').value = 'actual';
            document.getElementById('statsDesde').value = '';
            document.getElementById('statsHasta').value = '';
            onCambioBaseDatos(); // Esto recarga el dropdown de eventos y llama a cargarEstadisticas
        }
        
        // Mostrar estado de carga
        async function onEventoChange() {
            const idEvento = document.getElementById('statsEvento').value;
            const dbRef = document.getElementById('statsDB').value;
            const comboFunc = document.getElementById('statsFuncion');
            
            // Limpiar combo funciones
            comboFunc.innerHTML = '<option value="">🕒 Todas las Funciones</option>';
            
            if (idEvento) {
                // Cargar funciones via AJAX
                try {
                    const res = await fetch(`api_get_funciones.php?id_evento=${idEvento}&db=${dbRef}`);
                    const funcs = await res.json();
                    
                    if (funcs && funcs.length > 0) {
                        funcs.forEach(f => {
                            comboFunc.innerHTML += `<option value="${f.id}">${f.label}</option>`;
                        });
                    }
                } catch(e) {
                    console.error("Error cargando funciones", e);
                }
            }
            
            cargarEstadisticas();
        }

        function mostrarCargando(cargando) {
            const status = document.getElementById('filterStatus');
            if (cargando) {
                status.innerHTML = '<div class="spinner-border spinner-border-sm text-primary"></div> Cargando...';
                status.style.color = '#6366f1';
            } else {
                status.innerHTML = '<i class="bi bi-check-circle-fill"></i> Listo';
                status.style.color = '#10b981';
            }
        }

        async function cargarEstadisticas() {
            mostrarCargando(true);
            
            const params = new URLSearchParams({
                db: document.getElementById('statsDB').value,
                id_evento: document.getElementById('statsEvento').value,
                id_funcion: document.getElementById('statsFuncion').value,
                fecha_desde: document.getElementById('statsDesde').value,
                fecha_hasta: document.getElementById('statsHasta').value
            });

            try {
                const res = await fetch(`api_stats_premium.php?${params}`);
                const r = await res.json();
                
                if (!r.success) {
                    console.error('Error API:', r.error);
                    mostrarCargando(false);
                    return;
                }
                
                const d = r.data;
                
                // KPIs
                animateKPI('kpiIngresos', d.resumen.total_ingresos, '$');
                
                // BOLETOS VENDIDOS: Mostrar formato "Vendidos / Capacidad"
                // Usamos d.ocupacion.general.vendidos y d.ocupacion.general.capacidad (o d.resumen para vendidos si preferimos filtro fecha)
                // Usuario pidió "120/420". Usaremos los datos globales de ocupación para la capacidad.
                // Nota: d.resumen.total_boletos tiene filtro de fecha. d.ocupacion.general.vendidos es global.
                // Si el usuario quiere ver "vendidos en rango / capacidad total del evento", usamos resumen vs general.capacidad.
                // Generalmente "120/420" implica contexto global de ocupación. Si hay filtro de fecha, "10/420" es correcto.
                
                const boletosTexto = `${d.resumen.total_boletos.toLocaleString()} / ${d.ocupacion.general.capacidad.toLocaleString()}`;
                document.getElementById('kpiBoletos').textContent = boletosTexto;
                
                animateKPI('kpiPromedio', d.resumen.ticket_promedio, '$');
                animateKPI('kpiOcupacion', d.ocupacion.general.porcentaje_ocupacion, '', '%');
                animateKPI('kpiEventos', d.resumen.total_eventos);
                animateKPI('kpiFunciones', d.resumen.total_funciones);
                
                // Charts
                renderChartHora(d.tendencias.por_hora);
                renderChartDia(d.ocupacion.por_dia);
                renderChartHorario(d.ocupacion.por_horario);
                renderChartCategoria(d.ingresos.por_categoria);
                renderChartTipo(d.ingresos.por_tipo);
                renderChartTendencia(d.ingresos.mensuales);
                
                // Tables
                renderRanking(d.rendimiento.ranking);
                renderVendedores(d.vendedores);
                renderAlertas(d.rendimiento.alertas);
                renderOcupacion(d.ocupacion.por_funcion);
                
                mostrarCargando(false);
                
            } catch (e) {
                console.error('Error:', e);
                mostrarCargando(false);
            }
        }

        function animateKPI(id, value, prefix = '', suffix = '') {
            const el = document.getElementById(id);
            const formatted = typeof value === 'number' ? 
                (prefix === '$' ? value.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 
                value.toLocaleString('es-MX')) : value;
            el.textContent = prefix + formatted + suffix;
        }

        function renderChartHora(data) {
            const ctx = document.getElementById('chartHora');
            if (charts.hora) charts.hora.destroy();
            
            const labels = Array.from({length: 24}, (_, i) => `${i}:00`);
            const values = new Array(24).fill(0);
            data.forEach(d => values[parseInt(d.hora)] = parseFloat(d.ingresos));
            
            charts.hora = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Ingresos',
                        data: values,
                        backgroundColor: colors.primary,
                        borderRadius: 4
                    }]
                },
                options: chartOptions()
            });
        }

        function renderChartDia(data) {
            const ctx = document.getElementById('chartDia');
            if (charts.dia) charts.dia.destroy();
            
            charts.dia = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.dia),
                    datasets: [{
                        label: 'Ingresos',
                        data: data.map(d => parseFloat(d.ingresos)),
                        backgroundColor: [colors.rose, colors.amber, colors.emerald, colors.accent, colors.purple, colors.primary, colors.pink]
                    }]
                },
                options: chartOptions()
            });
        }

        function renderChartHorario(data) {
            const ctx = document.getElementById('chartHorario');
            if (charts.horario) charts.horario.destroy();
            
            charts.horario = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.map(d => d.franja),
                    datasets: [{
                        data: data.map(d => d.cantidad),
                        backgroundColor: [colors.amber, colors.accent, colors.purple]
                    }]
                },
                options: { ...chartOptions(), cutout: '60%' }
            });
        }

        function renderChartCategoria(data) {
            const ctx = document.getElementById('chartCategoria');
            if (charts.categoria) charts.categoria.destroy();
            
            charts.categoria = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.map(d => d.nombre_categoria),
                    datasets: [{
                        data: data.map(d => d.cantidad),
                        backgroundColor: [colors.accent, colors.purple, colors.pink, colors.emerald, colors.amber]
                    }]
                },
                options: { ...chartOptions(), cutout: '55%' }
            });
        }

        function renderChartTipo(data) {
            const ctx = document.getElementById('chartTipo');
            if (charts.tipo) charts.tipo.destroy();
            
            charts.tipo = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.map(d => d.tipo),
                    datasets: [{
                        data: data.map(d => d.cantidad),
                        backgroundColor: [colors.emerald, colors.rose, colors.blue, colors.amber]
                    }]
                },
                options: chartOptions()
            });
        }

        function renderChartTendencia(data) {
            const ctx = document.getElementById('chartTendencia');
            if (charts.tendencia) charts.tendencia.destroy();
            
            charts.tendencia = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.mes),
                    datasets: [{
                        label: 'Ingresos',
                        data: data.map(d => parseFloat(d.ingresos)),
                        borderColor: colors.primary,
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: chartOptions()
            });
        }

        function chartOptions() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'bottom', 
                        labels: { color: '#94a3b8', padding: 15, usePointStyle: true }
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: '#64748b' }
                    },
                    x: { 
                        grid: { display: false },
                        ticks: { color: '#64748b', maxTicksLimit: 12 }
                    }
                }
            };
        }

        function renderRanking(data) {
            const tbody = document.getElementById('tbodyRanking');
            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Sin datos</td></tr>';
                return;
            }
            tbody.innerHTML = data.slice(0, 10).map(ev => `
                <tr>
                    <td><span class="rank-badge ${ev.rank <= 3 ? 'rank-' + ev.rank : 'rank-default'}">${ev.rank}</span></td>
                    <td class="fw-semibold text-white">${ev.titulo}</td>
                    <td class="text-center"><span class="badge bg-secondary">${ev.boletos}</span></td>
                    <td class="text-center text-muted">${ev.funciones}</td>
                    <td class="text-end text-success fw-bold">$${parseFloat(ev.ingresos).toLocaleString('es-MX', {minimumFractionDigits: 2})}</td>
                </tr>
            `).join('');
        }

        function renderVendedores(data) {
            const container = document.getElementById('containerVendedores');
            if (!data.length) {
                container.innerHTML = '<p class="text-muted text-center small">Sin datos</p>';
                return;
            }
            container.innerHTML = data.slice(0, 5).map((v, i) => `
                <div class="vendedor-row">
                    <div class="vendedor-info">
                        <div class="vendedor-avatar">${v.vendedor.charAt(0)}</div>
                        <div>
                            <div class="fw-semibold text-white small">${v.vendedor}</div>
                            <div class="text-muted" style="font-size: 0.7rem;">${v.boletos} boletos</div>
                        </div>
                    </div>
                    <div class="text-success fw-bold">$${parseFloat(v.ingresos).toLocaleString('es-MX')}</div>
                </div>
            `).join('');
        }

        function renderAlertas(data) {
            const container = document.getElementById('containerAlertas');
            if (!data.length) {
                container.innerHTML = '<p class="text-muted text-center small py-3"><i class="bi bi-check-circle me-2"></i>Todo en orden</p>';
                return;
            }
            container.innerHTML = data.map(a => `
                <div class="alert-card ${a.porcentaje < 15 ? 'danger' : 'warning'}">
                    <i class="bi bi-exclamation-triangle"></i>
                    <div>
                        <div class="fw-semibold small">${a.evento}</div>
                        <div class="text-muted" style="font-size: 0.7rem;">${a.fecha} - Solo ${a.porcentaje}% ocupación</div>
                    </div>
                </div>
            `).join('');
        }

        function renderOcupacion(data) {
            const container = document.getElementById('containerOcupacion');
            if (!data.length) {
                container.innerHTML = '<p class="text-muted text-center col-12">Sin funciones activas</p>';
                return;
            }
            container.innerHTML = data.slice(0, 8).map(f => {
                // Determinar color y valor exacto
                const pct = parseFloat(f.porcentaje);
                const color = pct >= 80 ? '#10b981' : pct >= 40 ? '#f59e0b' : '#ef4444';
                
                return `
                    <div class="col-md-3">
                        <div class="p-3 rounded-3" style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.05);">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold text-white small text-truncate" title="${f.evento}" style="max-width: 70%;">${f.evento}</span>
                                <span class="badge" style="background-color: ${color}">${pct}%</span>
                            </div>
                            
                            <div class="progress position-relative" style="height: 18px; background-color: rgba(255,255,255,0.1); border-radius: 10px;">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: ${pct}%; background-color: ${color};"
                                     aria-valuenow="${pct}" aria-valuemin="0" aria-valuemax="100">
                                </div>
                                <div class="position-absolute start-0 top-0 w-100 h-100 d-flex align-items-center justify-content-center text-white" 
                                     style="font-size: 0.7rem; font-weight: 600; text-shadow: 0 1px 2px rgba(0,0,0,0.8); pointer-events: none;">
                                     <i class="bi bi-calendar3 me-1" style="font-size: 0.65rem;"></i> ${f.fecha} - ${f.hora}
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-2" style="font-size: 0.7rem;">
                                <span class="text-muted">${f.vendidos} vendidos</span>
                                <span class="text-muted">${f.capacidad} total</span>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function descargarPDF() {
            const params = new URLSearchParams({
                db: document.getElementById('statsDB').value,
                id_evento: document.getElementById('statsEvento').value,
                id_funcion: document.getElementById('statsFuncion').value,
                fecha_desde: document.getElementById('statsDesde').value,
                fecha_hasta: document.getElementById('statsHasta').value
            });
            window.open(`generar_pdf.php?${params}`, '_blank');
        }
    </script>
</body>
</html>
