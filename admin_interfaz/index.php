<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    die('<html><head><link rel="stylesheet" href="../assets/css/teatro-style.css"></head>
    <body style="display:flex;justify-content:center;align-items:center;height:100vh;">
    <div style="text-align:center;color:var(--danger);"><p>Acceso denegado</p></div></body></html>');
}
require_once '../transacciones_helper.php';
registrar_transaccion('admin_panel', 'Ingreso al panel de administración');

if ($_SESSION['usuario_rol'] !== 'admin') {
    if (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']) {
        die('<html><head><link rel="stylesheet" href="../assets/css/teatro-style.css"></head>
        <body style="display:flex;justify-content:center;align-items:center;height:100vh;">
        <div style="text-align:center;color:var(--danger);"><p>Requiere verificación de administrador</p></div></body></html>');
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
    <link rel="icon" href="../crt_interfaz/imagenes_teatro/nat.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/teatro-style.css">
    <style>
        body {
            background: var(--bg-primary);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .admin-sidebar {
            width: 240px;
            background: var(--bg-secondary);
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color);
            flex-shrink: 0;
            transition: width 0.3s ease, padding 0.3s ease;
            overflow: hidden;
        }

        .admin-sidebar.collapsed {
            width: 0;
            padding: 0;
            border-right: none;
        }

        .toggle-btn {
            background: transparent;
            border: none;
            color: var(--text-primary);
            font-size: 1.2rem;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: var(--radius-sm);
            margin-right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toggle-btn:hover {
            background: var(--bg-tertiary);

        .admin-sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .admin-sidebar-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-sidebar-title i {
            width: 36px;
            height: 36px;
            background: var(--gradient-primary);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }

        .admin-menu {
            flex: 1;
            padding: 12px 8px;
            overflow-y: auto;
        }

        .admin-menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            margin-bottom: 2px;
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: var(--transition-fast);
            cursor: pointer;
        }

        .admin-menu-item:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .admin-menu-item.active {
            background: rgba(21, 97, 240, 0.15);
            color: var(--accent-blue);
        }

        .admin-menu-item i {
            font-size: 1.1rem;
            width: 22px;
            text-align: center;
        }

        .admin-menu-divider {
            height: 1px;
            background: var(--border-color);
            margin: 10px 0;
        }

        .admin-menu-item.danger {
            color: var(--danger);
        }

        .admin-menu-item.danger:hover {
            background: var(--danger-bg);
        }

        .admin-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .admin-header {
            background: var(--bg-secondary);
            padding: 14px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .admin-header-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-header-title i {
            color: var(--accent-blue);
        }

        .db-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .db-label {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .db-toggle {
            display: flex;
            background: var(--bg-tertiary);
            border-radius: var(--radius-md);
            padding: 3px;
            gap: 3px;
        }

        .db-toggle button {
            padding: 6px 12px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            cursor: pointer;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            font-weight: 600;
            transition: var(--transition-fast);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .db-toggle button:hover {
            color: var(--text-primary);
        }

        .db-toggle button.active {
            background: var(--accent-blue);
            color: white;
        }

        .admin-frame {
            flex: 1;
            width: 100%;
            border: none;
            background: var(--bg-primary);
        }
    </style>
</head>

<body>
    <aside class="admin-sidebar">
        <div class="admin-sidebar-header">
            <div class="admin-sidebar-title">
                <i class="bi bi-gear-fill"></i>
                <span>Administración</span>
            </div>
        </div>

        <nav class="admin-menu">
            <a class="admin-menu-item active" href="rpt_reportes/index.php" target="contentFrame">
                <i class="bi bi-graph-up-arrow"></i> <span>Reportes</span>
            </a>
            <a class="admin-menu-item" href="../mp_interfaz/index.php" target="contentFrame" data-action="collapse">
                <i class="bi bi-grid-3x3-gap-fill"></i> <span>Mapeo de Asientos</span>
            </a>
            <?php if ($_SESSION['usuario_rol'] === 'admin'): ?>
                <div class="admin-menu-divider"></div>
                <div class="admin-menu-section-title">Administración</div>
                
                <a class="admin-menu-item" href="transacciones/index.php" target="contentFrame">
                    <i class="bi bi-arrow-left-right"></i> <span>Transacciones</span>
                </a>

                <a class="admin-menu-item" href="usuarios/index.php" target="contentFrame">
                    <i class="bi bi-people-fill"></i> <span>Usuarios</span>
                </a>
            <?php endif; ?>
        </nav>
    </aside>

    <main class="admin-content">
        <header class="admin-header">
            <div class="admin-header-title">
                <button class="toggle-btn" id="sidebarToggle" title="Alternar Menú">
                    <i class="bi bi-list"></i>
                </button>
                <i class="bi bi-speedometer2"></i> <span>Panel de Administración</span>
            </div>
            <div class="db-selector">
                <span class="db-label">Base de datos:</span>
                <div class="db-toggle">
                    <button id="btnAmbas" onclick="cambiarBD('ambas')"><i class="bi bi-collection"></i> Ambas</button>
                    <button id="btnActual" onclick="cambiarBD('actual')"><i class="bi bi-database"></i> Actual</button>
                    <button id="btnHistorico" onclick="cambiarBD('historico')"><i class="bi bi-archive"></i>
                        Histórico</button>
                </div>
            </div>
        </header>
        <iframe class="admin-frame" name="contentFrame" id="contentFrame" src="rpt_reportes/index.php"></iframe>
    </main>

    <script>
        let dbActual = sessionStorage.getItem('admin_db') || 'ambas';

        document.addEventListener('DOMContentLoaded', () => {
            actualizarEstadoToggle();


            document.querySelectorAll('.admin-menu-item').forEach(link => {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    document.querySelectorAll('.admin-menu-item').forEach(item => item.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Collapse sidebar if it's the mapping view
                    const sidebar = document.querySelector('.admin-sidebar');
                    if (this.dataset.action === 'collapse') {
                         sidebar.classList.add('collapsed');
                    } else {
                        // Optional: auto-expand for other views if desired, but user might want to keep it customized.
                        // For now we only enforce collapse on mapping entry as requested.
                        // sidebar.classList.remove('collapsed'); 
                    }

                    document.getElementById('contentFrame').src = agregarParamDB(this.getAttribute('href'));
                });
            });

            document.getElementById('sidebarToggle').addEventListener('click', () => {
                document.querySelector('.admin-sidebar').classList.toggle('collapsed');
            });

            document.getElementById('contentFrame').src = agregarParamDB('rpt_reportes/index.php');
        });

        function cambiarBD(db) {
            dbActual = db;
            sessionStorage.setItem('admin_db', db);
            actualizarEstadoToggle();
            const activeLink = document.querySelector('.admin-menu-item.active');
            const href = activeLink ? activeLink.getAttribute('href') : 'rpt_reportes/index.php';
            document.getElementById('contentFrame').src = agregarParamDB(href);
        }

        function actualizarEstadoToggle() {
            document.getElementById('btnActual').classList.toggle('active', dbActual === 'actual');
            document.getElementById('btnHistorico').classList.toggle('active', dbActual === 'historico');
            document.getElementById('btnAmbas').classList.toggle('active', dbActual === 'ambas');
        }

        function agregarParamDB(url) {
            return url + (url.includes('?') ? '&' : '?') + 'db=' + dbActual;
        }
    </script>
</body>

</html>