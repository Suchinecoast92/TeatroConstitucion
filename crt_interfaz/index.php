<?php
// 1. CONEXIÓN A LA BD
include "../conexion.php"; // Ajusta la ruta si es necesario

// 2. OBTENER EVENTOS ACTIVOS CON SU PRÓXIMA FUNCIÓN (Y SU ID DE FUNCIÓN)
$query = "
    SELECT 
        e.*,
        f1.id_funcion AS proxima_id_funcion,
        f1.fecha_hora AS proxima_funcion_fecha
    FROM evento e
    INNER JOIN funciones f1 ON f1.id_evento = e.id_evento
    WHERE e.finalizado = 0
      AND f1.fecha_hora = (
            SELECT MIN(f.fecha_hora)
            FROM funciones f
            WHERE f.id_evento = e.id_evento
              AND f.fecha_hora >= NOW()
      )
    ORDER BY proxima_funcion_fecha ASC;
";

$resultado = $conn->query($query);

// 2.1 Obtener boletos vendidos por función para detectar si la próxima está agotada
$vendidos_por_funcion = [];
$sqlVendidos = "
    SELECT id_evento, id_funcion, COUNT(*) AS vendidos
    FROM boletos
    WHERE estatus = 1
    GROUP BY id_evento, id_funcion
";

$resVendidos = $conn->query($sqlVendidos);
if ($resVendidos) {
    while ($rowV = $resVendidos->fetch_assoc()) {
        $idEv = (int) $rowV['id_evento'];
        $idFun = (int) $rowV['id_funcion'];
        $vendidos_por_funcion[$idEv][$idFun] = (int) $rowV['vendidos'];
    }
}

// Separar eventos: esta semana vs próximos
$eventos_esta_semana = [];
$eventos_proximos = [];

if ($resultado && $resultado->num_rows > 0) {
    $ahora = new DateTime();
    $fin_semana = clone $ahora;

    $fin_semana->modify('Sunday this week');
    $fin_semana->setTime(23, 59, 59);

    while ($evento = $resultado->fetch_assoc()) {
        $fecha_funcion = new DateTime($evento['proxima_funcion_fecha']);

        // Calcular total de asientos del evento a partir de mapa_json
        $total_asientos = 0;
        if (!empty($evento['mapa_json'])) {
            $mapa_guardado = json_decode($evento['mapa_json'], true);
            if (is_array($mapa_guardado)) {
                $total_asientos = count($mapa_guardado);
            }
        }

        $id_ev = (int) $evento['id_evento'];
        $id_fun = isset($evento['proxima_id_funcion']) ? (int) $evento['proxima_id_funcion'] : 0;
        $vendidos = ($id_fun > 0 && isset($vendidos_por_funcion[$id_ev][$id_fun]))
            ? $vendidos_por_funcion[$id_ev][$id_fun]
            : 0;

        $evento['agotado_proxima'] = ($total_asientos > 0 && $vendidos >= $total_asientos);
        $evento['total_asientos'] = $total_asientos;
        $evento['vendidos'] = $vendidos;
        $evento['disponibles'] = max(0, $total_asientos - $vendidos);
        $evento['funciones'] = [];

        if ($fecha_funcion <= $fin_semana) {
            $eventos_esta_semana[] = $evento;
        } else {
            $eventos_proximos[] = $evento;
        }
    }
}

// 2.2 Todas las funciones futuras por evento (detalle móvil / horarios)
$funciones_por_evento = [];
$sqlFunciones = "
    SELECT id_evento, id_funcion, fecha_hora
    FROM funciones
    WHERE fecha_hora >= NOW()
    ORDER BY fecha_hora ASC
";
$resFunc = $conn->query($sqlFunciones);
if ($resFunc) {
    while ($rowF = $resFunc->fetch_assoc()) {
        $idEv = (int) $rowF['id_evento'];
        $idFun = (int) $rowF['id_funcion'];
        $funciones_por_evento[$idEv][] = [
            'id_funcion' => $idFun,
            'fecha_hora' => $rowF['fecha_hora'],
        ];
    }
}

$adjuntarFunciones = function (array &$lista) use ($funciones_por_evento, $vendidos_por_funcion) {
    foreach ($lista as &$ev) {
        $idEv = (int) $ev['id_evento'];
        $total = (int) ($ev['total_asientos'] ?? 0);
        $funcs = [];
        foreach ($funciones_por_evento[$idEv] ?? [] as $f) {
            $idFun = (int) $f['id_funcion'];
            $vendidos = $vendidos_por_funcion[$idEv][$idFun] ?? 0;
            $agotado = ($total > 0 && $vendidos >= $total);
            $funcs[] = [
                'id_funcion' => $idFun,
                'fecha_hora' => $f['fecha_hora'],
                'agotado' => $agotado,
                'vendidos' => $vendidos,
                'disponibles' => max(0, $total - $vendidos),
                'total_asientos' => $total,
            ];
        }
        $ev['funciones'] = $funcs;
    }
    unset($ev);
};
$adjuntarFunciones($eventos_esta_semana);
$adjuntarFunciones($eventos_proximos);

// No enviar mapa_json al navegador (peso innecesario)
$quitarMapa = function (array &$lista): void {
    foreach ($lista as &$ev) {
        unset($ev['mapa_json']);
    }
    unset($ev);
};
$quitarMapa($eventos_esta_semana);
$quitarMapa($eventos_proximos);
$eventos_movil = array_merge($eventos_esta_semana, $eventos_proximos);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teatro Constitución · Apatzingan</title>
    <link rel="icon" href="imagenes_teatro/nat.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        body {
            background-image: url('imagenes_teatro/TeatroNoche1.jpg');
            background-size: cover;
            background-position: center center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            color: #e8e8e8;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            min-height: 100vh;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: inherit;
            filter: blur(0px);
            z-index: -1;
        }

        .site-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(10, 10, 12, 0.9);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        .header-inner {
            width: 100%;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            padding: 12px 20px 12px 0;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 20px;
            text-decoration: none;
            color: #ffffff;
            margin-right: auto;
        }

        .brand-logo {
            width: 50px;
            height: 50px;
            margin-left: 20px;
            border-radius: 7px;
            display: grid;
            place-items: center;
        }

        .brand-logo .logo-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .brand-name {
            font-weight: 600;
            font-size: 1.55rem;
        }

        .site-nav {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-left: auto;
        }

        /* Desktop: enlaces alineados al header (nav vive fuera del header por el drawer móvil) */
        @media (min-width: 1101px) {
            .site-nav {
                position: fixed;
                top: 0;
                right: 0;
                left: 0;
                height: 74px;
                z-index: 1001;
                justify-content: flex-end;
                align-items: center;
                padding: 0 24px 0 180px;
                margin: 0;
                background: transparent;
                pointer-events: none;
                box-sizing: border-box;
            }

            .site-nav a {
                pointer-events: auto;
            }

            .hamburger {
                display: none !important;
            }
        }

        .site-nav a {
            color: #e8e8e8;
            text-decoration: none;
            font-weight: 600;
            transition: color .2s ease, transform .2s ease;
        }

        .site-nav a:not(.cta) {
            padding: 8px 10px;
            border-radius: 10px;
            transition: color .2s ease, transform .2s ease, background-color .2s ease, box-shadow .2s ease;
        }

        .site-nav a:not(.cta):hover,
        .site-nav a:not(.cta).active {
            color: #ffffff;
            transform: translateY(-1px);
            background: rgba(148, 163, 184, 0.25);
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.6);
        }

        .cta {
            margin-left: 6px;
            padding: 10px 14px;
            border-radius: 8px;
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: #fff !important;
            font-weight: 700;
            border: 0;
            box-shadow: 0 8px 18px rgba(15, 23, 42, .55);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
        }

        .cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(15, 23, 42, .75);
            filter: brightness(1.03);
        }

        /* Botón de cambio de tema (oscuro/claro) */
        .theme-toggle-btn {
            margin-left: 8px;
            width: 38px;
            height: 38px;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, 0.7);
            background: transparent;
            color: #e5e7eb;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color .2s ease, box-shadow .2s ease, transform .2s ease, color .2s ease, border-color .2s ease;
        }

        .theme-toggle-btn:hover {
            background: rgba(148, 163, 184, 0.2);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.7);
            border-color: rgba(209, 213, 219, 0.9);
            transform: translateY(-1px);
        }

        .theme-toggle-btn .theme-icon {
            font-size: 1.1rem;
        }

        .hamburger {
            display: none;
            background: transparent;
            color: #e8e8e8;
            border: 1px solid rgba(255, 255, 255, 0.15);
            width: 42px;
            height: 42px;
            border-radius: 8px;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            z-index: 1002;
        }

        /* Capas solo móvil (ocultas en desktop) */
        .mobile-only {
            display: none;
        }

        .mobile-home {
            display: none;
        }

        .mobile-bottom-bar {
            display: none;
        }

        .header-cartelera {
            display: none;
        }

        .mobile-event-detail {
            display: none;
        }

        @media (max-width: 1100px) {
            body {
                background-attachment: scroll;
                padding-bottom: env(safe-area-inset-bottom, 0px);
            }

            .desktop-only {
                display: none !important;
            }

            .mobile-only {
                display: block;
            }

            .header-inner {
                padding: 10px 14px;
                gap: 8px;
                position: relative;
            }

            .brand {
                gap: 10px;
                margin-right: 0;
                min-width: 0;
                flex: 1;
            }

            .brand-logo {
                width: 40px;
                height: 40px;
                margin-left: 0;
                flex-shrink: 0;
            }

            .brand-name {
                font-size: 0.95rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .header-cartelera {
                display: inline-flex !important;
                align-items: center;
                justify-content: center;
                gap: 6px;
                flex-shrink: 0;
                margin-left: auto;
                padding: 8px 14px;
                border-radius: 999px;
                border: 1px solid rgba(255, 255, 255, 0.28);
                background: linear-gradient(160deg, rgba(255, 255, 255, 0.92), rgba(220, 220, 224, 0.88));
                color: #0a0a0a !important;
                font-weight: 750;
                font-size: 0.82rem;
                text-decoration: none;
                white-space: nowrap;
                line-height: 1.2;
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.7);
            }
            .header-cartelera i {
                font-size: 0.95rem;
            }

            /* Panel fuera del header (evita que backdrop-filter atrape position:fixed) */
            #mainNav.site-nav {
                position: fixed;
                top: 0;
                right: 0;
                bottom: 0;
                left: auto;
                width: min(86vw, 320px);
                max-width: 100%;
                height: 100dvh;
                margin: 0;
                display: flex !important;
                flex-direction: column !important;
                flex-wrap: nowrap !important;
                align-items: stretch !important;
                justify-content: flex-start;
                padding: calc(64px + env(safe-area-inset-top, 0px)) 20px 24px;
                gap: 6px;
                background: rgba(8, 12, 22, 0.98);
                transform: translateX(105%);
                transition: transform .28s ease;
                z-index: 1102;
                box-shadow: -12px 0 40px rgba(0, 0, 0, 0.45);
                border-left: 1px solid rgba(255, 255, 255, 0.08);
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
            }

            #mainNav.site-nav.open {
                transform: translateX(0);
            }

            #mainNav.site-nav a:not(.cta) {
                display: block;
                width: 100%;
                padding: 14px 12px;
                font-size: 1.05rem;
                box-sizing: border-box;
            }

            #mainNav.site-nav .cta {
                display: none !important;
            }

            .hamburger {
                display: inline-flex;
                margin-left: 0;
                position: relative;
                z-index: 1103;
            }

            .mobile-bottom-bar {
                display: none !important;
            }

            .nav-backdrop {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.55);
                z-index: 1101;
            }

            .nav-backdrop.show {
                display: block;
            }

            .hero {
                padding: 0;
                max-width: none;
            }

            .site-footer {
                display: none;
            }

            /* Home móvil: grid 2 columnas */
            .mobile-home {
                display: block;
                padding: 16px 14px 24px;
            }

            .mobile-home-title {
                font-size: 1.35rem;
                font-weight: 700;
                color: #fff;
                margin: 0 0 16px;
                letter-spacing: -0.02em;
            }

            .mobile-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 14px 12px;
            }

            .mobile-poster {
                display: block;
                text-decoration: none;
                color: inherit;
                background: transparent;
                border: 0;
                padding: 0;
                text-align: left;
                cursor: pointer;
                width: 100%;
            }

            .mobile-poster-img {
                width: 100%;
                aspect-ratio: 2 / 3;
                border-radius: 14px;
                overflow: hidden;
                background: linear-gradient(160deg, rgba(255, 255, 255, 0.1), rgba(0, 0, 0, 0.35));
                border: 1px solid rgba(255, 255, 255, 0.14);
                box-shadow: 0 12px 32px rgba(0, 0, 0, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.1);
            }

            .mobile-poster-img img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }

            .mobile-poster-title {
                margin-top: 8px;
                font-size: 0.88rem;
                font-weight: 700;
                color: #f8fafc;
                line-height: 1.25;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .mobile-empty {
                grid-column: 1 / -1;
                text-align: center;
                padding: 48px 16px;
                color: #cbd5e1;
            }

            /* Detalle evento móvil — modal semipequeño */
            .mobile-event-detail {
                display: none;
                position: fixed;
                inset: 0;
                z-index: 1200;
                align-items: center;
                justify-content: center;
                padding: 18px 16px;
                box-sizing: border-box;
                color: #f1f5f9;
                overflow: hidden;
            }

            .mobile-event-detail.open {
                display: flex;
            }

            .mobile-detail-bg {
                display: none;
            }

            .mobile-detail-scrim {
                position: absolute;
                inset: 0;
                background: rgba(0, 0, 0, 0.58);
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
            }

            .mobile-detail-content {
                position: relative;
                z-index: 1;
                width: min(100%, 400px);
                max-height: min(72vh, 520px);
                min-height: 0;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
                padding: 14px 16px 18px;
                display: flex;
                flex-direction: column;
                border-radius: 18px;
                background: linear-gradient(160deg, rgba(255, 255, 255, 0.12), rgba(12, 12, 14, 0.92));
                backdrop-filter: blur(22px) saturate(115%);
                -webkit-backdrop-filter: blur(22px) saturate(115%);
                border: 1px solid rgba(255, 255, 255, 0.14);
                box-shadow: 0 24px 56px rgba(0, 0, 0, 0.55), inset 0 1px 0 rgba(255, 255, 255, 0.12);
                animation: mobileDetailIn .28s cubic-bezier(.22, 1, .36, 1);
            }

            @keyframes mobileDetailIn {
                from { opacity: 0; transform: translateY(12px) scale(.97); }
                to { opacity: 1; transform: none; }
            }

            @media (prefers-reduced-motion: reduce) {
                .mobile-detail-content { animation: none; }
            }

            .mobile-detail-top {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 4px;
            }

            .mobile-detail-back {
                width: 36px;
                height: 36px;
                border-radius: 999px;
                border: 1px solid rgba(255, 255, 255, 0.2);
                background: rgba(30, 41, 59, 0.85);
                color: #fff;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                flex-shrink: 0;
            }

            .mobile-detail-heading {
                font-size: 0.95rem;
                font-weight: 700;
                margin: 0;
            }

            .mobile-detail-title {
                font-size: 1.35rem;
                font-weight: 800;
                line-height: 1.2;
                margin: 8px 0 6px;
            }

            .mobile-detail-desc {
                font-size: 0.88rem;
                line-height: 1.45;
                color: rgba(241, 245, 249, 0.88);
                margin-bottom: 14px;
                max-height: 4.5em;
                overflow: hidden;
                display: -webkit-box;
                -webkit-line-clamp: 3;
                -webkit-box-orient: vertical;
            }

            .mobile-horarios-label {
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                color: #94a3b8;
                margin-bottom: 8px;
                font-weight: 700;
            }

            .mobile-horarios {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                overflow: visible;
                padding-bottom: 0;
                margin-bottom: 0;
            }

            .mobile-horario {
                flex: 0 0 auto;
                min-width: 100px;
                padding: 10px 12px;
                border-radius: 12px;
                border: 1px solid rgba(255, 255, 255, 0.28);
                background: linear-gradient(160deg, rgba(255, 255, 255, 0.12), rgba(0, 0, 0, 0.35));
                color: #fff;
                text-decoration: none;
                text-align: center;
                font-weight: 700;
                font-size: 0.88rem;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
            }

            .mobile-horario .dia {
                display: block;
                font-size: 0.7rem;
                font-weight: 600;
                color: rgba(228, 228, 231, 0.75);
                margin-bottom: 3px;
            }

            .mobile-horario.agotado {
                opacity: 0.45;
                pointer-events: none;
                border-color: rgba(255, 255, 255, 0.12);
            }

            .mobile-horario:not(.agotado):active {
                background: rgba(255, 255, 255, 0.18);
                border-color: rgba(255, 255, 255, 0.45);
            }

            body.mobile-detail-open {
                overflow: hidden;
            }
        }


        .hero {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px 80px;
        }

        .section-title {
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 30px;
            text-align: left;
            text-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
            letter-spacing: -0.02em;
        }

        /* HERO CAROUSEL */
        .eventos-semana-section {
            margin-bottom: 60px;
            margin-left: -20px;
            margin-right: -20px;
            padding: 0;
        }

        .hero-carrusel {
            position: relative;
            width: 100%;
            height: 500px;
            overflow: hidden;
            border-radius: 20px;
        }

        .hero-slide {
            display: none;
            position: relative;
            width: 100%;
            height: 100%;
            text-decoration: none;
            color: inherit;
        }

        .hero-slide.active {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 40px;
            padding: 40px 60px;
            background: linear-gradient(160deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.04) 40%, rgba(0, 0, 0, 0.3));
            backdrop-filter: blur(24px) saturate(115%);
            -webkit-backdrop-filter: blur(24px) saturate(115%);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 20px;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.12);
            animation: fadeIn 0.6s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.98);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        .hero-imagen {
            width: 100%;
            height: 100%;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            background: rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-imagen img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.4s ease;
        }

        .hero-slide:hover .hero-imagen img {
            transform: scale(1.05);
        }

        .hero-contenido {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding-right: 40px;
        }

        .hero-titulo {
            font-size: 3rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 20px;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            line-height: 1.1;
        }

        .hero-descripcion {
            font-size: 1.15rem;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.7;
            margin-bottom: 25px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            overflow-wrap: break-word;
            word-wrap: break-word;
            word-break: break-word;
        }

        .hero-fecha {
            font-size: 1.2rem;
            color: #e5e7eb;
            font-weight: 700;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .hero-btn {
            background: linear-gradient(160deg, rgba(255, 255, 255, 0.92), rgba(220, 220, 224, 0.88));
            color: #0a0a0a;
            padding: 16px 40px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 750;
            text-decoration: none;
            display: inline-block;
            width: fit-content;
            transition: all 0.3s ease;
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.7);
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, 0.28);
        }

        .hero-btn:hover {
            transform: translateY(-2px);
            color: #000;
            filter: brightness(1.05);
            box-shadow: 0 14px 32px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.7);
            background: linear-gradient(160deg, #fff, rgba(230, 230, 234, 0.95));
        }

        .hero-btn-agotado {
            background: linear-gradient(135deg, rgba(70, 70, 74, 0.95), rgba(40, 40, 44, 0.95));
            color: #e4e4e7;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            cursor: default;
            border-color: rgba(255, 255, 255, 0.15);
        }

        .hero-btn-agotado:hover {
            transform: none;
            filter: none;
            color: #e4e4e7;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            background: linear-gradient(135deg, rgba(70, 70, 74, 0.95), rgba(40, 40, 44, 0.95));
        }

        .btn-hero-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 20;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .btn-hero-nav:hover {
            background: rgba(75, 85, 99, 0.95);
            border-color: rgba(148, 163, 184, 1);
            transform: translateY(-50%) scale(1.1);
        }

        .btn-hero-nav.prev {
            left: 20px;
        }

        .btn-hero-nav.next {
            right: 20px;
        }

        .hero-indicadores {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 15;
        }

        .indicador {
            width: 40px;
            height: 4px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 2px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .indicador.active {
            background: #9ca3af;
            width: 60px;
        }

        /* PROXIMOS EVENTOS */
        .carrusel-horizontal {
            position: relative;
            padding: 0 60px;
        }

        .eventos-scroll-container {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            scroll-behavior: smooth;
            scrollbar-width: none;
            -ms-overflow-style: none;
            padding: 10px 0;
        }

        .eventos-scroll-container::-webkit-scrollbar {
            display: none;
        }

        .evento-card {
            flex: 0 0 auto;
            width: 280px;
            background: linear-gradient(160deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.04) 40%, rgba(0, 0, 0, 0.3));
            backdrop-filter: blur(22px) saturate(115%);
            -webkit-backdrop-filter: blur(22px) saturate(115%);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.14);
            box-shadow: 0 20px 48px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.12);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            color: inherit;
            display: block;
            cursor: pointer;
        }

        .evento-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 28px 64px rgba(0, 0, 0, 0.55), inset 0 1px 0 rgba(255, 255, 255, 0.16);
            border-color: rgba(255, 255, 255, 0.22);
        }

        .evento-card-imagen {
            width: 100%;
            height: 380px;
            overflow: hidden;
            position: relative;
            background: rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .evento-card-imagen img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.4s ease;
        }

        .evento-card:hover .evento-card-imagen img {
            transform: scale(1.08);
        }

        .evento-card-info {
            padding: 20px;
            background: linear-gradient(to bottom, rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.6));
        }

        .evento-card-titulo {
            font-size: 1.2rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 10px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .evento-card-fecha {
            font-size: 0.9rem;
            color: #e5e7eb;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .evento-card-disponibles {
            font-size: 0.85rem;
            color: #d4d4d8;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
        }

        .evento-card-disponibles.agotado {
            color: #a1a1aa;
        }

        .evento-card-disponibles.pocos {
            color: #fafafa;
        }

        .boletos-disponibles {
            font-size: 0.95rem;
            color: #d4d4d8;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
        }

        .boletos-disponibles.agotado {
            color: #a1a1aa;
        }

        .boletos-disponibles.pocos {
            color: #fafafa;
        }

        .btn-nav-carrusel {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            background: rgba(75, 85, 99, 0.95);
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
        }

        .btn-nav-carrusel:hover {
            background: rgba(55, 65, 81, 1);
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.8);
        }

        .btn-nav-carrusel.prev {
            left: 5px;
        }

        .btn-nav-carrusel.next {
            right: 5px;
        }

        .eventos-proximos-section {
            margin-bottom: 60px;
        }

        .no-eventos-msg {
            background: linear-gradient(160deg, rgba(255, 255, 255, 0.1), rgba(0, 0, 0, 0.35));
            backdrop-filter: blur(22px) saturate(115%);
            -webkit-backdrop-filter: blur(22px) saturate(115%);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.1);
            color: rgba(249, 250, 251, 0.95);
            padding: 40px;
            text-align: center;
        }

        /* Footer */
        .site-footer {
            background: rgba(10, 10, 12, 0.95);
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            color: #cfd3d7;
            margin-top: 80px;
        }

        .footer-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 28px 20px;
            display: grid;
            grid-template-columns: 1.2fr 1fr 1fr 1fr;
            gap: 24px;
        }

        .footer-col h4 {
            color: #ffffff;
            font-size: 1rem;
            margin-bottom: 12px;
            font-weight: 700;
        }

        .footer-col p,
        .footer-col li,
        .footer-col a,
        .footer-col span {
            font-size: 0.95rem;
            color: #cfd3d7;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin: 8px 0;
        }

        .footer-links a {
            text-decoration: none;
            color: #cfd3d7;
            transition: color .2s ease;
        }

        .footer-links a:hover {
            color: #fff;
        }

        .social {
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }

        .social a {
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            color: #e8e8e8;
            text-decoration: none;
            transition: all .2s ease;
        }

        .social a:hover {
            color: #fff;
            border-color: rgba(255, 255, 255, 0.35);
            transform: translateY(-2px);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            padding: 14px 20px;
            color: #aeb4ba;
            font-size: 0.9rem;
        }

        .footer-bottom-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .muted {
            color: #aeb4ba;
        }

        @media (max-width: 1100px) {
            .footer-inner {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 600px) {
            .footer-inner {
                grid-template-columns: 1fr;
            }

            .footer-bottom-inner {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        /* Celular / tablet en landscape: 3 eventos por fila, póster completo */
        @media (max-width: 1100px) and (orientation: landscape) {
            .header-inner {
                padding: 6px 12px;
            }
            .brand-logo {
                width: 32px;
                height: 32px;
            }
            .brand-name {
                font-size: 0.82rem;
            }
            .hamburger {
                width: 36px;
                height: 36px;
            }
            .mobile-home {
                padding: 8px 10px 12px;
            }
            .mobile-home-title {
                font-size: 1rem;
                margin: 0 0 8px;
            }
            .mobile-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 8px 10px;
                align-items: start;
            }
            .mobile-poster {
                min-width: 0;
                width: 100%;
            }
            /* Contenedor a ancho completo; proporción tipo póster */
            .mobile-poster-img {
                width: 100%;
                height: auto;
                max-height: none;
                aspect-ratio: 2 / 3;
                margin: 0;
                border-radius: 10px;
            }
            .mobile-poster-img img {
                object-fit: cover;
                object-position: center top;
            }
            .mobile-poster-title {
                width: 100%;
                margin-top: 6px;
                font-size: 0.72rem;
                text-align: center;
                -webkit-line-clamp: 1;
            }
            .mobile-empty {
                padding: 16px 12px;
            }
            .mobile-empty i {
                font-size: 1.75rem !important;
            }
            .header-cartelera {
                padding: 6px 10px;
                font-size: 0.75rem;
                gap: 5px;
            }
            .mobile-detail-content {
                width: min(92vw, 480px);
                max-height: min(86vh, 360px);
                padding: 12px 14px 14px;
            }
            .mobile-detail-title {
                font-size: 1.2rem;
                margin: 4px 0 4px;
            }
            .mobile-detail-desc {
                font-size: 0.82rem;
                margin-bottom: 10px;
                -webkit-line-clamp: 2;
                max-height: 3em;
            }
            .mobile-horarios {
                gap: 6px;
            }
            .mobile-horario {
                min-width: 92px;
                padding: 8px 10px;
                font-size: 0.82rem;
            }
            #mainNav.site-nav {
                width: min(70vw, 280px);
                padding-top: calc(52px + env(safe-area-inset-top, 0px));
            }
        }
    </style>
</head>

<body>
    <div class="nav-backdrop" id="navBackdrop" aria-hidden="true"></div>
    <nav class="site-nav" id="mainNav" aria-label="Principal">
        <a href="#inicio" class="active" data-nav-home>Inicio</a>
        <a href="acerca.php">Acerca del teatro</a>
        <a href="contacto.php">Contacto / Reservaciones</a>
        <a href="cartelera_cliente.php" class="cta">Ver cartelera completa <i class="bi bi-arrow-right"></i></a>
    </nav>
    <header class="site-header">
        <div class="header-inner">
            <a href="#inicio" class="brand" aria-label="Inicio" id="brandHome">
                <div class="brand-logo"><img src="imagenes_teatro/nat.png" alt="Teatro Constitución" class="logo-img">
                </div>
                <div class="brand-name">Teatro Constitución · Apatzingan</div>
            </a>
            <a href="cartelera_cliente.php" class="header-cartelera mobile-only" id="headerCartelera">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                <span>Cartelera</span>
            </a>
            <button class="hamburger" id="hamburgerBtn" aria-label="Menú" aria-expanded="false" aria-controls="mainNav">
                <i class="bi bi-list" style="font-size:1.25rem"></i>
            </button>
        </div>
    </header>

    <main class="hero" id="inicio">
        <section class="eventos-semana-section desktop-only">
            <div class="hero-carrusel" id="hero-carrusel">
                <button class="btn-hero-nav prev" id="btn-hero-prev"><i class="bi bi-chevron-left"></i></button>
                <button class="btn-hero-nav next" id="btn-hero-next"><i class="bi bi-chevron-right"></i></button>
                <div class="hero-indicadores" id="hero-indicadores"></div>
            </div>
        </section>

        <?php if (!empty($eventos_proximos)): ?>
            <section class="eventos-proximos-section desktop-only">
                <h2 class="section-title">Próximos Eventos</h2>
                <div class="carrusel-horizontal">
                    <div id="eventos-proximos-container" class="eventos-scroll-container"></div>
                    <button class="btn-nav-carrusel prev" id="btn-prev-proximos"><i class="bi bi-chevron-left"></i></button>
                    <button class="btn-nav-carrusel next" id="btn-next-proximos"><i
                            class="bi bi-chevron-right"></i></button>
                </div>
            </section>
        <?php endif; ?>

        <section class="mobile-home mobile-only" id="mobileHome" aria-label="Cartelera">
            <h2 class="mobile-home-title">Eventos</h2>
            <div class="mobile-grid" id="mobileGrid"></div>
        </section>
    </main>

    <div class="mobile-event-detail" id="mobileEventDetail" aria-hidden="true">
        <div class="mobile-detail-bg" id="mobileDetailBg"></div>
        <div class="mobile-detail-scrim"></div>
        <div class="mobile-detail-content" role="dialog" aria-modal="true" aria-labelledby="mobileDetailTitle">
            <div class="mobile-detail-top">
                <button type="button" class="mobile-detail-back" id="mobileDetailBack" aria-label="Cerrar">
                    <i class="bi bi-x-lg"></i>
                </button>
                <h2 class="mobile-detail-heading">Función</h2>
            </div>
            <h1 class="mobile-detail-title" id="mobileDetailTitle"></h1>
            <p class="mobile-detail-desc" id="mobileDetailDesc"></p>
            <div class="mobile-horarios-label">Horarios disponibles</div>
            <div class="mobile-horarios" id="mobileHorarios"></div>
        </div>
    </div>

    <footer class="site-footer desktop-only" id="contacto">
        <div class="footer-inner">
            <div class="footer-col">
                <h4>Teatro Constitución · Apatzingan</h4>
                <p class="muted">Arte escénico, música y cultura para todos. Vive la experiencia teatral.</p>
                <div class="social" aria-label="Redes sociales">
                    <a href="https://www.facebook.com/people/Teatro-Constituci%C3%B3n/100077079712986/" target="_blank"
                        rel="noopener noreferrer" title="Facebook" aria-label="Facebook"><i
                            class="bi bi-facebook"></i></a>
                    <a href="https://www.instagram.com/teatro_constitucion_apatzingan?igsh=YzZ4YmxhcWU2Zmk%3D"
                        target="_blank" rel="noopener noreferrer" title="Instagram" aria-label="Instagram"><i
                            class="bi bi-instagram"></i></a>
                </div>
            </div>
            <div class="footer-col">
                <h4>Enlaces</h4>
                <ul class="footer-links">
                    <li><a href="#inicio">Inicio</a></li>
                    <li><a href="acerca.php">Acerca del teatro</a></li>
                    <li><a href="contacto.php">Contacto / Reservaciones</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Contacto</h4>
                <ul class="footer-links">
                    <li><i class="bi bi-telephone"></i> <span>+52 (453) 534 5751</span></li>
                    <li><i class="bi bi-envelope"></i> <span>teatroconstitucion@outlook.es</span></li>
                    <li><i class="bi bi-geo-alt"></i> <span>Apatzingán, Michoacán, México</span></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Horarios</h4>
                <ul class="footer-links">
                    <li><span>Taquilla: Lunes a Viernes 09:00 am –08:00 pm</span></li>
                    <li><span>Funciones: Según cartelera</span></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="footer-bottom-inner">
                <div> Teatro Constitución · Apatzingan. Todos los derechos reservados.</div>
                <div class="muted">
                    <a href="terminos.php" style="color: inherit; text-decoration: none;">Términos · Privacidad</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // DATOS DE PHP A JAVASCRIPT
        const eventosEstaSemana = <?php echo json_encode($eventos_esta_semana, JSON_UNESCAPED_UNICODE); ?>;
        const eventosProximos = <?php echo json_encode($eventos_proximos, JSON_UNESCAPED_UNICODE); ?>;
        const eventosMovil = <?php echo json_encode($eventos_movil, JSON_UNESCAPED_UNICODE); ?>;

        const containerProximos = document.getElementById('eventos-proximos-container');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const mainNav = document.getElementById('mainNav');
        const navBackdrop = document.getElementById('navBackdrop');
        const mobileGrid = document.getElementById('mobileGrid');
        const mobileEventDetail = document.getElementById('mobileEventDetail');
        const mobileDetailBg = document.getElementById('mobileDetailBg');
        const mobileDetailTitle = document.getElementById('mobileDetailTitle');
        const mobileDetailDesc = document.getElementById('mobileDetailDesc');
        const mobileHorarios = document.getElementById('mobileHorarios');
        const mobileDetailBack = document.getElementById('mobileDetailBack');

        // FALLBACK DE IMAGEN
        function buildImageCandidates(evento) {
            const src = (evento && evento.imagen) ? String(evento.imagen) : '';
            const parts = src.split('/');
            const filename = parts[parts.length - 1] || '';
            const basename = filename.replace(/\.(jpg|jpeg|png|gif)$/i, '');
            const exts = ['jpg', 'jpeg', 'png', 'gif'];
            const candidates = [];
            if (src) { candidates.push(`../evt_interfaz/${src}`); }
            exts.forEach(ext => candidates.push(`../evt_interfaz/imagenes/${basename}.${ext}`));
            if (src) { candidates.push(src); }
            return [...new Set(candidates)];
        }

        function createImgWithFallback(evento, alt, className = '') {
            const img = document.createElement('img');
            if (className) img.className = className;
            img.alt = alt || '';
            const cands = buildImageCandidates(evento);
            let idx = 0;
            function tryNext() {
                if (idx < cands.length) { img.src = cands[idx++]; }
            }
            img.onerror = () => { tryNext(); };
            tryNext();
            return img;
        }

        function escHtml(s) {
            return String(s ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function firstImageUrl(evento) {
            const cands = buildImageCandidates(evento);
            return cands[0] || '';
        }

        // FORMATEAR FECHA
        function formatearFecha(fechaStr) {
            const fecha = new Date(fechaStr);
            return fecha.toLocaleString('es-ES', {
                weekday: 'long', day: 'numeric', month: 'long',
                hour: 'numeric', minute: '2-digit', hour12: true
            });
        }

        function formatearHorarioCorto(fechaStr) {
            const fecha = new Date(fechaStr);
            const dia = fecha.toLocaleDateString('es-MX', { weekday: 'short', day: 'numeric', month: 'short' });
            const hora = fecha.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit', hour12: true });
            return { dia, hora };
        }

        function setNavOpen(open) {
            if (!mainNav || !hamburgerBtn) return;
            mainNav.classList.toggle('open', open);
            hamburgerBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (navBackdrop) {
                navBackdrop.classList.toggle('show', open);
                navBackdrop.setAttribute('aria-hidden', open ? 'false' : 'true');
            }
            const icon = hamburgerBtn.querySelector('i');
            if (icon) {
                icon.className = open ? 'bi bi-x-lg' : 'bi bi-list';
                icon.style.fontSize = '1.25rem';
            }
        }

        // —— Móvil: grid + detalle ——
        function renderizarMobileGrid() {
            if (!mobileGrid) return;
            mobileGrid.innerHTML = '';
            if (!eventosMovil || eventosMovil.length === 0) {
                mobileGrid.innerHTML = `
                    <div class="mobile-empty">
                        <i class="bi bi-calendar-x" style="font-size:2.5rem"></i>
                        <p style="margin-top:12px">No hay eventos próximos</p>
                    </div>`;
                return;
            }
            eventosMovil.forEach(evento => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'mobile-poster';
                btn.setAttribute('aria-label', evento.titulo || 'Evento');

                const wrap = document.createElement('div');
                wrap.className = 'mobile-poster-img';
                wrap.appendChild(createImgWithFallback(evento, evento.titulo));

                const title = document.createElement('div');
                title.className = 'mobile-poster-title';
                title.textContent = evento.titulo || '';

                btn.appendChild(wrap);
                btn.appendChild(title);
                btn.addEventListener('click', () => abrirDetalleMovil(evento));
                mobileGrid.appendChild(btn);
            });
        }

        function abrirDetalleMovil(evento) {
            if (!mobileEventDetail) return;
            setNavOpen(false);
            mobileDetailTitle.textContent = evento.titulo || '';
            mobileDetailDesc.textContent = (evento.descripcion || '').trim() || 'Sin descripción disponible.';
            const url = firstImageUrl(evento);
            mobileDetailBg.style.backgroundImage = url ? `url("${url}")` : 'none';

            mobileHorarios.innerHTML = '';
            const funciones = Array.isArray(evento.funciones) ? evento.funciones : [];
            if (!funciones.length) {
                mobileHorarios.innerHTML = '<span style="color:#94a3b8;font-size:0.9rem">No hay horarios disponibles</span>';
            } else {
                funciones.forEach(f => {
                    const { dia, hora } = formatearHorarioCorto(f.fecha_hora);
                    const a = document.createElement(f.agotado ? 'span' : 'a');
                    a.className = 'mobile-horario' + (f.agotado ? ' agotado' : '');
                    a.innerHTML = `<span class="dia">${escHtml(dia)}</span>${escHtml(hora)}${f.agotado ? '<br><small>Agotado</small>' : ''}`;
                    if (!f.agotado) {
                        a.href = `comprar.php?id_evento=${encodeURIComponent(evento.id_evento)}&id_funcion=${encodeURIComponent(f.id_funcion)}`;
                    }
                    mobileHorarios.appendChild(a);
                });
            }

            mobileEventDetail.classList.add('open');
            mobileEventDetail.setAttribute('aria-hidden', 'false');
            document.body.classList.add('mobile-detail-open');
        }

        function cerrarDetalleMovil() {
            if (!mobileEventDetail) return;
            mobileEventDetail.classList.remove('open');
            mobileEventDetail.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('mobile-detail-open');
        }

        // RENDERIZAR CARRUSEL HERO
        let heroSlideActual = 0;
        let heroAutoplay = null;

        function renderizarHeroCarrusel() {
            const heroCarrusel = document.getElementById('hero-carrusel');
            const indicadores = document.getElementById('hero-indicadores');
            if (!heroCarrusel) return;

            if (!eventosEstaSemana || eventosEstaSemana.length === 0) {
                heroCarrusel.innerHTML = `
                    <div class="no-eventos-msg" style="margin: 40px; text-align: center;">
                        <i class="bi bi-calendar-x" style="font-size: 3rem;"></i>
                        <h4 style="margin-top: 15px;">No hay eventos esta semana</h4>
                        <p>Revisa nuestros próximos eventos más abajo</p>
                    </div>`;
                return;
            }

            const slides = heroCarrusel.querySelectorAll('.hero-slide');
            slides.forEach(s => s.remove());

            eventosEstaSemana.forEach((evento, index) => {
                const slide = document.createElement('a');
                const idFuncion = evento.proxima_id_funcion || '';
                const agotadoHero = !!evento.agotado_proxima;
                if (agotadoHero) {
                    slide.href = '#';
                    slide.addEventListener('click', e => e.preventDefault());
                } else if (idFuncion) {
                    slide.href = `comprar.php?id_evento=${evento.id_evento}&id_funcion=${idFuncion}`;
                } else {
                    slide.href = `cartelera_cliente.php`;
                }
                slide.className = `hero-slide ${index === 0 ? 'active' : ''}`;

                const imagen = document.createElement('div');
                imagen.className = 'hero-imagen';
                imagen.appendChild(createImgWithFallback(evento, evento.titulo));

                const contenido = document.createElement('div');
                contenido.className = 'hero-contenido';

                const descripcionRaw = evento.descripcion ? String(evento.descripcion).substring(0, 200) + '...' : '';
                const descripcion = escHtml(descripcionRaw);
                const tituloEsc = escHtml(evento.titulo || '');
                const fechaEsc = escHtml(formatearFecha(evento.proxima_funcion_fecha));
                const agotado = agotadoHero;

                const disponibles = evento.disponibles || 0;
                const totalAsientos = evento.total_asientos || 0;
                let claseDisponibles = '';
                if (agotado) {
                    claseDisponibles = 'agotado';
                } else if (disponibles <= 10 && disponibles > 0) {
                    claseDisponibles = 'pocos';
                }

                contenido.innerHTML = `
                    <h2 class="hero-titulo">${tituloEsc}</h2>
                    ${descripcion ? `<p class="hero-descripcion">${descripcion}</p>` : ''}
                    <p class="hero-fecha">
                        <i class="bi bi-calendar-event"></i>
                        ${fechaEsc}
                    </p>
                    ${totalAsientos > 0 ? `
                    <p class="boletos-disponibles ${claseDisponibles}">
                        <i class="bi bi-ticket-perforated"></i>
                        ${agotado
                            ? 'Sin boletos disponibles'
                            : `${disponibles} boleto${disponibles !== 1 ? 's' : ''} disponible${disponibles !== 1 ? 's' : ''}`}
                    </p>
                    ` : ''}
                    <span class="hero-btn ${agotado ? 'hero-btn-agotado' : ''}">
                        ${agotado
                        ? '<i class="bi bi-x-octagon-fill"></i> Agotado'
                        : 'Comprar boletos <i class="bi bi-arrow-right"></i>'}
                    </span>
                `;

                slide.appendChild(imagen);
                slide.appendChild(contenido);
                heroCarrusel.insertBefore(slide, heroCarrusel.querySelector('.btn-hero-nav'));
            });

            if (indicadores) {
                indicadores.innerHTML = '';
                eventosEstaSemana.forEach((_, index) => {
                    const indicador = document.createElement('div');
                    indicador.className = `indicador ${index === 0 ? 'active' : ''}`;
                    indicador.addEventListener('click', () => cambiarHeroSlide(index));
                    indicadores.appendChild(indicador);
                });
            }

            iniciarHeroAutoplay();
        }

        function cambiarHeroSlide(nuevoIndex) {
            const slides = document.querySelectorAll('.hero-slide');
            const indicadores = document.querySelectorAll('.indicador');

            if (!slides.length) return;

            slides[heroSlideActual].classList.remove('active');
            if (indicadores[heroSlideActual]) indicadores[heroSlideActual].classList.remove('active');

            heroSlideActual = nuevoIndex;
            if (heroSlideActual >= slides.length) heroSlideActual = 0;
            if (heroSlideActual < 0) heroSlideActual = slides.length - 1;

            slides[heroSlideActual].classList.add('active');
            if (indicadores[heroSlideActual]) indicadores[heroSlideActual].classList.add('active');
        }

        function iniciarHeroAutoplay() {
            clearInterval(heroAutoplay);
            if (eventosEstaSemana && eventosEstaSemana.length > 1) {
                heroAutoplay = setInterval(() => {
                    cambiarHeroSlide(heroSlideActual + 1);
                }, 5000);
            }
        }

        function detenerHeroAutoplay() { clearInterval(heroAutoplay); }

        // RENDERIZAR PRÓXIMOS EVENTOS
        function renderizarEventosCarrusel(eventos, container) {
            if (!eventos || eventos.length === 0 || !container) return;

            eventos.forEach(evento => {
                const eventoCard = document.createElement('a');
                const idFuncion = evento.proxima_id_funcion || '';
                const agotadoCard = !!evento.agotado_proxima;
                if (agotadoCard) {
                    eventoCard.href = '#';
                    eventoCard.addEventListener('click', e => e.preventDefault());
                    eventoCard.style.cursor = 'default';
                } else if (idFuncion) {
                    eventoCard.href = `comprar.php?id_evento=${evento.id_evento}&id_funcion=${idFuncion}`;
                } else {
                    eventoCard.href = `cartelera_cliente.php`;
                }
                eventoCard.className = 'evento-card';

                const imagen = document.createElement('div');
                imagen.className = 'evento-card-imagen';
                imagen.appendChild(createImgWithFallback(evento, evento.titulo));

                const info = document.createElement('div');
                info.className = 'evento-card-info';

                const disponibles = evento.disponibles || 0;
                const totalAsientos = evento.total_asientos || 0;
                const agotado = agotadoCard;
                let claseDisponibles = '';
                if (agotado) {
                    claseDisponibles = 'agotado';
                } else if (disponibles <= 10 && disponibles > 0) {
                    claseDisponibles = 'pocos';
                }

                info.innerHTML = `
                    <h4 class="evento-card-titulo">${escHtml(evento.titulo)}</h4>
                    <p class="evento-card-fecha">
                        <i class="bi bi-calendar-event"></i>
                        ${escHtml(formatearFecha(evento.proxima_funcion_fecha))}
                    </p>
                    ${totalAsientos > 0 ? `
                    <p class="evento-card-disponibles ${claseDisponibles}">
                        <i class="bi bi-ticket-perforated"></i>
                        ${agotado
                            ? 'Agotado'
                            : `${disponibles} disponible${disponibles !== 1 ? 's' : ''}`}
                    </p>
                    ` : ''}
                `;

                eventoCard.appendChild(imagen);
                eventoCard.appendChild(info);
                container.appendChild(eventoCard);
            });
        }

        // SCROLL CARRUSEL
        function configurarCarrusel(containerId, btnPrevId, btnNextId) {
            const container = document.getElementById(containerId);
            const btnPrev = document.getElementById(btnPrevId);
            const btnNext = document.getElementById(btnNextId);

            if (!container || !btnPrev || !btnNext) return;

            const scrollAmount = 300;
            btnNext.addEventListener('click', () => { container.scrollBy({ left: scrollAmount, behavior: 'smooth' }); });
            btnPrev.addEventListener('click', () => { container.scrollBy({ left: -scrollAmount, behavior: 'smooth' }); });

            function actualizarBotones() {
                const maxScroll = container.scrollWidth - container.clientWidth;
                btnPrev.style.opacity = container.scrollLeft > 0 ? '1' : '0.3';
                btnNext.style.opacity = container.scrollLeft < maxScroll - 10 ? '1' : '0.3';
                btnPrev.style.pointerEvents = container.scrollLeft > 0 ? 'auto' : 'none';
                btnNext.style.pointerEvents = container.scrollLeft < maxScroll - 10 ? 'auto' : 'none';
            }
            container.addEventListener('scroll', actualizarBotones);
            actualizarBotones();
        }

        // INICIALIZACIÓN
        document.addEventListener('DOMContentLoaded', () => {
            renderizarHeroCarrusel();
            renderizarMobileGrid();

            const btnHeroPrev = document.getElementById('btn-hero-prev');
            const btnHeroNext = document.getElementById('btn-hero-next');
            const heroCarrusel = document.getElementById('hero-carrusel');

            if (btnHeroPrev) {
                btnHeroPrev.addEventListener('click', (e) => {
                    e.preventDefault();
                    cambiarHeroSlide(heroSlideActual - 1);
                    detenerHeroAutoplay();
                    setTimeout(iniciarHeroAutoplay, 1000);
                });
            }
            if (btnHeroNext) {
                btnHeroNext.addEventListener('click', (e) => {
                    e.preventDefault();
                    cambiarHeroSlide(heroSlideActual + 1);
                    detenerHeroAutoplay();
                    setTimeout(iniciarHeroAutoplay, 1000);
                });
            }
            if (heroCarrusel) {
                heroCarrusel.addEventListener('mouseenter', detenerHeroAutoplay);
                heroCarrusel.addEventListener('mouseleave', iniciarHeroAutoplay);
            }

            if (containerProximos) {
                renderizarEventosCarrusel(eventosProximos, containerProximos);
                configurarCarrusel('eventos-proximos-container', 'btn-prev-proximos', 'btn-next-proximos');
            }

            if (hamburgerBtn && mainNav) {
                hamburgerBtn.addEventListener('click', () => {
                    setNavOpen(!mainNav.classList.contains('open'));
                });
            }
            if (navBackdrop) {
                navBackdrop.addEventListener('click', () => setNavOpen(false));
            }
            mainNav?.querySelectorAll('a').forEach(a => {
                a.addEventListener('click', () => setNavOpen(false));
            });
            document.querySelectorAll('[data-nav-home]').forEach(a => {
                a.addEventListener('click', () => {
                    cerrarDetalleMovil();
                    setNavOpen(false);
                });
            });
            if (mobileDetailBack) {
                mobileDetailBack.addEventListener('click', cerrarDetalleMovil);
            }
            const mobileDetailScrim = mobileEventDetail?.querySelector('.mobile-detail-scrim');
            if (mobileDetailScrim) {
                mobileDetailScrim.addEventListener('click', cerrarDetalleMovil);
            }
            mobileEventDetail?.addEventListener('click', (e) => {
                if (e.target === mobileEventDetail) cerrarDetalleMovil();
            });
        });
    </script>
</body>

</html>