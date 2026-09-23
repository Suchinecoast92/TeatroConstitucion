<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto · Teatro Constitución</title>
    <link rel="icon" href="imagenes_teatro/nat.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&display=swap" rel="stylesheet">
    
    <style>
        body {
            background-image: url('imagenes_teatro/InteriorChido11.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: #e8e8e8;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
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
            gap: 20px;
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
        }

        /* Contenido principal */
        .main-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 44px 28px 64px;
        }

        .contacto-display {
            font-family: "Cormorant Garamond", Georgia, serif;
            font-weight: 600;
            letter-spacing: -0.02em;
            line-height: 1.15;
        }

        .hero-section {
            text-align: center;
            margin-bottom: 32px;
            /* Solo mover: animar opacity en el padre rompe backdrop-filter del glass card */
            animation: fadeInUpSoft 0.8s ease;
        }

        .hero-panel {
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

        .hero-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.7), transparent);
        }

        .hero-section h1 {
            font-size: clamp(2.35rem, 4.8vw, 3.4rem);
            font-weight: 600;
            margin-bottom: 20px;
            color: #ffffff;
            text-shadow: 0 6px 28px rgba(0, 0, 0, 0.5);
        }

        .hero-section p {
            font-size: clamp(1.05rem, 1.7vw, 1.18rem);
            line-height: 1.8;
            color: rgba(255, 255, 255, 0.92);
            max-width: 820px;
            margin: 0 auto 22px;
            text-shadow: none;
        }

        .contacto-cta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
            margin-top: 8px;
        }

        .contacto-cta-row--card {
            justify-content: flex-start;
            margin-top: 14px;
        }

        .contacto-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            transition: transform .2s ease, filter .2s ease, box-shadow .2s ease;
        }

        .contacto-btn--primary {
            background: linear-gradient(160deg, rgba(255,255,255,.92), rgba(220,220,224,.88));
            color: #0a0a0a;
            box-shadow: 0 10px 28px rgba(0,0,0,.35);
            padding: 14px 24px;
            font-size: 1.05rem;
            border-radius: 14px;
        }

        .contacto-btn--ghost {
            background: rgba(0,0,0,.35);
            color: #fff;
            border: 1px solid rgba(255,255,255,.28);
        }

        .contacto-btn:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
            color: inherit;
        }

        /* Sección de ubicación */
        .location-section {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.06));
            backdrop-filter: blur(25px) saturate(180%);
            -webkit-backdrop-filter: blur(25px) saturate(180%);
            border-radius: 18px;
            padding: 40px 42px;
            margin-bottom: 32px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 
                0 12px 40px rgba(0, 0, 0, 0.12),
                inset 0 1px 1px rgba(255, 255, 255, 0.5),
                inset 0 -1px 1px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
            animation: fadeInUpSoft 0.8s ease 0.12s both;
        }

        .location-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.7), transparent);
        }

        .location-section h2 {
            font-size: clamp(1.75rem, 3.2vw, 2.35rem);
            font-weight: 600;
            margin-bottom: 28px;
            color: #ffffff;
            text-align: center;
            letter-spacing: -0.02em;
            text-shadow: 0 2px 12px rgba(0, 0, 0, 0.2);
        }

        .location-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-top: 8px;
        }

        .location-info {
            color: rgba(255, 255, 255, 0.9);
            max-width: 100%;
            margin: 0 auto;
            width: 100%;
            font-size: 1.02rem;
            line-height: 1.65;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px 28px;
        }

        .location-info h3 {
            color: #ffffff;
            margin-bottom: 14px;
            font-weight: 600;
            font-size: 1.1rem;
            letter-spacing: -0.02em;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            text-align: center;
        }

        .location-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 0;
            padding: 20px 18px;
            border-bottom: none;
            border-radius: 14px;
            background: rgba(0, 0, 0, 0.22);
            border: 1px solid rgba(255, 255, 255, 0.14);
            transition: transform .2s ease, border-color .2s ease, background .2s ease;
        }

        .location-item:hover {
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.28);
            background: rgba(0, 0, 0, 0.3);
        }

        .location-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 20px;
        }

        .location-item i {
            color: #ffffff;
            font-size: 1.35rem;
            margin-top: 2px;
            opacity: 0.95;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        .location-item strong {
            color: #ffffff;
            font-weight: 650;
            font-size: 1.05rem;
            display: block;
            margin-bottom: 4px;
        }

        .location-item a {
            color: #fff;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .reservas-section {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.06));
            backdrop-filter: blur(25px) saturate(180%);
            -webkit-backdrop-filter: blur(25px) saturate(180%);
            border-radius: 18px;
            padding: 40px 42px;
            margin-bottom: 32px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow:
                0 12px 40px rgba(0, 0, 0, 0.12),
                inset 0 1px 1px rgba(255, 255, 255, 0.5);
            position: relative;
            overflow: hidden;
            animation: fadeInUpSoft 0.8s ease 0.2s both;
        }

        .reservas-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.7), transparent);
        }

        .reservas-section h2 {
            font-size: clamp(1.75rem, 3.2vw, 2.35rem);
            font-weight: 600;
            margin: 0 0 14px;
            color: #fff;
            text-align: center;
        }

        .reservas-section > p {
            text-align: center;
            max-width: 780px;
            margin: 0 auto 22px;
            color: #cfd3d7;
            font-size: 1.02rem;
            line-height: 1.7;
        }

        .reservas-steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .reservas-steps li {
            background: rgba(0, 0, 0, 0.22);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 14px;
            padding: 20px 18px;
            color: #e8e8e8;
            font-size: 0.98rem;
            line-height: 1.55;
        }

        .reservas-steps strong {
            display: block;
            color: #fff;
            font-size: 1.05rem;
            margin-bottom: 6px;
        }

        .reservas-steps .num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(255,255,255,.16);
            margin-right: 8px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        /* Animaciones */
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

        @keyframes fadeInUpSoft {
            from {
                transform: translateY(18px);
            }
            to {
                transform: translateY(0);
            }
        }

        @media (max-width: 900px) {
            .location-info,
            .reservas-steps {
                grid-template-columns: 1fr;
            }
            .hero-panel,
            .location-section,
            .reservas-section {
                padding: 28px 20px;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-section {
                margin-bottom: 20px;
            }
            .hero-section h1 {
                font-size: 1.85rem;
            }
            .hero-section p {
                font-size: 0.98rem;
            }
            .location-section,
            .reservas-section,
            .hero-panel {
                padding: 24px 18px;
            }
            .location-section h2,
            .reservas-section h2 {
                font-size: 1.45rem;
            }
            .site-footer {
                margin-top: 40px;
            }
        }

        /* Footer */
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

        /* (Tema único) Se mantienen colores neutros/grises, sin cambio de fondo */
        @media (max-width: 1100px){
            .footer-inner { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 600px){
            .footer-inner { grid-template-columns: 1fr; }
            .footer-bottom-inner { flex-direction: column; align-items: flex-start; }
        }

        /* Celular landscape: hero y contacto compactos */
        @media (max-width: 1100px) and (orientation: landscape) and (max-height: 520px) {
            .header-inner { padding: 6px 12px; }
            .brand-logo { width: 32px; height: 32px; margin-left: 0; }
            .brand-name { font-size: 0.82rem; }
            .hamburger { width: 36px; height: 36px; }
            .main-content { padding: 14px 16px 24px; }
            .hero-section { margin-bottom: 12px; }
            .hero-section h1 { font-size: 1.3rem; margin-bottom: 6px; }
            .hero-section p { font-size: 0.85rem; line-height: 1.4; }
            .location-section { padding: 14px 16px; }
            .location-section h2 { font-size: 1.05rem; margin-bottom: 10px; }
            .site-footer { margin-top: 28px; }
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
        <a href="contacto.php" class="active">Contacto / Reservaciones</a>
        <a href="cartelera_cliente.php" class="cta">Ver cartelera completa <i class="bi bi-arrow-right"></i></a>
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
        <section class="hero-section">
            <div class="hero-panel">
                <h1 class="contacto-display">Contacto y Reservaciones</h1>
                <p>Estamos aquí para atenderte. Contáctanos para más información sobre eventos, reservaciones o cualquier consulta que tengas.</p>
                <div class="contacto-cta-row">
                    <a class="contacto-btn contacto-btn--primary" href="cartelera_cliente.php"><i class="bi bi-ticket-perforated"></i> Ver cartelera</a>
                </div>
            </div>
        </section>

        <section class="location-section">
            <h2 class="contacto-display">Información de Contacto</h2>
            <div class="location-grid">
                <div class="location-info">
                    <div class="location-item">
                        <i class="bi bi-telephone-fill"></i>
                        <div>
                            <strong>Teléfono</strong>
                            <a href="tel:+524535345751">+52 (453) 534 5751</a><br>
                            Lunes a Viernes: 09:00 am – 08:00 pm
                            <div class="contacto-cta-row contacto-cta-row--card">
                                <a class="contacto-btn contacto-btn--ghost" href="tel:+524535345751"><i class="bi bi-telephone-fill"></i> Llamar ahora</a>
                            </div>
                        </div>
                    </div>

                    <div class="location-item">
                        <i class="bi bi-envelope-fill"></i>
                        <div>
                            <strong>Correo Electrónico</strong>
                            <a href="mailto:teatroconstitucion@outlook.es">teatroconstitucion@outlook.es</a>
                            <div class="contacto-cta-row contacto-cta-row--card">
                                <a class="contacto-btn contacto-btn--ghost" href="mailto:teatroconstitucion@outlook.es"><i class="bi bi-envelope-fill"></i> Escribir correo</a>
                            </div>
                        </div>
                    </div>

                    <div class="location-item">
                        <i class="bi bi-clock-fill"></i>
                        <div>
                            <strong>Horario de Taquilla</strong>
                            Lunes a Viernes: 09:00 am – 08:00 pm<br>
                            Funciones: Según cartelera
                        </div>
                    </div>

                    <div class="location-item">
                        <i class="bi bi-geo-alt-fill"></i>
                        <div>
                            <strong>Dirección</strong>
                            C. José Sotero de Castañeda 724, Ferrocarril,<br>
                            60690 Apatzingán de la Constitución, Mich.
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="reservas-section">
            <h2 class="contacto-display">Cómo reservar</h2>
            <p>Puedes comprar en taquilla o consultar disponibilidad por teléfono y correo. Para funciones con preventa online, usa la cartelera.</p>
            <ol class="reservas-steps">
                <li>
                    <strong><span class="num">1</span> Elige la función</strong>
                    Revisa horarios y disponibilidad en cartelera o llámanos a taquilla.
                </li>
                <li>
                    <strong><span class="num">2</span> Confirma tus lugares</strong>
                    Indica cantidad de boletos y preferencia de zona. Te orientamos según aforo.
                </li>
                <li>
                    <strong><span class="num">3</span> Paga y listo</strong>
                    Completa el pago en taquilla o por el canal indicado. Conserva tu comprobante o QR.
                </li>
            </ol>
        </section>
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
                <div> Teatro Constitución · Apatzingan. Todos los derechos reservados.</div>
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

        document.addEventListener('DOMContentLoaded', () => {
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
        });
    </script>
</body>
</html>
