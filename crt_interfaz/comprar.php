<?php
/**
 * Paso 1 — Selección de butacas (mapa real) + mini resumen.
 * Paso 2 — checkout_online.php (datos + pago).
 */
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/precio_helper.php';
require_once __DIR__ . '/../sync/reservas_helper.php';

$id_evento = isset($_GET['id_evento']) ? (int) $_GET['id_evento'] : 0;
$id_funcion = isset($_GET['id_funcion']) ? (int) $_GET['id_funcion'] : 0;

$evento = null;
$funcion = null;
$mapa = [];
$colores = [];
$info_cat = [];
$tipos = ['adulto'];
$error = null;
$texto_funcion = '';

if ($id_evento <= 0 || $id_funcion <= 0) {
    $error = 'Seleccione un evento y una función desde la cartelera.';
} else {
    $stmt = $conn->prepare('SELECT id_evento, titulo, mapa_json, imagen, tipo FROM evento WHERE id_evento = ? AND finalizado = 0');
    $stmt->bind_param('i', $id_evento);
    $stmt->execute();
    $evento = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$evento) {
        $error = 'Evento no disponible.';
    } else {
        $mapa = json_decode($evento['mapa_json'] ?? '{}', true) ?: [];
        $stmt = $conn->prepare('SELECT id_funcion, fecha_hora FROM funciones WHERE id_funcion = ? AND id_evento = ?');
        $stmt->bind_param('ii', $id_funcion, $id_evento);
        $stmt->execute();
        $funcion = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$funcion) {
            $error = 'Función no válida.';
        } else {
            foreach (obtener_categorias_evento_completas($conn, $id_evento) as $c) {
                $colores[$c['id_categoria']] = $c['color'];
                $info_cat[$c['id_categoria']] = $c;
            }
            $tipos = array_values(array_filter(
                tipos_boleto_venta_evento($conn, $id_evento),
                static fn($t) => $t !== 'cortesia'
            ));
            if (!$tipos) {
                $tipos = ['adulto'];
            }

            $fecha = new DateTime($funcion['fecha_hora']);
            $dias = ['Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado','Sunday'=>'Domingo'];
            $meses = ['January'=>'Enero','February'=>'Febrero','March'=>'Marzo','April'=>'Abril','May'=>'Mayo','June'=>'Junio','July'=>'Julio','August'=>'Agosto','September'=>'Septiembre','October'=>'Octubre','November'=>'Noviembre','December'=>'Diciembre'];
            $texto_funcion = strtr($fecha->format('l, d \d\e F \d\e Y'), $dias + $meses) . ' · ' . $fecha->format('h:i A');
        }
    }
}

$disp = ['vendidos' => [], 'reservados' => []];
if (!$error) {
    // Excluir holds de esta misma sesión (cookie) para no mostrarlos como “Apartado”
    $sidCookie = isset($_COOKIE['teatro_sid']) ? trim((string) $_COOKIE['teatro_sid']) : '';
    if ($sidCookie !== '' && !preg_match('/^[a-zA-Z0-9_.-]{8,80}$/', $sidCookie)) {
        $sidCookie = '';
    }
    $disp = obtenerDisponibilidadFuncion($id_evento, $id_funcion, $sidCookie !== '' ? $sidCookie : null, $conn);
}

$etiquetas = ETIQUETAS_TIPO_BOLETO;
$appRoot = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\');
if ($appRoot === '/' || $appRoot === '\\') {
    $appRoot = '';
}

function h($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function renderSeatBuy($codigo, $mapa, $vendidos, $reservados, $colores, $info_cat, $idCatDefault = 0, $colorDefault = '#cbd5e1') {
    $idCat = isset($mapa[$codigo]) ? (int) $mapa[$codigo] : $idCatDefault;
    $cat = ($idCat && isset($info_cat[$idCat])) ? $info_cat[$idCat] : null;
    $noVenta = $cat && es_categoria_no_venta($cat['nombre_categoria']);
    $vendido = in_array($codigo, $vendidos, true);
    $reservado = !$vendido && in_array($codigo, $reservados, true);
    $color = $cat['color'] ?? ($colores[$idCat] ?? $colorDefault);
    $clase = 'seat';
    $disabled = false;
    $title = $codigo;
    if ($cat) {
        $title .= ' | ' . $cat['nombre_categoria'] . ' | $' . number_format((float) $cat['precio'], 2);
    }
    if ($vendido) {
        $clase .= ' vendido';
        $disabled = true;
        $title = $codigo . ' | Vendido';
    } elseif ($reservado) {
        $clase .= ' reservado';
        $disabled = true;
        $title = $codigo . ' | Apartado';
    } elseif ($noVenta) {
        $clase .= ' no-venta';
        $disabled = true;
        $title = $codigo . ' | No disponible';
    }
    $style = ($vendido || $reservado || $noVenta) ? '' : 'background-color:' . h($color) . ';';
    $dataCat = $idCat ?: 0;
    $precio = $cat ? (float) $cat['precio'] : 0;
    $disAttr = $disabled ? ' aria-disabled="true" tabindex="-1"' : ' tabindex="0"';
    $dataColor = ($vendido || $reservado || $noVenta) ? '' : ' data-color="' . h($color) . '"';
    return '<button type="button" class="' . h($clase) . '" data-asiento="' . h($codigo) . '" data-categoria="' . $dataCat . '" data-precio="' . h((string) $precio) . '"'
        . $dataColor . $disAttr . ' title="' . h($title) . '" style="' . $style . '">' . h($codigo) . '</button>';
}

$id_categoria_general = 0;
$color_default = '#cbd5e1';
if (!$error && $info_cat) {
    foreach ($info_cat as $c) {
        if (strcasecmp($c['nombre_categoria'], 'General') === 0) {
            $id_categoria_general = (int) $c['id_categoria'];
            $color_default = $c['color'] ?: $color_default;
            break;
        }
    }
    if (!$id_categoria_general) {
        $first = reset($info_cat);
        $id_categoria_general = (int) $first['id_categoria'];
        $color_default = $first['color'] ?: $color_default;
    }
}

$vendidos = $disp['vendidos'] ?? [];
$reservados = $disp['reservados'] ?? [];
$tipo_evento = isset($evento['tipo']) ? (int) $evento['tipo'] : 1;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Seleccionar asientos<?= $evento ? ' — ' . h($evento['titulo']) : '' ?></title>
<link rel="icon" href="imagenes_teatro/nat.png" type="image/png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root { --text:#e5e7eb; --accent:#2563eb; }
body {
  margin:0; min-height:100vh; color:var(--text);
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
  background-image:url('imagenes_teatro/TeatroNoche1.jpg');
  background-size:cover; background-position:center; background-attachment:fixed;
  display:flex; flex-direction:column;
}
.header-simple {
  display:flex; justify-content:space-between; align-items:center;
  padding:18px 24px 20px;
  background:linear-gradient(180deg, rgba(15,23,42,.97) 0%, rgba(15,23,42,.88) 100%);
  border-bottom:1px solid rgba(148,163,184,.28);
  box-shadow:0 8px 28px rgba(0,0,0,.35);
  gap:16px; flex-wrap:wrap;
}
.header-simple .header-copy { min-width:0; flex:1; }
.header-simple .eyebrow {
  display:inline-block; margin:0 0 6px;
  font-size:.72rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase;
  color:#fbbf24;
}
.header-simple h1 {
  margin:0; font-weight:800; color:#fff;
  font-size:clamp(1.55rem, 3.2vw, 2.15rem);
  letter-spacing:.04em; line-height:1.15; text-wrap:balance;
  text-shadow:0 2px 18px rgba(0,0,0,.45);
}
.header-simple .meta {
  margin-top:8px; color:#e2e8f0; font-size:clamp(.95rem, 1.6vw, 1.08rem);
  font-weight:500; letter-spacing:.01em; opacity:.95;
  display:flex; align-items:center; gap:8px; flex-wrap:wrap;
}
.header-simple .meta::before {
  content:""; width:6px; height:6px; border-radius:50%;
  background:#38bdf8; box-shadow:0 0 0 3px rgba(56,189,248,.25); flex-shrink:0;
}
.timer-wrap {
  display:none; align-items:center; gap:10px;
  background:rgba(30,41,59,.95); border:1px solid rgba(148,163,184,.35);
  border-radius:999px; padding:8px 14px;
}
.timer-wrap .lbl { font-size:.8rem; color:#94a3b8; }
.timer-wrap #compraTimer {
  font-variant-numeric:tabular-nums; font-weight:800; font-size:1.15rem; color:#e2e8f0;
}
.timer-wrap #compraTimer.timer-warn { color:#fbbf24; }
.timer-wrap #compraTimer.timer-danger { color:#f87171; animation:pulse .8s ease infinite; }
@keyframes pulse { 50% { opacity:.65; } }
.map-viewport {
  flex:1; width:100%;
  overflow-x:hidden; overflow-y:auto;
  display:flex; justify-content:center; align-items:flex-start;
  padding:16px 8px 168px;
  background:radial-gradient(circle at top, rgba(15,23,42,.75), rgba(15,23,42,.92));
  box-sizing:border-box;
}
.map-card {
  display:block; box-sizing:border-box;
  padding:16px 12px 28px;
  background:linear-gradient(135deg, rgba(255,255,255,.16), rgba(255,255,255,.06));
  backdrop-filter:blur(18px); border-radius:16px; border:1px solid rgba(255,255,255,.22);
  overflow:hidden; max-width:100%;
  flex-shrink:0;
  visibility:hidden;
}
.map-card.is-fitted { visibility:visible; }
.map-content {
  transform-origin:top left;
  transition:none;
  width:max-content;
  margin:0 auto;
}
.screen {
  background:#334155; color:#fff; padding:10px; text-align:center; border-radius:8px;
  margin:0 auto 28px; font-weight:bold; letter-spacing:2px; width:80%;
}
.seat-row-wrapper { display:flex; align-items:center; justify-content:center; margin-bottom:8px; }
.seats-block { display:flex; gap:6px; }
.row-label { width:40px; text-align:center; font-weight:bold; color:#94a3b8; flex-shrink:0; }
.pasillo { width:30px; flex-shrink:0; }
.pasarela {
  position:absolute; width:80px; top:0; left:50%; transform:translateX(-50%);
  background:#475569; color:#fff; display:flex; align-items:center; justify-content:center;
  border-radius:8px; z-index:5;
}
.pasarela-text { writing-mode:vertical-rl; letter-spacing:5px; font-weight:bold; }
.seat {
  width:40px; height:40px; border:0; border-radius:8px; margin:0;
  font-size:11px; font-weight:600; color:#fff; cursor:pointer;
  display:flex; align-items:center; justify-content:center;
  box-shadow:0 2px 4px rgba(0,0,0,.18); user-select:none; flex-shrink:0;
}
@media (max-width: 900px) {
  .header-simple { padding:12px 14px 14px; }
  .header-simple h1 { font-size:1.35rem; }
  .header-simple .meta { font-size:.85rem; }
  .map-viewport { padding:10px 4px 168px; }
  .map-card { padding:12px 8px 20px; border-radius:12px; }
  .seat { width:44px; height:44px; font-size:12px; border-radius:7px; }
  .seat.selected {
    outline-width: 3px;
    box-shadow: 0 0 0 3px rgba(125, 211, 252, 0.95), 0 4px 12px rgba(37, 99, 235, 0.6);
  }
  .seats-block { gap:5px; }
  .row-label { width:28px; font-size:.75rem; }
  .pasillo { width:18px; }
  .screen { margin-bottom:20px; padding:8px; font-size:.85rem; width:90%; }
}
.seat.selected {
  background: #2563eb !important;
  color: #fff !important;
  outline: 3px solid #7dd3fc;
  box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.55), 0 4px 14px rgba(37, 99, 235, 0.45);
  transform: scale(1.06);
  z-index: 2;
  position: relative;
  font-weight: 800;
}
.seat.vendido, .seat.reservado, .seat.no-venta { cursor:not-allowed; }
.seat.vendido {
  background:repeating-linear-gradient(45deg,#6b7280,#6b7280 10px,#4b5563 10px,#4b5563 20px)!important;
}
.seat.reservado {
  background:repeating-linear-gradient(-45deg,#f59e0b,#f59e0b 10px,#d97706 10px,#d97706 20px)!important;
  color:#fff!important;
}
.seat.no-venta { background:#0f172a!important; color:#64748b; }
.leyenda {
  display:flex; gap:14px; flex-wrap:wrap; align-items:center; justify-content:center;
  padding:8px 16px 0; font-size:.8rem; color:#cbd5e1;
}
.dot { width:12px; height:12px; border-radius:3px; display:inline-block; margin-right:6px; vertical-align:middle; }
.mini-resumen {
  position:fixed; left:0; right:0; bottom:0; z-index:30;
  background:#0f172a; border-top:1px solid rgba(148,163,184,.35);
  box-shadow:0 -8px 24px rgba(0,0,0,.35);
}
.mini-inner {
  max-width:1100px; margin:0 auto; padding:8px 16px 12px;
  display:flex; gap:16px; align-items:center; justify-content:space-between; flex-wrap:wrap;
}
.chips { display:flex; gap:8px; flex-wrap:wrap; align-items:center; max-width:65%; }
.chip {
  background:#1e293b; border:1px solid #334155; border-radius:999px;
  padding:4px 10px; font-size:.85rem; display:inline-flex; gap:6px; align-items:center;
}
.chip button { border:0; background:transparent; color:#f87171; font-size:1rem; line-height:1; padding:0; }
.msg { font-size:.85rem; color:#7dd3fc; min-height:1.2rem; }
</style>
</head>
<body>
<?php if ($error): ?>
  <div class="p-4"><div class="alert alert-warning"><?= h($error) ?></div>
  <a class="btn btn-outline-light btn-sm" href="cartelera_cliente.php" id="btnVolverError">Volver</a></div>
<?php else: ?>
<div class="header-simple">
  <div class="header-copy">
    <div class="eyebrow">Selecciona tus asientos</div>
    <h1><?= h($evento['titulo']) ?></h1>
    <div class="meta"><?= h($texto_funcion) ?></div>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <div class="timer-wrap" id="compraTimerWrap" title="Tiempo para completar tu compra">
      <span class="lbl">Tiempo restante</span>
      <span id="compraTimer">--:--</span>
    </div>
    <a href="cartelera_cliente.php" class="btn btn-outline-light btn-sm" id="btnCerrarMapa">Cerrar</a>
  </div>
</div>

<div class="map-viewport" id="mapaViewport">
  <div class="map-card">
    <div class="map-content" id="mapa">
      <div class="screen"><?= $tipo_evento === 1 ? 'ESCENARIO' : 'ESCENARIO PRINCIPAL' ?></div>

      <?php if ($tipo_evento === 2): ?>
        <div style="position:relative;display:flex;flex-direction:column;">
          <?php for ($fila = 1; $fila <= 10; $fila++):
              $nombre_fila = 'PB' . $fila;
              $numero_en_fila_pb = 1;
          ?>
          <div class="seat-row-wrapper">
            <div class="row-label"><?= h($nombre_fila) ?></div>
            <div class="seats-block">
              <?php for ($i = 1; $i <= 6; $i++):
                  echo renderSeatBuy($nombre_fila . '-' . $numero_en_fila_pb++, $mapa, $vendidos, $reservados, $colores, $info_cat, $id_categoria_general, $color_default);
              endfor; ?>
              <div style="width:80px;flex-shrink:0;"></div>
              <?php for ($i = 1; $i <= 6; $i++):
                  echo renderSeatBuy($nombre_fila . '-' . $numero_en_fila_pb++, $mapa, $vendidos, $reservados, $colores, $info_cat, $id_categoria_general, $color_default);
              endfor; ?>
            </div>
            <div class="row-label"><?= h($nombre_fila) ?></div>
          </div>
          <?php endfor; ?>
          <div class="pasarela" style="height:<?= (40 + 8) * 10 ?>px;"><span class="pasarela-text">PASARELA</span></div>
        </div>
        <hr style="margin:30px 0;border:0;border-top:2px dashed #64748b;">
      <?php endif; ?>

      <?php foreach (range('A', 'O') as $fila): $numero_en_fila = 1; ?>
      <div class="seat-row-wrapper">
        <div class="row-label"><?= h($fila) ?></div>
        <div class="seats-block">
          <?php for ($i = 0; $i < 6; $i++):
              echo renderSeatBuy($fila . $numero_en_fila++, $mapa, $vendidos, $reservados, $colores, $info_cat, $id_categoria_general, $color_default);
          endfor; ?>
          <div class="pasillo"></div>
          <?php for ($i = 0; $i < 14; $i++):
              echo renderSeatBuy($fila . $numero_en_fila++, $mapa, $vendidos, $reservados, $colores, $info_cat, $id_categoria_general, $color_default);
          endfor; ?>
          <div class="pasillo"></div>
          <?php for ($i = 0; $i < 6; $i++):
              echo renderSeatBuy($fila . $numero_en_fila++, $mapa, $vendidos, $reservados, $colores, $info_cat, $id_categoria_general, $color_default);
          endfor; ?>
        </div>
        <div class="row-label"><?= h($fila) ?></div>
      </div>
      <?php endforeach; ?>

      <div class="seat-row-wrapper" style="margin-top:15px;">
        <div class="row-label">P</div>
        <div class="seats-block">
          <?php $n = 1; for ($i = 0; $i < 30; $i++):
              echo renderSeatBuy('P' . $n++, $mapa, $vendidos, $reservados, $colores, $info_cat, $id_categoria_general, $color_default);
          endfor; ?>
        </div>
        <div class="row-label">P</div>
      </div>
    </div>
  </div>
</div>

<div class="mini-resumen">
  <div class="leyenda">
    <span><span class="dot" style="background:#cbd5e1;border:1px solid #94a3b8"></span>Disponible</span>
    <span><span class="dot" style="background:#2563eb;box-shadow:0 0 0 2px #7dd3fc"></span>Seleccionado</span>
    <span><span class="dot" style="background:#f59e0b"></span>Apartado</span>
    <span><span class="dot" style="background:#6b7280"></span>Vendido</span>
  </div>
  <div class="mini-inner">
    <div>
      <div class="fw-semibold mb-1">Tu selección <span id="countSeats">(0)</span></div>
      <div class="chips" id="chips"><span class="text-secondary small">Toca un asiento libre para apartarlo</span></div>
      <div class="msg" id="msg"></div>
    </div>
    <div class="d-flex align-items-center gap-3">
      <div class="text-end">
        <div class="small text-secondary">Total estimado</div>
        <div class="fs-5 fw-bold" id="totalEst">$0.00</div>
      </div>
      <button type="button" class="btn btn-primary px-4" id="btnContinuar" disabled>Continuar</button>
    </div>
  </div>
</div>

<script src="js/compra-timer.js"></script>
<script>
(() => {
  const APP_ROOT = <?= json_encode($appRoot) ?>;
  const ID_EVENTO = <?= (int) $id_evento ?>;
  const ID_FUNCION = <?= (int) $id_funcion ?>;
  const TITULO = <?= json_encode($evento['titulo'], JSON_UNESCAPED_UNICODE) ?>;
  const TEXTO_FUNCION = <?= json_encode($texto_funcion, JSON_UNESCAPED_UNICODE) ?>;
  const TIPOS = <?= json_encode($tipos, JSON_UNESCAPED_UNICODE) ?>;
  const API_RES = APP_ROOT + '/api/online/reservas.php';
  const API_ORD = APP_ROOT + '/api/online/ordenes.php';
  const STORAGE_KEY = 'teatro_checkout_' + ID_EVENTO + '_' + ID_FUNCION;
  const EDITAR = new URLSearchParams(window.location.search).get('editar') === '1';
  let navigatingToCheckout = false;
  let limpiandoSalida = false;

  function sessionId() {
    let s = sessionStorage.getItem('teatro_online_session_id');
    if (!s) {
      s = 'web_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
      sessionStorage.setItem('teatro_online_session_id', s);
    }
    // Cookie para que el PHP no marque nuestros holds como “Apartado”
    try {
      document.cookie = 'teatro_sid=' + encodeURIComponent(s) + '; path=/; SameSite=Lax; max-age=86400';
    } catch (e) {}
    return s;
  }

  function limpiarStorageCheckout() {
    try {
      sessionStorage.removeItem(STORAGE_KEY);
      TeatroCompraTimer.clear();
    } catch (e) {}
  }

  /** Libera holds de esta sesión al abandonar el mapa (no al ir a checkout). */
  function liberarSesionAlSalir() {
    if (navigatingToCheckout || limpiandoSalida) return;
    limpiandoSalida = true;
    limpiarStorageCheckout();
    const body = JSON.stringify({ session_id: sessionId() });
    const url = API_RES + '?action=liberar_sesion';
    try {
      if (navigator.sendBeacon) {
        const blob = new Blob([body], { type: 'application/json' });
        navigator.sendBeacon(url, blob);
      } else {
        fetch(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body,
          keepalive: true,
        }).catch(() => {});
      }
    } catch (e) {}
  }

  function marcarSeleccionado(btn) {
    if (!btn) return;
    btn.classList.remove('reservado');
    btn.classList.add('selected');
    btn.removeAttribute('aria-disabled');
    btn.setAttribute('tabindex', '0');
    btn.style.backgroundColor = '';
    btn.title = (btn.dataset.asiento || '') + ' | Seleccionado';
  }

  function desmarcarSeleccionado(btn) {
    if (!btn) return;
    btn.classList.remove('selected');
    if (btn.dataset.color) {
      btn.style.backgroundColor = btn.dataset.color;
    }
    const codigo = btn.dataset.asiento || '';
    btn.title = codigo;
  }

  function seatByCodigo(codigo) {
    try {
      return document.querySelector('.seat[data-asiento="' + CSS.escape(String(codigo)) + '"]');
    } catch (e) {
      return document.querySelector('.seat[data-asiento="' + String(codigo).replace(/"/g, '') + '"]');
    }
  }

  /** Solo restaura si volvemos desde checkout con ?editar=1 */
  function restaurarSeleccionDesdeStorage() {
    if (!EDITAR) return false;
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY);
      if (!raw) return false;
      const data = JSON.parse(raw);
      if (!data || !Array.isArray(data.asientos) || !data.asientos.length) return false;
      if ((data.id_evento|0) !== ID_EVENTO || (data.id_funcion|0) !== ID_FUNCION) return false;
      data.asientos.forEach((item) => {
        const codigo = item.asiento || item.codigo_asiento;
        if (!codigo) return;
        const btn = seatByCodigo(codigo);
        if (!btn || btn.classList.contains('vendido') || btn.classList.contains('no-venta')) return;
        carrito.set(codigo, {
          asiento: codigo,
          tipo_boleto: item.tipo_boleto || TIPOS[0] || 'adulto',
        });
        marcarSeleccionado(btn);
      });
      return carrito.size > 0;
    } catch (e) {
      return false;
    }
  }

  const carrito = new Map();
  const msg = document.getElementById('msg');
  const chips = document.getElementById('chips');
  const totalEst = document.getElementById('totalEst');
  const countSeats = document.getElementById('countSeats');
  const btnContinuar = document.getElementById('btnContinuar');

  function setMsg(t, isErr) {
    msg.textContent = t || '';
    msg.style.color = isErr ? '#fca5a5' : '#7dd3fc';
  }

  async function api(url, opts) {
    const r = await fetch(url, opts);
    return r.json();
  }

  async function reservar(codigo) {
    return api(API_RES + '?action=reservar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        session_id: sessionId(),
        id_evento: ID_EVENTO,
        id_funcion: ID_FUNCION,
        asientos: [codigo],
      }),
    });
  }

  async function liberar(codigo) {
    return api(API_RES + '?action=liberar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        session_id: sessionId(),
        id_evento: ID_EVENTO,
        id_funcion: ID_FUNCION,
        asientos: [codigo],
      }),
    });
  }

  async function renovarHolds(ttlSeg) {
    const body = {
      session_id: sessionId(),
      id_evento: ID_EVENTO,
      id_funcion: ID_FUNCION,
    };
    if (ttlSeg) body.ttl = ttlSeg;
    const r = await api(API_RES + '?action=renovar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    if (r.success && r.expira_en) {
      TeatroCompraTimer.setExpiresAt(r.expira_en);
    }
    return r;
  }

  async function expirarSesion() {
    if (navigatingToCheckout) return;
    liberarSesionAlSalir();
    window.location.href = 'sesion_expirada.php';
  }

  function renderMini() {
    countSeats.textContent = '(' + carrito.size + ')';
    btnContinuar.disabled = carrito.size === 0;
    if (!carrito.size) {
      chips.innerHTML = '<span class="text-secondary small">Toca un asiento libre para apartarlo</span>';
      totalEst.textContent = '$0.00';
      return;
    }
    let html = '';
    carrito.forEach((item, codigo) => {
      html += `<span class="chip"><strong>${codigo}</strong>
        <button type="button" data-quitar="${codigo}" title="Quitar">&times;</button></span>`;
    });
    chips.innerHTML = html;
    cotizar();
  }

  async function cotizar() {
    const asientos = [...carrito.values()];
    const data = await api(API_ORD + '?action=cotizar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id_evento: ID_EVENTO, asientos }),
    });
    if (!data.success) {
      setMsg(data.error || 'No se pudo cotizar', true);
      return;
    }
    totalEst.textContent = '$' + Number(data.total).toFixed(2);
    setMsg('');
  }

  document.getElementById('mapa').addEventListener('click', async (e) => {
    const btn = e.target.closest('.seat');
    if (!btn || btn.classList.contains('vendido') || btn.classList.contains('reservado') || btn.classList.contains('no-venta')) return;
    const codigo = btn.dataset.asiento;
    if (carrito.has(codigo)) {
      await liberar(codigo);
      carrito.delete(codigo);
      desmarcarSeleccionado(btn);
      renderMini();
      if (carrito.size) await renovarHolds();
      return;
    }
    setMsg('Apartando ' + codigo + '…');
    const r = await reservar(codigo);
    if (!r.success) {
      const why = (r.conflictos && r.conflictos[codigo]) || r.error || 'ocupado';
      setMsg('No se pudo apartar ' + codigo + ' (' + why + ')', true);
      return;
    }
    carrito.set(codigo, { asiento: codigo, tipo_boleto: TIPOS[0] || 'adulto' });
    marcarSeleccionado(btn);
    await renovarHolds();
    setMsg(codigo + ' apartado.');
    renderMini();
  });

  chips.addEventListener('click', async (e) => {
    const q = e.target.getAttribute('data-quitar');
    if (!q) return;
    await liberar(q);
    carrito.delete(q);
    desmarcarSeleccionado(seatByCodigo(q));
    renderMini();
    if (carrito.size) await renovarHolds();
  });

  const CHECKOUT_TTL = 300; // 5 min desde Continuar

  btnContinuar.addEventListener('click', async () => {
    if (!carrito.size) return;
    navigatingToCheckout = true;
    btnContinuar.disabled = true;
    setMsg('Preparando checkout…');

    // Arranca el contador de 5 min al continuar; renueva holds en servidor
    let r = await renovarHolds(CHECKOUT_TTL);
    if (!r || !r.success) {
      navigatingToCheckout = false;
      btnContinuar.disabled = false;
      setMsg('No se pudieron renovar los asientos. Intenta de nuevo.', true);
      return;
    }
    if (!r.renovados) {
      // Holds perdidos: volver a apartar
      const codes = [...carrito.keys()];
      r = await api(API_RES + '?action=reservar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          session_id: sessionId(),
          id_evento: ID_EVENTO,
          id_funcion: ID_FUNCION,
          asientos: codes,
          ttl: CHECKOUT_TTL,
        }),
      });
      if (!r.success) {
        navigatingToCheckout = false;
        btnContinuar.disabled = false;
        setMsg('Los asientos ya no están disponibles. Elige de nuevo.', true);
        return;
      }
    }
    if (r.expira_en) {
      TeatroCompraTimer.setExpiresAt(r.expira_en);
    } else {
      TeatroCompraTimer.startFromNow(CHECKOUT_TTL);
    }

    const payload = {
      id_evento: ID_EVENTO,
      id_funcion: ID_FUNCION,
      session_id: sessionId(),
      titulo: TITULO,
      texto_funcion: TEXTO_FUNCION,
      asientos: [...carrito.values()],
      tipos: TIPOS,
      expires_at: TeatroCompraTimer.getExpiresAt(),
      checkout_started: true,
    };
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
    window.location.href = 'checkout_online.php?id_evento=' + ID_EVENTO + '&id_funcion=' + ID_FUNCION;
  });

  // En el mapa no corre el contador de checkout (empieza al pulsar Continuar)
  TeatroCompraTimer.clear();
  const timerWrap = document.getElementById('compraTimerWrap');
  if (timerWrap) timerWrap.style.display = 'none';

  function ajustarMapa() {
    const viewport = document.getElementById('mapaViewport');
    const card = viewport ? viewport.querySelector('.map-card') : null;
    const content = document.getElementById('mapa');
    if (!viewport || !card || !content) return;

    // Medir tamaño natural (sin escala)
    content.style.transform = 'none';
    card.style.width = 'auto';
    card.style.height = 'auto';

    const naturalW = Math.max(content.scrollWidth, content.offsetWidth);
    const naturalH = Math.max(content.scrollHeight, content.offsetHeight);
    if (naturalW <= 0) return;

    const cs = window.getComputedStyle(card);
    const padX = (parseFloat(cs.paddingLeft) || 0) + (parseFloat(cs.paddingRight) || 0);
    const padY = (parseFloat(cs.paddingTop) || 0) + (parseFloat(cs.paddingBottom) || 0);
    const borderX = (parseFloat(cs.borderLeftWidth) || 0) + (parseFloat(cs.borderRightWidth) || 0);
    const borderY = (parseFloat(cs.borderTopWidth) || 0) + (parseFloat(cs.borderBottomWidth) || 0);

    // Ancho útil del viewport (sin scroll horizontal)
    const availW = Math.max(260, viewport.clientWidth - 8);
    let escala = (availW - padX - borderX) / naturalW;

    // En pantallas anchas no agrandar demasiado; en móvil llenar el ancho
    const maxScale = window.matchMedia('(max-width: 900px)').matches ? 1.35 : 1;
    if (escala > maxScale) escala = maxScale;
    if (escala < 0.2) escala = 0.2;

    content.style.transformOrigin = 'top left';
    content.style.transform = 'scale(' + escala + ')';

    // El layout debe coincidir con el tamaño VISUAL (evita scroll vacío)
    card.style.width = Math.ceil(naturalW * escala + padX + borderX) + 'px';
    card.style.height = Math.ceil(naturalH * escala + padY + borderY) + 'px';
    viewport.scrollLeft = 0;
    card.classList.add('is-fitted');
  }

  window.addEventListener('resize', () => {
    window.clearTimeout(window.__mapaFitT);
    window.__mapaFitT = window.setTimeout(ajustarMapa, 80);
  });
  // Cookie de sesión lo antes posible
  sessionId();

  // Entrada normal (cartelera, etc.): limpiar selección vieja y liberar holds
  // Solo ?editar=1 (desde checkout) restaura la selección
  if (!EDITAR) {
    limpiarStorageCheckout();
    api(API_RES + '?action=liberar_sesion', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ session_id: sessionId() }),
    }).catch(() => {});
  } else if (restaurarSeleccionDesdeStorage()) {
    renovarHolds().catch(() => {});
  }
  renderMini();

  // Al salir del mapa (Cerrar / otra página), liberar asientos
  document.getElementById('btnCerrarMapa')?.addEventListener('click', (e) => {
    e.preventDefault();
    const href = e.currentTarget.getAttribute('href') || 'cartelera_cliente.php';
    liberarSesionAlSalir();
    window.location.href = href;
  });
  window.addEventListener('pagehide', () => {
    if (!navigatingToCheckout) liberarSesionAlSalir();
  });
  // Evitar que el caché del navegador reaparezca con asientos ya elegidos
  window.addEventListener('pageshow', (ev) => {
    if (ev.persisted) {
      window.location.reload();
    }
  });

  // Un solo ajuste al cargar (sin transición / sin “crecer”)
  requestAnimationFrame(() => ajustarMapa());
})();
</script>
<?php endif; ?>
</body>
</html>
