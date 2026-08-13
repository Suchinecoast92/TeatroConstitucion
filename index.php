<?php
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Panel admin solo para administradores; empleados van a su pantalla de venta
if (($_SESSION['usuario_rol'] ?? '') !== 'admin') {
    header('Location: index_empleado.php');
    exit();
}

$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$usuario_apellido = $_SESSION['usuario_apellido'] ?? '';
$usuario_rol = $_SESSION['usuario_rol'] ?? 'empleado';
$nombre_completo = $usuario_nombre . ' ' . $usuario_apellido;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teatro - Panel de Control</title>
    <link rel="icon" href="crt_interfaz/imagenes_teatro/nat.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/teatro-style.css">
    <style>
        :root {
            --sidebar-width: 260px;
            --sidebar-collapsed: 72px;
        }

        body {
            background: var(--bg-primary);
            overflow: hidden;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--bg-secondary);
            position: fixed;
            left: 0;
            top: 0;
            z-index: 100;
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--border-color);
            transition: width var(--transition-normal);
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed);
        }

        /* Logo */
        .sidebar-brand {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            border-bottom: 1px solid var(--border-color);
        }

        .sidebar-brand-icon {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .sidebar-brand-text {
            font-weight: 700;
            font-size: 1rem;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar-brand-text span {
            display: block;
            font-size: 0.7rem;
            font-weight: 400;
            color: var(--text-muted);
        }

        .sidebar.collapsed .sidebar-brand-text {
            display: none;
        }

        /* User */
        .sidebar-user {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-user-avatar {
            width: 38px;
            height: 38px;
            background: var(--bg-tertiary);
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-blue);
            font-size: 1rem;
            flex-shrink: 0;
        }

        .sidebar-user-info {
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar-user-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
            text-overflow: ellipsis;
            overflow: hidden;
        }

        .sidebar-user-role {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .sidebar.collapsed .sidebar-user-info {
            display: none;
        }

        /* Menu */
        .sidebar-menu {
            flex: 1;
            padding: 12px 8px;
            overflow-y: auto;
        }

        .sidebar-menu-item {
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

        .sidebar-menu-item:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .sidebar-menu-item.active {
            background: rgba(21, 97, 240, 0.15);
            color: var(--accent-blue);
        }

        .sidebar-menu-item i {
            font-size: 1.15rem;
            width: 22px;
            text-align: center;
            flex-shrink: 0;
        }

        .sidebar-menu-item span {
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar.collapsed .sidebar-menu-item {
            justify-content: center;
            padding: 12px;
        }

        .sidebar.collapsed .sidebar-menu-item span {
            display: none;
        }

        /* Footer */
        .sidebar-footer {
            padding: 12px;
            border-top: 1px solid var(--border-color);
        }

        .sidebar-toggle {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px;
            border: none;
            background: var(--bg-tertiary);
            border-radius: var(--radius-md);
            color: var(--text-muted);
            cursor: pointer;
            transition: var(--transition-fast);
            font-size: 0.85rem;
        }

        .sidebar-toggle:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }

        .sidebar-toggle i {
            transition: transform var(--transition-normal);
        }

        .sidebar.collapsed .sidebar-toggle i {
            transform: rotate(180deg);
        }

        .sidebar.collapsed .sidebar-toggle span {
            display: none;
        }

        .btn-logout {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px;
            border: none;
            background: var(--danger-bg);
            border-radius: var(--radius-md);
            color: var(--danger);
            cursor: pointer;
            transition: var(--transition-fast);
            margin-top: 8px;
            font-weight: 500;
            font-size: 0.85rem;
        }

        .btn-logout:hover {
            background: var(--danger);
            color: white;
        }

        .sidebar.collapsed .btn-logout span {
            display: none;
        }

        /* Content */
        .content-area {
            margin-left: var(--sidebar-width);
            height: 100vh;
            transition: margin-left var(--transition-normal);
        }

        body.sidebar-collapsed .content-area {
            margin-left: var(--sidebar-collapsed);
        }

        .content-frame {
            width: 100%;
            height: 100%;
            border: none;
            display: none;
            background: var(--bg-primary);
        }

        .content-frame.active {
            display: block;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: var(--bg-secondary);
            border-radius: var(--radius-xl);
            width: 90%;
            max-width: 400px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-xl);
            animation: modalIn 0.25s ease;
        }
            }

            .sidebar-user-info {
                min-width: 0;
                white-space: nowrap;
                overflow: hidden;
            }

            .sidebar-user-name {
                font-weight: 600;
                color: var(--text-primary);
                font-size: 0.9rem;
                text-overflow: ellipsis;
                overflow: hidden;
            }

            .sidebar-user-role {
                font-size: 0.75rem;
                color: var(--text-muted);
            }

            .sidebar.collapsed .sidebar-user-info {
                display: none;
            }

            /* Menu */
            .sidebar-menu {
                flex: 1;
                padding: 12px 8px;
                overflow-y: auto;
            }

            .sidebar-menu-item {
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

            .sidebar-menu-item:hover {
                background: var(--bg-tertiary);
                color: var(--text-primary);
            }

            .sidebar-menu-item.active {
                background: rgba(21, 97, 240, 0.15);
                color: var(--accent-blue);
            }

            .sidebar-menu-item i {
                font-size: 1.15rem;
                width: 22px;
                text-align: center;
                flex-shrink: 0;
            }

            .sidebar-menu-item span {
                white-space: nowrap;
                overflow: hidden;
            }

            .sidebar.collapsed .sidebar-menu-item {
                justify-content: center;
                padding: 12px;
            }

            .sidebar.collapsed .sidebar-menu-item span {
                display: none;
            }

            /* Footer */
            .sidebar-footer {
                padding: 12px;
                border-top: 1px solid var(--border-color);
            }

            .sidebar-toggle {
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 10px;
                border: none;
                background: var(--bg-tertiary);
                border-radius: var(--radius-md);
                color: var(--text-muted);
                cursor: pointer;
                transition: var(--transition-fast);
                font-size: 0.85rem;
            }

            .sidebar-toggle:hover {
                background: var(--bg-hover);
                color: var(--text-primary);
            }

            .sidebar-toggle i {
                transition: transform var(--transition-normal);
            }

            .sidebar.collapsed .sidebar-toggle i {
                transform: rotate(180deg);
            }

            .sidebar.collapsed .sidebar-toggle span {
                display: none;
            }

            .btn-logout {
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 10px;
                border: none;
                background: var(--danger-bg);
                border-radius: var(--radius-md);
                color: var(--danger);
                cursor: pointer;
                transition: var(--transition-fast);
                margin-top: 8px;
                font-weight: 500;
                font-size: 0.85rem;
            }

            .btn-logout:hover {
                background: var(--danger);
                color: white;
            }

            .sidebar.collapsed .btn-logout span {
                display: none;
            }

            /* Content */
            .content-area {
                margin-left: var(--sidebar-width);
                height: 100vh;
                transition: margin-left var(--transition-normal);
            }

            body.sidebar-collapsed .content-area {
                margin-left: var(--sidebar-collapsed);
            }

            .content-frame {
                width: 100%;
                height: 100%;
                border: none;
                display: none;
                background: var(--bg-primary);
            }

            .content-frame.active {
                display: block;
            }

            /* Modal */
            .modal-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.85);
                backdrop-filter: blur(8px);
                z-index: 1000;
                align-items: center;
                justify-content: center;
            }

            .modal-overlay.active {
                display: flex;
            }

            .modal-box {
                background: var(--bg-secondary);
                border-radius: var(--radius-xl);
                width: 90%;
                max-width: 400px;
                border: 1px solid var(--border-color);
                box-shadow: var(--shadow-xl);
                animation: modalIn 0.25s ease;
            }

            @keyframes modalIn {
                from {
                    opacity: 0;
                    transform: scale(0.96) translateY(-16px);
                }

                to {
                    opacity: 1;
                    transform: scale(1) translateY(0);
                }
            }

            .modal-header {
                padding: 20px 24px;
                border-bottom: 1px solid var(--border-color);
            }

            .modal-header h3 {
                font-size: 1.1rem;
                font-weight: 600;
                color: var(--text-primary);
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .modal-header h3 i {
                color: var(--accent-blue);
            }

            .modal-body {
                padding: 24px;
                text-align: center;
            }

            .modal-icon {
                font-size: 3rem;
                color: var(--accent-blue);
                margin-bottom: 16px;
            }

            .modal-text {
                color: var(--text-muted);
                margin-bottom: 20px;
                font-size: 0.95rem;
            }

            .modal-input {
                width: 100%;
                padding: 14px;
                font-size: 1.3rem;
                text-align: center;
                letter-spacing: 8px;
                border: 1px solid var(--border-color);
                border-radius: var(--radius-md);
                background: var(--bg-tertiary);
                color: var(--text-primary);
                transition: var(--transition-fast);
            }

            .modal-input:focus {
                outline: none;
                border-color: var(--accent-blue);
                box-shadow: 0 0 0 3px rgba(21, 97, 240, 0.2);
            }

            .modal-error {
                color: var(--danger);
                font-size: 0.85rem;
                margin-top: 12px;
                display: none;
            }

            .modal-footer {
                padding: 16px 24px;
                border-top: 1px solid var(--border-color);
                display: flex;
                gap: 10px;
            }

            .modal-footer button {
                flex: 1;
                padding: 12px;
                border: none;
                border-radius: var(--radius-md);
                font-weight: 600;
                cursor: pointer;
                transition: var(--transition-fast);
                font-size: 0.9rem;
            }

            .btn-cancel {
                background: var(--bg-tertiary);
                color: var(--text-secondary);
            }

            .btn-cancel:hover {
                background: var(--bg-hover);
            }

            .btn-confirm {
                background: var(--accent-blue);
                color: white;
            }

            .btn-confirm:hover {
                background: var(--accent-blue-hover);
            }
        </style>
    </head>

    <body>

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-icon">
                    <img src="crt_interfaz/imagenes_teatro/nat.png" alt="Teatro" style="width: 70%; height: 70%; object-fit: contain; display: block;">
                </div>
                <div class="sidebar-brand-text">
                    Teatro<span>Sistema de Gestión</span>
                </div>
            </div>

            <div class="sidebar-user">
                <div class="sidebar-user-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?php echo htmlspecialchars($nombre_completo); ?></div>
                    <div class="sidebar-user-role"><?php echo $usuario_rol === 'admin' ? 'Administrador' : 'Empleado'; ?>
                    </div>
                </div>
            </div>

            <nav class="sidebar-menu">
                <a class="sidebar-menu-item active" data-target="frame-venta">
                    <i class="bi bi-cart-fill"></i>
                    <span>Punto de Venta</span>
                </a>
                <a class="sidebar-menu-item" data-target="frame-evento">
                    <i class="bi bi-calendar-event-fill"></i>
                    <span>Eventos</span>
                </a>
                <a class="sidebar-menu-item" data-target="frame-cartelera">
                    <i class="bi bi-film"></i>
                    <span>Cartelera</span>
                </a>
                <a class="sidebar-menu-item" data-target="frame-transacciones">
                    <i class="bi bi-clock-history"></i>
                    <span>Transacciones</span>
                </a>
                <a class="sidebar-menu-item" data-target="frame-ajustes">
                    <i class="bi bi-sliders"></i>
                    <span>Ajustes</span>
                </a>

                <?php if ($usuario_rol === 'admin'): ?>
                    <a class="sidebar-menu-item" data-target="frame-registro">
                        <i class="bi bi-person-plus-fill"></i>
                        <span>Usuarios</span>
                    </a>
                    <?php /* Pestaña Ventas oculta temporalmente (se habilitará más adelante)
                    <a class="sidebar-menu-item" data-target="frame-admin-panel">
                        <i class="bi bi-clipboard-data"></i>
                        <span>Ventas</span>
                    </a>
                    */ ?>
                <?php endif; ?>
            </nav>

            <div class="sidebar-footer">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="bi bi-chevron-left"></i>
                    <span>Minimizar</span>
                </button>
                <button class="btn-logout" onclick="abrirVisorCliente()" style="background: var(--bg-tertiary); color: var(--text-primary); margin-top: 8px;">
                    <i class="bi bi-display"></i>
                    <span>Pantalla Cliente</span>
                </button>
                <button class="btn-logout" onclick="location.href='logout.php'">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Cerrar Sesión</span>
                </button>
            </div>
        </aside>

        <script>
            function abrirVisorCliente() {
                window.open('vnt_interfaz/visor_cliente.php', 'VisorCliente', 'width=1200,height=800,menubar=no,toolbar=no');
            }
        </script>

        <main class="content-area" id="contentArea">
            <iframe id="frame-venta" src="vnt_interfaz/index.php" class="content-frame active"></iframe>
            <iframe id="frame-evento" src="evt_interfaz/index.php" class="content-frame"></iframe>
            <iframe id="frame-cartelera" src="crt_interfaz/index.php" class="content-frame"></iframe>
            <iframe id="frame-mapa" src="mp_interfaz/index.php" class="content-frame"></iframe>
            <iframe id="frame-transacciones" src="admin_interfaz/transacciones/index.php" class="content-frame"></iframe>
            <iframe id="frame-ajustes" src="admin_interfaz/Ajs_interfaz/index.php" class="content-frame"></iframe>

            <?php if ($usuario_rol === 'admin'): ?>
                <iframe id="frame-registro" src="auth/registrar_empleado.php" class="content-frame"></iframe>
                <?php /* Iframe de Ventas oculto temporalmente (se habilitará más adelante)
                <iframe id="frame-admin-panel" src="ventas/panel_admin.php" class="content-frame"></iframe>
                */ ?>
            <?php endif; ?>
        </main>

<!-- Modal -->
<div class="modal-overlay" id="adminModal">
<div class="modal-box">
<div class="modal-header">
<h3><i class="bi bi-shield-lock-fill"></i> Verificación</h3>
</div>
<div class="modal-body">
<i class="bi bi-key-fill modal-icon"></i>
<p class="modal-text">Ingresa la contraseña de administrador</p>
<input type="password" id="adminPassword" class="modal-input" placeholder="••••••" maxlength="20" autocomplete="off">
<div class="modal-error" id="modalError">
<i class="bi bi-exclamation-triangle"></i> Contraseña incorrecta
</div>
</div>
<div class="modal-footer">
<button class="btn-cancel" onclick="cancelarVerificacion()">Cancelar</button>
<button class="btn-confirm" onclick="verificarAdmin()">Verificar</button>
</div>
</div>
</div>

<div class="modal-overlay" id="inactivityModal">
<div class="modal-box">
<div class="modal-header">
<h3><i class="bi bi-exclamation-triangle-fill"></i> Sesión inactiva</h3>
</div>
<div class="modal-body">
<p class="modal-text">Por inactividad, la sesión se cerrará en <span id="inactivityCountdown">30</span> segundos.</p>
</div>
<div class="modal-footer">
<button class="btn-confirm" id="inactivityStayButton">Continuar en el sistema</button>
</div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const menuItems = document.querySelectorAll('.sidebar-menu-item');
const iframes = document.querySelectorAll('.content-frame');
const TAB_KEY = 'teatro_tab';
const SIDEBAR_KEY = 'teatro_sidebar';
const INACTIVITY_LIMIT = 5 * 60 * 1000; // 5 minutos
const WARNING_TIME = 30 * 1000;
let inactivityTimer;
let warningTimer;
let countdownInterval;

function cambiarPestana(targetId) {
iframes.forEach(f => f.classList.remove('active'));
menuItems.forEach(m => m.classList.remove('active'));

const frame = document.getElementById(targetId);
const menuItem = document.querySelector(`[data-target="${targetId}"]`);

if (frame) {
frame.classList.add('active');
if (menuItem) menuItem.classList.add('active');
localStorage.setItem(TAB_KEY, targetId);
return true;
}
return false;
}

function toggleSidebar() {
sidebar.classList.toggle('collapsed');
document.body.classList.toggle('sidebar-collapsed');
const collapsed = sidebar.classList.contains('collapsed');
localStorage.setItem(SIDEBAR_KEY, collapsed ? 'c' : 'e');
sidebarToggle.querySelector('span').textContent = collapsed ? '' : 'Minimizar';
}

function clearInactividadAviso() {
if (countdownInterval) {
clearInterval(countdownInterval);
countdownInterval = null;
}
const modal = document.getElementById('inactivityModal');
if (modal) {
modal.classList.remove('active');
}
}

function mostrarInactividadAviso() {
const modal = document.getElementById('inactivityModal');
const countdownSpan = document.getElementById('inactivityCountdown');
const stayButton = document.getElementById('inactivityStayButton');

if (!modal || !countdownSpan || !stayButton) {
return;
}

let remaining = WARNING_TIME / 1000;
countdownSpan.textContent = remaining;
modal.classList.add('active');

if (countdownInterval) {
clearInterval(countdownInterval);
}

countdownInterval = setInterval(() => {
remaining -= 1;
if (remaining <= 0) {
clearInterval(countdownInterval);
countdownInterval = null;
window.location.href = 'logout.php?motivo=inactividad';
return;
}
countdownSpan.textContent = remaining;
}, 1000);

stayButton.onclick = () => {
clearInactividadAviso();
resetInactividad();
};
}

function resetInactividad() {
clearTimeout(inactivityTimer);
clearTimeout(warningTimer);
clearInactividadAviso();

warningTimer = setTimeout(() => {
mostrarInactividadAviso();
}, INACTIVITY_LIMIT - WARNING_TIME);

inactivityTimer = setTimeout(() => {
window.location.href = 'logout.php?motivo=inactividad';
}, INACTIVITY_LIMIT);
}

// Restaurar estado del sidebar
if (localStorage.getItem(SIDEBAR_KEY) === 'c') {
sidebar.classList.add('collapsed');
document.body.classList.add('sidebar-collapsed');
}

// Restaurar pestaña activa
const savedTab = localStorage.getItem(TAB_KEY);
if (savedTab) {
cambiarPestana(savedTab);
}

menuItems.forEach(item => {
item.addEventListener('click', () => cambiarPestana(item.dataset.target));
});

sidebarToggle.addEventListener('click', toggleSidebar);
document.getElementById('adminPassword').addEventListener('keypress', e => {
if (e.key === 'Enter') verificarAdmin();
});

// Atajo Alt+F5 para ir a Punto de Venta
document.addEventListener('keydown', e => {
if (e.altKey && e.key === 'F5') {
e.preventDefault();
cambiarPestana('frame-venta');
}
});

// Inactividad: reiniciar temporizador en eventos de interacción
['click', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
document.addEventListener(evt, resetInactividad, { passive: true });
});

// Mensajes desde iframes (por ejemplo, Punto de Venta) para ir a Punto de Venta
window.addEventListener('message', event => {
if (event && event.data && event.data.tipo === 'ir_punto_venta') {
cambiarPestana('frame-venta');
}
});

resetInactividad();
});

function mostrarModalAdmin() {
document.getElementById('adminModal').classList.add('active');
document.getElementById('adminPassword').focus();
document.getElementById('modalError').style.display = 'none';
}

function cancelarVerificacion() {
document.getElementById('adminModal').classList.remove('active');
document.getElementById('adminPassword').value = '';
}

async function verificarAdmin() {
const password = document.getElementById('adminPassword').value;
const errorDiv = document.getElementById('modalError');

if (!password) {
errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Ingresa la contraseña';
errorDiv.style.display = 'block';
return;
}

try {
const response = await fetch('auth/verificar_admin.php', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ password })
});

const data = await response.json();

if (data.success) {
cancelarVerificacion();
location.reload();
} else {
errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle"></i> ' + (data.message || 'Incorrecta');
errorDiv.style.display = 'block';
document.getElementById('adminPassword').value = '';
document.getElementById('adminPassword').focus();
}
} catch (e) {
errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Error de conexión';
errorDiv.style.display = 'block';
}
}
</script>

<script>
window.addEventListener('pageshow', function(e) {
    if (e.persisted) window.location.reload();
});
</script>

</body>

</html>