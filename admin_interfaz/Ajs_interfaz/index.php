<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SESSION['usuario_rol'] !== 'admin') {
    if (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']) {
        die('<html><head><link rel="stylesheet" href="../../assets/css/teatro-style.css"></head>
        <body style="display:flex;justify-content:center;align-items:center;height:100vh;">
        <div style="text-align:center;color:var(--danger);"><p>Acceso denegado</p></div></body></html>');
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajustes</title>
    <link rel="icon" href="../../crt_interfaz/imagenes_teatro/nat.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../assets/css/teatro-style.css">
    <style>
        body {
            background: var(--bg-primary);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* Sidebar */
        .settings-sidebar {
            width: 260px;
            background: var(--bg-secondary);
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--border-color);
            flex-shrink: 0;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .settings-sidebar.collapsed {
            width: 80px;
        }

        .settings-sidebar.collapsed .settings-sidebar-subtitle,
        .settings-sidebar.collapsed .settings-menu-section-title,
        .settings-sidebar.collapsed .menu-text,
        .settings-sidebar.collapsed .toggle-text,
        .settings-sidebar.collapsed .settings-sidebar-title span {
            display: none;
        }

        .settings-sidebar.collapsed .settings-sidebar-title {
            justify-content: center;
        }
        
        .settings-sidebar.collapsed .settings-menu-item {
            justify-content: center;
            padding: 12px 0;
        }

        .settings-sidebar.collapsed .settings-menu-item .menu-icon {
            margin: 0;
        }

        .settings-sidebar.collapsed .settings-back {
            text-align: center;
        }

        .settings-sidebar.collapsed .btn-toggle {
            justify-content: center;
        }

        .settings-sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .settings-sidebar-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .settings-sidebar-title i {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
        }

        .settings-sidebar-subtitle {
            color: var(--text-muted);
            font-size: 0.8rem;
            margin-top: 4px;
        }

        /* Menu */
        .settings-menu {
            flex: 1;
            padding: 16px 10px;
            overflow-y: auto;
        }

        .settings-menu-section {
            margin-bottom: 20px;
        }

        .settings-menu-section-title {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0 14px;
            margin-bottom: 8px;
        }

        .settings-menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            margin-bottom: 4px;
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: var(--transition-fast);
            cursor: pointer;
        }

        .settings-menu-item:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .settings-menu-item.active {
            background: rgba(139, 92, 246, 0.15);
            color: #8b5cf6;
        }

        .settings-menu-item i {
            font-size: 1.15rem;
            width: 24px;
            text-align: center;
        }

        .settings-menu-item .menu-icon {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .settings-menu-item .menu-icon.green {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }

        .settings-menu-item .menu-icon.blue {
            background: rgba(21, 97, 240, 0.15);
            color: var(--accent-blue);
        }

        .settings-menu-item .menu-icon.purple {
            background: rgba(139, 92, 246, 0.15);
            color: #8b5cf6;
        }

        .settings-menu-item.active .menu-icon {
            background: rgba(139, 92, 246, 0.25);
        }

        .settings-menu-item .menu-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .settings-menu-item .menu-text span {
            font-size: 0.85rem;
            font-weight: 600;
        }

        .settings-menu-item .menu-text small {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 400;
        }

        .settings-menu-divider {
            height: 1px;
            background: var(--border-color);
            margin: 12px 0;
        }

        /* Back button */
        .settings-back {
            padding: 16px;
            border-top: 1px solid var(--border-color);
        }

        .settings-back a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: var(--radius-md);
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: var(--transition-fast);
        }

        /* Toggle Button */
        .btn-toggle {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: var(--radius-md);
            color: var(--text-muted);
            background: transparent;
            border: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition-fast);
            cursor: pointer;
        }

        .btn-toggle:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .btn-toggle i {
            font-size: 1.2rem;
        }

        /* Content area */
        .settings-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .settings-header {
            background: var(--bg-secondary);
            padding: 16px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .settings-header-icon {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .settings-header-icon.green {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }

        .settings-header-icon.blue {
            background: rgba(21, 97, 240, 0.15);
            color: var(--accent-blue);
        }

        .settings-header-icon.purple {
            background: rgba(139, 92, 246, 0.15);
            color: #8b5cf6;
        }

        .settings-header-text h2 {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .settings-header-text p {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin: 0;
        }

        .settings-frame {
            flex: 1;
            width: 100%;
            border: none;
            background: var(--bg-primary);
        }

        /* Welcome content */
        .settings-welcome {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .settings-welcome-content {
            text-align: center;
            max-width: 500px;
        }

        .settings-welcome-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 2rem;
            color: white;
        }

        .settings-welcome-content h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 12px 0;
        }

        .settings-welcome-content p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin: 0;
            line-height: 1.6;
        }
    </style>
</head>

<body>
    <aside class="settings-sidebar">
        <div class="settings-sidebar-header">
            <div class="settings-sidebar-title">
                <i class="bi bi-sliders"></i>
                <div>
                    <span>Ajustes</span>
                    <div class="settings-sidebar-subtitle">Configuración del sistema</div>
                </div>
            </div>
        </div>

        <nav class="settings-menu">
            <div class="settings-menu-section">
                <div class="settings-menu-section-title">Boletos y Precios</div>
                
                <a class="settings-menu-item" href="../dsc_boletos/index.php" data-title="Descuentos" data-desc="Gestiona los descuentos disponibles" data-icon="green">
                    <div class="menu-icon green">
                        <i class="bi bi-percent"></i>
                    </div>
                    <div class="menu-text">
                        <span>Descuentos</span>
                        <small>Niños, tercera edad y cortesías</small>
                    </div>
                </a>

                <a class="settings-menu-item" href="../ctg_boletos/index.php" data-title="Categorías de Boletos" data-desc="Configura categorías y precios" data-icon="blue">
                    <div class="menu-icon blue">
                        <i class="bi bi-tags-fill"></i>
                    </div>
                    <div class="menu-text">
                        <span>Categorías de Boletos</span>
                        <small>Precios y zonas por evento</small>
                    </div>
                </a>

                <a class="settings-menu-item" href="../bsc_boletos/index.php" data-title="Buscador de Boletos" data-desc="Busca y gestiona todos los boletos" data-icon="purple">
                    <div class="menu-icon purple">
                        <i class="bi bi-search"></i>
                    </div>
                    <div class="menu-text">
                        <span>Buscador de Boletos</span>
                        <small>Buscar, ver, imprimir y cancelar</small>
                    </div>
                </a>
            </div>

            <div class="settings-menu-divider"></div>

            <div class="settings-menu-section">
                <div class="settings-menu-section-title">Teatro</div>
                
                <a class="settings-menu-item" href="../../mp_interfaz/index.php" data-title="Mapeo de Asientos" data-desc="Define la distribución del teatro" data-icon="purple">
                    <div class="menu-icon purple">
                        <i class="bi bi-grid-3x3"></i>
                    </div>
                    <div class="menu-text">
                        <span>Mapeo de Asientos</span>
                        <small>Distribución y zonas del teatro</small>
                    </div>
                </a>
            </div>
        </nav>

        <div class="settings-back">
            <button id="sidebarToggle" class="btn-toggle" title="Minimizar menú">
                <i class="bi bi-arrow-bar-left"></i>
                <span class="toggle-text">Ocultar Menú</span>
            </button>
        </div>
    </aside>

    <main class="settings-content">
        <header class="settings-header" id="settingsHeader" style="display: none;">
            <div class="settings-header-icon" id="headerIcon">
                <i class="bi bi-sliders"></i>
            </div>
            <div class="settings-header-text">
                <h2 id="headerTitle">Ajustes</h2>
                <p id="headerDesc">Selecciona una opción del menú</p>
            </div>
        </header>

        <div class="settings-welcome" id="welcomeContent">
            <div class="settings-welcome-content">
                <div class="settings-welcome-icon">
                    <i class="bi bi-sliders"></i>
                </div>
                <h2>Configuración del Sistema</h2>
                <p>Selecciona una opción del menú lateral para configurar descuentos, categorías de boletos o el mapeo de asientos del teatro.</p>
            </div>
        </div>

        <iframe class="settings-frame" name="contentFrame" id="contentFrame" style="display: none;"></iframe>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const menuItems = document.querySelectorAll('.settings-menu-item');
            const welcomeContent = document.getElementById('welcomeContent');
            const contentFrame = document.getElementById('contentFrame');
            const settingsHeader = document.getElementById('settingsHeader');
            const headerIcon = document.getElementById('headerIcon');
            const headerTitle = document.getElementById('headerTitle');
            const headerDesc = document.getElementById('headerDesc');

            // Sidebar Toggle Logic
            const sidebar = document.querySelector('.settings-sidebar');
            const toggleBtn = document.getElementById('sidebarToggle');
            const toggleIcon = toggleBtn.querySelector('i');
            const toggleText = toggleBtn.querySelector('.toggle-text');

            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                const isCollapsed = sidebar.classList.contains('collapsed');
                
                if (isCollapsed) {
                    toggleIcon.className = 'bi bi-arrow-bar-right';
                    toggleText.textContent = ''; // Hidden by CSS anyway, but cleaner
                    toggleBtn.title = 'Expandir menú';
                } else {
                    toggleIcon.className = 'bi bi-arrow-bar-left';
                    toggleText.textContent = 'Ocultar Menú';
                    toggleBtn.title = 'Minimizar menú';
                }
            });

            menuItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Update active state
                    menuItems.forEach(mi => mi.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Update header
                    const title = this.dataset.title;
                    const desc = this.dataset.desc;
                    const iconColor = this.dataset.icon;
                    const iconEl = this.querySelector('.menu-icon i').className;
                    
                    headerTitle.textContent = title;
                    headerDesc.textContent = desc;
                    headerIcon.className = 'settings-header-icon ' + iconColor;
                    headerIcon.innerHTML = '<i class="' + iconEl + '"></i>';
                    
                    // Show content
                    welcomeContent.style.display = 'none';
                    settingsHeader.style.display = 'flex';
                    contentFrame.style.display = 'block';
                    contentFrame.src = this.getAttribute('href');
                });
            });
        });
    </script>
</body>

</html>
