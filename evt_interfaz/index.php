<?php
session_start();
include "../conexion.php"; 

// Ejecutar auto-archivado de eventos al entrar
include_once __DIR__ . "/auto_archivar.php";

if (!isset($_SESSION['usuario_id'])) {
    die('<html><head><link rel="stylesheet" href="../assets/css/teatro-style.css"></head>
    <body style="display:flex;justify-content:center;align-items:center;height:100vh;">
    <div style="text-align:center;color:var(--danger);"><p>Acceso denegado</p></div></body></html>');
}

if ($_SESSION['usuario_rol'] !== 'admin' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado'])) {
    die('<html><head><link rel="stylesheet" href="../assets/css/teatro-style.css"></head>
    <body style="display:flex;justify-content:center;align-items:center;height:100vh;">
    <div style="text-align:center;color:var(--danger);"><p>Solo administradores</p></div></body></html>');
}

$sql_check = "SELECT COUNT(*) as total FROM evento WHERE finalizado = 0";
$res_check = $conn->query($sql_check);
$row_check = $res_check->fetch_assoc();
$hay_eventos = $row_check['total'] > 0;

$tab = $_GET['tab'] ?? 'activos';
$src_inicial = '';
$class_activos = ''; 
$class_historial = '';

if ($tab === 'historial') {
    $src_inicial = 'htr_eventos.php';
    $class_historial = 'active';
} else {
    $src_inicial = "act_evento.php";
    if ($tab !== 'historial') $class_activos = 'active';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Eventos</title>
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

        .event-sidebar {
            width: 200px;
            background: var(--bg-secondary);
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--border-color);
            flex-shrink: 0;
        }

        .event-sidebar-header {
            padding: 20px 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .event-sidebar-header i {
            color: var(--accent-blue);
            font-size: 1.2rem;
        }

        .event-sidebar-header h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .event-menu {
            flex: 1;
            padding: 12px 8px;
        }

        .event-menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            margin-bottom: 4px;
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: var(--transition-fast);
        }

        .event-menu-item:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .event-menu-item.active {
            background: rgba(21, 97, 240, 0.15);
            color: var(--accent-blue);
        }

        .event-menu-item i {
            font-size: 1.1rem;
        }

        .event-menu-divider {
            height: 1px;
            background: var(--border-color);
            margin: 10px 0;
        }

        .event-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .event-frame {
            flex: 1;
            width: 100%;
            border: none;
            background: var(--bg-primary);
        }
    </style>
</head>
<body>
    <aside class="event-sidebar">
        <div class="event-sidebar-header">
            <i class="bi bi-calendar-event-fill"></i>
            <h4>Eventos</h4>
        </div>

        <nav class="event-menu">
            <a class="event-menu-item <?= $class_activos ?>" href="act_evento.php" target="contentFrame">
                <i class="bi bi-lightning-fill"></i> Activos
            </a>
            <a class="event-menu-item <?= $class_historial ?>" href="htr_eventos.php" target="contentFrame">
                <i class="bi bi-archive-fill"></i> Historial
            </a>
            <div class="event-menu-divider"></div>
            <a class="event-menu-item" href="crear_evento.php" target="contentFrame">
                <i class="bi bi-plus-circle-fill"></i> Nuevo Evento
            </a>
        </nav>
    </aside>

    <main class="event-content">
        <iframe class="event-frame" name="contentFrame" id="contentFrame" src="<?php echo $src_inicial; ?>"></iframe>
    </main>

    <script>
        const iframe = document.getElementById('contentFrame');
        const menuItems = document.querySelectorAll('.event-menu-item');

        menuItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                menuItems.forEach(m => m.classList.remove('active'));
                this.classList.add('active');
                iframe.src = this.getAttribute('href');
            });
        });

        iframe.onload = function() {
            try {
                const path = iframe.contentWindow.location.pathname;
                const filename = path.substring(path.lastIndexOf('/') + 1);
                const search = iframe.contentWindow.location.search;

                menuItems.forEach(link => {
                    link.classList.remove('active');
                    const href = link.getAttribute('href');
                    
                    if (href === filename) {
                        link.classList.add('active');
                    } else if (filename.includes('editar_evento.php')) {
                        if (search.includes('modo_reactivacion=1') && href === 'htr_eventos.php') {
                            link.classList.add('active');
                        } else if (!search.includes('modo_reactivacion=1') && href === 'act_evento.php') {
                            link.classList.add('active');
                        }
                    }
                });
            } catch (e) {}
        };
    </script>
    <script src="../vnt_interfaz/js/teatro-sync.js"></script>
</body>
</html>