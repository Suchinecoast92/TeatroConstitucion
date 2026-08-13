<?php
// 1. CONFIGURACIÓN Y SEGURIDAD
// Versión limpia - Conflictos de merge resueltos - 2026-01-26
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Mexico_City');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../conexion.php";
require_once __DIR__ . '/../config/ventas.php';
if (file_exists("../transacciones_helper.php")) {
    require_once "../transacciones_helper.php";
}
require_once __DIR__ . "/../api/registrar_cambio.php";

// Verificar permisos
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] !== 'admin' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']))) {
    die('<div style="display:flex;height:100vh;align-items:center;justify-content:center;font-family:sans-serif;color:#ef4444;"><h1>Acceso Denegado</h1></div>');
}

$errores_php = [];
$modo_reactivacion = isset($_GET['modo_reactivacion']) && $_GET['modo_reactivacion'] == 1;
$id_evento = $_GET['id'] ?? 0;

// ==================================================================
// 2. PROCESADOR (POST)
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion']) && $_POST['accion'] == 'actualizar') {
        $titulo = trim($_POST['titulo']);
        $desc = trim($_POST['descripcion']);
        $tipo = $_POST['tipo'];
        $ini = $_POST['inicio_venta'];
        $fin = $_POST['cierre_venta'];
        $img = $_POST['imagen_actual'];

        if (empty($titulo))
            $errores_php[] = "Falta el título.";
        if (empty($_POST['funciones']))
            $errores_php[] = "Debe agregar al menos una función.";
        if (empty($ini))
            $errores_php[] = "Falta inicio de venta.";

        if (!empty($ini) && strtotime($ini) < time()) {
            $errores_php[] = "La fecha/hora de inicio de venta no puede ser anterior a ahora.";
        }

        if (!empty($_POST['funciones'])) {
            foreach ($_POST['funciones'] as $func) {
                if (strtotime($func) <= time()) {
                    $errores_php[] = "No se puede guardar con funciones en el pasado.";
                    break;
                }
            }
            // FORZAMOS (y validamos) que el cierre de la venta sea SIEMPRE y exactamente 24 horas después de la última función programada.
            // Ignoramos lo que venga del lado del cliente.
            $ultimaFuncion = max(array_map('strtotime', $_POST['funciones']));
            $fin = date('Y-m-d H:i:s', $ultimaFuncion + SEGUNDOS_CIERRE_VENTAS_POST_FUNCION);
        }

        if (empty($errores_php)) {
            $conn->begin_transaction();
            try {
                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
                    $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                        if (!is_dir("imagenes"))
                            mkdir("imagenes", 0755, true);
                        $ruta = "imagenes/evt_" . time() . "." . $ext;
                        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta)) {
                            $img = $ruta;
                            if ($_POST['imagen_actual'] && file_exists($_POST['imagen_actual']))
                                unlink($_POST['imagen_actual']);
                        }
                    }
                }

                if ($modo_reactivacion) {
                    $res_old = $conn->query("SELECT mapa_json FROM trt_historico_evento.evento WHERE id_evento = $id_evento");
                    $mapa_json = $res_old->fetch_object()->mapa_json;

                    $stmt = $conn->prepare("INSERT INTO evento (titulo, descripcion, imagen, tipo, inicio_venta, cierre_venta, finalizado, mapa_json) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
                    $stmt->bind_param("sssisss", $titulo, $desc, $img, $tipo, $ini, $fin, $mapa_json);
                    $stmt->execute();
                    $new_id = $conn->insert_id;
                    $stmt->close();

                    $conn->query("INSERT INTO categorias (id_evento, nombre_categoria, precio, color) SELECT $new_id, nombre_categoria, precio, color FROM trt_historico_evento.categorias WHERE id_evento = $id_evento");

                    $stmt_f = $conn->prepare("INSERT INTO funciones (id_evento, fecha_hora, estado) VALUES (?, ?, 0)");
                    foreach ($_POST['funciones'] as $fh) {
                        $stmt_f->bind_param("is", $new_id, $fh);
                        $stmt_f->execute();
                    }
                    $stmt_f->close();

                    // COMENTADO POR SOLICITUD DEL USUARIO:
                    // Al reactivar, NO borrar el historial. Se crea un nuevo evento, pero el anterior conserva sus boletos y estadísticas.
                    // $conn->query("DELETE FROM trt_historico_evento.boletos WHERE id_evento = $id_evento");
                    // $conn->query("DELETE FROM trt_historico_evento.promociones WHERE id_evento = $id_evento");
                    // $conn->query("DELETE FROM trt_historico_evento.categorias WHERE id_evento = $id_evento");
                    // $conn->query("DELETE FROM trt_historico_evento.funciones WHERE id_evento = $id_evento");
                    // $conn->query("DELETE FROM trt_historico_evento.evento WHERE id_evento = $id_evento");

                    $evt_id = $new_id;
                } else {
                    $stmt = $conn->prepare("UPDATE evento SET titulo=?, inicio_venta=?, cierre_venta=?, descripcion=?, imagen=?, tipo=? WHERE id_evento=?");
                    $stmt->bind_param("sssssii", $titulo, $ini, $fin, $desc, $img, $tipo, $id_evento);
                    $stmt->execute();

                    // ==================================================================
                    // Actualizar funciones SIN perder historial ni boletos vendidos
                    // ==================================================================

                    // 1) Cargar funciones existentes con conteo de boletos vendidos
                    $funciones_existentes_db = [];
                    $sql_funciones = "SELECT f.id_funcion, f.fecha_hora, f.estado,
                                        (SELECT COUNT(*) FROM boletos b WHERE b.id_funcion = f.id_funcion AND b.estatus = 1) AS boletos_vendidos
                                      FROM funciones f
                                      WHERE f.id_evento = $id_evento";
                    if ($res_f_post = $conn->query($sql_funciones)) {
                        while ($row = $res_f_post->fetch_assoc()) {
                            $row['boletos_vendidos'] = (int) $row['boletos_vendidos'];
                            $funciones_existentes_db[] = $row;
                        }
                        $res_f_post->free();
                    }

                    // Indexar funciones existentes por fecha_hora para coincidencia rápida
                    $funciones_por_fecha = [];
                    foreach ($funciones_existentes_db as $f) {
                        $funciones_por_fecha[$f['fecha_hora']] = $f;
                    }

                    // Normalizar fechas recibidas por POST (asegurar segundos ":00")
                    $fechas_post = [];
                    if (!empty($_POST['funciones']) && is_array($_POST['funciones'])) {
                        foreach ($_POST['funciones'] as $fh) {
                            $fh = trim($fh);
                            if ($fh === '') continue;
                            // Si viene como "Y-m-d H:i" agregar ":00"
                            if (strlen($fh) === 16) {
                                $fh .= ':00';
                            }
                            $fechas_post[] = $fh;
                        }
                    }

                    // 2) Eliminar SOLO funciones futuras sin boletos que el usuario quitó
                    foreach ($funciones_existentes_db as $f) {
                        $fecha = $f['fecha_hora'];
                        $estado = (int) $f['estado'];
                        $boletosVendidos = (int) $f['boletos_vendidos'];

                        $esPasada = ($estado === 1) || (strtotime($fecha) <= time());

                        // No tocar funciones pasadas ni las que tienen boletos vendidos
                        if ($esPasada || $boletosVendidos > 0) {
                            continue;
                        }

                        // Si la fecha de esta función futura ya no está en el POST, el usuario la eliminó
                        if (!in_array($fecha, $fechas_post, true)) {
                            $id_func = (int) $f['id_funcion'];
                            $conn->query("DELETE FROM funciones WHERE id_funcion = $id_func");
                        }
                    }

                    // 3) Insertar funciones NUEVAS (fechas que no existían antes)
                    if (!empty($fechas_post)) {
                        $stmt_f = $conn->prepare("INSERT INTO funciones (id_evento, fecha_hora, estado) VALUES (?, ?, 0)");
                        foreach ($fechas_post as $fh_norm) {
                            // Si ya existe una función con esa fecha, no crear otra
                            if (isset($funciones_por_fecha[$fh_norm])) {
                                continue;
                            }
                            $stmt_f->bind_param("is", $id_evento, $fh_norm);
                            $stmt_f->execute();
                        }
                        $stmt_f->close();
                    }
                    $evt_id = $id_evento;
                }

                $conn->commit();
                if (function_exists('registrar_transaccion'))
                    registrar_transaccion('evento_guardar', "Guardó evento: $titulo");
                registrar_cambio('evento', $evt_id, null, ['accion' => $modo_reactivacion ? 'reactivar' : 'editar']);
                registrar_cambio('funcion', $evt_id, null, ['accion' => 'modificar', 'cantidad' => count($_POST['funciones'])]);

                ?>
                <!DOCTYPE html>
                <html lang="es">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
                    <link rel="stylesheet" href="../assets/css/teatro-style.css">
                    <style>
                        body {
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            height: 100vh;
                            margin: 0;
                            overflow: hidden;
                        }

                        .success-card {
                            background: var(--bg-secondary);
                            border-radius: 24px;
                            padding: 40px;
                            text-align: center;
                            box-shadow: var(--shadow-xl);
                            max-width: 380px;
                            width: 90%;
                            border: 1px solid var(--border-color);
                            animation: popIn 0.5s ease forwards;
                        }

                        .icon-circle {
                            width: 80px;
                            height: 80px;
                            background: var(--success-bg);
                            color: var(--success);
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 40px;
                            margin: 0 auto 20px;
                        }

                        .progress-track {
                            height: 6px;
                            background: var(--bg-tertiary);
                            border-radius: 3px;
                            margin-top: 30px;
                            overflow: hidden;
                        }

                        .progress-fill {
                            height: 100%;
                            background: var(--success);
                            width: 0;
                            transition: width 1.2s linear;
                        }

                        @keyframes popIn {
                            from {
                                opacity: 0;
                                transform: scale(0.8);
                            }

                            to {
                                opacity: 1;
                                transform: scale(1);
                            }
                        }

                        body.exiting {
                            animation: fadeOut 0.4s ease-in forwards;
                        }

                        @keyframes fadeOut {
                            to {
                                opacity: 0;
                                transform: scale(0.95);
                            }
                        }

                        h4 {
                            color: var(--text-primary);
                        }

                        p {
                            color: var(--text-muted);
                        }
                    </style>
                </head>

                <body>
                    <div class="success-card">
                        <div class="icon-circle"><i class="bi bi-check-lg"></i></div>
                        <h4 class="fw-bold mb-2">¡Operación Exitosa!</h4>
                        <p class="small mb-0">Los datos se han guardado correctamente.</p>
                        <div class="progress-track">
                            <div class="progress-fill" id="pBar"></div>
                        </div>
                        <p class="mt-2" style="font-size: 0.75rem; font-weight: 700;">REDIRIGIENDO...</p>
                    </div>
                    <script>
                        setTimeout(() => document.getElementById('pBar').style.width = '100%', 50);
                        localStorage.setItem("evt_upd", Date.now());
                        setTimeout(() => { document.body.classList.add('exiting'); setTimeout(() => { window.parent.location.href = "index.php?tab=activos"; }, 350); }, 1300);
                    </script>
                </body>

                </html>
                <?php exit;
            } catch (Exception $e) {
                $conn->rollback();
                $errores_php[] = "Error DB: " . $e->getMessage();
            }
        }
    }
}

// ==================================================================
// 3. CARGA DE DATOS (GET)
// ==================================================================
if (!$id_evento || !is_numeric($id_evento)) {
    header('Location: act_evento.php');
    exit;
}

$tabla_evt = $modo_reactivacion ? "trt_historico_evento.evento" : "evento";
$tabla_fun = $modo_reactivacion ? "trt_historico_evento.funciones" : "funciones";

$res = $conn->query("SELECT * FROM $tabla_evt WHERE id_evento = $id_evento");
$evento = $res->fetch_assoc();
if (!$evento) {
    header('Location: act_evento.php');
    exit;
}

$funciones_existentes = [];
if (!$modo_reactivacion) {
    $tabla_boletos = "boletos";
    $res_f = $conn->query("SELECT f.id_funcion, f.fecha_hora, f.estado, (SELECT COUNT(*) FROM $tabla_boletos b WHERE b.id_funcion = f.id_funcion AND b.estatus = 1) as boletos_vendidos FROM $tabla_fun f WHERE f.id_evento = $id_evento ORDER BY f.fecha_hora ASC");
    while ($f = $res_f->fetch_assoc()) {
        $funciones_existentes[] = ['id_funcion' => (int) $f['id_funcion'], 'fecha' => new DateTime($f['fecha_hora']), 'estado' => $f['estado'], 'boletos' => (int) $f['boletos_vendidos']];
    }
}

$defaultVenta = $modo_reactivacion ? '' : date('Y-m-d H:i', strtotime($evento['inicio_venta']));
$defaultCierre = $modo_reactivacion ? '' : date('Y-m-d H:i', strtotime($evento['cierre_venta']));
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $modo_reactivacion ? 'Reactivar' : 'Editar' ?> Evento</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/teatro-style.css">
    <style>
        body {
            padding: 30px 20px;
            min-height: 100vh;
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        body.loaded {
            opacity: 1;
        }

        body.exiting {
            opacity: 0;
        }

        .main-wrapper {
            max-width: 850px;
            margin: 0 auto;
        }

        .card {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            padding: 40px;
            border: 1px solid var(--border-color);
        }

        .form-control,
        .form-select {
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            background: var(--bg-tertiary);
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .form-control:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(21, 97, 240, 0.2);
            outline: none;
        }

        .form-label {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }

        .form-text {
            color: var(--text-muted) !important;
            font-size: 0.8rem;
        }

        .btn {
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--accent-blue);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: var(--accent-blue-hover);
            transform: translateY(-2px);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-success {
            background: var(--success);
            color: #000;
        }

        .btn-success:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .input-error {
            border-color: var(--danger) !important;
        }

        .input-error:not(select) {
            background: var(--danger-bg) !important;
        }

        select.input-error,
        select.form-control.input-error {
            background: var(--bg-tertiary) !important;
            color: var(--text-primary) !important;
        }

        select.form-control option,
        .form-select option {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        .tooltip-error {
            color: var(--danger);
            font-size: 0.85em;
            margin-top: 5px;
            display: none;
            font-weight: 600;
        }

        .funciones-section {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 20px;
        }

        #lista-funciones {
            background: var(--bg-primary);
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-sm);
            padding: 15px;
            min-height: 70px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .funcion-item {
            padding: 8px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .funcion-item.future {
            background: var(--bg-secondary);
            border: 1px solid var(--accent-blue);
            color: var(--accent-blue);
        }

        .funcion-item.past {
            background: var(--danger-bg);
            border: 1px solid var(--danger);
            color: var(--danger);
            text-decoration: line-through;
            opacity: 0.7;
        }

        .funcion-item.protected {
            background: linear-gradient(135deg, var(--bg-secondary) 0%, rgba(21, 97, 240, 0.1) 100%);
            border: 2px solid var(--accent-blue);
        }

        .funcion-item button {
            background: none;
            border: none;
            color: var(--danger);
            font-size: 1.1em;
            padding: 0;
            cursor: pointer;
        }

        .funcion-item .func-protected {
            color: var(--warning);
            font-size: 0.9em;
            cursor: help;
        }

        .funcion-item .boletos-count {
            background: var(--success-bg);
            color: var(--success);
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 4px;
        }

        .input-group {
            display: flex;
            gap: 8px;
        }

        .input-group .form-control {
            flex: 1;
        }

        .cierre-container {
            position: relative;
        }

        .cierre-lock-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 6px 10px;
            color: var(--text-muted);
            cursor: pointer;
        }

        .cierre-lock-btn.unlocked {
            background: var(--warning-bg);
            color: var(--warning);
            border-color: var(--warning);
        }

        .alert-danger {
            background: var(--danger-bg) !important;
            border: 1px solid rgba(255, 69, 58, 0.3) !important;
            color: var(--danger) !important;
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            margin-bottom: 20px;
        }

        .alert-danger ul {
            margin: 0;
            padding-left: 20px;
        }

        .alert-warning {
            background: var(--warning-bg);
            border: 1px solid rgba(255, 159, 10, 0.3);
            color: var(--warning);
            border-radius: var(--radius-sm);
            padding: 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .page-header h2 {
            margin: 0;
            color: var(--accent-blue);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: -10px;
        }

        .col-12 {
            width: 100%;
            padding: 10px;
        }

        .col-md-5 {
            width: 41.666%;
            padding: 10px;
        }

        .col-md-6 {
            width: 50%;
            padding: 10px;
        }

        .col-md-7 {
            width: 58.333%;
            padding: 10px;
        }

        @media (max-width: 768px) {

            .col-md-5,
            .col-md-6,
            .col-md-7 {
                width: 100%;
            }
        }

        hr {
            border: none;
            border-top: 1px solid var(--border-color);
            margin: 30px 0;
        }

        .img-preview {
            width: 100px;
            height: 140px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            margin-top: 10px;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(20px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: linear-gradient(160deg, #1e1e22 0%, #141416 50%, #1a1a1e 100%);
            border-radius: 24px;
            padding: 0;
            max-width: 440px;
            width: 92%;
            border: 1px solid rgba(255, 255, 255, 0.08);
            text-align: center;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.7);
            animation: modalPop 0.4s ease;
            overflow: hidden;
        }

        @keyframes modalPop {
            from {
                opacity: 0;
                transform: scale(0.85) translateY(-30px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-icon-container {
            background: linear-gradient(135deg, #ff9f0a 0%, #ff6b35 50%, #ff453a 100%);
            padding: 40px 30px;
            position: relative;
        }

        .modal-icon-container::after {
            content: '';
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            border-left: 30px solid transparent;
            border-right: 30px solid transparent;
            border-top: 25px solid #ff453a;
        }

        .modal-icon {
            font-size: 4rem;
            color: white;
        }

        .modal-body-content {
            padding: 50px 35px 30px;
        }

        .modal-content h5 {
            color: #ffffff;
            margin-bottom: 16px;
            font-size: 1.5rem;
            font-weight: 800;
        }

        .modal-content p {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 0;
            font-size: 1rem;
            line-height: 1.7;
        }

        .modal-buttons {
            display: flex;
            gap: 14px;
            padding: 25px 35px 35px;
            justify-content: center;
        }

        .modal-buttons .btn {
            flex: 1;
            padding: 16px 24px;
            font-weight: 700;
            border-radius: 14px;
        }

        .btn-stay {
            background: linear-gradient(135deg, var(--accent-blue) 0%, #4f46e5 100%);
            color: white;
            border: none;
        }

        .btn-leave {
            background: rgba(255, 69, 58, 0.1);
            color: var(--danger);
            border: 2px solid rgba(255, 69, 58, 0.5);
        }

        .btn-leave:hover {
            background: var(--danger);
            color: white;
        }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <div style="margin-bottom: 20px;">
            <button onclick="abrirModalCancelar()" class="btn btn-secondary"><i class="bi bi-arrow-left"></i>
                <?= $modo_reactivacion ? 'Cancelar Reactivación' : 'Volver a Eventos' ?></button>
        </div>
        <div class="card">
            <div class="page-header">
                <h2><i class="bi <?= $modo_reactivacion ? 'bi-arrow-counterclockwise' : 'bi-pencil-square' ?>"></i>
                    <?= $modo_reactivacion ? 'Reactivar Evento' : 'Editar Evento' ?></h2>
            </div>

            <?php if ($modo_reactivacion): ?>
                <div class="alert-warning"><i class="bi bi-exclamation-triangle-fill"></i>
                    <div><strong>Modo Reactivación:</strong> Las funciones y fechas anteriores se han borrado. Debes agregar
                        nuevas funciones.</div>
                </div>
            <?php endif; ?>

            <?php if ($errores_php): ?>
                <div class="alert-danger">
                    <ul><?php foreach ($errores_php as $e)
                        echo "<li>$e</li>"; ?></ul>
                </div>
            <?php endif; ?>

            <form id="fEdit" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="actualizar">
                <input type="hidden" name="id_evento" value="<?= $id_evento ?>">
                <input type="hidden" name="imagen_actual" value="<?= htmlspecialchars($evento['imagen']) ?>">

                <div class="row">
                    <div class="col-12">
                        <label class="form-label">Título del Evento</label>
                        <input type="text" id="tit" name="titulo" class="form-control"
                            style="font-size: 1.1rem; font-weight: 600;"
                            value="<?= htmlspecialchars($evento['titulo']) ?>" required>
                    </div>
                    <div class="col-12">
                        <div class="funciones-section">
                            <label class="form-label"><i class="bi bi-calendar-week"
                                    style="margin-right: 8px;"></i>Gestión de Funciones</label>
                            <div class="input-group">
                                <input type="text" id="fDate" class="form-control" placeholder="Selecciona Fecha"
                                    readonly>
                                <input type="text" id="fTime" class="form-control" placeholder="Hora" readonly
                                    style="max-width:130px">
                                <button type="button" id="fAdd" class="btn btn-success" disabled><i
                                        class="bi bi-plus-lg"></i> Agregar</button>
                            </div>
                            <div id="ttFunc" class="tooltip-error"></div>
                            <div id="lista-funciones">
                                <p id="noFunc"
                                    style="color: var(--text-muted); margin: 0; width: 100%; text-align: center; font-style: italic; font-size: 0.9rem;">
                                    <i class="bi bi-inbox" style="margin-right: 8px;"></i>No hay funciones asignadas.
                                </p>
                            </div>
                            <div id="hidFunc"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Inicio Venta</label>
                        <input type="text" id="ini" name="inicio_venta" class="form-control"
                            value="<?= $defaultVenta ?>" readonly required placeholder="Selecciona fecha y hora">
                        <div id="ttIni" class="tooltip-error"></div>
                        <div class="form-text"><i class="bi bi-info-circle"></i> No puede ser anterior a ahora</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" style="color: var(--text-muted);">Cierre Venta (Automático)</label>
                        <div class="cierre-container">
                            <input type="text" id="fin" name="cierre_venta" class="form-control"
                                value="<?= $defaultCierre ?>" readonly style="padding-right: 50px;">
                            <button type="button" class="cierre-lock-btn" id="lockBtn"
                                title="Desbloquear edición manual"><i class="bi bi-lock-fill"></i></button>
                        </div>
                        <div class="form-text"><i class="bi bi-info-circle"></i> Se calcula 24 horas después de la última
                            función.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descripción</label>
                        <textarea id="desc" name="descripcion" class="form-control" rows="4"
                            required><?= htmlspecialchars($evento['descripcion']) ?></textarea>
                        <div id="ttDesc" class="tooltip-error"></div>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label">Imagen Promocional</label>
                        <input type="file" id="img" name="imagen" class="form-control" accept="image/*">
                        <?php if ($evento['imagen']): ?>
                            <div
                                style="margin-top: 12px; padding: 10px; background: var(--bg-tertiary); border-radius: var(--radius-sm); display: inline-block;">
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 6px;">Imagen Actual:
                                </div>
                                <img src="../evt_interfaz/<?= htmlspecialchars($evento['imagen']) ?>" class="img-preview"
                                    onerror="this.style.display='none'">
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Tipo de Escenario</label>
                        <select id="tipo" name="tipo" class="form-control" required>
                            <option value="1" <?= $evento['tipo'] == 1 ? 'selected' : '' ?>>🎭 Teatro (420 Butacas)
                            </option>
                            <option value="2" <?= $evento['tipo'] == 2 ? 'selected' : '' ?>>🚶 Pasarela (540 Butacas)
                            </option>
                        </select>
                        <div id="ttTipo" class="tooltip-error"></div>
                    </div>
                </div>
                <hr>
                <button type="submit" id="bSub" class="btn btn-primary"
                    style="width: 100%; padding: 16px; font-size: 1.1rem;" disabled>
                    <?= $modo_reactivacion ? '<i class="bi bi-arrow-counterclockwise"></i> Confirmar Reactivación' : '<i class="bi bi-check2-circle"></i> Guardar Cambios' ?>
                </button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="modalCancelar">
        <div class="modal-content">
            <div class="modal-icon-container"><i class="bi bi-exclamation-triangle-fill modal-icon"></i></div>
            <div class="modal-body-content">
                <h5>¿Salir sin guardar?</h5>
                <p>Tienes cambios sin guardar. Si sales ahora, perderás todas las modificaciones.
                    <?php if ($modo_reactivacion): ?><br><strong>El evento no se reactivará y permanecerá en el
                            historial.</strong><?php endif; ?>
                </p>
            </div>
            <div class="modal-buttons">
                <button class="btn btn-leave" onclick="confirmarSalida()"><i class="bi bi-box-arrow-left"></i>
                    Salir</button>
                <button class="btn btn-stay" onclick="cerrarModal()"><i class="bi bi-pencil-fill"></i> Seguir
                    Editando</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.body.classList.add('loaded');
            flatpickr.localize(flatpickr.l10ns.es);
            const now = new Date();
            const esReactivacion = <?= $modo_reactivacion ? 'true' : 'false' ?>;
            let cierreBloqueado = true;
            let funcs = [];
            let formModificado = false;

            <?php if (!$modo_reactivacion): ?>
                <?php foreach ($funciones_existentes as $f): ?>
                    <?php $fechaObj = $f['fecha'];
                    $esPasada = $f['estado'] == 1 || $fechaObj < new DateTime(); ?>
                    funcs.push({ id: <?= $f['id_funcion'] ?>, date: new Date('<?= $fechaObj->format('c') ?>'), past: <?= $esPasada ? 'true' : 'false' ?>, boletos: <?= $f['boletos'] ?> });
                <?php endforeach; ?>
            <?php endif; ?>

            const els = { add: document.getElementById('fAdd'), sub: document.getElementById('bSub'), list: document.getElementById('lista-funciones'), hid: document.getElementById('hidFunc'), no: document.getElementById('noFunc'), ttF: document.getElementById('ttFunc'), ttI: document.getElementById('ttIni'), ini: document.getElementById('ini'), fin: document.getElementById('fin'), desc: document.getElementById('desc'), img: document.getElementById('img'), tipo: document.getElementById('tipo'), ttDesc: document.getElementById('ttDesc'), ttTipo: document.getElementById('ttTipo'), lockBtn: document.getElementById('lockBtn') };

            const fpD = flatpickr("#fDate", { minDate: "today", dateFormat: "Y-m-d", onChange: check });
            const fpT = flatpickr("#fTime", { enableTime: true, noCalendar: true, dateFormat: "H:i", time_24hr: true, minuteIncrement: 15, onChange: check });
            const fpI = flatpickr("#ini", { enableTime: true, minDate: now, dateFormat: "Y-m-d H:i", onChange: () => val(false) });
            const fpE = flatpickr("#fin", { enableTime: true, dateFormat: "Y-m-d H:i", clickOpens: false });

            els.lockBtn.onclick = () => { cierreBloqueado = !cierreBloqueado; if (cierreBloqueado) { els.lockBtn.innerHTML = '<i class="bi bi-lock-fill"></i>'; els.lockBtn.classList.remove('unlocked'); fpE.set('clickOpens', false); recalcularCierre(); } else { els.lockBtn.innerHTML = '<i class="bi bi-unlock-fill"></i>'; els.lockBtn.classList.add('unlocked'); fpE.set('clickOpens', true); } };

            function recalcularCierre() { const futuras = funcs.filter(f => !f.past); if (futuras.length) { const funcionMayor = futuras.reduce((max, f) => f.date > max.date ? f : max, futuras[0]); fpE.setDate(new Date(funcionMayor.date.getTime() + <?= (int) MS_CIERRE_VENTAS_POST_FUNCION ?>), true); } }
            function check() { els.add.disabled = !(fpD.selectedDates.length && fpT.selectedDates.length); }

            els.add.onclick = () => {
                if (!fpD.selectedDates[0] || !fpT.selectedDates[0]) return;
                let dt = new Date(fpD.selectedDates[0].getTime()); dt.setHours(fpT.selectedDates[0].getHours()); dt.setMinutes(fpT.selectedDates[0].getMinutes()); dt.setSeconds(0);
                if (dt <= new Date(Date.now() + 60000)) { alert("La función debe ser en el futuro."); return; }
                if (funcs.some(f => f.date.getTime() === dt.getTime())) { alert("Ya existe esta función."); return; }
                funcs.push({ id: 0, date: dt, past: false, boletos: 0 }); funcs.sort((a, b) => a.date - b.date); formModificado = true; fpD.clear(); fpT.clear(); check(); upd();
            };

            function upd() {
                els.list.innerHTML = ''; els.hid.innerHTML = '';
                const futuras = funcs.filter(f => !f.past);
                if (!funcs.length) { els.list.appendChild(els.no); fpI.set('maxDate', null); fpE.setDate(null); }
                else {
                    funcs.forEach((f, i) => {
                        const d = f.date;
                        const fechaStr = d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
                        const sqlDate = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}:00`;
                        const clase = f.past ? 'past' : 'future';
                        const icono = f.past ? 'bi-hourglass-bottom' : 'bi-calendar-event';
                        const tieneBoletos = (f.boletos || 0) > 0;
                        let btnDel = tieneBoletos ? `<span class="func-protected" title="${f.boletos} boletos vendidos"><i class="bi bi-lock-fill"></i></span>` : `<button type="button" onclick="del(${i})">×</button>`;
                        let boletosInfo = tieneBoletos ? `<span class="boletos-count">${f.boletos} <i class="bi bi-ticket-fill"></i></span>` : '';
                        els.list.innerHTML += `<div class="funcion-item ${clase}${tieneBoletos ? ' protected' : ''}"><i class="bi ${icono}"></i> ${fechaStr}${boletosInfo}${btnDel}</div>`;
                        if (!f.past) { els.hid.innerHTML += `<input type="hidden" name="funciones[]" value="${sqlDate}">`; }
                    });
                    if (futuras.length) { fpI.set('maxDate', new Date(futuras[0].date.getTime() - 60000)); }
                    recalcularCierre();
                }
                val(false);
            }

            window.del = i => { if (funcs[i].boletos > 0) { alert(`No se puede eliminar: tiene ${funcs[i].boletos} boletos vendidos.`); return; } funcs.splice(i, 1); formModificado = true; upd(); };

            function clearFieldErrors() {
                [els.ttF, els.ttI, els.ttDesc, els.ttTipo].forEach(e => { if (e) e.style.display = 'none'; });
                document.querySelectorAll('.input-error').forEach(e => e.classList.remove('input-error'));
            }

            function val(showErrors = false) {
                let ok = true;
                clearFieldErrors();
                if (!document.getElementById('tit').value.trim()) ok = false;
                const futuras = funcs.filter(f => !f.past);
                if (!futuras.length) {
                    ok = false;
                    if (showErrors) err(els.ttF, null, 'Añade al menos una función futura.');
                }
                if (!fpI.selectedDates.length) {
                    if (futuras.length) {
                        ok = false;
                        if (showErrors) err(els.ttI, els.ini, 'Requerido.');
                    }
                } else {
                    if (fpI.selectedDates[0] < new Date()) {
                        ok = false;
                        if (showErrors) err(els.ttI, els.ini, 'No puede ser anterior a ahora.');
                    }
                    if (futuras.length && fpI.selectedDates[0] >= futuras[0].date) {
                        ok = false;
                        if (showErrors) err(els.ttI, els.ini, 'Debe ser antes de la primera función.');
                    }
                }
                if (!els.desc.value.trim()) {
                    ok = false;
                    if (showErrors) err(els.ttDesc, els.desc, 'Descripción obligatoria.');
                }
                els.sub.disabled = !ok;
                return ok;
            }

            function err(t, i, m) { t.textContent = m; t.style.display = 'flex'; if (i) i.classList.add('input-error'); }

            ['tit', 'desc', 'img', 'tipo'].forEach(id => { const el = document.getElementById(id); el.addEventListener(id === 'img' || id === 'tipo' ? 'change' : 'input', () => { formModificado = true; val(false); }); });
            document.getElementById('ini').addEventListener('change', () => { formModificado = true; val(false); });
            document.getElementById('fin').addEventListener('change', () => formModificado = true);
            window.addEventListener('beforeunload', function (e) { if (formModificado) { e.preventDefault(); e.returnValue = ''; return ''; } });
            document.getElementById('fEdit').addEventListener('submit', e => { const futuras = funcs.filter(f => !f.past); if (!futuras.length) { e.preventDefault(); alert("No puedes guardar sin funciones futuras."); return; } if (!val(true)) { e.preventDefault(); alert('Por favor, completa todos los campos.'); } else { formModificado = false; } });
            upd();
        });

        let urlDestino = null;
        function abrirModalCancelar() { if (typeof formModificado !== 'undefined' && formModificado) { document.getElementById('modalCancelar').classList.add('active'); } else { goBack(); } }
        function cerrarModal() { document.getElementById('modalCancelar').classList.remove('active'); urlDestino = null; }
        function confirmarSalida() { cerrarModal(); goBack(); }
        function goBack() { window.onbeforeunload = null; formModificado = false; document.body.classList.remove('loaded'); document.body.classList.add('exiting'); const esReactivacion = <?= $modo_reactivacion ? 'true' : 'false' ?>; const tab = esReactivacion ? 'historial' : 'activos'; setTimeout(() => window.parent.location.href = urlDestino || 'index.php?tab=' + tab, 350); }
    </script>
</body>

</html>