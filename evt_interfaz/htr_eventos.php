<?php
// Activar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include "../conexion.php";

if(file_exists("../transacciones_helper.php")) {
    require_once "../transacciones_helper.php";
}

// ==================================================================
// VERIFICACIÓN DE SESIÓN
// ==================================================================
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] !== 'admin' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']))) {
    die('<div style="font-family: Arial; text-align: center; margin-top: 50px; color: red;"><h1>Acceso Denegado</h1><p>No tiene permiso para ver esta página.</p></div>');
}

// ==================================================================
// PROCESADOR AJAX (SOLO PARA BORRAR)
// ==================================================================
if (isset($_POST['accion']) && $_POST['accion'] === 'borrar_permanente') {
    header('Content-Type: application/json');
    $id = (int)$_POST['id_evento'];
    $password = $_POST['password'] ?? '';
    
    // Verificar contraseña del admin actual
    $stmt = $conn->prepare("SELECT password FROM usuarios WHERE id_usuario = ? AND rol = 'admin'");
    $stmt->bind_param("i", $_SESSION['usuario_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Error de autenticación']);
        exit;
    }
    
    $admin = $result->fetch_assoc();
    if (!password_verify($password, $admin['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Contraseña incorrecta']);
        exit;
    }
    $stmt->close();
    
    // ===== RESPALDO INMUTABLE PREVIO ANTES DE BORRAR DEFINITIVAMENTE =====
    if (file_exists(__DIR__ . '/../sync/backup_helper.php')) {
        require_once __DIR__ . '/../sync/backup_helper.php';
        // Respaldar evento y boletos vendidos (lectura desde BD histórica)
        $conn_hist_select = $conn;
        $conn_hist_select->query("USE trt_historico_evento");
        @respaldarEvento($conn_hist_select, $id, 'local');
        $rs = $conn_hist_select->query("SELECT id_boleto FROM boletos WHERE id_evento = $id AND estatus IN (1,2)");
        if ($rs) {
            while ($r = $rs->fetch_assoc()) {
                @respaldarBoleto($conn_hist_select, (int)$r['id_boleto'], 'local');
            }
        }
        $conn_hist_select->query("USE trt_25"); // Volver a la BD principal
    }

    $conn->begin_transaction();
    try {
        // Borrar todo de las tablas HISTÓRICAS
        $conn->query("DELETE FROM trt_historico_evento.boletos WHERE id_evento = $id");
        $conn->query("DELETE FROM trt_historico_evento.promociones WHERE id_evento = $id");
        $conn->query("DELETE FROM trt_historico_evento.categorias WHERE id_evento = $id");
        $conn->query("DELETE FROM trt_historico_evento.funciones WHERE id_evento = $id");
        $conn->query("DELETE FROM trt_historico_evento.evento WHERE id_evento = $id");
        
        if(function_exists('registrar_transaccion')) {
            registrar_transaccion('evento_borrar_historico', "Borró permanentemente evento histórico ID $id");
        }
        
        $conn->commit();
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

//
// PARÁMETROS DE FILTRADO
$filtro_titulo = $_GET['filtro_titulo'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// CARGA DE DATOS (HISTORIAL) con filtros
$sql_base = "SELECT * FROM trt_historico_evento.evento";
$condiciones = [];
$params = [];
$types = '';

if (!empty($filtro_titulo)) {
    $condiciones[] = "titulo LIKE ?";
    $params[] = "%$filtro_titulo%";
    $types .= 's';
}

if (!empty($fecha_desde)) {
    $condiciones[] = "cierre_venta >= ?";
    $params[] = $fecha_desde;
    $types .= 's';
}

if (!empty($fecha_hasta)) {
    $condiciones[] = "cierre_venta <= ?";
    $params[] = $fecha_hasta;
    $types .= 's';
}

if (count($condiciones) > 0) {
    $sql_base .= " WHERE " . implode(" AND ", $condiciones);
}

$sql_base .= " ORDER BY cierre_venta DESC";

if (count($params) > 0) {
    $stmt = $conn->prepare($sql_base);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $historial = $stmt->get_result();
} else {
    $historial = $conn->query($sql_base);
}

$total_eventos = $historial->num_rows;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    :root {
        --primary-color: #1561f0; 
        --bg-primary: #131313;
        --bg-card: #1c1c1e;
        --text-primary: #ffffff;
        --text-secondary: #86868b;
        --border-color: #3a3a3c;
        --radius-md: 12px;
    }
    body { 
        background-color: var(--bg-primary); 
        color: var(--text-primary);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
        opacity: 0; transition: opacity 0.4s;
    }
    body.loaded { opacity: 1; }
    
    .content-wrapper { padding: 20px; }
    
    /* --- TARJETA --- */
    .card { 
        border: 1px solid var(--border-color); 
        border-radius: var(--radius-md); 
        box-shadow: 0 4px 8px rgba(0,0,0,0.3); 
        background: var(--bg-card); 
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
        animation: cardEntry 0.4s ease forwards;
        opacity: 0; transform: translateY(15px);
    }
    @keyframes cardEntry { to { opacity: 1; transform: translateY(0); } }
    
    .card:hover { 
        transform: translateY(-3px); 
        box-shadow: 0 8px 20px rgba(0,0,0,0.4);
        border-color: #ffffff;
    }
    
    /* Estilo para histórico */
    .card-img-container {
        width: 100%;
        aspect-ratio: 3 / 4; 
        background-color: #2b2b2b; 
        position: relative;
        display: flex; align-items: center; justify-content: center;
        overflow: hidden;
    }
    
    .card-img-top { 
        width: 100%; height: 100%; object-fit: cover; 
        transition: transform 0.4s, filter 0.4s;
        filter: grayscale(100%) contrast(0.9);
    }
    .card:hover .card-img-top { 
        transform: scale(1.08); 
        filter: grayscale(0%) contrast(1);
    }

    .card-body { padding: 0.8rem; display: flex; flex-direction: column; gap: 5px; } 
    .card-title { 
        font-size: 0.95rem; 
        margin-bottom: 0.2rem; 
        line-height: 1.2; 
        color: var(--text-secondary) !important;
    }
    
    /* --- CONTENEDOR DE FUNCIONES --- */
    .funcs-container {
        display: flex; flex-wrap: wrap; gap: 4px;
        max-height: 65px; overflow-y: auto;
        padding-right: 2px;
    }
    .funcs-container::-webkit-scrollbar { width: 3px; }
    .funcs-container::-webkit-scrollbar-thumb { background: #3a3a3c; border-radius: 3px; }

    .func-badge {
        font-size: 0.65rem; 
        background: #2b2b2b; 
        color: var(--text-secondary);
        padding: 2px 6px; border-radius: 4px;
        border: 1px solid var(--border-color);
        white-space: nowrap; font-weight: 600;
    }
    
    .card-footer { 
        padding: 0.6rem 0.8rem; 
        background: var(--bg-card) !important;
        border-top: 1px solid var(--border-color) !important;
    }
    .btn-sm-custom { padding: 0.25rem 0.5rem; font-size: 0.75rem; border-radius: 6px; }
    .badge-archived { font-size: 0.65rem; background: #2b2b2b; color: var(--text-secondary); padding: 2px 6px; border-radius: 4px; font-weight: bold; width: fit-content; }

    /* Título y badge */
    .text-secondary { color: var(--text-secondary) !important; }
    .badge { background: #2b2b2b !important; color: var(--text-secondary) !important; }

    /* Botones */
    .btn-outline-primary {
        border-color: var(--primary-color) !important;
        color: var(--primary-color) !important;
    }
    .btn-outline-primary:hover {
        background: var(--primary-color) !important;
        color: white !important;
    }
    .btn-light {
        background: #2b2b2b !important;
        border-color: var(--border-color) !important;
    }
    .text-danger { color: #ff453a !important; }

    /* Alerta vacía */
    .alert-light {
        background: var(--bg-card) !important;
        border-color: var(--border-color) !important;
        color: var(--text-secondary) !important;
    }

    /* Modal */
    .modal-content { background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-primary); }
    .modal-header, .modal-footer { border-color: var(--border-color); }
    .form-control { background: #2b2b2b; border-color: var(--border-color); color: #ffffff !important; }
    .form-control::placeholder { color: rgba(255, 255, 255, 0.5) !important; opacity: 1; }
    .form-control:focus { background: #2b2b2b; border-color: #ffffff; color: #ffffff !important; }
    .bg-light { background: #2b2b2b !important; }
    .alert-warning { background: rgba(255, 159, 10, 0.15) !important; border-color: rgba(255, 159, 10, 0.3) !important; color: #ff9f0a !important; }
    
    /* Estilos para el card de filtros */
    .filter-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 20px;
        margin-bottom: 24px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    }
    
    .filtros-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-secondary);
        margin-bottom: 8px;
        display: block;
    }
    
    .form-control {
        background: #2b2b2b !important;
        border: 1px solid var(--border-color) !important;
        color: var(--text-primary) !important;
        border-radius: 8px;
    }
    
    .form-control::placeholder {
        color: var(--text-secondary) !important;
        opacity: 1;
    }
    
    .form-control:focus {
        background: #2b2b2b !important;
        border-color: var(--primary-color) !important;
        box-shadow: 0 0 0 3px rgba(21, 97, 240, 0.2);
        color: var(--text-primary) !important;
    }
    
    .btn-primary {
        background: var(--primary-color) !important;
        border-color: var(--primary-color) !important;
        color: white !important;
    }
    
    .btn-primary:hover {
        background: #0d4fc4 !important;
        border-color: #0d4fc4 !important;
    }
    
    .btn-outline-secondary {
        color: var(--text-secondary) !important;
        border-color: var(--border-color) !important;
        background: transparent !important;
    }
    
    .btn-outline-secondary:hover {
        background: #2b2b2b !important;
        color: var(--text-primary) !important;
        border-color: var(--border-color) !important;
    }
    
    /* Secciones por mes */
    .users-section { margin-bottom: 32px; }
    .section-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 18px; padding: 16px 20px; background: var(--bg-card); border-radius: var(--radius-md); border-left: 4px solid var(--primary-color); cursor: pointer; transition: all 0.2s; }
    .section-header:hover { background: #2b2b2b; }
    .section-header h2 { font-size: 1.2rem; font-weight: 600; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 10px; flex: 1; }
    .section-header h2 i { font-size: 1.3rem; color: #ffffff; }
    .section-header .user-count { background: #2b2b2b; color: var(--text-secondary); padding: 4px 12px; border-radius: 999px; font-size: 0.85rem; font-weight: 600; }
    .section-toggle-btn { background: transparent; border: none; color: var(--text-muted); font-size: 1.4rem; cursor: pointer; padding: 8px; border-radius: 8px; transition: all 0.2s; display: flex; align-items: center; justify-content: center; }
    .section-toggle-btn:hover { background: #2b2b2b; color: #ffffff; }
    .section-toggle-btn i { transition: transform 0.3s ease; }
    .section-toggle-btn.collapsed i { transform: rotate(-90deg); }
    .row.g-3.collapsed { max-height: 0; opacity: 0; overflow: hidden; margin-top: 0 !important; pointer-events: none; transition: all 0.3s ease; }
    
    /* Secciones por mes */
    .users-section { margin-bottom: 32px; }
    .section-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 18px; padding: 16px 20px; background: var(--bg-card); border-radius: var(--radius-md); border-left: 4px solid var(--primary-color); cursor: pointer; transition: all 0.2s; }
    .section-header:hover { background: #2b2b2b; }
    .section-header h2 { font-size: 1.2rem; font-weight: 600; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 10px; flex: 1; }
    .section-header h2 i { font-size: 1.3rem; color: #ffffff; }
    .section-header .user-count { background: #2b2b2b; color: var(--text-secondary); padding: 4px 12px; border-radius: 999px; font-size: 0.85rem; font-weight: 600; }
    .section-toggle-btn { background: transparent; border: none; color: var(--text-muted); font-size: 1.4rem; cursor: pointer; padding: 8px; border-radius: 8px; transition: all 0.2s; display: flex; align-items: center; justify-content: center; }
    .section-toggle-btn:hover { background: #2b2b2b; color: #ffffff; }
    .section-toggle-btn i { transition: transform 0.3s ease; }
    .section-toggle-btn.collapsed i { transform: rotate(-90deg); }
    .row.g-3.collapsed { max-height: 0; opacity: 0; overflow: hidden; margin-top: 0 !important; pointer-events: none; transition: all 0.3s ease; }
</style>
</head>
<body>

<div class="content-wrapper">
    <!-- Card de Filtros -->
    <div class="filter-card">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
            <div>
                <h4 class="fw-bold text-secondary m-0 d-flex align-items-center">
                    <i class="bi bi-archive me-2"></i>Historial de Eventos
                </h4>
                <p class="text-secondary mb-0 small">Eventos archivados y cerrados</p>
            </div>
            <div class="text-end">
                <span class="text-secondary small">Eventos mostrados:</span>
                <div class="fs-4 fw-bold" id="countEventos"><?= $total_eventos ?></div>
            </div>
        </div>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="filtro_titulo" class="filtros-label">Buscar por título</label>
                <input type="text" id="filtro_titulo" name="filtro_titulo" class="form-control" placeholder="Escribe para buscar..." value="<?= htmlspecialchars($filtro_titulo) ?>">
            </div>
            <div class="col-md-3">
                <label for="fecha_desde" class="filtros-label">Cierre desde</label>
                <input type="date" id="fecha_desde" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($fecha_desde) ?>">
            </div>
            <div class="col-md-3">
                <label for="fecha_hasta" class="filtros-label">Cierre hasta</label>
                <input type="date" id="fecha_hasta" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($fecha_hasta) ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Filtrar</button>
                <a href="htr_eventos.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
            </div>
        </form>
    </div>


    <?php 
    if($historial && $historial->num_rows > 0): 
        $eventos_por_mes = [];
        $delay = 0;
        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        while($e = $historial->fetch_assoc()): 
            $fecha_cierre = new DateTime($e['cierre_venta']);
            $mes_anio = $fecha_cierre->format('Y-m');
            $mes_nombre = $meses[(int)$fecha_cierre->format('m') - 1] . ' ' . $fecha_cierre->format('Y');
            if (!isset($eventos_por_mes[$mes_anio])) {
                $eventos_por_mes[$mes_anio] = ['nombre' => $mes_nombre, 'eventos' => []];
            }
            $eventos_por_mes[$mes_anio]['eventos'][] = $e;
        endwhile;
        krsort($eventos_por_mes);
        foreach($eventos_por_mes as $mes_key => $mes_data):
            $mes_id = str_replace('-', '_', $mes_key);
    ?>
    <div class="users-section">
        <div class="section-header" onclick="toggleSection('<?= $mes_id ?>')">
            <h2><i class="bi bi-calendar-month"></i> <?= $mes_data['nombre'] ?> <span class="user-count"><?= count($mes_data['eventos']) ?></span></h2>
            <button class="section-toggle-btn" id="toggle-<?= $mes_id ?>"><i class="bi bi-chevron-down"></i></button>
        </div>
        <div class="row g-3" id="grid-<?= $mes_id ?>">
            <?php foreach($mes_data['eventos'] as $e): 
                $delay += 30;
                $delay += 30;
                
                // 1. IMAGEN
                $img = '';
                if (!empty($e['imagen'])) {
                    // Intentar buscar en interfaz o local
                    $rutas = ["../evt_interfaz/" . $e['imagen'], $e['imagen']];
                    foreach($rutas as $r) { if(file_exists($r)) { $img = $r; break; } }
                }

                // 2. OBTENER FUNCIONES HISTÓRICAS
                $id_evt = $e['id_evento'];
                // Nota: Consultamos a trt_historico_evento
                $sql_func = "SELECT fecha_hora FROM trt_historico_evento.funciones WHERE id_evento = $id_evt ORDER BY fecha_hora ASC";
                $res_func = $conn->query($sql_func);
                $funciones = [];
                if($res_func){
                    while($f = $res_func->fetch_assoc()){
                        $funciones[] = $f['fecha_hora'];
                    }
                }
        ?>
        <div class="col-xxl-2 col-xl-2 col-lg-3 col-md-4 col-6 evento-card" data-titulo="<?= strtolower(htmlspecialchars($e['titulo'])) ?>" data-cierre="<?= $e['cierre_venta'] ?>">
            <div class="card h-100" style="animation-delay: <?= $delay ?>ms">
                <div class="card-img-container">
                    <?php if($img): ?>
                        <img src="<?= htmlspecialchars($img) ?>" class="card-img-top" loading="lazy">
                    <?php else: ?>
                        <div class="text-center text-muted"><i class="bi bi-archive fs-4 opacity-50"></i></div>
                    <?php endif; ?>
                </div>
                
                <div class="card-body">
                    <h6 class="card-title fw-bold text-truncate" title="<?= htmlspecialchars($e['titulo']) ?>">
                        <?= htmlspecialchars($e['titulo']) ?>
                    </h6>
                    <div class="badge-archived mb-2">Cerró: <?= date('d/m/y', strtotime($e['cierre_venta'])) ?></div>

                    <div class="funcs-container">
                        <?php if(count($funciones) > 0): ?>
                            <?php foreach($funciones as $fh): ?>
                                <span class="func-badge">
                                    <i class="bi bi-clock me-1" style="font-size:9px"></i><?= date('d/m H:i', strtotime($fh)) ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="func-badge">Sin datos</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card-footer bg-white border-0 d-flex justify-content-between align-items-center">
                    <button onclick="irAReactivar(<?= $e['id_evento'] ?>)" class="btn btn-outline-primary btn-sm-custom w-100 me-1">
                        <i class="bi bi-arrow-counterclockwise"></i> Reactivar
                    </button>
                    
                    <button class="btn btn-light text-danger btn-sm-custom" onclick="conf('borrar_permanente', <?= $e['id_evento'] ?>, <?= htmlspecialchars(json_encode($e['titulo']), ENT_QUOTES, 'UTF-8') ?>)" title="Borrar Definitivamente">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; else: ?>
        <div class="col-12">
            <div class="alert alert-light border text-center p-5">
                <i class="bi bi-archive fs-1 mb-3" style="color: white; display: block;"></i>
                <p class="mb-0 text-muted">El historial está vacío.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="mConf" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" id="mContent">
            <div class="modal-header border-0 bg-danger text-white" id="mHeader">
                <h5 class="modal-title fw-bold" id="mTitle"><i class="bi bi-shield-lock-fill me-2"></i>Acceso Restringido</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" id="mClose"></button>
            </div>
            <div class="modal-body text-center p-4">
                <i class="bi bi-person-badge-fill" style="font-size: 4rem; color: #ff453a; margin-bottom: 20px; display: block;"></i>
                <p id="mMsg" class="fs-5 mb-3"></p>
                <p class="text-white small mb-3">Ingresa tu contraseña de administrador para continuar.</p>
                <input type="password" id="mPin" class="form-control form-control-lg text-center" placeholder="••••••" maxlength="20" style="letter-spacing: 5px;">
                <div id="mError" class="text-danger small mt-2" style="display: none;">
                    <i class="bi bi-exclamation-triangle"></i> <span id="mErrorText">Contraseña incorrecta</span>
                </div>
                <div id="mWarn" class="alert alert-warning border-0 small d-flex align-items-center mt-3">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i><span id="mTxt"></span>
                </div>
            </div>
            <div class="modal-footer border-0" style="justify-content: center;">
                <button id="mBtn" class="btn btn-danger fw-bold px-5 py-2">
                    <i class="bi bi-trash3-fill me-2"></i>Confirmar Eliminación
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => document.body.classList.add('loaded'));

// REDIRECCIÓN DIRECTA PARA REACTIVAR (Sin AJAX)
function irAReactivar(id) {
    // Pasamos el ID histórico y la bandera
    window.location.href = `editar_evento.php?id=${id}&modo_reactivacion=1`;
}

// LOGICA DE BORRADO (Modal)
const m = new bootstrap.Modal('#mConf');
const els = { 
    msg: document.getElementById('mMsg'), 
    txt: document.getElementById('mTxt'), 
    btn: document.getElementById('mBtn'), 
    pin: document.getElementById('mPin'),
    error: document.getElementById('mError'),
    errorText: document.getElementById('mErrorText')
};

function conf(act, id, nom) {
    els.pin.value = '';
    els.error.style.display = 'none';
    els.msg.innerHTML = `¿Eliminar <strong>${nom}</strong> para siempre?`;
    els.txt.textContent = 'Esta acción NO se puede deshacer.';
    els.btn.disabled = false;
    els.btn.innerHTML = '<i class="bi bi-trash3-fill me-2"></i>Confirmar Eliminación';
    els.btn.onclick = () => ejecutar(act, id);
    m.show();
    setTimeout(() => els.pin.focus(), 300);
}

// Permitir confirmar con Enter
document.getElementById('mPin').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        els.btn.click();
    }
});

function ejecutar(act, id) {
    const password = els.pin.value.trim();
    
    if (!password) { 
        els.error.style.display = 'block';
        els.errorText.textContent = 'Ingresa tu contraseña';
        els.pin.focus();
        return; 
    }

    let fd = new FormData(); 
    fd.append('accion', act); 
    fd.append('id_evento', id);
    fd.append('password', password);
    
    els.btn.disabled = true; 
    els.btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando...';
    els.error.style.display = 'none';
    
    fetch('', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if(d.status === 'success') {
            location.reload();
        } else {
            els.error.style.display = 'block';
            els.errorText.textContent = d.message || 'Error al procesar';
            els.btn.disabled = false; 
            els.btn.innerHTML = '<i class="bi bi-trash3-fill me-2"></i>Confirmar Eliminación';
            els.pin.value = '';
            els.pin.focus();
        }
    })
    .catch(() => { 
        els.error.style.display = 'block';
        els.errorText.textContent = 'Error de conexión';
        els.btn.disabled = false; 
        els.btn.innerHTML = '<i class="bi bi-trash3-fill me-2"></i>Confirmar Eliminación'; 
    });
}
</script>
<script src="filter_realtime.js"></script>
<script>
// JavaScript para toggle de secciones por mes
function toggleSection(sectionId) {
    const grid = document.getElementById('grid-' + sectionId);
    const toggleBtn = document.getElementById('toggle-' + sectionId);
    if (grid && toggleBtn) {
        grid.classList.toggle('collapsed');
        toggleBtn.classList.toggle('collapsed');
        const isCollapsed = grid.classList.contains('collapsed');
        localStorage.setItem('section-' + sectionId, isCollapsed ? 'collapsed' : 'expanded');
    }
}
// Restaurar estado de las secciones al cargar la página
document.addEventListener('DOMContentLoaded', function () {
    const sections = document.querySelectorAll('[id^="grid-"]');
    sections.forEach(grid => {
        const sectionId = grid.id.replace('grid-', '');
        const savedState = localStorage.getItem('section-' + sectionId);
        if (savedState === 'collapsed') {
            const toggleBtn = document.getElementById('toggle-' + sectionId);
            if (grid && toggleBtn) {
                grid.classList.add('collapsed');
                toggleBtn.classList.add('collapsed');
            }
        }
    });
});

</script>
</body>
</html>