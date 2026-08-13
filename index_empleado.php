<?php
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

if (($_SESSION['usuario_rol'] ?? '') === 'admin') {
    header("Location: index.php");
    exit();
}

$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$usuario_apellido = $_SESSION['usuario_apellido'] ?? '';
$nombre_completo = $usuario_nombre . ' ' . $usuario_apellido;

include "conexion.php";
require_once __DIR__ . '/config/ventas.php';
$horasVentaAbierta = (int) HORAS_CIERRE_VENTAS_POST_FUNCION;

// El evento cierra las ventas 24 horas después de su última función.
// Comparación en SQL con NOW() (misma zona horaria que los datos).
$query = "
    SELECT 
        e.id_evento, e.titulo, e.descripcion, e.imagen, e.tipo,
        (SELECT MIN(f.fecha_hora) FROM funciones f 
         WHERE f.id_evento = e.id_evento AND f.fecha_hora > (NOW() - INTERVAL {$horasVentaAbierta} HOUR) AND f.estado = 0) AS proxima_funcion
    FROM evento e WHERE e.finalizado = 0 HAVING proxima_funcion IS NOT NULL ORDER BY proxima_funcion ASC
";
$resultado = $conn->query($query);
$eventos = [];
if ($resultado) {
    while ($row = $resultado->fetch_assoc()) {
        $eventos[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta - Teatro</title>
    <link rel="icon" href="crt_interfaz/imagenes_teatro/nat.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/teatro-style.css">
    <style>
        body {
            background: var(--bg-primary);
            min-height: 100vh;
        }

        .header {
            background: var(--bg-secondary);
            padding: 16px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .header-logo {
            width: 42px;
            height: 42px;
            background: var(--gradient-primary);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: white;
        }

        .header-title h1 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .header-title span {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            background: var(--bg-tertiary);
            border-radius: var(--radius-full);
            border: 1px solid var(--border-color);
        }

        .user-badge-avatar {
            width: 32px;
            height: 32px;
            background: var(--accent-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
        }

        .user-badge-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .btn-logout {
            padding: 10px 18px;
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid rgba(255, 69, 58, 0.3);
            border-radius: var(--radius-md);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: var(--transition-fast);
        }

        .btn-logout:hover {
            background: var(--danger);
            color: white;
        }

        .main-content {
            padding: 32px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .section-header {
            margin-bottom: 28px;
        }

        .section-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 6px;
        }

        .section-title i {
            color: var(--accent-blue);
        }

        .section-subtitle {
            color: var(--text-muted);
            font-size: 1rem;
        }

        .eventos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }

        .evento-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-xl);
            overflow: hidden;
            border: 1px solid var(--border-color);
            transition: var(--transition-normal);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .evento-card:hover {
            border-color: var(--accent-blue);
            transform: translateY(-6px);
            box-shadow: var(--shadow-glow);
        }

        .evento-card:hover .evento-imagen img {
            transform: scale(1.05);
        }

        .evento-imagen {
            width: 100%;
            height: 280px;
            overflow: hidden;
            position: relative;
            background: var(--bg-tertiary);
        }

        .evento-imagen img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .evento-badge {
            position: absolute;
            top: 14px;
            right: 14px;
            background: var(--gradient-primary);
            padding: 6px 14px;
            border-radius: var(--radius-full);
            font-size: 0.8rem;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .evento-info {
            padding: 20px;
        }

        .evento-titulo {
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .evento-fecha {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--accent-blue);
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .evento-descripcion {
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .btn-vender {
            margin-top: 16px;
            width: 100%;
            padding: 14px;
            background: var(--accent-blue);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: var(--transition-fast);
        }

        .evento-card:hover .btn-vender {
            background: var(--accent-blue-hover);
        }

        .empty-state {
            text-align: center;
            padding: 60px 32px;
            background: var(--bg-secondary);
            border-radius: var(--radius-xl);
            border: 1px dashed var(--border-color);
        }

        .empty-state-icon {
            width: 80px;
            height: 80px;
            background: var(--bg-tertiary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .empty-state-icon i {
            font-size: 2.5rem;
            color: var(--text-muted);
        }

        .empty-state h3 {
            font-size: 1.25rem;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .empty-state p {
            color: var(--text-muted);
        }

        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 14px; padding: 14px; }
            .main-content { padding: 20px 14px; }
            .eventos-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <div class="header-logo"><i class="bi bi-ticket-perforated-fill"></i></div>
            <div class="header-title">
                <h1>Punto de Venta</h1>
                <span>Sistema de Teatro</span>
            </div>
        </div>
        <div class="header-right">
            <button class="btn-logout" onclick="abrirVisorCliente()" style="background: var(--bg-tertiary); color: var(--text-primary); border-color: var(--border-color); margin-right: 10px;">
                <i class="bi bi-display"></i> Pantalla Cliente
            </button>
            <div class="user-badge">
                <div class="user-badge-avatar"><i class="bi bi-person-fill"></i></div>
                <div class="user-badge-name"><?php echo htmlspecialchars($nombre_completo); ?></div>
            </div>
            <button class="btn-logout" onclick="location.href='logout.php'">
                <i class="bi bi-box-arrow-right"></i> Salir
            </button>
        </div>
    </header>

    <script>
    function abrirVisorCliente() {
        window.open('vnt_interfaz/visor_cliente.php', 'VisorCliente', 'width=1200,height=800,menubar=no,toolbar=no');
    }

    (function() {
        const INACTIVITY_LIMIT = 5 * 60 * 1000; // 5 minutos
        let inactivityTimer;

        function resetInactividad() {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(function() {
                window.location.href = 'logout.php?motivo=inactividad';
            }, INACTIVITY_LIMIT);
        }

        // Atajo Alt+F5 para regresar rápido a Punto de Venta
        document.addEventListener('keydown', function(e) {
            if (e.altKey && e.key === 'F5') {
                e.preventDefault();
                window.location.href = 'index_empleado.php';
            }
        });

        // Reiniciar temporizador en eventos de interacción
        ['click', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach(function(evt) {
            document.addEventListener(evt, resetInactividad, { passive: true });
        });

        resetInactividad();
    })();
    </script>


    <main class="main-content">
        <div class="section-header">
            <h2 class="section-title"><i class="bi bi-film"></i> Eventos Disponibles</h2>
            <p class="section-subtitle">Selecciona un evento para vender boletos</p>
        </div>

        <?php if (empty($eventos)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-calendar-x"></i></div>
            <h3>Sin eventos disponibles</h3>
            <p>No hay funciones programadas para venta</p>
        </div>
        <?php else: ?>
        <div class="eventos-grid">
            <?php foreach ($eventos as $evento): 
                $fecha = new DateTime($evento['proxima_funcion']);
                $dias = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
                $fecha_texto = $dias[(int)$fecha->format('w')] . ' ' . $fecha->format('d') . ' - ' . $fecha->format('g:i A');
                
                $hoy = new DateTime();
                $es_hoy = $fecha->format('Y-m-d') === $hoy->format('Y-m-d');
                $manana = (new DateTime())->modify('+1 day')->format('Y-m-d');
                $badge = $es_hoy ? 'HOY' : ($fecha->format('Y-m-d') === $manana ? 'MAÑANA' : '');
                
                $imagen_src = !empty($evento['imagen']) ? 'evt_interfaz/' . $evento['imagen'] : 'evt_interfaz/imagenes/default.jpg';
            ?>
            <a href="vnt_interfaz/index.php?id_evento=<?php echo $evento['id_evento']; ?>" class="evento-card">
                <div class="evento-imagen">
                    <img src="<?php echo htmlspecialchars($imagen_src); ?>" 
                         alt="<?php echo htmlspecialchars($evento['titulo']); ?>"
                         onerror="this.src='evt_interfaz/imagenes/default.jpg'">
                    <?php if ($badge): ?>
                    <div class="evento-badge"><i class="bi bi-lightning-fill"></i> <?php echo $badge; ?></div>
                    <?php endif; ?>
                </div>
                <div class="evento-info">
                    <h3 class="evento-titulo"><?php echo htmlspecialchars($evento['titulo']); ?></h3>
                    <div class="evento-fecha"><i class="bi bi-calendar-event"></i> <?php echo $fecha_texto; ?></div>
                    <?php if (!empty($evento['descripcion'])): ?>
                    <p class="evento-descripcion"><?php echo htmlspecialchars(substr($evento['descripcion'], 0, 80)); ?>...</p>
                    <?php endif; ?>
                    <div class="btn-vender"><i class="bi bi-cart-plus-fill"></i> Vender Boletos</div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
    <script>
        window.addEventListener('pageshow', function(e) {
            if (e.persisted) window.location.reload();
        });
    </script>
</body>
</html>
