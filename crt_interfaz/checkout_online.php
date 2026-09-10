<?php
/**
 * Paso 2 — Datos del comprador + métodos de pago (MP en Fase 3).
 */
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/precio_helper.php';
require_once __DIR__ . '/../includes/ordenes_helper.php';

$id_evento = isset($_GET['id_evento']) ? (int) $_GET['id_evento'] : 0;
$id_funcion = isset($_GET['id_funcion']) ? (int) $_GET['id_funcion'] : 0;

$evento = null;
$funcion = null;
$error = null;

if ($id_evento <= 0 || $id_funcion <= 0) {
    $error = 'Faltan datos de la función.';
} else {
    $stmt = $conn->prepare('SELECT id_evento, titulo, imagen FROM evento WHERE id_evento = ? AND finalizado = 0');
    $stmt->bind_param('i', $id_evento);
    $stmt->execute();
    $evento = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $stmt = $conn->prepare('SELECT id_funcion, fecha_hora FROM funciones WHERE id_funcion = ? AND id_evento = ?');
    $stmt->bind_param('ii', $id_funcion, $id_evento);
    $stmt->execute();
    $funcion = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$evento || !$funcion) {
        $error = 'Evento o función no disponibles.';
    }
}

$etiquetas = ETIQUETAS_TIPO_BOLETO;
$tipos = [];
if (!$error) {
    $tipos = array_values(array_filter(
        tipos_boleto_venta_evento($conn, $id_evento),
        static fn($t) => $t !== 'cortesia'
    ));
    if (!$tipos) {
        $tipos = ['adulto'];
    }
}

$appRoot = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\');
if ($appRoot === '/' || $appRoot === '\\') {
    $appRoot = '';
}

function h($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

$imgEvento = '';
if ($evento && !empty($evento['imagen'])) {
    $rawImg = (string) $evento['imagen'];
    if (preg_match('#^https?://#i', $rawImg)) {
        $imgEvento = $rawImg;
    } else {
        $imgEvento = '../evt_interfaz/' . ltrim(str_replace('\\', '/', $rawImg), '/');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout<?= $evento ? ' — ' . h($evento['titulo']) : '' ?></title>
<link rel="icon" href="imagenes_teatro/nat.png" type="image/png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root {
  --text:#e8e8ea;
  --muted:#a1a1aa;
  --stroke:rgba(255,255,255,.14);
  --stroke-soft:rgba(255,255,255,.08);
}
body {
  margin:0; min-height:100vh; color:var(--text);
  font-family:"Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif;
  background-image:
    linear-gradient(180deg, rgba(0,0,0,.58) 0%, rgba(0,0,0,.75) 50%, rgba(0,0,0,.85) 100%),
    url('imagenes_teatro/TeatroNoche1.jpg');
  background-size:cover; background-position:center; background-attachment:fixed;
}
.topbar {
  background:linear-gradient(180deg, rgba(8,8,10,.82), rgba(12,12,14,.62));
  backdrop-filter:blur(18px) saturate(120%);
  -webkit-backdrop-filter:blur(18px) saturate(120%);
  color:#fff; padding:12px 18px;
  display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;
  border-bottom:1px solid var(--stroke-soft);
  box-shadow:0 10px 40px rgba(0,0,0,.4);
}
.topbar .small { color:rgba(228,228,231,.8); }
.btn-nav-pill {
  display:inline-flex; align-items:center; gap:8px;
  padding:10px 16px; border-radius:999px;
  border:1px solid var(--stroke);
  background:linear-gradient(145deg, rgba(40,40,44,.85), rgba(18,18,20,.9));
  backdrop-filter:blur(12px);
  color:#fafafa !important; font-weight:650; font-size:.9rem;
  text-decoration:none; line-height:1.2;
  box-shadow:0 8px 24px rgba(0,0,0,.4), inset 0 1px 0 rgba(255,255,255,.1);
  transition:transform .15s ease, border-color .15s ease, filter .15s ease;
}
.btn-nav-pill:hover {
  color:#fff !important; border-color:rgba(255,255,255,.32);
  filter:brightness(1.1); transform:translateY(-1px);
}
.btn-nav-pill .ico {
  display:inline-flex; align-items:center; justify-content:center;
  width:22px; height:22px; border-radius:999px;
  background:rgba(255,255,255,.12); font-size:.85rem; line-height:1;
}
.timer-wrap {
  display:none; align-items:center; gap:10px;
  background:rgba(20,20,22,.7); border:1px solid var(--stroke-soft);
  backdrop-filter:blur(10px);
  border-radius:999px; padding:8px 14px;
}
.timer-wrap .lbl { font-size:.75rem; color:var(--muted); }
.timer-wrap #compraTimer {
  font-variant-numeric:tabular-nums; font-weight:750; font-size:1.15rem; color:#f4f4f5;
}
.timer-wrap #compraTimer.timer-warn { color:#e4e4e7; }
.timer-wrap #compraTimer.timer-danger { color:#fff; }
.timer-wrap.visible { display:inline-flex !important; }
.layout {
  max-width:1100px; margin:0 auto; padding:20px 16px 40px;
  display:grid; grid-template-columns:.85fr 1.15fr; gap:20px;
  align-items:start;
}
@media (max-width:900px) {
  .layout { grid-template-columns:1fr; }
}
.cardx {
  background:linear-gradient(160deg, rgba(255,255,255,.12), rgba(255,255,255,.04) 40%, rgba(0,0,0,.35));
  backdrop-filter:blur(22px) saturate(115%);
  -webkit-backdrop-filter:blur(22px) saturate(115%);
  border-radius:18px;
  border:1px solid var(--stroke);
  box-shadow:0 24px 64px rgba(0,0,0,.45), inset 0 1px 0 rgba(255,255,255,.12);
  overflow:hidden;
}
.cardx h2 {
  font-size:1.1rem; margin:0 0 1rem; color:#fafafa; font-weight:700; letter-spacing:.01em;
}
.section { padding:20px; color:var(--text); }
.section .fw-semibold { color:#f4f4f5; }
.section .text-muted, .section .small.text-muted { color:var(--muted) !important; }
.form-label { color:rgba(244,244,245,.88); font-weight:600; font-size:.9rem; }
.form-control {
  background:rgba(0,0,0,.35) !important;
  border:1px solid rgba(255,255,255,.16) !important;
  color:#fafafa !important;
  border-radius:10px;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.04);
}
.form-control::placeholder { color:rgba(161,161,170,.75); }
.form-control:focus {
  border-color:rgba(255,255,255,.35) !important;
  box-shadow:0 0 0 .2rem rgba(255,255,255,.08) !important;
  background:rgba(0,0,0,.45) !important;
  color:#fff !important;
}
.form-control.is-invalid {
  border-color:rgba(248,113,113,.55) !important;
  box-shadow:0 0 0 .12rem rgba(248,113,113,.12) !important;
}
.msg.msg-invalid {
  display: block;
  text-align: center;
  color: #f87171 !important;
  font-size: 1.05rem;
  font-weight: 600;
  letter-spacing: .01em;
  margin-top: .85rem;
}
.form-select {
  background-color:rgba(0,0,0,.35) !important;
  border:1px solid rgba(255,255,255,.16) !important;
  color:#fafafa !important;
  border-radius:8px;
}
.pay-option {
  border:1px solid var(--stroke); border-radius:12px; padding:14px 16px;
  display:flex; justify-content:space-between; align-items:center;
  margin-bottom:10px;
  background:rgba(0,0,0,.28);
  backdrop-filter:blur(8px);
  color:#f4f4f5;
}
.pay-option.disabled { cursor:not-allowed; opacity:.55; }
.pay-option .soon {
  font-size:.75rem; background:rgba(255,255,255,.1); color:#e4e4e7;
  border-radius:999px; padding:2px 8px; border:1px solid rgba(255,255,255,.15);
}
.pay-option .form-check-input {
  background-color:rgba(255,255,255,.15);
  border-color:rgba(255,255,255,.35);
}
.pay-option .form-check-input:checked {
  background-color:#f4f4f5;
  border-color:#f4f4f5;
}
.pedido-head {
  background:linear-gradient(135deg, rgba(255,255,255,.1), rgba(0,0,0,.35));
  color:#fafafa; border-radius:18px 18px 0 0;
  padding:14px 18px; display:flex; justify-content:space-between; align-items:center;
  border-bottom:1px solid var(--stroke-soft);
}
.item {
  display:flex; gap:12px; align-items:flex-start; justify-content:space-between;
  padding:12px 0; border-bottom:1px solid var(--stroke-soft);
  color:#f4f4f5;
}
.seat-ico {
  width:28px; height:28px; border-radius:6px;
  background:rgba(255,255,255,.14); color:#fff;
  display:inline-flex; align-items:center; justify-content:center; font-size:.75rem; flex-shrink:0;
  border:1px solid rgba(255,255,255,.18);
}
.totals {
  background:rgba(0,0,0,.32); border-radius:12px; padding:12px 14px; margin-top:12px;
  border:1px solid var(--stroke-soft); color:#e4e4e7;
}
.msg { min-height:1.25rem; font-size:.9rem; color:rgba(244,244,245,.8); }
.msg.text-primary { color:#e4e4e7 !important; }
.msg.text-danger { color:#f4f4f5 !important; opacity:.9; }
.aviso-accesibilidad {
  display: none;
  gap: 8px;
  align-items: flex-start;
  margin: 10px 0 4px;
  padding: 9px 12px;
  border-radius: 10px;
  border: 1px solid rgba(148, 163, 184, 0.28);
  background: rgba(30, 41, 59, 0.45);
  color: rgba(226, 232, 240, 0.92);
  font-size: 0.8rem;
  line-height: 1.4;
}
.aviso-accesibilidad.visible { display: flex; }
.aviso-accesibilidad i { color: rgba(186, 230, 253, 0.95); margin-top: 1px; flex-shrink: 0; }
.aviso-accesibilidad strong { color: #f8fafc; font-weight: 650; }

.alert-glass {
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.16);
  color:rgba(228,228,231,.92);
  border-radius:12px;
  padding:10px 12px;
}
.alert-glass code { color:#fafafa; }

.btn-edit-asientos {
  display:inline-flex; align-items:center; justify-content:center; width:100%;
  margin-top:12px; padding:10px 14px; border-radius:10px;
  border:1px solid rgba(255,255,255,.22);
  background:rgba(255,255,255,.06);
  color:#f4f4f5 !important; font-weight:650; font-size:.9rem;
  text-decoration:none;
  transition:filter .15s ease, border-color .15s ease;
}
.btn-edit-asientos:hover {
  color:#fff !important; border-color:rgba(255,255,255,.35); filter:brightness(1.08);
}
.btn-pagar {
  display:inline-flex; align-items:center; justify-content:center; width:100%;
  padding:14px 18px; border-radius:12px; border:1px solid rgba(255,255,255,.28);
  background:linear-gradient(160deg, rgba(255,255,255,.92), rgba(220,220,224,.88));
  color:#0a0a0a; font-weight:750; font-size:1.05rem;
  box-shadow:0 12px 28px rgba(0,0,0,.4), inset 0 1px 0 rgba(255,255,255,.7);
  transition:transform .15s ease, filter .15s ease;
}
.btn-pagar:hover:not(:disabled) { filter:brightness(1.05); transform:translateY(-1px); color:#000; }
.btn-pagar:disabled { opacity:.4; cursor:not-allowed; box-shadow:none; }

/* Modal confirmación */
.confirm-overlay {
  position:fixed; inset:0; z-index:2000;
  display:flex; align-items:center; justify-content:center;
  padding:16px; box-sizing:border-box;
  background:rgba(0,0,0,0);
  opacity:0; visibility:hidden; pointer-events:none;
  transition: opacity .28s ease, visibility .28s ease, background .28s ease;
}
.confirm-overlay.open {
  opacity:1; visibility:visible; pointer-events:auto;
  background:rgba(0,0,0,.62);
  backdrop-filter:blur(8px);
  -webkit-backdrop-filter:blur(8px);
}
.confirm-modal {
  width:100%; max-width:420px;
  background:linear-gradient(160deg, rgba(255,255,255,.12), rgba(12,12,14,.94));
  backdrop-filter:blur(22px) saturate(115%);
  -webkit-backdrop-filter:blur(22px) saturate(115%);
  border-radius:18px;
  border:1px solid var(--stroke);
  box-shadow:0 28px 64px rgba(0,0,0,.55), inset 0 1px 0 rgba(255,255,255,.12);
  overflow:hidden;
  transform: translateY(14px) scale(.97);
  opacity:0;
  transition: transform .32s cubic-bezier(.22,1,.36,1), opacity .28s ease;
  color:#f4f4f5;
}
.confirm-overlay.open .confirm-modal {
  transform: translateY(0) scale(1);
  opacity:1;
}
@media (prefers-reduced-motion: reduce) {
  .confirm-overlay,
  .confirm-modal { transition: none; }
  .confirm-modal { transform: none; }
}
.confirm-modal .cm-head {
  background:linear-gradient(135deg, rgba(255,255,255,.1), rgba(0,0,0,.35));
  color:#fafafa; padding:14px 18px;
  font-weight:700; font-size:1.05rem;
  border-bottom:1px solid var(--stroke-soft);
}
.confirm-modal .cm-body { padding:16px 18px; }
.confirm-modal .cm-warn {
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.18);
  color:rgba(244,244,245,.9);
  border-radius:12px; padding:10px 12px; font-size:.88rem; margin-bottom:14px;
}
.confirm-modal .cm-row {
  display:flex; justify-content:space-between; gap:12px;
  padding:6px 0; font-size:.92rem; border-bottom:1px solid var(--stroke-soft);
  color:var(--muted);
}
.confirm-modal .cm-row strong { color:#fafafa; font-weight:650; text-align:right; }
.confirm-modal .cm-row:last-of-type { border-bottom:0; }
.confirm-modal .cm-total {
  display:flex; justify-content:space-between; font-weight:800;
  font-size:1.1rem; margin-top:12px; padding-top:10px;
  border-top:1px solid rgba(255,255,255,.2); color:#fafafa;
}
.confirm-modal .cm-actions {
  display:flex; gap:10px; padding:0 18px 18px;
}
.confirm-modal .cm-actions .btn { flex:1; }
.btn-cm-cancel {
  border:1px solid rgba(255,255,255,.22) !important;
  background:rgba(255,255,255,.06) !important;
  color:#f4f4f5 !important; font-weight:650; border-radius:10px; padding:10px 12px;
}
.btn-cm-cancel:hover { background:rgba(255,255,255,.12) !important; color:#fff !important; }
.btn-cm-confirm {
  border:1px solid rgba(255,255,255,.28) !important;
  background:linear-gradient(160deg, rgba(255,255,255,.92), rgba(220,220,224,.88)) !important;
  color:#0a0a0a !important; font-weight:750; border-radius:10px; padding:10px 12px;
  box-shadow:0 8px 20px rgba(0,0,0,.35);
}
.btn-cm-confirm:hover { filter:brightness(1.05); color:#000 !important; }
</style>
</head>
<body>
<div class="topbar">
  <a href="comprar.php?id_evento=<?= (int)$id_evento ?>&id_funcion=<?= (int)$id_funcion ?>&editar=1" class="btn-nav-pill">
    <span class="ico" aria-hidden="true">←</span>
    Asientos
  </a>
  <div class="d-flex align-items-center gap-3">
    <div class="timer-wrap" id="compraTimerWrap" title="Tiempo para completar tu compra">
      <span class="lbl">Tiempo restante</span>
      <span id="compraTimer">--:--</span>
    </div>
    <span class="small">Paso 2 de 2 · Datos y pago</span>
  </div>
</div>

<?php if ($error): ?>
  <div class="container py-4"><div class="alert alert-warning"><?= h($error) ?></div></div>
<?php else: ?>
<div class="layout">
  <aside class="cardx" id="pedidoCard">
    <div class="pedido-head">
      <strong>Tu pedido</strong>
      <strong id="totalHead">$0.00</strong>
    </div>
    <div class="section">
      <div class="d-flex gap-3 mb-3">
        <?php if ($imgEvento !== ''): ?>
          <img src="<?= h($imgEvento) ?>" alt="" style="width:64px;height:80px;object-fit:cover;border-radius:8px;" onerror="this.style.display='none'">
        <?php endif; ?>
        <div>
          <div class="fw-semibold"><?= h($evento['titulo']) ?></div>
          <div class="small text-muted" id="metaFuncion"></div>
        </div>
      </div>
      <div class="fw-semibold mb-2">Asientos <span id="countItems">(0)</span></div>
      <div id="listaItems"></div>
      <div class="aviso-accesibilidad" id="avisoAccesibilidad" role="status" aria-live="polite">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        <div>
          <strong>Incluye asiento de accesibilidad.</strong>
          Preferente para personas con discapacidad o movilidad reducida. Gracias por respetar esta zona.
        </div>
      </div>
      <div class="totals">
        <div class="d-flex justify-content-between"><span>Subtotal</span><span id="subtotal">$0.00</span></div>
        <div class="d-flex justify-content-between fw-bold mt-1"><span>Total</span><span id="total">$0.00</span></div>
      </div>
      <a class="btn-edit-asientos" href="comprar.php?id_evento=<?= (int)$id_evento ?>&id_funcion=<?= (int)$id_funcion ?>&editar=1">Editar asientos</a>
    </div>
  </aside>

  <div class="cardx">
    <div class="section">
      <h2>Datos personales</h2>
      <form id="formPago" novalidate>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label" for="inpNombre">Nombre(s)</label>
            <input type="text" class="form-control" id="inpNombre" name="nombre" required minlength="2" maxlength="40"
              autocomplete="given-name" placeholder="Nombre" inputmode="text" spellcheck="false">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="inpApellido">Apellido(s)</label>
            <input type="text" class="form-control" id="inpApellido" name="apellido" required minlength="2" maxlength="40"
              autocomplete="family-name" placeholder="Apellido" inputmode="text" spellcheck="false">
          </div>
          <div class="col-12">
            <label class="form-label" for="inpEmail">Correo electrónico</label>
            <input type="text" class="form-control" id="inpEmail" name="email" required maxlength="180"
              autocomplete="email" placeholder="correo@ejemplo.com" inputmode="email" spellcheck="false">
          </div>
          <div class="col-12">
            <label class="form-label" for="inpTelefono">Teléfono <span class="text-muted">(opcional)</span></label>
            <input type="tel" class="form-control" id="inpTelefono" name="telefono" maxlength="10"
              autocomplete="tel" placeholder="10 dígitos" inputmode="numeric">
          </div>
        </div>

        <h2 class="mt-4">Métodos de pago</h2>
        <label class="pay-option" style="cursor:pointer;opacity:1">
          <div class="d-flex align-items-center gap-2">
            <input type="radio" name="metodo" value="mercadopago" checked class="form-check-input m-0">
            <div>
              <div class="fw-semibold">Mercado Pago</div>
              <div class="small text-muted">Tarjeta, efectivo y más</div>
            </div>
          </div>
          <i class="bi bi-chevron-right text-muted"></i>
        </label>

        <?php
        $mostrarMockHint = false;
        if (is_file(dirname(__DIR__) . '/includes/pagos/PaymentService.php')) {
            require_once dirname(__DIR__) . '/includes/pagos/PaymentService.php';
            $mostrarMockHint = function_exists('payment_mock_permitido') && payment_mock_permitido();
        }
        if ($mostrarMockHint):
        ?>
        <div class="alert-glass small mt-3 mb-3">
          Entorno de prueba: sin token de Mercado Pago el sistema usa <strong>modo mock</strong>.
        </div>
        <?php else: ?>
        <div class="alert-glass small mt-3 mb-3">
          Al confirmar se crea la orden y se abre el checkout de Mercado Pago de forma segura.
        </div>
        <?php endif; ?>

        <button type="submit" class="btn-pagar" id="btnPagar">
          Pagar
        </button>
        <div class="msg mt-2" id="msg"></div>
        <div id="resultado" class="mt-3"></div>
      </form>
    </div>
  </div>
</div>

<div class="confirm-overlay" id="confirmOverlay" aria-hidden="true">
  <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
    <div class="cm-head" id="confirmTitle">Confirmar pago</div>
    <div class="cm-body">
      <div class="cm-warn">
        Revisa tu pedido antes de continuar. Al confirmar se iniciará el cobro.
      </div>
      <div class="cm-row"><span>Evento</span><strong id="cmEvento"></strong></div>
      <div class="cm-row"><span>Función</span><strong id="cmFuncion"></strong></div>
      <div class="cm-row"><span>Asientos</span><strong id="cmAsientos"></strong></div>
      <div class="cm-row"><span>Cliente</span><strong id="cmCliente"></strong></div>
      <div class="cm-row"><span>Correo</span><strong id="cmEmail"></strong></div>
      <div class="cm-total"><span>Total</span><span id="cmTotal">$0.00</span></div>
    </div>
    <div class="cm-actions">
      <button type="button" class="btn btn-cm-cancel" id="btnConfirmCancel">Cancelar</button>
      <button type="button" class="btn btn-cm-confirm" id="btnConfirmPay">Confirmar</button>
    </div>
  </div>
</div>

<script src="js/compra-timer.js"></script>
<script src="js/orden-aviso.js"></script>
<script>
(() => {
  const APP_ROOT = <?= json_encode($appRoot) ?>;
  const ID_EVENTO = <?= (int) $id_evento ?>;
  const ID_FUNCION = <?= (int) $id_funcion ?>;
  const ETIQUETAS = <?= json_encode($etiquetas, JSON_UNESCAPED_UNICODE) ?>;
  const TIPOS = <?= json_encode($tipos, JSON_UNESCAPED_UNICODE) ?>;
  const API_ORD = APP_ROOT + '/api/online/ordenes.php';
  const API_RES = APP_ROOT + '/api/online/reservas.php';
  const STORAGE_KEY = 'teatro_checkout_' + ID_EVENTO + '_' + ID_FUNCION;
  const CHECKOUT_TTL = <?= (int) (defined('ORDEN_TTL_SEG') ? ORDEN_TTL_SEG : 300) ?>; // 5 min datos/pago
  const PAGO_TTL = <?= (int) (defined('PAGO_EN_CURSO_TTL_SEG') ? PAGO_EN_CURSO_TTL_SEG : 2700) ?>;
  const PAGO_CURSO_KEY = 'teatro_pago_en_curso';
  let leavingToPay = false;

  const raw = sessionStorage.getItem(STORAGE_KEY);
  if (!raw) {
    window.location.href = 'comprar.php?id_evento=' + ID_EVENTO + '&id_funcion=' + ID_FUNCION;
    return;
  }
  const data = JSON.parse(raw);
  if (!data.asientos || !data.asientos.length || !data.session_id) {
    window.location.href = 'comprar.php?id_evento=' + ID_EVENTO + '&id_funcion=' + ID_FUNCION;
    return;
  }

  try {
    sessionStorage.setItem('teatro_online_session_id', data.session_id);
    document.cookie = 'teatro_sid=' + encodeURIComponent(data.session_id) + '; path=/; SameSite=Lax; max-age=86400';
  } catch (e) {}

  document.getElementById('metaFuncion').textContent = data.texto_funcion || '';
  document.getElementById('countItems').textContent = '(' + data.asientos.length + ')';

  const lista = document.getElementById('listaItems');
  const msg = document.getElementById('msg');
  const btn = document.getElementById('btnPagar');

  function money(n) { return '$' + Number(n).toFixed(2); }

  function esc(s) {
    return String(s ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  async function expirarSesion() {
    // Tras clic en Pagar o con cobro en curso: no liberar asientos
    if (leavingToPay) return;
    const codigoPago = sessionStorage.getItem(PAGO_CURSO_KEY);
    if (codigoPago) {
      try { TeatroCompraTimer.clear(); } catch (e) {}
      window.location.href = 'orden.php?codigo=' + encodeURIComponent(codigoPago) + '&espera=1';
      return;
    }
    // No liberar desde el navegador: evita carrera con clic en Pagar a 1–2 s.
    // El servidor libera holds al caducar (o al expirar órdenes).
    try {
      sessionStorage.removeItem(STORAGE_KEY);
      TeatroCompraTimer.clear();
    } catch (e) {}
    window.location.href = 'sesion_expirada.php';
  }

  async function renovarCheckout() {
    const r = await fetch(API_RES + '?action=renovar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        session_id: data.session_id,
        id_evento: ID_EVENTO,
        id_funcion: ID_FUNCION,
        ttl: CHECKOUT_TTL,
      }),
    }).then(x => x.json()).catch(() => ({ success: false }));

    if (r.success && r.renovados > 0) {
      if (r.expira_en) TeatroCompraTimer.setExpiresAt(r.expira_en);
      else TeatroCompraTimer.startFromNow(CHECKOUT_TTL);
      return true;
    }

    // renovados=0 → holds perdidos: re-apartar (no confiar solo en expira_en del API)
    const asientos = (data.asientos || []).map(a => a.asiento || a.codigo_asiento).filter(Boolean);
    if (!asientos.length) {
      await expirarSesion();
      return false;
    }
    const r2 = await fetch(API_RES + '?action=reservar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        session_id: data.session_id,
        id_evento: ID_EVENTO,
        id_funcion: ID_FUNCION,
        asientos,
        ttl: CHECKOUT_TTL,
      }),
    }).then(x => x.json()).catch(() => ({ success: false }));

    if (!r2.success) {
      await expirarSesion();
      return false;
    }
    if (r2.expira_en) TeatroCompraTimer.setExpiresAt(r2.expira_en);
    else TeatroCompraTimer.startFromNow(CHECKOUT_TTL);
    return true;
  }

  function renderItems(items) {
    lista.innerHTML = items.map(it => {
      const tipo = ETIQUETAS[it.tipo_boleto] || it.tipo_boleto;
      const cat = it.nombre_categoria ? esc(it.nombre_categoria) : esc(tipo);
      const detalle = it.nombre_categoria && ETIQUETAS[it.tipo_boleto] && it.nombre_categoria !== ETIQUETAS[it.tipo_boleto]
        ? `${esc(tipo)} · ${esc(it.nombre_categoria)}`
        : cat;
      return `<div class="item">
        <div class="d-flex gap-2">
          <span class="seat-ico"><i class="bi bi-ticket-perforated"></i></span>
          <div>
            <div class="fw-semibold">${esc(it.codigo_asiento)}</div>
            <div class="small text-muted">${detalle}</div>
          </div>
        </div>
        <div class="fw-semibold">${money(it.precio_final)}</div>
      </div>`;
    }).join('');

    const aviso = document.getElementById('avisoAccesibilidad');
    if (aviso) {
      const hayAcc = (items || []).some(it =>
        it.tipo_boleto === 'discapacitado'
        || String(it.nombre_categoria || '').toLowerCase() === 'discapacitado'
      );
      aviso.classList.toggle('visible', hayAcc);
    }
    const countEl = document.getElementById('countItems');
    if (countEl) countEl.textContent = '(' + (items ? items.length : 0) + ')';
  }

  async function cotizar() {
    const r = await fetch(API_ORD + '?action=cotizar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id_evento: ID_EVENTO, asientos: data.asientos }),
    }).then(x => x.json());
    if (!r.success) {
      msg.textContent = r.error || 'No se pudo cotizar';
      msg.className = 'msg mt-2 text-danger';
      return;
    }
    renderItems(r.items);
    // Alinear sessionStorage con lo que fijó el backend (mapa), no el cliente
    if (Array.isArray(r.items)) {
      data.asientos = r.items.map(it => ({
        asiento: it.codigo_asiento,
        tipo_boleto: it.tipo_boleto,
      }));
    }
    document.getElementById('subtotal').textContent = money(r.total);
    document.getElementById('total').textContent = money(r.total);
    document.getElementById('totalHead').textContent = money(r.total);
    data._cotizacion = r;
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(data));
  }

  const API_PAGOS = APP_ROOT + '/api/online/pagos.php';
  const overlay = document.getElementById('confirmOverlay');
  const btnConfirmPay = document.getElementById('btnConfirmPay');
  const btnConfirmCancel = document.getElementById('btnConfirmCancel');
  let pendingPago = null;

  function cerrarConfirm(opts) {
    if (!overlay) return;
    const keepPayLock = !!(opts && opts.keepPayLock);
    const focused = document.activeElement;
    if (focused && overlay.contains(focused)) {
      focused.blur();
    }
    if (btn && typeof btn.focus === 'function') {
      try { btn.focus({ preventScroll: true }); } catch (e) { btn.focus(); }
    }
    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
    if ('inert' in overlay) overlay.inert = true;
    pendingPago = null;
    if (!keepPayLock) {
      leavingToPay = false;
      TeatroCompraTimer.start({ onExpire: expirarSesion, arm: true });
    }
  }

  async function abrirConfirm(payload) {
    pendingPago = payload;
    // Mientras revisa el resumen: no liberar asientos si el timer llega a 0
    leavingToPay = true;
    try {
      const r = await fetch(API_RES + '?action=renovar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          session_id: data.session_id,
          id_evento: ID_EVENTO,
          id_funcion: ID_FUNCION,
          ttl: CHECKOUT_TTL,
          gracia: 45,
        }),
      }).then(x => x.json()).catch(() => null);
      if (r && r.success && r.expira_en) {
        TeatroCompraTimer.setExpiresAt(r.expira_en);
      }
    } catch (err) {}

    document.getElementById('cmEvento').textContent = <?= json_encode($evento['titulo'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
    document.getElementById('cmFuncion').textContent = data.texto_funcion || '';
    document.getElementById('cmAsientos').textContent = (data.asientos || [])
      .map(a => a.asiento)
      .join(', ');
    document.getElementById('cmCliente').textContent = payload.nombre;
    document.getElementById('cmEmail').textContent = payload.email;
    document.getElementById('cmTotal').textContent = document.getElementById('total')?.textContent || '$0.00';
    if ('inert' in overlay) overlay.inert = false;
    overlay.setAttribute('aria-hidden', 'false');
    overlay.classList.add('open');
    requestAnimationFrame(() => {
      try { btnConfirmPay?.focus({ preventScroll: true }); } catch (e) { btnConfirmPay?.focus(); }
    });
  }

  async function ejecutarPago(payload) {
    const nombre = payload.nombre;
    // Bloquear liberación por timer desde el primer clic (antes del redirect a MP)
    leavingToPay = true;
    TeatroCompraTimer.clear();
    btn.disabled = true;
    msg.textContent = 'Confirmando asientos…';
    msg.className = 'msg mt-2 text-primary';

    try {
      await fetch(API_RES + '?action=renovar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          session_id: data.session_id,
          id_evento: ID_EVENTO,
          id_funcion: ID_FUNCION,
          ttl: PAGO_TTL,
          gracia: 45,
        }),
      });
    } catch (err) {}

    msg.textContent = 'Creando orden…';
    let r;
    try {
      r = await fetch(API_ORD + '?action=crear', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id_evento: ID_EVENTO,
          id_funcion: ID_FUNCION,
          session_id: data.session_id,
          nombre,
          email: payload.email,
          telefono: payload.telefono || '',
          asientos: data.asientos,
        }),
      }).then(x => x.json());
    } catch (err) {
      leavingToPay = false;
      msg.textContent = 'No se pudo conectar. Intenta de nuevo.';
      msg.className = 'msg mt-2 text-danger';
      btn.disabled = false;
      TeatroCompraTimer.start({ onExpire: expirarSesion, arm: true });
      return;
    }

    if (!r.success) {
      leavingToPay = false;
      msg.textContent = r.error || 'No se pudo crear la orden';
      msg.className = 'msg mt-2 text-danger';
      btn.disabled = false;
      TeatroCompraTimer.start({ onExpire: expirarSesion, arm: true });
      return;
    }

    try { TeatroOrdenAviso.guardarCodigo(r.codigo_publico); } catch (e) {}

    msg.textContent = 'Iniciando pago…';
    let p;
    try {
      p = await fetch(API_PAGOS + '?action=iniciar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ codigo_publico: r.codigo_publico, session_id: data.session_id }),
      }).then(x => x.json());
    } catch (err) {
      leavingToPay = false;
      btn.disabled = false;
      TeatroCompraTimer.start({ onExpire: expirarSesion, arm: true });
      TeatroOrdenAviso.mostrar(r.codigo_publico, {
        titulo: 'Tu orden quedó registrada',
        mensaje: 'No pudimos abrir el pago. Guarda este número y preséntalo en taquilla si necesitas aclaración.',
      });
      return;
    }

    if (!p.success || !p.init_point) {
      leavingToPay = false;
      msg.textContent = '';
      msg.className = 'msg mt-2';
      document.getElementById('resultado').innerHTML = '';
      btn.disabled = false;
      TeatroCompraTimer.start({ onExpire: expirarSesion, arm: true });
      TeatroOrdenAviso.mostrar(r.codigo_publico, {
        titulo: 'Tu orden quedó registrada',
        mensaje: 'No pudimos iniciar el cobro. Guarda este número y preséntalo en taquilla si necesitas aclaración o reintentar tu compra.',
      });
      return;
    }

    try {
      sessionStorage.setItem(PAGO_CURSO_KEY, r.codigo_publico);
      sessionStorage.removeItem(STORAGE_KEY);
    } catch (err) {}
    window.location.href = p.init_point;
  }

  document.getElementById('formPago').addEventListener('submit', (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const nombre = String(fd.get('nombre') || '').trim();
    const apellido = String(fd.get('apellido') || '').trim();
    const email = String(fd.get('email') || '').trim();
    const telefono = String(fd.get('telefono') || '').trim();

    const badNombre = !validarNombrePersona(nombre);
    const badApellido = !validarNombrePersona(apellido);
    const badEmail = !validarEmail(email);
    const badTel = !validarTelefono(telefono);

    marcarCampo('inpNombre', badNombre);
    marcarCampo('inpApellido', badApellido);
    marcarCampo('inpEmail', badEmail);
    marcarCampo('inpTelefono', badTel);

    if (badNombre || badApellido || badEmail || badTel) {
      msg.textContent = 'Verifica los datos ingresados';
      msg.className = 'msg msg-invalid mt-2';
      const firstBad = document.querySelector('#formPago .form-control.is-invalid');
      if (firstBad) firstBad.focus();
      return;
    }

    const nombreCompleto = (nombre + ' ' + apellido).replace(/\s+/g, ' ').trim();
    if (nombreCompleto.length > 80) {
      marcarCampo('inpNombre', true);
      marcarCampo('inpApellido', true);
      msg.textContent = 'Verifica los datos ingresados';
      msg.className = 'msg msg-invalid mt-2';
      return;
    }

    msg.textContent = '';
    msg.className = 'msg mt-2';
    abrirConfirm({
      nombre: nombreCompleto,
      email,
      telefono,
    });
  });

  const inpTel = document.getElementById('inpTelefono');
  if (inpTel) {
    inpTel.addEventListener('input', () => {
      inpTel.value = inpTel.value.replace(/\D/g, '').slice(0, 10);
      if (inpTel.classList.contains('is-invalid')) {
        marcarCampo('inpTelefono', !validarTelefono(inpTel.value));
      }
    });
  }

  ['inpNombre', 'inpApellido'].forEach((id) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', () => {
      // Limitar longitud y recortar espacios extremos al final lo hace blur/submit
      if (el.value.length > 40) el.value = el.value.slice(0, 40);
      if (el.classList.contains('is-invalid')) {
        marcarCampo(id, !validarNombrePersona(el.value));
      }
    });
    el.addEventListener('blur', () => {
      el.value = el.value.trim().replace(/\s+/g, ' ');
      if (el.classList.contains('is-invalid') || el.value !== '') {
        marcarCampo(id, !validarNombrePersona(el.value));
      }
    });
  });

  const inpEmail = document.getElementById('inpEmail');
  if (inpEmail) {
    inpEmail.addEventListener('blur', () => {
      if (inpEmail.classList.contains('is-invalid') || inpEmail.value.trim() !== '') {
        marcarCampo('inpEmail', !validarEmail(inpEmail.value));
      }
    });
    inpEmail.addEventListener('input', () => {
      if (inpEmail.classList.contains('is-invalid')) {
        marcarCampo('inpEmail', !validarEmail(inpEmail.value));
      }
    });
  }

  function marcarCampo(inputId, invalido) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (invalido) {
      input.classList.add('is-invalid');
      input.setAttribute('aria-invalid', 'true');
    } else {
      input.classList.remove('is-invalid');
      input.removeAttribute('aria-invalid');
    }
  }

  /** @returns {boolean} true si es válido */
  function validarNombrePersona(raw) {
    const s = String(raw || '').trim().replace(/\s+/g, ' ');
    if (s.length < 2 || s.length > 40) return false;
    if (!/^[A-Za-zÁÉÍÓÚÜáéíóúüÑñ'’\- ]+$/u.test(s)) return false;
    if (!/[A-Za-zÁÉÍÓÚÜáéíóúüÑñ]{2,}/u.test(s)) return false;
    return true;
  }

  function validarEmail(raw) {
    const s = String(raw || '').trim();
    if (!s || s.length > 180) return false;
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(s);
  }

  function validarTelefono(raw) {
    const s = String(raw || '').trim();
    if (!s) return true; // opcional
    return /^[0-9]{10}$/.test(s);
  }

  btnConfirmCancel?.addEventListener('click', () => cerrarConfirm());
  overlay?.addEventListener('click', (e) => {
    if (e.target === overlay) cerrarConfirm();
  });
  btnConfirmPay?.addEventListener('click', async () => {
    if (!pendingPago) return;
    const payload = pendingPago;
    cerrarConfirm({ keepPayLock: true });
    await ejecutarPago(payload);
  });
  if (overlay && 'inert' in overlay) overlay.inert = true;

  // No armar el timer hasta renovar holds (evita "sesión expiró" por valor viejo en sessionStorage)
  TeatroCompraTimer.clear();
  TeatroCompraTimer.start({ onExpire: expirarSesion, arm: false });
  renovarCheckout().then((ok) => {
    if (ok) {
      const wrap = document.getElementById('compraTimerWrap');
      if (wrap) wrap.classList.add('visible');
      TeatroCompraTimer.start({ onExpire: expirarSesion, arm: true });
      cotizar();
    }
  });
})();
</script>
<?php endif; ?>
</body>
</html>
