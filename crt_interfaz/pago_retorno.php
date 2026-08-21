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
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Confirmación de pago</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { min-height:100vh; background:#0b1120; color:#e2e8f0; display:flex; align-items:center; }
.cardx { background:rgba(15,23,42,.9); border:1px solid rgba(148,163,184,.3); border-radius:16px; padding:28px; max-width:520px; margin:auto; }
</style>
</head>
<body>
<div class="cardx text-center">
  <h1 class="h4 mb-3">Estamos confirmando tu pago</h1>
  <p class="text-secondary">Esta pantalla es solo informativa. La confirmación definitiva la hace el servidor.</p>
  <?php if ($codigo): ?>
    <p class="mb-1"><strong>Orden:</strong> <?= h($codigo) ?></p>
    <p class="mb-3"><strong>Estado actual:</strong> <span id="estado"><?= h($estadoOrden) ?></span></p>
    <a class="btn btn-primary" href="orden.php?codigo=<?= urlencode($codigo) ?>">Ver orden</a>
  <?php else: ?>
    <a class="btn btn-outline-light" href="cartelera_cliente.php">Ir a cartelera</a>
  <?php endif; ?>
  <p class="small text-secondary mt-3 mb-0">Return status: <?= h($status) ?></p>
</div>
<?php if ($codigo): ?>
<script>
try { sessionStorage.removeItem('teatro_pago_en_curso'); } catch (e) {}
(async () => {
  const APP = <?= json_encode(rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\') ?: '') ?>;
  for (let i = 0; i < 8; i++) {
    try {
      const r = await fetch(APP + '/api/online/pagos.php?action=estado&codigo=' + encodeURIComponent(<?= json_encode($codigo) ?>));
      const j = await r.json();
      if (j.success && j.orden) {
        document.getElementById('estado').textContent = j.orden.estado;
        if (j.orden.estado === 'pagada' || j.orden.estado === 'fallida') break;
      }
    } catch (e) {}
    await new Promise(res => setTimeout(res, 1500));
  }
})();
</script>
<?php endif; ?>
</body>
</html>
