<?php
include "../conexion.php";
require_once __DIR__ . '/../config/ventas.php';
require_once __DIR__ . '/../includes/catalogo_boletos_helper.php';
$horasVentaAbierta = (int) HORAS_CIERRE_VENTAS_POST_FUNCION;

$id_evento = isset($_GET['id_evento']) ? (int) $_GET['id_evento'] : 0;
$evento_info = null;
$mapa_guardado = [];
$eventos_disponibles = [];
$funciones_evento = [];
$categorias_evento = [];

if ($id_evento > 0) {
    $res = $conn->query("SELECT titulo, tipo, mapa_json, imagen FROM evento WHERE id_evento = $id_evento");
    if ($res && $fila = $res->fetch_assoc()) {
        $evento_info = $fila;
        $mapa_guardado = json_decode($fila['mapa_json'] ?? '', true) ?: [];
    }
    // Se permite vender hasta 24 horas después de iniciada la función (comparación en SQL/NOW()).
    $stmt_fun = $conn->prepare("SELECT id_funcion, fecha_hora FROM funciones WHERE id_evento = ? AND fecha_hora > (NOW() - INTERVAL {$horasVentaAbierta} HOUR) AND estado = 0 ORDER BY fecha_hora ASC");
    $stmt_fun->bind_param("i", $id_evento);
    $stmt_fun->execute();
    $res_fun = $stmt_fun->get_result();
    while ($f = $res_fun->fetch_assoc()) {
        $funciones_evento[] = $f;
    }
    $stmt_fun->close();

    $stmt_cat = $conn->prepare("SELECT id_categoria, nombre_categoria, color, precio FROM categorias WHERE id_evento = ? ORDER BY precio DESC");
    $stmt_cat->bind_param("i", $id_evento);
    $stmt_cat->execute();
    $res_cat = $stmt_cat->get_result();
    while ($c = $res_cat->fetch_assoc()) {
        $categorias_evento[] = $c;
    }
    $stmt_cat->close();

    $evento_es_gratuito = evento_es_gratuito($conn, $id_evento);
} else {
    $evento_es_gratuito = false;
}

if (!$evento_info) {
    // Se permite vender hasta 24 horas después de iniciada la función (comparación en SQL/NOW()).
    $sql = "SELECT e.id_evento, e.titulo, e.descripcion, e.imagen, e.tipo, e.mapa_json,
                   f.id_funcion, f.fecha_hora, f.estado
            FROM evento e
            LEFT JOIN funciones f ON e.id_evento = f.id_evento AND f.fecha_hora > (NOW() - INTERVAL {$horasVentaAbierta} HOUR)
            WHERE e.finalizado = 0
            ORDER BY e.titulo ASC, f.fecha_hora ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    // Obtener boletos vendidos por evento/función
    $vendidos_por_funcion = [];
    $sqlVendidos = "SELECT id_evento, id_funcion, COUNT(*) AS vendidos FROM boletos WHERE estatus = 1 GROUP BY id_evento, id_funcion";
    $resVendidos = $conn->query($sqlVendidos);
    if ($resVendidos) {
        while ($rowV = $resVendidos->fetch_assoc()) {
            $idEv = (int) $rowV['id_evento'];
            $idFun = (int) $rowV['id_funcion'];
            $vendidos_por_funcion[$idEv][$idFun] = (int) $rowV['vendidos'];
        }
    }

    while ($row = $result->fetch_assoc()) {
        $id = $row['id_evento'];
        if (!isset($eventos_disponibles[$id])) {
            // Calcular total de asientos del mapa
            $total_asientos = 0;
            if (!empty($row['mapa_json'])) {
                $mapa = json_decode($row['mapa_json'], true);
                if (is_array($mapa)) {
                    $total_asientos = count($mapa);
                }
            }
            $eventos_disponibles[$id] = [
                'titulo' => $row['titulo'],
                'descripcion' => $row['descripcion'],
                'imagen' => $row['imagen'],
                'tipo' => $row['tipo'],
                'total_asientos' => $total_asientos,
                'funciones' => []
            ];
        }
        if ($row['id_funcion'] && $row['estado'] == 0) {
            $id_fun = (int) $row['id_funcion'];
            $vendidos = $vendidos_por_funcion[$id][$id_fun] ?? 0;
            $total = $eventos_disponibles[$id]['total_asientos'];
            $disponibles = max(0, $total - $vendidos);

            $eventos_disponibles[$id]['funciones'][] = [
                'id_funcion' => $row['id_funcion'],
                'fecha_hora' => $row['fecha_hora'],
                'vendidos' => $vendidos,
                'disponibles' => $disponibles
            ];
        }
    }
    $stmt->close();
    
    // Filtrar eventos sin funciones disponibles y ordenar
    // Solo mostrar eventos que tienen al menos una función
    $eventos_disponibles = array_filter($eventos_disponibles, function($evento) {
        return count($evento['funciones']) > 0;
    });
    
    // Ordenar por título alfabéticamente
    uasort($eventos_disponibles, function($a, $b) {
        return strcmp($a['titulo'], $b['titulo']);
    });
}

$colores_categoria = [];
foreach ($categorias_evento as $cat) {
    $colores_categoria[$cat['id_categoria']] = $cat['color'];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teatro - Pantalla Cliente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #0a1628;
            --navy-mid: #0f1f38;
            --navy-light: #162844;
            --text-on-navy: #ffffff;

            --yellow: #ffd600;
            --yellow-dark: #e6c200;
            --text-on-yellow: #000000;

            --white: #ffffff;
            --black: #000000;

            --primary-color: var(--navy-light);
            --primary-dark: var(--navy);
            --success-color: #34d399;
            --accent-color: #ffffff;
            --danger-color: #f87171;
            --warning-color: #fbbf24;
            --bg-primary: #0a0e14;
            --bg-secondary: var(--navy-mid);
            --bg-tertiary: var(--navy-light);
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.7);
            --border-color: rgba(255, 255, 255, 0.15);
            --cart-bg: var(--navy);
            --cart-surface: var(--navy-mid);
            --cart-border: var(--navy-light);
            --cart-text: #ffffff;
            --cart-muted: rgba(255, 255, 255, 0.6);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Lato', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            overflow: hidden;
        }

        /* ===== OVERLAY DE GRACIAS ===== */
        .overlay-gracias {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--navy);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.5s ease;
        }

        .overlay-gracias.active {
            opacity: 1;
            visibility: visible;
        }

        .gracias-icon {
            font-size: 8rem;
            color: white;
            animation: bounceIn 0.8s ease;
        }

        .gracias-titulo {
            font-size: 3.5rem;
            font-weight: 800;
            color: white;
            margin: 20px 0 10px;
            animation: fadeInUp 0.8s ease 0.3s both;
        }

        .gracias-mensaje {
            font-size: 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            animation: fadeInUp 0.8s ease 0.5s both;
        }

        .gracias-total {
            font-size: 4rem;
            font-weight: 800;
            color: #ffffff;
            margin-top: 30px;
            animation: fadeInUp 0.8s ease 0.7s both;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }

        .gracias-disfrute {
            font-size: 2rem;
            color: rgba(255, 255, 255, 0.95);
            margin-top: 30px;
            animation: fadeInUp 0.8s ease 0.9s both;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        /* Barra de progreso para transición automática */
        .gracias-progress {
            width: 300px;
            height: 6px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
            margin-top: 50px;
            overflow: hidden;
            animation: fadeInUp 0.8s ease 1.1s both;
        }

        .gracias-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #fbbf24, #f59e0b, #fbbf24);
            background-size: 200% 100%;
            border-radius: 3px;
            width: 0%;
            animation: progressGrow 5s linear forwards, shimmer 1.5s infinite;
        }

        @keyframes progressGrow {
            0% {
                width: 0%;
            }

            100% {
                width: 100%;
            }
        }

        @keyframes shimmer {
            0% {
                background-position: 200% 0;
            }

            100% {
                background-position: -200% 0;
            }
        }

        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background: #fbbf24;
            animation: confettiFall 3s ease-out forwards;
        }

        @keyframes bounceIn {
            0% {
                transform: scale(0);
            }

            50% {
                transform: scale(1.2);
            }

            100% {
                transform: scale(1);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes confettiFall {
            0% {
                transform: translateY(-100vh) rotate(0deg);
                opacity: 1;
            }

            100% {
                transform: translateY(100vh) rotate(720deg);
                opacity: 0;
            }
        }

        /* ===== CARTELERA ===== */
        .view-cartelera {
            min-height: 100vh;
            height: auto;
            background: var(--bg-primary);
            padding: 40px;
            overflow-y: auto;
            overflow-x: hidden;
        }

        /* Permitir scroll cuando la cartelera está visible */
        body:has(.view-cartelera) {
            overflow-y: auto;
            height: auto;
        }

        .header-cartelera {
            text-align: center;
            margin-bottom: 40px;
            animation: fadeInDown 0.6s ease;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header-cartelera h1 {
            font-size: 2.8rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .header-cartelera h1 i {
            color: var(--accent-color);
        }

        .header-cartelera p {
            color: var(--text-secondary);
            font-size: 1.2rem;
        }

        .eventos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 28px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .evento-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            animation: cardAppear 0.6s ease backwards;
        }

        .evento-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .evento-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .evento-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        .evento-card:nth-child(4) {
            animation-delay: 0.4s;
        }

        @keyframes cardAppear {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.9);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .evento-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
            border-color: rgba(255, 255, 255, 0.35);
        }

        .evento-poster {
            width: 100%;
            aspect-ratio: 2/3;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .evento-card:hover .evento-poster {
            transform: scale(1.05);
        }

        .evento-poster-placeholder {
            width: 100%;
            aspect-ratio: 2/3;
            background: linear-gradient(135deg, var(--bg-tertiary) 0%, var(--bg-secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            font-size: 4rem;
        }

        .evento-info {
            padding: 20px;
            background: var(--bg-secondary);
        }

        .evento-titulo {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #ffffff;
        }

        .evento-funciones {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .evento-disponibles {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            background: rgba(16, 185, 129, 0.15);
            color: var(--success-color);
        }

        .evento-disponibles.pocos {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning-color);
        }

        .evento-disponibles.agotado {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger-color);
        }

        .evento-disponibles i {
            font-size: 1rem;
        }

        .sync-badge {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 12px 24px;
            border-radius: 30px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .sync-dot {
            width: 10px;
            height: 10px;
            background: var(--success-color);
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.3);
                opacity: 0.7;
            }
        }

        /* ===== HORARIOS ===== */
        .view-horarios {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--bg-primary);
        }

        .horarios-header {
            background: var(--bg-primary);
            color: #ffffff;
            padding: 40px;
            text-align: center;
            animation: slideDown 0.5s ease;
            border-bottom: 2px solid var(--border-color);
        }

        @keyframes slideDown {
            from {
                transform: translateY(-100%);
            }

            to {
                transform: translateY(0);
            }
        }

        .horarios-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .horarios-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .horarios-titulo {
            font-size: 1rem;
            color: var(--text-on-navy);
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 3px;
            opacity: 0.85;
        }

        .horarios-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            max-width: 900px;
        }

        .horario-card {
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: 20px;
            padding: 36px 48px;
            text-align: center;
            transition: all 0.3s ease;
            min-width: 220px;
            animation: cardAppear 0.5s ease backwards;
            color: #ffffff;
        }

        .horario-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .horario-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .horario-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        .horario-dia {
            font-size: 1rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }

        .horario-fecha {
            font-size: 2.5rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1;
            margin: 8px 0;
        }

        .horario-hora {
            font-size: 3.2rem;
            font-weight: 900;
            color: #ffffff;
            padding: 8px 0;
            display: inline-block;
            letter-spacing: 0.02em;
        }

        .esperando-msg {
            margin-top: 50px;
            text-align: center;
            color: var(--text-secondary);
        }

        .esperando-msg i {
            font-size: 3rem;
            margin-bottom: 16px;
            animation: bounce 1s infinite;
        }

        @keyframes bounce {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        /* ===== MAPA ===== */
        .view-mapa {
            display: block;
            height: 100vh;
            position: relative;
            background: var(--bg-primary);
        }

        .mapa-panel {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: var(--bg-primary);
            height: 100vh;
        }

        .mapa-header {
            background: var(--bg-primary);
            color: #ffffff;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            border-bottom: 2px solid var(--border-color);
            flex-shrink: 0;
        }

        .mapa-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .mapa-header h2 {
            font-size: 0.75rem;
            font-weight: 900;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        .mapa-evento-titulo {
            font-size: 1.1rem;
            font-weight: 900;
            color: #ffffff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mapa-header-horario {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.65rem;
            color: #ffffff;
            font-weight: 900;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .mapa-header-horario i {
            font-size: 1.4rem;
            opacity: 0.85;
        }

        .mapa-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 20px;
            background: var(--bg-primary);
        }

        .seat {
            width: 50px;
            height: 50px;
            border-radius: 6px;
            margin: 3px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 900;
            color: #ffffff;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.85), 0 0 1px #000;
            background: var(--cat-color, #0d1829);
            border: 2px solid rgba(0, 0, 0, 0.4);
            border-bottom: 4px solid rgba(0, 0, 0, 0.55);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            letter-spacing: 0.02em;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2), 0 2px 4px rgba(0, 0, 0, 0.25);
        }

        .row-label {
            display: inline-block;
            min-width: 38px;
            text-align: center;
            color: #ffffff;
            background: transparent;
            font-weight: 900;
            font-size: 1.15rem;
            padding: 3px 5px;
            border-radius: 0;
            border: none;
            text-shadow: none;
        }

        .seat-map-wrapper {
            transform-origin: center center;
            transition: transform 0.4s ease;
        }

        .seat-row {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 3px;
        }

        .pasillo {
            width: 20px;
        }

        /* Teatro 420 butacas — asientos más grandes para el visor cliente */
        .mapa-teatro-420 .seat {
            width: 62px;
            height: 62px;
            font-size: 15px;
            margin: 4px;
            border-radius: 7px;
            border-bottom-width: 5px;
        }

        .mapa-teatro-420 .row-label {
            min-width: 44px;
            font-size: 1.4rem;
        }

        .mapa-teatro-420 .pasillo {
            width: 26px;
        }

        .mapa-teatro-420 .screen {
            font-size: 1.25rem;
            letter-spacing: 6px;
            margin-bottom: 22px;
        }

        .mapa-teatro-420 .seat-row {
            margin-bottom: 4px;
        }

        .seat.client-selected {
            transform: scale(1.3) !important;
            z-index: 100;
            color: #ffffff !important;
            filter: brightness(1.15) saturate(1.1);
            border-color: rgba(255, 255, 255, 0.85) !important;
            border-bottom-color: rgba(0, 0, 0, 0.5) !important;
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.9) !important;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.65), 0 0 24px rgba(255, 255, 255, 0.35) !important;
            animation: selectedPulse 1.5s infinite;
        }

        @keyframes selectedPulse {

            0%,
            100% {
                box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.6), 0 0 16px rgba(255, 255, 255, 0.25);
            }

            50% {
                box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.75), 0 0 24px rgba(255, 255, 255, 0.35);
            }
        }

        .seat.vendido {
            opacity: 0.32 !important;
            filter: grayscale(0.25) brightness(0.85);
            color: rgba(255, 255, 255, 0.45) !important;
            text-shadow: none !important;
            pointer-events: none;
            /* Conserva el color de categoría del asiento; solo se atenúa */
        }

        .seat.vendido::after {
            content: '✕';
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            font-weight: 900;
            line-height: 1;
            color: rgba(0, 0, 0, 0.82);
            text-shadow:
                0 0 4px #fff,
                0 0 8px #fff,
                0 1px 2px rgba(255, 255, 255, 0.9);
            pointer-events: none;
            z-index: 2;
        }

        .mapa-teatro-420 .seat.vendido::after {
            font-size: 2.1rem;
        }

        .categorias-leyenda {
            padding: 18px 24px 22px;
            background: linear-gradient(180deg, rgba(15, 31, 56, 0.98) 0%, rgba(10, 14, 20, 1) 100%);
            border-top: 3px solid rgba(255, 214, 0, 0.35);
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            justify-content: center;
            align-items: center;
            box-shadow: 0 -8px 32px rgba(0, 0, 0, 0.45);
        }

        .categorias-leyenda-titulo {
            width: 100%;
            text-align: center;
            font-size: 0.95rem;
            font-weight: 900;
            letter-spacing: 0.18em;
            color: var(--yellow);
            text-transform: uppercase;
            margin-bottom: 2px;
            text-shadow: 0 1px 8px rgba(255, 214, 0, 0.35);
        }

        .cat-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.05rem;
            color: #ffffff;
            background: rgba(255, 255, 255, 0.07);
            padding: 12px 20px;
            border-radius: 14px;
            border: 2px solid rgba(255, 255, 255, 0.18);
            border-left-width: 6px;
            border-left-color: var(--cat-accent, rgba(255, 255, 255, 0.5));
            font-weight: 800;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
            min-width: 160px;
        }

        .cat-item-vendido {
            border-left-color: rgba(255, 255, 255, 0.35);
            opacity: 0.9;
        }

        .cat-color {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 2px solid rgba(255, 255, 255, 0.55);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.25);
            flex-shrink: 0;
        }

        .cat-color-vendido {
            background: rgba(255, 255, 255, 0.12) !important;
            position: relative;
            opacity: 0.45;
        }

        .cat-color-vendido::after {
            content: '✕';
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: 900;
            color: rgba(0, 0, 0, 0.85);
            text-shadow: 0 0 3px #fff;
        }

        .cat-nombre {
            flex: 1;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .cat-precio {
            font-weight: 900;
            font-size: 1.2rem;
            color: var(--yellow);
            text-shadow: 0 1px 6px rgba(255, 214, 0, 0.3);
            white-space: nowrap;
        }

        .info-panel,
        .carrito-float {
            position: fixed;
            bottom: 18px;
            right: 18px;
            width: 300px;
            max-height: min(340px, 48vh);
            background: rgba(10, 14, 20, 0.92);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            box-shadow: 0 10px 36px rgba(0, 0, 0, 0.6);
            display: flex;
            flex-direction: column;
            z-index: 500;
            overflow: hidden;
            color: #ffffff;
            animation: carritoPop 0.35s ease;
            backdrop-filter: blur(8px);
        }

        .carrito-float.oculto {
            display: none;
        }

        @keyframes carritoPop {
            from {
                opacity: 0;
                transform: translateY(12px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes slideLeft {
            from {
                transform: translateX(100%);
            }

            to {
                transform: translateX(0);
            }
        }

        .evento-header-info {
            display: none;
        }

        .evento-nombre {
            font-size: 0.95rem;
            font-weight: 900;
            margin-bottom: 6px;
            line-height: 1.25;
            letter-spacing: 0.02em;
        }

        .evento-funcion {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.82rem;
            color: var(--accent-color);
            font-weight: 700;
        }

        .evento-funcion i {
            font-size: 0.9rem;
        }

        /* ===== SCREEN / ESCENARIO ===== */
        .screen {
            background: transparent;
            color: #ffffff;
            padding: 12px 40px;
            text-align: center;
            font-size: 1.15rem;
            font-weight: 900;
            margin-bottom: 30px;
            border-radius: 50% 50% 10px 10px / 10px 10px 0 0;
            box-shadow: none;
            letter-spacing: 5px;
            text-shadow: none;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .carrito-section {
            flex: 1;
            padding: 10px 12px 6px;
            overflow-y: hidden;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .carrito-titulo {
            font-size: 0.72rem;
            font-weight: 900;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 6px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .badge-contador {
            background: transparent;
            color: #ffffff;
            font-size: 0.85rem;
            font-weight: 900;
            padding: 0;
            border-radius: 0;
            min-width: auto;
            text-align: center;
            animation: badgePop 0.3s ease;
            border: none;
        }

        .badge-contador:empty {
            display: none;
        }

        @keyframes badgePop {
            0% {
                transform: scale(0);
            }

            50% {
                transform: scale(1.2);
            }

            100% {
                transform: scale(1);
            }
        }

        .carrito-items {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
            overflow-y: auto;
            padding-right: 4px;
            scroll-behavior: smooth;
            max-height: 165px;
        }

        .carrito-items::-webkit-scrollbar {
            width: 4px;
        }

        .carrito-items::-webkit-scrollbar-track {
            background: transparent;
            border-radius: 2px;
        }

        .carrito-items::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
        }

        .carrito-item {
            background: transparent;
            border-radius: 0;
            padding: 8px 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            animation: itemSlideIn 0.3s ease-out forwards;
            flex-shrink: 0;
            opacity: 0;
            transform: translateX(12px);
        }

        @keyframes itemSlideIn {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .item-asiento-info {
            display: flex;
            align-items: baseline;
            gap: 6px;
            line-height: 1;
        }

        .item-fila-label,
        .item-num-label {
            font-size: 0.65rem;
            font-weight: 900;
            color: var(--cart-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .item-fila-val,
        .item-num-val {
            font-size: 1.3rem;
            font-weight: 900;
            color: #ffffff;
            background: transparent;
            padding: 0;
            border-radius: 0;
            letter-spacing: 0.02em;
            border: none;
        }

        .item-fila-val {
            min-width: 1.2em;
        }

        .item-asiento-sep {
            color: rgba(255, 255, 255, 0.35);
            font-weight: 700;
            font-size: 0.9rem;
            margin: 0 1px;
        }

        .item-categoria {
            font-size: 0.62rem;
            color: var(--cart-muted);
            margin-top: 2px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .item-precio {
            font-size: 1.05rem;
            font-weight: 900;
            color: #ffffff;
            white-space: nowrap;
        }

        .item-precio-final {
            font-size: 1.05rem;
            font-weight: 900;
            color: #ffffff;
            padding: 0;
            border-radius: 0;
            background: transparent;
        }

        .total-section {
            padding: 12px 4px;
            background: transparent;
            color: #ffffff;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-shrink: 0;
        }

        .total-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-secondary);
            font-weight: 900;
            margin-bottom: 0;
        }

        .total-amount {
            font-size: 2rem;
            font-weight: 900;
            line-height: 1;
            display: flex;
            align-items: baseline;
            gap: 2px;
            color: #ffffff;
            letter-spacing: -0.02em;
        }

        .total-amount .currency {
            font-size: 1.2rem;
            color: #ffffff;
            font-weight: 900;
        }

        .carrito-vacio {
            display: none;
        }

        .hidden {
            display: none !important;
        }

        /* ===== ANIMACIONES DE DESCUENTO Y CORTESÍA ===== */
        .descuento-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 6px;
            border-radius: 6px;
            font-size: 0.58rem;
            font-weight: 900;
            animation: descuentoPop 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .descuento-badge.descuento {
            background: transparent;
            color: rgba(255, 255, 255, 0.75);
            border: none;
            padding: 0;
        }

        .descuento-badge.cortesia {
            background: transparent;
            color: rgba(255, 255, 255, 0.75);
            border: none;
            padding: 0;
        }

        @keyframes descuentoPop {
            0% {
                transform: scale(0);
                opacity: 0;
            }

            50% {
                transform: scale(1.2);
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .carrito-item.con-descuento,
        .carrito-item.cortesia {
            border-left-color: transparent;
        }

        .item-precio.tachado {
            text-decoration: line-through;
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.72rem;
        }

        .precio-wrapper {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 2px;
        }

        /* Notificación flotante de descuento */
        .visor-notificacion {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(-100px);
            background: rgba(10, 14, 20, 0.9);
            color: #ffffff;
            padding: 16px 28px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 10000;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.12);
            opacity: 0;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            backdrop-filter: blur(8px);
        }

        .visor-notificacion.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        .visor-notificacion.descuento-notif,
        .visor-notificacion.cortesia-notif {
            background: rgba(10, 14, 20, 0.9);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.12);
        }

        .visor-notificacion i {
            font-size: 1.5rem;
        }

        /* Banner evento gratuito / cortesía */
        .visor-evento-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 14px 24px;
            font-size: 1.15rem;
            font-weight: 700;
            text-align: center;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.35);
            animation: bannerSlideDown 0.5s ease;
        }

        .visor-evento-banner i {
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .visor-evento-banner.banner-gratuito {
            background: linear-gradient(135deg, #059669 0%, #10b981 50%, #34d399 100%);
            color: #ffffff;
        }

        .visor-evento-banner.banner-cortesia {
            background: linear-gradient(135deg, #b45309 0%, #f59e0b 50%, #fbbf24 100%);
            color: #1a1a1a;
        }

        body.con-banner-gratuito #bannerCortesia:not(.hidden) {
            top: 52px;
        }

        body.con-banner-gratuito #viewHorarios,
        body.con-banner-gratuito #viewMapa .mapa-panel {
            padding-top: 56px;
        }

        body.con-banner-cortesia #viewHorarios,
        body.con-banner-cortesia #viewMapa .mapa-panel {
            padding-top: 56px;
        }

        body.con-banner-gratuito.con-banner-cortesia #viewHorarios,
        body.con-banner-gratuito.con-banner-cortesia #viewMapa .mapa-panel {
            padding-top: 108px;
        }

        @keyframes bannerSlideDown {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .total-amount.gratis,
        .total-amount.cortesia {
            font-size: 2.2rem;
            letter-spacing: 0.05em;
        }

        .item-precio-final.gratis {
            color: #34d399;
            font-weight: 800;
        }

        .item-precio-final.cortesia {
            color: #fbbf24;
            font-weight: 800;
        }

        /* Animación de precio tachado */
        @keyframes strikethrough {
            0% {
                width: 0;
            }

            100% {
                width: 100%;
            }
        }

        .precio-strike {
            position: relative;
            display: inline-block;
        }

        .precio-strike::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            width: 100%;
            height: 2px;
            background: #ef4444;
            animation: strikethrough 0.3s ease-out forwards;
        }

        /* Total con descuento */
        .total-descuento-info {
            font-size: 0.55rem;
            color: rgba(255, 255, 255, 0.65);
            background: transparent;
            padding: 0;
            border-radius: 0;
            font-weight: 900;
            margin-top: 0;
            display: inline-block;
            border: none;
        }

        .total-section .total-descuento-info {
            flex: 1;
            text-align: left;
        }


        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ===== OVERLAY DE TRANSICIÓN (Evento/Horario) ===== */
        .overlay-transicion {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--navy);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9998;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s ease;
        }

        .overlay-transicion.active {
            opacity: 1;
            visibility: visible;
        }

        .transicion-icon {
            font-size: 6rem;
            color: white;
            animation: pulseIcon 1s ease infinite;
        }

        .transicion-titulo {
            font-size: 2.5rem;
            font-weight: 800;
            color: white;
            margin: 20px 0 10px;
            text-align: center;
            animation: fadeInUp 0.5s ease 0.2s both;
        }

        .transicion-subtitulo {
            font-size: 1.3rem;
            color: rgba(255, 255, 255, 0.85);
            animation: fadeInUp 0.5s ease 0.4s both;
            text-align: center;
        }

        .transicion-hora {
            font-size: 3.5rem;
            font-weight: 900;
            color: #ffffff;
            margin-top: 20px;
            animation: bounceIn 0.6s ease 0.6s both;
        }

        @keyframes pulseIcon {

            0%,
            100% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.1);
                opacity: 0.8;
            }
        }

        /* Estilo para el badge de sincronización */
        .sync-badge {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.85);
            color: white;
            padding: 14px 28px;
            border-radius: 40px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeIn 0.5s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            z-index: 100;
        }

        .sync-dot {
            width: 12px;
            height: 12px;
            background: var(--success-color);
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }

        .sync-badge.conectado .sync-dot {
            background: var(--success-color);
        }

        .sync-badge.esperando .sync-dot {
            background: var(--warning-color);
        }

        @media (max-width: 992px) {
            .carrito-float {
                bottom: 12px;
                right: 12px;
                width: 260px;
                max-height: min(300px, 42vh);
            }

            .total-amount {
                font-size: 1.65rem;
            }

            .mapa-header {
                flex-wrap: wrap;
                padding: 8px 12px;
            }

            .mapa-evento-titulo {
                font-size: 0.9rem;
            }

            .mapa-header-horario {
                font-size: 1.35rem;
            }

            .transicion-titulo {
                font-size: 2rem;
            }

            .transicion-hora {
                font-size: 2.5rem;
            }
        }

        .visor-notificacion.show i {
            animation: rubberBand 1s;
        }

        @keyframes rubberBand {
            from {
                transform: scale3d(1, 1, 1);
            }

            30% {
                transform: scale3d(1.25, 0.75, 1);
            }

            40% {
                transform: scale3d(0.75, 1.25, 1);
            }

            50% {
                transform: scale3d(1.15, 0.85, 1);
            }

            65% {
                transform: scale3d(0.95, 1.05, 1);
            }

            75% {
                transform: scale3d(1.05, 0.95, 1);
            }

            to {
                transform: scale3d(1, 1, 1);
            }
        }
    </style>
</head>

<body>

    <!-- Overlay de Gracias -->
    <div class="overlay-gracias" id="overlayGracias">
        <i class="bi bi-check-circle-fill gracias-icon"></i>
        <h1 class="gracias-titulo" id="graciasTitulo">¡Gracias por su compra!</h1>
        <p class="gracias-mensaje" id="graciasMensaje">Su compra ha sido procesada exitosamente</p>
        <div class="gracias-total" id="graciasTotal">$0.00</div>
        <p class="gracias-disfrute" id="graciasDisfrute">🎭 ¡Que disfrute la función!</p>
        <div class="gracias-progress">
            <div class="gracias-progress-bar" id="graciasProgressBar"></div>
        </div>
    </div>

    <!-- Overlay de Transición (Evento/Horario) -->
    <div class="overlay-transicion" id="overlayTransicion">
        <i class="bi bi-calendar-event transicion-icon" id="transicionIcon"></i>
        <h1 class="transicion-titulo" id="transicionTitulo">Cargando...</h1>
        <p class="transicion-subtitulo" id="transicionSubtitulo">Preparando el evento</p>
        <div class="transicion-hora" id="transicionHora"></div>
    </div>

    <?php if (!$evento_info): ?>

        <div class="view-cartelera" id="viewCartelera">
            <div class="header-cartelera">
                <h1><i class="bi bi-film"></i> Cartelera</h1>
                <p>Próximas funciones disponibles</p>
            </div>

            <?php if (empty($eventos_disponibles)): ?>
                <div style="text-align:center; padding:100px; color:var(--text-secondary);">
                    <i class="bi bi-calendar-x" style="font-size:5rem; opacity:0.3;"></i>
                    <h3 style="margin-top:24px; font-weight:600; color:var(--text-primary);">No hay eventos disponibles</h3>
                    <p>Pronto agregaremos nuevas funciones</p>
                </div>
            <?php else: ?>
                <div class="eventos-grid">
                    <?php foreach ($eventos_disponibles as $id_evt => $evt):
                        $img = '';
                        if (!empty($evt['imagen'])) {
                            $rutas = ["../evt_interfaz/" . $evt['imagen'], $evt['imagen']];
                            foreach ($rutas as $r) {
                                if (file_exists($r)) {
                                    $img = $r;
                                    break;
                                }
                            }
                        }
                        ?>
                        <div class="evento-card">
                            <?php if ($img): ?>
                                <img src="<?= htmlspecialchars($img) ?>" class="evento-poster" alt="">
                            <?php else: ?>
                                <div class="evento-poster-placeholder"><i class="bi bi-image"></i></div>
                            <?php endif; ?>
                            <div class="evento-info">
                                <h3 class="evento-titulo"><?= htmlspecialchars($evt['titulo']) ?></h3>
                                <p class="evento-funciones"><?= count($evt['funciones']) ?>
                                    función<?= count($evt['funciones']) !== 1 ? 'es' : '' ?>
                                    disponible<?= count($evt['funciones']) !== 1 ? 's' : '' ?></p>
                                <?php
                                // Calcular total de boletos disponibles sumando todas las funciones
                                $total_disponibles = 0;
                                foreach ($evt['funciones'] as $func) {
                                    $total_disponibles += $func['disponibles'] ?? 0;
                                }
                                $total_asientos = $evt['total_asientos'] ?? 0;

                                if ($total_asientos > 0):
                                    $claseDisp = '';
                                    if ($total_disponibles <= 0) {
                                        $claseDisp = 'agotado';
                                    } elseif ($total_disponibles <= 20) {
                                        $claseDisp = 'pocos';
                                    }
                                    ?>
                                    <div class="evento-disponibles <?= $claseDisp ?>">
                                        <i class="bi bi-ticket-perforated"></i>
                                        <?php if ($total_disponibles <= 0): ?>
                                            Agotado
                                        <?php else: ?>
                                            <?= $total_disponibles ?> boleto<?= $total_disponibles !== 1 ? 's' : '' ?>
                                            disponible<?= $total_disponibles !== 1 ? 's' : '' ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Script de sincronización para cartelera -->
        <script>
            (function () {
                const canalCartelera = new BroadcastChannel('pos_sync_channel');
                console.log('🎬 Visor Cartelera - Escuchando cambios de evento...');

                canalCartelera.onmessage = (event) => {
                    const data = event.data;
                    console.log('📥 Cartelera recibió:', data);

                    if (data.accion === 'INIT' && data.id_evento) {
                        console.log('🔄 Redirigiendo a evento:', data.id_evento);
                        window.location.href = `visor_cliente.php?id_evento=${data.id_evento}`;
                    }

                    if (data.accion === 'SELECCION_EVENTO' && data.id_evento) {
                        console.log('🎭 Evento seleccionado:', data.titulo);
                        window.location.href = `visor_cliente.php?id_evento=${data.id_evento}`;
                    }
                };
            })();
        </script>
    </body>

    </html>
<?php else: ?>

    <div id="bannerEventoGratuito" class="visor-evento-banner banner-gratuito <?= $evento_es_gratuito ? '' : 'hidden' ?>">
        <i class="bi bi-ticket-perforated-fill"></i>
        <span>¡Disfruta la función gratuita!</span>
    </div>
    <div id="bannerCortesia" class="visor-evento-banner banner-cortesia hidden">
        <i class="bi bi-gift-fill"></i>
        <span>Este boleto va de parte de la casa — ¡Cortesía!</span>
    </div>

    <div id="viewHorarios" class="view-horarios">
        <div class="horarios-header">
            <h1><?= htmlspecialchars($evento_info['titulo']) ?></h1>
            <p>Seleccione el horario de su preferencia</p>
        </div>

        <div class="horarios-content">
            <div class="horarios-titulo">Horarios Disponibles</div>

            <?php if (empty($funciones_evento)): ?>
                <p style="color:var(--text-secondary); font-size:1.1rem;">No hay funciones disponibles</p>
            <?php else: ?>
                <div class="horarios-grid">
                    <?php
                    $hoy = new DateTime();
                    $hoy->setTime(0, 0, 0);
                    $manana = clone $hoy;
                    $manana->modify('+1 day');

                    $dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
                    foreach ($funciones_evento as $f):
                        $fecha = new DateTime($f['fecha_hora']);
                        $fechaSolo = clone $fecha;
                        $fechaSolo->setTime(0, 0, 0);

                        // Determinar cómo mostrar la fecha
                        if ($fechaSolo == $hoy) {
                            $mostrarFecha = 'Hoy';
                        } elseif ($fechaSolo == $manana) {
                            $mostrarFecha = 'Mañana';
                        } else {
                            $mostrarFecha = $dias[(int) $fecha->format('w')] . ' ' . $fecha->format('d');
                        }

                        // Formato de hora más amigable (12h con AM/PM)
                        $hora = $fecha->format('g:i A');
                        ?>
                        <div class="horario-card">
                            <div class="horario-dia"><?= $mostrarFecha ?></div>
                            <div class="horario-hora"><?= $hora ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="viewMapa" class="view-mapa hidden <?= ($evento_info['tipo'] == 1) ? 'mapa-teatro-420' : 'mapa-pasarela' ?>">
        <div class="mapa-panel">
            <div class="mapa-header">
                <div class="mapa-header-left">
                    <h2><?= ($evento_info['tipo'] == 1) ? 'Escenario' : 'Pasarela' ?></h2>
                    <span class="mapa-evento-titulo"><?= htmlspecialchars($evento_info['titulo']) ?></span>
                </div>
                <div class="mapa-header-horario">
                    <i class="bi bi-calendar3"></i>
                    <span id="txtHorario">-</span>
                </div>
            </div>

            <div class="mapa-container">
                <div class="seat-map-wrapper" id="mapaContenido">
                    <?php
                    if ($evento_info['tipo'] == 2) {
                        echo "<div class='screen'>PASARELA / ESCENARIO</div>";
                    } else {
                        echo "<div class='screen'>ESCENARIO</div>";
                    }

                    if ($evento_info['tipo'] == 2) {
                        for ($fila = 1; $fila <= 10; $fila++) {
                            echo "<div class='seat-row'>";
                            echo "<span class='row-label'>PB$fila</span>";
                            for ($i = 1; $i <= 6; $i++) {
                                $id = "PB$fila-$i";
                                $id_cat = $mapa_guardado[$id] ?? 0;
                                $color = $colores_categoria[$id_cat] ?? 'rgba(255,255,255,0.35)';
                                echo "<div class='seat' id='seat-$id' data-id='$id' style='--cat-color:$color'>$id</div>";
                            }
                            echo "<div class='pasillo'></div>";
                            for ($i = 7; $i <= 12; $i++) {
                                $id = "PB$fila-$i";
                                $id_cat = $mapa_guardado[$id] ?? 0;
                                $color = $colores_categoria[$id_cat] ?? 'rgba(255,255,255,0.35)';
                                echo "<div class='seat' id='seat-$id' data-id='$id' style='--cat-color:$color'>$id</div>";
                            }
                            echo "<span class='row-label'>PB$fila</span>";
                            echo "</div>";
                        }
                        echo "<div style='height:16px'></div>";
                    }

                    $letras = range('A', 'O');
                    foreach ($letras as $fila) {
                        echo "<div class='seat-row'>";
                        echo "<span class='row-label'>$fila</span>";
                        for ($i = 1; $i <= 6; $i++) {
                            $id = "$fila$i";
                            $id_cat = $mapa_guardado[$id] ?? 0;
                            $color = $colores_categoria[$id_cat] ?? 'rgba(255,255,255,0.35)';
                            echo "<div class='seat' id='seat-$id' data-id='$id' style='--cat-color:$color'>$id</div>";
                        }
                        echo "<div class='pasillo'></div>";
                        for ($i = 7; $i <= 20; $i++) {
                            $id = "$fila$i";
                            $id_cat = $mapa_guardado[$id] ?? 0;
                            $color = $colores_categoria[$id_cat] ?? 'rgba(255,255,255,0.35)';
                            echo "<div class='seat' id='seat-$id' data-id='$id' style='--cat-color:$color'>$id</div>";
                        }
                        echo "<div class='pasillo'></div>";
                        for ($i = 21; $i <= 26; $i++) {
                            $id = "$fila$i";
                            $id_cat = $mapa_guardado[$id] ?? 0;
                            $color = $colores_categoria[$id_cat] ?? 'rgba(255,255,255,0.35)';
                            echo "<div class='seat' id='seat-$id' data-id='$id' style='--cat-color:$color'>$id</div>";
                        }
                        echo "<span class='row-label'>$fila</span>";
                        echo "</div>";
                    }

                    echo "<div class='seat-row'>";
                    echo "<span class='row-label'>P</span>";
                    for ($i = 1; $i <= 30; $i++) {
                        $id = "P$i";
                        $id_cat = $mapa_guardado[$id] ?? 0;
                        $color = $colores_categoria[$id_cat] ?? 'rgba(255,255,255,0.35)';
                        echo "<div class='seat' id='seat-$id' data-id='$id' style='--cat-color:$color'>$id</div>";
                    }
                    echo "<span class='row-label'>P</span>";
                    echo "</div>";
                    ?>
                </div>
            </div>

            <div class="categorias-leyenda">
                <div class="categorias-leyenda-titulo">Categorías y precios</div>
                <?php foreach ($categorias_evento as $cat):
                    $colorCat = htmlspecialchars($cat['color']);
                    $esNoVenta = stripos($cat['nombre_categoria'], 'no venta') !== false;
                ?>
                    <div class="cat-item" style="--cat-accent: <?= $colorCat ?>">
                        <div class="cat-color" style="background:<?= $colorCat ?>"></div>
                        <span class="cat-nombre"><?= htmlspecialchars($cat['nombre_categoria']) ?></span>
                        <?php if (!$esNoVenta): ?>
                            <?php if ($evento_es_gratuito): ?>
                                <span class="cat-precio">Gratis</span>
                            <?php else: ?>
                                <span class="cat-precio">$<?= number_format($cat['precio'], 0) ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="cat-item cat-item-vendido">
                    <div class="cat-color cat-color-vendido"></div>
                    <span class="cat-nombre">Vendido</span>
                </div>
            </div>
        </div>

        <div class="carrito-float oculto" id="carritoFloat">
            <div class="carrito-section">
                <div class="carrito-titulo">
                    Asientos <span id="contadorAsientos" class="badge-contador"></span>
                </div>
                <div class="carrito-items" id="listaCarrito"></div>
            </div>

            <div class="total-section">
                <div class="total-label">Total</div>
                <div class="total-amount"><span class="currency">$</span><span id="txtTotal">0.00</span></div>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php if ($evento_info): ?>
    <!-- Script principal para vista de evento -->
    <script>
        const canal = new BroadcastChannel('pos_sync_channel');
        const idEventoActual = <?= $id_evento ?>;
        const VISOR_EVENTO_GRATUITO = <?= $evento_es_gratuito ? 'true' : 'false' ?>;
        let funcionSeleccionada = false;

        document.body.classList.toggle('con-banner-gratuito', VISOR_EVENTO_GRATUITO);

        function actualizarBannersVisor(hayCortesia) {
            const bannerCortesia = document.getElementById('bannerCortesia');
            if (bannerCortesia) {
                bannerCortesia.classList.toggle('hidden', !hayCortesia);
            }
            document.body.classList.toggle('con-banner-cortesia', !!hayCortesia);
        }

        function textoPrecioVisor(precio, categoria, tipoBoleto) {
            const tipo = (tipoBoleto || '').toLowerCase();
            if (tipo === 'cortesia') return { texto: 'CORTESÍA', clase: 'cortesia' };
            const p = Number(precio);
            if (p > 0) return { texto: '$' + p.toFixed(2), clase: '' };
            const cat = (categoria || '').toLowerCase();
            if (cat.includes('cortes')) return { texto: 'CORTESÍA', clase: 'cortesia' };
            if (VISOR_EVENTO_GRATUITO) return { texto: 'GRATIS', clase: 'gratis' };
            return { texto: '$0.00', clase: '' };
        }

        function textoTotalVisor(total, hayCortesia) {
            const t = parseFloat(total);
            if (hayCortesia && t === 0) return { texto: 'CORTESÍA', clase: 'cortesia' };
            if (VISOR_EVENTO_GRATUITO && t === 0) return { texto: 'GRATIS', clase: 'gratis' };
            return { texto: parseFloat(total).toFixed(2), clase: '' };
        }

        console.log('🎬 Visor Cliente iniciado - Evento actual:', idEventoActual);

        // SOLICITAR ESTADO COMPLETO AL POS (Handshake)
        setTimeout(() => {
            console.log('🔄 Solicitando sincronización al POS...');
            canal.postMessage({ accion: 'REQUEST_SYNC' });
        }, 500);

        // ===== FUNCIONES DE ANIMACIÓN =====

        // Mostrar overlay de transición para evento
        function mostrarTransicionEvento(titulo) {
            const overlay = document.getElementById('overlayTransicion');
            const icon = document.getElementById('transicionIcon');
            const tituloEl = document.getElementById('transicionTitulo');
            const subtituloEl = document.getElementById('transicionSubtitulo');
            const horaEl = document.getElementById('transicionHora');

            icon.className = 'bi bi-film transicion-icon';
            tituloEl.textContent = titulo;
            subtituloEl.textContent = 'Cargando evento...';
            horaEl.textContent = '';

            overlay.classList.add('active');

            // Se ocultará cuando cargue la nueva página
        }

        // Mostrar overlay de transición para horario
        function mostrarTransicionHorario(texto) {
            const overlay = document.getElementById('overlayTransicion');
            const icon = document.getElementById('transicionIcon');
            const tituloEl = document.getElementById('transicionTitulo');
            const subtituloEl = document.getElementById('transicionSubtitulo');
            const horaEl = document.getElementById('transicionHora');

            icon.className = 'bi bi-clock transicion-icon';
            tituloEl.textContent = 'Horario Seleccionado';
            subtituloEl.textContent = 'Preparando mapa de asientos...';
            horaEl.textContent = texto;

            overlay.classList.add('active');

            // Ocultar después de 2 segundos
            setTimeout(() => {
                overlay.classList.remove('active');
            }, 2000);
        }

        // Crear confetti para compra exitosa
        function crearConfetti() {
            const overlay = document.getElementById('overlayGracias');
            const colores = ['#fbbf24', '#10b981', '#3b82f6', '#ef4444', '#8b5cf6', '#ec4899'];

            for (let i = 0; i < 100; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.className = 'confetti';
                    confetti.style.left = Math.random() * 100 + '%';
                    confetti.style.background = colores[Math.floor(Math.random() * colores.length)];
                    confetti.style.animationDuration = (2 + Math.random() * 2) + 's';
                    confetti.style.width = (5 + Math.random() * 10) + 'px';
                    confetti.style.height = confetti.style.width;
                    overlay.appendChild(confetti);

                    setTimeout(() => confetti.remove(), 4000);
                }, i * 30);
            }
        }

        // Mostrar animación de gracias por la compra
        function mostrarGracias(total) {
            const overlay = document.getElementById('overlayGracias');
            const progressBar = document.getElementById('graciasProgressBar');
            const hayCortesia = window.ultimoEstadoCortesia;
            const t = parseFloat(total);

            const titulo = document.getElementById('graciasTitulo');
            const mensaje = document.getElementById('graciasMensaje');
            const totalEl = document.getElementById('graciasTotal');
            const disfrute = document.getElementById('graciasDisfrute');

            if (hayCortesia) {
                if (titulo) titulo.textContent = '¡Cortesía confirmada!';
                if (mensaje) mensaje.textContent = 'Este boleto va de parte de la casa';
                if (totalEl) totalEl.textContent = 'CORTESÍA';
                if (disfrute) disfrute.textContent = '🎁 ¡Que disfrute la función!';
            } else if (VISOR_EVENTO_GRATUITO || t === 0) {
                if (titulo) titulo.textContent = '¡Entrada confirmada!';
                if (mensaje) mensaje.textContent = 'Su registro ha sido procesado exitosamente';
                if (totalEl) totalEl.textContent = 'GRATIS';
                if (disfrute) disfrute.textContent = '🎭 ¡Disfruta la función gratuita!';
            } else {
                if (titulo) titulo.textContent = '¡Gracias por su compra!';
                if (mensaje) mensaje.textContent = 'Su compra ha sido procesada exitosamente';
                if (totalEl) totalEl.textContent = '$' + t.toFixed(2);
                if (disfrute) disfrute.textContent = '🎭 ¡Que disfrute la función!';
            }

            // Reiniciar la animación de la barra de progreso
            if (progressBar) {
                progressBar.style.animation = 'none';
                progressBar.offsetHeight; // Forzar reflow
                progressBar.style.animation = 'progressGrow 5s linear forwards, shimmer 1.5s infinite';
            }

            overlay.classList.add('active');
            crearConfetti();

            overlay.classList.add('active');
            crearConfetti();

            // Transición automática ELIMINADA - Esperar a NUEVA_VENTA
            // setTimeout(() => {
            //    cerrarGraciasYContinuar();
            // }, 5000);
        }

        // Cerrar overlay de gracias y mostrar vista de horarios
        function cerrarGraciasYContinuar() {
            const overlay = document.getElementById('overlayGracias');
            overlay.classList.remove('active');

            // Cambiar a vista de horarios en lugar de ir a cartelera
            funcionSeleccionada = false;
            const viewHorarios = document.getElementById('viewHorarios');
            const viewMapa = document.getElementById('viewMapa');

            if (viewMapa) viewMapa.classList.add('hidden');
            if (viewHorarios) viewHorarios.classList.remove('hidden');

            // Resetear la vista
            resetearVista();

            console.log('🕐 Vista de horarios mostrada después de venta');
        }

        // ===== MANEJO DE MENSAJES =====

        canal.onmessage = (event) => {
            const data = event.data;
            console.log('📥 Mensaje recibido:', data);

            // Cambio de evento
            if (data.accion === 'INIT' && idEventoActual !== parseInt(data.id_evento)) {
                console.log('🔄 Cambiando a evento:', data.id_evento);
                mostrarTransicionEvento(data.titulo || 'Cargando evento...');
                setTimeout(() => {
                    window.location.href = `visor_cliente.php?id_evento=${data.id_evento}`;
                }, 800);
                return;
            }

            // Selección de evento (desde cartelera)
            if (data.accion === 'SELECCION_EVENTO') {
                console.log('🎭 Evento seleccionado:', data.titulo);
                mostrarTransicionEvento(data.titulo || 'Cargando evento...');
                setTimeout(() => {
                    window.location.href = `visor_cliente.php?id_evento=${data.id_evento}`;
                }, 800);
                return;
            }

            // Selección de horario/función
            if (data.accion === 'UPDATE_FUNCION' && data.texto) {
                console.log('🕐 Horario seleccionado:', data.texto);
                funcionSeleccionada = true;

                // Mostrar animación de transición
                mostrarTransicionHorario(data.texto);

                // Actualizar UI después de un pequeño delay
                setTimeout(() => {
                    const txtHorario = document.getElementById('txtHorario');
                    if (txtHorario) txtHorario.textContent = data.texto;

                    const viewHorarios = document.getElementById('viewHorarios');
                    const viewMapa = document.getElementById('viewMapa');
                    if (viewHorarios) viewHorarios.classList.add('hidden');
                    if (viewMapa) {
                        viewMapa.classList.remove('hidden');
                        setTimeout(ajustarMapa, 100);
                    }
                    resetearVista();
                }, 500);
            }

            // Actualización del carrito
            if (data.accion === 'UPDATE_CARRITO') {
                console.log('🛒 Carrito actualizado:', data.cantidad, 'items');
                actualizarInterfaz(data.carrito, data.total);
            }

            // Actualización de asientos vendidos
            if (data.accion === 'UPDATE_VENDIDOS') {
                console.log('🔴 Vendidos actualizados:', data.cantidad, 'asientos');
                marcarVendidos(data.asientos);
            }

            // Compra exitosa
            if (data.accion === 'COMPRA_EXITOSA') {
                console.log('✅ Compra exitosa! Total:', data.total);
                // Mostrar gracias (NO OCULTAR AUTOMÁTICAMENTE, esperar a NUEVA_VENTA)
                mostrarGracias(data.total);
            }

            // Nueva Venta: Limpiar pantalla de gracias y resetear vista
            if (data.accion === 'NUEVA_VENTA') {
                console.log('✨ Nueva venta iniciada: Limpiando pantalla de gracias');

                // Ocultar overlay de gracias
                const overlayGracias = document.getElementById('overlayGracias');
                if (overlayGracias) overlayGracias.classList.remove('active');

                // Resetear vista
                resetearVista();

                // Asegurar que se ve el mapa o los horarios según corresponda
                const viewHorarios = document.getElementById('viewHorarios');
                const viewMapa = document.getElementById('viewMapa');

                // Si estábamos en horarios, mantener horarios. Si no, mapa.
                // Por defecto, nueva venta suele implicar selección nueva, mantenemos donde esté
            }

            // Regresar a cartelera (Cambio de evento)
            if (data.accion === 'REGRESAR_CARTELERA') {
                console.log('🏠 Regresando a cartelera');
                window.location.href = 'visor_cliente.php';
            }

            // Selección de evento desde cartelera (Forzar recarga si es diferente)
            if (data.accion === 'SELECCION_EVENTO') {
                console.log('📅 Selección de evento:', data);
                if (data.id_evento) {
                    window.location.href = 'visor_cliente.php?id_evento=' + data.id_evento;
                }
            }

            // Mostrar horarios (después de una venta, sin cambiar de evento)
            if (data.accion === 'MOSTRAR_HORARIOS') {
                console.log('🕐 Mostrando vista de horarios');
                funcionSeleccionada = false;

                // Ocultar overlay de gracias si está activo
                const overlayGracias = document.getElementById('overlayGracias');
                if (overlayGracias) overlayGracias.classList.remove('active');

                // Cambiar a vista de horarios
                const viewHorarios = document.getElementById('viewHorarios');
                const viewMapa = document.getElementById('viewMapa');

                if (viewMapa) viewMapa.classList.add('hidden');
                if (viewHorarios) viewHorarios.classList.remove('hidden');

                // Resetear la vista del mapa
                resetearVista();
            }
        };

        // ===== FUNCIONES DE INTERFAZ =====

        function parseAsiento(id) {
            const pb = String(id).match(/^PB(\d+)-(\d+)$/i);
            if (pb) return { fila: 'PB' + pb[1], numero: pb[2] };
            const p = String(id).match(/^P(\d+)$/i);
            if (p) return { fila: 'P', numero: p[1] };
            const std = String(id).match(/^([A-Z]+)(\d+)$/i);
            if (std) return { fila: std[1].toUpperCase(), numero: std[2] };
            return { fila: id, numero: '' };
        }

        function htmlAsiento(id) {
            const { fila, numero } = parseAsiento(id);
            if (!numero) {
                return `<div class="item-asiento-info"><span class="item-fila-val">${fila}</span></div>`;
            }
            return `
                <div class="item-asiento-info">
                    <span class="item-fila-label">Fila</span>
                    <span class="item-fila-val">${fila}</span>
                    <span class="item-asiento-sep">·</span>
                    <span class="item-num-label">No.</span>
                    <span class="item-num-val">${numero}</span>
                </div>`;
        }

        function toggleCarritoFloat(visible) {
            const carrito = document.getElementById('carritoFloat');
            if (!carrito) return;
            carrito.classList.toggle('oculto', !visible);
        }

        function resetearVista() {
            document.querySelectorAll('.seat.client-selected').forEach(el => {
                el.classList.remove('client-selected');
            });
            const lista = document.getElementById('listaCarrito');
            if (lista) lista.innerHTML = '';
            const total = document.getElementById('txtTotal');
            const totalWrapper = document.querySelector('.total-amount');
            if (total) total.textContent = '0.00';
            if (totalWrapper) {
                totalWrapper.classList.remove('gratis', 'cortesia');
                const currency = totalWrapper.querySelector('.currency');
                if (currency) currency.style.display = '';
            }
            actualizarBannersVisor(false);
            const contador = document.getElementById('contadorAsientos');
            if (contador) contador.textContent = '';
            toggleCarritoFloat(false);
            ocultarInfoDescuento();
        }

        function actualizarInterfaz(carrito, total, descuentoInfo = null) {
            const lista = document.getElementById('listaCarrito');
            if (!lista) return;

            document.querySelectorAll('.seat.client-selected').forEach(el => {
                el.classList.remove('client-selected');
            });

            if (carrito.length === 0) {
                lista.innerHTML = '';
                const totalEl = document.getElementById('txtTotal');
                const totalWrapper = document.querySelector('.total-amount');
                if (totalEl) totalEl.textContent = '0.00';
                if (totalWrapper) totalWrapper.classList.remove('gratis', 'cortesia');
                const contador = document.getElementById('contadorAsientos');
                if (contador) contador.textContent = '';
                toggleCarritoFloat(false);
                ocultarInfoDescuento();
                actualizarBannersVisor(false);
                return;
            }

            toggleCarritoFloat(true);
            lista.innerHTML = '';

            // Actualizar contador de asientos
            const contador = document.getElementById('contadorAsientos');
            if (contador) {
                contador.textContent = carrito.length;
            }

            // Limitar el delay máximo para que las animaciones no se amontonen
            const maxDelay = 0.3;
            const delayStep = Math.min(0.05, maxDelay / carrito.length);

            let hayDescuento = false;
            let hayCortesia = false;
            let totalDescuento = 0;

            carrito.forEach((item, index) => {
                const el = document.getElementById('seat-' + item.id);
                if (el) el.classList.add('client-selected');

                const esCortesia = item.tipo_boleto === 'cortesia';
                const tieneDescuento = item.descuento_aplicado && parseFloat(item.descuento_aplicado) > 0;
                const precioBase = parseFloat(item.precio || 0);
                const descuento = parseFloat(item.descuento_aplicado || 0);
                const precioFinal = esCortesia ? 0 : Math.max(0, precioBase - descuento);

                if (tieneDescuento) { hayDescuento = true; totalDescuento += descuento; }
                if (esCortesia) hayCortesia = true;

                const itemDiv = document.createElement('div');
                itemDiv.className = 'carrito-item';
                if (tieneDescuento) itemDiv.classList.add('con-descuento');
                if (esCortesia) itemDiv.classList.add('cortesia');
                itemDiv.style.animationDelay = (index * delayStep) + 's';

                // Generar badge de tipo/descuento
                let badgeHTML = '';
                if (esCortesia) {
                    badgeHTML = '<span class="descuento-badge cortesia"><i class="bi bi-gift-fill"></i> Cortesía</span>';
                } else if (tieneDescuento) {
                    // Mostrar claramente el valor del descuento
                    let descText = '';
                    if (descuento > 0) {
                        descText = `-$${descuento.toFixed(2)}`;
                    } else {
                        descText = '%';
                    }
                    badgeHTML = `<span class="descuento-badge descuento"><i class="bi bi-tag-fill"></i> ${descText}</span>`;
                }

                // Generar HTML del precio
                let precioHTML = '';
                if (esCortesia) {
                    precioHTML = `
                <div class="precio-wrapper">
                    ${precioBase > 0 ? `<span class="item-precio tachado precio-strike">$${precioBase.toFixed(2)}</span>` : ''}
                    <span class="item-precio-final cortesia">CORTESÍA</span>
                </div>
            `;
                } else if (tieneDescuento) {
                    precioHTML = `
                <div class="precio-wrapper">
                    <span class="item-precio tachado precio-strike">$${precioBase.toFixed(2)}</span>
                    <span class="item-precio-final">$${precioFinal.toFixed(2)}</span>
                </div>
            `;
                } else {
                    const precioInfo = textoPrecioVisor(precioBase, item.categoria, item.tipo_boleto);
                    if (precioInfo.clase) {
                        precioHTML = `<div class="item-precio-final ${precioInfo.clase}">${precioInfo.texto}</div>`;
                    } else {
                        precioHTML = `<div class="item-precio">${precioInfo.texto}</div>`;
                    }
                }

                itemDiv.innerHTML = `
            <div>
                ${htmlAsiento(item.id)}
                <div style="display:flex; gap:4px; align-items:center; margin-top:2px;">
                    ${badgeHTML}
                </div>
                <div class="item-categoria">${item.categoria}</div>
            </div>
            ${precioHTML}
        `;
                lista.appendChild(itemDiv);
            });

            actualizarBannersVisor(hayCortesia);

            // Mostrar notificación si hay cortesía o descuento nuevo
            if (hayCortesia && !window.ultimoEstadoCortesia) {
                mostrarNotificacionVisor('🎁 Este boleto va de parte de la casa — ¡Cortesía!', 'cortesia-notif');
            }
            if (hayDescuento && !window.ultimoEstadoDescuento) {
                mostrarNotificacionVisor('🏷️ Descuento aplicado: -$' + totalDescuento.toFixed(2), 'descuento-notif');
            }
            window.ultimoEstadoCortesia = hayCortesia;
            window.ultimoEstadoDescuento = hayDescuento;

            // Scroll suave al último elemento después de la animación
            setTimeout(() => {
                lista.scrollTop = lista.scrollHeight;
            }, 100);

            const totalEl = document.getElementById('txtTotal');
            const totalWrapper = document.querySelector('.total-amount');
            if (totalEl) {
                const totalInfo = textoTotalVisor(total, hayCortesia);
                totalEl.textContent = totalInfo.texto;
                if (totalWrapper) {
                    totalWrapper.classList.remove('gratis', 'cortesia');
                    if (totalInfo.clase) totalWrapper.classList.add(totalInfo.clase);
                    const currency = totalWrapper.querySelector('.currency');
                    if (currency) {
                        const sinMoneda = totalInfo.clase === 'gratis' || totalInfo.clase === 'cortesia';
                        currency.style.display = sinMoneda ? 'none' : '';
                    }
                }

                // Mostrar info de descuento
                if (totalDescuento > 0) {
                    mostrarInfoDescuento(totalDescuento);
                } else {
                    ocultarInfoDescuento();
                }
            }
        }

        // Mostrar notificación animada en el visor
        function mostrarNotificacionVisor(mensaje, tipo = '') {
            // Remover notificación anterior
            const anterior = document.querySelector('.visor-notificacion');
            if (anterior) anterior.remove();

            const notif = document.createElement('div');
            notif.className = 'visor-notificacion ' + tipo;
            notif.innerHTML = `<i class="bi bi-stars"></i><span>${mensaje}</span>`;
            document.body.appendChild(notif);

            // Animar entrada
            setTimeout(() => notif.classList.add('show'), 50);

            // Ocultar después de 5 segundos (aumentado para mejor visibilidad)
            setTimeout(() => {
                notif.classList.remove('show');
                setTimeout(() => notif.remove(), 500);
            }, 5000);
        }

        // Mostrar info de descuento total
        function mostrarInfoDescuento(monto) {
            let infoEl = document.getElementById('descuentoTotalInfo');
            if (!infoEl) {
                const totalWrapper = document.querySelector('.total-amount');
                if (totalWrapper && totalWrapper.parentElement) {
                    infoEl = document.createElement('div');
                    infoEl.id = 'descuentoTotalInfo';
                    infoEl.className = 'total-descuento-info';
                    totalWrapper.parentElement.appendChild(infoEl);
                }
            }
            if (infoEl) {
                infoEl.innerHTML = `<i class="bi bi-tag-fill"></i> Ahorro: $${monto.toFixed(2)}`;
            }
        }

        // Ocultar info de descuento
        function ocultarInfoDescuento() {
            const infoEl = document.getElementById('descuentoTotalInfo');
            if (infoEl) infoEl.remove();
            window.ultimoEstadoCortesia = false;
            window.ultimoEstadoDescuento = false;
        }

        function marcarVendidos(ids) {
            if (!ids || !Array.isArray(ids)) return;

            document.querySelectorAll('.seat.vendido').forEach(el => el.classList.remove('vendido'));
            ids.forEach(id => {
                const el = document.getElementById('seat-' + id);
                if (el) el.classList.add('vendido');
            });
        }

        function ajustarMapa() {
            const mapa = document.getElementById('mapaContenido');
            if (mapa) {
                const container = mapa.parentElement;
                const esTeatro420 = document.getElementById('viewMapa')?.classList.contains('mapa-teatro-420');
                const factor = esTeatro420 ? 0.97 : 0.88;
                const minScale = esTeatro420 ? 0.5 : 0.35;
                const scale = Math.min(
                    container.clientWidth / mapa.scrollWidth,
                    container.clientHeight / mapa.scrollHeight
                ) * factor;
                mapa.style.transform = `scale(${Math.max(scale, minScale)})`;
            }
        }

        // Inicialización
        window.onload = () => {
            console.log('🎬 Visor Cliente cargado');
            ajustarMapa();
        };
        window.onresize = ajustarMapa;
    </script>
    <script src="js/teatro-sync.js"></script>
    <script>
        // El visor cliente NO debe auto-recargar ni mostrar notificaciones
        // Solo escucha cambios de venta para actualizar asientos vendidos
        if (typeof TeatroSync !== 'undefined') {
            TeatroSync.init({
                eventoId: <?= $id_evento ?: 'null' ?>,
                autoReload: false // NO recargar automáticamente
            });

            // Solo escuchar ventas para marcar asientos
            TeatroSync.on('venta', (data) => {
                console.log('[Visor] Venta detectada');
                if (data.datos && data.datos.asientos) {
                    data.datos.asientos.forEach(asiento => {
                        const el = document.getElementById('seat-' + asiento);
                        if (el) el.classList.add('vendido');
                    });
                }
            });
        }
    </script>
<?php endif; ?>
</body>

</html>