<?php
// 1. CONEXIÓN
include "../../evt_interfaz/conexion.php";

$id_evento_seleccionado = $_GET['id_evento'] ?? null;
$nombre_evento = "";
$eventos = [];
$all_categorias = [];

// Categorías base para descuentos globales
$categorias_base = [
    ['id_categoria' => 'general', 'nombre_categoria' => 'General', 'precio' => 80],
];

// 2. Cargar todos los eventos para el dropdown (con fechas)
$res_eventos = $conn->query("
    SELECT e.id_evento, e.titulo, 
           MIN(f.fecha_hora) as fecha_inicio,
           MAX(f.fecha_hora) as fecha_fin
    FROM evento e
    LEFT JOIN funciones f ON e.id_evento = f.id_evento
    WHERE e.finalizado = 0 
    GROUP BY e.id_evento, e.titulo
    ORDER BY e.titulo ASC
");
if ($res_eventos) {
    while ($row = $res_eventos->fetch_assoc()) {
        $eventos[] = $row;
    }
}

// 3. Cargar TODAS las categorías para pasarlas a JavaScript (excluyendo No Venta y Discapacitado)
$res_cats = $conn->query("
    SELECT id_categoria, id_evento, nombre_categoria, precio 
    FROM categorias 
    WHERE LOWER(nombre_categoria) NOT IN ('no venta', 'noventa', 'no_venta', 'discapacitado', 'discapacidad')
    ORDER BY nombre_categoria ASC
");
if ($res_cats) {
    $all_categorias = $res_cats->fetch_all(MYSQLI_ASSOC);
}

// 4. Obtener el nombre del evento para el título
if ($id_evento_seleccionado) {
    if ($id_evento_seleccionado == 'todos') {
        $nombre_evento = "Todos los Eventos";
    } else {
        foreach ($eventos as $evento) {
            if ($evento['id_evento'] == $id_evento_seleccionado) {
                $nombre_evento = $evento['titulo'];
                break;
            }
        }
    }
}
$conn->close();

$EVENTOS_JSON = json_encode($eventos, JSON_UNESCAPED_UNICODE);
$ALL_CATEGORIAS_JSON = json_encode($all_categorias, JSON_UNESCAPED_UNICODE);
$CATEGORIAS_BASE_JSON = json_encode($categorias_base, JSON_UNESCAPED_UNICODE);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestor de Descuentos - Teatro</title>
    <link rel="icon" href="../../crt_interfaz/imagenes_teatro/nat.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-main);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 20px;
        }

        .container-main {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header */
        .page-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--info));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 6px;
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin: 0;
        }

        /* Cards */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 16px;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-primary);
        }

        .card-title i {
            font-size: 1.2rem;
            color: var(--primary);
        }

        /* Form elements */
        .form-label {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 4px;
            display: block;
        }

        .form-control,
        .form-select {
            background: var(--bg-input);
            border: 1px solid var(--border);
            color: var(--text-primary);
            border-radius: var(--radius-sm);
            padding: 10px 12px;
            font-size: 0.9rem;
            transition: all 0.2s;
            width: 100%;
        }

        .form-control:focus,
        .form-select:focus {
            background: var(--bg-input);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
            color: var(--text-primary);
            outline: none;
        }

        .form-control::placeholder {
            color: var(--text-secondary);
        }

        .form-control:disabled,
        .form-select:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Selector de evento */
        .event-selector {
            background: linear-gradient(135deg, var(--bg-card), var(--bg-input));
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }

        .event-selector select {
            max-width: 450px;
            margin: 0 auto;
            font-size: 1rem;
            font-weight: 600;
            padding: 12px 16px;
        }

        /* Buttons */
        .btn {
            padding: 10px 16px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: none;
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-1px);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: var(--primary-dark);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: #1e293b;
        }

        .btn-secondary {
            background: var(--bg-input);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        .btn-sm {
            padding: 6px 10px;
            font-size: 0.8rem;
        }

        /* Discount type buttons */
        .discount-types {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }

        .discount-type-btn {
            padding: 12px;
            border-radius: var(--radius-md);
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid var(--border);
            background: var(--bg-input);
            color: var(--text-primary);
        }

        .discount-type-btn:hover {
            border-color: var(--primary);
        }

        .discount-type-btn.active {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.15);
        }

        .discount-type-btn i {
            font-size: 1.5rem;
            display: block;
            margin-bottom: 4px;
        }

        .discount-type-btn span {
            font-weight: 600;
            font-size: 0.85rem;
        }

        /* Form grid */
        .form-grid {
            display: grid;
            gap: 12px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* Input group */
        .input-group {
            display: flex;
            gap: 0;
        }

        .input-group .input-addon {
            background: var(--bg-main);
            border: 1px solid var(--border);
            border-right: none;
            border-radius: var(--radius-sm) 0 0 var(--radius-sm);
            padding: 10px 12px;
            font-weight: 600;
            color: var(--text-secondary);
            min-width: 40px;
            text-align: center;
        }

        .input-group .form-control {
            border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
        }

        /* Table */
        .promo-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 6px;
        }

        .promo-table th {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--text-secondary);
            font-weight: 600;
            padding: 8px 12px;
            text-align: left;
            letter-spacing: 0.5px;
        }

        .promo-table td {
            padding: 12px;
            background: var(--bg-input);
            vertical-align: middle;
            font-size: 0.85rem;
        }

        .promo-table tr td:first-child {
            border-radius: var(--radius-sm) 0 0 var(--radius-sm);
        }

        .promo-table tr td:last-child {
            border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
        }

        .promo-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .promo-event {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .discount-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .discount-badge.percent {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
        }

        .discount-badge.fixed {
            background: rgba(14, 165, 233, 0.2);
            color: var(--info);
        }

        .status-active {
            color: var(--success);
            font-weight: 600;
        }

        .status-inactive {
            color: var(--text-secondary);
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--text-secondary);
            margin-bottom: 12px;
        }

        .empty-state h3 {
            font-size: 1.1rem;
            margin-bottom: 6px;
        }

        .empty-state p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        /* Alert info */
        .alert-info-custom {
            background: rgba(14, 165, 233, 0.1);
            border: 1px solid rgba(14, 165, 233, 0.3);
            border-radius: var(--radius-sm);
            padding: 10px;
            font-size: 0.8rem;
            color: var(--info);
            margin-bottom: 12px;
        }

        .alert-warning-custom {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: var(--radius-sm);
            padding: 10px;
            font-size: 0.8rem;
            color: var(--warning);
            margin-bottom: 12px;
        }

        /* Back button */
        .back-btn {
            position: fixed;
            bottom: 16px;
            left: 16px;
            z-index: 100;
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-main);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }
    </style>
</head>

<body>

    <div class="container-main">

        <!-- Header -->
        <div class="page-header">
            <h1><i class="bi bi-percent"></i> Gestor de Descuentos</h1>
            <p>Crea y administra promociones para tus eventos</p>
        </div>

        <!-- Selector de Evento -->
        <div class="event-selector">
            <form method="GET" action="">
                <select name="id_evento" class="form-select" onchange="this.form.submit()">
                    <option value="" <?= ($id_evento_seleccionado == null) ? 'selected' : '' ?> disabled>
                        🎭 Selecciona un evento para comenzar
                    </option>
                    <option value="todos" <?= ($id_evento_seleccionado == 'todos') ? 'selected' : '' ?>>
                        🌐 Descuentos Globales (todos los eventos)
                    </option>
                    <?php foreach ($eventos as $evento): ?>
                        <option value="<?= $evento['id_evento'] ?>" <?= ($id_evento_seleccionado == $evento['id_evento']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($evento['titulo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($id_evento_seleccionado): ?>

            <div class="row g-3">
                <!-- Formulario de Crear/Editar -->
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-title">
                            <i class="bi bi-plus-circle-fill"></i>
                            <span id="form-title-text">Crear Descuento</span>
                        </div>

                        <?php if ($id_evento_seleccionado == 'todos'): ?>
                            <div class="alert-info-custom">
                                <i class="bi bi-globe"></i> Los descuentos globales aplican a todos los eventos
                            </div>
                        <?php endif; ?>

                        <form id="form-promocion" class="form-grid">
                            <input type="hidden" id="id_promocion" value="">
                            <input type="hidden" id="nombre_fijo" value="">
                            <input type="hidden" id="tipo_boleto_hidden" value="">
                            <input type="hidden" id="modo" value="porcentaje">

                            <!-- Selector de Evento para el descuento -->
                            <div>
                                <label class="form-label">Aplicar a evento:</label>
                                <select id="id_evento" class="form-select">
                                    <option value="">🌐 Global (todos los eventos)</option>
                                    <?php foreach ($eventos as $evento): ?>
                                        <option value="<?= $evento['id_evento'] ?>"
                                            data-fecha-inicio="<?= $evento['fecha_inicio'] ?? '' ?>"
                                            data-fecha-fin="<?= $evento['fecha_fin'] ?? '' ?>">
                                            <?= htmlspecialchars($evento['titulo']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Selector de Categoría -->
                            <div id="campo-categoria">
                                <label class="form-label">Categoría de boleto:</label>
                                <select id="id_categoria_select" class="form-select">
                                    <option value="">-- Selecciona un evento primero --</option>
                                </select>
                            </div>

                            <!-- Precio base -->
                            <div>
                                <label class="form-label">Precio base:</label>
                                <div class="input-group">
                                    <span class="input-addon">$</span>
                                    <input type="number" id="precio_base" class="form-control" min="0" step="0.01"
                                        placeholder="0.00" readonly>
                                </div>
                            </div>

                            <!-- Tipo de descuento -->
                            <div>
                                <label class="form-label">Tipo de descuento:</label>
                                <div class="discount-types">
                                    <div class="discount-type-btn active" data-tipo="porcentaje"
                                        onclick="seleccionarTipoDescuento('porcentaje')">
                                        <i class="bi bi-percent text-success"></i>
                                        <span>Porcentaje</span>
                                    </div>
                                    <div class="discount-type-btn" data-tipo="fijo"
                                        onclick="seleccionarTipoDescuento('fijo')">
                                        <i class="bi bi-cash text-info"></i>
                                        <span>Monto Fijo</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Valor del descuento -->
                            <div>
                                <label class="form-label">Valor del descuento:</label>
                                <div class="input-group">
                                    <span class="input-addon" id="valor-addon">%</span>
                                    <input type="number" id="valor" class="form-control" min="1" max="100" step="1"
                                        placeholder="Ej: 20" required>
                                </div>
                            </div>

                            <!-- Mínimo de boletos -->
                            <div>
                                <label class="form-label">Mínimo de boletos:</label>
                                <input type="number" id="min_cantidad" class="form-control" min="1" value="1">
                            </div>

                            <!-- Tipo de boleto aplicable -->
                            <div>
                                <label class="form-label">Aplica a tipo de boleto:</label>
                                <select id="tipo_boleto_aplicable" class="form-select">
                                    <option value="">🎫 Todos los tipos (General)</option>
                                    <option value="adulto">👤 Solo Adultos</option>
                                    <option value="nino">👶 Solo Niños</option>
                                    <option value="adulto_mayor">👴 Solo 3ra Edad</option>
                                    <option value="discapacitado">♿ Solo Discapacitados</option>
                                </select>
                                <small class="text-muted mt-1 d-block">
                                    <i class="bi bi-info-circle"></i> Si eliges un tipo específico, el descuento solo aplica
                                    a ese tipo.
                                </small>
                            </div>

                            <!-- Vigencia -->
                            <div class="form-row">
                                <div>
                                    <label class="form-label">Válido desde:</label>
                                    <input type="date" id="desde" class="form-control">
                                </div>
                                <div>
                                    <label class="form-label">Válido hasta:</label>
                                    <input type="date" id="hasta" class="form-control">
                                </div>
                            </div>

                            <div id="fecha-warning" class="alert-warning-custom" style="display: none;">
                                <i class="bi bi-exclamation-triangle"></i> <span id="fecha-warning-text"></span>
                            </div>

                            <!-- Condiciones -->
                            <div>
                                <label class="form-label">Condiciones (opcional):</label>
                                <input type="text" id="condiciones" class="form-control"
                                    placeholder="Ej: No aplica con otras promociones">
                            </div>

                            <!-- Botones -->
                            <div class="form-row" style="margin-top: 8px;">
                                <button type="submit" id="btn-guardar" class="btn btn-success">
                                    <i class="bi bi-check-circle"></i> Guardar
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="limpiarForm()">
                                    <i class="bi bi-x-circle"></i> Cancelar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Lista de Promociones -->
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-title">
                            <i class="bi bi-list-ul"></i>
                            <span>Descuentos: <?= htmlspecialchars($nombre_evento) ?></span>
                        </div>

                        <div style="overflow-x: auto;">
                            <table class="promo-table" id="tabla-promos">
                                <thead>
                                    <tr>
                                        <th>Promoción</th>
                                        <th>Descuento</th>
                                        <th>Min.</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="5" class="text-center p-4">Cargando...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>

            <!-- Estado vacío -->
            <div class="card">
                <div class="empty-state">
                    <i class="bi bi-hand-index-thumb"></i>
                    <h3>Selecciona un evento</h3>
                    <p>Elige un evento del menú superior para ver y crear descuentos</p>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <!-- Botón de regreso -->


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_URL = 'promos_api.php';
        const PHP_EVENTOS = <?= $EVENTOS_JSON ?>;
        const ALL_CATEGORIAS = <?= $ALL_CATEGORIAS_JSON ?>;
        const CATEGORIAS_BASE = <?= $CATEGORIAS_BASE_JSON ?>;

        const state = {
            promos: [],
            editingId: null,
            filtroId: <?= json_encode($id_evento_seleccionado) ?>,
            allCategorias: ALL_CATEGORIAS,
            tipoDescuento: 'porcentaje',
            fechaMaxEvento: null
        };

        // Helpers
        const $ = s => document.querySelector(s);
        const fmtMoney = n => '$' + Number(n || 0).toFixed(2);

        function getTodayString() {
            const today = new Date();
            today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
            return today.toISOString().split('T')[0];
        }

        // API
        async function api(action, payload = {}, id = null) {
            let url = `${API_URL}?action=${action}`;
            if (id) url += `&id=${id}`;
            if (action === 'list' && payload.filtro_evento) {
                url += `&filtro_evento=${payload.filtro_evento}`;
            }

            const config = {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
            };

            if (action === 'list' || action === 'delete') {
                config.method = 'GET';
            } else {
                config.body = JSON.stringify(payload);
            }

            try {
                const r = await fetch(url, config);
                const j = await r.json();
                if (!j.ok) throw new Error(j.error || 'Error de API');
                return j;
            } catch (e) {
                Swal.fire('Error', e.message, 'error');
                throw e;
            }
        }

        // Seleccionar tipo de descuento
        function seleccionarTipoDescuento(tipo) {
            state.tipoDescuento = tipo;
            $('#modo').value = tipo;

            document.querySelectorAll('.discount-type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            $(`.discount-type-btn[data-tipo="${tipo}"]`).classList.add('active');

            const valorAddon = $('#valor-addon');
            const valorInput = $('#valor');

            if (tipo === 'porcentaje') {
                valorAddon.textContent = '%';
                valorInput.max = 100;
                valorInput.placeholder = 'Ej: 20';
            } else {
                valorAddon.textContent = '$';
                valorInput.max = 99999;
                valorInput.placeholder = 'Ej: 50';
            }
        }

        // Validar fechas según el evento
        function validarFechas() {
            const desdeInput = $('#desde');
            const hastaInput = $('#hasta');
            const warning = $('#fecha-warning');
            const warningText = $('#fecha-warning-text');
            const eventSelect = $('#id_evento');
            const todayString = getTodayString();

            // Obtener fecha del evento seleccionado
            const selectedOption = eventSelect.selectedOptions[0];
            const fechaFinEvento = selectedOption ? selectedOption.dataset.fechaFin : null;

            // Configurar fecha mínima (hoy)
            desdeInput.min = todayString;

            // Si hay fecha de evento, usarla como máximo
            if (fechaFinEvento) {
                const fechaEvento = fechaFinEvento.split(' ')[0];
                hastaInput.max = fechaEvento;
                state.fechaMaxEvento = fechaEvento;

                // Validar si la fecha hasta es después del evento
                if (hastaInput.value && hastaInput.value > fechaEvento) {
                    warning.style.display = 'block';
                    warningText.textContent = `La promoción no puede terminar después del evento (${fechaEvento})`;
                    hastaInput.value = fechaEvento;
                } else {
                    warning.style.display = 'none';
                }
            } else {
                hastaInput.max = '';
                state.fechaMaxEvento = null;
                warning.style.display = 'none';
            }

            // Validar que desde no sea después de hasta
            if (desdeInput.value && hastaInput.value && desdeInput.value > hastaInput.value) {
                warning.style.display = 'block';
                warningText.textContent = 'La fecha de inicio no puede ser después de la fecha final';
                desdeInput.value = hastaInput.value;
            }

            // Actualizar mínimo de hasta según desde
            if (desdeInput.value) {
                hastaInput.min = desdeInput.value;
            } else {
                hastaInput.min = todayString;
            }
        }

        // Render tabla
        function renderTable() {
            const tb = $('#tabla-promos tbody');
            tb.innerHTML = '';

            if (!state.promos.length) {
                tb.innerHTML = `<tr><td colspan="5" class="text-center p-4" style="color: var(--text-secondary);">
            <i class="bi bi-tag d-block mb-2" style="font-size: 2rem;"></i>
            No hay descuentos configurados
        </td></tr>`;
                return;
            }

            state.promos.forEach(p => {
                const descTxt = p.modo_calculo === 'porcentaje' ? `${p.valor}%` : fmtMoney(p.valor);
                const badgeClass = p.modo_calculo === 'porcentaje' ? 'percent' : 'fixed';
                const estado = p.activo ? '<span class="status-active">✓ Activo</span>' : '<span class="status-inactive">Inactivo</span>';
                const eventoNombre = p.evento_titulo || 'Global';

                const tr = document.createElement('tr');
                tr.innerHTML = `
            <td>
                <div class="promo-name">${p.nombre}</div>
                <div class="promo-event">${eventoNombre} - ${fmtMoney(p.precio)}</div>
            </td>
            <td><span class="discount-badge ${badgeClass}">${descTxt}</span></td>
            <td>${p.min_cantidad}</td>
            <td>${estado}</td>
            <td>
                <button class="btn btn-warning btn-sm" onclick='cargarEnForm(${p.id_promocion})' title="Editar">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-danger btn-sm" onclick="eliminar(${p.id_promocion}, '${p.nombre.replace(/'/g, "\\'")}')" title="Eliminar">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
                tb.appendChild(tr);
            });
        }

        // Popular categorías
        function popularCategorias(eventoId) {
            const selectCat = $('#id_categoria_select');
            selectCat.innerHTML = '';

            // Categorías a excluir (en minúsculas)
            const categoriasExcluidas = ['no venta', 'noventa', 'no_venta', 'discapacitado', 'discapacidad'];

            if (!eventoId) {
                // Mostrar categorías base para descuentos globales
                selectCat.innerHTML = '<option value="">-- Selecciona categoría base --</option>';
                CATEGORIAS_BASE.forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat.id_categoria;
                    option.textContent = `${cat.nombre_categoria} - $${cat.precio}`;
                    option.dataset.precio = cat.precio;
                    option.dataset.nombre = cat.nombre_categoria;
                    selectCat.appendChild(option);
                });
                return;
            }

            selectCat.innerHTML = '<option value="">-- Selecciona una categoría --</option>';

            // Filtrar por evento y excluir categorías no deseadas
            const categoriasFiltradas = state.allCategorias.filter(c => {
                if (c.id_evento != eventoId) return false;
                const nombreLower = (c.nombre_categoria || '').toLowerCase().trim();
                return !categoriasExcluidas.includes(nombreLower);
            });

            if (!categoriasFiltradas.length) {
                selectCat.innerHTML = '<option value="">-- No hay categorías para este evento --</option>';
                return;
            }

            categoriasFiltradas.forEach(cat => {
                const option = document.createElement('option');
                option.value = cat.id_categoria;
                option.textContent = `${cat.nombre_categoria} - $${cat.precio}`;
                option.dataset.precio = cat.precio;
                option.dataset.nombre = cat.nombre_categoria;
                selectCat.appendChild(option);
            });
        }

        // Limpiar formulario
        function limpiarForm() {
            $('#form-promocion').reset();
            $('#id_promocion').value = '';
            $('#nombre_fijo').value = '';
            $('#tipo_boleto_hidden').value = '';
            $('#precio_base').value = '';
            state.editingId = null;

            $('#form-title-text').textContent = 'Crear Descuento';
            $('#btn-guardar').className = 'btn btn-success';
            $('#btn-guardar').innerHTML = '<i class="bi bi-check-circle"></i> Guardar';
            $('#campo-categoria').style.display = 'block';
            $('#fecha-warning').style.display = 'none';

            seleccionarTipoDescuento('porcentaje');

            const defaultEventId = (state.filtroId && state.filtroId !== 'todos') ? state.filtroId : '';
            $('#id_evento').value = defaultEventId;
            popularCategorias(defaultEventId);
            validarFechas();
        }

        // Cargar en formulario para editar
        function cargarEnForm(id) {
            const p = state.promos.find(x => x.id_promocion == id);
            if (!p) return;

            state.editingId = id;
            $('#id_promocion').value = p.id_promocion;
            $('#nombre_fijo').value = p.nombre;
            $('#precio_base').value = p.precio;
            $('#valor').value = p.valor;
            $('#min_cantidad').value = p.min_cantidad;
            $('#condiciones').value = p.condiciones || '';
            $('#id_evento').value = p.id_evento || '';

            seleccionarTipoDescuento(p.modo_calculo);

            const fechaDesde = p.fecha_desde ? p.fecha_desde.split(' ')[0] : '';
            const fechaHasta = p.fecha_hasta ? p.fecha_hasta.split(' ')[0] : '';
            $('#desde').value = fechaDesde;
            $('#hasta').value = fechaHasta;

            $('#form-title-text').textContent = 'Editar Descuento';
            $('#btn-guardar').className = 'btn btn-warning';
            $('#btn-guardar').innerHTML = '<i class="bi bi-save"></i> Actualizar';
            $('#campo-categoria').style.display = 'none';

            validarFechas();
            document.querySelector('.card').scrollIntoView({ behavior: 'smooth' });
        }

        // Guardar
        async function guardar(e) {
            e.preventDefault();
            const btn = $('#btn-guardar');

            const payload = {
                id_evento: $('#id_evento').value || null,
                precio_base: $('#precio_base').value,
                modo: $('#modo').value,
                valor: $('#valor').value,
                min_cantidad: $('#min_cantidad').value,
                desde: $('#desde').value,
                hasta: $('#hasta').value,
                condiciones: $('#condiciones').value,
                tipo_boleto: $('#tipo_boleto_hidden').value,
                tipo_boleto_aplicable: $('#tipo_boleto_aplicable').value || null,
                nombre_fijo: $('#nombre_fijo').value
            };

            // Validaciones
            if (!payload.precio_base || payload.precio_base <= 0) {
                Swal.fire('Error', 'Selecciona una categoría de boleto', 'warning');
                return;
            }

            if (!payload.valor || payload.valor <= 0) {
                Swal.fire('Error', 'Ingresa un valor de descuento', 'warning');
                return;
            }

            if (!state.editingId && !payload.tipo_boleto) {
                Swal.fire('Error', 'Selecciona una categoría de boleto', 'warning');
                return;
            }

            // Validar fecha máxima del evento
            if (state.fechaMaxEvento && payload.hasta && payload.hasta > state.fechaMaxEvento) {
                Swal.fire('Error', `La fecha final no puede ser después del evento (${state.fechaMaxEvento})`, 'warning');
                return;
            }

            const action = state.editingId ? 'update' : 'create';
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            try {
                const data = await api(action, payload, state.editingId);

                // Notificar cambio para sincronización
                if (data.notify_change && data.id_evento) {
                    localStorage.setItem('descuentos_actualizados', JSON.stringify({
                        id_evento: data.id_evento,
                        timestamp: Date.now()
                    }));
                }

                await cargarDatos();
                limpiarForm();

                Swal.fire({
                    icon: 'success',
                    title: '¡Guardado!',
                    timer: 1500,
                    showConfirmButton: false
                });
            } catch (e) {
                console.error(e);
            } finally {
                btn.disabled = false;
                limpiarForm();
            }
        }

        // Eliminar
        function eliminar(id, nombre) {
            Swal.fire({
                title: `¿Eliminar "${nombre}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Eliminar',
                cancelButtonText: 'Cancelar'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        await api('delete', {}, id);
                        await cargarDatos();
                        Swal.fire({ icon: 'success', title: 'Eliminado', timer: 1500, showConfirmButton: false });
                    } catch (e) {
                        console.error(e);
                    }
                }
            });
        }

        // Cargar datos
        async function cargarDatos() {
            try {
                const data = await api('list', { filtro_evento: state.filtroId });
                state.promos = data.items || [];
                renderTable();
            } catch (e) {
                if ($('#tabla-promos tbody')) {
                    $('#tabla-promos tbody').innerHTML = `<tr><td colspan="5" class="text-center text-danger p-4">Error al cargar</td></tr>`;
                }
            }
        }

        // Inicialización
        document.addEventListener('DOMContentLoaded', () => {
            if (state.filtroId) {
                $('#form-promocion').addEventListener('submit', guardar);

                $('#id_evento').addEventListener('change', (e) => {
                    popularCategorias(e.target.value);
                    $('#precio_base').value = '';
                    $('#tipo_boleto_hidden').value = '';
                    validarFechas();
                });

                $('#id_categoria_select').addEventListener('change', (e) => {
                    const option = e.target.selectedOptions[0];
                    if (option && option.dataset.precio) {
                        $('#precio_base').value = option.dataset.precio;
                        $('#tipo_boleto_hidden').value = option.dataset.nombre;
                    } else {
                        $('#precio_base').value = '';
                        $('#tipo_boleto_hidden').value = '';
                    }
                });

                $('#desde').addEventListener('change', validarFechas);
                $('#hasta').addEventListener('change', validarFechas);

                cargarDatos();
                limpiarForm();
            }
        });

        // Exponer funciones globalmente
        window.seleccionarTipoDescuento = seleccionarTipoDescuento;
        window.cargarEnForm = cargarEnForm;
        window.eliminar = eliminar;
        window.limpiarForm = limpiarForm;
    </script>

</body>

</html>