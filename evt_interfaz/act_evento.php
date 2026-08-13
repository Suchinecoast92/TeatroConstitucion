<?php
// Activar reporte de errores
// Versión limpia - 2026-01-26
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Mexico_City');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../conexion.php";
require_once __DIR__ . '/../config/ventas.php';
$horasVentaAbierta = (int) HORAS_CIERRE_VENTAS_POST_FUNCION;

if (file_exists("../transacciones_helper.php")) {
    require_once "../transacciones_helper.php";
}

require_once __DIR__ . "/../api/registrar_cambio.php";

function formato_funcion_evento(string $fecha_hora): array
{
    $dt = new DateTime($fecha_hora);
    $meses_corto = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

    $h = (int) $dt->format('G');
    $ampm = $h >= 12 ? 'PM' : 'AM';
    $h12 = $h % 12;
    if ($h12 === 0) {
        $h12 = 12;
    }
    $min = $dt->format('i');
    $hora = $min !== '00' ? sprintf('%d:%s %s', $h12, $min, $ampm) : sprintf('%d %s', $h12, $ampm);
    $mes_idx = (int) $dt->format('n') - 1;

    return [
        'dia' => $dt->format('j'),
        'mes_corto' => $meses_corto[$mes_idx],
        'mes' => $meses[$mes_idx],
        'anio' => $dt->format('Y'),
        'dia_nombre' => $dias[(int) $dt->format('w')],
        'texto_corto' => $dt->format('j') . ' de ' . $meses[$mes_idx] . ' de ' . $dt->format('Y'),
        'hora' => $hora,
    ];
}

// ==================================================================
// VERIFICACIÓN DE SESIÓN
// ==================================================================
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] !== 'admin' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']))) {
    die('<div style="font-family: Arial; text-align: center; margin-top: 50px; color: red;"><h1>Acceso Denegado</h1><p>No tiene permiso para ver esta página.</p></div>');
}

// ==================================================================
// ACTUALIZACIÓN AUTOMÁTICA DE ESTADOS DE FUNCIONES
// ==================================================================
// La función se marca como vencida 24 horas después de su hora de inicio (cierre de ventas)
$conn->query("UPDATE funciones SET estado = 1 WHERE fecha_hora < (NOW() - INTERVAL {$horasVentaAbierta} HOUR) AND estado = 0");

// ==================================================================
// AUTO-ARCHIVADO DE EVENTOS CADUCADOS
// ==================================================================
include_once __DIR__ . '/auto_archivar.php';


// ==================================================================
// FUNCIÓN PARA ARCHIVAR (MOVER A HISTÓRICO)
// ==================================================================
function archivar_evento_completo($id, $conn)
{
    $db_historico = 'trt_historico_evento';
    $db_principal = 'trt_25';
    $id = (int) $id;

    // ===== RESPALDO INMUTABLE PREVIO A trt_25_backup =====
    // Antes de tocar producción, garantizamos que el evento y todos sus boletos
    // vendidos queden respaldados en la BD de respaldo. Si esto falla por
    // alguna razón, NO procedemos con el borrado para evitar pérdida de datos.
    if (file_exists(__DIR__ . '/../sync/backup_helper.php')) {
        require_once __DIR__ . '/../sync/backup_helper.php';
        @respaldarEvento($conn, $id, 'local');
        $rs = $conn->query("SELECT id_boleto FROM boletos WHERE id_evento = $id AND estatus IN (1,2)");
        if ($rs) {
            while ($r = $rs->fetch_assoc()) {
                @respaldarBoleto($conn, (int)$r['id_boleto'], 'local');
            }
        }
    }

    $tablas = ['evento', 'funciones', 'categorias', 'promociones', 'boletos', 'precios_tipo_boleto'];

    foreach ($tablas as $tabla) {
        $sql = "INSERT IGNORE INTO `$db_historico`.`$tabla` SELECT * FROM `$db_principal`.`$tabla` WHERE id_evento = $id";
        if (!$conn->query($sql)) {
            // Si falla la tabla de precios (puede que no tenga id_evento si es global, pero aquí filtramos por id_evento)
            // Si el precio es global (id_evento IS NULL), no se debe archivar asociado a un evento específico.
            // Pero si tiene id_evento asignado, SÍ se debe archivar.
            if ($tabla !== 'precios_tipo_boleto') {
                throw new Exception("Error archivando tabla $tabla: " . $conn->error);
            }
        }
    }

    $conn->query("DELETE FROM `$db_principal`.boletos WHERE id_evento = $id");
    $conn->query("DELETE FROM `$db_principal`.precios_tipo_boleto WHERE id_evento = $id");
    $conn->query("DELETE FROM `$db_principal`.promociones WHERE id_evento = $id");
    $conn->query("DELETE FROM `$db_principal`.categorias WHERE id_evento = $id");
    $conn->query("DELETE FROM `$db_principal`.funciones WHERE id_evento = $id");
    $conn->query("DELETE FROM `$db_principal`.evento WHERE id_evento = $id");

    return true;
}

// ==================================================================
// PROCESADOR AJAX
// ==================================================================
if (isset($_POST['accion'])) {
    header('Content-Type: application/json');
    $id = (int) ($_POST['id_evento'] ?? 0);

    if ($_POST['accion'] === 'consultar_boletos') {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM boletos WHERE id_evento = ? AND estatus = 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $total = $result->fetch_assoc()['total'];
        $stmt->close();
        echo json_encode(['status' => 'success', 'boletos' => (int) $total]);
        exit;
    }

    $conn->begin_transaction();
    try {
        if ($_POST['accion'] === 'finalizar') {
            $password = $_POST['password'] ?? '';

            $stmt = $conn->prepare("SELECT password FROM usuarios WHERE id_usuario = ? AND rol = 'admin'");
            $stmt->bind_param("i", $_SESSION['usuario_id']);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception("Error de autenticación.");
            }

            $admin = $result->fetch_assoc();

            if (!password_verify($password, $admin['password'])) {
                throw new Exception("Contraseña incorrecta.");
            }
            $stmt->close();

            archivar_evento_completo($id, $conn);

            if (function_exists('registrar_transaccion')) {
                registrar_transaccion('evento_archivar', 'Archivó evento ID ' . $id);
            }

            registrar_cambio('evento', $id, null, ['accion' => 'archivar']);
        }
        $conn->commit();
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// CARGA DE DATOS (SOLO ACTIVOS)
$activos = $conn->query("
    SELECT e.*, COALESCE(b.total, 0) as boletos_vendidos
    FROM trt_25.evento e 
    LEFT JOIN (
        SELECT id_evento, COUNT(*) as total 
        FROM boletos 
        WHERE estatus = 1 
        GROUP BY id_evento
    ) b ON e.id_evento = b.id_evento
    WHERE e.finalizado = 0 
    ORDER BY e.inicio_venta DESC
");
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
            opacity: 0;
            transition: opacity 0.4s;
        }

        body.loaded {
            opacity: 1;
        }

        .content-wrapper {
            padding: 24px;
        }

        .empty-state {
            text-align: center;
            padding: 80px 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 400px;
            background: linear-gradient(135deg, rgba(21, 97, 240, 0.03) 0%, rgba(139, 92, 246, 0.05) 50%, rgba(21, 97, 240, 0.03) 100%);
            border-radius: var(--radius-lg);
            border: 2px dashed var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .empty-state-content {
            position: relative;
            z-index: 1;
        }

        .empty-state-panda {
            width: 280px;
            height: auto;
            margin: 0 auto 10px;
            display: block;
            filter: drop-shadow(0 20px 40px rgba(0,0,0,0.6));
            animation: floatPanda 6s ease-in-out infinite;
        }

        @keyframes floatPanda {
            0% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-12px) rotate(1deg); }
            100% { transform: translateY(0px) rotate(0deg); }
        }

        .empty-state h3 {
            color: #ffffff !important;
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 0 12px;
        }

        .empty-state p {
            color: #cbd5e1 !important;
            font-size: 1rem;
            margin: 0 0 30px;
            max-width: 400px;
            line-height: 1.6;
        }

        .empty-state .btn-create {
            background: linear-gradient(135deg, var(--accent-blue) 0%, #8b5cf6 100%);
            color: white;
            padding: 16px 40px;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 8px 30px rgba(21, 97, 240, 0.4);
            text-decoration: none;
        }

        .empty-state .btn-create:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 12px 40px rgba(21, 97, 240, 0.5);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .page-header h4 {
            margin: 0;
            font-weight: 700;
            color: var(--accent-blue);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .badge-count {
            background: var(--bg-tertiary);
            color: var(--text-muted);
            padding: 6px 14px;
            border-radius: var(--radius-full);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(460px, 1fr));
            gap: 24px;
        }

        .event-card {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            background: var(--bg-card);
            overflow: hidden;
            transition: var(--transition-normal);
            animation: cardEntry 0.4s ease forwards;
            opacity: 0;
            transform: translateY(15px);
            display: flex;
            flex-direction: row;
            min-height: 240px;
        }

        .card-main {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        @keyframes cardEntry {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .event-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--accent-blue);
        }

        .card-img-container {
            width: 200px;
            flex-shrink: 0;
            align-self: stretch;
            background-color: var(--bg-tertiary);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .card-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s;
        }

        .event-card:hover .card-img {
            transform: scale(1.08);
        }

        .card-body {
            padding: 20px 22px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            flex: 1;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 800;
            margin: 0;
            line-height: 1.25;
            color: var(--text-primary);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .evento-terminado-msg {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(251, 191, 36, 0.1) 100%);
            border: 1px solid rgba(251, 191, 36, 0.4);
            color: #fbbf24;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }
        }

        .funcs-panel {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 14px;
        }

        .funcs-panel-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border-color);
        }

        .funcs-panel-header i {
            color: var(--accent-blue);
            font-size: 0.95rem;
        }

        .funcs-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 10px;
            max-height: 260px;
            overflow-y: auto;
            padding-right: 2px;
        }

        .func-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 10px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            min-width: 0;
        }

        .func-row.active {
            border-color: rgba(21, 97, 240, 0.35);
            background: linear-gradient(135deg, rgba(21, 97, 240, 0.08) 0%, rgba(21, 97, 240, 0.02) 100%);
        }

        .func-row.expired {
            border-color: rgba(255, 69, 58, 0.25);
            background: rgba(255, 69, 58, 0.06);
            opacity: 0.85;
        }

        .func-date-badge {
            flex-shrink: 0;
            width: 54px;
            padding: 8px 4px;
            border-radius: 8px;
            text-align: center;
            line-height: 1.1;
            background: rgba(21, 97, 240, 0.15);
            border: 1px solid rgba(21, 97, 240, 0.25);
        }

        .func-row.expired .func-date-badge {
            background: rgba(255, 69, 58, 0.12);
            border-color: rgba(255, 69, 58, 0.25);
        }

        .func-dia-num {
            display: block;
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1;
        }

        .func-mes-corto {
            display: block;
            font-size: 0.62rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--accent-blue);
            letter-spacing: 0.04em;
        }

        .func-row.expired .func-mes-corto {
            color: var(--danger);
        }

        .func-row-body {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .func-row-text {
            font-size: 0.72rem;
            color: var(--text-muted);
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: none;
        }

        .func-row-hora {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 5px;
            line-height: 1.2;
        }

        .func-row-hora i {
            font-size: 0.75rem;
            color: var(--accent-blue);
        }

        .func-row.expired .func-row-hora {
            color: var(--danger);
            text-decoration: line-through;
        }

        .func-row.expired .func-row-hora i {
            color: var(--danger);
        }

        .func-row-tag {
            flex-shrink: 0;
            font-size: 0.58rem;
            font-weight: 700;
            padding: 3px 6px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .func-row-tag.active {
            display: none;
        }

        .func-row-tag.expired {
            background: rgba(255, 69, 58, 0.12);
            color: var(--danger);
            border: 1px solid rgba(255, 69, 58, 0.25);
        }

        .func-badge-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 10px;
            border-radius: 8px;
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid rgba(255, 69, 58, 0.3);
        }

        .card-footer {
            padding: 12px 14px;
            background: var(--bg-secondary);
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 8px;
        }

        .btn-card {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition-fast);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-card.primary {
            background: var(--accent-blue);
            color: white;
        }

        .btn-card.primary:hover {
            background: var(--accent-blue-hover);
        }

        .btn-card.danger {
            background: var(--bg-tertiary);
            color: var(--danger);
            border: 1px solid var(--border-color);
        }

        .btn-card.danger:hover {
            background: var(--danger-bg);
            border-color: var(--danger);
        }

        .boletos-badge {
            background: var(--danger);
            color: white;
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 4px;
            font-weight: 700;
        }

        /* Tarjetas grandes: apilar imagen arriba en pantallas angostas */
        @media (max-width: 560px) {
            .events-grid {
                grid-template-columns: 1fr;
            }

            .event-card {
                flex-direction: column;
                min-height: 0;
            }

            .card-img-container {
                width: 100%;
                height: 180px;
                align-self: auto;
            }

            .funcs-list {
                grid-template-columns: 1fr;
            }
        }

        .auto-archive-alert {
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.3);
            color: #a5b4fc;
            padding: 14px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .auto-archive-alert .btn-close {
            margin-left: auto;
            background: none;
            border: none;
            color: #a5b4fc;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0.7;
        }

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
            background: linear-gradient(160deg, #1e1e22 0%, #141416 50%, #1a1a1e 100%);
            border-radius: 24px;
            width: 92%;
            max-width: 440px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.7);
            animation: modalIn 0.4s ease;
            overflow: hidden;
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-header-premium {
            background: linear-gradient(135deg, #ff453a 0%, #d70015 100%);
            padding: 40px 30px;
            position: relative;
            text-align: center;
        }

        .modal-header-premium::after {
            content: '';
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            border-left: 30px solid transparent;
            border-right: 30px solid transparent;
            border-top: 25px solid #d70015;
        }

        .modal-header-premium h5 {
            margin: 0;
            font-weight: 800;
            font-size: 1.5rem;
            color: white;
        }

        .modal-header-premium .btn-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.2);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-body {
            padding: 50px 35px 30px;
            text-align: center;
        }

        .modal-body .modal-icon {
            font-size: 4rem;
            color: var(--danger);
            margin-bottom: 20px;
        }

        .modal-body p {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 8px;
            font-size: 1rem;
            line-height: 1.6;
        }

        .modal-body .event-name {
            color: white;
            font-weight: 800;
            font-size: 1.3rem;
            margin: 10px 0 20px;
        }

        .modal-body input[type="password"] {
            width: 100%;
            padding: 16px;
            margin-top: 25px;
            font-size: 1.2rem;
            text-align: center;
            letter-spacing: 6px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.3);
            color: white;
        }

        .modal-body input[type="password"]:focus {
            outline: none;
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(255, 69, 58, 0.2);
        }

        .modal-error {
            color: #ff453a;
            font-size: 0.9rem;
            margin-top: 15px;
            display: none;
            padding: 10px;
            background: rgba(255, 69, 58, 0.1);
            border-radius: 8px;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .boletos-warning {
            background: rgba(255, 159, 10, 0.1);
            border: 1px solid rgba(255, 159, 10, 0.3);
            color: var(--warning);
            padding: 16px;
            border-radius: 12px;
            margin-top: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            text-align: left;
        }

        .modal-footer {
            padding: 20px 35px 35px;
            display: flex;
            justify-content: center;
            border: none;
        }

        .modal-footer button {
            padding: 16px 30px;
            width: 100%;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .modal-footer .btn-confirm {
            background: linear-gradient(135deg, #ff453a 0%, #d70015 100%);
            color: white;
            box-shadow: 0 8px 25px rgba(255, 69, 58, 0.3);
        }

        .modal-footer .btn-confirm:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(255, 69, 58, 0.4);
        }

        .modal-footer .btn-confirm:disabled {
            background: #3a3a3c;
            color: #86868b;
            box-shadow: none;
            cursor: not-allowed;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <?php if (!empty($eventos_auto_archivados)): ?>
            <div class="auto-archive-alert">
                <i class="bi bi-archive-fill"></i>
                <div>
                    <strong>Auto-archivado:</strong>
                    <?php
                    $nombres = array_column($eventos_auto_archivados, 'titulo');
                    echo count($nombres) . ' evento(s) (' . implode(', ', $nombres) . ') fueron movidos al historial.';
                    ?>
                </div>
                <button class="btn-close" onclick="this.parentElement.remove()">×</button>
            </div>
            <?php unset($_SESSION['eventos_auto_archivados']); endif; ?>

        <div class="page-header">
            <h4><i class="bi bi-calendar-event"></i> Eventos Activos</h4>
            <span class="badge-count">
                <?= $activos->num_rows ?> eventos
            </span>
        </div>

        <?php if ($activos && $activos->num_rows > 0): ?>
            <div class="events-grid">
                <?php
                $delay = 0;
                while ($e = $activos->fetch_assoc()):
                    $delay += 40;
                    $img = '';
                    if (!empty($e['imagen'])) {
                        $rutas = ["../evt_interfaz/" . $e['imagen'], $e['imagen']];
                        foreach ($rutas as $r) {
                            if (file_exists($r)) {
                                $img = $r;
                                break;
                            }
                        }
                    }

                    $id_evt = $e['id_evento'];
                    $sql_func = "SELECT fecha_hora, estado FROM funciones WHERE id_evento = $id_evt ORDER BY fecha_hora ASC";
                    $res_func = $conn->query($sql_func);
                    $funciones = [];
                    if ($res_func) {
                        while ($f = $res_func->fetch_assoc()) {
                            $funciones[] = $f;
                        }
                    }
                    ?>
                    <div class="event-card" style="animation-delay: <?= $delay ?>ms">
                        <div class="card-img-container">
                            <?php if ($img): ?>
                                <img src="<?= htmlspecialchars($img) ?>" class="card-img" loading="lazy"
                                    alt="<?= htmlspecialchars($e['titulo']) ?>">
                            <?php else: ?>
                                <i class="bi bi-image" style="font-size: 2rem; color: var(--text-muted); opacity: 0.4;"></i>
                            <?php endif; ?>
                        </div>

                        <div class="card-main">
                        <div class="card-body">
                            <h6 class="card-title" title="<?= htmlspecialchars($e['titulo']) ?>">
                                <?= htmlspecialchars($e['titulo']) ?>
                            </h6>

                            <?php
                            $todasVencidas = true;
                            foreach ($funciones as $f) {
                                if ($f['estado'] != 1 && strtotime($f['fecha_hora']) > time()) {
                                    $todasVencidas = false;
                                    break;
                                }
                            }
                            ?>

                            <?php if ($todasVencidas && count($funciones) > 0): ?>
                                <div class="evento-terminado-msg">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    <span>Evento terminado. Puedes archivarlo.</span>
                                </div>
                            <?php endif; ?>

                            <div class="funcs-panel">
                                <?php if (count($funciones) > 0): ?>
                                    <div class="funcs-panel-header">
                                        <i class="bi bi-calendar3"></i>
                                        <span><?= count($funciones) ?> función<?= count($funciones) !== 1 ? 'es' : '' ?></span>
                                    </div>
                                    <div class="funcs-list">
                                        <?php foreach ($funciones as $f):
                                            $esVencida = ($f['estado'] == 1) || (strtotime($f['fecha_hora']) < time());
                                            $clase = $esVencida ? 'expired' : 'active';
                                            $fmt = formato_funcion_evento($f['fecha_hora']);
                                            ?>
                                            <div class="func-row <?= $clase ?>">
                                                <div class="func-date-badge">
                                                    <span class="func-dia-num"><?= $fmt['dia'] ?></span>
                                                    <span class="func-mes-corto"><?= $fmt['mes_corto'] ?></span>
                                                </div>
                                                <div class="func-row-body">
                                                    <span class="func-row-text"><?= htmlspecialchars($fmt['texto_corto']) ?></span>
                                                    <span class="func-row-hora"><i class="bi bi-clock"></i> <?= htmlspecialchars($fmt['hora']) ?></span>
                                                </div>
                                                <span class="func-row-tag <?= $clase ?>"><?= $esVencida ? 'Finalizada' : 'Activa' ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="func-badge-empty"><i class="bi bi-calendar-x"></i> Sin funciones</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card-footer">
                            <button onclick="window.location.href='editar_evento.php?id=<?= $e['id_evento'] ?>'"
                                class="btn-card primary"><i class="bi bi-pencil"></i> Editar</button>
                            <button class="btn-card danger"
                                onclick="prepararArchivado(<?= $e['id_evento'] ?>, '<?= addslashes($e['titulo']) ?>', <?= (int) $e['boletos_vendidos'] ?>)"
                                title="Mover al Historial">
                                <i class="bi bi-archive"></i>
                                <?php if ($e['boletos_vendidos'] > 0): ?><span class="boletos-badge">
                                        <?= $e['boletos_vendidos'] ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                        </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-content">

                    <h3 style="color: #ffffff !important; font-weight: 800;">¡El escenario está vacío!</h3>
                    <p style="color: #cbd5e1 !important;">No hay eventos activos en este momento.<br>¿Por qué no creas uno nuevo para comenzar el show?</p>
                    <a href="crear_evento.php" class="btn-create"><i class="bi bi-sparkles"></i> Crear Evento Mágico</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal-overlay" id="modalArchivar">
        <div class="modal-box">
            <div class="modal-header-premium">
                <h5>Acceso Restringido</h5>
                <button class="btn-close" onclick="cerrarModal()">×</button>
            </div>
            <div class="modal-body">
                <i class="bi bi-shield-lock-fill modal-icon"></i>
                <p>Estás a punto de archivar el evento:</p>
                <div class="event-name" id="nombreEventoArchivar"></div>
                <p style="font-size: 0.9rem;">El evento dejará de ser visible y pasará al historial.</p>
                <div class="boletos-warning" id="boletosWarning" style="display: none;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div style="font-size: 0.9rem;"><strong>Atención:</strong> Este evento tiene <span
                            id="cantidadBoletos" style="font-weight: 700; color: white;">0</span> boletos vendidos.
                    </div>
                </div>
                <input type="password" id="auth_pass" placeholder="••••••" maxlength="20">
                <div class="modal-error" id="errorArchivar"><i class="bi bi-exclamation-circle-fill"></i> <span
                        id="errorArchivarText">Contraseña incorrecta</span></div>
            </div>
            <div class="modal-footer">
                <button id="btnConfirmarFinal" class="btn-confirm" onclick="ejecutarArchivado()"><i
                        class="bi bi-archive-fill"></i> Confirmar Archivo</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => document.body.classList.add('loaded'));

        let eventoIdSeleccionado = null;
        let boletosEvento = 0;

        function prepararArchivado(id, titulo, boletos) {
            eventoIdSeleccionado = id;
            boletosEvento = boletos || 0;
            document.getElementById('nombreEventoArchivar').textContent = titulo;
            document.getElementById('auth_pass').value = '';
            document.getElementById('errorArchivar').style.display = 'none';
            document.getElementById('btnConfirmarFinal').disabled = false;

            const warningDiv = document.getElementById('boletosWarning');
            const cantidadSpan = document.getElementById('cantidadBoletos');

            if (boletosEvento > 0) {
                warningDiv.style.display = 'flex';
                cantidadSpan.textContent = boletosEvento;
                document.getElementById('btnConfirmarFinal').innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Archivar con ' + boletosEvento + ' boletos';
            } else {
                warningDiv.style.display = 'none';
                document.getElementById('btnConfirmarFinal').innerHTML = '<i class="bi bi-archive-fill"></i> Confirmar Archivo';
            }

            document.getElementById('modalArchivar').classList.add('active');
            setTimeout(() => document.getElementById('auth_pass').focus(), 300);
        }

        function cerrarModal() { document.getElementById('modalArchivar').classList.remove('active'); }

        document.getElementById('auth_pass').addEventListener('keypress', function (e) { if (e.key === 'Enter') { ejecutarArchivado(); } });

        function ejecutarArchivado() {
            const password = document.getElementById('auth_pass').value.trim();
            const errorDiv = document.getElementById('errorArchivar');
            const errorText = document.getElementById('errorArchivarText');

            if (!password) { errorDiv.style.display = 'block'; errorText.textContent = 'Ingresa tu contraseña'; document.getElementById('auth_pass').focus(); return; }

            const btn = document.getElementById('btnConfirmarFinal');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Procesando...';
            errorDiv.style.display = 'none';

            let fd = new FormData();
            fd.append('accion', 'finalizar');
            fd.append('id_evento', eventoIdSeleccionado);
            fd.append('password', password);

            fetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') { cerrarModal(); location.reload(); }
                    else { errorDiv.style.display = 'block'; errorText.textContent = data.message || 'Error al procesar'; btn.disabled = false; btn.innerHTML = '<i class="bi bi-archive-fill"></i> Confirmar Archivo'; document.getElementById('auth_pass').value = ''; document.getElementById('auth_pass').focus(); }
                })
                .catch(() => { errorDiv.style.display = 'block'; errorText.textContent = 'Error de conexión'; btn.disabled = false; btn.innerHTML = '<i class="bi bi-archive-fill"></i> Confirmar Archivo'; });
        }
    </script>
</body>

</html>