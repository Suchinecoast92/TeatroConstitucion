<?php
// 1. CONEXIÓN y SESIÓN
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

// Ajusta la ruta si es necesario. Asumo que está en /vnt_interfaz/
include "../conexion.php";
require_once __DIR__ . '/../config/ventas.php';
require_once __DIR__ . '/../includes/catalogo_boletos_helper.php';
$horasVentaAbierta = (int) HORAS_CIERRE_VENTAS_POST_FUNCION;

// Cargar helper de transacciones
if(file_exists("../transacciones_helper.php")) {
    require_once "../transacciones_helper.php";
}

// AUTO-ARCHIVADO: Ejecutar verificación de eventos caducados
include_once __DIR__ . "/../evt_interfaz/auto_archivar.php";

// Detectar si es empleado o admin para redirigir al lugar correcto
$es_empleado = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] !== 'admin';
$url_regresar = $es_empleado ? '../index_empleado.php' : 'index.php';
$url_panel = $es_empleado ? '../index_empleado.php' : '../index.php';

$id_evento_seleccionado = null;
$id_funcion_seleccionada = null;
$evento_info = null;
$eventos_lista = [];
$categorias_palette = [];
$mapa_guardado = []; 
$funciones_evento = [];

// --- MODIFICADO: Empezar vacíos. Se llenarán dinámicamente ---
$colores_por_id = []; 
$categorias_js = []; 

// 2. Cargar todos los eventos (incluyendo imagen para el póster)
// Solo eventos con funciones disponibles. El evento cierra las ventas 24 horas
// después de su última función. La comparación se hace en SQL con NOW() para
// usar la misma zona horaria que los datos (evita desfases de zona con PHP).
$sql_eventos = "SELECT e.id_evento, e.titulo, e.tipo, e.mapa_json, e.imagen,
                       (SELECT COUNT(*) FROM funciones f 
                        WHERE f.id_evento = e.id_evento 
                        AND f.estado = 0 
                        AND f.fecha_hora > (NOW() - INTERVAL {$horasVentaAbierta} HOUR)) as tiene_funciones
                FROM evento e 
                WHERE e.finalizado = 0 
                HAVING tiene_funciones > 0
                ORDER BY e.titulo ASC";
$res_eventos = $conn->query($sql_eventos);
if ($res_eventos) {
    $eventos_lista = $res_eventos->fetch_all(MYSQLI_ASSOC);
}

// 3. Verificar si se seleccionó un evento
if (isset($_GET['id_evento']) && is_numeric($_GET['id_evento'])) {
    $id_evento_seleccionado = (int)$_GET['id_evento'];

    foreach ($eventos_lista as $evt) {
        if ($evt['id_evento'] == $id_evento_seleccionado) {
            $evento_info = $evt;
            break;
        }
    }

    if ($evento_info) {
        // 4. Cargar la paleta
        $stmt_cat = $conn->prepare("SELECT * FROM categorias WHERE id_evento = ? ORDER BY precio ASC");
        $stmt_cat->bind_param("i", $id_evento_seleccionado);
        $stmt_cat->execute();
        $res_categorias = $stmt_cat->get_result();
        
        // --- INICIO: MODIFICADO (Lógica para buscar "General" como default) ---
        $id_categoria_general = null; 
        $color_categoria_general = '#BDBDBD'; // Color fallback (gris)
        $nombre_categoria_general = 'General (Default)';
        $precio_categoria_general = 0.00;

        if ($res_categorias) {
            $categorias_palette = $res_categorias->fetch_all(MYSQLI_ASSOC);
            foreach ($categorias_palette as $c) {
                // Llenar los arrays para JS y PHP
                $colores_por_id[$c['id_categoria']] = $c['color'];
                $categorias_js[$c['id_categoria']] = [
                    'nombre' => $c['nombre_categoria'],
                    'precio' => $c['precio']
                ];
                
                // Buscar "General" (ignorando mayúsculas/minúsculas)
                if (is_null($id_categoria_general) && strtolower($c['nombre_categoria']) === 'general') {
                    $id_categoria_general = (int)$c['id_categoria'];
                    $color_categoria_general = $c['color'];
                    $nombre_categoria_general = $c['nombre_categoria'];
                    $precio_categoria_general = $c['precio'];
                }
            }
        }
        $stmt_cat->close();
        
        // Si por alguna razón "General" NO existe en la BD, creamos un fallback con ID 0
        // para evitar que la página se rompa.
        if (is_null($id_categoria_general)) {
             $id_categoria_general = 0; 
             // Asegurarnos de que el fallback exista en los arrays
             if (!isset($colores_por_id[0])) {
                 $colores_por_id[0] = $color_categoria_general;
             }
             if (!isset($categorias_js[0])) {
                 $categorias_js[0] = [
                     'nombre' => $nombre_categoria_general, 
                     'precio' => $precio_categoria_general
                 ];
             }
        }
        // --- FIN: MODIFICADO ---

        // 5. Cargar funciones disponibles para el evento
        // Se permite vender hasta 24 horas después de iniciada la función (comparación en SQL/NOW()).
        $stmt_fun = $conn->prepare("SELECT id_funcion, fecha_hora, estado FROM funciones WHERE id_evento = ? AND fecha_hora > (NOW() - INTERVAL {$horasVentaAbierta} HOUR) ORDER BY fecha_hora ASC");
        $stmt_fun->bind_param("i", $id_evento_seleccionado);
        $stmt_fun->execute();
        $res_funciones = $stmt_fun->get_result();
        if ($res_funciones) {
            $funciones_evento = $res_funciones->fetch_all(MYSQLI_ASSOC);
        }
        $stmt_fun->close();

        if (!empty($funciones_evento)) {
            if (isset($_GET['id_funcion'])) {
                $id_funcion_propuesto = (int)$_GET['id_funcion'];
                foreach ($funciones_evento as $funcion) {
                    if ((int)$funcion['id_funcion'] === $id_funcion_propuesto) {
                        $id_funcion_seleccionada = $id_funcion_propuesto;
                        break;
                    }
                }
            }
            // NO seleccionar función por defecto - el vendedor debe elegir manualmente
        }

        // 6. Cargar el mapa desde JSON
        if (!empty($evento_info['mapa_json'])) {
            $mapa_guardado = json_decode($evento_info['mapa_json'], true);
        }
    }
}

// Cargar precios por tipo de boleto
$precios_tipo_boleto = [
    'adulto' => 80,
    'general' => 80,  // Precio general (igual a adulto por defecto)
    'nino' => 50,
    'adulto_mayor' => 60,
    'discapacitado' => 40,
    'cortesia' => 0
];

// Verificar si existe la tabla de precios por tipo
$check_table = $conn->query("SHOW TABLES LIKE 'precios_tipo_boleto'");
if ($check_table && $check_table->num_rows > 0) {
    // Primero intentar cargar precios específicos del evento
    if ($id_evento_seleccionado) {
        $stmt_precios = $conn->prepare("SELECT tipo_boleto, precio FROM precios_tipo_boleto WHERE id_evento = ?");
        $stmt_precios->bind_param("i", $id_evento_seleccionado);
        $stmt_precios->execute();
        $res_precios = $stmt_precios->get_result();
        
        if ($res_precios && $res_precios->num_rows > 0) {
            // Tiene precios específicos
            while ($row = $res_precios->fetch_assoc()) {
                $precios_tipo_boleto[$row['tipo_boleto']] = (float)$row['precio'];
            }
        } else {
            // Usar precios globales
            $res_global = $conn->query("SELECT tipo_boleto, precio FROM precios_tipo_boleto WHERE id_evento IS NULL");
            if ($res_global) {
                while ($row = $res_global->fetch_assoc()) {
                    $precios_tipo_boleto[$row['tipo_boleto']] = (float)$row['precio'];
                }
            }
        }
        $stmt_precios->close();
    } else {
        // Sin evento seleccionado, usar globales
        $res_global = $conn->query("SELECT tipo_boleto, precio FROM precios_tipo_boleto WHERE id_evento IS NULL");
        if ($res_global) {
            while ($row = $res_global->fetch_assoc()) {
                $precios_tipo_boleto[$row['tipo_boleto']] = (float)$row['precio'];
            }
        }
    }
}

// Sincronizar precios estándar desde categorias (fuente del mapa de asientos)
if ($id_evento_seleccionado && $evento_info) {
    $mapa_cat_tipo = [
        'General' => 'general',
        'Niño' => 'nino',
        'Nino' => 'nino',
        '3ra Edad' => 'adulto_mayor',
        'Adulto Mayor' => 'adulto_mayor',
        'Discapacitado' => 'discapacitado',
    ];
    $stmt_cat_precios = $conn->prepare('SELECT nombre_categoria, precio FROM categorias WHERE id_evento = ?');
    $stmt_cat_precios->bind_param('i', $id_evento_seleccionado);
    $stmt_cat_precios->execute();
    $res_cat_precios = $stmt_cat_precios->get_result();
    while ($row = $res_cat_precios->fetch_assoc()) {
        if (isset($mapa_cat_tipo[$row['nombre_categoria']])) {
            $precios_tipo_boleto[$mapa_cat_tipo[$row['nombre_categoria']]] = (float) $row['precio'];
        }
    }
    $stmt_cat_precios->close();
}

$config_tipos_boleto = config_tipos_boleto_js($conn, $id_evento_seleccionado ?: null);

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Punto de Venta</title>
<link rel="icon" href="../crt_interfaz/imagenes_teatro/nat.png" type="image/png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="css/carrito.css?v=2">
<link rel="stylesheet" href="css/descuentos-modal.css">
<link rel="stylesheet" href="css/notifications.css">
<link rel="stylesheet" href="css/animations.css">
<link rel="stylesheet" href="css/menu-mejoras.css?v=2">
<link rel="stylesheet" href="css/seleccion-multiple.css">
<link rel="stylesheet" href="../assets/css/seat-map.css?v=1">
<link rel="stylesheet" href="css/buscador_pos.css">
<script src="js/qz-tray.js"></script>
<script src="js/qz_interface.js"></script>
<style>
:root {
  --primary-color: #1561f0;
  --primary-dark: #0d4fc4;
  --success-color: #32d74b;
  --danger-color: #ff453a;
  --warning-color: #ff9f0a;
  --bg-primary: #131313;
  --bg-secondary: #1c1c1e;
  --text-primary: #ffffff;
  --text-secondary: #86868b;
  --border-color: #3a3a3c;
  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.3);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.4);
  --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
  --radius-sm: 8px;
  --radius-md: 12px;
  --radius-lg: 16px;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

html, body {
  height: 100vh;
  overflow: hidden;
}

body {
  background: var(--bg-primary);
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  color: var(--text-primary);
  line-height: 1.6;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

.container-fluid {
  display: flex;
  height: 100vh;
  padding: 20px;
  gap: 20px;
  overflow: hidden;
}

.card {
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-md);
  transition: all 0.3s ease;
}

.mapper-container {
  flex: 1;
  display: flex;
  gap: 20px;
  overflow: hidden;
  position: relative;
  min-width: 0;
}

.seat-map-wrapper {
  flex: 1;
  background: var(--bg-secondary);
  border-radius: var(--radius-lg);
  padding: 40px;
  overflow: hidden;
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-md);
  display: flex;
  justify-content: center;
  position: relative;
  min-width: 0;
  animation: none !important;
}

.seat-map-wrapper::-webkit-scrollbar {
  width: 8px;
}

.seat-map-wrapper::-webkit-scrollbar-track {
  background: var(--bg-primary);
  border-radius: 4px;
}

.seat-map-wrapper::-webkit-scrollbar-thumb {
  background: var(--border-color);
  border-radius: 4px;
  transition: background 0.2s;
}

.seat-map-wrapper::-webkit-scrollbar-thumb:hover {
  background: var(--text-secondary);
}

.seat-map-content {
  min-width: min-content;
  transform-origin: top center;
  transition: transform 0.3s ease;
}

.screen {
  background: linear-gradient(135deg, #1c1c1e 0%, #334155 100%);
  color: white;
  padding: 10px 20px;
  text-align: center;
  font-weight: 700;
  font-size: 0.85rem;
  letter-spacing: 2px;
  border-radius: 8px;
  margin-bottom: 20px;
  box-shadow: var(--shadow-lg);
  position: sticky;
  top: 0;
  z-index: 50;
  border: 1px solid var(--border-color);
}

.seat {
  width: 32px;
  height: 32px;
  background: #0066ff;
  color: #000000;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
  padding: 2px;
  box-sizing: border-box;
  text-align: center;
  line-height: 1;
}

.seat:hover, .seat:focus {
  background: #0052cc;
  transform: scale(1.1);
  outline: none;
}

.seat.selected {
  background: #32d74b !important;
  transform: scale(1.1);
}

.seat.vendido {
  background: #1c1c1e !important;
  color: #666 !important;
  cursor: not-allowed !important;
  opacity: 0.6;
}

.seat:active {
  transform: scale(0.95);
}

.row-label {
  width: 32px;
  text-align: center;
  font-weight: 700;
  font-size: 0.85rem;
  color: var(--text-secondary);
  border-radius: 6px;
  padding: 6px 0;
  cursor: pointer;
  transition: all 0.2s ease;
  user-select: none;
}

.row-label:hover {
  background-color: var(--bg-primary);
  color: var(--primary-color);
  transform: scale(1.05);
}

.pasarela-container {
  position: relative;
  width: 50px;
  flex-shrink: 0;
}

.pasarela {
  width: 50px;
  background: linear-gradient(180deg, var(--text-primary) 0%, #334155 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  position: absolute;
  top: 0;
  left: 0;
  box-shadow: var(--shadow-md);
}

.pasarela-text {
  writing-mode: vertical-rl;
  text-orientation: mixed;
  font-weight: 700;
  letter-spacing: 3px;
  font-size: 0.6rem;
}

.seats-block {
  display: flex;
  align-items: center;
  gap: 4px;
}

.seat-row-wrapper {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-bottom: 6px;
}

.pasillo, .aisle {
  width: 20px;
}

.controls-panel {
  width: 280px;
  height: 100%;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  overflow-y: auto;
  overflow-x: hidden;
  padding: 12px;
  gap: 10px;
  z-index: 200;
  position: relative;
  animation: none !important;
}

.controls-panel::-webkit-scrollbar {
  width: 6px;
}

.controls-panel::-webkit-scrollbar-track {
  background: var(--bg-primary);
  border-radius: 4px;
}

.controls-panel::-webkit-scrollbar-thumb {
  background: var(--border-color);
  border-radius: 4px;
  transition: background 0.2s;
}

.controls-panel::-webkit-scrollbar-thumb:hover {
  background: var(--text-secondary);
}

.panel-header, .palette-header {
  padding: 12px;
  border-bottom: 1px solid var(--border-color);
  background: linear-gradient(to right, var(--bg-primary), var(--bg-secondary));
  border-radius: 8px;
  margin-bottom: 8px;
}

.palette-body, .panel-body {
  padding: 10px;
  overflow-y: auto;
  flex: 1;
}

.palette-footer, .panel-footer {
  padding: 12px;
  border-top: 1px solid var(--border-color);
  background: var(--bg-primary);
  border-radius: 8px;
  margin-top: 8px;
}

.controls-panel h2 {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.controls-panel h5 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.seccion-horario {
  margin-bottom: 4px;
}

.form-label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 6px;
}

.form-select {
  border: 1px solid #4a4a4c;
  border-radius: var(--radius-sm);
  padding: 8px 12px;
  font-size: 0.9rem;
  transition: all 0.2s;
  background-color: #2b2b2b;
  color: #ffffff;
}

.form-select:focus {
  border-color: #1561f0;
  box-shadow: 0 0 0 3px rgba(21, 97, 240, 0.3);
  outline: none;
}

/* Estilos para opciones deshabilitadas (funciones vencidas) */
.form-select option:disabled {
  color: #666 !important;
  background-color: #1c1c1e !important;
  font-style: italic;
  cursor: not-allowed;
}

#selectFuncion option:disabled {
  color: #666 !important;
  background-color: #1c1c1e !important;
}

/* === CARTELERA DE EVENTOS === */
.eventos-cartelera {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 12px;
  max-height: 280px;
  overflow-y: auto;
  padding: 4px;
}

.evento-mini-card {
  background: #2b2b2b;
  border: 2px solid #4a4a4c;
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.3s ease;
}

.evento-mini-card:hover {
  border-color: #1561f0;
  transform: translateY(-3px);
  box-shadow: 0 8px 20px rgba(21,97,240,0.3);
}

.evento-mini-card.selected {
  border-color: #1561f0;
  box-shadow: 0 0 0 3px rgba(21,97,240,0.3);
}

.evento-mini-poster {
  width: 100%;
  aspect-ratio: 2/3;
  object-fit: cover;
  display: block;
}

.evento-mini-poster-placeholder {
  width: 100%;
  aspect-ratio: 2/3;
  background: linear-gradient(135deg, #3a3a3c 0%, #2b2b2b 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #86868b;
  font-size: 1.5rem;
}

.evento-mini-info {
  padding: 8px;
  text-align: center;
}

.evento-mini-titulo {
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--text-primary);
  line-height: 1.2;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.cambiar-evento-btn {
  background: var(--bg-primary);
  border: 1px dashed var(--border-color);
  border-radius: 8px;
  padding: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
  color: var(--text-secondary);
  font-size: 0.85rem;
  transition: all 0.2s;
}

.cambiar-evento-btn:hover {
  background: var(--bg-secondary);
  border-color: var(--primary-color);
  color: var(--primary-color);
}

/* === CARTELERA FULLSCREEN (sin evento seleccionado) === */
.cartelera-fullscreen {
  min-height: 100vh;
  height: auto;
  background: linear-gradient(135deg, var(--bg-primary) 0%, #1c1c1e 100%);
  padding: 40px;
  overflow-y: auto;
  overflow-x: hidden;
}

/* Permitir scroll solo cuando estamos en cartelera fullscreen */
body:has(.cartelera-fullscreen) {
  overflow-y: auto;
  height: auto;
}

html:has(.cartelera-fullscreen) {
  overflow-y: auto;
  height: auto;
}

.cartelera-header {
  text-align: center;
  margin-bottom: 40px;
}

.cartelera-header h1 {
  font-size: 2.5rem;
  font-weight: 800;
  color: var(--text-primary);
  margin-bottom: 8px;
}

.cartelera-header h1 i {
  color: var(--primary-color);
}

.cartelera-header p {
  color: var(--text-secondary);
  font-size: 1.1rem;
}

.cartelera-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 24px;
  max-width: 1400px;
  margin: 0 auto;
}

.evento-card-full {
  background: var(--bg-secondary);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 15px rgba(0,0,0,0.08);
  transition: all 0.3s ease;
  cursor: pointer;
}

.evento-card-full:hover {
  transform: translateY(-8px);
  box-shadow: 0 15px 35px rgba(37,99,235,0.2);
}

.evento-card-full img {
  width: 100%;
  aspect-ratio: 2/3;
  object-fit: cover;
  display: block;
  transition: transform 0.4s ease;
}

.evento-card-full:hover img {
  transform: scale(1.05);
}

.evento-card-full .placeholder-img {
  width: 100%;
  aspect-ratio: 2/3;
  background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-secondary);
  font-size: 3rem;
}

.evento-card-full .info {
  padding: 16px;
}

.evento-card-full .titulo {
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 4px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.evento-card-full .tipo-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.75rem;
  padding: 4px 10px;
  border-radius: 20px;
  background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
  color: var(--primary-dark);
  font-weight: 600;
}

.btn {
  border-radius: var(--radius-sm);
  padding: 10px 20px;
  font-weight: 600;
  font-size: 0.95rem;
  transition: all 0.2s;
  border: none;
  cursor: pointer;
}

.btn-primary {
  background: var(--primary-color);
  color: white;
}

.btn-primary:hover {
  background: var(--primary-dark);
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
}

.btn-success {
  background: var(--success-color);
  color: white;
}

.btn-success:hover:not(:disabled) {
  background: #059669;
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
}

.btn-success:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.alert {
  border-radius: var(--radius-sm);
  padding: 12px 16px;
  font-size: 0.9rem;
  border: none;
  background: #dbeafe;
  color: #1e40af;
}

.list-group-item {
  border: 1px solid var(--border-color);
  padding: 12px 16px;
  font-size: 0.9rem;
  transition: all 0.2s;
}

.list-group-item:first-child {
  border-top-left-radius: var(--radius-sm);
  border-top-right-radius: var(--radius-sm);
}

.list-group-item:last-child {
  border-bottom-left-radius: var(--radius-sm);
  border-bottom-right-radius: var(--radius-sm);
}

.list-group-item:hover {
  background-color: var(--bg-primary);
}

.palette-color {
  box-shadow: var(--shadow-sm);
  border: 2px solid white;
}

hr {
  border: none;
  height: 1px;
  background: var(--border-color);
  margin: 20px 0;
}

.modal-content {
  border-radius: var(--radius-lg);
  border: none;
  box-shadow: var(--shadow-lg);
}

.modal-header {
  border-bottom: 1px solid var(--border-color);
  padding: 20px 24px;
}

.modal-body {
  padding: 24px;
}

.modal-footer {
  border-top: 1px solid var(--border-color);
  padding: 16px 24px;
}

/* Spinner personalizado */
.spinner-border {
  width: 2.5rem;
  height: 2.5rem;
  border-width: 3px;
}

/* Mejoras en inputs y selects */
.form-select:hover {
  border-color: var(--primary-color);
}

/* Animaciones suaves */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.controls-panel > * {
  animation: fadeIn 0.3s ease;
}

/* Mejoras en botones */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn i {
  font-size: 1.1em;
}

/* Botón para ocultar paneles */
.toggle-panels-btn {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 1000;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: var(--primary-color);
  color: white;
  border: none;
  box-shadow: var(--shadow-lg);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
  font-size: 1.3rem;
}

.toggle-panels-btn:hover {
  background: var(--primary-dark);
  transform: scale(1.1);
}

.toggle-panels-btn:active {
  transform: scale(0.95);
}

/* Clases para ocultar paneles */
.controls-panel.hidden {
  display: none;
}

.seat-map-wrapper.fullscreen {
  width: 100%;
}

/* Responsive */
@media (max-width: 1200px) {
  .controls-panel {
    width: 280px;
  }
}

@media (max-width: 992px) {
  .mapper-container {
    flex-direction: column;
  }
  
  .controls-panel {
    width: 100%;
    max-height: 400px;
  }
  
  .seat-map-wrapper {
    min-height: 500px;
  }
}

@media (max-width: 768px) {
  .container-fluid {
    padding: 16px;
  }
  
  .seat {
    width: 42px;
    height: 42px;
    font-size: 11px;
  }
  
  .row-label {
    width: 42px;
    font-size: 1rem;
  }
  
  .seats-block {
    gap: 6px;
  }
  
  .seat-row-wrapper {
    margin-bottom: 8px;
  }
}

/* Sección de Categorías Separada */
.seccion-categorias-separada {
  margin-top: 20px;
  padding-top: 0;
  border: 1px solid var(--border-color);
  border-radius: 10px;
  overflow: hidden;
  background: var(--bg-secondary);
}

.seccion-categorias-separada .seccion-header {
  background: linear-gradient(135deg, #0066ff 0%, #0052cc 100%);
  border-bottom: 1px solid #003d99;
}

.seccion-categorias-separada .seccion-header:hover {
  background: linear-gradient(135deg, #0052cc 0%, #003d99 100%);
}

.seccion-categorias-separada .seccion-header h5 {
  color: #ffffff;
}

.seccion-categorias-separada .seccion-header .toggle-icon {
  color: #ffffff;
}

.categorias-info-text {
  background: #1c1c1e;
  border: 1px solid #3a3a3c;
  border-radius: 6px;
  padding: 8px 12px;
  color: #ffffff;
}

.categorias-info-text i {
  color: #0066ff;
}

/* Mejoras visuales adicionales */
.list-group {
  border-radius: var(--radius-sm);
  overflow: hidden;
}

.d-grid .btn {
  padding: 12px 20px;
}

/* Efecto hover en categorías */
.list-group-item {
  cursor: default;
  transition: all 0.2s ease;
}

/* Sombra suave en elementos interactivos */
.form-select:focus,
.btn:focus {
  outline: none;
}

/* Mejora en el diseño del total */
.total-section h4 {
  font-size: 1.3rem;
  letter-spacing: -0.5px;
}

/* ===== ESTILOS MEJORADOS DEL PANEL ===== */

.panel-header {
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 2px solid var(--border-color);
}

.panel-header h2 {
  margin-bottom: 8px;
}

.evento-badge {
  background: #0066ff;
  border: 1px solid #0052cc;
  border-radius: 8px;
  padding: 8px 12px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.85rem;
  color: #ffffff;
  font-weight: 600;
  margin-top: 8px;
}

.evento-badge i {
  font-size: 1rem;
  color: #ffffff;
}

.seccion-evento {
  margin-bottom: 20px;
}

.input-group-text {
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  color: var(--text-secondary);
}

/* Estadísticas Rápidas */
.stats-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 20px;
}

.stat-card {
  background: #0066ff;
  border: 1px solid #0052cc;
  border-radius: 10px;
  padding: 14px;
  display: flex;
  align-items: center;
  gap: 12px;
  transition: all 0.2s ease;
}

.stat-card:hover {
  border-color: #ffffff;
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}

.stat-card i {
  font-size: 1.8rem;
  color: #ffffff;
}

.stat-value {
  font-size: 1.3rem;
  font-weight: 700;
  color: #ffffff;
  line-height: 1;
}

.stat-label {
  font-size: 0.75rem;
  color: rgba(255,255,255,0.8);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-top: 2px;
}

/* Secciones Colapsables */
.seccion-carrito,
.seccion-categorias-separada {
  margin-bottom: 8px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  overflow: hidden;
  background: var(--bg-secondary);
}

/* Contenedor de items del carrito con scroll */
.carrito-items-container {
  max-height: 150px;
  overflow-y: auto;
  overflow-x: hidden;
  margin-bottom: 8px;
  padding-right: 4px;
}

.carrito-items-container::-webkit-scrollbar {
  width: 6px;
}

.carrito-items-container::-webkit-scrollbar-track {
  background: var(--bg-primary);
  border-radius: 3px;
}

.carrito-items-container::-webkit-scrollbar-thumb {
  background: var(--border-color);
  border-radius: 3px;
  transition: background 0.2s;
}

.carrito-items-container::-webkit-scrollbar-thumb:hover {
  background: var(--text-secondary);
}

/* Footer fijo del carrito */
.carrito-footer {
  position: relative;
  background: var(--bg-secondary);
  padding-top: 12px;
  border-top: 2px solid var(--border-color);
}

.seccion-header {
  background: linear-gradient(135deg, #0066ff 0%, #0052cc 100%);
  padding: 12px 16px;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  transition: all 0.2s ease;
  user-select: none;
}

.seccion-header:hover {
  background: linear-gradient(135deg, #0052cc 0%, #003d99 100%);
}

.seccion-header h5 {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
  color: #ffffff;
  display: flex;
  align-items: center;
  gap: 8px;
}

.seccion-header .toggle-icon {
  transition: transform 0.3s ease;
  color: #ffffff;
}

.seccion-header.collapsed .toggle-icon {
  transform: rotate(-90deg);
}

.seccion-content {
  padding: 10px;
  max-height: 800px;
  overflow: hidden;
  transition: max-height 0.3s ease, padding 0.3s ease;
}

.seccion-content.collapsed {
  max-height: 0;
  padding: 0 10px;
}

/* Selector de Descuentos Minimalista */
.descuento-selector-separado {
  background: transparent;
  border: none;
  border-radius: 0;
  padding: 0;
  margin-bottom: 16px;
}

.descuento-selector-separado .form-label {
  margin-bottom: 6px;
  font-size: 0.75rem;
  color: var(--text-secondary);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  display: flex;
  align-items: center;
  gap: 4px;
}

.descuento-selector-separado .form-label i {
  font-size: 0.9rem;
}

.descuento-selector-separado .form-select {
  font-size: 0.9rem;
  border: 1px solid var(--border-color);
  background-color: var(--bg-secondary);
  font-weight: 500;
  padding: 10px 12px;
  border-radius: var(--radius-sm);
  transition: all 0.2s ease;
}

.descuento-selector-separado .form-select:hover {
  border-color: var(--text-secondary);
}

.descuento-selector-separado .form-select:focus {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.descuento-selector-separado small {
  font-size: 0.75rem;
  color: var(--success-color);
  font-weight: 500;
}

.descuento-selector .form-label {
  margin-bottom: 6px;
  font-size: 0.85rem;
  color: #92400e;
  font-weight: 600;
}

.descuento-selector .form-select {
  font-size: 0.9rem;
}

.descuento-selector small {
  display: block;
  margin-top: 6px;
  font-size: 0.8rem;
  color: #92400e;
}

/* Acciones Rápidas */
.acciones-rapidas {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--border-color);
}

.acciones-rapidas .btn {
  font-weight: 600;
  padding: 12px 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.acciones-rapidas .btn-outline-secondary {
  border: 2px solid var(--border-color);
  color: var(--text-secondary);
}

.acciones-rapidas .btn-outline-secondary:hover {
  background: var(--bg-primary);
  border-color: var(--text-secondary);
  color: var(--text-primary);
}

/* Sección de Categorías Separada */
.seccion-categorias-separada {
  margin-top: 20px;
  padding-top: 0;
}

.seccion-categorias-separada .seccion-header {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  border-bottom: 1px solid #fbbf24;
}

.seccion-categorias-separada .seccion-header:hover {
  background: linear-gradient(135deg, #fde68a 0%, #fcd34d 100%);
}

.seccion-categorias-separada .seccion-header h5 {
  color: #92400e;
}

.seccion-categorias-separada .seccion-header .toggle-icon {
  color: #92400e;
}

.categorias-info-text {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 6px;
  padding: 8px 12px;
}

.categorias-info-text i {
  color: #2563eb;
}

/* Mejoras en badges */
.badge {
  padding: 6px 12px;
  font-weight: 600;
  font-size: 0.85rem;
}

/* Animaciones suaves para las secciones */
@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.seccion-content:not(.collapsed) {
  animation: slideDown 0.3s ease;
}
/* Responsive para el panel - ajustado para iframe */
@media (max-width: 1200px) {
  .controls-panel {
    width: 260px;
    min-width: 240px;
    padding: 12px;
  }
  
  .seat {
    width: 28px;
    height: 28px;
    font-size: 9px;
  }
  
  .pasillo {
    width: 18px;
  }
}

@media (max-width: 900px) {
  .mapper-container {
    flex-direction: column;
  }
  
  .controls-panel {
    width: 100%;
    max-width: none;
    min-width: unset;
    max-height: 40vh;
  }
  
  .seat-map-wrapper {
    min-height: 55vh;
  }
  
  .stats-grid {
    grid-template-columns: 1fr 1fr;
  }
  
  .stat-card {
    padding: 10px;
  }
}

/* Overlay para bloquear asientos cuando no hay horario seleccionado */
.seat-map-wrapper {
  position: relative;
}

.overlay-sin-horario {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  width: 100%;
  height: 100%;
  background: rgba(19, 19, 19, 0.95);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
  border-radius: var(--radius-lg);
  transition: opacity 0.3s ease, visibility 0.3s ease;
}

.overlay-sin-horario.hidden {
  opacity: 0;
  visibility: hidden;
  pointer-events: none;
}

.overlay-sin-horario-content {
  text-align: center;
  color: white;
  padding: 40px;
  max-width: 400px;
}

.overlay-sin-horario-content i.bi-calendar-x {
  font-size: 5rem;
  color: #f59e0b;
  display: block;
  margin-bottom: 20px;
  animation: pulse 2s infinite;
}

.overlay-sin-horario-content h3 {
  font-size: 1.8rem;
  font-weight: 700;
  margin-bottom: 12px;
}

.overlay-sin-horario-content p {
  font-size: 1.1rem;
  color: rgba(255, 255, 255, 0.8);
  margin-bottom: 20px;
}

.overlay-sin-horario-content .arrow-indicator {
  animation: bounceUp 1s infinite;
}

.overlay-sin-horario-content .arrow-indicator i {
  font-size: 2rem;
  color: #10b981;
}

@keyframes bounceUp {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-15px);
  }
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.7;
    transform: scale(1.05);
  }
}
</style>
</head>
<body>

<?php if (!$evento_info): ?>
<!-- ===== CARTELERA FULLSCREEN (sin evento seleccionado) ===== -->
<div class="cartelera-fullscreen">
    <div class="cartelera-header">
        <h1><i class="bi bi-film"></i> Cartelera</h1>
        <p>Selecciona un evento para comenzar a vender</p>
        <div style="margin-top: 20px;">
            <button class="btn btn-outline-light" onclick="abrirVisorCliente()">
                <i class="bi bi-display"></i> Abrir Pantalla Cliente
            </button>
        </div>
    </div>
    
    <?php if (empty($eventos_lista)): ?>
    <div style="text-align:center; padding:80px; color:var(--text-secondary);">
        <i class="bi bi-calendar-x" style="font-size:5rem; opacity:0.3;"></i>
        <h3 style="margin-top:20px; font-weight:600;">No hay eventos disponibles</h3>
        <p>Crea un evento desde el panel de administración</p>
    </div>
    <?php else: ?>
    <div class="cartelera-grid">
        <?php foreach ($eventos_lista as $e): 
            $img_evt = '';
            if (!empty($e['imagen'])) {
                $rutas_img = ["../evt_interfaz/" . $e['imagen'], $e['imagen']];
                foreach ($rutas_img as $r) { if (file_exists($r)) { $img_evt = $r; break; } }
            }
        ?>
        <div class="evento-card-full" onclick="seleccionarEvento(<?= $e['id_evento'] ?>)">
            <?php if($img_evt): ?>
                <img src="<?= htmlspecialchars($img_evt) ?>" alt="">
            <?php else: ?>
                <div class="placeholder-img"><i class="bi bi-image"></i></div>
            <?php endif; ?>
            <div class="info">
                <div class="titulo"><?= htmlspecialchars($e['titulo']) ?></div>
                <span class="tipo-badge">
                    <i class="bi bi-<?= $e['tipo'] == 1 ? 'music-note-beamed' : 'person-walking' ?>"></i>
                    <?= $e['tipo'] == 1 ? 'Teatro' : 'Pasarela' ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Scripts para cartelera fullscreen -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/sync-sender.js?v=2"></script>
<script>
// Función para abrir el visor cliente
function abrirVisorCliente() {
    // Abrir ventana emergente sin barras de navegación
    // En el inicio no hay evento seleccionado, así que enviamos id_evento=0 o vacío
    window.open(`visor_cliente.php`, 'VisorCliente', 'width=1200,height=800,menubar=no,toolbar=no');
}

// Función para seleccionar evento desde cartelera fullscreen
function seleccionarEvento(idEvento) {
    if (!idEvento) return;
    
    // Obtener título del evento si está disponible
    const eventoCard = document.querySelector(`.evento-card-full[onclick*="${idEvento}"]`);
    const titulo = eventoCard?.querySelector('.titulo')?.textContent || 'Cargando evento...';
    
    // Enviar notificación al visor cliente antes de redirigir
    if (typeof enviarSeleccionEvento === 'function') {
        enviarSeleccionEvento(idEvento, titulo);
    }
    
    // Pequeño delay para que el mensaje llegue al visor
    setTimeout(() => {
        window.location.href = 'index.php?id_evento=' + idEvento;
    }, 100);
}
</script>
</body>
</html>
<?php 
endif; // Fin de cartelera fullscreen (!$evento_info)

// Si hay evento, continúa con la vista de ventas
if ($evento_info): 
?>
<!-- ===== VISTA DE VENTAS (con evento seleccionado) ===== -->
<div class="container-fluid">
  
  <!-- Botón para ocultar/mostrar paneles -->
  <button class="toggle-panels-btn" id="togglePanelsBtn" onclick="togglePanels()" title="Ocultar/Mostrar paneles">
    <i class="bi bi-arrows-angle-expand" id="toggleIcon"></i>
  </button>
  
  <div class="mapper-container">
    
    <?php if ($evento_info): ?>
    <div class="seat-map-wrapper" style="position: relative;">
      
      <!-- Overlay de bloqueo cuando no hay horario seleccionado -->
      <?php if (!$id_funcion_seleccionada): ?>
      <div class="overlay-sin-horario" id="overlaySinHorario">
        <div class="overlay-sin-horario-content">
          <i class="bi bi-calendar-x"></i>
          <h3>Seleccione un horario</h3>
          <p>Para comenzar a vender, primero debe seleccionar un horario de función.</p>
          <div class="arrow-indicator">
            <i class="bi bi-arrow-right"></i>
          </div>
        </div>
      </div>
      <?php endif; ?>
      
      <div class="seat-map-content" id="seatMapContent">
      <div class="screen"><?= ($evento_info['tipo']==1)?'ESCENARIO':'PASARELA / ESCENARIO' ?></div>

      <?php 
      // ========== PASARELA 540 (PB + Teatro) ==========
      if ($evento_info['tipo'] == 2): 
      ?>
      <div style="position: relative; display: flex; flex-direction: column;">
      <?php
          for ($fila=1; $fila<=10; $fila++):
              $nombre_fila = "PB".$fila;
              $numero_en_fila_pb = 1; 
      ?>
      <div class="seat-row-wrapper">
        <div class="row-label"><?= $nombre_fila ?></div>
        <div class="seats-block">
          <?php for ($i=1; $i<=6; $i++): 
                  $nombre_asiento = $nombre_fila . '-' . $numero_en_fila_pb++;
                  // --- MODIFICADO: Default a $id_categoria_general ---
                  $id_cat = $mapa_guardado[$nombre_asiento] ?? $id_categoria_general;
                  $color_asiento = $colores_por_id[$id_cat] ?? $color_categoria_general;
          ?>
            <div class="seat" style="background-color: <?= $color_asiento ?>" data-asiento-id="<?= $nombre_asiento ?>" data-categoria-id="<?= $id_cat ?>">
                <?= $nombre_asiento ?>
            </div>
          <?php endfor; ?>
          <div style="width: 60px; flex-shrink: 0;"></div>
          <?php for ($i=1; $i<=6; $i++): 
                  $nombre_asiento = $nombre_fila . '-' . $numero_en_fila_pb++;
                  // --- MODIFICADO: Default a $id_categoria_general ---
                  $id_cat = $mapa_guardado[$nombre_asiento] ?? $id_categoria_general;
                  $color_asiento = $colores_por_id[$id_cat] ?? $color_categoria_general;
          ?>
            <div class="seat" style="background-color: <?= $color_asiento ?>" data-asiento-id="<?= $nombre_asiento ?>" data-categoria-id="<?= $id_cat ?>">
                <?= $nombre_asiento ?>
            </div>
          <?php endfor; ?>
        </div>
        <div class="row-label"><?= $nombre_fila ?></div>
      </div>
      <?php endfor; ?>
      
      <!-- Pasarela posicionada absolutamente sobre todas las filas -->
      <div class="pasarela" style="position: absolute; width: 60px; top: 0; bottom: 0; left: 50%; transform: translateX(-50%); background: linear-gradient(180deg, #1c1c1e 0%, #334155 100%); color: #fff; display: flex; align-items: center; justify-content: center; border-radius: 6px; box-shadow: var(--shadow-md);">
        <span class="pasarela-text" style="font-size: 0.5rem; letter-spacing: 2px;">PASARELA</span>
      </div>
      </div>
      <hr style="margin-top: 20px; margin-bottom: 20px; border-width: 2px;">

      <?php
          $letras = range('A','O'); 
          foreach ($letras as $fila): 
            $numero_en_fila = 1; 
      ?>
          <div class="seat-row-wrapper">
            <div class="row-label"><?= $fila ?></div>
            <div class="seats-block">
              <?php for ($i=0;$i<6;$i++): 
                      $nombre_asiento = $fila . $numero_en_fila++;
                      // --- MODIFICADO: Default a $id_categoria_general ---
                      $id_cat = $mapa_guardado[$nombre_asiento] ?? $id_categoria_general;
                      $color_asiento = $colores_por_id[$id_cat] ?? $color_categoria_general;
              ?>
                <div class="seat" style="background-color: <?= $color_asiento ?>" data-asiento-id="<?= $nombre_asiento ?>" data-categoria-id="<?= $id_cat ?>">
                    <?= $nombre_asiento ?>
                </div>
              <?php endfor; ?>
              <div class="pasillo"></div>
              <?php for ($i=0;$i<14;$i++): 
                      $nombre_asiento = $fila . $numero_en_fila++;
                      // --- MODIFICADO: Default a $id_categoria_general ---
                      $id_cat = $mapa_guardado[$nombre_asiento] ?? $id_categoria_general;
                      $color_asiento = $colores_por_id[$id_cat] ?? $color_categoria_general;
              ?>
                <div class="seat" style="background-color: <?= $color_asiento ?>" data-asiento-id="<?= $nombre_asiento ?>" data-categoria-id="<?= $id_cat ?>">
                    <?= $nombre_asiento ?>
                </div>
              <?php endfor; ?>
              <div class="pasillo"></div>
              <?php for ($i=0;$i<6;$i++): 
                      $nombre_asiento = $fila . $numero_en_fila++;
                      // --- MODIFICADO: Default a $id_categoria_general ---
                      $id_cat = $mapa_guardado[$nombre_asiento] ?? $id_categoria_general;
                      $color_asiento = $colores_por_id[$id_cat] ?? $color_categoria_general;
              ?>
                <div class="seat" style="background-color: <?= $color_asiento ?>" data-asiento-id="<?= $nombre_asiento ?>" data-categoria-id="<?= $id_cat ?>">
                    <?= $nombre_asiento ?>
                </div>
              <?php endfor; ?>
            </div>
            <div class="row-label"><?= $fila ?></div>
          </div>
          <?php endforeach; ?>
          
          <div class="seat-row-wrapper">
            <div class="row-label">P</div>
            <div class="seats-block">
              <?php $numero_en_fila_p = 1; ?>
              <?php for ($i=0;$i<30;$i++): 
                      $nombre_asiento = 'P' . $numero_en_fila_p++;
                      // --- MODIFICADO: Default a $id_categoria_general ---
                      $id_cat = $mapa_guardado[$nombre_asiento] ?? $id_categoria_general;
                      $color_asiento = $colores_por_id[$id_cat] ?? $color_categoria_general;
              ?>
                <div class="seat" style="background-color: <?= $color_asiento ?>" data-asiento-id="<?= $nombre_asiento ?>" data-categoria-id="<?= $id_cat ?>">
                    <?= $nombre_asiento ?>
                </div>
              <?php endfor; ?>
            </div>
            <div class="row-label">P</div>
          </div>
      <?php 
      // ========== TEATRO 420 (Solo) ==========
      elseif ($evento_info['tipo'] == 1):
          $letras = range('A','O'); 
          foreach ($letras as $fila): 
            $numero_en_fila = 1; 
      ?>
          <div class="seat-row-wrapper">
            <div class="row-label"><?= $fila ?></div>
            <div class="seats-block">
              <?php for ($i=0;$i<6;$i++): 
                      $nombre_asiento = $fila . $numero_en_fila++;
                      // --- MODIFICADO: Default a $id_categoria_general ---
                      $id_cat = $mapa_guardado[$nombre_asiento] ?? $id_categoria_general;
                      $color_asiento = $colores_por_id[$id_cat] ?? $color_categoria_general;
              ?>
                <div class="seat" style="background-color: <?= $color_asiento ?>" data-asiento-id="<?= $nombre_asiento ?>" data-categoria-id="<?= $id_cat ?>">
                    <?= $nombre_asiento ?>
                </div>
              <?php endfor; ?>
              <div class="pasillo"></div>
              <?php for ($i=0;$i<14;$i++): 
                      $nombre_asiento = $fila . $numero_en_fila++;
                      // --- MODIFICADO: Default a $id_categoria_general ---
                      $id_cat = $mapa_guardado[$nombre_asiento] ?? $id_categoria_general;
                      $color_asiento = $colores_por_id[$id_cat] ?? $color_categoria_general;
              ?>
                <div class="seat" style="background-color: <?= $color_asiento ?>" data-asiento-id="<?= $nombre_asiento ?>" data-categoria-id="<?= $id_cat ?>">
                    <?= $nombre_asiento ?>
                </div>
              <?php endfor; ?>
              <div class="pasillo"></div>
              <?php for ($i=0;$i<6;$i++): 
                      $nombre_asiento = $fila . $numero_en_fila++;
                      // --- MODIFICADO: Default a $id_categoria_general ---
                      $id_cat = $mapa_guardado[$nombre_asiento] ?? $id_categoria_general;
                      $color_asiento = $colores_por_id[$id_cat] ?? $color_categoria_general;
              ?>
                <div class="seat" style="background-color: <?= $color_asiento ?>" data-asiento-id="<?= $nombre_asiento ?>" data-categoria-id="<?= $id_cat ?>">
                    <?= $nombre_asiento ?>
                </div>
              <?php endfor; ?>
            </div>
            <div class="row-label"><?= $fila ?></div>
          </div>
          <?php endforeach; ?>
          
          <div class="seat-row-wrapper">
            <div class="row-label">P</div>
            <div class="seats-block">
              <?php $numero_en_fila_p = 1; ?>
              <?php for ($i=0;$i<30;$i++): 
                      $nombre_asiento = 'P' . $numero_en_fila_p++;
                      // --- MODIFICADO: Default a $id_categoria_general ---
                      $id_cat = $mapa_guardado[$nombre_asiento] ?? $id_categoria_general;
                      $color_asiento = $colores_por_id[$id_cat] ?? $color_categoria_general;
              ?>
                <div class="seat" style="background-color: <?= $color_asiento ?>" data-asiento-id="<?= $nombre_asiento ?>" data-categoria-id="<?= $id_cat ?>">
                    <?= $nombre_asiento ?>
                </div>
              <?php endfor; ?>
            </div>
            <div class="row-label">P</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
    <div class="controls-panel card">
      
        <!-- Header del Panel con título del evento -->
        <div class="panel-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0" style="font-size:1rem; font-weight:600;">
                    <i class="bi bi-ticket-perforated text-primary"></i> 
                    <?= htmlspecialchars($evento_info['titulo']) ?>
                </h5>
                <button class="btn btn-sm btn-outline-primary" onclick="location.href='<?= $url_regresar ?>'" title="Cambiar evento">
                    <i class="bi bi-arrow-left-right"></i>
                </button>
            </div>
        </div>
        
        <!-- Selector de Horario -->
        <?php if (!empty($funciones_evento)): ?>
        <div class="seccion-horario">
            <label class="form-label mb-1" style="font-size:0.85rem; font-weight:500;">
                <i class="bi bi-clock"></i> Horario
            </label>
            <input type="hidden" name="id_funcion" id="inputIdFuncion" value="<?= htmlspecialchars($id_funcion_seleccionada ?? '') ?>">
            <select id="selectFuncion" class="form-select" onchange="cambiarFuncion(this)">
                <option value="">Seleccionar horario...</option>
                <?php 
                $dias_semana = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
                foreach ($funciones_evento as $funcion): 
                    $fecha_funcion = new DateTime($funcion['fecha_hora']);
                    $dia = $dias_semana[(int)$fecha_funcion->format('w')];
                    $num = $fecha_funcion->format('d');
                    $hora = $fecha_funcion->format('g:i A');
                    $texto_funcion = "$dia $num - $hora";
                    $estado = (int)$funcion['estado'];
                    $es_vencida = $estado === 1;
                    $texto_estado = $es_vencida ? ' (Vencida)' : '';
                ?>
                <option 
                    value="<?= $funcion['id_funcion'] ?>" 
                    <?= ($id_funcion_seleccionada==$funcion['id_funcion'])?'selected':'' ?>
                    <?= $es_vencida ? 'disabled style="color: #9ca3af;"' : '' ?>
                    data-estado="<?= $estado ?>">
                    <?= $texto_funcion . $texto_estado ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <div id="loadingIndicator" style="display:none;" class="text-center py-3">
            <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
            <small class="d-block mt-1 text-muted">Cargando...</small>
        </div>
        
        <?php if ($evento_info): ?>
        
        <!-- Carrito de Compras (siempre visible) -->
        <div class="carrito-simple" style="background: var(--bg-primary); border-radius: 8px; padding: 10px; margin-bottom: 10px;">
            <h6 style="margin: 0 0 8px 0; color: #0066ff; font-size: 0.85rem;"><i class="bi bi-cart3"></i> Carrito</h6>
            <div id="carritoItems" style="max-height: 220px; overflow-y: auto; margin-bottom: 8px; scrollbar-width: thin;">
                <div class="carrito-vacio" style="font-size: 0.8rem; padding: 30px 10px; text-align: center; color: #86868b;">Selecciona asientos en el mapa</div>
            </div>
            <div style="background: linear-gradient(135deg, #0066ff 0%, #0052cc 100%); padding: 12px; border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: white; font-weight: 600; font-size: 0.9rem;"><span id="statAsientos">0</span> asientos</span>
                    <span style="color: white; font-weight: 700; font-size: 1.1rem;">Total: <span id="totalCompra">$0</span></span>
                </div>
            </div>
        </div>
        
        <!-- Botón de Pago Principal -->
        <button id="btnPagar" class="btn w-100 mb-2" onclick="procesarPago()" disabled style="background: linear-gradient(135deg, #32d74b 0%, #1f9d3c 100%); color: white; padding: 16px 20px; font-size: 1.1rem; font-weight: 700; border: 2px solid #28a745; border-radius: 10px; box-shadow: 0 6px 20px rgba(50, 215, 75, 0.4); text-transform: uppercase; letter-spacing: 1px;">
            <i class="bi bi-credit-card-fill"></i> Procesar Pago
        </button>
        
        <!-- Acciones Rápidas -->
        <div class="acciones-rapidas" style="display: flex; flex-direction: column; gap: 6px;">
            <button class="btn btn-primary btn-sm w-100" onclick="abrirBuscadorBoletos()" style="font-size: 0.8rem; padding: 8px;">
                <i class="bi bi-search"></i> Buscar Boletos
            </button>
            
            <button class="btn btn-warning btn-sm w-100" onclick="verCategorias()" style="font-size: 0.8rem; padding: 8px;">
                <i class="bi bi-palette"></i> Categorías
            </button>
            
            <button class="btn btn-outline-secondary btn-sm w-100" onclick="limpiarSeleccion()" style="font-size: 0.8rem; padding: 8px;">
                <i class="bi bi-arrow-counterclockwise"></i> Limpiar Selección
            </button>
            

        </div>

        <!-- Resumen de Asientos Disponibles/Ocupados -->
        <div id="resumenAsientos" class="mt-3" style="border-radius: 8px; padding: 10px 12px; background: rgba(15,23,42,0.95); border: 1px solid rgba(148,163,184,0.4);">
            <div style="display:flex; justify-content: space-between; align-items: center; font-size: 0.8rem; color: #e5e7eb;">
                <div>
                    <div style="font-weight:600; text-transform: uppercase; letter-spacing: .05em; font-size: 0.7rem; color:#9ca3af;">Asientos</div>
                    <div>
                        <span style="color:#22c55e; font-weight:600;">Disponibles:</span>
                        <span id="labelAsientosDisponibles">-</span>
                    </div>
                    <div>
                        <span style="color:#f97316; font-weight:600;">Ocupados:</span>
                        <span id="labelAsientosOcupados">-</span>
                    </div>
                </div>
                <div style="text-align:right; font-size: 0.75rem;">
                    <div style="color:#9ca3af;">Total vendibles</div>
                    <div id="labelAsientosTotales" style="font-weight:600; color:#e5e7eb;">-</div>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>
    </div>
</div>


<!-- Modal Buscador de Boletos (NUEVO) -->
<div class="modal fade" id="modalBuscador" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-search"></i> Buscador de Boletos</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        
        <!-- Filtros -->
        <div class="buscador-filtros">
            <div class="buscador-search-bar">
                <i class="bi bi-search"></i>
                <input type="text" id="buscInput" class="buscador-input" placeholder="Buscar por código, asiento..." onkeyup="if(event.key === 'Enter') realizarBusqueda()">
            </div>
            
            <div class="filtros-grid">
                <div class="filtro-group">
                    <label>Evento</label>
                    <select id="buscEvento" class="filtro-select" onchange="actualizarFuncionesFiltro(); realizarBusqueda();">
                        <option value="">Seleccione Evento...</option>
                        <?php foreach ($eventos_lista as $ev): ?>
                            <option value="<?= $ev['id_evento'] ?>" <?= ($id_evento_seleccionado == $ev['id_evento']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ev['titulo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filtro-group">
                    <label>Función</label>
                    <select id="buscFuncion" class="filtro-select" onchange="realizarBusqueda()">
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="filtro-group" style="display: flex; align-items: end;">
                     <button class="btn btn-primary w-100" onclick="iniciarEscanerSimple()">
                        <i class="bi bi-qr-code-scan"></i> Escanear
                     </button>
                </div>
            </div>
        </div>

        <!-- Resultados -->
        <div class="buscador-resultados" id="buscadorResultados">
             <!-- Tabla dinámica -->
             <div class="text-center py-5 text-muted">
                 <i class="bi bi-search" style="font-size: 2rem;"></i>
                 <p class="mt-2">Realice una búsqueda o escanee un boleto</p>
             </div>
        </div>
        
        <!-- Overlay Escáner -->
        <div id="scannerOverlay" class="qr-scanner-overlay">
             <div style="position: absolute; top: 20px; right: 20px; z-index: 60;">
                 <button class="btn btn-danger btn-sm rounded-circle" onclick="detenerEscanerSimple()"><i class="bi bi-x-lg"></i></button>
             </div>
             <div id="qr-reader-simple" style="width: 100%; max-width: 500px;"></div>
             <p class="text-white mt-3">Escanee el código QR del boleto</p>
        </div>

      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
    // --- LÓGICA DEL BUSCADOR DE BOLETOS (NUEVO) ---
    
    let buscadorModal;
    let qrScannerSimple = null;
    let funcionesPorEvento = [];

    document.addEventListener('DOMContentLoaded', function() {
        buscadorModal = new bootstrap.Modal(document.getElementById('modalBuscador'));
        
        // Cargar funciones para el filtro cuando se carga la página (si hay evento seleccionado)
        if (EVENTO_SELECCIONADO) {
            cargarFuncionesParaFiltro(EVENTO_SELECCIONADO);
        }
    });

    function abrirBuscadorBoletos() {
        buscadorModal.show();
        // Reset
        document.getElementById('buscInput').value = '';
        
        // Si hay un evento seleccionado GLOBALMENTE en el POS
        if (typeof EVENTO_SELECCIONADO !== 'undefined' && EVENTO_SELECCIONADO) {
            const selectEvento = document.getElementById('buscEvento');
            selectEvento.value = EVENTO_SELECCIONADO;
            selectEvento.disabled = true; // Bloquear selección
            
            // Cargar funciones y buscar automáticamente
            cargarFuncionesParaFiltro(EVENTO_SELECCIONADO);
            // La búsqueda se hará dentro de cargarFuncionesParaFiltro al terminar o explícitamente aquí
            // (Mejor esperar a que carguen funciones para no hacer race condition, 
            // pero para rapidez en UI, podemos lanzar búsqueda de "todas las funciones" de ese evento ya)
            realizarBusqueda();
        } else {
            // Si es un POS "libre" (no debería pasar según diseño actual pero por si acaso)
            document.getElementById('buscEvento').disabled = false;
        }
    }

    function cargarFuncionesParaFiltro(idEvento) {
        fetch('obtener_funciones.php?id_evento=' + idEvento)
            .then(res => res.json())
            .then(data => {
                const select = document.getElementById('buscFuncion');
                select.innerHTML = '<option value="">Todas</option>';
                
                if(data.success && data.funciones) {
                    data.funciones.forEach(f => {
                         const opt = document.createElement('option');
                         opt.value = f.id_funcion;
                         opt.textContent = f.texto;
                         select.appendChild(opt);
                    });
                }
            });
    }

    function actualizarFuncionesFiltro() {
        const idEvento = document.getElementById('buscEvento').value;
        if(idEvento) {
            cargarFuncionesParaFiltro(idEvento);
        } else {
             document.getElementById('buscFuncion').innerHTML = '<option value="">Todas</option>';
        }
    }

    function realizarBusqueda() {
        const idEvento = document.getElementById('buscEvento').value;
        const idFuncion = document.getElementById('buscFuncion').value;
        const query = document.getElementById('buscInput').value;
        const container = document.getElementById('buscadorResultados');
        
        container.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
        
        const params = new URLSearchParams({
            action: 'buscar',
            id_evento: idEvento,
            id_funcion: idFuncion,
            q: query
        });

        fetch('api_buscador.php?' + params.toString())
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    renderizarResultados(data.data);
                } else {
                    container.innerHTML = `<div class="p-4 text-center text-danger">${data.error}</div>`;
                }
            })
            .catch(err => {
                container.innerHTML = `<div class="p-4 text-center text-danger">Error de conexión</div>`;
            });
    }

    function renderizarResultados(boletos) {
        const container = document.getElementById('buscadorResultados');
        
        if(boletos.length === 0) {
            container.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                    <p class="mt-2">No se encontraron boletos</p>
                </div>`;
            return;
        }

        let html = `
            <table class="table-buscador">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Asiento</th>
                        <th>Función</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
        `;

        boletos.forEach(b => {
             let badgeClass = 'activo';
             if(b.estatus == 0) badgeClass = 'usado';
             if(b.estatus == 2) badgeClass = 'cancelado';
             
             html += `
                <tr>
                    <td><strong class="text-white">${b.codigo_unico}</strong></td>
                    <td>${b.codigo_asiento}</td>
                    <td>${b.funcion_fecha_fmt}</td>
                    <td><span class="badge-status ${badgeClass}">${b.estado_texto}</span></td>
                    <td class="text-end">
                        <button class="btn-action-sm btn-verify" onclick="accionReimprimir('${b.codigo_unico}')" title="Reimprimir Boleto" style="background: rgba(245, 158, 11, 0.2); color: #fbbf24;">
                            <i class="bi bi-printer"></i>
                        </button>
                        ${b.estatus == 1 ? `
                        <button class="btn-action-sm btn-verify" onclick="accionVerificar('${b.codigo_unico}')" title="Verificar Entrada">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <button class="btn-action-sm btn-cancel-ticket" onclick="accionCancelar('${b.codigo_unico}', '${b.codigo_asiento}')" title="Cancelar/Devolver">
                            <i class="bi bi-trash"></i>
                        </button>
                        ` : ''}
                    </td>
                </tr>
             `;
        });
        
        // Guardar datos actuales para uso local (reimpresión)
        window.boletosResultados = boletos;

        html += '</tbody></table>';
        container.innerHTML = html;
    }

    // --- ACCIONES DE VALIDACIÓN ---
    function accionVerificar(codigo) {
        if(!confirm('¿Confirmar entrada para el boleto ' + codigo + '?')) return;
        
        fetch('confirmar_entrada.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ codigo_unico: codigo })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                notify.success('Entrada confirmada');
                realizarBusqueda(); // Recargar tabla
            } else {
                notify.error(data.message || 'Error al validar');
            }
        });
    }

    function accionReimprimir(codigo) {
        if (!window.boletosResultados) return;
        const boleto = window.boletosResultados.find(b => b.codigo_unico === codigo);
        if (!boleto) return;
        
        // Construir objeto para QZ (Adaptador)
        // Necesitamos generar el QR en base64 para QZ si no viene del backend (en api_buscador no viene la imagen)
        // Usaremos el generador QR de la librería
        
        // 1. Generar QR dummy temporales o usar librería si es posible obtener DataURI
        // Como 'html5-qrcode' es lector, usamos una librería de generación si existe, o un servicio. 
        // PERO 'qz_interface.js' espera boleto.qr_image.
        // Simularemos con una API de QR pública O mejor, un canvas invisible si tenemos una lib
        // Simplificación: Usaremos una API publica de QR para el base64 o intentaremos que QZ lo maneje si modificamos
        // Para robustez y dado que NO tengo librería de generación QR JS cargada (solo lector), usaré una API rápida
        // OJO: qz_interface espera base64 completo. 
        
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${boleto.codigo_unico}`;
        
        // Convertir URL a Base64 (fetch)
        notify.info('Preparando impresión...');
        
        fetch(qrUrl)
            .then(r => r.blob())
            .then(blob => {
                const reader = new FileReader();
                reader.onloadend = function() {
                    const base64data = reader.result;
                    
                    const boletoImprimir = {
                        evento: boleto.evento_titulo,
                        fecha: boleto.fecha_simple, // dd/mm/yyyy
                        hora: boleto.hora_simple,   // HH:mm hrs
                        asiento: boleto.codigo_asiento,
                        categoria: boleto.nombre_categoria,
                        precio: boleto.precio_final,
                        codigo_unico: boleto.codigo_unico,
                        vendedor: boleto.vendedor_nombre,
                        qr_image: base64data,
                        logo_image: null // Opcional, qz tiene fallback
                    };
                    
                    printTicketsQZ([boletoImprimir], 'REIMPRESION', 'default')
                        .then(res => {
                            if(res.success) notify.success('Enviado a impresora');
                            else notify.error(res.message);
                        });
                }
                reader.readAsDataURL(blob);
            })
            .catch(e => {
                console.error(e);
                notify.error('Error generando QR para impresión');
            });
    }

    function accionCancelar(codigo, asiento) {
         if(!confirm('¿CANCELAR boleto ' + codigo + ' (Asiento: ' + asiento + ')? Estás seguro?')) return;

         fetch('cancelar_boleto.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ codigo_unico: codigo })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                notify.success('Boleto cancelado');
                realizarBusqueda(); // Recargar tabla
                // Actualizar mapa si es el evento actual
                if (typeof cargarAsientosVendidos === 'function') cargarAsientosVendidos();
            } else {
                notify.error(data.message || 'Error al cancelar');
            }
        });
    }

    // --- ESCÁNER ---
    function iniciarEscanerSimple() {
        const overlay = document.getElementById('scannerOverlay');
        overlay.classList.add('active');
        
        qrScannerSimple = new Html5Qrcode("qr-reader-simple");
        qrScannerSimple.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: 250 },
            (decodedText) => {
                // Success
                detenerEscanerSimple();
                document.getElementById('buscInput').value = decodedText;
                realizarBusqueda();
                notify.info('Código escaneado: ' + decodedText);
            },
            (errorMessage) => {
                // Parsing error, ignore
            }
        ).catch(err => {
            console.error("Error iniciando cámara", err);
            notify.error("No se pudo acceder a la cámara");
            overlay.classList.remove('active');
        });
    }

    function detenerEscanerSimple() {
        if(qrScannerSimple) {
            qrScannerSimple.stop().then(() => {
                qrScannerSimple.clear();
                document.getElementById('scannerOverlay').classList.remove('active');
            }).catch(err => console.error(err));
        } else {
            document.getElementById('scannerOverlay').classList.remove('active');
        }
    }

    
    // --- MODIFICADO: Estos datos ahora vienen de la lógica de PHP ---
    const CATEGORIAS_INFO = <?= json_encode($categorias_js, JSON_NUMERIC_CHECK) ?>;
    
    // --- MODIFICADO: Pasamos el ID real de "General" a JavaScript ---
    const DEFAULT_CAT_ID = <?= $id_categoria_general ?? 0 ?>;
    const EVENTO_SELECCIONADO = <?= $id_evento_seleccionado ? (int)$id_evento_seleccionado : 'null' ?>;
    const FUNCION_SELECCIONADA = <?= $id_funcion_seleccionada ? (int)$id_funcion_seleccionada : 'null' ?>;
    const TOTAL_EVENTOS = <?= count($eventos_lista) ?>;
    
    // Variable global para almacenar datos del boleto a cancelar
    let boletoACancelar = null;
    
    // Función para cambiar evento con indicador de carga
    function cambiarEvento(select) {
        const idEvento = select.value;
        if (!idEvento) return;
        
        const loading = document.getElementById('loadingIndicator');
        if (loading) loading.style.display = 'block';
        
        const funcionInput = document.getElementById('inputIdFuncion');
        if (funcionInput) {
            funcionInput.value = '';
        }
        
        select.form.submit();
    }
    
    // Función para seleccionar evento desde la cartelera visual
    function seleccionarEvento(idEvento) {
        if (!idEvento) return;
        
        const loading = document.getElementById('loadingIndicator');
        if (loading) loading.style.display = 'block';
        
        // Redirigir al evento seleccionado
        window.location.href = 'index.php?id_evento=' + idEvento;
    }
    
    function cambiarFuncion(select) {
    const idFuncion = select.value;
    
    console.log('Cambiando a función:', idFuncion);
    
    // Ocultar overlay inmediatamente al seleccionar un horario
    const overlaySinHorario = document.getElementById('overlaySinHorario');
    if (overlaySinHorario) {
        console.log('Ocultando overlay...');
        overlaySinHorario.classList.add('hidden');
        // Remover después de la transición
        setTimeout(() => {
            if (overlaySinHorario.parentNode) {
                overlaySinHorario.remove();
                console.log('Overlay removido del DOM');
            }
        }, 350);
    }
    
    // Si no se seleccionó ninguna función, no hacer nada más
    if (!idFuncion) {
        return;
    }
    
    const loading = document.getElementById('loadingIndicator');
    if (loading) loading.style.display = 'block';
    
    const funcionInput = document.getElementById('inputIdFuncion');
    if (funcionInput) {
        funcionInput.value = idFuncion;
    }

    // Get the current URL and update the id_funcion parameter
    const url = new URL(window.location.href);
    url.searchParams.set('id_funcion', idFuncion);
    
    // Update the URL without reloading the page
    window.history.pushState({}, '', url);

    // Use fetch to update the page content
    fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest' // Add this header to identify AJAX requests
        }
    })
    .then(response => response.text())
    .then(async html => {
        // Create a temporary container to hold the new content
        const temp = document.createElement('div');
        temp.innerHTML = html;
        
        // Update the seat map content
        const newSeatMap = temp.querySelector('.seat-map-content');
        if (newSeatMap) {
            const currentSeatMap = document.querySelector('.seat-map-content');
            if (currentSeatMap) {
                currentSeatMap.innerHTML = newSeatMap.innerHTML;
            }
        }
        
        // Update the function dropdown to maintain the selected value
        const newSelect = temp.querySelector('#selectFuncion');
        if (newSelect) {
            const currentSelect = document.getElementById('selectFuncion');
            if (currentSelect) {
                currentSelect.innerHTML = newSelect.innerHTML;
                currentSelect.value = idFuncion; // Maintain the selected value
            }
        }
        
        // Limpiar carrito y selecciones al cambiar de función
        // (los asientos vendidos pueden cambiar entre funciones)
        if (typeof carrito !== 'undefined' && carrito.length > 0) {
            if (window.TeatroReservas && typeof window.TeatroReservas.liberarTodos === 'function') {
                window.TeatroReservas.liberarTodos();
            }
            document.querySelectorAll('.seat.selected').forEach(seat => {
                seat.classList.remove('selected');
            });
            carrito = [];
            if (typeof actualizarCarrito === 'function') {
                actualizarCarrito();
            }
        }
        
        // IMPORTANTE: Cargar asientos vendidos DESPUÉS de actualizar el DOM
        // y ESPERAR a que termine para marcarlos correctamente
        console.log('Cargando asientos vendidos para la nueva función...');
        if (window.cargarAsientosVendidos) {
            try {
                await cargarAsientosVendidos();
                console.log('Asientos vendidos cargados y marcados');
            } catch (e) {
                console.error('Error cargando asientos vendidos:', e);
            }
        }

        if (typeof TeatroSync !== 'undefined' && TeatroSync.setFuncion) {
            TeatroSync.setFuncion(parseInt(idFuncion, 10));
        }
        if (window.TeatroReservas && typeof window.TeatroReservas.cargarReservasActivas === 'function') {
            window.TeatroReservas.cargarReservasActivas();
        }
        
        // Usar setTimeout para asegurar que el DOM se haya actualizado completamente
        // antes de reinicializar los event listeners
        setTimeout(() => {
            // Re-escalar y centrar el mapa
            if (typeof escalarMapa === 'function') {
                escalarMapa();
            }
            
            // Volver a marcar asientos vendidos (por si acaso)
            if (typeof marcarAsientosVendidos === 'function') {
                marcarAsientosVendidos();
            }
            
            if (window.cargarDescuentos) {
                cargarDescuentos();
            }
            // Reinicializar event listeners de los asientos
            if (window.inicializarEventListenersAsientos) {
                inicializarEventListenersAsientos();
                console.log('Event listeners reinicializados después de cambio de función');
            }
            // Marcar asientos de categoría "No Venta"
            if (typeof marcarAsientosNoVenta === 'function') {
                marcarAsientosNoVenta();
            }
            
            // Sincronizar con visor cliente
            if (typeof enviarFuncion === 'function') {
                enviarFuncion();
            }
            
            // Notificar al usuario
            if (typeof notify !== 'undefined') {
                notify.success('Horario seleccionado. ¡Listo para vender!');
            }
        }, 150);
    })
    .catch(error => {
        console.error('Error al cargar la función:', error);
        // If there's an error, fall back to the original form submission
        select.form.submit();
    })
    .finally(() => {
        if (loading) loading.style.display = 'none';
    });
}


// Add popstate event listener for browser back/forward buttons
window.addEventListener('popstate', function() {
    window.location.reload();
});

// ===== ACTUALIZACIÓN EN TIEMPO REAL DEL SELECT DE FUNCIONES =====
let intervalActualizacionFunciones = null;

function actualizarFuncionesDisponibles() {
    if (!EVENTO_SELECCIONADO) return;
    
    fetch('obtener_funciones.php?id_evento=' + EVENTO_SELECCIONADO)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.funciones) {
                const selectFuncion = document.getElementById('selectFuncion');
                if (!selectFuncion) return;
                
                const funcionActual = selectFuncion.value;
                const funcionesActuales = Array.from(selectFuncion.options).map(opt => opt.value);
                const funcionesNuevas = data.funciones.map(f => f.id_funcion.toString());
                
                // Verificar si hay cambios (incluyendo cambios de estado)
                const hayNuevasFunciones = funcionesNuevas.some(id => !funcionesActuales.includes(id));
                const hayFuncionesEliminadas = funcionesActuales.some(id => !funcionesNuevas.includes(id));
                
                // Verificar si alguna función cambió de estado
                let hayCambioEstado = false;
                data.funciones.forEach(funcion => {
                    const optionActual = selectFuncion.querySelector(`option[value="${funcion.id_funcion}"]`);
                    if (optionActual) {
                        const estadoActual = optionActual.getAttribute('data-estado');
                        if (estadoActual !== funcion.estado.toString()) {
                            hayCambioEstado = true;
                        }
                    }
                });
                
                if (hayNuevasFunciones || hayFuncionesEliminadas || hayCambioEstado) {
                    // Actualizar el select
                    selectFuncion.innerHTML = '';
                    
                    // Agregar opción vacía primero
                    const opcionVacia = document.createElement('option');
                    opcionVacia.value = '';
                    opcionVacia.textContent = 'Seleccionar horario...';
                    selectFuncion.appendChild(opcionVacia);
                    
                    let primeraFuncionActiva = null;
                    
                    data.funciones.forEach(funcion => {
                        const option = document.createElement('option');
                        option.value = funcion.id_funcion;
                        option.setAttribute('data-estado', funcion.estado);
                        
                        // Si la función está vencida (estado = 1)
                        if (funcion.vencida) {
                            option.textContent = funcion.texto + ' (Vencida)';
                            option.disabled = true;
                            option.style.color = '#9ca3af';
                            option.style.backgroundColor = '#f3f4f6';
                        } else {
                            option.textContent = funcion.texto;
                            // Guardar la primera función activa
                            if (!primeraFuncionActiva) {
                                primeraFuncionActiva = funcion.id_funcion;
                            }
                        }
                        
                        if (funcion.id_funcion.toString() === funcionActual) {
                            option.selected = true;
                        }
                        
                        selectFuncion.appendChild(option);
                    });
                    
                    // Solo si había una función seleccionada previamente y ya no está disponible
                    if (funcionActual && funcionActual !== '') {
                        const funcionActualData = data.funciones.find(f => f.id_funcion.toString() === funcionActual);
                        const funcionActualVencida = funcionActualData && funcionActualData.vencida;
                        
                        if ((!funcionesNuevas.includes(funcionActual) || funcionActualVencida) && primeraFuncionActiva) {
                            selectFuncion.value = primeraFuncionActiva;
                            // Notificar al usuario
                            if (typeof notify !== 'undefined') {
                                if (funcionActualVencida) {
                                    notify.warning('La función seleccionada ha vencido. Se ha seleccionado otra función activa.');
                                } else {
                                    notify.warning('La función seleccionada ya no está disponible. Se ha seleccionado otra función.');
                                }
                            }
                            // Cambiar automáticamente a la nueva función
                            cambiarFuncion(selectFuncion);
                        }
                    }
                    
                    console.log('Funciones actualizadas:', data.funciones.length, '(Activas:', data.funciones.filter(f => !f.vencida).length + ')');
                }
            }
        })
        .catch(error => {
            console.error('Error al actualizar funciones:', error);
        });
}

// Iniciar actualización automática cada 30 segundos
function iniciarActualizacionFunciones() {
    if (EVENTO_SELECCIONADO && !intervalActualizacionFunciones) {
        // Primera actualización inmediata
        actualizarFuncionesDisponibles();
        
        // Actualizar cada 30 segundos
        intervalActualizacionFunciones = setInterval(actualizarFuncionesDisponibles, 30000);
        console.log('Actualización automática de funciones iniciada');
    }
}

// Detener actualización automática
function detenerActualizacionFunciones() {
    if (intervalActualizacionFunciones) {
        clearInterval(intervalActualizacionFunciones);
        intervalActualizacionFunciones = null;
        console.log('Actualización automática de funciones detenida');
    }
}

// Iniciar cuando la página carga
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarActualizacionFunciones);
} else {
    iniciarActualizacionFunciones();
}

// Detener cuando la página se descarga
window.addEventListener('beforeunload', detenerActualizacionFunciones);
    
    // Función para abrir el modal de cancelar boleto (redirige al sistema unificado)
    function abrirCancelarBoletoLegacy() {
        // Usar el nuevo sistema unificado
        if (typeof abrirGestionBoletos === 'function') {
            abrirGestionBoletos('cancelar');
        } else {
            // Fallback al modal antiguo
            const modal = new bootstrap.Modal(document.getElementById('modalCancelarBoleto'));
            document.getElementById('inputCodigoBoleto').value = '';
            document.getElementById('infoBoletoACancelar').style.display = 'none';
            document.getElementById('btnConfirmarCancelacion').disabled = true;
            boletoACancelar = null;
            modal.show();
        }
    }
    
    // Función para buscar boleto por código (con debounce para evitar múltiples llamadas)
    let timeoutBusqueda = null;
    document.getElementById('inputCodigoBoleto')?.addEventListener('input', function(e) {
        const codigo = e.target.value.trim();
        
        // Limpiar timeout anterior
        if (timeoutBusqueda) {
            clearTimeout(timeoutBusqueda);
        }
        
        if (codigo.length >= 8) {
            // Esperar 500ms antes de buscar (debounce)
            timeoutBusqueda = setTimeout(() => {
                console.log('Buscando boleto con código:', codigo);
                buscarBoletoPorCodigo(codigo);
            }, 500);
        } else {
            document.getElementById('infoBoletoACancelar').style.display = 'none';
            document.getElementById('btnConfirmarCancelacion').disabled = true;
            boletoACancelar = null;
        }
    });
    
    // Función para buscar boleto en la base de datos
    function buscarBoletoPorCodigo(codigo) {
        console.log('Enviando petición para buscar boleto:', codigo);
        
        fetch('buscar_boleto.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'codigo=' + encodeURIComponent(codigo) + '&id_evento=<?= $id_evento_seleccionado ?>'
        })
        .then(response => {
            console.log('Respuesta recibida:', response.status);
            return response.text();
        })
        .then(text => {
            console.log('Texto de respuesta:', text);
            try {
                const data = JSON.parse(text);
                console.log('Datos parseados:', data);
                
                if (data.success) {
                    mostrarInfoBoletoParaCancelar(data.boleto);
                } else {
                    document.getElementById('infoBoletoACancelar').style.display = 'none';
                    document.getElementById('btnConfirmarCancelacion').disabled = true;
                    boletoACancelar = null;
                    if (data.message && typeof notify !== 'undefined') {
                        notify.error(data.message);
                    }
                }
            } catch (e) {
                console.error('Error al parsear JSON:', e);
                console.error('Texto recibido:', text);
                if (typeof notify !== 'undefined') {
                    notify.error('Error al procesar la respuesta del servidor');
                }
            }
        })
        .catch(error => {
            console.error('Error en la petición:', error);
            if (typeof notify !== 'undefined') {
                notify.error('Error al buscar el boleto');
            }
        });
    }
    
    // Función para mostrar información del boleto para cancelar
    function mostrarInfoBoletoParaCancelar(boleto) {
        boletoACancelar = boleto;
        document.getElementById('cancelarAsiento').textContent = boleto.asiento;
        document.getElementById('cancelarCategoria').textContent = boleto.categoria;
        document.getElementById('cancelarPrecio').textContent = '$' + parseFloat(boleto.precio).toFixed(2);
        document.getElementById('cancelarFecha').textContent = boleto.fecha_compra;
        document.getElementById('infoBoletoACancelar').style.display = 'block';
        document.getElementById('btnConfirmarCancelacion').disabled = false;
    }
    
    // Función para confirmar la cancelación
    function confirmarCancelacion() {
        if (!boletoACancelar) {
            if (typeof notify !== 'undefined') {
                notify.error('No hay boleto seleccionado');
            }
            return;
        }
        
        if (!confirm('¿Está seguro de que desea cancelar este boleto? Esta acción no se puede deshacer.')) {
            return;
        }
        
        const btnConfirmar = document.getElementById('btnConfirmarCancelacion');
        btnConfirmar.disabled = true;
        btnConfirmar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Cancelando...';
        
        fetch('cancelar_boleto.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id_boleto=' + encodeURIComponent(boletoACancelar.id_boleto)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof notify !== 'undefined') {
                    notify.success('Boleto cancelado exitosamente');
                }
                
                // Cerrar el modal
                bootstrap.Modal.getInstance(document.getElementById('modalCancelarBoleto')).hide();
                
                // Liberar el asiento visualmente
                const asientoId = boletoACancelar.asiento;
                
                // Remover del set de asientos vendidos
                if (typeof asientosVendidos !== 'undefined') {
                    asientosVendidos.delete(asientoId);
                }
                
                // Buscar el elemento del asiento en el DOM y quitarle la clase 'vendido'
                const seatElement = document.querySelector(`.seat[data-asiento-id="${asientoId}"]`);
                if (seatElement) {
                    seatElement.classList.remove('vendido');
                    seatElement.style.pointerEvents = 'auto';
                    seatElement.style.cursor = 'pointer';
                    console.log('Asiento liberado visualmente:', asientoId);
                }
                
                // Recargar los asientos vendidos para asegurar sincronización
                setTimeout(() => {
                    if (typeof cargarAsientosVendidos === 'function') {
                        cargarAsientosVendidos();
                        console.log('Asientos vendidos recargados desde el servidor');
                    }
                }, 300);
                
                // Resetear el formulario
                document.getElementById('inputCodigoBoleto').value = '';
                document.getElementById('infoBoletoACancelar').style.display = 'none';
                boletoACancelar = null;
            } else {
                if (typeof notify !== 'undefined') {
                    notify.error(data.message || 'Error al cancelar el boleto');
                }
                btnConfirmar.disabled = false;
                btnConfirmar.innerHTML = '<i class="bi bi-trash"></i> Confirmar Cancelación';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof notify !== 'undefined') {
                notify.error('Error al cancelar el boleto');
            }
            btnConfirmar.disabled = false;
            btnConfirmar.innerHTML = '<i class="bi bi-trash"></i> Confirmar Cancelación';
        });
    }
    
    // Función para escanear QR para cancelar
    function escanearParaCancelar() {
        // Cerrar el modal de cancelación
        const modalCancelar = bootstrap.Modal.getInstance(document.getElementById('modalCancelarBoleto'));
        if (modalCancelar) {
            modalCancelar.hide();
        }
        
        // Esperar a que se cierre el modal antes de abrir el escáner
        setTimeout(() => {
            // Configurar el escáner para modo cancelación
            window.modoCancelacion = true;
            console.log('Modo cancelación activado:', window.modoCancelacion);
            
            // Usar la función existente para abrir el escáner
            if (typeof abrirEscanerQR === 'function') {
                abrirEscanerQR();
            } else {
                console.error('abrirEscanerQR no está definida');
            }
        }, 300);
    }
    
    // Función para escalar el mapa de asientos automáticamente para que quepa en el área visible
    let resizeTimeout;
    function escalarMapa() {
        const wrapper = document.querySelector('.seat-map-wrapper');
        const content = document.getElementById('seatMapContent');
        
        if (!wrapper || !content) return;

        // Dimensiones visibles del contenedor (restando un pequeño margen interno)
        const wrapperWidth = Math.max(wrapper.clientWidth - 40, 0);
        const wrapperHeight = Math.max(wrapper.clientHeight - 40, 0);

        // Dimensiones reales del contenido (sin escala)
        const contentWidth = content.scrollWidth;
        const contentHeight = content.scrollHeight;

        if (!contentWidth || !contentHeight || !wrapperWidth || !wrapperHeight) {
            content.style.transform = 'scale(1)';
            return;
        }

        // Calcular factor de escala máximo que permite que quepa en ancho y alto
        const scaleX = wrapperWidth / contentWidth;
        const scaleY = wrapperHeight / contentHeight;
        let scale = Math.min(scaleX, scaleY, 1);

        // Proteger contra valores inválidos
        if (!isFinite(scale) || scale <= 0) {
            scale = 1;
        }

        content.style.transform = `scale(${scale})`;
    }
    
    function escalarMapaDebounced() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(escalarMapa, 150);
    }
    
    // Ejecutar al cargar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', escalarMapa);
    } else {
        escalarMapa();
    }
    
    window.addEventListener('resize', escalarMapaDebounced);
    
    // Función para ocultar/mostrar paneles laterales
    let panelsHidden = false;
    
    function togglePanels() {
        const controlsPanel = document.querySelector('.controls-panel');
        const seatMapWrapper = document.querySelector('.seat-map-wrapper');
        const toggleIcon = document.getElementById('toggleIcon');
        
        panelsHidden = !panelsHidden;
        
        if (panelsHidden) {
            // Ocultar panel de controles
            if (controlsPanel) controlsPanel.classList.add('hidden');
            if (seatMapWrapper) seatMapWrapper.classList.add('fullscreen');
            if (toggleIcon) {
                toggleIcon.classList.remove('bi-arrows-angle-expand');
                toggleIcon.classList.add('bi-arrows-angle-contract');
            }
        } else {
            // Mostrar panel de controles
            if (controlsPanel) controlsPanel.classList.remove('hidden');
            if (seatMapWrapper) seatMapWrapper.classList.remove('fullscreen');
            if (toggleIcon) {
                toggleIcon.classList.remove('bi-arrows-angle-contract');
                toggleIcon.classList.add('bi-arrows-angle-expand');
            }
        }
        
        // Re-escalar el mapa después de cambiar la visibilidad
        setTimeout(escalarMapa, 100);
    }
    
    // ==================================================================
    // ACTUALIZACIÓN AUTOMÁTICA DEL SELECTOR DE EVENTOS
    // ==================================================================
    // La lógica de sincronización está en js/evento-sync.js
</script>

<!-- Biblioteca para escanear QR con cámara -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script src="js/notifications.js"></script>
<script src="js/evento-sync.js"></script>
<script>
// Precios por tipo de boleto (cargados desde la BD)
window.PRECIOS_TIPO_BOLETO = <?= json_encode($precios_tipo_boleto) ?>;
window.CONFIG_TIPOS_BOLETO = <?= json_encode($config_tipos_boleto, JSON_UNESCAPED_UNICODE) ?>;
// URL para regresar (empleado vs admin)
window.URL_REGRESAR = '<?= $url_regresar ?>';
window.URL_PANEL = '<?= $url_panel ?>';

// Inactividad y atajo Alt+F5 dentro del Punto de Venta
(function() {
    const INACTIVITY_LIMIT = 5 * 60 * 1000; // 5 minutos
    let inactivityTimer;

    function resetInactividad() {
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(function() {
            window.location.href = '../logout.php?motivo=inactividad';
        }, INACTIVITY_LIMIT);
    }

    // Atajo Alt+F5: si está embebido, pide al padre ir a Punto de Venta;
    // si no está embebido, actúa como el botón "Cambiar evento" (usa URL_REGRESAR)
    document.addEventListener('keydown', function(e) {
        if (e.altKey && e.key === 'F5') {
            e.preventDefault();
            if (window.parent && window.parent !== window) {
                try {
                    window.parent.postMessage({ tipo: 'ir_punto_venta' }, '*');
                } catch (err) {
                    // Fallback según rol (empleado → su panel, admin → panel principal)
                    window.parent.location.href = window.URL_PANEL || '../index.php';
                }
            } else {
                // Cuando el punto de venta está abierto directo (empleado o admin)
                // usamos la misma URL que el botón de "Cambiar evento"
                if (typeof window.URL_REGRESAR !== 'undefined' && window.URL_REGRESAR) {
                    window.location.href = window.URL_REGRESAR;
                } else {
                    window.location.href = 'index.php';
                }
            }
        }
    });

    ['click', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach(function(evt) {
        document.addEventListener(evt, resetInactividad, { passive: true });
    });

    resetInactividad();
})();
</script>
<script src="js/carrito.js?v=27"></script>

<script src="js/carrito-patch.js"></script>
<script src="js/descuentos-modal.js"></script>
<script src="js/escaner_qr.js?v=4"></script>
<script src="js/menu-mejoras.js?v=1"></script>
<script src="js/seleccion-multiple.js?v=1"></script>
<script src="js/sync-sender.js?v=2"></script>
<script src="js/reservas-tiempo-real.js?v=2"></script>

<!-- Forzar carga de asientos vendidos al inicio -->
<script>
// Esperar a que todo esté cargado y forzar la visualización de asientos vendidos
window.addEventListener('load', function() {
    console.log('Página completamente cargada - forzando carga de asientos vendidos');
    
    // Dar un pequeño delay para asegurar que todo esté inicializado
    setTimeout(function() {
        if (typeof cargarAsientosVendidos === 'function') {
            console.log('Ejecutando cargarAsientosVendidos...');
            cargarAsientosVendidos();
        }
        if (typeof marcarAsientosVendidos === 'function') {
            marcarAsientosVendidos();
        }
    }, 300);
});
</script>

<script>
// Función para abrir el visor cliente en una nueva ventana
function abrirVisorCliente() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id_evento') || 0;
    // Abrir ventana emergente sin barras de navegación
    window.open(`visor_cliente.php?id_evento=${id}`, 'VisorCliente', 'width=1200,height=800,menubar=no,toolbar=no');
}

// Función para seleccionar evento desde cartelera fullscreen
function seleccionarEvento(idEvento) {
    if (!idEvento) return;
    
    // Enviar notificación al visor cliente antes de redirigir
    if (typeof enviarSeleccionEvento === 'function') {
        // Obtener título del evento si está disponible
        const eventoCard = document.querySelector(`.evento-card-full[onclick*="${idEvento}"]`);
        const titulo = eventoCard?.querySelector('.titulo')?.textContent || 'Cargando...';
        enviarSeleccionEvento(idEvento, titulo);
    }
    
    // Pequeño delay para que el mensaje llegue al visor
    setTimeout(() => {
        window.location.href = 'index.php?id_evento=' + idEvento;
    }, 100);
}

// Auto-escalado deshabilitado - el mapa usa scroll si no cabe
// function escalarMapa() { ... }
</script>

<?php endif; // Fin de la vista de ventas (con evento) ?>

<!-- Sistema de Auto-Actualización en Tiempo Real -->
<script src="js/teatro-sync.js?v=2"></script>
<script>
// Configurar TeatroSync con el evento y función actual
if (typeof TeatroSync !== 'undefined') {
    TeatroSync.init({
        eventoId: <?= $id_evento_seleccionado ?: 'null' ?>,
        funcionId: <?= $id_funcion_seleccionada ?: 'null' ?>,
        autoReload: false
    });
}
</script>

</body>
</html>