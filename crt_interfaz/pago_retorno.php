<?php
/**
 * Return URL de Mercado Pago — solo UX.
 * La confirmación real es el webhook.
 */
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/pagos/PaymentService.php';

$codigo = isset($_GET['codigo']) ? trim((string) $_GET['codigo']) : '';
$status = isset($_GET['status']) ? trim((string) $_GET['status']) : 'pending';
$mockPay = isset($_GET['mock_pay']);

function h($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

// En modo mock (solo desarrollo), al volver del checkout simulado confirmamos el pago
if ($mockPay && $codigo !== '' && function_exists('payment_mock_permitido') && payment_mock_permitido()) {
    payment_procesar_webhook($conn, ['mock' => '1', 'codigo' => $codigo, 'result' => 'approved'], []);
}

$orden = $codigo !== '' ? obtenerOrdenPorCodigo($conn, $codigo) : null;
$estadoOrden = $orden['estado'] ?? 'desconocido';

$estadoLabels = [
    'pendiente' => 'En confirmación',
    'pagada' => 'Pago confirmado',
    'fallida' => 'Pago no completado',
    'reembolsada' => 'Reembolsada',
    'desconocido' => 'Consultando…',
];
$estadoLabel = $estadoLabels[$estadoOrden] ?? 'Consultando…';

// Si ya está confirmada, ir directo a la orden (evita quedarse en “confirmando”)
if ($codigo !== '' && in_array($estadoOrden, ['pagada', 'fallida', 'reembolsada'], true)) {
    header('Location: orden.php?codigo=' . rawurlencode($codigo));
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Confirmación de pago · Teatro Constitución</title>
<link rel="icon" href="imagenes_teatro/nat.png" type="image/png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root {
  --text: #e8e8ea;
  --muted: #a1a1aa;
  --stroke: rgba(255, 255, 255, 0.14);
}
* { box-sizing: border-box; }
body {
  min-height: 100vh;
  margin: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  color: var(--text);
  font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif;
  background-image:
    linear-gradient(180deg, rgba(0, 0, 0, 0.58) 0%, rgba(0, 0, 0, 0.75) 50%, rgba(0, 0, 0, 0.85) 100%),
    url('imagenes_teatro/TeatroNoche1.jpg');
  background-size: cover;
  background-position: center;
  background-attachment: scroll;
}
.cardx {
  width: 100%;
  max-width: 520px;
  text-align: center;
  padding: 32px 28px 26px;
  background: linear-gradient(160deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.04) 40%, rgba(0, 0, 0, 0.35));
  backdrop-filter: blur(22px) saturate(140%);
  -webkit-backdrop-filter: blur(22px) saturate(140%);
  border: 1px solid var(--stroke);
  border-radius: 20px;
  box-shadow: 0 28px 64px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.12);
  position: relative;
  overflow: hidden;
}
.cardx::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2px;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,.7), transparent);
}
.icon {
  width: 52px;
  height: 52px;
  margin: 0 auto 16px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  font-size: 1.35rem;
  color: #fafafa;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.18);
}
h1 {
  margin: 0 0 12px;
  font-size: clamp(1.35rem, 3.5vw, 1.65rem);
  font-weight: 750;
  color: #fafafa;
  letter-spacing: -0.02em;
}
.lead {
  margin: 0 0 18px;
  color: var(--muted);
  font-size: 0.98rem;
  line-height: 1.55;
}
.meta {
  margin: 0 0 8px;
  color: #e4e4e7;
  font-size: 0.98rem;
}
.meta strong { color: #fafafa; }
#estado {
  font-weight: 650;
  letter-spacing: 0.02em;
  color: #fff;
}
.btn-glass {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 18px;
  padding: 13px 28px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.28);
  background: linear-gradient(160deg, rgba(255, 255, 255, 0.92), rgba(220, 220, 224, 0.88));
  color: #0a0a0a !important;
  font-weight: 750;
  font-size: 1rem;
  text-decoration: none;
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.7);
  transition: transform .15s ease, filter .15s ease;
}
.btn-glass:hover {
  color: #000 !important;
  filter: brightness(1.05);
  transform: translateY(-1px);
}
.foot {
  margin: 18px 0 0;
  font-size: 0.82rem;
  color: rgba(161, 161, 170, 0.9);
  line-height: 1.45;
}
</style>
</head>
<body>
<div class="cardx">
  <div class="icon" aria-hidden="true"><i class="bi bi-hourglass-split"></i></div>
  <h1>Estamos confirmando tu pago</h1>
  <p class="lead">En unos segundos te mostraremos tus boletos. No cierres esta pantalla.</p>
  <?php if ($codigo): ?>
    <p class="meta"><strong>Orden:</strong> <?= h($codigo) ?></p>
    <p class="meta"><strong>Estado:</strong> <span id="estado" data-raw="<?= h($estadoOrden) ?>"><?= h($estadoLabel) ?></span></p>
    <a class="btn-glass" href="orden.php?codigo=<?= urlencode($codigo) ?>"><i class="bi bi-ticket-perforated"></i> Ver mi orden</a>
  <?php else: ?>
    <a class="btn-glass" href="cartelera_cliente.php"><i class="bi bi-calendar-event"></i> Ir a cartelera</a>
  <?php endif; ?>
  <p class="foot">Si tarda un poco, guarda tu número de orden. También puedes presentarlo en taquilla.</p>
</div>
<?php if ($codigo): ?>
<script src="js/orden-aviso.js"></script>
<script>
try {
  sessionStorage.removeItem('teatro_pago_en_curso');
  TeatroOrdenAviso.guardarCodigo(<?= json_encode($codigo) ?>);
} catch (e) {}
const labels = {
  pendiente: 'En confirmación',
  pagada: 'Pago confirmado',
  fallida: 'Pago no completado',
  reembolsada: 'Reembolsada',
  desconocido: 'Consultando…'
};
(async () => {
  const APP = <?= json_encode(rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\') ?: '') ?>;
  const codigo = <?= json_encode($codigo) ?>;
  for (let i = 0; i < 12; i++) {
    try {
      const r = await fetch(APP + '/api/online/pagos.php?action=estado&codigo=' + encodeURIComponent(codigo));
      const j = await r.json();
      if (j.success && j.orden) {
        const est = document.getElementById('estado');
        if (est) {
          const raw = j.orden.estado || 'desconocido';
          est.dataset.raw = raw;
          est.textContent = labels[raw] || 'Consultando…';
        }
        if (j.orden.estado === 'pagada' || j.orden.estado === 'fallida' || j.orden.estado === 'reembolsada') {
          window.location.href = 'orden.php?codigo=' + encodeURIComponent(codigo);
          return;
        }
      }
    } catch (e) {}
    await new Promise(res => setTimeout(res, 1500));
  }
  TeatroOrdenAviso.mostrar(codigo, {
    titulo: 'Guarda tu número de orden',
    mensaje: 'Aún no confirmamos el pago. Conserva este número y, si hay cualquier inconveniente, preséntalo en taquilla para aclarar tu compra.',
  });
})();
</script>
<?php endif; ?>
</body>
</html>
