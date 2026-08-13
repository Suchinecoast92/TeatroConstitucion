<?php
// 1. CONEXIÓN
// Ajustamos la ruta asumiendo que estamos en /crt_interfaz/
include "../conexion.php"; 

// Obtener ID del evento (compatibilidad con ?id y ?id_evento)
$id_evento = 0;
if (isset($_GET['id_evento']) && is_numeric($_GET['id_evento'])) {
    $id_evento = (int)$_GET['id_evento'];
} elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_evento = (int)$_GET['id'];
}

// Obtener ID de la función (opcional, para disponibilidad por función)
$id_funcion = isset($_GET['id_funcion']) && is_numeric($_GET['id_funcion']) ? (int)$_GET['id_funcion'] : 0;

$evento = null;
$mapa_guardado = [];
$colores_por_id = [];
$info_categorias = []; // Para guardar nombre y precio
$asientos_vendidos = [];
$texto_funcion = '';

if ($id_evento > 0) {
    // A. Datos del Evento
    $stmt = $conn->prepare("SELECT titulo, tipo, mapa_json FROM evento WHERE id_evento = ?");
    $stmt->bind_param("i", $id_evento);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        $evento = $row;
        $mapa_guardado = json_decode($row['mapa_json'], true) ?? [];
    }
    $stmt->close();

    // B. Cargar Categorías (Colores y Precios)
    $res_cat = $conn->query("SELECT id_categoria, nombre_categoria, color, precio FROM categorias WHERE id_evento = $id_evento");
    if ($res_cat) {
        while ($c = $res_cat->fetch_assoc()) {
            $colores_por_id[$c['id_categoria']] = $c['color'];
            $info_categorias[$c['id_categoria']] = [
                'nombre' => $c['nombre_categoria'],
                'precio' => $c['precio']
            ];
        }
    }

    // C. Cargar Asientos Vendidos (por evento o por función si aplica)
    $check_column = $conn->query("SHOW COLUMNS FROM boletos LIKE 'id_funcion'");
    $has_id_funcion = ($check_column && $check_column->num_rows > 0);

    if ($has_id_funcion && $id_funcion > 0) {
        $stmt_v = $conn->prepare("
            SELECT a.codigo_asiento 
            FROM boletos b 
            JOIN asientos a ON b.id_asiento = a.id_asiento 
            WHERE b.id_evento = ? AND b.id_funcion = ? AND b.estatus = 1
        ");
        if ($stmt_v) {
            $stmt_v->bind_param("ii", $id_evento, $id_funcion);
        }
    } else {
        $stmt_v = $conn->prepare("
            SELECT a.codigo_asiento 
            FROM boletos b 
            JOIN asientos a ON b.id_asiento = a.id_asiento 
            WHERE b.id_evento = ? AND b.estatus = 1
        ");
        if ($stmt_v) {
            $stmt_v->bind_param("i", $id_evento);
        }
    }

    if (isset($stmt_v) && $stmt_v) {
        $stmt_v->execute();
        $res_ven = $stmt_v->get_result();
        if ($res_ven) {
            while ($v = $res_ven->fetch_assoc()) {
                $asientos_vendidos[] = $v['codigo_asiento'];
            }
        }
        $stmt_v->close();
    }

    // D. Texto descriptivo de la función (si se proporcionó id_funcion)
    if ($id_funcion > 0) {
        $stmt_fun = $conn->prepare("SELECT fecha_hora FROM funciones WHERE id_funcion = ? AND id_evento = ? LIMIT 1");
        if ($stmt_fun) {
            $stmt_fun->bind_param("ii", $id_funcion, $id_evento);
            $stmt_fun->execute();
            $res_fun = $stmt_fun->get_result();
            if ($res_fun && $res_fun->num_rows > 0) {
                $row_fun = $res_fun->fetch_assoc();
                if (!empty($row_fun['fecha_hora'])) {
                    $fecha = new DateTime($row_fun['fecha_hora']);
                    $fechaFormateada = $fecha->format('l, d \d\e F \d\e Y');
                    $horaFormateada = $fecha->format('h:i A');

                    $dias = [
                        'Monday' => 'Lunes',
                        'Tuesday' => 'Martes',
                        'Wednesday' => 'Miércoles',
                        'Thursday' => 'Jueves',
                        'Friday' => 'Viernes',
                        'Saturday' => 'Sábado',
                        'Sunday' => 'Domingo',
                    ];

                    $meses = [
                        'January' => 'Enero',
                        'February' => 'Febrero',
                        'March' => 'Marzo',
                        'April' => 'Abril',
                        'May' => 'Mayo',
                        'June' => 'Junio',
                        'July' => 'Julio',
                        'August' => 'Agosto',
                        'September' => 'Septiembre',
                        'October' => 'Octubre',
                        'November' => 'Noviembre',
                        'December' => 'Diciembre',
                    ];

                    $fechaEspanol = str_replace(
                        array_keys($dias),
                        array_values($dias),
                        str_replace(
                            array_keys($meses),
                            array_values($meses),
                            $fechaFormateada
                        )
                    );

                    $texto_funcion = 'Función seleccionada: ' . $fechaEspanol . ' · ' . $horaFormateada;
                }
            }
            $stmt_fun->close();
        }
    }
}

// Valores por defecto
$id_categoria_general = 0; 
$color_default = '#BDBDBD'; 

// --- FUNCIÓN HELPER PARA RENDERIZAR ASIENTO ---
function renderSeat($codigo, $mapa, $vendidos, $colores, $infos, $id_def, $col_def) {
    $id_cat = $mapa[$codigo] ?? $id_def;
    $color = $colores[$id_cat] ?? $col_def;
    $esta_vendido = in_array($codigo, $vendidos);

    // Datos para tooltip
    $nombre_cat = $infos[$id_cat]['nombre'] ?? 'General';
    $precio = isset($infos[$id_cat]['precio']) ? number_format($infos[$id_cat]['precio'], 2) : '0.00';

    $clase = 'seat';
    $style = "background-color: $color;";
    $title = "$codigo | $nombre_cat | $$precio";

    if ($esta_vendido) {
        $clase .= ' vendido';
        $style = '';
        $title = "$codigo | Ocupado";
    }

    $class_attr = htmlspecialchars($clase, ENT_QUOTES, 'UTF-8');
    $style_attr = $style !== '' ? ' style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '"' : '';
    $title_attr = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $codigo_html = htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8');

    return '<div class="' . $class_attr . '"' . $style_attr . ' title="' . $title_attr . '">' . $codigo_html . '</div>';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Disponibilidad - <?= htmlspecialchars($evento['titulo'] ?? 'Evento') ?></title>
<link rel="icon" href="imagenes_teatro/nat.png" type="image/png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    /* ESTILOS DISPONIBILIDAD CON FONDO Y GLASS EFFECT */
    :root {
        --bg-color: #0b1120;
        --text-color: #e5e7eb;
    }

    body {
        margin: 0;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: var(--text-color);
        background-image: url('imagenes_teatro/TeatroNoche1.jpg');
        background-size: cover;
        background-position: center center;
        background-attachment: fixed;
        background-repeat: no-repeat;
    }

    /* Header Simple */
    .header-simple {
        position: sticky;
        top: 0;
        z-index: 100;
        padding: 12px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(10, 10, 12, 0.9);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
    }
    .header-simple h4 {
        margin: 0;
        font-weight: 700;
        color: #ffffff;
    }
    .header-simple .text-muted {
        color: #e5e7eb !important;
    }

    /* Contenedor Mapa */
    .map-viewport {
        flex: 1;
        width: 100%;
        overflow: auto;
        display: flex;
        justify-content: center;
        padding: 30px 20px 60px;
        background: radial-gradient(circle at top, rgba(15, 23, 42, 0.85), rgba(15, 23, 42, 0.95));
        cursor: grab; /* Indicador de que se puede mover/scrollear */
    }
    .map-viewport:active { cursor: grabbing; }

    .glass-card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.18), rgba(255, 255, 255, 0.08));
        backdrop-filter: blur(20px) saturate(180%);
        -webkit-backdrop-filter: blur(20px) saturate(180%);
        border-radius: 16px;
        border: 1px solid rgba(255, 255, 255, 0.25);
        box-shadow:
            0 8px 32px rgba(0, 0, 0, 0.4),
            inset 0 1px 1px rgba(255, 255, 255, 0.4),
            inset 0 -1px 1px rgba(0, 0, 0, 0.15);
    }

    .map-card {
        display: inline-block;
        padding: 24px 24px 40px;
    }

    .map-content {
        transform-origin: top center;
        transition: transform 0.2s;
        padding-bottom: 100px;
    }

    /* Asientos */
    .seat-row-wrapper { display: flex; align-items: center; justify-content: center; margin-bottom: 8px; }
    .seats-block { display: flex; gap: 6px; }
    .row-label { width: 40px; text-align: center; font-weight: bold; color: #94a3b8; }
    .pasillo { width: 30px; }

    .seat {
        width: 40px; height: 40px; border-radius: 8px; display: flex;
        align-items: center; justify-content: center;
        font-weight: 600; color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.18);
        cursor: default; user-select: none;
    }

    .seat.vendido {
        /* Estilo neutro con alto contraste para asientos ocupados */
        background: repeating-linear-gradient(
            45deg,
            #6b7280,
            #6b7280 10px,
            #4b5563 10px,
            #4b5563 20px
        ) !important;
        color: #ffffff !important;
        box-shadow: 0 0 0 2px rgba(31, 41, 55, 0.9), 0 4px 8px rgba(0,0,0,0.4);
    }

    .screen {
        background: #334155; color: white; padding: 10px; text-align: center;
        border-radius: 8px; margin-bottom: 40px; font-weight: bold; letter-spacing: 2px;
        width: 80%; margin-left: auto; margin-right: auto;
    }

    .pasarela {
        position: absolute; width: 80px; top: 0; left: 50%; transform: translateX(-50%);
        background: #475569; color: white; display: flex; align-items: center; justify-content: center;
        border-radius: 8px; z-index: 5; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .pasarela-text { writing-mode: vertical-rl; letter-spacing: 5px; font-weight: bold; }

    /* Leyenda Flotante */
    .leyenda {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        padding: 10px 20px;
        border-radius: 999px;
        display: flex;
        gap: 20px;
        z-index: 100;
        font-size: 0.9rem;
        color: #e5e7eb;
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(15, 23, 42, 0.95));
        backdrop-filter: blur(18px) saturate(160%);
        -webkit-backdrop-filter: blur(18px) saturate(160%);
        border: 1px solid rgba(148, 163, 184, 0.6);
        box-shadow: 0 12px 35px rgba(15, 23, 42, 0.8);
    }
    .leyenda-item { display: flex; align-items: center; gap: 8px; }
    .dot { width: 12px; height: 12px; border-radius: 50%; }

    /* Tema claro para disponibilidad */
    body.theme-light {
        color: #0f172a;
    }
    body.theme-light .header-simple {
        background: rgba(255, 255, 255, 0.9);
        border-bottom-color: #e5e7eb;
    }
    body.theme-light .header-simple h4,
    body.theme-light .header-simple .text-muted { color: #111827 !important; }
    body.theme-light .map-viewport {
        background: radial-gradient(circle at top, rgba(226, 232, 240, 0.95), rgba(209, 213, 219, 0.95));
    }
    body.theme-light .glass-card {
        background: linear-gradient(135deg, #ffffff, #e5e7eb);
        border-color: #e5e7eb;
    }
    body.theme-light .screen {
        background: #e5e7eb;
        color: #111827;
    }
    body.theme-light .seat.vendido {
        background: repeating-linear-gradient(
            45deg,
            #4b5563,
            #4b5563 10px,
            #374151 10px,
            #374151 20px
        ) !important;
        box-shadow: 0 0 0 2px rgba(31, 41, 55, 0.9), 0 4px 8px rgba(0,0,0,0.4);
    }
    body.theme-light .leyenda {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(229, 231, 235, 0.95));
        border-color: #e5e7eb;
        color: #111827;
        box-shadow: 0 12px 35px rgba(148, 163, 184, 0.6);
    }
</style>
</head>
<body>
<div class="header-simple">
    <div>
        <h4><?= htmlspecialchars($evento['titulo'] ?? 'Evento no encontrado') ?></h4>
        <small class="text-muted">
            <?php if (!empty($texto_funcion)): ?>
                <?= htmlspecialchars($texto_funcion) ?>
            <?php else: ?>
                Consulta de Disponibilidad
            <?php endif; ?>
        </small>
    </div>
    <button type="button" class="btn btn-outline-light btn-sm" onclick="cerrarDisponibilidad()">Cerrar</button>
</div>

<div class="map-viewport">
    <div class="glass-card map-card">
        <div class="map-content" id="mapContent">
            <?php if ($evento): ?>
                <div class="screen"><?= ($evento['tipo']==1)?'ESCENARIO':'ESCENARIO PRINCIPAL' ?></div>

                <?php 
                // =========================================================
                // CASO 1: PASARELA (TIPO 2)
                // =========================================================
                if ($evento['tipo'] == 2): 
                ?>
                    <div style="position: relative; display: flex; flex-direction: column;">
                        <?php for ($fila=1; $fila<=10; $fila++):
                            $nombre_fila = "PB".$fila;
                            $numero_en_fila_pb = 1; ?>
                        <div class="seat-row-wrapper">
                            <div class="row-label"><?= $nombre_fila ?></div>
                            <div class="seats-block">
                                <?php for ($i=1; $i<=6; $i++): 
                                    echo renderSeat($nombre_fila . '-' . $numero_en_fila_pb++, $mapa_guardado, $asientos_vendidos, $colores_por_id, $info_categorias, $id_categoria_general, $color_default);
                                endfor; ?>
                            
                                <div style="width: 80px; flex-shrink: 0;"></div>
                            
                                <?php for ($i=1; $i<=6; $i++): 
                                    echo renderSeat($nombre_fila . '-' . $numero_en_fila_pb++, $mapa_guardado, $asientos_vendidos, $colores_por_id, $info_categorias, $id_categoria_general, $color_default);
                                endfor; ?>
                            </div>
                            <div class="row-label"><?= $nombre_fila ?></div>
                        </div>
                        <?php endfor; ?>
                        
                        <div class="pasarela" style="height: <?= (40 + 8) * 10 ?>px;">
                            <span class="pasarela-text">PASARELA</span>
                        </div>
                    </div>
                    <hr style="margin: 30px 0; border: 0; border-top: 2px dashed #cbd5e1;">
                <?php endif; ?>

                <?php 
                // =========================================================
                // ZONA DE BUTACAS (COMÚN TIPO 1 Y 2)
                // =========================================================
                $letras = range('A','O'); 
                foreach ($letras as $fila): $numero_en_fila = 1; ?>
                <div class="seat-row-wrapper">
                    <div class="row-label"><?= $fila ?></div>
                    <div class="seats-block">
                        <?php for ($i=0;$i<6;$i++): 
                            echo renderSeat($fila . $numero_en_fila++, $mapa_guardado, $asientos_vendidos, $colores_por_id, $info_categorias, $id_categoria_general, $color_default);
                        endfor; ?>
                        
                        <div class="pasillo"></div>
                    
                        <?php for ($i=0;$i<14;$i++): 
                            echo renderSeat($fila . $numero_en_fila++, $mapa_guardado, $asientos_vendidos, $colores_por_id, $info_categorias, $id_categoria_general, $color_default);
                        endfor; ?>
                    
                        <div class="pasillo"></div>
                    
                        <?php for ($i=0;$i<6;$i++): 
                            echo renderSeat($fila . $numero_en_fila++, $mapa_guardado, $asientos_vendidos, $colores_por_id, $info_categorias, $id_categoria_general, $color_default);
                        endfor; ?>
                    </div>
                    <div class="row-label"><?= $fila ?></div>
                </div>
                <?php endforeach; ?>

                <div class="seat-row-wrapper" style="margin-top: 15px;">
                    <div class="row-label">P</div>
                    <div class="seats-block">
                        <?php $numero_en_fila_p = 1; for ($i=0;$i<30;$i++): 
                            echo renderSeat('P' . $numero_en_fila_p++, $mapa_guardado, $asientos_vendidos, $colores_por_id, $info_categorias, $id_categoria_general, $color_default);
                        endfor; ?>
                    </div>
                    <div class="row-label">P</div>
                </div>

            <?php else: ?>
                <div class="alert alert-warning text-center">No se encontró información del evento o el ID es inválido.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="leyenda">
    <div class="leyenda-item"><div class="dot" style="background: #6b7280; border: 1px solid #4b5563;"></div> Ocupado</div>
    <div class="leyenda-item"><div class="dot" style="background: #e2e8f0; border: 1px solid #cbd5e1;"></div> Disponible</div>
</div>

<script>
    // Ajustar zoom automáticamente para que el mapa quepa en pantalla
    function ajustarMapa() {
        const viewport = document.querySelector('.map-viewport');
        const content = document.querySelector('.map-content');
        if (!viewport || !content) return;
        
        content.style.transform = 'scale(1)';
        const anchoDisponible = viewport.clientWidth - 40; 
        const anchoContenido = content.scrollWidth;
        
        let escala = 1;
        if (anchoContenido > anchoDisponible) {
            escala = anchoDisponible / anchoContenido;
        }
        // Límite mínimo para móviles
        if (escala < 0.5) escala = 0.5;
        
        content.style.transform = 'scale(' + escala + ')';
    }

    function cerrarDisponibilidad() {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = 'cartelera_cliente.php';
        }
    }

    window.addEventListener('load', ajustarMapa);
    window.addEventListener('resize', ajustarMapa);
</script>

</body>
</html>