<?php
// CONEXIÓN A LA BD (UTF-8)
header('Content-Type: text/html; charset=UTF-8');
include "../conexion.php";

// OBTENER EVENTOS ACTIVOS CON SUS PRÓXIMAS FUNCIONES
$query = "
    SELECT 
        e.id_evento,
        e.titulo,
        e.descripcion,
        e.imagen,
        e.mapa_json,
        f.id_funcion,
        f.fecha_hora
    FROM evento e
    INNER JOIN funciones f ON e.id_evento = f.id_evento
    WHERE e.finalizado = 0 
      AND f.fecha_hora >= NOW()
    ORDER BY f.fecha_hora ASC
";

$resultado = $conn->query($query);

// Obtener boletos vendidos agrupados por evento/función para detectar funciones agotadas
$vendidos_por_funcion = [];
$sql_vendidos = "
    SELECT id_evento, id_funcion, COUNT(*) AS vendidos
    FROM boletos
    WHERE estatus = 1
    GROUP BY id_evento, id_funcion
";

$res_vendidos = $conn->query($sql_vendidos);
if ($res_vendidos) {
    while ($row_v = $res_vendidos->fetch_assoc()) {
        $id_ev = (int)$row_v['id_evento'];
        $id_fun = (int)$row_v['id_funcion'];
        $vendidos_por_funcion[$id_ev][$id_fun] = (int)$row_v['vendidos'];
    }
}

// Agrupar funciones por evento
$eventos = [];
if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $id_evento = (int)$row['id_evento'];
        $id_funcion = (int)$row['id_funcion'];
        
        // Si el evento no existe en el array, lo creamos
        if (!isset($eventos[$id_evento])) {
            // Calcular total de asientos del mapa a partir de mapa_json
            $total_asientos = 0;
            if (!empty($row['mapa_json'])) {
                $mapa_guardado = json_decode($row['mapa_json'], true);
                if (is_array($mapa_guardado)) {
                    $total_asientos = count($mapa_guardado);
                }
            }

            $eventos[$id_evento] = [
                'id_evento' => $id_evento,
                'titulo' => $row['titulo'],
                'descripcion' => $row['descripcion'],
                'imagen' => $row['imagen'],
                'total_asientos' => $total_asientos,
                'funciones' => []
            ];
        }
        
        // Agregamos la función al evento
        $total_asientos_evento = $eventos[$id_evento]['total_asientos'] ?? 0;
        $vendidos = $vendidos_por_funcion[$id_evento][$id_funcion] ?? 0;
        $agotado = ($total_asientos_evento > 0 && $vendidos >= $total_asientos_evento);
        $disponibles = max(0, $total_asientos_evento - $vendidos);

        $eventos[$id_evento]['funciones'][] = [
            'id_funcion' => $id_funcion,
            'fecha_hora' => $row['fecha_hora'],
            'agotado' => $agotado,
            'vendidos' => $vendidos,
            'disponibles' => $disponibles,
            'total_asientos' => $total_asientos_evento
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartelera Completa · Teatro Constitución</title>
    <link rel="icon" href="imagenes_teatro/nat.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        body {
            background-image: url('imagenes_teatro/TeatroNoche1.jpg');
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
            font-height: 1.55rem;
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
        .theme-toggle-btn .theme-icon { font-size: 1.1rem; }
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
        }
        @media (max-width: 900px) {
            .nav {
                position: fixed;
                inset: 64px 0 0 0;
                background: rgba(10,10,12,.98);
                flex-direction: column;
                padding: 24px;
                gap: 12px;
                transform: translateY(-120%);
                transition: transform .25s ease;
            }
            .nav.open { transform: translateY(0); }
            .hamburger { display: inline-flex; }
        }

        /* Contenido principal */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 60px 20px;
        }

        .hero-section {
            text-align: center;
            margin-bottom: 50px;
            animation: fadeInUp 0.8s ease;
        }

        .hero-section h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #ffffff;
            text-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
            letter-spacing: -0.03em;
        }

        .hero-section p {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
            max-width: 700px;
            margin: 0 auto;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        /* Lista de eventos */
        .eventos-lista {
            display: flex;
            flex-direction: column;
            gap: 30px;
            margin-top: 40px;
        }

        .evento-item {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.08));
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.2),
                inset 0 1px 1px rgba(255, 255, 255, 0.5),
                inset 0 -1px 1px rgba(0, 0, 0, 0.05);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            animation: fadeInUp 0.6s ease;
        }

        .evento-item:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 16px 48px rgba(0, 0, 0, 0.3),
                inset 0 1px 1px rgba(255, 255, 255, 0.6),
                inset 0 -1px 1px rgba(0, 0, 0, 0.05);
            border-color: rgba(255, 255, 255, 0.35);
        }

        .evento-content {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
            padding: 30px;
        }

        .evento-imagen {
            width: 100%;
            height: 400px;
            border-radius: 12px;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.02));
        }

        .evento-imagen img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .evento-item:hover .evento-imagen img {
            transform: scale(1.05);
        }

        .evento-info {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .evento-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 10px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            letter-spacing: -0.02em;
        }

        .evento-descripcion {
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.6;
            font-size: 0.95rem;
            margin-bottom: 10px;
            overflow-wrap: break-word;
            word-wrap: break-word;
            word-break: break-word;
        }

        .funciones-titulo {
            font-size: 1.3rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 15px;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .funciones-lista {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .funcion-item {
            background: rgba(0, 0, 0, 0.3);
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 4px solid #9ca3af;
            transition: all 0.3s ease;
        }

        .funcion-item:hover {
            background: rgba(0, 0, 0, 0.4);
            border-left-color: #6b7280;
            transform: translateX(5px);
        }

        .funcion-item.funcion-agotada {
            background: rgba(15, 23, 42, 0.85);
            border-left-color: #9ca3af;
            opacity: 0.9;
        }

        .funcion-fecha {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ffffff;
            font-weight: 600;
        }

        .funcion-fecha i {
            color: #e5e7eb;
            font-size: 1.2rem;
        }

        .funcion-acciones {
            margin-top: 10px;
            text-align: right;
        }

        .funcion-acciones .btn {
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 999px;
            border-width: 1px;
        }

        .boletos-disponibles-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
            background: rgba(52, 211, 153, 0.2);
            color: #34d399;
            border: 1px solid rgba(52, 211, 153, 0.5);
            margin-right: 10px;
        }
        .boletos-disponibles-badge.pocos {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
            border-color: rgba(251, 191, 36, 0.5);
        }
        .boletos-disponibles-badge i {
            font-size: 0.95rem;
        }
        .badge-agotado {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #6b7280, #374151);
            color: #fff;
            border: 1px solid rgba(156, 163, 175, 0.8);
            box-shadow: 0 4px 10px rgba(0,0,0,0.4);
        }

        .no-eventos {
            text-align: center;
            padding: 80px 20px;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(15, 23, 42, 0.8));
            backdrop-filter: blur(25px) saturate(180%);
            border-radius: 20px;
            border: 1px solid rgba(148, 163, 184, 0.4);
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.7);
        }

        .no-eventos i {
            font-size: 4rem;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 20px;
        }

        .no-eventos h3 {
            color: #ffffff;
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .no-eventos p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
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

        /* Responsive */
        @media (max-width: 992px) {
            .evento-content {
                grid-template-columns: 1fr;
            }
            
            .evento-imagen {
                height: 300px;
            }
        }

        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 2rem;
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

        /* (Tema único) Se mantienen colores neutros/grises, sin cambio de fondo */
        body.theme-light .site-footer {
            background: #0b1120;
            border-top-color: #111827;
        }
        body.theme-light .footer-col h4,
        body.theme-light .footer-links a,
        body.theme-light .social a { color: #e5e7eb; }
        body.theme-light .footer-links a:hover { color: #ffffff; }
        @media (max-width: 900px){
            .footer-inner { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 600px){
            .footer-inner { grid-template-columns: 1fr; }
            .footer-bottom-inner { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

    <header class="site-header">
        <div class="header-inner">
            <a href="index.php" class="brand" aria-label="Inicio">
                <div class="brand-logo"><img src="imagenes_teatro/nat.png" alt="Teatro Constitución" class="logo-img"></div>
                <div class="brand-name">Teatro Constitución · Apatzingan</div>
            </a>
            <nav class="nav" id="mainNav">
                <a href="index.php">Inicio</a>
                <a href="acerca.php">Acerca del teatro</a>
                <a href="contacto.php">Contacto / Reservaciones</a>
                <a href="cartelera_cliente.php" class="cta">Ver cartelera completa <i class="bi bi-arrow-right"></i></a>
            </nav>
            <button class="hamburger" id="hamburgerBtn" aria-label="Menú">
                <i class="bi bi-list" style="font-size:1.25rem"></i>
            </button>
        </div>
    </header>

    <main class="main-content">
        <!-- Hero Section -->
        <section class="hero-section">
            <h1>Cartelera Completa</h1>
            <p>Todos los eventos y funciones próximas del Teatro Constitución</p>
        </section>

        <!-- Lista de Eventos -->
        <div class="eventos-lista">
            <?php if (empty($eventos)): ?>
                <div class="no-eventos">
                    <i class="bi bi-calendar-x"></i>
                    <h3>No hay eventos programados</h3>
                    <p>En este momento no hay funciones disponibles. Vuelve pronto para ver nuestra nueva cartelera.</p>
                </div>
            <?php else: ?>
                <?php foreach ($eventos as $evento): ?>
                    <div class="evento-item">
                        <div class="evento-content">
                            <!-- Imagen del evento -->
                            <div class="evento-imagen">
                                <img src="../evt_interfaz/<?php echo htmlspecialchars($evento['imagen']); ?>" 
                                     alt="<?php echo htmlspecialchars($evento['titulo']); ?>"
                                     onerror="this.src='imagenes_teatro/nat.png'">
                            </div>
                            
                            <!-- Información del evento -->
                            <div class="evento-info">
                                <div class="evento-header">
                                    <h2><?php echo htmlspecialchars($evento['titulo']); ?></h2>
                                    <?php if (!empty($evento['descripcion'])): ?>
                                        <p class="evento-descripcion"><?php echo nl2br(htmlspecialchars($evento['descripcion'])); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Lista de funciones -->
                                <div>
                                    <h3 class="funciones-titulo">
                                        <i class="bi bi-calendar-event"></i>
                                        Próximas Funciones
                                    </h3>
                                    <div class="funciones-lista">
                                        <?php foreach ($evento['funciones'] as $funcion): ?>
                                            <?php
                                            $fecha = new DateTime($funcion['fecha_hora']);
                                            $fechaFormateada = $fecha->format('l, d \d\e F \d\e Y');
                                            $horaFormateada = $fecha->format('h:i A');
                                            
                                            // Traducir días de la semana y meses al español
                                            $dias = ['Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles', 
                                                     'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado', 'Sunday' => 'Domingo'];
                                            $meses = ['January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 
                                                      'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio',
                                                      'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre', 
                                                      'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'];
                                            
                                            $diaIngles = $fecha->format('l');
                                            $mesIngles = $fecha->format('F');
                                            
                                            $fechaEspanol = str_replace(
                                                array_keys($dias),
                                                array_values($dias),
                                                str_replace(
                                                    array_keys($meses),
                                                    array_values($meses),
                                                    $fechaFormateada
                                                )
                                            );
                                            ?>
                                            <div class="funcion-item <?php echo !empty($funcion['agotado']) ? 'funcion-agotada' : ''; ?>">
                                                <div class="funcion-fecha">
                                                    <i class="bi bi-calendar-check"></i>
                                                    <span><?php echo $fechaEspanol; ?> · <?php echo $horaFormateada; ?></span>
                                                </div>
                                                <div class="funcion-acciones">
                                                    <?php if (!empty($funcion['agotado'])): ?>
                                                        <span class="badge-agotado">
                                                            <i class="bi bi-x-octagon-fill"></i> Agotado
                                                        </span>
                                                    <?php else: ?>
                                                        <?php 
                                                        $disponibles = $funcion['disponibles'] ?? 0;
                                                        $clasePocos = ($disponibles <= 10 && $disponibles > 0) ? 'pocos' : '';
                                                        ?>
                                                        <?php if ($funcion['total_asientos'] > 0): ?>
                                                        <span class="boletos-disponibles-badge <?php echo $clasePocos; ?>">
                                                            <i class="bi bi-ticket-perforated"></i>
                                                            <?php echo $disponibles; ?> disponible<?php echo $disponibles !== 1 ? 's' : ''; ?>
                                                        </span>
                                                        <?php endif; ?>
                                                        <a href="disponibles.php?id_evento=<?php echo $evento['id_evento']; ?>&id_funcion=<?php echo $funcion['id_funcion']; ?>" class="btn btn-outline-light btn-sm">
                                                            Ver disponibilidad
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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

        document.addEventListener('DOMContentLoaded', () => {
            if (hamburgerBtn && mainNav) {
                hamburgerBtn.addEventListener('click', () => {
                    mainNav.classList.toggle('open');
                });
            }
        });
    </script>

</body>
</html>
