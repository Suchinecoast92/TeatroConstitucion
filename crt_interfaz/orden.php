<?php
/**
 * Consulta de orden online por código público + boletos/QR si ya se emitieron.
 */
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/ordenes_helper.php';
require_once __DIR__ . '/../includes/emision_helper.php';

$codigo = isset($_GET['codigo']) ? trim((string) $_GET['codigo']) : '';
$esperaPago = isset($_GET['espera']);
expirarOrdenesPendientes($conn);
$orden = $codigo !== '' ? obtenerOrdenPorCodigo($conn, $codigo) : null;

$appRoot = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\');
if ($appRoot === '/' || $appRoot === '\\') {
    $appRoot = '';
}

function h($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

$boletos = [];
if ($orden && $orden['estado'] === 'pagada') {
    // Completar emisión si el webhook llegó antes de que existiera el helper
    emitir_boletos_orden_pagada($conn, (int) $orden['id_orden']);
    $orden = obtenerOrdenPorCodigo($conn, $codigo);
    $boletos = emision_listar_boletos_orden($conn, (int) $orden['id_orden']);
}

$evt = null;
if ($orden) {
    $st = $conn->prepare('SELECT titulo FROM evento WHERE id_evento = ?');
    $eid = (int) $orden['id_evento'];
    $st->bind_param('i', $eid);
    $st->execute();
    $evt = $st->get_result()->fetch_assoc();
    $st->close();
}
$tituloEvt = $evt['titulo'] ?? ($orden ? ('Evento #' . $orden['id_evento']) : '');
$pagadaConBoletos = $orden && $orden['estado'] === 'pagada' && $boletos;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Orden <?= h($codigo) ?></title>
<link rel="icon" href="imagenes_teatro/nat.png" type="image/png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root {
  --text: #e8e8ea;
  --muted: #a1a1aa;
  --stroke: rgba(255, 255, 255, 0.14);
  --stroke-soft: rgba(255, 255, 255, 0.08);
}
body {
  min-height: 100vh;
  margin: 0;
  color: var(--text);
  font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif;
  background-image:
    linear-gradient(180deg, rgba(0, 0, 0, 0.58) 0%, rgba(0, 0, 0, 0.75) 50%, rgba(0, 0, 0, 0.85) 100%),
    url('imagenes_teatro/TeatroNoche1.jpg');
  background-size: cover;
  background-position: center;
  background-attachment: fixed;
}
.page {
  max-width: 880px;
  margin: 0 auto;
  padding: 16px 14px 32px;
}
.card-dark {
  background: linear-gradient(160deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.04) 40%, rgba(0, 0, 0, 0.35));
  backdrop-filter: blur(22px) saturate(115%);
  -webkit-backdrop-filter: blur(22px) saturate(115%);
  border: 1px solid var(--stroke);
  border-radius: 18px;
  padding: 16px 18px 20px;
  box-shadow: 0 24px 64px rgba(0, 0, 0, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.12);
}
.top-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}
.btn-back-cartelera {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 999px;
  border: 1px solid var(--stroke);
  background: linear-gradient(145deg, rgba(40, 40, 44, 0.85), rgba(18, 18, 20, 0.9));
  backdrop-filter: blur(12px);
  color: #fafafa !important;
  font-weight: 650;
  font-size: 0.92rem;
  text-decoration: none;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.1);
  transition: transform .15s ease, border-color .15s ease, filter .15s ease;
}
.btn-back-cartelera:hover {
  color: #fff !important;
  border-color: rgba(255, 255, 255, 0.32);
  filter: brightness(1.1);
  transform: translateY(-1px);
}
.btn-back-cartelera .ico {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.12);
  font-size: 0.85rem;
  line-height: 1;
}
.ok-banner {
  background: rgba(255, 255, 255, 0.1);
  color: #f4f4f5;
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 14px;
  padding: 12px 14px;
  font-weight: 650;
  font-size: 0.92rem;
  margin-bottom: 14px;
  backdrop-filter: blur(12px);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
}
.alert-glass {
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.16);
  color: rgba(228, 228, 231, 0.95);
  border-radius: 12px;
  padding: 12px 14px;
  font-size: 0.92rem;
  font-weight: 550;
  line-height: 1.45;
}
.alert-glass.soft { margin-top: 12px; }
.alert-glass.warn {
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.28);
  color: #fafafa;
}
.alert-glass.danger {
  background: rgba(0, 0, 0, 0.35);
  border-color: rgba(255, 255, 255, 0.22);
  color: #f4f4f5;
}
.orden-head h1 {
  font-size: 1.15rem;
  font-weight: 750;
  margin: 0 0 4px;
  color: #fafafa;
}
.orden-head .evt {
  margin: 0;
  font-size: 1rem;
  color: #f4f4f5;
}
.orden-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  margin-top: 8px;
  font-size: 0.88rem;
  color: var(--muted);
}
.orden-meta strong { color: #fafafa; }

.tickets {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-bottom: 16px;
}
.ticket-panel {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  background: linear-gradient(160deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.04) 40%, rgba(0, 0, 0, 0.35));
  backdrop-filter: blur(22px) saturate(115%);
  -webkit-backdrop-filter: blur(22px) saturate(115%);
  border: 1px solid var(--stroke);
  border-radius: 18px;
  padding: 16px;
  box-shadow: 0 24px 56px rgba(0, 0, 0, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.12);
}
.ticket-qr {
  flex-shrink: 0;
  text-align: center;
}
.ticket-qr img {
  width: min(78vw, 280px);
  height: auto;
  aspect-ratio: 1;
  object-fit: contain;
  background: #fff;
  border-radius: 12px;
  padding: 12px;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.4);
}
.ticket-meta {
  width: 100%;
  text-align: center;
}
.ticket-meta .seat {
  font-size: 1.25rem;
  font-weight: 800;
  margin: 0 0 4px;
  color: #fafafa;
}
.ticket-meta .tipo {
  color: var(--muted);
  font-size: 0.92rem;
  margin-bottom: 10px;
}
.codigo-mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  letter-spacing: 0.04em;
  font-size: 0.85rem;
  word-break: break-all;
  background: rgba(0, 0, 0, 0.35);
  border: 1px solid var(--stroke);
  border-radius: 10px;
  padding: 8px 10px;
  display: inline-block;
  max-width: 100%;
  color: #f4f4f5;
}

.btn-descargar-img {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  margin-top: 18px;
  padding: 16px 20px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.28);
  background: linear-gradient(160deg, rgba(255, 255, 255, 0.92), rgba(220, 220, 224, 0.88));
  color: #0a0a0a !important;
  font-size: 1.1rem;
  font-weight: 750;
  text-decoration: none;
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.7);
  transition: transform .15s ease, filter .15s ease;
}
.btn-descargar-img:hover {
  color: #000 !important;
  filter: brightness(1.05);
  transform: translateY(-1px);
}

.detalle-block {
  margin-top: 4px;
  border-top: 1px solid var(--stroke-soft);
  padding-top: 14px;
}
.detalle-block h2 {
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--muted);
  margin: 0 0 10px;
  font-weight: 700;
}
.table-dark {
  --bs-table-bg: transparent;
  --bs-table-color: #e4e4e7;
  --bs-table-striped-bg: rgba(255, 255, 255, 0.04);
  --bs-table-border-color: rgba(255, 255, 255, 0.1);
  margin-bottom: 0;
  font-size: 0.88rem;
}

@media (min-width: 720px), (orientation: landscape) and (min-width: 560px) {
  .page { padding-top: 14px; max-width: 980px; }
  .ticket-panel {
    flex-direction: row-reverse;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    padding: 18px 22px;
  }
  .ticket-meta {
    text-align: left;
    flex: 1;
    min-width: 0;
  }
  .ticket-meta .seat { font-size: 1.4rem; }
  .ticket-qr img {
    width: min(62vh, 300px);
    max-width: min(46vw, 320px);
  }
}

@media (max-width: 719px) and (orientation: portrait) {
  .ticket-qr img {
    width: min(86vw, 300px);
    padding: 14px;
  }
  .orden-head h1 { font-size: 1.05rem; }
  .card-dark { padding: 14px 14px 16px; }
}

@media (orientation: landscape) and (max-height: 480px) {
  .ok-banner { padding: 8px 12px; font-size: 0.85rem; margin-bottom: 10px; }
  .ticket-panel { padding: 12px 16px; gap: 18px; }
  .ticket-qr img {
    width: min(72vh, 260px);
    max-width: min(48vw, 280px);
  }
  .ticket-meta .seat { font-size: 1.2rem; }
}
</style>
</head>
<body>
<div class="page">
  <div class="top-row">
    <a href="cartelera_cliente.php" class="btn-back-cartelera">
      <span class="ico" aria-hidden="true">←</span>
      Cartelera
    </a>
  </div>

  <?php if (!$orden): ?>
    <div class="alert alert-warning mb-0">Orden no encontrada.</div>
  <?php else: ?>

    <?php if ($pagadaConBoletos): ?>
      <div class="ok-banner">Pago confirmado. Tus boletos están listos para presentar en la entrada.</div>

      <div class="tickets">
        <?php foreach ($boletos as $b):
          $qrUrl = $appRoot . '/boletos_qr/' . rawurlencode($b['codigo_unico']) . '.png';
        ?>
          <div class="ticket-panel">
            <div class="ticket-qr">
              <img src="<?= h($qrUrl) ?>" alt="QR <?= h($b['codigo_unico']) ?>"
                   onerror="this.style.display='none'">
            </div>
            <div class="ticket-meta">
              <p class="seat mb-0">Asiento <?= h($b['codigo_asiento']) ?></p>
              <div class="tipo"><?= h($b['tipo_boleto']) ?> · $<?= number_format((float) $b['precio_final'], 2) ?></div>
              <div class="codigo-mono"><?= h($b['codigo_unico']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="card-dark">
        <div class="orden-head">
          <h1>Orden <?= h($orden['codigo_publico']) ?></h1>
          <p class="evt"><?= h($tituloEvt) ?></p>
          <div class="orden-meta">
            <span>Estado: <strong><?= h($orden['estado']) ?></strong></span>
            <span>Total: <strong>$<?= number_format((float) $orden['total'], 2) ?></strong></span>
            <span>Cliente: <?= h($orden['nombre']) ?> · <?= h($orden['email']) ?></span>
          </div>
        </div>
        <div class="detalle-block">
          <h2>Detalle de la orden</h2>
          <div class="table-responsive">
            <table class="table table-sm table-dark table-striped mb-0">
              <thead><tr><th>Asiento</th><th>Tipo</th><th>Precio</th><th>Código</th></tr></thead>
              <tbody>
              <?php foreach ($orden['items'] as $it):
                $codBol = '';
                foreach ($boletos as $b) {
                    if ((int) $b['id_boleto'] === (int) ($it['id_boleto'] ?? 0)) {
                        $codBol = $b['codigo_unico'];
                        break;
                    }
                }
              ?>
                <tr>
                  <td><?= h($it['codigo_asiento']) ?></td>
                  <td><?= h($it['tipo_boleto']) ?></td>
                  <td>$<?= number_format((float) $it['precio_final'], 2) ?></td>
                  <td class="codigo-mono" style="background:none;border:0;padding:0"><?= $codBol !== '' ? h($codBol) : '—' ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php
          $nBol = count($boletos);
          $labelDesc = $nBol === 1 ? 'Descargar boleto' : 'Descargar boletos';
        ?>
        <a class="btn-descargar-img" href="descargar_boleto_imagen.php?orden=<?= rawurlencode($orden['codigo_publico']) ?>">
          <span aria-hidden="true">⬇</span>
          <?= h($labelDesc) ?>
        </a>
      </div>

    <?php else: ?>
      <div class="card-dark">
        <div class="orden-head">
          <h1>Orden <?= h($orden['codigo_publico']) ?></h1>
          <p class="evt"><?= h($tituloEvt) ?></p>
          <div class="orden-meta">
            <span>Estado: <strong><?= h($orden['estado']) ?></strong></span>
            <span>Total: <strong>$<?= number_format((float) $orden['total'], 2) ?></strong></span>
            <span>Cliente: <?= h($orden['nombre']) ?> · <?= h($orden['email']) ?></span>
          </div>
        </div>

        <div class="detalle-block">
          <h2>Detalle de la orden</h2>
          <div class="table-responsive">
            <table class="table table-sm table-dark table-striped mb-0">
              <thead><tr><th>Asiento</th><th>Tipo</th><th>Precio</th><th>Código</th></tr></thead>
              <tbody>
              <?php foreach ($orden['items'] as $it): ?>
                <tr>
                  <td><?= h($it['codigo_asiento']) ?></td>
                  <td><?= h($it['tipo_boleto']) ?></td>
                  <td>$<?= number_format((float) $it['precio_final'], 2) ?></td>
                  <td>—</td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php if ($orden['estado'] === 'pendiente'): ?>
          <?php if ($esperaPago): ?>
            <div class="alert-glass warn soft">Si ya iniciaste el pago en Mercado Pago, no cierres esa ventana. Tus asientos se mantienen mientras el cobro está en curso.</div>
          <?php endif; ?>
          <div class="alert-glass soft">Pago pendiente. Si ya pagaste, espera la confirmación del servidor o vuelve desde Mercado Pago.</div>
        <?php elseif ($orden['estado'] === 'pagada'): ?>
          <div class="alert-glass warn soft">Pago confirmado. Los boletos se están generando; recarga en unos segundos.</div>
        <?php elseif ($orden['estado'] === 'fallida'): ?>
          <div class="alert-glass danger soft">El pago no se completó.</div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  <?php endif; ?>
</div>
<?php if ($codigo !== ''): ?>
<script>
try {
  if (<?= json_encode(($orden['estado'] ?? '') === 'pagada' || ($orden['estado'] ?? '') === 'fallida') ?>) {
    sessionStorage.removeItem('teatro_pago_en_curso');
  }
} catch (e) {}
</script>
<?php endif; ?>
</body>
</html>
