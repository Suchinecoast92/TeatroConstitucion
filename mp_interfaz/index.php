<?php
// 0. VERIFICACIÓN DE SEGURIDAD
session_start();

if (!isset($_SESSION['usuario_id'])) {
    die('<html><head><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></head><body style="display: flex; justify-content: center; align-items: center; height: 100vh; font-family: Arial; background: #f4f7f6; color: #e74c3c; font-size: 1.2em;"><div style="text-align: center;"><i class="bi bi-lock-fill" style="font-size: 3em;"></i><p>Acceso denegado. Debe iniciar sesión.</p></div></body></html>');
}

if ($_SESSION['usuario_rol'] !== 'admin' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado'])) {
    die('<html><head><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></head><body style="display: flex; justify-content: center; align-items: center; height: 100vh; font-family: Arial; background: #f4f7f6; color: #e74c3c; font-size: 1.2em;"><div style="text-align: center;"><i class="bi bi-shield-lock-fill" style="font-size: 3em;"></i><p>Solo Administradores pueden configurar el escenario.</p><button onclick="window.location.href=\'../index_empleado.php\'" style="margin-top: 20px; padding: 12px 24px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer;">Volver al Sistema</button></div></body></html>');
}

// 1. CONEXIÓN
include "../evt_interfaz/conexion.php";

$id_evento_seleccionado = null;
$evento_info = null;
$eventos_lista = [];
$categorias_palette = [];
$mapa_guardado = [];
$colores_por_id = [];

// 2. Cargar eventos
$res_eventos = $conn->query("SELECT id_evento, titulo, tipo, mapa_json FROM evento WHERE finalizado = 0 ORDER BY titulo ASC");
if ($res_eventos) {
    $eventos_lista = $res_eventos->fetch_all(MYSQLI_ASSOC);
}

// 3. Verificar selección
if (isset($_GET['id_evento']) && is_numeric($_GET['id_evento'])) {
    $id_evento_seleccionado = (int) $_GET['id_evento'];
    foreach ($eventos_lista as $evt) {
        if ($evt['id_evento'] == $id_evento_seleccionado) {
            $evento_info = $evt;
            break;
        }
    }

    if ($evento_info) {
        // 4. Cargar categorías
        $stmt_cat = $conn->prepare("SELECT * FROM categorias WHERE id_evento = ? ORDER BY precio ASC");
        $stmt_cat->bind_param("i", $id_evento_seleccionado);
        $stmt_cat->execute();
        $res_categorias = $stmt_cat->get_result();

        $id_categoria_general = null;
        $color_categoria_general = '#e2e8f0'; // Color base (gris claro visualmente mejor)

        if ($res_categorias) {
            $categorias_palette = $res_categorias->fetch_all(MYSQLI_ASSOC);
            foreach ($categorias_palette as $c) {
                $colores_por_id[$c['id_categoria']] = $c['color'];
                if (is_null($id_categoria_general) && strtolower($c['nombre_categoria']) === 'general') {
                    $id_categoria_general = (int) $c['id_categoria'];
                    $color_categoria_general = $c['color'];
                }
            }
        }
        if (is_null($id_categoria_general)) {
            $id_categoria_general = 0;
            $colores_por_id[0] = $color_categoria_general;
        }

        // 5. Cargar mapa
        if (!empty($evento_info['mapa_json'])) {
            $mapa_guardado = json_decode($evento_info['mapa_json'], true);
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de mapeado de asientos por categorías para eventos del teatro">
    <title>Mapeador de Asientos - Teatro</title>
    <link rel="icon" href="../crt_interfaz/imagenes_teatro/nat.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #1561f0;
            --primary-dark: #0d4fc4;
            --success-color: #32d74b;
            --danger-color: #ff453a;
            --warning-color: #ff9f0a;
            --info-color: #64d2ff;
            --bg-primary: #131313;
            --bg-secondary: #1c1c1e;
            --text-primary: #ffffff;
            --text-secondary: #86868b;
            --border-color: #3a3a3c;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
        }

        /* === BASE === */
        html,
        body {
            height: 100vh;
            overflow: hidden;
            margin: 0;
        }

        body {
            font-family: "Inter", system-ui, -apple-system, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .container-fluid {
            display: flex;
            height: 100%;
            padding: 20px;
            gap: 20px;
        }

        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
        }

        /* === MAPPER AREA === */
        .mapper-container {
            flex: 1;
            display: flex;
            overflow: hidden;
            position: relative;
        }

        .seat-map-wrapper {
            flex: 1;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 40px;
            overflow: auto;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: center;
        }

        .seat-map-content {
            min-width: min-content;
            transform-origin: top center;
            transition: transform 0.3s ease;
        }

        .screen {
            background: linear-gradient(135deg, var(--text-primary) 0%, #334155 100%);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: 700;
            letter-spacing: 2px;
            border-radius: var(--radius-md);
            margin-bottom: 50px;
            box-shadow: var(--shadow-md);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        /* === ASIENTOS === */
        .seat {
            width: 48px;
            height: 48px;
            background: #0066ff;
            color: #000000;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 2px;
            box-sizing: border-box;
            text-align: center;
            line-height: 1;
        }

        .seat:hover,
        .seat:focus {
            background: #0052cc;
            transform: scale(1.08);
            outline: none;
        }

        .seat:focus-visible {
            outline: 2px solid #ffffff;
            outline-offset: 2px;
        }

        .row-label:focus-visible {
            outline: 3px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Skip Link */
        .skip-link {
            position: absolute;
            top: -50px;
            left: 10px;
            background: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            z-index: 10000;
            text-decoration: none;
        }

        .skip-link:focus {
            top: 10px;
        }

        /* Live region */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }

        /* === PREVISUALIZACIÓN DE SELECCIÓN === */
        .seat.preview-selection {
            animation: pulseGlow 1s ease-in-out infinite;
            position: relative;
            z-index: 50;
        }

        .seat.preview-selection::before {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 12px;
            background: linear-gradient(45deg, var(--preview-color, #fbbf24), #f59e0b, var(--preview-color, #fbbf24));
            opacity: 0.6;
            z-index: -1;
            animation: rotateBorder 2s linear infinite;
        }

        @keyframes pulseGlow {

            0%,
            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.7);
            }

            50% {
                transform: scale(1.08);
                box-shadow: 0 0 20px 5px rgba(251, 191, 36, 0.5);
            }
        }

        @keyframes rotateBorder {
            0% {
                filter: hue-rotate(0deg);
            }

            100% {
                filter: hue-rotate(360deg);
            }
        }

        /* Barra de confirmación flotante */
        .selection-confirm-bar {
            position: fixed;
            bottom: -100px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 16px 24px;
            border-radius: var(--radius-lg);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            gap: 20px;
            z-index: 1000;
            transition: bottom 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .selection-confirm-bar.visible {
            bottom: 30px;
        }

        .selection-confirm-bar .info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .selection-confirm-bar .info .icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            animation: pulse 1.5s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        .selection-confirm-bar .info .text {
            display: flex;
            flex-direction: column;
        }

        .selection-confirm-bar .info .text .title {
            font-weight: 700;
            font-size: 1rem;
        }

        .selection-confirm-bar .info .text .subtitle {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        .selection-confirm-bar .actions {
            display: flex;
            gap: 10px;
        }

        .selection-confirm-bar .btn-confirm {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .selection-confirm-bar .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }

        .selection-confirm-bar .btn-cancel {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 12px 20px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .selection-confirm-bar .btn-cancel:hover {
            background: rgba(239, 68, 68, 0.8);
            border-color: transparent;
        }

        .selection-confirm-bar .preview-color-dot {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .seat-row-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 10px;
        }

        .seats-block {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pasillo {
            width: 32px;
        }

        .row-label {
            width: 48px;
            text-align: center;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text-secondary);
            border-radius: var(--radius-sm);
            padding: 8px 0;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
        }

        .row-label:hover {
            background-color: var(--bg-primary);
            color: var(--primary-color);
            transform: scale(1.05);
        }

        .seats-group {
            display: flex;
            gap: 8px;
        }

        .aisle {
            width: 32px;
        }

        .pasarela-container {
            position: relative;
            width: 100px;
            flex-shrink: 0;
        }

        .pasarela {
            width: 100px;
            background: linear-gradient(180deg, var(--text-primary) 0%, #334155 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
            position: absolute;
            top: 0;
            left: 0;
            box-shadow: var(--shadow-md);
        }

        .pasarela-text {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            font-weight: 700;
            letter-spacing: 6px;
            font-size: 1rem;
        }

        /* === SIDEBAR === */
        .sidebar {
            width: 320px;
            display: flex;
            flex-direction: column;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            flex-shrink: 0;
            position: relative;
        }

        .sidebar.collapsed {
            width: 0;
            opacity: 0;
            margin-left: -20px;
            pointer-events: none;
        }

        .palette-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(to right, var(--bg-primary), var(--bg-secondary));
        }

        .palette-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }

        .palette-footer {
            padding: 20px;
            border-top: 1px solid var(--border-color);
            background: var(--bg-primary);
        }

        .palette-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            margin-bottom: 8px;
            border: 2px solid transparent;
            background: #2b2b2b;
            transition: all 0.2s;
            color: var(--text-primary);
        }

        .palette-item:hover {
            border-color: var(--primary-color);
            transform: translateX(5px);
            background: #3a3a3c;
        }

        .palette-item.active {
            background: rgba(21, 97, 240, 0.15);
            border-color: var(--primary-color);
            box-shadow: var(--shadow-sm);
        }

        .color-dot {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            margin-right: 12px;
            box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.1);
        }

        .palette-info {
            flex: 1;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-primary);
        }

        /* CONTROLES */
        .toggle-sidebar-btn {
            position: absolute;
            top: 25px;
            right: 25px;
            z-index: 100;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: none;
            background: var(--primary-color);
            color: white;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 1.3rem;
        }

        .toggle-sidebar-btn:hover {
            transform: scale(1.1);
            background: var(--primary-dark);
        }

        .btn {
            border-radius: var(--radius-sm);
            padding: 12px;
            font-weight: 600;
            border: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .form-select {
            padding: 12px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            background-color: var(--bg-primary);
            color: #fff;
        }

        .form-select option {
            background-color: #1c1c1e;
            color: #fff;
        }

        .form-select option:checked {
            background-color: var(--primary-color);
        }

        /* HELPERS */
        #loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(3px);
        }

        .shortcuts-info {
            background: rgba(21, 97, 240, 0.15);
            border: 1px solid rgba(21, 97, 240, 0.3);
            color: #64d2ff;
            padding: 15px;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
        }

        .shortcuts-info li {
            margin-bottom: 4px;
        }
    </style>
</head>

<body>
    <a href="#seatMapContent" class="skip-link">Ir al mapa de asientos</a>
    <div id="liveAnnouncer" class="sr-only" aria-live="polite" aria-atomic="true"></div>

    <div id="loading-overlay" class="d-none" role="status" aria-label="Cargando">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" aria-hidden="true"></div>
        <span class="sr-only">Cargando, por favor espere...</span>
    </div>

    <!-- Barra de confirmación para selección de rango -->
    <div class="selection-confirm-bar" id="selectionConfirmBar" role="alertdialog" aria-labelledby="confirmBarTitle"
        aria-describedby="confirmBarDesc">
        <div class="info">
            <div class="icon">
                <i class="bi bi-check2-all" aria-hidden="true"></i>
            </div>
            <div class="text">
                <span class="title" id="confirmBarTitle">Previsualización de selección</span>
                <span class="subtitle" id="confirmBarDesc"><span id="seatCount">0</span> asientos serán pintados</span>
            </div>
        </div>
        <div class="actions">
            <div class="preview-color-dot" id="previewColorDot" title="Color a aplicar"></div>
            <button type="button" class="btn-confirm" id="btnConfirmSelection" aria-label="Confirmar selección">
                <i class="bi bi-check-lg" aria-hidden="true"></i> Aplicar
            </button>
            <button type="button" class="btn-cancel" id="btnCancelSelection" aria-label="Cancelar selección">
                <i class="bi bi-x-lg" aria-hidden="true"></i> Cancelar
            </button>
        </div>
    </div>

    <div class="container-fluid" role="main">

        <button class="toggle-sidebar-btn" id="btnToggleSidebar" title="Alternar Panel"
            aria-label="Alternar panel lateral" aria-expanded="true" aria-controls="sidebarPanel">
            <i class="bi bi-layout-sidebar-inset-reverse" aria-hidden="true"></i>
        </button>

        <div class="mapper-container">
            <?php if ($evento_info): ?>
                <div class="seat-map-wrapper">
                    <div class="seat-map-content" id="seatMapContent">
                        <div class="screen" role="img"
                            aria-label="<?= ($evento_info['tipo'] == 1) ? 'Escenario Principal' : 'Pasarela 360 grados' ?>">
                            <?= ($evento_info['tipo'] == 1) ? 'ESCENARIO PRINCIPAL' : 'PASARELA 360°' ?></div>

                        <div style="position: relative; margin: 0 auto; width: fit-content;">

                            <?php if ($evento_info['tipo'] == 2): /* PASARELA */ ?>
                                <div style="position: relative; display: flex; flex-direction: column;">
                                    <?php for ($f = 1; $f <= 10; $f++):
                                        $nom = "PB" . $f;
                                        $n = 1; ?>
                                        <div class="seat-row-wrapper" role="row">
                                            <div class="row-label" data-row="<?= $nom ?>" tabindex="0" role="button"
                                                aria-label="Seleccionar fila <?= $nom ?> completa"><?= $nom ?></div>
                                            <div class="seats-block" role="group" aria-label="Bloque izquierdo fila <?= $nom ?>">
                                                <?php for ($i = 1; $i <= 6; $i++):
                                                    $na = $nom . '-' . $n++;
                                                    $ic = $mapa_guardado[$na] ?? 0; ?>
                                                    <div class="seat" style="background-color:<?= $colores_por_id[$ic] ?? '#e2e8f0' ?>"
                                                        data-id="<?= $na ?>" data-cat="<?= $ic ?>" tabindex="0" role="button"
                                                        aria-label="Asiento <?= $na ?>"><?= $na ?></div>
                                                <?php endfor; ?>
                                            </div>
                                            <div style="width: 140px; flex-shrink:0;"></div>
                                            <div class="seats-block" role="group" aria-label="Bloque derecho fila <?= $nom ?>">
                                                <?php for ($i = 1; $i <= 6; $i++):
                                                    $na = $nom . '-' . $n++;
                                                    $ic = $mapa_guardado[$na] ?? 0; ?>
                                                    <div class="seat" style="background-color:<?= $colores_por_id[$ic] ?? '#e2e8f0' ?>"
                                                        data-id="<?= $na ?>" data-cat="<?= $ic ?>" tabindex="0" role="button"
                                                        aria-label="Asiento <?= $na ?>"><?= $na ?></div>
                                                <?php endfor; ?>
                                            </div>
                                            <div class="row-label" data-row="<?= $nom ?>" tabindex="0" role="button"
                                                aria-label="Seleccionar fila <?= $nom ?> completa"><?= $nom ?></div>
                                        </div>
                                    <?php endfor; ?>

                                    <!-- Pasarela posicionada absolutamente sobre todas las filas -->
                                    <div class="pasarela"
                                        style="position: absolute; width: 100px; height: <?= (48 + 10) * 10 ?>px; top: 0; left: 50%; transform: translateX(-50%); background: linear-gradient(180deg, var(--text-primary) 0%, #334155 100%); color: #fff; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-md); box-shadow: var(--shadow-md);">
                                        <span class="pasarela-text">PASARELA</span>
                                    </div>
                                </div>
                                <hr style="margin-top: 20px; margin-bottom: 20px; border-width: 2px;">
                            <?php endif; ?>

                            <?php foreach (range('A', 'O') as $fila):
                                $n = 1; ?>
                                <div class="seat-row-wrapper" role="row">
                                    <div class="row-label" data-row="<?= $fila ?>" tabindex="0" role="button"
                                        aria-label="Seleccionar fila <?= $fila ?> completa"><?= $fila ?></div>
                                    <div class="seats-block">
                                        <?php
                                        for ($i = 0; $i < 6; $i++):
                                            $na = $fila . $n++;
                                            $ic = $mapa_guardado[$na] ?? 0;
                                            echo "<div class='seat' style='background-color:" . ($colores_por_id[$ic] ?? '#e2e8f0') . "' data-id='$na' data-cat='$ic' tabindex='0' role='button' aria-label='Asiento $na'>$na</div>";
                                        endfor;
                                        echo '<div class="aisle" aria-hidden="true"></div>';
                                        for ($i = 0; $i < 14; $i++):
                                            $na = $fila . $n++;
                                            $ic = $mapa_guardado[$na] ?? 0;
                                            echo "<div class='seat' style='background-color:" . ($colores_por_id[$ic] ?? '#e2e8f0') . "' data-id='$na' data-cat='$ic' tabindex='0' role='button' aria-label='Asiento $na'>$na</div>";
                                        endfor;
                                        echo '<div class="aisle" aria-hidden="true"></div>';
                                        for ($i = 0; $i < 6; $i++):
                                            $na = $fila . $n++;
                                            $ic = $mapa_guardado[$na] ?? 0;
                                            echo "<div class='seat' style='background-color:" . ($colores_por_id[$ic] ?? '#e2e8f0') . "' data-id='$na' data-cat='$ic' tabindex='0' role='button' aria-label='Asiento $na'>$na</div>";
                                        endfor;
                                        ?>
                                    </div>
                                    <div class="row-label" data-row="<?= $fila ?>" tabindex="0" role="button"
                                        aria-label="Seleccionar fila <?= $fila ?> completa"><?= $fila ?></div>
                                </div>
                            <?php endforeach; ?>

                            <div class="seat-row-wrapper" role="row">
                                <div class="row-label" data-row="P" data-exact="true" tabindex="0" role="button"
                                    aria-label="Seleccionar fila P completa">P</div>
                                <div class="seats-block" role="group" aria-label="Fila P">
                                    <?php $n = 1;
                                    for ($i = 0; $i < 30; $i++):
                                        $na = 'P' . $n++;
                                        $ic = $mapa_guardado[$na] ?? 0; ?>
                                        <div class="seat" style="background-color:<?= $colores_por_id[$ic] ?? '#e2e8f0' ?>"
                                            data-id="<?= $na ?>" data-cat="<?= $ic ?>" tabindex="0" role="button"
                                            aria-label="Asiento <?= $na ?>"><?= $na ?></div>
                                    <?php endfor; ?>
                                </div>
                                <div class="row-label" data-row="P" data-exact="true" tabindex="0" role="button"
                                    aria-label="Seleccionar fila P completa">P</div>
                            </div>

                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div
                    class="d-flex align-items-center justify-content-center flex-grow-1 text-muted bg-white rounded-4 shadow-sm border">
                    <div class="text-center">
                        <i class="bi bi-arrow-left-square fs-1 text-primary mb-3"></i>
                        <h3>Selecciona un evento para comenzar el mapeo</h3>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <aside class="sidebar card" id="sidebarPanel" role="complementary" aria-label="Panel de herramientas">
            <div class="palette-header">
                <h1 class="m-0 fw-bold text-primary" style="font-size: 1.5rem;"><i class="bi bi-palette2 me-2"
                        aria-hidden="true"></i>Mapeador</h1>
            </div>

            <div class="palette-body">
                <div class="mb-4">
                    <label class="form-label fw-bold small text-uppercase text-secondary ls-1">Evento Activo</label>
                    <form method="GET">
                        <select name="id_evento" class="form-select fw-bold" onchange="this.form.submit()">
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($eventos_lista as $e): ?>
                                <option value="<?= $e['id_evento'] ?>"
                                    <?= ($id_evento_seleccionado == $e['id_evento']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($e['titulo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <?php if ($evento_info): ?>
                    <div class="shortcuts-info mb-4">
                        <div class="fw-bold mb-2"><i class="bi bi-keyboard me-2"></i>Atajos de Teclado</div>
                        <ul class="m-0 ps-3">
                            <li><strong>Click:</strong> Pintar un asiento.</li>
                            <li><strong>Ctrl + Click:</strong> Previsualizar rango.</li>
                            <li><strong>Enter:</strong> Confirmar selección de rango.</li>
                            <li><strong>Escape:</strong> Cancelar selección.</li>
                            <li><strong>Click en Letra:</strong> Pintar fila completa.</li>
                        </ul>
                    </div>

                    <h2 class="fw-bold small text-uppercase text-secondary mb-3 ls-1" style="font-size: 0.875rem;">
                        Categorías</h2>
                    <div id="paletteContainer" role="listbox" aria-label="Seleccionar categoría de asiento">
                        <div class="palette-item" data-color="#e2e8f0" data-cat-id="0" role="option" tabindex="0"
                            aria-selected="false">
                            <div class="color-dot" style="background:#e2e8f0; border: 2px solid #cbd5e1;"
                                aria-hidden="true"></div>
                            <div class="palette-info text-secondary">Sin Asignar / Borrar</div>
                        </div>

                        <?php foreach ($categorias_palette as $c):
                            $isGeneral = strtolower($c['nombre_categoria']) === 'general'; ?>
                            <div class="palette-item <?= $isGeneral ? 'active' : '' ?>"
                                data-color="<?= htmlspecialchars($c['color']) ?>" data-cat-id="<?= $c['id_categoria'] ?>"
                                role="option" tabindex="0" aria-selected="<?= $isGeneral ? 'true' : 'false' ?>">
                                <div class="color-dot" style="background:<?= htmlspecialchars($c['color']) ?>"
                                    aria-hidden="true"></div>
                                <div class="palette-info"><?= htmlspecialchars($c['nombre_categoria']) ?></div>
                                <span class="badge bg-light text-dark border">$<?= number_format($c['precio'], 0) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($evento_info): ?>
                <div class="palette-footer">
                    <button type="button" class="btn btn-success w-100 mb-2" data-bs-toggle="modal"
                        data-bs-target="#modalNuevaCategoria">
                        <i class="bi bi-plus-lg"></i> Nueva Categoría
                    </button>
                    <button id="btnGuardar" class="btn btn-primary w-100 py-3 fs-6">
                        <i class="bi bi-cloud-arrow-up-fill me-2"></i> Guardar Mapa
                    </button>
                </div>
            <?php endif; ?>
        </aside>
    </div>

    <div class="modal fade" id="modalNuevaCategoria" tabindex="-1" role="dialog"
        aria-labelledby="modalNuevaCategoriaLabel" aria-modal="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h2 class="modal-title fw-bold" id="modalNuevaCategoriaLabel" style="font-size: 1.25rem;"><i
                            class="bi bi-bookmark-plus-fill me-2 text-success" aria-hidden="true"></i>Nueva Categoría
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar modal"></button>
                </div>
                <div class="modal-body pt-4">
                    <form id="formNuevaCategoria">
                        <input type="hidden" name="id_evento" value="<?= $id_evento_seleccionado ?>">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="catNombre" name="nombre" required
                                placeholder="Nombre">
                            <label for="catNombre">Nombre de la Categoría</label>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="catPrecio" name="precio" step="0.01"
                                        min="0" required placeholder="0.00">
                                    <label for="catPrecio">Precio ($)</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="h-100 border rounded-3 p-1 bg-light">
                                    <input type="color" class="form-control form-control-color w-100 h-100 border-0"
                                        name="color" value="#2563eb" title="Elige color">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-light text-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success px-4" form="formNuevaCategoria">Guardar
                        Categoría</button>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="current_event_id" value="<?= $id_evento_seleccionado ?>">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../vnt_interfaz/js/teatro-sync.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {

            // --- ESTADO GLOBAL ---
            let activeColor = '<?= $color_categoria_general ?? "#e2e8f0" ?>';
            let activeCatId = <?= $id_categoria_general ?? 0 ?>;
            let lastClickedSeatIndex = null;

            // Cache de elementos
            const allSeats = Array.from(document.querySelectorAll('.seat'));
            const overlay = document.getElementById('loading-overlay');
            const sidebar = document.getElementById('sidebarPanel');
            const seatMapWrapper = document.querySelector('.seat-map-wrapper');
            const seatMapContent = document.getElementById('seatMapContent');

            // Función para anuncios de accesibilidad
            function announce(message) {
                const announcer = document.getElementById('liveAnnouncer');
                if (announcer) {
                    announcer.textContent = message;
                    setTimeout(() => announcer.textContent = '', 1000);
                }
            }

            // --- 1. GESTIÓN DE PALETA (CON ACCESIBILIDAD) ---
            document.querySelectorAll('.palette-item').forEach(item => {
                const selectPalette = () => {
                    document.querySelectorAll('.palette-item').forEach(i => {
                        i.classList.remove('active');
                        i.setAttribute('aria-selected', 'false');
                    });
                    item.classList.add('active');
                    item.setAttribute('aria-selected', 'true');
                    activeColor = item.dataset.color;
                    activeCatId = item.dataset.catId;
                    const catName = item.querySelector('.palette-info')?.textContent || 'categoría';
                    announce(`Categoría seleccionada: ${catName}`);
                };
                item.addEventListener('click', selectPalette);
                item.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        selectPalette();
                    }
                });
            });

            // --- 2. INTERACCIÓN AVANZADA CON ASIENTOS (CON PREVISUALIZACIÓN) ---

            // Estado de selección de rango
            let pendingSelection = [];
            let pendingColor = null;
            let pendingCatId = null;

            // Elementos de la barra de confirmación
            const confirmBar = document.getElementById('selectionConfirmBar');
            const seatCountEl = document.getElementById('seatCount');
            const previewColorDot = document.getElementById('previewColorDot');
            const btnConfirm = document.getElementById('btnConfirmSelection');
            const btnCancel = document.getElementById('btnCancelSelection');

            // Mostrar barra de confirmación
            function showConfirmBar(count, color) {
                if (confirmBar) {
                    seatCountEl.textContent = count;
                    previewColorDot.style.background = color;
                    confirmBar.classList.add('visible');
                    announce(`Previsualización: ${count} asientos seleccionados. Presiona Enter para confirmar o Escape para cancelar.`);
                }
            }

            // Ocultar barra de confirmación
            function hideConfirmBar() {
                if (confirmBar) {
                    confirmBar.classList.remove('visible');
                }
            }

            // Limpiar previsualización
            function clearPreview() {
                pendingSelection.forEach(seat => {
                    seat.classList.remove('preview-selection');
                    seat.style.removeProperty('--preview-color');
                });
                pendingSelection = [];
                pendingColor = null;
                pendingCatId = null;
                hideConfirmBar();
            }

            // Mostrar previsualización de rango
            function showRangePreview(startIdx, endIdx) {
                clearPreview(); // Limpiar previsualización anterior

                const start = Math.min(startIdx, endIdx);
                const end = Math.max(startIdx, endIdx);

                pendingColor = activeColor;
                pendingCatId = activeCatId;

                for (let i = start; i <= end; i++) {
                    const seat = allSeats[i];
                    if (seat.dataset.cat != activeCatId) { // Solo previsualizar los que cambiarán
                        seat.classList.add('preview-selection');
                        seat.style.setProperty('--preview-color', activeColor);
                        pendingSelection.push(seat);
                    }
                }

                if (pendingSelection.length > 0) {
                    showConfirmBar(pendingSelection.length, activeColor);
                } else {
                    announce('Todos los asientos ya tienen esta categoría');
                }
            }

            // Confirmar selección
            function confirmSelection() {
                if (pendingSelection.length === 0) return;

                const count = pendingSelection.length;
                const colorToApply = pendingColor;
                const catToApply = pendingCatId;

                // Primero limpiar la previsualización
                pendingSelection.forEach(seat => {
                    seat.classList.remove('preview-selection');
                    seat.style.removeProperty('--preview-color');
                });

                // Luego aplicar el color real
                pendingSelection.forEach(seat => {
                    seat.style.backgroundColor = colorToApply;
                    seat.style.color = getContrastColor(colorToApply);
                    seat.dataset.cat = catToApply;
                    // Animación de confirmación
                    seat.animate([
                        { transform: 'scale(1)', boxShadow: '0 0 0 0 rgba(16, 185, 129, 0.7)' },
                        { transform: 'scale(1.15)', boxShadow: '0 0 20px 5px rgba(16, 185, 129, 0.5)' },
                        { transform: 'scale(1)', boxShadow: '0 0 0 0 rgba(16, 185, 129, 0)' }
                    ], { duration: 400, easing: 'cubic-bezier(0.34, 1.56, 0.64, 1)' });
                });

                announce(`¡${count} asientos pintados exitosamente!`);
                pendingSelection = [];
                pendingColor = null;
                pendingCatId = null;
                hideConfirmBar();
            }

            // Cancelar selección
            function cancelSelection() {
                if (pendingSelection.length > 0) {
                    announce('Selección cancelada');
                }
                clearPreview();
            }

            // Event listeners para botones de confirmación
            btnConfirm?.addEventListener('click', confirmSelection);
            btnCancel?.addEventListener('click', cancelSelection);

            // Teclas globales para confirmar/cancelar
            document.addEventListener('keydown', (e) => {
                if (pendingSelection.length > 0) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        confirmSelection();
                    } else if (e.key === 'Escape') {
                        e.preventDefault();
                        cancelSelection();
                    }
                }
            });

            allSeats.forEach((seat, index) => {
                const handleSeatInteraction = (e) => {
                    // Si hay una selección pendiente y se hace click normal, cancelarla
                    if (pendingSelection.length > 0 && !e.ctrlKey && !e.shiftKey) {
                        clearPreview();
                    }

                    // RANGO CON PREVISUALIZACIÓN: Si presiona Ctrl o Shift y ya había hecho click antes
                    if ((e.ctrlKey || e.shiftKey) && lastClickedSeatIndex !== null) {
                        showRangePreview(lastClickedSeatIndex, index);
                    } else {
                        // CLICK NORMAL - pintar inmediatamente
                        paintSeat(seat);
                        announce(`Asiento ${seat.dataset.id} pintado`);
                    }
                    lastClickedSeatIndex = index;
                };
                seat.addEventListener('click', handleSeatInteraction);
                seat.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        // No prevenir default si hay selección pendiente (para que Enter confirme)
                        if (pendingSelection.length === 0) {
                            e.preventDefault();
                            handleSeatInteraction(e);
                        }
                    }
                });
            });

            // Función para calcular contraste automático
            function getContrastColor(hexcolor) {
                const hex = hexcolor.replace('#', '');
                const r = parseInt(hex.substr(0, 2), 16);
                const g = parseInt(hex.substr(2, 2), 16);
                const b = parseInt(hex.substr(4, 2), 16);
                const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                return luminance > 0.5 ? '#1e293b' : '#ffffff';
            }

            function paintSeat(seatElement) {
                if (seatElement.dataset.cat == activeCatId) return; // Evitar repintar lo mismo
                seatElement.style.backgroundColor = activeColor;
                seatElement.style.color = getContrastColor(activeColor); // Contraste automático
                seatElement.dataset.cat = activeCatId;
                // Animación visual "pop"
                seatElement.animate([
                    { transform: 'scale(1)' }, { transform: 'scale(1.4)' }, { transform: 'scale(1)' }
                ], { duration: 300, easing: 'cubic-bezier(0.34, 1.56, 0.64, 1)' });
            }

            // --- 3. PINTADO DE FILA COMPLETA (CORREGIDO) ---
            document.querySelectorAll('.row-label').forEach(label => {
                const handleRowSelect = () => {
                    const rowName = label.dataset.row;
                    const isExact = label.dataset.exact === 'true';
                    // Selector corregido: para filas como 'P', solo seleccionar P1-P30, no PB1, etc.
                    const selector = isExact
                        ? `.seat[data-id^="${rowName}"][data-id$=""]:not([data-id*="-"])`
                        : `.seat[data-id^="${rowName}"]`;

                    // Filtrar correctamente los asientos de esta fila
                    const seatsInRow = allSeats.filter(s => {
                        const id = s.dataset.id;
                        if (isExact) {
                            // Para fila P: solo P1, P2... P30 (sin guión)
                            return id.match(new RegExp(`^${rowName}\\d+$`));
                        }
                        return id.startsWith(rowName);
                    });

                    seatsInRow.forEach(s => paintSeat(s));
                    announce(`Fila ${rowName} pintada con ${seatsInRow.length} asientos`);
                };
                label.addEventListener('click', handleRowSelect);
                label.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        handleRowSelect();
                    }
                });
            });

            // --- 4. GUARDAR MAPA (AJAX) ---
            const btnGuardar = document.getElementById('btnGuardar');
            if (btnGuardar) {
                btnGuardar.addEventListener('click', async () => {
                    overlay.classList.remove('d-none'); // Mostrar spinner full screen
                    const eventId = document.getElementById('current_event_id').value;
                    // Preparar datos exactamente como el backend los espera
                    const mapaArray = allSeats.map(s => ({ asiento: s.dataset.id, cat_id: s.dataset.cat }));

                    try {
                        const res = await fetch('ajax_guardar_mapa.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id_evento: eventId, mapa: mapaArray })
                        });
                        const data = await res.json();

                        // Notificar cambio si hay id_evento
                        if (data.notify_change && data.id_evento) {
                            localStorage.setItem('mapa_actualizado', JSON.stringify({
                                id_evento: data.id_evento,
                                timestamp: Date.now()
                            }));
                        }

                        showToast(data.status === 'success' ? 'Mapa guardado exitosamente' : 'Error: ' + data.message,
                            data.status === 'success' ? 'success' : 'danger');
                    } catch (e) {
                        showToast('Error de conexión al guardar.', 'danger');
                    } finally {
                        overlay.classList.add('d-none');
                    }
                });
            }

            // --- 5. NUEVA CATEGORÍA (AJAX) - Guarda el mapa primero ---
            const formCat = document.getElementById('formNuevaCategoria');
            const modalCat = new bootstrap.Modal(document.getElementById('modalNuevaCategoria'));

            // Función auxiliar para guardar el mapa (reutilizable)
            async function saveMapData() {
                const eventId = document.getElementById('current_event_id').value;
                if (!eventId) return { status: 'error', message: 'No hay evento seleccionado' };

                const mapaArray = allSeats.map(s => ({ asiento: s.dataset.id, cat_id: s.dataset.cat }));

                try {
                    const res = await fetch('ajax_guardar_mapa.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id_evento: eventId, mapa: mapaArray })
                    });
                    return await res.json();
                } catch (e) {
                    return { status: 'error', message: 'Error de conexión' };
                }
            }

            if (formCat) {
                formCat.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    overlay.classList.remove('d-none');

                    try {
                        // 1. PRIMERO guardar el mapa actual para no perder los asientos pintados
                        showToast('Guardando mapa actual...', 'info');
                        const saveResult = await saveMapData();

                        if (saveResult.status !== 'success') {
                            showToast('Advertencia: No se pudo guardar el mapa. ' + (saveResult.message || ''), 'warning');
                            // Continuar de todos modos con la creación de categoría
                        }

                        // 2. LUEGO crear la nueva categoría
                        const res = await fetch('ajax_guardar_categoria.php', { method: 'POST', body: new FormData(formCat) });
                        const data = await res.json();

                        if (data.status === 'success') {
                            showToast('Categoría creada. Recargando página...', 'success');
                            setTimeout(() => window.location.reload(), 500); // Pequeño delay para que se vea el toast
                        } else {
                            showToast('Error: ' + data.message, 'danger');
                            overlay.classList.add('d-none');
                        }
                    } catch (e) {
                        showToast('Error de conexión.', 'danger');
                        overlay.classList.add('d-none');
                    }
                });
            }

            // --- UI: TOGGLE SIDEBAR & ESCALADO (CON ACCESIBILIDAD) ---
            const btnToggle = document.getElementById('btnToggleSidebar');
            btnToggle?.addEventListener('click', () => {
                const isCollapsed = sidebar.classList.toggle('collapsed');
                btnToggle.setAttribute('aria-expanded', !isCollapsed);
                announce(isCollapsed ? 'Panel lateral oculto' : 'Panel lateral visible');
                setTimeout(escalarMapa, 350); // Reajustar mapa tras la animación
            });

            function escalarMapa() {
                if (!seatMapWrapper || !seatMapContent) return;
                // Calcular escala para que el mapa quepa siempre en el contenedor
                const scale = Math.min((seatMapWrapper.clientWidth - 80) / seatMapContent.scrollWidth, 1);
                seatMapContent.style.transform = `scale(${Math.max(scale, 0.4)})`; // Limitar zoom mínimo
            }
            window.addEventListener('resize', escalarMapa);
            if (seatMapContent) setTimeout(escalarMapa, 100); // Escalar al inicio

            // --- HELPER: NOTIFICACIONES TOAST ---
            function showToast(msg, type = 'primary') {
                const toast = document.createElement('div');
                toast.className = `position-fixed bottom-0 end-0 m-4 p-3 text-white rounded-3 shadow-lg d-flex align-items-center`;
                toast.style.zIndex = 10000;
                toast.style.background = `var(--${type}-color)`; // Usar colores del root
                toast.innerHTML = `<i class="bi bi-info-circle-fill fs-5 me-3"></i><div class="fw-semibold">${msg}</div>`;
                document.body.appendChild(toast);
                // Animación de entrada
                toast.animate([{ opacity: 0, transform: 'translateY(20px)' }, { opacity: 1, transform: 'translateY(0)' }], { duration: 300 });
                setTimeout(() => {
                    // Animación de salida y remover
                    toast.animate([{ opacity: 1 }, { opacity: 0, transform: 'translateY(-20px)' }], { duration: 300 })
                        .onfinish = () => toast.remove();
                }, 3000);
            }

            // --- ESCUCHAR CAMBIOS EN CATEGORÍAS ---
            window.addEventListener('storage', (e) => {
                if (e.key === 'categorias_actualizadas' && e.newValue) {
                    try {
                        const data = JSON.parse(e.newValue);
                        const eventoActual = document.getElementById('current_event_id')?.value;

                        console.log('Categorías actualizadas:', data.id_evento, 'Evento actual:', eventoActual);

                        if (data.id_evento == eventoActual && eventoActual) {
                            showToast('Las categorías han sido actualizadas, recargando...', 'info');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        }
                    } catch (error) {
                        console.error('Error al procesar categorias_actualizadas:', error);
                    }
                }
            });
        });
    </script>

    <!-- Botón de regreso a Ajustes -->


</body>

</html>