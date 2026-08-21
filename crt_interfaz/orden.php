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
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Orden <?= h($codigo) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { min-height: 100vh; background: linear-gradient(160deg,#0b1120,#1e293b); color: #e2e8f0; }
.card-dark { background: rgba(15,23,42,.85); border: 1px solid rgba(148,163,184,.25); border-radius: 16px; }
.boleto-card {
  background: rgba(30,41,59,.9); border: 1px solid rgba(148,163,184,.3);
  border-radius: 12px; padding: 16px; text-align: center;
}
.boleto-card img {
  width: 160px; height: 160px; object-fit: contain;
  background: #fff; border-radius: 8px; padding: 8px;
}
.codigo-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: .04em; }
</style>
</head>
<body>
<div class="container py-4" style="max-width:720px">
  <a href="cartelera_cliente.php" class="btn btn-sm btn-outline-light mb-3">← Cartelera</a>
  <?php if (!$orden): ?>
    <div class="alert alert-warning">Orden no encontrada.</div>
  <?php else:
    $evt = null;
    $st = $conn->prepare('SELECT titulo FROM evento WHERE id_evento = ?');
    $eid = (int) $orden['id_evento'];
    $st->bind_param('i', $eid);
    $st->execute();
    $evt = $st->get_result()->fetch_assoc();
    $st->close();
  ?>
    <div class="card-dark p-4">
      <h1 class="h4">Orden <?= h($orden['codigo_publico']) ?></h1>
      <p class="mb-1"><?= h($evt['titulo'] ?? ('Evento #' . $orden['id_evento'])) ?></p>
      <p class="mb-1">Estado: <strong><?= h($orden['estado']) ?></strong></p>
      <p class="mb-1">Total: <strong>$<?= number_format((float) $orden['total'], 2) ?></strong></p>
      <p class="mb-3 text-secondary small">Cliente: <?= h($orden['nombre']) ?> · <?= h($orden['email']) ?></p>
      <table class="table table-sm table-dark table-striped">
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
            <td class="codigo-mono small"><?= $codBol !== '' ? h($codBol) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($orden['estado'] === 'pendiente'): ?>
        <?php if ($esperaPago): ?>
          <div class="alert alert-warning mb-2">Si ya iniciaste el pago en Mercado Pago, no cierres esa ventana. Tus asientos se mantienen mientras el cobro está en curso.</div>
        <?php endif; ?>
        <div class="alert alert-info mb-0">Pago pendiente. Si ya pagaste, espera la confirmación del servidor o vuelve desde Mercado Pago.</div>
      <?php elseif ($orden['estado'] === 'pagada' && $boletos): ?>
        <div class="alert alert-success">Pago confirmado. Tus boletos están listos para presentar en la entrada.</div>
        <div class="row g-3 mt-1">
          <?php foreach ($boletos as $b):
            $qrUrl = $appRoot . '/boletos_qr/' . rawurlencode($b['codigo_unico']) . '.png';
          ?>
            <div class="col-sm-6">
              <div class="boleto-card">
                <div class="fw-semibold mb-1">Asiento <?= h($b['codigo_asiento']) ?></div>
                <div class="small text-secondary mb-2"><?= h($b['tipo_boleto']) ?> · $<?= number_format((float) $b['precio_final'], 2) ?></div>
                <img src="<?= h($qrUrl) ?>" alt="QR <?= h($b['codigo_unico']) ?>"
                     onerror="this.style.display='none'">
                <div class="codigo-mono small mt-2"><?= h($b['codigo_unico']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php elseif ($orden['estado'] === 'pagada'): ?>
        <div class="alert alert-warning mb-0">Pago confirmado. Los boletos se están generando; recarga en unos segundos.</div>
      <?php elseif ($orden['estado'] === 'fallida'): ?>
        <div class="alert alert-danger mb-0">El pago no se completó.</div>
      <?php endif; ?>
    </div>
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
