<?php
/**
 * Demo: aviso de orden interrumpida (solo entorno local / mock).
 * Uso:
 *   demo_interrupcion_orden.php
 *   demo_interrupcion_orden.php?codigo=ORD…
 *   demo_interrupcion_orden.php?escenario=fallida
 */
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/ordenes_helper.php';
require_once __DIR__ . '/../includes/pagos/PaymentService.php';

function h($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

$permitido = function_exists('payment_mock_permitido') && payment_mock_permitido();
$env = strtolower((string) (function_exists('teatro_env') ? teatro_env('APP_ENV', 'local') : 'local'));
if (!$permitido && !in_array($env, ['local', 'dev', 'development', ''], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Demo deshabilitada fuera de entorno local/mock.\n";
    exit;
}

asegurarTablasOrdenes($conn);

$codigo = isset($_GET['codigo']) ? trim((string) $_GET['codigo']) : '';
$escenario = isset($_GET['escenario']) ? trim((string) $_GET['escenario']) : 'interrupcion';

$orden = null;
if ($codigo !== '') {
    $orden = obtenerOrdenPorCodigo($conn, $codigo);
}

if (!$orden) {
    $st = $conn->query("
        SELECT codigo_publico FROM ordenes
        ORDER BY id_orden DESC
        LIMIT 1
    ");
    $row = $st ? $st->fetch_assoc() : null;
    if ($row) {
        $codigo = $row['codigo_publico'];
        $orden = obtenerOrdenPorCodigo($conn, $codigo);
    }
}

if (!$codigo) {
    $codigo = 'ORDDEMO' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
}

$mensajes = [
    'interrupcion' => 'Si algo salió mal o la compra se interrumpió, presenta este número en taquilla para aclarar tu caso y recibir tu boleto.',
    'fallida' => 'El pago no se completó. Conserva este número y preséntalo en taquilla si necesitas aclaración.',
    'pendiente' => 'Tu compra está en proceso. Conserva este número; si hay cualquier inconveniente, preséntalo en taquilla.',
    'sin_boletos' => 'Tu pago está confirmado, pero los boletos aún no están listos. Guarda este número y, si no aparecen, preséntalo en taquilla.',
];
$mensaje = $mensajes[$escenario] ?? $mensajes['interrupcion'];
$titulo = 'Guarda tu número de orden';

$appRoot = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\');
if ($appRoot === '/' || $appRoot === '\\') {
    $appRoot = '';
}
$adminUrl = $appRoot . '/admin_interfaz/ordenes_online/index.php';
$ordenUrl = 'orden.php?codigo=' . rawurlencode($codigo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Demo — Aviso de orden</title>
<link rel="icon" href="imagenes_teatro/nat.png" type="image/png">
<style>
:root { --text:#e8e8ea; --muted:#a1a1aa; --stroke:rgba(255,255,255,.14); }
* { box-sizing: border-box; }
body {
  min-height: 100vh; margin: 0; padding: 24px 16px 40px;
  color: var(--text);
  font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif;
  background-image:
    linear-gradient(180deg, rgba(0,0,0,.58), rgba(0,0,0,.82)),
    url('imagenes_teatro/TeatroNoche1.jpg');
  background-size: cover; background-position: center; background-attachment: fixed;
}
.wrap { max-width: 640px; margin: 0 auto; }
.card {
  background: linear-gradient(160deg, rgba(255,255,255,.12), rgba(0,0,0,.35));
  border: 1px solid var(--stroke); border-radius: 18px; padding: 20px 18px;
  backdrop-filter: blur(18px);
}
h1 { margin: 0 0 8px; font-size: 1.25rem; }
.lead { color: var(--muted); margin: 0 0 16px; line-height: 1.45; font-size: .95rem; }
.mono {
  display: inline-block; margin: 8px 0 14px; padding: 10px 12px; border-radius: 10px;
  background: rgba(0,0,0,.4); border: 1px solid var(--stroke);
  font-family: ui-monospace, Consolas, monospace; letter-spacing: .05em; font-weight: 700;
}
.steps { margin: 0; padding-left: 1.2rem; color: #e4e4e7; line-height: 1.55; }
.steps li { margin-bottom: 8px; }
.actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
.btn {
  display: inline-flex; align-items: center; justify-content: center;
  padding: 11px 16px; border-radius: 999px; text-decoration: none; font-weight: 700; font-size: .92rem;
  border: 1px solid rgba(255,255,255,.28);
  background: linear-gradient(160deg, rgba(255,255,255,.92), rgba(220,220,224,.88));
  color: #0a0a0a !important; cursor: pointer;
}
.btn-ghost {
  background: transparent; color: #f4f4f5 !important; border-color: rgba(255,255,255,.2);
}
.badge {
  display: inline-block; font-size: .72rem; padding: 3px 8px; border-radius: 999px;
  background: rgba(120,53,15,.55); color: #fde68a; margin-bottom: 10px;
}
.escenarios { display: flex; flex-wrap: wrap; gap: 8px; margin: 12px 0 4px; }
.escenarios a {
  color: #93c5fd; font-size: .85rem; text-decoration: none;
  border: 1px solid rgba(147,197,253,.25); border-radius: 999px; padding: 6px 10px;
}
.escenarios a.active { background: rgba(147,197,253,.15); }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="badge">SOLO PRUEBAS · local/mock</div>
    <h1>Demo: interrupción de compra</h1>
    <p class="lead">
      Simula lo que ve el cliente si el pago se corta o hay un inconveniente.
      El popup se abre solo. Luego prueba el lado taquilla buscando esa orden en admin.
    </p>

    <div>Número de orden de prueba:</div>
    <div class="mono" id="codigoDemo"><?= h($codigo) ?></div>
    <?php if ($orden): ?>
      <div class="lead" style="margin-top:0">Estado real en BD: <strong><?= h($orden['estado']) ?></strong></div>
    <?php else: ?>
      <div class="lead" style="margin-top:0">Código de demostración (no está en BD). Para taquilla usa una orden real.</div>
    <?php endif; ?>

    <div class="escenarios">
      <a class="<?= $escenario === 'interrupcion' ? 'active' : '' ?>" href="?escenario=interrupcion<?= $orden ? '&codigo=' . rawurlencode($codigo) : '' ?>">Interrupción</a>
      <a class="<?= $escenario === 'fallida' ? 'active' : '' ?>" href="?escenario=fallida<?= $orden ? '&codigo=' . rawurlencode($codigo) : '' ?>">Pago fallido</a>
      <a class="<?= $escenario === 'pendiente' ? 'active' : '' ?>" href="?escenario=pendiente<?= $orden ? '&codigo=' . rawurlencode($codigo) : '' ?>">Pendiente</a>
      <a class="<?= $escenario === 'sin_boletos' ? 'active' : '' ?>" href="?escenario=sin_boletos<?= $orden ? '&codigo=' . rawurlencode($codigo) : '' ?>">Pagada sin boletos</a>
    </div>

    <h2 style="font-size:1rem;margin:18px 0 8px">1) Cliente</h2>
    <ol class="steps">
      <li>Se abre el popup con el número de orden.</li>
      <li>El cliente lo toca para copiarlo.</li>
      <li>Puede ir a “Ver mi orden” o mostrar el número en taquilla.</li>
    </ol>

    <h2 style="font-size:1rem;margin:18px 0 8px">2) Taquilla / admin</h2>
    <ol class="steps">
      <li>Entra a <strong>Admin → Órdenes online</strong>.</li>
      <li>Pega el número <span class="mono" style="padding:2px 6px;font-size:.8rem"><?= h($codigo) ?></span> en el buscador.</li>
      <li>Abre el detalle: estado, asientos, reemitir boletos o reembolsar según el caso.</li>
    </ol>

    <div class="actions">
      <button type="button" class="btn" id="btnMostrar">Mostrar aviso otra vez</button>
      <?php if ($orden): ?>
        <a class="btn btn-ghost" href="<?= h($ordenUrl) ?>">Ver orden (cliente)</a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="<?= h($adminUrl) ?>" target="_blank" rel="noopener">Abrir órdenes online</a>
      <a class="btn btn-ghost" href="cartelera_cliente.php">Volver a cartelera</a>
    </div>
  </div>
</div>

<script src="js/orden-aviso.js"></script>
<script>
(function () {
  const codigo = <?= json_encode($codigo) ?>;
  const titulo = <?= json_encode($titulo, JSON_UNESCAPED_UNICODE) ?>;
  const mensaje = <?= json_encode($mensaje, JSON_UNESCAPED_UNICODE) ?>;

  function abrir() {
    try { sessionStorage.removeItem('teatro_orden_aviso_shown_' + codigo); } catch (e) {}
    TeatroOrdenAviso.mostrar(codigo, {
      titulo,
      mensaje,
      once: false,
      verOrden: <?= $orden ? 'true' : 'false' ?>,
    });
  }

  document.getElementById('btnMostrar').addEventListener('click', abrir);
  // Abrir al cargar para la prueba
  setTimeout(abrir, 250);
})();
</script>
</body>
</html>
