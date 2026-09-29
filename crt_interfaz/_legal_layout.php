<?php
/**
 * Plantilla compartida de las páginas informativas del sitio público
 * (términos, aviso de privacidad, mi pedido). Estilos en css/legal.css.
 */
require_once dirname(__DIR__) . '/config/legal.php';

function legal_h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function legal_page_open(string $titulo): void
{
    ?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= legal_h($titulo) ?> · Teatro Constitución</title>
    <link rel="icon" href="imagenes_teatro/nat.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/legal.css?v=2">
</head>
<body>
    <div class="nav-backdrop" id="navBackdrop" aria-hidden="true"></div>
    <nav class="site-nav" id="mainNav" aria-label="Principal">
        <a href="index.php">Inicio</a>
        <a href="acerca.php">Acerca del teatro</a>
        <a href="contacto.php">Contacto / Reservaciones</a>
        <a href="mi_pedido.php">Mi pedido</a>
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
<?php
}

function legal_page_close(): void
{
    ?>
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
                    <li><a href="mi_pedido.php">Mi pedido</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Contacto</h4>
                <ul class="footer-links">
                    <li><i class="bi bi-telephone"></i> <span><?= legal_h(LEGAL_TELEFONO) ?></span></li>
                    <li><i class="bi bi-envelope"></i> <span><?= legal_h(LEGAL_CORREO) ?></span></li>
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
                <div>© <?= date('Y') ?> Teatro Constitución · Apatzingan. Todos los derechos reservados.</div>
                <div class="muted">
                    <a href="terminos.php" style="color: inherit; text-decoration: none;">Términos</a> ·
                    <a href="privacidad.php" style="color: inherit; text-decoration: none;">Aviso de privacidad</a>
                </div>
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
            hamburgerBtn.addEventListener('click', () => setNavOpen(!mainNav.classList.contains('open')));
        }
        if (navBackdrop) {
            navBackdrop.addEventListener('click', () => setNavOpen(false));
        }
        mainNav?.querySelectorAll('a').forEach(a => a.addEventListener('click', () => setNavOpen(false)));
        document.documentElement.style.scrollBehavior = 'smooth';
    </script>
</body>
</html>
<?php
}

/**
 * Bloque numerado estilo términos.
 */
function legal_bloque_open(string $id, string $icono, string $num, string $titulo): void
{
    ?>
            <article class="terminos-block" id="<?= legal_h($id) ?>">
                <div class="terminos-block__head">
                    <div class="terminos-block__icon"><i class="bi <?= legal_h($icono) ?>"></i></div>
                    <div>
                        <span class="terminos-block__num"><?= legal_h($num) ?></span>
                        <h2 class="terminos-display"><?= legal_h($titulo) ?></h2>
                    </div>
                </div>
<?php
}

function legal_bloque_close(): void
{
    echo "            </article>\n";
}
