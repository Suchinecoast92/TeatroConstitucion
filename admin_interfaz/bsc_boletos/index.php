<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SESSION['usuario_rol'] !== 'admin') {
    if (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']) {
        die('<html><head><link rel="stylesheet" href="../../assets/css/teatro-style.css"></head>
        <body style="display:flex;justify-content:center;align-items:center;height:100vh;">
        <div style="text-align:center;color:var(--danger);"><p>Acceso denegado</p></div></body></html>');
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscador de Boletos</title>
    <link rel="icon" href="../../crt_interfaz/imagenes_teatro/nat.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../assets/css/teatro-style.css">
    <!-- QZ Tray para impresión térmica directa -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsencrypt/2.3.1/jsencrypt.min.js"></script>
    <script src="../../vnt_interfaz/js/qz-tray.js"></script>
    <script src="../../vnt_interfaz/js/qz_interface.js"></script>
    <!-- HTML5 QR Code Scanner -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        body {
            background: var(--bg-primary);
            padding: 24px;
            overflow-y: auto;
        }

        .search-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .search-header {
            background: var(--bg-secondary);
            padding: 24px;
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
        }

        .search-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .search-header p {
            color: var(--text-muted);
            margin: 0;
            font-size: 0.9rem;
        }

        .header-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
        }

        /* Barra de búsqueda y filtros */
        .search-controls {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: var(--radius-lg);
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .search-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
        }

        .search-input-wrapper {
            flex: 1;
            position: relative;
        }

        .search-input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .search-input {
            width: 100%;
            padding: 14px 16px 14px 46px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            color: var(--text-primary);
            font-size: 0.95rem;
            transition: var(--transition-fast);
        }

        .search-input:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
        }

        .btn-clear {
            padding: 14px 20px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition-fast);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-clear:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }

        /* Filtros */
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .filter-select {
            padding: 10px 14px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            color: var(--text-primary);
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition-fast);
        }

        .filter-select:focus {
            outline: none;
            border-color: #8b5cf6;
        }

        /* Estadísticas */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: var(--bg-secondary);
            padding: 16px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .stat-icon.blue {
            background: rgba(21, 97, 240, 0.15);
            color: var(--accent-blue);
        }

        .stat-icon.green {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }

        .stat-icon.yellow {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
        }

        .stat-icon.red {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
        }

        .stat-content {
            flex: 1;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Tabla de resultados */
        .results-container {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .results-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .results-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .results-count {
            font-size: 0.85rem;
            color: var(--text-muted);
            background: var(--bg-tertiary);
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-weight: 600;
        }

        .table-wrapper {
            overflow-x: auto;
            max-height: 600px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            position: sticky;
            top: 0;
            background: var(--bg-tertiary);
            z-index: 10;
        }

        th {
            padding: 12px 16px;
            text-align: left;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 14px 16px;
            color: var(--text-primary);
            font-size: 0.9rem;
            border-bottom: 1px solid var(--border-color);
        }

        tbody tr {
            transition: var(--transition-fast);
        }

        tbody tr:hover {
            background: var(--bg-tertiary);
        }

        /* Estados */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: var(--radius-full);
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-badge.activo {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }

        .status-badge.usado {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
        }

        .status-badge.cancelado {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
        }

        .status-badge.archivado {
            background: rgba(107, 114, 128, 0.15);
            color: #6b7280;
        }

        /* Acciones */
        .actions {
            display: flex;
            gap: 6px;
        }

        .btn-action {
            padding: 8px 12px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition-fast);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
        }

        .btn-view {
            background: rgba(21, 97, 240, 0.15);
            color: var(--accent-blue);
        }

        .btn-view:hover {
            background: rgba(21, 97, 240, 0.25);
        }

        .btn-print {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }

        .btn-print:hover {
            background: rgba(16, 185, 129, 0.25);
        }

        .btn-cancel {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
        }

        .btn-cancel:hover {
            background: rgba(239, 68, 68, 0.25);
        }

        .btn-action:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Empty state */
        .empty-state {
            padding: 60px 20px;
            text-align: center;
        }

        .empty-icon {
            font-size: 4rem;
            color: var(--text-muted);
            opacity: 0.3;
            margin-bottom: 16px;
        }

        .empty-text {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        /* Loading */
        .loading {
            padding: 40px;
            text-align: center;
            color: var(--text-muted);
        }

        .spinner {
            display: inline-block;
            width: 32px;
            height: 32px;
            border: 3px solid var(--border-color);
            border-top-color: #8b5cf6;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: var(--bg-secondary);
            border-radius: var(--radius-xl);
            width: 90%;
            max-width: 500px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-xl);
            animation: modalIn 0.25s ease;
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(0.96) translateY(-16px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-body {
            padding: 24px;
        }

        .info-grid {
            display: grid;
            gap: 16px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .info-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .info-value {
            font-size: 1rem;
            color: var(--text-primary);
            font-weight: 500;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-modal {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition-fast);
            font-size: 0.9rem;
        }

        .btn-modal-cancel {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
        }

        .btn-modal-cancel:hover {
            background: var(--bg-hover);
        }

        .btn-modal-confirm {
            background: var(--danger);
            color: white;
        }

        .btn-modal-confirm:hover {
            background: #dc2626;
        }

        .btn-modal-primary {
            background: var(--accent-blue);
            color: white;
        }

        .btn-modal-primary:hover {
            background: var(--accent-blue-hover);
        }

        /* Quitar efecto espejo del escáner QR */
        #qr-reader video {
            transform: scaleX(1) !important;
            -webkit-transform: scaleX(1) !important;
        }
        
        #qr-reader__dashboard_section_csr video {
            transform: scaleX(1) !important;
            -webkit-transform: scaleX(1) !important;
        }

        #qr-reader > div > video {
            transform: scaleX(1) !important;
            -webkit-transform: scaleX(1) !important;
        }
    </style>
</head>

<body>
    <div class="search-container">
        <!-- Header -->
        <div class="search-header">
            <h1>
                <div class="header-icon">
                    <i class="bi bi-search"></i>
                </div>
                <div>
                    Buscador de Boletos
                    <p>Busca, visualiza y gestiona boletos de todos los eventos</p>
                </div>
            </h1>
        </div>

        <!-- Search Controls -->
        <div class="search-controls">
            <div class="search-bar">
                <div class="search-input-wrapper">
                    <i class="bi bi-search"></i>
                    <input type="text" class="search-input" id="searchInput"
                        placeholder="Buscar por código de boleto, asiento, evento, cliente...">
                </div>
                <button class="btn-clear" id="btnQRScanner">
                    <i class="bi bi-qr-code-scan"></i>
                    Escanear QR
                </button>
                <button class="btn-clear" id="btnClear">
                    <i class="bi bi-x-circle"></i>
                    Limpiar
                </button>
            </div>

            <div class="filters">
                <div class="filter-group">
                    <label class="filter-label">Estado</label>
                    <select class="filter-select" id="filterEstado">
                        <option value="">Todos</option>
                        <option value="1" selected>Activos</option>
                        <option value="0">Usados</option>
                        <option value="2">Cancelados</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Base de Datos</label>
                    <select class="filter-select" id="filterDB">
                        <option value="ambas" selected>Ambas</option>
                        <option value="actual">Solo Actual</option>
                        <option value="historico">Solo Histórico</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Evento</label>
                    <select class="filter-select" id="filterEvento">
                        <option value="">Todos los eventos</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats" id="stats">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="bi bi-ticket-perforated"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value" id="statTotal">0</div>
                    <div class="stat-label">Total Boletos</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value" id="statActivos">0</div>
                    <div class="stat-label">Activos</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon yellow">
                    <i class="bi bi-x-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value" id="statUsados">0</div>
                    <div class="stat-label">Usados</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="bi bi-slash-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value" id="statCancelados">0</div>
                    <div class="stat-label">Cancelados</div>
                </div>
            </div>
        </div>

        <!-- Results -->
        <div class="results-container">
            <div class="results-header">
                <div class="results-title">
                    <i class="bi bi-list-ul"></i>
                    Resultados
                </div>
                <div class="results-count" id="resultsCount">0 boletos</div>
            </div>

            <div class="table-wrapper">
                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <p>Cargando boletos...</p>
                </div>

                <table id="resultsTable" style="display: none;">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Asiento</th>
                            <th>Evento</th>
                            <th>Tipo Evento</th>
                            <th>Función</th>
                            <th>Categoría</th>
                            <th>Precio</th>
                            <th>Estado</th>
                            <th>Fecha Compra</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="resultsBody">
                    </tbody>
                </table>

                <div class="empty-state" id="emptyState" style="display: none;">
                    <div class="empty-icon">
                        <i class="bi bi-inbox"></i>
                    </div>
                    <p class="empty-text">No se encontraron boletos con los criterios seleccionados</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ver Información -->
    <div class="modal-overlay" id="modalInfo">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="bi bi-info-circle"></i> Información del Boleto</h3>
            </div>
            <div class="modal-body">
                <div class="info-grid" id="infoContent">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal btn-modal-cancel" onclick="cerrarModal('modalInfo')">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Modal QR Scanner -->
    <div class="modal-overlay" id="modalQRScanner">
        <div class="modal-box" style="max-width: 600px;">
            <div class="modal-header">
                <h3><i class="bi bi-qr-code-scan"></i> Escanear Código QR</h3>
            </div>
            <div class="modal-body">
                <!-- Controles de cámara -->
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <select id="cameraSelect" style="flex: 1; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary);" onchange="switchCamera(this.value)">
                        <option value="">Detectando cámaras...</option>
                    </select>
                    <button class="btn-action" onclick="toggleMirror()" title="Espejo" style="padding: 0 15px;">
                        <i class="bi bi-symmetry-horizontal"></i>
                    </button>
                </div>

                <div id="cameraContainer" style="width: 100%; border-radius: 12px; overflow: hidden; background: #000; min-height: 300px; display: flex; align-items: center; justify-content: center; position: relative;">
                    <div id="cameraLoading" style="position: absolute; text-align: center; color: white;">
                        <div class="spinner-border" role="status" style="width: 3rem; height: 3rem; border-width: 0.25em;"></div>
                        <p style="margin-top: 10px;">Iniciando cámara...</p>
                    </div>
                </div>
                
                <div style="margin-top: 16px; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
                    <i class="bi bi-info-circle"></i> Apunta la cámara al código QR del boleto
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal btn-modal-cancel" onclick="cerrarEscaner()">Cancelar</button>
            </div>
        </div>
    </div>

    <!-- Modal Cancelar -->
    <div class="modal-overlay" id="modalCancelar">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="bi bi-exclamation-triangle"></i> Confirmar Cancelación</h3>
            </div>
            <div class="modal-body">
                <p style="color: var(--text-primary); margin: 0;">
                    ¿Estás seguro de que deseas cancelar este boleto? Esta acción no se puede deshacer.
                </p>
                <div class="info-grid" id="cancelInfo" style="margin-top: 20px;">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal btn-modal-cancel" onclick="cerrarModal('modalCancelar')">No, volver</button>
                <button class="btn-modal btn-modal-confirm" onclick="confirmarCancelacion()">Sí, cancelar boleto</button>
            </div>
        </div>
    </div>

    <script>
        let allTickets = [];
        let filteredTickets = [];
        let currentCancelId = null;
        let searchTimeout = null;
        
        // Variables del escáner
        let html5QrCode = null;
        let currentCameraId = null;
        let mirrorEnabled = true;
        let isProcessing = false;
        let lastScannedCode = null;
        let lastScanTime = 0;

        // Inicializar
        document.addEventListener('DOMContentLoaded', () => {
            loadEvents();
            loadTickets();

            // Event listeners
            document.getElementById('searchInput').addEventListener('input', handleSearch);
            document.getElementById('filterEstado').addEventListener('change', applyFilters);
            document.getElementById('filterDB').addEventListener('change', applyFilters);
            document.getElementById('filterEvento').addEventListener('change', applyFilters);
            document.getElementById('btnClear').addEventListener('click', clearFilters);
            document.getElementById('btnQRScanner').addEventListener('click', abrirEscaner);
        });

        // Cargar eventos
        function loadEvents() {
            fetch('buscar_boletos.php?action=eventos')
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error loading events:', data.error);
                        return;
                    }
                    if (!Array.isArray(data)) {
                        console.error('Invalid data format for events:', data);
                        return;
                    }
                    const select = document.getElementById('filterEvento');
                    data.forEach(evento => {
                        const option = document.createElement('option');
                        option.value = evento.id_evento;
                        option.textContent = evento.titulo;
                        select.appendChild(option);
                    });
                })
                .catch(err => console.error('Fetch error (events):', err));
        }



        // Cargar boletos
        function loadTickets() {
            const db = document.getElementById('filterDB').value;

            document.getElementById('loading').style.display = 'block';
            document.getElementById('resultsTable').style.display = 'none';
            document.getElementById('emptyState').style.display = 'none';

            fetch(`buscar_boletos.php?action=boletos&db=${db}`)
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        alert('Error al cargar boletos: ' + data.error);
                        document.getElementById('loading').style.display = 'none';
                        document.getElementById('emptyState').style.display = 'block';
                        return;
                    }
                    if (!Array.isArray(data)) {
                        console.error('Invalid data format for tickets:', data);
                        alert('Error: Formato de datos inválido');
                        document.getElementById('loading').style.display = 'none';
                        document.getElementById('emptyState').style.display = 'block';
                        return;
                    }
                    allTickets = data;
                    applyFilters();
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('loading').style.display = 'none';
                    document.getElementById('emptyState').style.display = 'block';
                });
        }

        // Búsqueda en tiempo real
        function handleSearch() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                applyFilters();
            }, 300);
        }

        // Aplicar filtros
        function applyFilters() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const estado = document.getElementById('filterEstado').value;
            const evento = document.getElementById('filterEvento').value;

            filteredTickets = allTickets.filter(ticket => {
                // Filtro de búsqueda
                const matchSearch = !searchTerm ||
                    ticket.codigo_unico.toLowerCase().includes(searchTerm) ||
                    ticket.codigo_asiento.toLowerCase().includes(searchTerm) ||
                    ticket.evento_titulo.toLowerCase().includes(searchTerm) ||
                    (ticket.cliente_nombre && ticket.cliente_nombre.toLowerCase().includes(searchTerm));

                // Filtro de estado
                const matchEstado = !estado || ticket.estatus == estado;

                // Filtro de evento
                const matchEvento = !evento || ticket.id_evento == evento;

                return matchSearch && matchEstado && matchEvento;
            });

            updateStats();
            renderResults();
        }

        // Actualizar estadísticas
        function updateStats() {
            const total = filteredTickets.length;
            const activos = filteredTickets.filter(t => t.estatus == 1).length;
            const usados = filteredTickets.filter(t => t.estatus == 0).length;
            const cancelados = filteredTickets.filter(t => t.estatus == 2).length;

            document.getElementById('statTotal').textContent = total;
            document.getElementById('statActivos').textContent = activos;
            document.getElementById('statUsados').textContent = usados;
            document.getElementById('statCancelados').textContent = cancelados;
        }

        // Renderizar resultados
        function renderResults() {
            const tbody = document.getElementById('resultsBody');
            const loading = document.getElementById('loading');
            const table = document.getElementById('resultsTable');
            const empty = document.getElementById('emptyState');
            const count = document.getElementById('resultsCount');

            loading.style.display = 'none';

            if (filteredTickets.length === 0) {
                table.style.display = 'none';
                empty.style.display = 'block';
                count.textContent = '0 boletos';
                return;
            }

            empty.style.display = 'none';
            table.style.display = 'table';
            count.textContent = `${filteredTickets.length} boleto${filteredTickets.length !== 1 ? 's' : ''}`;

            tbody.innerHTML = filteredTickets.map(ticket => {
                const estadoClass = ticket.estatus == 1 ? 'activo' : ticket.estatus == 0 ? 'usado' : 'cancelado';
                const estadoText = ticket.estatus == 1 ? 'Activo' : ticket.estatus == 0 ? 'Usado' : 'Cancelado';
                const estadoIcon = ticket.estatus == 1 ? 'check-circle' : ticket.estatus == 0 ? 'x-circle' : 'slash-circle';

                // Tipo de evento según la base de datos
                const tipoEventoClass = ticket.db_source === 'actual' ? 'activo' : 'archivado';
                const tipoEventoText = ticket.db_source === 'actual' ? 'Activo' : 'Archivado';
                const tipoEventoIcon = ticket.db_source === 'actual' ? 'check-circle-fill' : 'archive-fill';

                return `
                    <tr>
                        <td><strong>${ticket.codigo_unico}</strong></td>
                        <td>${ticket.codigo_asiento}</td>
                        <td>${ticket.evento_titulo}</td>
                        <td>
                            <span class="status-badge ${tipoEventoClass}">
                                <i class="bi bi-${tipoEventoIcon}"></i>
                                ${tipoEventoText}
                            </span>
                        </td>
                        <td>${ticket.funcion_fecha ? formatFecha(ticket.funcion_fecha) : 'N/A'}</td>
                        <td>${ticket.nombre_categoria}</td>
                        <td><strong>$${parseFloat(ticket.precio_final).toFixed(2)}</strong></td>
                        <td>
                            <span class="status-badge ${estadoClass}">
                                <i class="bi bi-${estadoIcon}"></i>
                                ${estadoText}
                            </span>
                        </td>
                        <td>${formatFecha(ticket.fecha_compra)}</td>
                        <td>
                            <div class="actions">
                                <button class="btn-action btn-view" onclick="verInfo('${ticket.codigo_unico}')" title="Ver información">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn-action btn-print" onclick="imprimirBoleto('${ticket.codigo_unico}')" 
                                    ${ticket.estatus != 1 ? 'disabled' : ''} title="Imprimir">
                                    <i class="bi bi-printer"></i>
                                </button>
                                <button class="btn-action btn-cancel" onclick="mostrarCancelar('${ticket.codigo_unico}')" 
                                    ${ticket.estatus != 1 ? 'disabled' : ''} title="Cancelar">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Ver información
        function verInfo(codigo) {
            const ticket = allTickets.find(t => t.codigo_unico === codigo);
            if (!ticket) return;

            const estadoText = ticket.estatus == 1 ? 'Activo' : ticket.estatus == 0 ? 'Usado' : 'Cancelado';

            const html = `
                <div class="info-item">
                    <div class="info-label">Código de Boleto</div>
                    <div class="info-value">${ticket.codigo_unico}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Asiento</div>
                    <div class="info-value">${ticket.codigo_asiento}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Evento</div>
                    <div class="info-value">${ticket.evento_titulo}</div>
                </div>
                ${ticket.funcion_fecha ? `
                <div class="info-item">
                    <div class="info-label">Función</div>
                    <div class="info-value">${formatFecha(ticket.funcion_fecha)}</div>
                </div>
                ` : ''}
                <div class="info-item">
                    <div class="info-label">Categoría</div>
                    <div class="info-value">${ticket.nombre_categoria}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Precio</div>
                    <div class="info-value">$${parseFloat(ticket.precio_final).toFixed(2)}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Estado</div>
                    <div class="info-value">${estadoText}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Fecha de Compra</div>
                    <div class="info-value">${formatFecha(ticket.fecha_compra)}</div>
                </div>
                ${ticket.cliente_nombre ? `
                <div class="info-item">
                    <div class="info-label">Cliente</div>
                    <div class="info-value">${ticket.cliente_nombre}</div>
                </div>
                ` : ''}
                ${ticket.vendedor_nombre ? `
                <div class="info-item">
                    <div class="info-label">Vendido por</div>
                    <div class="info-value">${ticket.vendedor_nombre}</div>
                </div>
                ` : ''}
            `;

            document.getElementById('infoContent').innerHTML = html;
            document.getElementById('modalInfo').classList.add('active');
        }

        // Mostrar modal cancelar
        function mostrarCancelar(codigo) {
            const ticket = allTickets.find(t => t.codigo_unico === codigo);
            if (!ticket) return;

            currentCancelId = codigo;

            const html = `
                <div class="info-item">
                    <div class="info-label">Código</div>
                    <div class="info-value">${ticket.codigo_unico}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Asiento</div>
                    <div class="info-value">${ticket.codigo_asiento}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Evento</div>
                    <div class="info-value">${ticket.evento_titulo}</div>
                </div>
            `;

            document.getElementById('cancelInfo').innerHTML = html;
            document.getElementById('modalCancelar').classList.add('active');
        }

        // Confirmar cancelación
        function confirmarCancelacion() {
            if (!currentCancelId) return;

            fetch('../../vnt_interfaz/cancelar_boleto.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ codigo_unico: currentCancelId })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('Boleto cancelado exitosamente');
                        cerrarModal('modalCancelar');
                        loadTickets();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cancelar el boleto');
                });
        }

        // Imprimir boleto usando el mismo sistema que punto de venta
        async function imprimirBoleto(codigo) {
            try {
                // 1. Obtener detalles completos del boleto desde el backend
                const response = await fetch('../../vnt_interfaz/imprimir_directo.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        codigos: [codigo],
                        mode: 'data'
                    })
                });

                const data = await response.json();

                if (data.success && data.boletos && data.boletos.length > 0) {
                    // 2. Intentar imprimir via QZ Tray si está disponible
                    if (typeof window.printTicketsQZ === 'function') {
                        try {
                            const result = await window.printTicketsQZ(data.boletos, null, 'default');
                            if (result.success) {
                                alert('Boleto enviado a impresora correctamente');
                                return;
                            }
                        } catch (qzError) {
                            console.log('QZ Tray no disponible, usando fallback PDF');
                        }
                    }

                    // 3. Fallback: Abrir PDF para imprimir
                    window.open(`../../vnt_interfaz/imprimir_boleto.php?codigo=${codigo}`, '_blank');
                } else {
                    alert('Error al obtener datos del boleto: ' + (data.message || 'Desconocido'));
                }
            } catch (error) {
                console.error('Error al imprimir:', error);
                // Fallback directo a PDF
                window.open(`../../vnt_interfaz/imprimir_boleto.php?codigo=${codigo}`, '_blank');
            }
        }

        // Cerrar modal
        function cerrarModal(id) {
            document.getElementById(id).classList.remove('active');
            currentCancelId = null;
        }

        // Limpiar filtros
        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('filterEstado').value = '1';
            document.getElementById('filterEvento').value = '';
            applyFilters();
        }

        // Formatear fecha
        function formatFecha(fecha) {
            const d = new Date(fecha);
            const day = String(d.getDate()).padStart(2, '0');
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const year = d.getFullYear();
            const hours = String(d.getHours()).padStart(2, '0');
            const minutes = String(d.getMinutes()).padStart(2, '0');
            return `${day}/${month}/${year} ${hours}:${minutes}`;
        }

        // Cerrar modales al hacer clic fuera
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                }
            });
        });

        // Funciones del escáner QR
        async function abrirEscaner() {
            const modal = document.getElementById('modalQRScanner');
            modal.classList.add('active');
            await initializeCameras();
        }
        //

        async function cerrarEscaner() {
            if (html5QrCode) {
                try {
                    await html5QrCode.stop();
                    html5QrCode.clear();
                } catch (e) {
                    console.error('Error stopping camera:', e);
                }
            }
            
            const modal = document.getElementById('modalQRScanner');
            modal.classList.remove('active');
            isProcessing = false;
        }

        async function initializeCameras() {
            const cameraSelect = document.getElementById('cameraSelect');
            cameraSelect.innerHTML = '<option value="">Detectando cámaras...</option>';
            
            try {
                // Solicitar permisos primero
                await navigator.mediaDevices.getUserMedia({ video: true });
                
                const devices = await navigator.mediaDevices.enumerateDevices();
                const videoDevices = devices.filter(device => device.kind === 'videoinput');
                
                if (videoDevices.length === 0) {
                    cameraSelect.innerHTML = '<option value="">Sin cámaras</option>';
                    showCameraError('No se detectaron cámaras');
                    return;
                }
                
                cameraSelect.innerHTML = videoDevices.map((device, index) => {
                    const label = device.label || `Cámara ${index + 1}`;
                    return `<option value="${device.deviceId}">${label}</option>`;
                }).join('');
                
                // Usar la trasera por defecto si es móvil o la primera disponible
                let selectedDeviceId = videoDevices[0].deviceId;
                // Intentar encontrar cámara trasera
                const backCamera = videoDevices.find(d => d.label.toLowerCase().includes('back') || d.label.toLowerCase().includes('trasera'));
                if (backCamera) {
                    selectedDeviceId = backCamera.deviceId;
                    cameraSelect.value = selectedDeviceId;
                }
                
                await startCamera(selectedDeviceId);
                
            } catch (error) {
                console.error('Error inicializando cámaras:', error);
                if (error.name === 'NotAllowedError') {
                    showCameraError('Permiso de cámara denegado');
                } else {
                    showCameraError('Error: ' + error.message);
                }
                cameraSelect.innerHTML = '<option value="">Error</option>';
            }
        }

        async function startCamera(deviceId) {
            const cameraLoading = document.getElementById('cameraLoading');
            if (cameraLoading) cameraLoading.style.display = 'block';
            
            if (html5QrCode) {
                try {
                    await html5QrCode.stop();
                    html5QrCode.clear();
                } catch (e) {}
            }
            
            try {
                html5QrCode = new Html5Qrcode("cameraContainer");
                
                await html5QrCode.start(
                    deviceId,
                    { fps: 10, aspectRatio: 1.0 },
                    onQRCodeScanned,
                    () => {} // Error callback (ignorado para evitar spam)
                );
                
                currentCameraId = deviceId;
                
                // Aplicar estilo espejo y ajustar video
                setTimeout(() => {
                    if (cameraLoading) cameraLoading.style.display = 'none';
                    
                    const video = document.querySelector('#cameraContainer video');
                    if (video) {
                        video.style.cssText = `
                            display: block !important;
                            width: 100% !important;
                            height: auto !important;
                            min-height: 280px;
                            max-height: 400px;
                            object-fit: cover;
                            border-radius: 12px;
                            transform: ${mirrorEnabled ? 'scaleX(-1)' : 'scaleX(1)'};
                        `;
                    }
                }, 400);
                
            } catch (error) {
                console.error('Error starting camera:', error);
                showCameraError('Error al iniciar cámara');
            }
        }

        async function switchCamera(deviceId) {
            if (!deviceId || deviceId === currentCameraId) return;
            await startCamera(deviceId);
        }

        function toggleMirror() {
            mirrorEnabled = !mirrorEnabled;
            const video = document.querySelector('#cameraContainer video');
            if (video) {
                video.style.transform = mirrorEnabled ? 'scaleX(-1)' : 'scaleX(1)';
            }
        }

        function showCameraError(message) {
            const container = document.getElementById('cameraContainer');
            container.innerHTML = `
                <div style="text-align: center; color: #ef4444; padding: 20px;">
                    <i class="bi bi-camera-video-off" style="font-size: 2.5rem;"></i>
                    <p style="margin-top: 10px;">${message}</p>
                    <button class="btn-action" onclick="initializeCameras()" style="margin-top: 10px;">
                        <i class="bi bi-arrow-clockwise"></i> Reintentar
                    </button>
                </div>
            `;
        }

        async function onQRCodeScanned(decodedText) {
            if (isProcessing) return;
            
            const now = Date.now();
            const code = decodedText.trim();
            
            // Evitar escaneos duplicados rápidos
            if (code === lastScannedCode && (now - lastScanTime) < 3000) {
                return;
            }
            
            lastScannedCode = code;
            lastScanTime = now;
            isProcessing = true;
            
            if ('vibrate' in navigator) navigator.vibrate(100);
            
            console.log(`Código QR detectado: ${code}`);
            
            // Pausar escáner momentáneamente
            if (html5QrCode && html5QrCode.isScanning) {
                await html5QrCode.pause(true);
            }
            
            // Buscar boleto
            document.getElementById('searchInput').value = code;
            handleSearch();
            
            // Cerrar Modal
            cerrarEscaner();
            
            
            isProcessing = false;
        }
    </script>
</body>

</html>
