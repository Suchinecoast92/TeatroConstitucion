<?php
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Términos y Condiciones · Teatro Constitución</title>
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
            .main-content { padding: 24px 14px 40px; }
            .terms-card { padding: 20px 16px; }
            .terms-card h1 { font-size: 1.65rem; }
            .terms-card h2 { font-size: 1.05rem; }
            .terms-card h3 { font-size: 1.2rem; }
            .terms-card p, .terms-card li { font-size: 0.92rem; line-height: 1.65; }
            .terms-card ol { padding-left: 18px; }
            .site-footer { margin-top: 40px; }
        }

        .main-content {
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 20px 80px;
        }

        .page-title {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .page-subtitle {
            font-size: 1rem;
            color: #cbd5f5;
            margin-bottom: 24px;
        }

        .glass-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.08));
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow:
                0 8px 32px rgba(0, 0, 0, 0.1),
                inset 0 1px 1px rgba(255, 255, 255, 0.5),
                inset 0 -1px 1px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .terms-card {
            padding: 28px 24px 24px;
        }
        .terms-card h1 {
            font-size: 2.05rem;
            margin-bottom: 6px;
            font-weight: 750;
            letter-spacing: -0.02em;
        }
        .terms-card h2 {
            font-size: 1.15rem;
            font-weight: 550;
            color: #d4d4d8;
            margin-bottom: 22px;
        }
        .terms-card h3 {
            font-size: 1.35rem;
            font-weight: 700;
            margin-top: 26px;
            margin-bottom: 10px;
            color: #fafafa;
            letter-spacing: -0.01em;
        }
        .terms-card p {
            font-size: 0.98rem;
            line-height: 1.8;
            color: #e5e7eb;
            margin-bottom: 10px;
        }
        .terms-card ol {
            padding-left: 24px;
            margin-top: 12px;
        }
        .terms-card li {
            margin-bottom: 18px;
        }
        .terms-card li strong {
            display: inline-block;
            margin-bottom: 4px;
        }

        .site-footer {
            background: rgba(10,10,12,0.95);
            border-top: 1px solid rgba(255,255,255,0.06);
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
        .footer-bottom-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
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
        <div class="glass-card terms-card">
            <h1>Términos y Condiciones de Uso</h1>
            <h2>Teatro Constitución de Apatzingán</h2>

            <p>El acceso y uso del sitio web del Teatro Constitución de Apatzingán (en adelante, "El Teatro") implica la aceptación plena y sin reserva de los presentes Términos y Condiciones. Si el usuario no está de acuerdo con su contenido, deberá abstenerse de utilizar este sitio.</p>

            <ol>
                <li>
                    <strong>Uso del Sitio Web</strong>
                    <p>El usuario se obliga a utilizar el sitio de manera lícita, adecuada y conforme a la legislación aplicable. Queda estrictamente prohibido alterar, modificar o interferir en el funcionamiento del sitio, así como utilizar herramientas automatizadas (incluidos bots) para realizar compras o cualquier otra actividad dentro del mismo.</p>
                </li>

                <li>
                    <strong>Compra de Boletos</strong>
                    <p>Todos los precios publicados en este sitio son netos, es decir, ya incluyen impuestos y no están sujetos a cargos adicionales por servicio.</p>
                    <p>Todas las compras realizadas a través del sitio se consideran definitivas y no reembolsables, salvo en el caso de cancelación del evento por parte del Teatro.</p>
                    <p>En caso de reprogramación del evento, los boletos previamente adquiridos serán válidos para la nueva fecha establecida.</p>
                    <p>Es responsabilidad exclusiva del usuario revisar con atención la fecha, función, horario, zona y asiento antes de confirmar su compra.</p>
                    <p>El Teatro no se hace responsable por boletos adquiridos a través de terceros no autorizados.</p>
                </li>

                <li>
                    <strong>Acceso y Admisión al Teatro</strong>
                    <p>Para ingresar a las instalaciones es indispensable presentar un boleto válido en formato físico o digital.</p>
                    <p>El Teatro se reserva el derecho de admisión por razones de seguridad, incumplimiento de normas internas o comportamientos que afecten la experiencia de otros asistentes.</p>
                    <p>No se permitirá el acceso con alimentos, bebidas, cámaras profesionales, objetos punzocortantes, sustancias ilícitas o cualquier artículo que el personal de seguridad considere riesgoso.</p>
                    <p>La puntualidad es responsabilidad del usuario; una vez iniciada la función, el acceso podrá estar restringido o condicionado al criterio del personal autorizado.</p>
                </li>

                <li>
                    <strong>Propiedad Intelectual</strong>
                    <p>Todos los contenidos del sitio, incluidos textos, imágenes, logotipos, diseños, materiales gráficos y audiovisuales, son propiedad del Teatro o cuentan con las licencias correspondientes, y están protegidos por la legislación mexicana e internacional en materia de propiedad intelectual. Queda prohibida su reproducción total o parcial sin autorización previa y por escrito.</p>
                </li>

                <li>
                    <strong>Protección de Datos Personales</strong>
                    <p>Los datos personales proporcionados por el usuario serán tratados conforme a la Ley Federal de Protección de Datos Personales en Posesión de los Particulares.</p>
                    <p>Dichos datos serán utilizados exclusivamente para fines administrativos, de comunicación, confirmación de compras y notificaciones relacionadas con eventos o actividades del Teatro.</p>
                    <p>El usuario podrá ejercer sus derechos ARCO mediante solicitud enviada al correo electrónico oficial del Teatro: <a href="mailto:teatroconstitucion@outlook.es">teatroconstitucion@outlook.es</a>, o asistiendo directamente a la taquilla física del Teatro Constitución de Apatzingán.</p>
                </li>

                <li>
                    <strong>Uso de Cookies</strong>
                    <p>El sitio podrá utilizar cookies con fines estadísticos, de mejora en la experiencia del usuario o para optimización de navegación. El usuario puede deshabilitar el uso de cookies desde la configuración de su navegador.</p>
                </li>

                <li>
                    <strong>Enlaces a Sitios de Terceros</strong>
                    <p>El sitio puede contener enlaces a páginas externas o plataformas de terceros. El Teatro no se hace responsable del contenido, prácticas o políticas de privacidad de dichos sitios, ya que operan de manera independiente.</p>
                </li>

                <li>
                    <strong>Limitación de Responsabilidad</strong>
                    <p>El Teatro no garantiza la disponibilidad continua y libre de errores del sitio web, y no será responsable por daños derivados del uso o imposibilidad de uso del mismo, ya sea por fallas técnicas, mantenimiento, actualizaciones o causas ajenas al control del Teatro.</p>
                </li>

                <li>
                    <strong>Modificaciones a los Términos y Condiciones</strong>
                    <p>El Teatro se reserva el derecho de modificar, actualizar o complementar, en cualquier momento y sin previo aviso, el contenido de los presentes Términos y Condiciones. Las modificaciones entrarán en vigor a partir de su publicación en este sitio.</p>
                </li>

                <li>
                    <strong>Legislación Aplicable y Jurisdicción</strong>
                    <p>Los presentes Términos y Condiciones se rigen por las leyes de los Estados Unidos Mexicanos. Cualquier controversia derivada de su interpretación o cumplimiento se someterá a los tribunales competentes del Estado de Michoacán.</p>
                </li>
            </ol>
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
                <div class="muted"><a href="terminos.php" style="color: inherit; text-decoration: none;">Términos</a> · Privacidad</div>
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
    </script>
</body>
</html>
