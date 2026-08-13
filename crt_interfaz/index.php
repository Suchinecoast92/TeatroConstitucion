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

        if ($fecha_funcion <= $fin_semana) {
            $eventos_esta_semana[] = $evento;
        } else {
            $eventos_proximos[] = $evento;
        }
    }
}
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

        .nav {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-left: auto;
        }

        .nav a {
            color: #e8e8e8;
            text-decoration: none;
            font-weight: 600;
            transition: color .2s ease, transform .2s ease;
        }

        .nav a:not(.cta) {
            padding: 8px 10px;
            border-radius: 10px;
            transition: color .2s ease, transform .2s ease, background-color .2s ease, box-shadow .2s ease;
        }

        .nav a:not(.cta):hover,
        .nav a:not(.cta).active {
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
        }

        @media (max-width: 900px) {
            .nav {
                position: fixed;
                inset: 64px 0 0 0;
                background: rgba(10, 10, 12, .98);
                flex-direction: column;
                padding: 24px;
                gap: 12px;
                transform: translateY(-120%);
                transition: transform .25s ease;
            }

            .nav.open {
                transform: translateY(0);
            }

            .hamburger {
                display: inline-flex;
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
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.06));
            backdrop-filter: blur(30px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.2);
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
<<<<<<< HEAD
        .hero-imagen img { width: 100%; height: 100%; object-fit: contain; transition: transform 0.4s ease; }
        .hero-slide:hover .hero-imagen img { transform: scale(1.05); }
        
        .hero-contenido { display: flex; flex-direction: column; justify-content: center; padding-right: 40px; }
        .hero-titulo { font-size: 3rem; font-weight: 800; color: #ffffff; margin-bottom: 20px; text-shadow: 0 4px 20px rgba(0, 0, 0, 0.4); line-height: 1.1; }
<<<<<<< HEAD
=======

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

>>>>>>> f91c89e211010a90fdbeb335b51e4e28c014b5f8
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
<<<<<<< HEAD
        
=======
        .hero-descripcion { font-size: 1.15rem; color: rgba(255, 255, 255, 0.9); line-height: 1.7; margin-bottom: 25px; text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3); }
>>>>>>> 451fae36cfd5700c4bda0f2b24d96380134a718a
        .hero-fecha { font-size: 1.2rem; color: #e5e7eb; font-weight: 700; margin-bottom: 30px; display: flex; align-items: center; gap: 10px; }
        
=======

        .hero-fecha {
            font-size: 1.2rem;
            color: #e5e7eb;
            font-weight: 700;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

>>>>>>> f91c89e211010a90fdbeb335b51e4e28c014b5f8
        .hero-btn {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: #ffffff;
            padding: 16px 40px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-block;
            width: fit-content;
            transition: all 0.3s ease;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.6);
            cursor: pointer;
        }

        .hero-btn:hover {
            transform: translateY(-3px);
            color: #fff;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.8);
            background: linear-gradient(135deg, #9ca3af, #4b5563);
        }

        .hero-btn-agotado {
            background: linear-gradient(135deg, #6b7280, #374151);
            box-shadow: 0 8px 24px rgba(55, 65, 81, 0.6);
            cursor: default;
        }

        .hero-btn-agotado:hover {
            transform: none;
            box-shadow: 0 8px 24px rgba(55, 65, 81, 0.6);
            background: linear-gradient(135deg, #6b7280, #374151);
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
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.08));
            backdrop-filter: blur(20px) saturate(180%);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            color: inherit;
            display: block;
            cursor: pointer;
        }

        .evento-card:hover {
            transform: translateY(-10px) scale(1.03);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.4);
            border-color: rgba(255, 255, 255, 0.4);
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
            color: #34d399;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
        }

        .evento-card-disponibles.agotado {
            color: #f87171;
        }

        .evento-card-disponibles.pocos {
            color: #fbbf24;
        }

        .boletos-disponibles {
            font-size: 0.95rem;
            color: #34d399;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
        }

        .boletos-disponibles.agotado {
            color: #f87171;
        }

        .boletos-disponibles.pocos {
            color: #fbbf24;
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
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(15, 23, 42, 0.8));
            backdrop-filter: blur(25px) saturate(180%);
            border-radius: 16px;
            border: 1px solid rgba(148, 163, 184, 0.4);
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.7);
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

        @media (max-width: 900px) {
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
    </style>
</head>

<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="#inicio" class="brand" aria-label="Inicio">
                <div class="brand-logo"><img src="imagenes_teatro/nat.png" alt="Teatro Constitución" class="logo-img">
                </div>
                <div class="brand-name">Teatro Constitución · Apatzingan</div>
            </a>
            <nav class="nav" id="mainNav">
                <a href="#inicio" class="active">Inicio</a>
                <a href="acerca.php">Acerca del teatro</a>
                <a href="contacto.php">Contacto / Reservaciones</a>
                <a href="cartelera_cliente.php" class="cta">Ver cartelera completa <i class="bi bi-arrow-right"></i></a>
            </nav>
            <button class="hamburger" id="hamburgerBtn" aria-label="Menú">
                <i class="bi bi-list" style="font-size:1.25rem"></i>
            </button>
        </div>
    </header>

    <main class="hero" id="inicio">
        <section class="eventos-semana-section">
            <div class="hero-carrusel" id="hero-carrusel">
                <button class="btn-hero-nav prev" id="btn-hero-prev"><i class="bi bi-chevron-left"></i></button>
                <button class="btn-hero-nav next" id="btn-hero-next"><i class="bi bi-chevron-right"></i></button>
                <div class="hero-indicadores" id="hero-indicadores"></div>
            </div>
        </section>

        <?php if (!empty($eventos_proximos)): ?>
            <section class="eventos-proximos-section">
                <h2 class="section-title">Próximos Eventos</h2>
                <div class="carrusel-horizontal">
                    <div id="eventos-proximos-container" class="eventos-scroll-container"></div>
                    <button class="btn-nav-carrusel prev" id="btn-prev-proximos"><i class="bi bi-chevron-left"></i></button>
                    <button class="btn-nav-carrusel next" id="btn-next-proximos"><i
                            class="bi bi-chevron-right"></i></button>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <footer class="site-footer" id="contacto">
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
        const eventosEstaSemana = <?php echo json_encode($eventos_esta_semana); ?>;
        const eventosProximos = <?php echo json_encode($eventos_proximos); ?>;

        const containerProximos = document.getElementById('eventos-proximos-container');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const mainNav = document.getElementById('mainNav');

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

        // FORMATEAR FECHA
        function formatearFecha(fechaStr) {
            const fecha = new Date(fechaStr);
            return fecha.toLocaleString('es-ES', {
                weekday: 'long', day: 'numeric', month: 'long',
                hour: 'numeric', minute: '2-digit', hour12: true
            });
        }

        // RENDERIZAR CARRUSEL HERO
        let heroSlideActual = 0;
        let heroAutoplay = null;

        function renderizarHeroCarrusel() {
            const heroCarrusel = document.getElementById('hero-carrusel');
            const indicadores = document.getElementById('hero-indicadores');

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
                if (idFuncion) {
                    slide.href = `disponibles.php?id_evento=${evento.id_evento}&id_funcion=${idFuncion}`;
                } else {
                    slide.href = `disponibles.php?id_evento=${evento.id_evento}`;
                }
                slide.className = `hero-slide ${index === 0 ? 'active' : ''}`;

                const imagen = document.createElement('div');
                imagen.className = 'hero-imagen';
                imagen.appendChild(createImgWithFallback(evento, evento.titulo));

                const contenido = document.createElement('div');
                contenido.className = 'hero-contenido';

                const descripcion = evento.descripcion ? evento.descripcion.substring(0, 200) + '...' : '';
                const agotado = !!evento.agotado_proxima;

                const disponibles = evento.disponibles || 0;
                const totalAsientos = evento.total_asientos || 0;
                let claseDisponibles = '';
                if (agotado) {
                    claseDisponibles = 'agotado';
                } else if (disponibles <= 10 && disponibles > 0) {
                    claseDisponibles = 'pocos';
                }

                contenido.innerHTML = `
                    <h2 class="hero-titulo">${evento.titulo}</h2>
                    ${descripcion ? `<p class="hero-descripcion">${descripcion}</p>` : ''}
                    <p class="hero-fecha">
                        <i class="bi bi-calendar-event"></i>
                        ${formatearFecha(evento.proxima_funcion_fecha)}
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
                        : 'Ver disponibilidad <i class="bi bi-arrow-right"></i>'}
                    </span>
                `;

                slide.appendChild(imagen);
                slide.appendChild(contenido);
                heroCarrusel.insertBefore(slide, heroCarrusel.querySelector('.btn-hero-nav'));
            });

            indicadores.innerHTML = '';
            eventosEstaSemana.forEach((_, index) => {
                const indicador = document.createElement('div');
                indicador.className = `indicador ${index === 0 ? 'active' : ''}`;
                indicador.addEventListener('click', () => cambiarHeroSlide(index));
                indicadores.appendChild(indicador);
            });

            iniciarHeroAutoplay();
        }

        function cambiarHeroSlide(nuevoIndex) {
            const slides = document.querySelectorAll('.hero-slide');
            const indicadores = document.querySelectorAll('.indicador');

            if (!slides.length) return;

            slides[heroSlideActual].classList.remove('active');
            indicadores[heroSlideActual].classList.remove('active');

            heroSlideActual = nuevoIndex;
            if (heroSlideActual >= slides.length) heroSlideActual = 0;
            if (heroSlideActual < 0) heroSlideActual = slides.length - 1;

            slides[heroSlideActual].classList.add('active');
            indicadores[heroSlideActual].classList.add('active');
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
            if (!eventos || eventos.length === 0) return;

            eventos.forEach(evento => {
                const eventoCard = document.createElement('a');
                const idFuncion = evento.proxima_id_funcion || '';
                if (idFuncion) {
                    eventoCard.href = `disponibles.php?id_evento=${evento.id_evento}&id_funcion=${idFuncion}`;
                } else {
                    eventoCard.href = `disponibles.php?id_evento=${evento.id_evento}`;
                }
                eventoCard.className = 'evento-card';

                const imagen = document.createElement('div');
                imagen.className = 'evento-card-imagen';
                imagen.appendChild(createImgWithFallback(evento, evento.titulo));

                const info = document.createElement('div');
                info.className = 'evento-card-info';

                const disponibles = evento.disponibles || 0;
                const totalAsientos = evento.total_asientos || 0;
                const agotado = !!evento.agotado_proxima;
                let claseDisponibles = '';
                if (agotado) {
                    claseDisponibles = 'agotado';
                } else if (disponibles <= 10 && disponibles > 0) {
                    claseDisponibles = 'pocos';
                }

                info.innerHTML = `
                    <h4 class="evento-card-titulo">${evento.titulo}</h4>
                    <p class="evento-card-fecha">
                        <i class="bi bi-calendar-event"></i>
                        ${formatearFecha(evento.proxima_funcion_fecha)}
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

            renderizarEventosCarrusel(eventosProximos, containerProximos);
            configurarCarrusel('eventos-proximos-container', 'btn-prev-proximos', 'btn-next-proximos');

            if (hamburgerBtn && mainNav) {
                hamburgerBtn.addEventListener('click', () => { mainNav.classList.toggle('open'); });
            }
        });
    </script>
</body>

</html>