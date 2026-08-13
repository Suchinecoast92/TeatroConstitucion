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

// Verificar permisos
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] !== 'admin' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']))) {
    die('<div style="display:flex;height:100vh;align-items:center;justify-content:center;font-family:sans-serif;color:#ef4444;"><h1>Acceso Denegado</h1></div>');
}

$errores_php = [];

// Precios globales por defecto (para el mini-menú de creación)
$precios_globales_js = [
    'general' => 80,
    'nino' => 50,
    'adulto_mayor' => 60,
    'discapacitado' => 40,
];
$usa_diferenciados_global = false;
$evt_destino_lista = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/act_evento.php';
$chk_tabla_precios = $conn->query("SHOW TABLES LIKE 'precios_tipo_boleto'");
if ($chk_tabla_precios && $chk_tabla_precios->num_rows > 0) {
    $res_pg = $conn->query("SELECT tipo_boleto, precio, usa_diferenciados FROM precios_tipo_boleto WHERE id_evento IS NULL");
    if ($res_pg) {
        while ($row_pg = $res_pg->fetch_assoc()) {
            $t = $row_pg['tipo_boleto'];
            $precio = (float) $row_pg['precio'];
            if ($t === 'general' || $t === 'adulto') {
                $precios_globales_js['general'] = $precio;
            }
            if (isset($precios_globales_js[$t])) {
                $precios_globales_js[$t] = $precio;
            }
            if ((int) ($row_pg['usa_diferenciados'] ?? 0) === 1) {
                $usa_diferenciados_global = true;
            }
        }
    }
}

function asegurar_tabla_precios_tipo(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS precios_tipo_boleto (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_evento INT NULL,
            tipo_boleto VARCHAR(50) NOT NULL,
            precio DECIMAL(10,2) NOT NULL DEFAULT 0,
            activo TINYINT(1) DEFAULT 1,
            usa_diferenciados TINYINT(1) DEFAULT 0,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_evento_tipo (id_evento, tipo_boleto)
        )
    ");
    $check_col = $conn->query("SHOW COLUMNS FROM precios_tipo_boleto LIKE 'usa_diferenciados'");
    if ($check_col && $check_col->num_rows == 0) {
        $conn->query("ALTER TABLE precios_tipo_boleto ADD COLUMN usa_diferenciados TINYINT(1) DEFAULT 0");
    }
}

function obtener_precios_desde_post(bool $es_gratuito, array $post): array
{
    if ($es_gratuito) {
        return [
            'general' => 0,
            'nino' => 0,
            'adulto_mayor' => 0,
            'discapacitado' => 0,
            'cortesia' => 0,
            'usa_diferenciados' => 0,
        ];
    }

    $general = max(0, (float) ($post['precio_general'] ?? 80));
    $usa_diff = isset($post['usa_diferenciados']) && $post['usa_diferenciados'] === '1';

    if ($usa_diff) {
        return [
            'general' => $general,
            'nino' => max(0, (float) ($post['precio_nino'] ?? 50)),
            'adulto_mayor' => max(0, (float) ($post['precio_adulto_mayor'] ?? 60)),
            'discapacitado' => max(0, (float) ($post['precio_discapacitado'] ?? 40)),
            'cortesia' => 0,
            'usa_diferenciados' => 1,
        ];
    }

    return [
        'general' => $general,
        'nino' => $general,
        'adulto_mayor' => $general,
        'discapacitado' => $general,
        'cortesia' => 0,
        'usa_diferenciados' => 0,
    ];
}

function guardar_precios_tipo_evento(mysqli $conn, int $id_evento, array $precios): void
{
    $usa = (int) ($precios['usa_diferenciados'] ?? 0);

    foreach (['general', 'nino', 'adulto_mayor', 'discapacitado', 'cortesia'] as $tipo) {
        $p = (float) ($precios[$tipo] ?? 0);
        $stmt = $conn->prepare("
            INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)
        ");
        $stmt->bind_param('isdi', $id_evento, $tipo, $p, $usa);
        $stmt->execute();
        $stmt->close();
    }

    $general = (float) $precios['general'];
    $stmt = $conn->prepare("
        INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados)
        VALUES (?, 'adulto', ?, ?)
        ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)
    ");
    $stmt->bind_param('idi', $id_evento, $general, $usa);
    $stmt->execute();
    $stmt->close();

    $mapa_cats = [
        'general' => 'General',
        'discapacitado' => 'Discapacitado',
    ];
    foreach ($mapa_cats as $key => $nombre) {
        $p = (float) ($precios[$key] ?? 0);
        $stmt = $conn->prepare("UPDATE categorias SET precio = ? WHERE id_evento = ? AND nombre_categoria = ?");
        $stmt->bind_param('dis', $p, $id_evento, $nombre);
        $stmt->execute();
        $stmt->close();
    }
}

function quiere_respuesta_json_crear(): bool
{
    return isset($_POST['respuesta_json']) && $_POST['respuesta_json'] === '1';
}

function responder_json_crear_evento(array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================================================================
// 2. PROCESAR FORMULARIO (POST)
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    asegurar_tabla_precios_tipo($conn);

    $titulo = htmlspecialchars(trim($_POST['titulo'] ?? ''), ENT_QUOTES, 'UTF-8');
    $desc = htmlspecialchars(trim($_POST['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8');
    $tipo = (int) ($_POST['tipo'] ?? 0);
    $ini = trim((string) ($_POST['inicio_venta'] ?? ''));
    $fin = trim((string) ($_POST['cierre_venta'] ?? ''));

    if (empty($titulo))
        $errores_php[] = "Falta el título.";
    if (strlen($titulo) > 200)
        $errores_php[] = "El título es demasiado largo (máximo 200 caracteres).";
    if (strlen($desc) > 5000)
        $errores_php[] = "La descripción es demasiado larga (máximo 5000 caracteres).";
    if (empty($_POST['funciones']))
        $errores_php[] = "Debe agregar al menos una función.";
    if (empty($ini))
        $errores_php[] = "Falta inicio de venta.";
    if ($tipo < 1 || $tipo > 2)
        $errores_php[] = "Tipo de escenario inválido.";

    if (!empty($ini) && strtotime($ini) < time()) {
        $errores_php[] = "La fecha/hora de inicio de venta no puede ser anterior a ahora.";
    }

    if (!empty($_POST['funciones'])) {
        foreach ($_POST['funciones'] as $func) {
            if (strtotime($func) <= time()) {
                $errores_php[] = "Todas las funciones deben ser en el futuro.";
                break;
            }
        }
        
        // FORZAMOS (y validamos) que el cierre de la venta sea SIEMPRE y exactamente 24 horas después de la última función programada.
        // Ignoramos lo que venga del lado del cliente.
        $ultimaFuncion = max(array_map('strtotime', $_POST['funciones']));
        $fin = date('Y-m-d H:i:s', $ultimaFuncion + SEGUNDOS_CIERRE_VENTAS_POST_FUNCION);
    }

    if (!empty($ini)) {
        $ini = date('Y-m-d H:i:s', strtotime($ini));
    }
    if (!empty($fin)) {
        $fin = date('Y-m-d H:i:s', strtotime($fin));
    }

    $imagen_ruta = "";
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            if (!is_dir("imagenes"))
                mkdir("imagenes", 0755, true);
            $ruta = "imagenes/evt_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta)) {
                $imagen_ruta = $ruta;
            } else
                $errores_php[] = "Error al guardar imagen.";
        } else
            $errores_php[] = "Formato de imagen no válido.";
    } else {
        $errImg = isset($_FILES['imagen']['error']) ? (int) $_FILES['imagen']['error'] : UPLOAD_ERR_NO_FILE;
        if ($errImg === UPLOAD_ERR_NO_FILE) {
            $errores_php[] = "La imagen es obligatoria.";
        } elseif ($errImg === UPLOAD_ERR_INI_SIZE || $errImg === UPLOAD_ERR_FORM_SIZE) {
            $errores_php[] = "La imagen es demasiado grande. Reduce el tamaño e intenta de nuevo.";
        } else {
            $errores_php[] = "Error al subir la imagen (código $errImg). Vuelve a seleccionarla.";
        }
    }

    $es_gratuito = isset($_POST['es_gratuito']) && $_POST['es_gratuito'] === '1';
    $precios_evento = obtener_precios_desde_post($es_gratuito, $_POST);

    if (!$es_gratuito) {
        $raw_precio_general = trim((string) ($_POST['precio_general'] ?? ''));
        if ($raw_precio_general === '' || $precios_evento['general'] <= 0) {
            $errores_php[] = "Debes definir un precio general mayor a cero para eventos de pago.";
        }
    }

    if (empty($errores_php)) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO evento (titulo, descripcion, imagen, tipo, inicio_venta, cierre_venta, finalizado) VALUES (?, ?, ?, ?, ?, ?, 0)");
            $stmt->bind_param("sssiss", $titulo, $desc, $imagen_ruta, $tipo, $ini, $fin);
            $stmt->execute();
            $id_nuevo = $conn->insert_id;
            $stmt->close();

            $stmt_f = $conn->prepare("INSERT INTO funciones (id_evento, fecha_hora, estado) VALUES (?, ?, 0)");
            foreach ($_POST['funciones'] as $fh) {
                $stmt_f->bind_param("is", $id_nuevo, $fh);
                $stmt_f->execute();
            }
            $stmt_f->close();

            $stmt_c = $conn->prepare("INSERT INTO categorias (id_evento, nombre_categoria, precio, color) VALUES (?, ?, ?, ?)");
            $nom = 'General';
            $prec = $precios_evento['general'];
            $col = '#cbd5e1';
            $stmt_c->bind_param("isds", $id_nuevo, $nom, $prec, $col);
            $stmt_c->execute();
            $id_cat_gen = $conn->insert_id;

            $nom = 'Discapacitado';
            $prec = $precios_evento['discapacitado'];
            $col = '#2563eb';
            $stmt_c->bind_param("isds", $id_nuevo, $nom, $prec, $col);
            $stmt_c->execute();

            $nom = 'No Venta';
            $prec = 0;
            $col = '#0f172a';
            $stmt_c->bind_param("isds", $id_nuevo, $nom, $prec, $col);
            $stmt_c->execute();
            $stmt_c->close();

            $mapa = [];
            if ($tipo == 2) {
                for ($f = 1; $f <= 10; $f++) {
                    for ($a = 1; $a <= 12; $a++)
                        $mapa["PB$f-$a"] = $id_cat_gen;
                }
            }
            foreach (range('A', 'O') as $l) {
                for ($a = 1; $a <= 26; $a++)
                    $mapa["$l$a"] = $id_cat_gen;
            }
            for ($a = 1; $a <= 30; $a++)
                $mapa["P$a"] = $id_cat_gen;

            $json = json_encode($mapa);
            $conn->query("UPDATE evento SET mapa_json = '$json' WHERE id_evento = $id_nuevo");

            guardar_precios_tipo_evento($conn, $id_nuevo, $precios_evento);

            if (!$conn->commit()) {
                throw new Exception('No se pudo confirmar el guardado en la base de datos.');
            }

            $stmt_ver = $conn->prepare('SELECT id_evento, titulo FROM evento WHERE id_evento = ?');
            $stmt_ver->bind_param('i', $id_nuevo);
            $stmt_ver->execute();
            $row_ver = $stmt_ver->get_result()->fetch_assoc();
            $stmt_ver->close();
            if (!$row_ver) {
                throw new Exception('El evento no apareció en la base de datos tras guardar.');
            }

            if (function_exists('registrar_transaccion')) {
                registrar_transaccion('evento_crear', "Creó evento: $titulo" . ($es_gratuito ? ' (GRATUITO)' : ''));
            }

            $evt_base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $destino_url = $evt_base . '/act_evento.php';
            $mensaje_tipo = $es_gratuito
                ? 'Evento gratuito creado. Los tickets mostrarán EVENTO GRATUITO.'
                : 'Evento de pago creado con precio $' . number_format($precios_evento['general'], 2) . ' (general).';
            $icono_tipo = $es_gratuito ? 'bi-gift-fill' : 'bi-cash-coin';
            $color_gradiente = $es_gratuito ? 'linear-gradient(135deg, #10b981 0%, #059669 100%)' : 'linear-gradient(135deg, var(--accent-blue) 0%, #4f46e5 100%)';

            if (quiere_respuesta_json_crear()) {
                responder_json_crear_evento([
                    'ok' => true,
                    'id_evento' => $id_nuevo,
                    'titulo' => $titulo,
                    'redirect' => $destino_url,
                    'mensaje' => $mensaje_tipo,
                    'es_gratuito' => $es_gratuito,
                ]);
            }

            ?>
            <!DOCTYPE html>
            <html lang="es">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
                <link rel="stylesheet" href="../assets/css/teatro-style.css">
                <style>
                    body { display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; overflow: hidden; }
                    .success-card { background: var(--bg-secondary); border-radius: 24px; padding: 40px; text-align: center; box-shadow: var(--shadow-xl); max-width: 420px; width: 92%; border: 1px solid var(--border-color); opacity: 0; transform: scale(0.9); animation: popIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
                    .icon-circle { width: 90px; height: 90px; background: <?= $es_gratuito ? 'rgba(16,185,129,0.15)' : 'rgba(21,97,240,0.15)' ?>; color: <?= $es_gratuito ? '#10b981' : 'var(--accent-blue)' ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 44px; margin: 0 auto 20px; box-shadow: 0 0 0 8px <?= $es_gratuito ? 'rgba(16,185,129,0.08)' : 'rgba(21,97,240,0.08)' ?>; }
                    .progress-track { height: 6px; background: var(--bg-tertiary); border-radius: 3px; margin-top: 30px; overflow: hidden; }
                    .progress-fill { height: 100%; background: <?= $es_gratuito ? '#10b981' : 'var(--accent-blue)' ?>; width: 0; border-radius: 3px; transition: width 1.5s cubic-bezier(0.4, 0, 0.2, 1); }
                    @keyframes popIn { to { opacity: 1; transform: scale(1); } }
                    body.exiting { animation: fadeOut 0.4s ease-in forwards; }
                    @keyframes fadeOut { to { opacity: 0; transform: translateY(10px); } }
                    h4 { color: var(--text-primary); } p { color: var(--text-muted); }
                    .tipo-badge { display: inline-block; padding: 6px 18px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; margin-top: 12px; background: <?= $es_gratuito ? 'rgba(16,185,129,0.15)' : 'rgba(21,97,240,0.15)' ?>; color: <?= $es_gratuito ? '#10b981' : 'var(--accent-blue)' ?>; }
                </style>
            </head>

            <body>
                <div class="success-card">
                    <div class="icon-circle"><i class="bi <?= $icono_tipo ?>"></i></div>
                    <h4 style="font-weight: 800; margin-bottom: 8px;">¡Evento Creado!</h4>
                    <p style="font-size: 0.95rem; margin-bottom: 0;"><?= htmlspecialchars($mensaje_tipo) ?></p>
                    <div class="tipo-badge"><?= $es_gratuito ? '🎁 EVENTO GRATUITO' : '💰 EVENTO DE PAGO' ?></div>
                    <div class="progress-track"><div class="progress-fill" id="pBar"></div></div>
                    <p style="font-size: 0.75rem; font-weight: 600; margin-top: 12px;">VOLVIENDO A EVENTOS ACTIVOS...</p>
                </div>
                <script>
                    setTimeout(() => document.getElementById('pBar').style.width = '100%', 100);
                    localStorage.setItem("evt_upd", Date.now());
                    setTimeout(() => { document.body.classList.add('exiting'); setTimeout(() => { window.location.href = <?= json_encode($destino_url) ?>; }, 350); }, 1800);
                </script>
            </body>

            </html>
            <?php exit;

        } catch (Exception $e) {
            $conn->rollback();
            $errores_php[] = "Error DB: " . $e->getMessage();
        }
    }

    if (quiere_respuesta_json_crear()) {
        responder_json_crear_evento([
            'ok' => false,
            'errores' => $errores_php,
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Evento</title>
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

        /* Pantalla de progreso al crear evento (misma UX que éxito PHP) */
        .evt-progreso-overlay {
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(0, 0, 0, 0.88);
            backdrop-filter: blur(12px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .evt-progreso-overlay.exiting {
            animation: evtFadeOut 0.4s ease-in forwards;
        }

        @keyframes evtFadeOut {
            to { opacity: 0; transform: scale(0.98); }
        }

        .evt-success-card {
            background: var(--bg-secondary);
            border-radius: 24px;
            padding: 36px 32px;
            text-align: center;
            box-shadow: var(--shadow-xl);
            max-width: 420px;
            width: 100%;
            border: 1px solid var(--border-color);
            animation: evtPopIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes evtPopIn {
            from { opacity: 0; transform: scale(0.92); }
            to { opacity: 1; transform: scale(1); }
        }

        .evt-icon-circle {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            margin: 0 auto 18px;
        }

        .evt-icon-circle.modo-creando {
            background: rgba(21, 97, 240, 0.15);
            color: var(--accent-blue);
            box-shadow: 0 0 0 8px rgba(21, 97, 240, 0.08);
        }

        .evt-icon-circle.modo-exito.pago {
            background: rgba(21, 97, 240, 0.15);
            color: var(--accent-blue);
            box-shadow: 0 0 0 8px rgba(21, 97, 240, 0.08);
        }

        .evt-icon-circle.modo-exito.gratis {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            box-shadow: 0 0 0 8px rgba(16, 185, 129, 0.08);
        }

        .evt-progress-track {
            height: 6px;
            background: var(--bg-tertiary);
            border-radius: 3px;
            margin-top: 24px;
            overflow: hidden;
        }

        .evt-progress-fill {
            height: 100%;
            width: 0;
            border-radius: 3px;
            background: var(--accent-blue);
            transition: width 1.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .evt-progress-fill.gratis {
            background: #10b981;
        }

        .evt-tipo-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            margin-top: 10px;
        }

        .evt-tipo-badge.pago {
            background: rgba(21, 97, 240, 0.15);
            color: var(--accent-blue);
        }

        .evt-tipo-badge.gratis {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
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
            transition: var(--transition-fast);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(21, 97, 240, 0.2);
            background: var(--bg-tertiary);
            color: var(--text-primary);
            outline: none;
        }

        .form-control::placeholder {
            color: var(--text-muted);
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
            transition: var(--transition-fast);
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

        .btn-secondary:hover {
            background: var(--bg-hover);
        }

        .btn-success {
            background: var(--success);
            color: #000;
        }

        .btn-success:hover:not(:disabled) {
            filter: brightness(1.1);
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
            padding: 12px;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 15px;
        }

        .funcion-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 12px;
            background: var(--bg-secondary);
            border: 1px solid rgba(21, 97, 240, 0.35);
            background: linear-gradient(135deg, rgba(21, 97, 240, 0.08) 0%, rgba(21, 97, 240, 0.02) 100%);
            box-shadow: var(--shadow-sm);
        }

        .funcion-item-date {
            flex-shrink: 0;
            width: 52px;
            padding: 8px 6px;
            border-radius: 10px;
            text-align: center;
            background: rgba(21, 97, 240, 0.15);
            border: 1px solid rgba(21, 97, 240, 0.25);
            line-height: 1.1;
        }

        .funcion-item-dia {
            display: block;
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-primary);
        }

        .funcion-item-mes {
            display: block;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--accent-blue);
            letter-spacing: 0.05em;
        }

        .funcion-item-body {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .funcion-item-texto {
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.3;
        }

        .funcion-item-hora {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .funcion-item-hora i {
            color: var(--accent-blue);
            font-size: 0.85rem;
        }

        .funcion-item-del {
            flex-shrink: 0;
            width: 28px;
            height: 28px;
            border: none;
            border-radius: 8px;
            background: rgba(255, 69, 58, 0.12);
            color: var(--danger);
            font-size: 1.1rem;
            line-height: 1;
            cursor: pointer;
            transition: var(--transition-fast);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .funcion-item-del:hover {
            background: var(--danger);
            color: #fff;
            transform: scale(1.05);
        }

        .conf-funciones-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .conf-funcion-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 12px;
            background: rgba(21, 97, 240, 0.08);
            border: 1px solid rgba(21, 97, 240, 0.25);
        }

        .conf-funcion-date {
            flex-shrink: 0;
            width: 48px;
            text-align: center;
            padding: 6px 4px;
            border-radius: 8px;
            background: rgba(21, 97, 240, 0.15);
            border: 1px solid rgba(21, 97, 240, 0.25);
            line-height: 1.1;
        }

        .conf-funcion-date span {
            display: block;
            font-size: 1.1rem;
            font-weight: 800;
            color: #fff;
        }

        .conf-funcion-date small {
            display: block;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--accent-blue);
            letter-spacing: 0.05em;
        }

        .conf-funcion-info {
            flex: 1;
            min-width: 0;
        }

        .conf-funcion-info strong {
            display: block;
            font-size: 0.82rem;
            color: rgba(255, 255, 255, 0.75);
            margin-bottom: 2px;
        }

        .conf-funcion-info span {
            font-size: 0.95rem;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .conf-funcion-info span i {
            color: var(--accent-blue);
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
            transition: var(--transition-fast);
        }

        .cierre-lock-btn:hover {
            background: var(--warning-bg);
            color: var(--warning);
            border-color: var(--warning);
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
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.05), 0 25px 80px rgba(0, 0, 0, 0.7);
            animation: modalPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
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
            animation: iconPulse 2s ease-in-out infinite;
        }

        @keyframes iconPulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
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

        /* Mini menú de precios (flujo creación) — lista */
        .mini-tipos-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
        }

        .mini-tipos-list.collapsed { display: none; }

        .precio-list-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
        }

        .precio-list-item.readonly {
            opacity: 0.75;
        }

        .precio-list-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .precio-list-info {
            flex: 1;
            min-width: 0;
        }

        .precio-list-nombre {
            font-weight: 700;
            font-size: 0.9rem;
            color: #fff;
            line-height: 1.2;
        }

        .precio-list-desc {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.45);
            margin-top: 2px;
        }

        .precio-list-input {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
        }

        .precio-list-input .simbolo {
            color: var(--accent-blue);
            font-weight: 800;
            font-size: 1rem;
        }

        .precio-list-input input {
            width: 88px;
            text-align: center;
            font-weight: 700;
            padding: 8px 6px;
            border-radius: 10px;
        }

        .mini-precio-general {
            background: linear-gradient(135deg, rgba(21, 97, 240, 0.15), rgba(79, 70, 229, 0.1));
            border: 2px solid rgba(21, 97, 240, 0.35);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            margin-bottom: 16px;
        }

        .mini-switch-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 14px;
        }

        /* Modal precios: adaptado a pantalla / iframe con scroll interno */
        #modalPreciosRapidos.modal-overlay {
            align-items: flex-start;
            justify-content: center;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 10px;
            -webkit-overflow-scrolling: touch;
            height: 100%;
            min-height: 100%;
            box-sizing: border-box;
        }

        #modalPreciosRapidos.modal-overlay.active {
            display: flex;
        }

        .modal-precios-panel {
            background: linear-gradient(160deg, #1e1e22 0%, #141416 50%, #1a1a1e 100%);
            border-radius: 20px;
            width: 100%;
            max-width: 480px;
            max-height: calc(100dvh - 20px);
            max-height: calc(100vh - 20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.7);
            animation: modalPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: flex;
            flex-direction: column;
            margin: 10px auto;
            overflow: hidden;
        }

        .modal-precios-header {
            flex-shrink: 0;
            background: linear-gradient(135deg, #1561f0 0%, #4f46e5 100%);
            padding: 18px 20px;
            text-align: center;
        }

        .modal-precios-header .icono-header { font-size: 2rem; line-height: 1; margin-bottom: 6px; }

        .modal-precios-header h3 {
            color: white;
            font-weight: 800;
            font-size: 1.2rem;
            margin: 0;
        }

        .modal-precios-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.8rem;
            margin: 6px 0 0;
            line-height: 1.35;
        }

        .modal-precios-scroll {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 16px 18px;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
        }

        .modal-precios-footer {
            flex-shrink: 0;
            display: flex;
            gap: 10px;
            padding: 14px 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(0, 0, 0, 0.25);
        }

        .modal-precios-footer .btn {
            flex: 1;
            padding: 14px 12px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.95rem;
        }

        .btn-precios-atras {
            background: rgba(255, 255, 255, 0.06);
            color: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .btn-precios-crear {
            background: linear-gradient(135deg, var(--accent-blue), #4f46e5);
            color: white;
            border: none;
        }

        .precios-toolbar {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 14px;
        }

        .badge-precios-globales {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 12px;
            background: rgba(21, 97, 240, 0.12);
            color: var(--accent-blue);
            font-size: 0.72rem;
            font-weight: 700;
            line-height: 1.3;
            text-align: center;
        }

        .btn-restaurar-precios {
            width: 100%;
            font-weight: 700;
            border-radius: 10px;
            padding: 10px 14px;
            background: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.75);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .inp-precio-grande {
            max-width: 160px;
            font-size: 1.75rem;
            font-weight: 800;
            text-align: center;
            border-radius: 12px;
            padding: 10px 8px;
        }

        .mini-precio-general.compacto {
            padding: 16px;
            margin-bottom: 12px;
        }

        .mini-precio-general.compacto .inp-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 8px;
        }

        .mini-precio-general.compacto .inp-row span {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--accent-blue);
        }

        .btn-continuar-resumen {
            flex: 1;
            padding: 16px;
            border-radius: 14px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent-blue) 0%, #4f46e5 100%);
            color: white;
            border: none;
            opacity: 0.4;
            cursor: not-allowed;
            transition: all 0.35s ease;
        }

        .btn-continuar-resumen.enabled {
            opacity: 1;
            cursor: pointer;
        }

        .opcion-flujo-btn {
            width: 100%;
            border-radius: 16px;
            padding: 20px 22px;
            margin-bottom: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.25s ease;
            text-align: left;
            border: 2px solid transparent;
            background: rgba(255, 255, 255, 0.04);
        }

        .opcion-flujo-btn:hover { transform: translateY(-2px); }

        .opcion-flujo-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            flex-shrink: 0;
        }

        .modal-elegir-panel {
            background: linear-gradient(160deg, #1e1e22 0%, #141416 50%, #1a1a1e 100%);
            border-radius: 20px;
            max-width: min(480px, 96vw);
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.7);
            animation: modalPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            overflow: hidden;
            margin: 10px auto;
        }

        .toolbar-personalizados {
            display: none;
            padding: 10px 12px;
            border-radius: 12px;
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.25);
            color: #fbbf24;
            font-size: 0.78rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 14px;
            line-height: 1.4;
        }

        .resumen-dato-legible {
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.45;
        }

        .resumen-label {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <div style="margin-bottom: 20px;">
            <button onclick="confirmarSalida()" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Volver a
                Eventos</button>
        </div>
        <div class="card">
            <div class="page-header">
                <h2><i class="bi bi-plus-circle-fill"></i>Crear Nuevo Evento</h2>
            </div>
            <?php if ($errores_php): ?>
                <div class="alert-danger" id="alertErroresEvento" style="border: 2px solid var(--danger); padding: 16px; border-radius: 12px; margin-bottom: 20px;">
                    <strong><i class="bi bi-exclamation-octagon-fill"></i> No se pudo crear el evento:</strong>
                    <ul style="margin: 10px 0 0;">
                        <?php foreach ($errores_php as $e)
                            echo "<li>$e</li>"; ?>
                    </ul>
                    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                        <p style="margin: 10px 0 0; font-size: 0.85rem; opacity: 0.9;">Si el error es por la imagen, vuelve a seleccionarla y repite el proceso.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <form id="fCreate" method="POST" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="es_gratuito" id="esGratuito" value="0">
                <input type="hidden" name="precio_general" id="hPrecioGeneral" value="">
                <input type="hidden" name="precio_nino" id="hPrecioNino" value="">
                <input type="hidden" name="precio_adulto_mayor" id="hPrecio3ra" value="">
                <input type="hidden" name="precio_discapacitado" id="hPrecioDisc" value="">
                <input type="hidden" name="usa_diferenciados" id="hUsaDiferenciados" value="0">
                <div class="row">
                    <div class="col-12">
                        <label class="form-label">Título del Evento</label>
                        <input type="text" id="tit" name="titulo" class="form-control"
                            style="font-size: 1.1rem; font-weight: 600;" required placeholder="Nombre del evento">
                    </div>
                    <div class="col-12">
                        <div class="funciones-section">
                            <label class="form-label"><i class="bi bi-calendar-week me-2"></i>Gestión de
                                Funciones</label>
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
                        <input type="text" id="ini" name="inicio_venta" class="form-control" readonly required
                            placeholder="Selecciona fecha y hora">
                        <div id="ttIni" class="tooltip-error"></div>
                        <div class="form-text"><i class="bi bi-info-circle"></i> No puede ser anterior a ahora</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" style="color: var(--text-muted);">Cierre Venta (Automático)</label>
                        <div class="cierre-container">
                            <input type="text" id="fin" name="cierre_venta" class="form-control" readonly required
                                style="padding-right: 50px;">
                            <button type="button" class="cierre-lock-btn" id="lockBtn"
                                title="Desbloquear edición manual"><i class="bi bi-lock-fill"></i></button>
                        </div>
                        <div class="form-text"><i class="bi bi-info-circle"></i> Se calcula 24 horas después de la última
                            función.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descripción</label>
                        <textarea id="desc" name="descripcion" class="form-control" rows="4" required
                            placeholder="Describe el evento..."></textarea>
                        <div id="ttDesc" class="tooltip-error"></div>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label">Imagen Promocional</label>
                        <input type="file" id="img" name="imagen" class="form-control" accept="image/*" required>
                        <div id="ttImg" class="tooltip-error"></div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Tipo de Escenario</label>
                        <select id="tipo" name="tipo" class="form-control" required>
                            <option value="">-- Selecciona --</option>
                            <option value="1">🎭 Teatro (420 Butacas)</option>
                            <option value="2">🚶 Pasarela (540 Butacas)</option>
                        </select>
                        <div id="ttTipo" class="tooltip-error"></div>
                    </div>
                </div>
                <hr>
                <button type="button" id="bSub" class="btn btn-primary"
                    style="width: 100%; padding: 16px; font-size: 1.1rem;" disabled><i class="bi bi-eye-fill"></i>
                    Revisar y Crear Evento</button>
            </form>
        </div>
    </div>

    <!-- MODAL: Cancelar/Salir -->
    <div class="modal-overlay" id="modalCancelar">
        <div class="modal-content">
            <div class="modal-icon-container"><i class="bi bi-exclamation-triangle-fill modal-icon"></i></div>
            <div class="modal-body-content">
                <h5>¿Salir sin guardar?</h5>
                <p>Tienes cambios sin guardar. Si sales ahora, perderás toda la información del nuevo evento.</p>
            </div>
            <div class="modal-buttons">
                <button class="btn btn-leave" onclick="goBack()"><i class="bi bi-box-arrow-left"></i> Salir</button>
                <button class="btn btn-stay" onclick="cerrarModal()"><i class="bi bi-pencil-fill"></i> Seguir
                    Editando</button>
            </div>
        </div>
    </div>

    <!-- MODAL: Confirmación Final de Datos del Evento -->
    <div class="modal-overlay" id="modalConfirmacion" style="overflow-y:auto;padding:20px 0;">
        <div style="background: linear-gradient(160deg, #1e1e22 0%, #141416 50%, #1a1a1e 100%); border-radius: 24px; max-width: min(600px, 96vw); width: 100%; border: 1px solid rgba(255,255,255,0.08); box-shadow: 0 25px 80px rgba(0,0,0,0.7); animation: modalPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); overflow: hidden; margin: auto;">
            <!-- Header con gradiente -->
            <div style="background: linear-gradient(135deg, #1561f0 0%, #4f46e5 50%, #7c3aed 100%); padding: 35px 30px; text-align: center; position: relative;">
                <div style="font-size: 3rem; margin-bottom: 8px;">🎭</div>
                <h3 style="color: white; font-weight: 800; font-size: 1.4rem; margin: 0;">Resumen del Evento</h3>
                <p style="color: rgba(255,255,255,0.7); font-size: 0.85rem; margin: 8px 0 0;">Revisa los datos antes de continuar</p>
                <div style="position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);border-left:25px solid transparent;border-right:25px solid transparent;border-top:20px solid #7c3aed;"></div>
            </div>

            <!-- Datos del evento -->
            <div style="padding: 40px 30px 20px;">
                <!-- Título -->
                <div style="text-align: center; margin-bottom: 25px;">
                    <div style="color: rgba(255,255,255,0.5); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 6px;">TÍTULO DEL EVENTO</div>
                    <div id="confTitulo" style="color: #fff; font-size: 1.6rem; font-weight: 800; line-height: 1.3;"></div>
                </div>

                <!-- Info Grid -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px;">
                    <!-- Escenario -->
                    <div style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 16px; text-align: center;">
                        <div style="color: rgba(255,255,255,0.5); font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">ESCENARIO</div>
                        <div id="confTipo" style="color: #fff; font-size: 1rem; font-weight: 700;"></div>
                    </div>
                    <!-- Inicio Venta -->
                    <div style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 16px; text-align: center;">
                        <div class="resumen-label">VENTAS DESDE</div>
                        <div id="confIniVenta" class="resumen-dato-legible"></div>
                    </div>
                </div>

                <!-- Cierre venta -->
                <div style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 16px; margin-bottom: 20px; text-align: center;">
                    <div class="resumen-label"><i class="bi bi-door-closed"></i> CIERRE DE VENTA</div>
                    <div id="confCierreVenta" class="resumen-dato-legible"></div>
                </div>

                <!-- Funciones -->
                <div style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 16px; margin-bottom: 20px;">
                    <div style="color: rgba(255,255,255,0.5); font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; text-align: center;"><i class="bi bi-calendar-event" style="margin-right: 6px;"></i>FUNCIONES PROGRAMADAS</div>
                    <div id="confFunciones" class="conf-funciones-list"></div>
                </div>

                <!-- Descripción -->
                <div style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 16px; margin-bottom: 20px;">
                    <div style="color: rgba(255,255,255,0.5); font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">DESCRIPCIÓN</div>
                    <div id="confDesc" style="color: rgba(255,255,255,0.8); font-size: 0.85rem; line-height: 1.6; max-height: 80px; overflow-y: auto;"></div>
                </div>

                <!-- Timer / Countdown -->
                <div style="text-align: center; margin-bottom: 10px;">
                    <div id="confTimer" style="color: var(--warning); font-size: 0.9rem; font-weight: 700;"><i class="bi bi-hourglass-split" style="margin-right: 5px;"></i>El botón Continuar se habilitará en <span id="confCountdown">5</span>s...</div>
                </div>
            </div>

            <!-- Botones -->
            <div style="display: flex; gap: 14px; padding: 10px 30px 30px;">
                <button type="button" onclick="cerrarConfirmacion()" class="btn" style="flex: 1; padding: 16px; border-radius: 14px; font-weight: 700; background: rgba(255,69,58,0.1); color: var(--danger); border: 2px solid rgba(255,69,58,0.5);">
                    <i class="bi bi-pencil"></i> Corregir
                </button>
                <button type="button" id="btnContinuar" onclick="abrirTipoPrecio()" class="btn btn-continuar-resumen" disabled>
                    <i class="bi bi-arrow-right"></i> Continuar
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: ¿Gratuito o De Pago? -->
    <div class="modal-overlay" id="modalTipoPrecio">
        <div style="background: linear-gradient(160deg, #1e1e22 0%, #141416 50%, #1a1a1e 100%); border-radius: 24px; max-width: 480px; width: 92%; border: 1px solid rgba(255,255,255,0.08); box-shadow: 0 25px 80px rgba(0,0,0,0.7); animation: modalPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); overflow: hidden;">
            <!-- Header -->
            <div style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 35px 30px; text-align: center; position: relative;">
                <div style="font-size: 3rem; margin-bottom: 8px;">💰</div>
                <h3 style="color: white; font-weight: 800; font-size: 1.4rem; margin: 0;">¿Tipo de Evento?</h3>
                <p style="color: rgba(255,255,255,0.8); font-size: 0.85rem; margin: 8px 0 0;">Elige cómo se cobrarán los boletos</p>
                <div style="position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);border-left:25px solid transparent;border-right:25px solid transparent;border-top:20px solid #d97706;"></div>
            </div>

            <div style="padding: 45px 30px 30px;">
                <!-- Opción Gratuito -->
                <button type="button" onclick="seleccionarTipo(true)" style="width: 100%; background: rgba(16,185,129,0.08); border: 2px solid rgba(16,185,129,0.3); border-radius: 16px; padding: 22px 24px; margin-bottom: 14px; cursor: pointer; display: flex; align-items: center; gap: 18px; transition: all 0.3s ease; text-align: left;" onmouseover="this.style.background='rgba(16,185,129,0.15)';this.style.borderColor='#10b981';this.style.transform='translateY(-2px) scale(1.01)'" onmouseout="this.style.background='rgba(16,185,129,0.08)';this.style.borderColor='rgba(16,185,129,0.3)';this.style.transform='none'">
                    <div style="width: 56px; height: 56px; background: rgba(16,185,129,0.2); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; flex-shrink: 0;">🎁</div>
                    <div>
                        <div style="color: #10b981; font-weight: 800; font-size: 1.15rem; margin-bottom: 4px;">Evento Gratuito</div>
                        <div style="color: rgba(255,255,255,0.5); font-size: 0.8rem; line-height: 1.4;">Todos los boletos serán $0. En el ticket aparecerá <strong style="color:#10b981">"EVENTO GRATUITO"</strong></div>
                    </div>
                </button>

                <!-- Opción De Pago -->
                <button type="button" onclick="seleccionarTipo(false)" style="width: 100%; background: rgba(21,97,240,0.08); border: 2px solid rgba(21,97,240,0.3); border-radius: 16px; padding: 22px 24px; cursor: pointer; display: flex; align-items: center; gap: 18px; transition: all 0.3s ease; text-align: left;" onmouseover="this.style.background='rgba(21,97,240,0.15)';this.style.borderColor='var(--accent-blue)';this.style.transform='translateY(-2px) scale(1.01)'" onmouseout="this.style.background='rgba(21,97,240,0.08)';this.style.borderColor='rgba(21,97,240,0.3)';this.style.transform='none'">
                    <div style="width: 56px; height: 56px; background: rgba(21,97,240,0.2); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; flex-shrink: 0;">💵</div>
                    <div>
                        <div style="color: var(--accent-blue); font-weight: 800; font-size: 1.15rem; margin-bottom: 4px;">Evento de Pago</div>
                        <div style="color: rgba(255,255,255,0.5); font-size: 0.8rem; line-height: 1.4;">Define el <strong style="color:var(--accent-blue)">precio del boleto</strong> en un menú rápido</div>
                    </div>
                </button>

                <!-- Botón Regresar -->
                <div style="text-align: center; margin-top: 20px;">
                    <button type="button" onclick="volverAConfirmacion()" style="background: none; border: none; color: rgba(255,255,255,0.4); font-size: 0.85rem; cursor: pointer; font-weight: 600;">
                        <i class="bi bi-arrow-left"></i> Volver atrás
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: ¿Predeterminados o personalizados? -->
    <div class="modal-overlay" id="modalElegirPrecios">
        <div class="modal-elegir-panel">
            <div style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); padding: 28px 24px; text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 6px;">💲</div>
                <h3 style="color: white; font-weight: 800; font-size: 1.25rem; margin: 0;">¿Cómo quieres los precios?</h3>
                <p style="color: rgba(255,255,255,0.8); font-size: 0.82rem; margin: 8px 0 0;">Elige si usas los del sistema o defines otros</p>
            </div>
            <div style="padding: 28px 24px 24px;">
                <button type="button" class="opcion-flujo-btn" style="border-color: rgba(21,97,240,0.35); background: rgba(21,97,240,0.08);" onclick="elegirModoPrecios('predeterminados')">
                    <div class="opcion-flujo-icon" style="background: rgba(21,97,240,0.2);">🌐</div>
                    <div>
                        <div style="color: var(--accent-blue); font-weight: 800; font-size: 1.05rem; margin-bottom: 4px;">Precios predeterminados</div>
                        <div style="color: rgba(255,255,255,0.5); font-size: 0.78rem; line-height: 1.4;">Usa los del módulo <strong>Precios y Categorías</strong> (General $<?= number_format($precios_globales_js['general'], 0) ?>, Niño $<?= number_format($precios_globales_js['nino'], 0) ?>...)</div>
                    </div>
                </button>
                <button type="button" class="opcion-flujo-btn" style="border-color: rgba(245,158,11,0.35); background: rgba(245,158,11,0.08);" onclick="elegirModoPrecios('personalizados')">
                    <div class="opcion-flujo-icon" style="background: rgba(245,158,11,0.2);">✏️</div>
                    <div>
                        <div style="color: #fbbf24; font-weight: 800; font-size: 1.05rem; margin-bottom: 4px;">Precios personalizados</div>
                        <div style="color: rgba(255,255,255,0.5); font-size: 0.78rem; line-height: 1.4;">Empiezas en <strong style="color:#fbbf24">$0</strong> y defines cada precio para este evento</div>
                    </div>
                </button>
                <div style="text-align: center; margin-top: 16px;">
                    <button type="button" onclick="volverDesdeElegirPrecios()" style="background: none; border: none; color: rgba(255,255,255,0.4); font-size: 0.85rem; cursor: pointer; font-weight: 600;">
                        <i class="bi bi-arrow-left"></i> Volver
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: Precios rápidos (mini versión de ctg_boletos) -->
    <div class="modal-overlay" id="modalPreciosRapidos">
        <div class="modal-precios-panel">
            <div class="modal-precios-header">
                <div class="icono-header">💵</div>
                <h3>Precio del Boleto</h3>
                <p>Precios predeterminados del sistema — ajusta si necesitas</p>
            </div>

            <div class="modal-precios-scroll" id="modalPreciosScroll">
                <div class="precios-toolbar" id="toolbarPredeterminados">
                    <span class="badge-precios-globales"><i class="bi bi-globe2"></i> Precios predeterminados del módulo</span>
                    <button type="button" id="btnRestaurarPred" onclick="restaurarPreciosPredeterminados()" class="btn btn-restaurar-precios">
                        <i class="bi bi-arrow-counterclockwise"></i> Restaurar predeterminados
                    </button>
                </div>
                <div class="toolbar-personalizados" id="toolbarPersonalizados">
                    <i class="bi bi-pencil-square"></i> Precios personalizados — ingresa el valor de cada tipo de boleto
                </div>

                <div class="mini-precio-general compacto">
                    <div class="resumen-label" style="color: rgba(255,255,255,0.65); font-size: 0.7rem;">PRECIO GENERAL</div>
                    <div class="inp-row">
                        <span>$</span>
                        <input type="number" id="inpPrecioGeneral" class="form-control inp-precio-grande" min="1" step="1">
                    </div>
                    <small style="color: rgba(255,255,255,0.45); display: block; margin-top: 8px; font-size: 0.75rem;">Adultos / boletos generales</small>
                </div>

                <div class="mini-switch-row">
                    <div>
                        <div style="color: #fff; font-weight: 700; font-size: 0.85rem;"><i class="bi bi-people-fill"></i> Precios diferenciados</div>
                        <div style="color: rgba(255,255,255,0.45); font-size: 0.72rem;">Niño, 3ra edad, discapacitado</div>
                    </div>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" id="switchMiniDiferenciados" style="width: 2.4em; height: 1.25em;">
                    </div>
                </div>

                <div class="mini-tipos-list collapsed" id="miniTiposGrid">
                    <div class="precio-list-item">
                        <div class="precio-list-icon">👶</div>
                        <div class="precio-list-info">
                            <div class="precio-list-nombre">Niño</div>
                            <div class="precio-list-desc">Menores</div>
                        </div>
                        <div class="precio-list-input">
                            <span class="simbolo">$</span>
                            <input type="number" id="inpPrecioNino" class="form-control" min="0" step="1">
                        </div>
                    </div>
                    <div class="precio-list-item">
                        <div class="precio-list-icon">👴</div>
                        <div class="precio-list-info">
                            <div class="precio-list-nombre">3ra Edad</div>
                            <div class="precio-list-desc">Adulto mayor</div>
                        </div>
                        <div class="precio-list-input">
                            <span class="simbolo">$</span>
                            <input type="number" id="inpPrecio3ra" class="form-control" min="0" step="1">
                        </div>
                    </div>
                    <div class="precio-list-item">
                        <div class="precio-list-icon">♿</div>
                        <div class="precio-list-info">
                            <div class="precio-list-nombre">Discapacitado</div>
                            <div class="precio-list-desc">Acceso especial</div>
                        </div>
                        <div class="precio-list-input">
                            <span class="simbolo">$</span>
                            <input type="number" id="inpPrecioDisc" class="form-control" min="0" step="1">
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-precios-footer">
                <button type="button" onclick="volverATipoPrecio()" class="btn btn-precios-atras">
                    <i class="bi bi-arrow-left"></i> Atrás
                </button>
                <button type="button" onclick="confirmarPreciosYcrear()" class="btn btn-precios-crear">
                    <i class="bi bi-check-circle-fill"></i> Crear evento
                </button>
            </div>
        </div>
    </div>

    <!-- Pantalla de progreso / éxito al crear evento -->
    <div id="overlayProgresoEvento" class="evt-progreso-overlay" aria-hidden="true">
        <div class="evt-success-card">
            <div class="evt-icon-circle modo-creando" id="evtProgressIcon"><i class="bi bi-hourglass-split"></i></div>
            <h4 id="evtProgressTitle" style="font-weight: 800; margin-bottom: 8px; color: var(--text-primary);">Creando evento...</h4>
            <p id="evtProgressMsg" style="font-size: 0.95rem; margin-bottom: 0; color: var(--text-muted);">Guardando datos e imagen</p>
            <div id="evtProgressBadge" class="evt-tipo-badge pago" style="display: none;"></div>
            <div class="evt-progress-track">
                <div class="evt-progress-fill" id="evtProgressBar"></div>
            </div>
            <p id="evtProgressFooter" style="font-size: 0.75rem; font-weight: 600; margin-top: 12px; color: var(--text-muted);">Por favor espera...</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    <script>
        // Estado compartido (fuera de DOMContentLoaded para que los modales lo usen)
        let formModificado = false;
        let beforeUnloadHandler = null;

        function desactivarProteccionSalida() {
            formModificado = false;
            if (beforeUnloadHandler) {
                window.removeEventListener('beforeunload', beforeUnloadHandler);
                beforeUnloadHandler = null;
            }
            window.onbeforeunload = null;
        }

        function activarProteccionSalida() {
            if (beforeUnloadHandler) return;
            beforeUnloadHandler = function (e) {
                if (formModificado) {
                    e.preventDefault();
                    e.returnValue = '';
                    return '';
                }
            };
            window.addEventListener('beforeunload', beforeUnloadHandler);
        }

        function configurarInputsPrecio() {
            ['inpPrecioGeneral', 'inpPrecioNino', 'inpPrecio3ra', 'inpPrecioDisc'].forEach(id => {
                const el = document.getElementById(id);
                if (!el || el.dataset.precioFocusBound) return;
                el.dataset.precioFocusBound = '1';
                el.addEventListener('focus', function () {
                    const v = this.value.trim();
                    if (v === '' || parseFloat(v) === 0) {
                        this.value = '';
                    }
                });
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.body.classList.add('loaded');
            configurarInputsPrecio();
            flatpickr.localize(flatpickr.l10ns.es);
            const now = new Date();
            let funcs = [];
            let cierreBloqueado = true;

            const els = {
                add: document.getElementById('fAdd'),
                sub: document.getElementById('bSub'),
                list: document.getElementById('lista-funciones'),
                hid: document.getElementById('hidFunc'),
                no: document.getElementById('noFunc'),
                ttF: document.getElementById('ttFunc'),
                ttI: document.getElementById('ttIni'),
                ini: document.getElementById('ini'),
                fin: document.getElementById('fin'),
                desc: document.getElementById('desc'),
                img: document.getElementById('img'),
                tipo: document.getElementById('tipo'),
                ttDesc: document.getElementById('ttDesc'),
                ttImg: document.getElementById('ttImg'),
                ttTipo: document.getElementById('ttTipo'),
                lockBtn: document.getElementById('lockBtn')
            };

            const fpD = flatpickr("#fDate", { minDate: "today", dateFormat: "Y-m-d", onChange: check });
            const fpT = flatpickr("#fTime", { enableTime: true, noCalendar: true, dateFormat: "H:i", time_24hr: true, minuteIncrement: 15, onChange: check });
            const fpI = flatpickr("#ini", { enableTime: true, minDate: now, dateFormat: "Y-m-d H:i", onChange: () => val(false) });
            const fpE = flatpickr("#fin", { enableTime: true, dateFormat: "Y-m-d H:i", clickOpens: false });

            els.lockBtn.onclick = () => {
                cierreBloqueado = !cierreBloqueado;
                if (cierreBloqueado) { els.lockBtn.innerHTML = '<i class="bi bi-lock-fill"></i>'; els.lockBtn.classList.remove('unlocked'); fpE.set('clickOpens', false); recalcularCierre(); }
                else { els.lockBtn.innerHTML = '<i class="bi bi-unlock-fill"></i>'; els.lockBtn.classList.add('unlocked'); fpE.set('clickOpens', true); }
            };

            function recalcularCierre() {
                if (funcs.length) {
                    const funcionMayor = funcs.reduce((max, f) => f > max ? f : max, funcs[0]);
                    // Se suma el margen de cierre de ventas a la ÚLTIMA función
                    fpE.setDate(new Date(funcionMayor.getTime() + <?= (int) MS_CIERRE_VENTAS_POST_FUNCION ?>), true);
                } 
            }
            function check() { els.add.disabled = !(fpD.selectedDates.length && fpT.selectedDates.length); }

            els.add.onclick = () => {
                if (!fpD.selectedDates[0] || !fpT.selectedDates[0]) return;
                let dt = new Date(fpD.selectedDates[0].getTime());
                dt.setHours(fpT.selectedDates[0].getHours()); dt.setMinutes(fpT.selectedDates[0].getMinutes()); dt.setSeconds(0);
                if (dt <= new Date(Date.now() + 60000)) { alert("La función debe ser en el futuro."); return; }
                if (funcs.some(d => d.getTime() === dt.getTime())) { alert("Ya existe esta función."); return; }
                funcs.push(dt); funcs.sort((a, b) => a - b); formModificado = true; fpD.clear(); fpT.clear(); check(); upd();
            };

            function upd() {
                els.list.innerHTML = ''; els.hid.innerHTML = '';
                if (!funcs.length) { els.list.appendChild(els.no); fpI.set('maxDate', null); fpE.setDate(null); }
                else {
                    funcs.forEach((d, i) => {
                        const sqlDate = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}:00`;
                        els.list.innerHTML += htmlFuncionItem(d, i);
                        els.hid.innerHTML += `<input type="hidden" name="funciones[]" value="${sqlDate}">`;
                    });
                    fpI.set('maxDate', new Date(funcs[0].getTime() - 60000)); recalcularCierre();
                }
                val(false);
            }

            window.del = i => { funcs.splice(i, 1); formModificado = true; upd(); };

            function clearFieldErrors() {
                [els.ttF, els.ttI, els.ttDesc, els.ttImg, els.ttTipo].forEach(e => { if (e) e.style.display = 'none'; });
                document.querySelectorAll('.input-error').forEach(e => e.classList.remove('input-error'));
            }

            function val(showErrors = false) {
                let ok = true;
                clearFieldErrors();

                if (!document.getElementById('tit').value.trim()) ok = false;

                if (!funcs.length) {
                    ok = false;
                    if (showErrors) err(els.ttF, null, 'Añade al menos una función.');
                }

                if (!fpI.selectedDates.length) {
                    if (funcs.length) {
                        ok = false;
                        if (showErrors) err(els.ttI, els.ini, 'Requerido.');
                    }
                } else {
                    if (fpI.selectedDates[0] < new Date()) {
                        ok = false;
                        if (showErrors) err(els.ttI, els.ini, 'No puede ser anterior a ahora.');
                    }
                    if (funcs.length && fpI.selectedDates[0] >= funcs[0]) {
                        ok = false;
                        if (showErrors) err(els.ttI, els.ini, 'Debe ser anterior a la primera función.');
                    }
                }

                if (!els.desc.value.trim()) {
                    ok = false;
                    if (showErrors) err(els.ttDesc, els.desc, 'Descripción obligatoria.');
                }
                if (!els.img.files.length) {
                    ok = false;
                    if (showErrors) err(els.ttImg, els.img, 'Imagen obligatoria.');
                }
                if (!els.tipo.value) {
                    ok = false;
                    if (showErrors) err(els.ttTipo, els.tipo, 'Selecciona escenario.');
                }

                els.sub.disabled = !ok;
                return ok;
            }

            function err(t, i, m) { t.textContent = m; t.style.display = 'flex'; if (i) i.classList.add('input-error'); }

            function intentarConfirmacion() {
                if (!val(true)) {
                    const firstErr = [...document.querySelectorAll('.tooltip-error')]
                        .find(el => el.style.display !== 'none' && el.textContent.trim());
                    if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }
                abrirConfirmacion();
            }
            els.sub.addEventListener('click', intentarConfirmacion);

            ['tit', 'desc', 'img', 'tipo'].forEach(id => {
                const el = document.getElementById(id);
                el.addEventListener(id === 'img' || id === 'tipo' ? 'change' : 'input', () => {
                    formModificado = true;
                    if (id === 'img') cachearImagenEvento();
                    val(false);
                });
            });
            document.getElementById('ini').addEventListener('change', () => { formModificado = true; val(false); });
            document.getElementById('fin').addEventListener('change', () => formModificado = true);
            activarProteccionSalida();
            val(false);

            const alertErr = document.getElementById('alertErroresEvento');
            if (alertErr) {
                alertErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        // ====== FUNCIONES DE LOS MODALES ======
        let urlDestino = null;
        let countdownInterval = null;

        function confirmarSalida(destino = null) {
            urlDestino = destino;
            if (formModificado) {
                document.getElementById('modalCancelar').classList.add('active');
            } else {
                goBack();
            }
        }
        function cerrarModal() { document.getElementById('modalCancelar').classList.remove('active'); urlDestino = null; }
        function goBack() {
            desactivarProteccionSalida();
            document.body.classList.remove('loaded');
            document.body.classList.add('exiting');
            setTimeout(() => window.location.href = urlDestino || 'act_evento.php', 350);
        }

        const preciosGlobalesDefault = <?= json_encode($precios_globales_js, JSON_UNESCAPED_UNICODE) ?>;
        const usaDiferenciadosGlobal = <?= $usa_diferenciados_global ? 'true' : 'false' ?>;
        const EVT_DESTINO_LISTA = <?= json_encode($evt_destino_lista) ?>;

        // Fechas legibles: "5 de febrero de 2026 a las 5:00 PM"
        function formatoAMPM(dateObj) {
            let h = dateObj.getHours();
            let m = dateObj.getMinutes();
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12; if (h === 0) h = 12;
            const min = m > 0 ? ':' + String(m).padStart(2, '0') : '';
            return h + min + ' ' + ampm;
        }

        function formatoLegible(dateObj) {
            const meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            return dateObj.getDate() + ' de ' + meses[dateObj.getMonth()] + ' de ' + dateObj.getFullYear() + ' a las ' + formatoAMPM(dateObj);
        }

        function formatFuncionDisplay(dateObj) {
            const mesesCorto = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
            const meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            const dia = dateObj.getDate();
            const mes = meses[dateObj.getMonth()];
            const mesCorto = mesesCorto[dateObj.getMonth()];
            const anio = dateObj.getFullYear();
            let h = dateObj.getHours();
            const m = dateObj.getMinutes();
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12;
            if (h === 0) h = 12;
            const minStr = m > 0 ? ':' + String(m).padStart(2, '0') : '';
            const hora = h + minStr + ' ' + ampm;
            return {
                dia,
                mes,
                mesCorto,
                anio,
                hora,
                texto: dia + ' de ' + mes + ' de ' + anio
            };
        }

        function htmlFuncionItem(dateObj, index) {
            const f = formatFuncionDisplay(dateObj);
            return `<div class="funcion-item">
                <div class="funcion-item-date">
                    <span class="funcion-item-dia">${f.dia}</span>
                    <span class="funcion-item-mes">${f.mesCorto}</span>
                </div>
                <div class="funcion-item-body">
                    <span class="funcion-item-texto">${f.texto}</span>
                    <span class="funcion-item-hora"><i class="bi bi-clock"></i> ${f.hora}</span>
                </div>
                <button type="button" class="funcion-item-del" onclick="del(${index})" title="Eliminar">×</button>
            </div>`;
        }

        function parseFechaInput(val) {
            if (!val) return null;
            return new Date(val.replace(' ', 'T'));
        }

        function habilitarBotonContinuar() {
            const btn = document.getElementById('btnContinuar');
            btn.disabled = false;
            btn.classList.add('enabled');
        }

        function reiniciarContadorConfirmacion() {
            const timerEl = document.getElementById('confTimer');
            timerEl.innerHTML = '<i class="bi bi-hourglass-split" style="margin-right: 5px;"></i>El botón Continuar se habilitará en <span id="confCountdown">5</span>s...';
            timerEl.style.display = 'block';

            const btn = document.getElementById('btnContinuar');
            btn.disabled = true;
            btn.classList.remove('enabled');

            let sec = 5;
            const tick = () => {
                const countdownEl = document.getElementById('confCountdown');
                if (countdownEl) countdownEl.textContent = sec;
            };
            tick();

            if (countdownInterval) clearInterval(countdownInterval);
            countdownInterval = setInterval(() => {
                sec--;
                tick();
                if (sec <= 0) {
                    clearInterval(countdownInterval);
                    countdownInterval = null;
                    timerEl.innerHTML = '<i class="bi bi-check-circle-fill" style="margin-right:5px;color:var(--success)"></i><span style="color:var(--success)">¡Listo! Puedes continuar cuando quieras</span>';
                    habilitarBotonContinuar();
                }
            }, 1000);
        }

        // ====== MODAL 1: Confirmación de datos ======
        function abrirConfirmacion() {
            cachearImagenEvento();
            // Llenar datos
            document.getElementById('confTitulo').textContent = document.getElementById('tit').value;
            
            const tipoSel = document.getElementById('tipo');
            document.getElementById('confTipo').textContent = tipoSel.options[tipoSel.selectedIndex].text;

            // Inicio venta en AM/PM
            const iniDate = parseFechaInput(document.getElementById('ini').value);
            if (iniDate) {
                document.getElementById('confIniVenta').textContent = formatoLegible(iniDate);
            }

            const finDate = parseFechaInput(document.getElementById('fin').value);
            if (finDate) {
                document.getElementById('confCierreVenta').textContent = formatoLegible(finDate);
            }

            const funcContainer = document.getElementById('confFunciones');
            funcContainer.innerHTML = '';
            document.querySelectorAll('input[name="funciones[]"]').forEach(input => {
                const dt = parseFechaInput(input.value);
                if (!dt) return;
                const f = formatFuncionDisplay(dt);
                const row = document.createElement('div');
                row.className = 'conf-funcion-row';
                row.innerHTML = `<div class="conf-funcion-date"><span>${f.dia}</span><small>${f.mesCorto}</small></div>
                    <div class="conf-funcion-info">
                        <strong>${f.texto}</strong>
                        <span><i class="bi bi-clock"></i> ${f.hora}</span>
                    </div>`;
                funcContainer.appendChild(row);
            });

            // Descripción
            document.getElementById('confDesc').textContent = document.getElementById('desc').value;

            // Mostrar modal y reiniciar contador (siempre restaura el HTML del timer)
            document.getElementById('modalConfirmacion').classList.add('active');
            reiniciarContadorConfirmacion();
        }

        function cerrarConfirmacion() {
            document.getElementById('modalConfirmacion').classList.remove('active');
            if (countdownInterval) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }
        }

        // ====== MODAL 2: Tipo de precio ======
        function abrirTipoPrecio() {
            document.getElementById('modalConfirmacion').classList.remove('active');
            if (countdownInterval) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }
            document.getElementById('modalTipoPrecio').classList.add('active');
        }

        function volverAConfirmacion() {
            document.getElementById('modalTipoPrecio').classList.remove('active');
            document.getElementById('modalElegirPrecios').classList.remove('active');
            document.getElementById('modalPreciosRapidos').classList.remove('active');
            abrirConfirmacion();
        }

        function volverDesdeElegirPrecios() {
            document.getElementById('modalElegirPrecios').classList.remove('active');
            document.getElementById('modalTipoPrecio').classList.add('active');
        }

        function volverATipoPrecio() {
            document.getElementById('modalPreciosRapidos').classList.remove('active');
            document.getElementById('modalElegirPrecios').classList.add('active');
        }

        let modoPreciosEvento = 'predeterminados';

        function aplicarPreciosPredeterminados() {
            const g = preciosGlobalesDefault.general || 80;
            const n = preciosGlobalesDefault.nino || 50;
            const a = preciosGlobalesDefault.adulto_mayor || 60;
            const d = preciosGlobalesDefault.discapacitado || 40;

            document.getElementById('inpPrecioGeneral').value = g;
            document.getElementById('inpPrecioNino').value = n;
            document.getElementById('inpPrecio3ra').value = a;
            document.getElementById('inpPrecioDisc').value = d;

            const sw = document.getElementById('switchMiniDiferenciados');
            sw.checked = usaDiferenciadosGlobal;
            document.getElementById('miniTiposGrid').classList.toggle('collapsed', !sw.checked);
            document.getElementById('inpPrecioGeneral').min = 1;
        }

        function aplicarPreciosPersonalizados() {
            document.getElementById('inpPrecioGeneral').value = 0;
            document.getElementById('inpPrecioNino').value = 0;
            document.getElementById('inpPrecio3ra').value = 0;
            document.getElementById('inpPrecioDisc').value = 0;

            const sw = document.getElementById('switchMiniDiferenciados');
            sw.checked = false;
            document.getElementById('miniTiposGrid').classList.add('collapsed');
            document.getElementById('inpPrecioGeneral').min = 0;
        }

        function restaurarPreciosPredeterminados() {
            modoPreciosEvento = 'predeterminados';
            aplicarPreciosPredeterminados();
            actualizarUIModoPrecios();
        }

        function actualizarUIModoPrecios() {
            const esPersonalizado = modoPreciosEvento === 'personalizados';
            document.getElementById('toolbarPredeterminados').style.display = esPersonalizado ? 'none' : 'flex';
            document.getElementById('toolbarPersonalizados').style.display = esPersonalizado ? 'block' : 'none';
            const headerP = document.querySelector('#modalPreciosRapidos .modal-precios-header p');
            if (headerP) {
                headerP.textContent = esPersonalizado
                    ? 'Ingresa los precios que quieras para este evento'
                    : 'Precios del módulo Precios y Categorías — ajusta si necesitas';
            }
        }

        function elegirModoPrecios(modo) {
            modoPreciosEvento = modo;
            document.getElementById('modalElegirPrecios').classList.remove('active');
            abrirPreciosRapidos(modo);
        }

        function abrirElegirModoPrecios() {
            document.getElementById('modalElegirPrecios').classList.add('active');
        }

        function abrirPreciosRapidos(modo) {
            if (modo) modoPreciosEvento = modo;

            if (modoPreciosEvento === 'personalizados') {
                aplicarPreciosPersonalizados();
            } else {
                aplicarPreciosPredeterminados();
            }
            actualizarUIModoPrecios();

            const sw = document.getElementById('switchMiniDiferenciados');
            if (!sw.dataset.bound) {
                sw.dataset.bound = '1';
                sw.addEventListener('change', () => {
                    document.getElementById('miniTiposGrid').classList.toggle('collapsed', !sw.checked);
                });
            }

            document.getElementById('modalPreciosRapidos').classList.add('active');
            const scrollEl = document.getElementById('modalPreciosScroll');
            if (scrollEl) scrollEl.scrollTop = 0;
        }

        let enviandoEvento = false;
        let imagenEventoCache = null;

        function cachearImagenEvento() {
            const img = document.getElementById('img');
            if (img && img.files && img.files.length) {
                imagenEventoCache = img.files[0];
            }
        }

        function restaurarImagenEnInput() {
            const img = document.getElementById('img');
            if (!imagenEventoCache || !img) return;
            try {
                const dt = new DataTransfer();
                dt.items.add(imagenEventoCache);
                img.files = dt.files;
            } catch (e) {
                /* algunos navegadores no permiten asignar files; fetch usará la caché */
            }
        }

        function validarImagenAntesDeEnviar() {
            cachearImagenEvento();
            restaurarImagenEnInput();
            const img = document.getElementById('img');
            const tieneArchivo = imagenEventoCache || (img && img.files && img.files.length > 0);
            if (!tieneArchivo) {
                alert('Falta la imagen del evento. Vuelve al formulario y selecciónala de nuevo.');
                enviandoEvento = false;
                ocultarPantallaProgresoEvento();
                return false;
            }
            return true;
        }

        function construirFormDataEvento() {
            cachearImagenEvento();
            restaurarImagenEnInput();

            const form = document.getElementById('fCreate');
            const fd = new FormData(form);

            fd.set('es_gratuito', document.getElementById('esGratuito').value);
            fd.set('precio_general', document.getElementById('hPrecioGeneral').value);
            fd.set('precio_nino', document.getElementById('hPrecioNino').value);
            fd.set('precio_adulto_mayor', document.getElementById('hPrecio3ra').value);
            fd.set('precio_discapacitado', document.getElementById('hPrecioDisc').value);
            fd.set('usa_diferenciados', document.getElementById('hUsaDiferenciados').value);
            fd.set('respuesta_json', '1');

            if (imagenEventoCache) {
                fd.delete('imagen');
                fd.append('imagen', imagenEventoCache, imagenEventoCache.name || 'evento.jpg');
            }

            return fd;
        }

        function mostrarPantallaProgresoCreando() {
            const overlay = document.getElementById('overlayProgresoEvento');
            const bar = document.getElementById('evtProgressBar');
            const icon = document.getElementById('evtProgressIcon');

            document.getElementById('evtProgressTitle').textContent = 'Creando evento...';
            document.getElementById('evtProgressMsg').textContent = 'Guardando datos e imagen';
            document.getElementById('evtProgressFooter').textContent = 'Por favor espera...';
            document.getElementById('evtProgressBadge').style.display = 'none';

            icon.className = 'evt-icon-circle modo-creando';
            icon.innerHTML = '<i class="bi bi-hourglass-split"></i>';
            bar.className = 'evt-progress-fill';
            bar.style.transition = 'width 2.5s ease';
            bar.style.width = '15%';

            overlay.classList.remove('exiting');
            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden', 'false');

            requestAnimationFrame(() => {
                setTimeout(() => { bar.style.width = '65%'; }, 80);
            });
        }

        function mostrarPantallaProgresoExito(esGratis) {
            const overlay = document.getElementById('overlayProgresoEvento');
            const bar = document.getElementById('evtProgressBar');
            const icon = document.getElementById('evtProgressIcon');
            const badge = document.getElementById('evtProgressBadge');

            document.getElementById('evtProgressTitle').textContent = '¡Evento Creado!';
            document.getElementById('evtProgressMsg').textContent = esGratis
                ? 'Evento gratuito creado. Los tickets mostrarán EVENTO GRATUITO.'
                : 'Evento de pago creado correctamente.';
            document.getElementById('evtProgressFooter').textContent = 'VOLVIENDO A EVENTOS ACTIVOS...';

            badge.style.display = 'inline-block';
            badge.textContent = esGratis ? '🎁 EVENTO GRATUITO' : '💰 EVENTO DE PAGO';
            badge.className = 'evt-tipo-badge ' + (esGratis ? 'gratis' : 'pago');

            icon.className = 'evt-icon-circle modo-exito ' + (esGratis ? 'gratis' : 'pago');
            icon.innerHTML = '<i class="bi ' + (esGratis ? 'bi-gift-fill' : 'bi-cash-coin') + '"></i>';

            bar.className = 'evt-progress-fill ' + (esGratis ? 'gratis' : '');
            bar.style.transition = 'width 1.5s cubic-bezier(0.4, 0, 0.2, 1)';
            bar.style.width = '100%';

            localStorage.setItem('evt_upd', Date.now());

            setTimeout(() => {
                overlay.classList.add('exiting');
                setTimeout(() => {
                    window.location.href = EVT_DESTINO_LISTA;
                }, 350);
            }, 1800);
        }

        function ocultarPantallaProgresoEvento() {
            const overlay = document.getElementById('overlayProgresoEvento');
            if (overlay) {
                overlay.style.display = 'none';
                overlay.classList.remove('exiting');
                overlay.setAttribute('aria-hidden', 'true');
            }
        }

        function cerrarModalesFlujo() {
            document.getElementById('modalConfirmacion').classList.remove('active');
            document.getElementById('modalTipoPrecio').classList.remove('active');
            document.getElementById('modalElegirPrecios').classList.remove('active');
            document.getElementById('modalPreciosRapidos').classList.remove('active');
        }

        function mostrarErroresCreacionEvento(errores) {
            const lista = (errores || []).filter(Boolean);
            if (!lista.length) {
                alert('No se pudo crear el evento. Revisa los datos e intenta de nuevo.');
                return;
            }
            alert('No se pudo crear el evento:\n\n• ' + lista.join('\n• '));
        }

        function enviarFormularioEvento() {
            if (!validarImagenAntesDeEnviar()) return;

            desactivarProteccionSalida();
            mostrarPantallaProgresoCreando();

            const esGratis = document.getElementById('esGratuito').value === '1';
            const fd = construirFormDataEvento();

            fetch(window.location.pathname + window.location.search, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(r => r.text())
                .then(text => {
                    let data = null;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        data = null;
                    }

                    if (data && data.ok === true && data.id_evento) {
                        mostrarPantallaProgresoExito(esGratis);
                        return;
                    }

                    ocultarPantallaProgresoEvento();
                    enviandoEvento = false;

                    if (data && data.ok === false) {
                        mostrarErroresCreacionEvento(data.errores);
                        return;
                    }

                    if (text.includes('class="success-card"') && text.includes('¡Evento Creado!</h4>')) {
                        mostrarPantallaProgresoExito(esGratis);
                        return;
                    }

                    if (text.includes('No se pudo crear el evento:')) {
                        document.open();
                        document.write(text);
                        document.close();
                        return;
                    }

                    mostrarErroresCreacionEvento(['Respuesta inesperada del servidor.']);
                })
                .catch(() => {
                    enviandoEvento = false;
                    ocultarPantallaProgresoEvento();
                    alert('No se pudo crear el evento. Revisa tu conexión e intenta de nuevo.');
                });
        }

        function seleccionarTipo(esGratis) {
            if (esGratis) {
                if (enviandoEvento) return;
                enviandoEvento = true;
                document.getElementById('esGratuito').value = '1';
                document.getElementById('hPrecioGeneral').value = '0';
                document.getElementById('hPrecioNino').value = '0';
                document.getElementById('hPrecio3ra').value = '0';
                document.getElementById('hPrecioDisc').value = '0';
                document.getElementById('hUsaDiferenciados').value = '0';
                cerrarModalesFlujo();
                enviarFormularioEvento();
            } else {
                document.getElementById('esGratuito').value = '0';
                document.getElementById('modalTipoPrecio').classList.remove('active');
                abrirElegirModoPrecios();
            }
        }

        function confirmarPreciosYcrear() {
            if (enviandoEvento) return;

            cachearImagenEvento();

            const inpGeneral = document.getElementById('inpPrecioGeneral');
            const general = parseFloat(inpGeneral.value);
            if (isNaN(general) || general <= 0) {
                alert('El precio general debe ser mayor a cero para eventos de pago.');
                inpGeneral.focus();
                return;
            }

            const usaDiff = document.getElementById('switchMiniDiferenciados').checked;
            const nino = parseFloat(document.getElementById('inpPrecioNino').value);
            const tercera = parseFloat(document.getElementById('inpPrecio3ra').value);
            const disc = parseFloat(document.getElementById('inpPrecioDisc').value);

            document.getElementById('esGratuito').value = '0';
            document.getElementById('hPrecioGeneral').value = String(general);
            document.getElementById('hPrecioNino').value = String(!isNaN(nino) ? nino : general);
            document.getElementById('hPrecio3ra').value = String(!isNaN(tercera) ? tercera : general);
            document.getElementById('hPrecioDisc').value = String(!isNaN(disc) ? disc : general);
            document.getElementById('hUsaDiferenciados').value = usaDiff ? '1' : '0';

            if (!validarImagenAntesDeEnviar()) return;

            enviandoEvento = true;
            cerrarModalesFlujo();
            enviarFormularioEvento();
        }
    </script>
</body>

</html>