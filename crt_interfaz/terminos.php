<?php
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Términos y Privacidad · Teatro Constitución</title>
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

        .site-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(10, 10, 12, 0.9);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid rgba(255,255,255,0.06);
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
            background: none;
            display: grid;
            place-items: center;
            font-weight: 800;
            letter-spacing: 0.1px;
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
            box-shadow: 0 8px 18px rgba(15,23,42,.55);
            transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
            border: 0;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(15,23,42,.75);
            filter: brightness(1.03);
        }
        .hamburger {
            display: none;
            background: transparent;
            color: #e8e8e8;
            border: 1px solid rgba(255,255,255,0.15);
            width: 42px;
            height: 42px;
            border-radius: 8px;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            z-index: 1103;
        }
        .nav-backdrop { display: none; }

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
            .site-nav a { pointer-events: auto; }
            .hamburger { display: none !important; }
        }

        @media (max-width: 1100px) {
            body { background-attachment: scroll; }
            .header-inner { padding: 10px 14px; gap: 10px; }
            .brand { gap: 10px; margin-right: 0; min-width: 0; flex: 1; }
            .brand-logo { width: 40px; height: 40px; margin-left: 0; }
            .brand-name {
                font-size: 0.95rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
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
                pointer-events: auto;
            }
            #mainNav.site-nav.open { transform: translateX(0); }
            #mainNav.site-nav a:not(.cta) {
                display: block;
                width: 100%;
                padding: 14px 12px;
                font-size: 1.05rem;
                box-sizing: border-box;
            }
            #mainNav.site-nav .cta { display: none !important; }
            .hamburger { display: inline-flex; margin-left: auto; }
            .nav-backdrop {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.55);
                z-index: 1101;
            }
            .nav-backdrop.show { display: block; }
            .main-content { padding: 24px 16px 40px; }
            .terminos-hero,
            .terminos-block {
                padding: 28px 20px;
            }
            .terminos-hero h1 {
                font-size: clamp(2.1rem, 6.5vw, 2.7rem);
            }
            .terminos-toc {
                gap: 8px;
            }
            .site-footer { margin-top: 40px; }
        }

        .main-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 44px 28px 64px;
        }

        .terminos-display {
            font-weight: 700;
            letter-spacing: -0.02em;
            line-height: 1.15;
        }

        .terminos-hero {
            text-align: center;
            margin-bottom: 28px;
            animation: fadeInUpSoft 0.8s ease;
        }

        .terminos-hero-panel {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.06));
            backdrop-filter: blur(25px) saturate(180%);
            -webkit-backdrop-filter: blur(25px) saturate(180%);
            border-radius: 18px;
            padding: 44px 42px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow:
                0 12px 40px rgba(0, 0, 0, 0.12),
                inset 0 1px 1px rgba(255, 255, 255, 0.5);
            position: relative;
            overflow: hidden;
            max-width: 1100px;
            margin: 0 auto;
        }

        .terminos-hero-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.7), transparent);
        }

        .terminos-hero h1 {
            font-size: clamp(2.45rem, 4.2vw, 3.15rem);
            margin: 0 0 12px;
            color: #fff;
            text-shadow: 0 6px 28px rgba(0, 0, 0, 0.5);
        }

        .terminos-hero .eyebrow {
            margin: 0 0 18px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 1.02rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .terminos-hero .lead {
            margin: 0 auto;
            max-width: 820px;
            color: rgba(255, 255, 255, 0.92);
            font-size: clamp(1.02rem, 1.6vw, 1.12rem);
            line-height: 1.75;
            text-align: left;
        }

        .terminos-toc-wrap {
            margin: 0 0 28px;
            animation: fadeInUpSoft 0.8s ease 0.08s both;
        }

        .terminos-toc {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }

        .terminos-toc a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 14px;
            border-radius: 999px;
            text-decoration: none;
            color: #fff;
            font-size: 0.9rem;
            font-weight: 650;
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.22);
            transition: transform .2s ease, border-color .2s ease, background .2s ease;
        }

        .terminos-toc a:hover {
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.4);
            background: rgba(0, 0, 0, 0.45);
            color: #fff;
        }

        .terminos-blocks {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .terminos-block {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.06));
            backdrop-filter: blur(25px) saturate(180%);
            -webkit-backdrop-filter: blur(25px) saturate(180%);
            border-radius: 18px;
            padding: 32px 36px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow:
                0 12px 40px rgba(0, 0, 0, 0.12),
                inset 0 1px 1px rgba(255, 255, 255, 0.5);
            position: relative;
            overflow: hidden;
            scroll-margin-top: 90px;
            animation: fadeInUpSoft 0.8s ease both;
        }

        .terminos-block:nth-child(1) { animation-delay: 0.1s; }
        .terminos-block:nth-child(2) { animation-delay: 0.14s; }
        .terminos-block:nth-child(3) { animation-delay: 0.18s; }
        .terminos-block:nth-child(4) { animation-delay: 0.22s; }
        .terminos-block:nth-child(5) { animation-delay: 0.26s; }
        .terminos-block:nth-child(6) { animation-delay: 0.3s; }
        .terminos-block:nth-child(7) { animation-delay: 0.34s; }
        .terminos-block:nth-child(8) { animation-delay: 0.38s; }
        .terminos-block:nth-child(9) { animation-delay: 0.42s; }
        .terminos-block:nth-child(10) { animation-delay: 0.46s; }

        .terminos-block::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.7), transparent);
        }

        .terminos-block__head {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 14px;
        }

        .terminos-block__icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            background: rgba(0, 0, 0, 0.28);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            font-size: 1.25rem;
        }

        .terminos-block__num {
            display: inline-block;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.65);
            margin-bottom: 4px;
        }

        .terminos-block h2 {
            margin: 0;
            font-size: clamp(1.45rem, 2.6vw, 1.85rem);
            color: #fff;
        }

        .terminos-block p {
            margin: 0 0 12px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.04rem;
            line-height: 1.75;
        }

        .terminos-block p:last-child {
            margin-bottom: 0;
        }

        .terminos-block a {
            color: #fff;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .terminos-block a:hover {
            opacity: 0.9;
        }

        @keyframes fadeInUpSoft {
            from { transform: translateY(18px); }
            to { transform: translateY(0); }
        }

        @media (prefers-reduced-motion: reduce) {
            .terminos-hero,
            .terminos-toc-wrap,
            .terminos-block {
                animation: none;
            }
        }

        .site-footer {
            background: rgba(10,10,12,0.95);
            border-top: 1px solid rgba(255,255,255,0.06);
            color: #cfd3d7;
            margin-top: 80px;
        }
        .footer-inner {
            max-width: 1280px;
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
        .footer-col p, .footer-col li, .footer-col a, .footer-col span {
            font-size: 0.95rem;
            color: #cfd3d7;
        }
        .footer-links { list-style: none; padding: 0; margin: 0; }
        .footer-links li { margin: 8px 0; }
        .footer-links a { text-decoration: none; color: #cfd3d7; transition: color .2s ease; }
        .footer-links a:hover { color: #fff; }
        .social { display: flex; gap: 10px; margin-top: 8px; }
        .social a { width: 36px; height: 36px; display: grid; place-items: center; border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; color: #e8e8e8; text-decoration: none; transition: all .2s ease; }
        .social a:hover { color: #fff; border-color: rgba(255,255,255,0.35); transform: translateY(-2px); }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,0.06); padding: 14px 20px; color: #aeb4ba; font-size: 0.9rem; }
        .footer-bottom-inner { max-width: 1280px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .muted { color: #aeb4ba; }
        @media (max-width: 1100px){
            .footer-inner { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 600px){
            .footer-inner { grid-template-columns: 1fr; }
            .footer-bottom-inner { flex-direction: column; align-items: flex-start; }
        }

        @media (max-width: 1100px) and (orientation: landscape) and (max-height: 520px) {
            .header-inner { padding: 6px 12px; }
            .brand-logo { width: 32px; height: 32px; margin-left: 0; }
            .brand-name { font-size: 0.82rem; }
            .hamburger { width: 36px; height: 36px; }
            .main-content { padding: 14px 16px 24px; }
            .terminos-hero-panel,
            .terminos-block { padding: 18px 16px; }
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
        <a href="index.php">Inicio</a>
        <a href="acerca.php">Acerca del teatro</a>
        <a href="contacto.php">Contacto / Reservaciones</a>
        <a href="cartelera_cliente.php" class="cta">Ver cartelera <i class="bi bi-arrow-right"></i></a>
    </nav>
    <header class="site-header">
        <div class="header-inner">
            <a href="index.php" class="brand" aria-label="Inicio">
                <div class="brand-logo"><img src="imagenes_teatro/nat.png" alt="Teatro Constitución" class="logo-img"></div>
                <div class="brand-name">Teatro Constitución · Apatzingan</div>
            </a>
            <button class="hamburger" id="hamburgerBtn" aria-label="Menú" aria-expanded="false" aria-controls="mainNav">
                <i class="bi bi-list" style="font-size:1.25rem"></i>
            </button>
        </div>
    </header>

    <main class="main-content">
        <section class="terminos-hero">
            <div class="terminos-hero-panel">
                <h1 class="terminos-display">Términos y Privacidad</h1>
                <p class="eyebrow">Teatro Constitución de Apatzingán</p>
                <p class="lead">El acceso y uso del sitio web del Teatro Constitución de Apatzingán (en adelante, "El Teatro") implica la aceptación plena y sin reserva de los presentes Términos y Condiciones. Si el usuario no está de acuerdo con su contenido, deberá abstenerse de utilizar este sitio.</p>
            </div>
        </section>

        <nav class="terminos-toc-wrap" aria-label="Índice de términos">
            <div class="terminos-toc">
                <a href="#uso"><i class="bi bi-globe2"></i> Uso</a>
                <a href="#compra"><i class="bi bi-ticket-perforated"></i> Compra</a>
                <a href="#acceso"><i class="bi bi-door-open"></i> Acceso</a>
                <a href="#propiedad"><i class="bi bi-c-circle"></i> Propiedad</a>
                <a href="#datos"><i class="bi bi-shield-lock"></i> Datos</a>
                <a href="#cookies"><i class="bi bi-sliders"></i> Cookies</a>
                <a href="#enlaces"><i class="bi bi-link-45deg"></i> Enlaces</a>
                <a href="#responsabilidad"><i class="bi bi-exclamation-triangle"></i> Responsabilidad</a>
                <a href="#modificaciones"><i class="bi bi-pencil-square"></i> Modificaciones</a>
                <a href="#legislacion"><i class="bi bi-bank"></i> Legislación</a>
            </div>
        </nav>

        <div class="terminos-blocks">
            <article class="terminos-block" id="uso">
                <div class="terminos-block__head">
                    <div class="terminos-block__icon"><i class="bi bi-globe2"></i></div>
                    <div>
                        <span class="terminos-block__num">Sección 01</span>
                        <h2 class="terminos-display">Uso del Sitio Web</h2>
                    </div>
                </div>
                <p>El usuario se obliga a utilizar el sitio de manera lícita, adecuada y conforme a la legislación aplicable. Queda estrictamente prohibido alterar, modificar o interferir en el funcionamiento del sitio, así como utilizar herramientas automatizadas (incluidos bots) para realizar compras o cualquier otra actividad dentro del mismo.</p>
            </article>

            <article class="terminos-block" id="compra">
                <div class="terminos-block__head">
                    <div class="terminos-block__icon"><i class="bi bi-ticket-perforated"></i></div>
                    <div>
                        <span class="terminos-block__num">Sección 02</span>
                        <h2 class="terminos-display">Compra de Boletos</h2>
                    </div>
                </div>
                <p>Todos los precios publicados en este sitio son netos, es decir, ya incluyen impuestos y no están sujetos a cargos adicionales por servicio.</p>
                <p>Todas las compras realizadas a través del sitio se consideran definitivas y no reembolsables, salvo en el caso de cancelación del evento por parte del Teatro.</p>
                <p>En caso de reprogramación del evento, los boletos previamente adquiridos serán válidos para la nueva fecha establecida.</p>
                <p>Es responsabilidad exclusiva del usuario revisar con atención la fecha, función, horario, zona y asiento antes de confirmar su compra.</p>
                <p>El Teatro no se hace responsable por boletos adquiridos a través de terceros no autorizados.</p>
            </article>

            <article class="terminos-block" id="acceso">
                <div class="terminos-block__head">
                    <div class="terminos-block__icon"><i class="bi bi-door-open"></i></div>
                    <div>
                        <span class="terminos-block__num">Sección 03</span>
                        <h2 class="terminos-display">Acceso y Admisión al Teatro</h2>
                    </div>
                </div>
                <p>Para ingresar a las instalaciones es indispensable presentar un boleto válido en formato físico o digital.</p>
                <p>El Teatro se reserva el derecho de admisión por razones de seguridad, incumplimiento de normas internas o comportamientos que afecten la experiencia de otros asistentes.</p>
                <p>No se permitirá el acceso con alimentos, bebidas, cámaras profesionales, objetos punzocortantes, sustancias ilícitas o cualquier artículo que el personal de seguridad considere riesgoso.</p>
                <p>La puntualidad es responsabilidad del usuario; una vez iniciada la función, el acceso podrá estar restringido o condicionado al criterio del personal autorizado.</p>
            </article>

            <article class="terminos-block" id="propiedad">
                <div class="terminos-block__head">
                    <div class="terminos-block__icon"><i class="bi bi-c-circle"></i></div>
                    <div>
                        <span class="terminos-block__num">Sección 04</span>
                        <h2 class="terminos-display">Propiedad Intelectual</h2>
                    </div>
                </div>
                <p>Todos los contenidos del sitio, incluidos textos, imágenes, logotipos, diseños, materiales gráficos y audiovisuales, son propiedad del Teatro o cuentan con las licencias correspondientes, y están protegidos por la legislación mexicana e internacional en materia de propiedad intelectual. Queda prohibida su reproducción total o parcial sin autorización previa y por escrito.</p>
            </article>

            <article class="terminos-block" id="datos">
                <div class="terminos-block__head">
                    <div class="terminos-block__icon"><i class="bi bi-shield-lock"></i></div>
                    <div>
                        <span class="terminos-block__num">Sección 05</span>
                        <h2 class="terminos-display">Protección de Datos Personales</h2>
                    </div>
                </div>
                <p>Los datos personales proporcionados por el usuario serán tratados conforme a la Ley Federal de Protección de Datos Personales en Posesión de los Particulares.</p>
                <p>Dichos datos serán utilizados exclusivamente para fines administrativos, de comunicación, confirmación de compras y notificaciones relacionadas con eventos o actividades del Teatro.</p>
                <p>El usuario podrá ejercer sus derechos ARCO mediante solicitud enviada al correo electrónico oficial del Teatro: <a href="mailto:teatroconstitucion@outlook.es">teatroconstitucion@outlook.es</a>, o asistiendo directamente a la taquilla física del Teatro Constitución de Apatzingán.</p>
            </article>

            <article class="terminos-block" id="cookies">
                <div class="terminos-block__head">
                    <div class="terminos-block__icon"><i class="bi bi-sliders"></i></div>
                    <div>
                        <span class="terminos-block__num">Sección 06</span>
                        <h2 class="terminos-display">Uso de Cookies</h2>
                    </div>
                </div>
                <p>El sitio podrá utilizar cookies con fines estadísticos, de mejora en la experiencia del usuario o para optimización de navegación. El usuario puede deshabilitar el uso de cookies desde la configuración de su navegador.</p>
            </article>

            <article class="terminos-block" id="enlaces">
                <div class="terminos-block__head">
                    <div class="terminos-block__icon"><i class="bi bi-link-45deg"></i></div>
                    <div>
                        <span class="terminos-block__num">Sección 07</span>
                        <h2 class="terminos-display">Enlaces a Sitios de Terceros</h2>
                    </div>
                </div>
                <p>El sitio puede contener enlaces a páginas externas o plataformas de terceros. El Teatro no se hace responsable del contenido, prácticas o políticas de privacidad de dichos sitios, ya que operan de manera independiente.</p>
            </article>

            <article class="terminos-block" id="responsabilidad">
                <div class="terminos-block__head">
                    <div class="terminos-block__icon"><i class="bi bi-exclamation-triangle"></i></div>
                    <div>
                        <span class="terminos-block__num">Sección 08</span>
                        <h2 class="terminos-display">Limitación de Responsabilidad</h2>
                    </div>
                </div>
                <p>El Teatro no garantiza la disponibilidad continua y libre de errores del sitio web, y no será responsable por daños derivados del uso o imposibilidad de uso del mismo, ya sea por fallas técnicas, mantenimiento, actualizaciones o causas ajenas al control del Teatro.</p>
            </article>

            <article class="terminos-block" id="modificaciones">
                <div class="terminos-block__head">
                    <div class="terminos-block__icon"><i class="bi bi-pencil-square"></i></div>
                    <div>
                        <span class="terminos-block__num">Sección 09</span>
                        <h2 class="terminos-display">Modificaciones a los Términos y Condiciones</h2>
                    </div>
                </div>
                <p>El Teatro se reserva el derecho de modificar, actualizar o complementar, en cualquier momento y sin previo aviso, el contenido de los presentes Términos y Condiciones. Las modificaciones entrarán en vigor a partir de su publicación en este sitio.</p>
            </article>

            <article class="terminos-block" id="legislacion">
                <div class="terminos-block__head">
                    <div class="terminos-block__icon"><i class="bi bi-bank"></i></div>
                    <div>
                        <span class="terminos-block__num">Sección 10</span>
                        <h2 class="terminos-display">Legislación Aplicable y Jurisdicción</h2>
                    </div>
                </div>
                <p>Los presentes Términos y Condiciones se rigen por las leyes de los Estados Unidos Mexicanos. Cualquier controversia derivada de su interpretación o cumplimiento se someterá a los tribunales competentes del Estado de Michoacán.</p>
            </article>
        </div>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-col">
                <h4>Teatro Constitución · Apatzingan</h4>
                <p class="muted">Arte escénico, música y cultura para todos. Vive la experiencia teatral.</p>
                <div class="social" aria-label="Redes sociales">
                    <a href="https://www.facebook.com/people/Teatro-Constituci%C3%B3n/100077079712986/" target="_blank" rel="noopener noreferrer" title="Facebook" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                    <a href="https://www.instagram.com/teatro_constitucion_apatzingan?igsh=YzZ4YmxhcWU2Zmk%3D" target="_blank" rel="noopener noreferrer" title="Instagram" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                </div>
            </div>
            <div class="footer-col">
                <h4>Enlaces</h4>
                <ul class="footer-links">
                    <li><a href="index.php">Inicio</a></li>
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
                <div>© <?php echo date('Y'); ?> Teatro Constitución · Apatzingan. Todos los derechos reservados.</div>
                <div class="muted"><a href="terminos.php" style="color: inherit; text-decoration: none;">Términos · Privacidad</a></div>
            </div>
        </div>
    </footer>

    <script>
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const mainNav = document.getElementById('mainNav');
        const navBackdrop = document.getElementById('navBackdrop');

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

        document.documentElement.style.scrollBehavior = 'smooth';
    </script>
</body>
</html>
