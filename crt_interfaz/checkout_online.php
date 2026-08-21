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
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root {
  --blue:#1d4ed8;
  --blue-dark:#1e3a8a;
  --bg:#f3f4f6;
  --card:#ffffff;
  --text:#111827;
  --muted:#6b7280;
  --line:#e5e7eb;
}
body {
  margin:0; min-height:100vh; background:var(--bg); color:var(--text);
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
}
.topbar {
  background:#0f172a; color:#fff; padding:12px 18px;
  display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;
}
.timer-wrap {
  display:none; align-items:center; gap:10px;
  background:rgba(30,41,59,.95); border:1px solid rgba(148,163,184,.35);
  border-radius:999px; padding:8px 14px;
}
.timer-wrap .lbl { font-size:.75rem; color:#94a3b8; }
.timer-wrap #compraTimer {
  font-variant-numeric:tabular-nums; font-weight:800; font-size:1.2rem; color:#e2e8f0;
}
.timer-wrap #compraTimer.timer-warn { color:#fbbf24; }
.timer-wrap #compraTimer.timer-danger { color:#f87171; }
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
  background:var(--card); border-radius:12px; border:1px solid var(--line);
  box-shadow:0 1px 2px rgba(0,0,0,.04);
}
.cardx h2 { font-size:1.15rem; margin:0 0 1rem; color:var(--blue-dark); }
.section { padding:20px; }
.pay-option {
  border:1px solid var(--line); border-radius:10px; padding:14px 16px;
  display:flex; justify-content:space-between; align-items:center;
  margin-bottom:10px; background:#fff; opacity:.85;
}
.pay-option.disabled { cursor:not-allowed; background:#f9fafb; }
.pay-option .soon {
  font-size:.75rem; background:#eff6ff; color:#1d4ed8;
  border-radius:999px; padding:2px 8px;
}
.pedido-head {
  background:var(--blue-dark); color:#fff; border-radius:12px 12px 0 0;
  padding:14px 18px; display:flex; justify-content:space-between; align-items:center;
}
.item {
  display:flex; gap:12px; align-items:flex-start; justify-content:space-between;
  padding:12px 0; border-bottom:1px solid var(--line);
}
.seat-ico {
  width:28px; height:28px; border-radius:6px; background:#2563eb; color:#fff;
  display:inline-flex; align-items:center; justify-content:center; font-size:.75rem; flex-shrink:0;
}
.totals { background:#f3f4f6; border-radius:10px; padding:12px 14px; margin-top:12px; }
.msg { min-height:1.25rem; font-size:.9rem; }

/* Modal confirmación de pago */
.confirm-overlay {
  position:fixed; inset:0; z-index:2000;
  display:flex; align-items:center; justify-content:center;
  padding:16px; box-sizing:border-box;
  background:rgba(15,23,42,.0);
  opacity:0; visibility:hidden; pointer-events:none;
  transition: opacity .28s ease, visibility .28s ease, background .28s ease;
}
.confirm-overlay.open {
  opacity:1; visibility:visible; pointer-events:auto;
  background:rgba(15,23,42,.55);
}
.confirm-modal {
  width:100%; max-width:420px; background:#fff; border-radius:14px;
  border:1px solid var(--line); box-shadow:0 20px 50px rgba(0,0,0,.25);
  overflow:hidden;
  transform: translateY(14px) scale(.97);
  opacity:0;
  transition: transform .32s cubic-bezier(.22,1,.36,1), opacity .28s ease;
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
  background:var(--blue-dark); color:#fff; padding:14px 18px;
  font-weight:700; font-size:1.05rem;
}
.confirm-modal .cm-body { padding:16px 18px; }
.confirm-modal .cm-warn {
  background:#fffbeb; border:1px solid #fcd34d; color:#92400e;
  border-radius:10px; padding:10px 12px; font-size:.88rem; margin-bottom:14px;
}
.confirm-modal .cm-row {
  display:flex; justify-content:space-between; gap:12px;
  padding:6px 0; font-size:.92rem; border-bottom:1px solid var(--line);
}
.confirm-modal .cm-row:last-of-type { border-bottom:0; }
.confirm-modal .cm-total {
  display:flex; justify-content:space-between; font-weight:800;
  font-size:1.1rem; margin-top:12px; padding-top:10px; border-top:2px solid var(--line);
}
.confirm-modal .cm-actions {
  display:flex; gap:10px; padding:0 18px 18px;
}
.confirm-modal .cm-actions .btn { flex:1; }
</style>
</head>
<body>
<div class="topbar">
  <a href="comprar.php?id_evento=<?= (int)$id_evento ?>&id_funcion=<?= (int)$id_funcion ?>&editar=1" class="btn btn-sm btn-outline-light">← Asientos</a>
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
      <div class="totals">
        <div class="d-flex justify-content-between"><span>Subtotal</span><span id="subtotal">$0.00</span></div>
        <div class="d-flex justify-content-between fw-bold mt-1"><span>Total</span><span id="total">$0.00</span></div>
      </div>
      <a class="btn btn-outline-secondary btn-sm w-100 mt-3" href="comprar.php?id_evento=<?= (int)$id_evento ?>&id_funcion=<?= (int)$id_funcion ?>&editar=1">Editar asientos</a>
    </div>
  </aside>

  <div class="cardx">
    <div class="section">
      <h2>Datos personales</h2>
      <form id="formPago">
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Nombre(s)</label>
            <input type="text" class="form-control" name="nombre" required maxlength="150" placeholder="Nombre">
          </div>
          <div class="col-md-6">
            <label class="form-label">Apellido(s)</label>
            <input type="text" class="form-control" name="apellido" maxlength="80" placeholder="Apellido">
          </div>
          <div class="col-12">
            <label class="form-label">Correo electrónico</label>
            <input type="email" class="form-control" name="email" required maxlength="180" placeholder="correo@ejemplo.com">
          </div>
          <div class="col-12">
            <label class="form-label">Teléfono <span class="text-muted">(opcional)</span></label>
            <input type="text" class="form-control" name="telefono" maxlength="40" placeholder="10 dígitos">
          </div>
        </div>

        <h2 class="mt-4">Métodos de pago</h2>
        <label class="pay-option" style="cursor:pointer;opacity:1">
          <div class="d-flex align-items-center gap-2">
            <input type="radio" name="metodo" value="mercadopago" checked class="form-check-input m-0">
            <div>
              <div class="fw-semibold">Mercado Pago</div>
              <div class="small text-muted">Tarjeta, efectivo y más (sandbox / mock local)</div>
            </div>
          </div>
          <i class="bi bi-chevron-right text-muted"></i>
        </label>

        <div class="alert alert-info small mt-3 mb-3">
          Al confirmar se crea la orden y se abre el checkout de pago.
          Sin <code>MP_ACCESS_TOKEN</code> el sistema usa <strong>modo mock</strong> para pruebas locales.
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2" id="btnPagar">
          Pagar
        </button>
        <div class="msg mt-2 text-primary" id="msg"></div>
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
      <button type="button" class="btn btn-outline-secondary" id="btnConfirmCancel">Cancelar</button>
      <button type="button" class="btn btn-primary" id="btnConfirmPay">Confirmar</button>
    </div>
  </div>
</div>

<script src="js/compra-timer.js"></script>
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
      return `<div class="item">
        <div class="d-flex gap-2">
          <span class="seat-ico"><i class="bi bi-ticket-perforated"></i></span>
          <div>
            <div class="fw-semibold">${it.codigo_asiento}</div>
            <div class="small text-muted">${tipo}${it.nombre_categoria ? ' · ' + it.nombre_categoria : ''}</div>
            <select class="form-select form-select-sm mt-1 tipo-sel" data-asiento="${it.codigo_asiento}" style="max-width:10rem">
              ${TIPOS.map(t => `<option value="${t}" ${t===it.tipo_boleto?'selected':''}>${ETIQUETAS[t]||t}</option>`).join('')}
            </select>
          </div>
        </div>
        <div class="fw-semibold">${money(it.precio_final)}</div>
      </div>`;
    }).join('');
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
    document.getElementById('subtotal').textContent = money(r.total);
    document.getElementById('total').textContent = money(r.total);
    document.getElementById('totalHead').textContent = money(r.total);
    data._cotizacion = r;
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(data));
  }

  lista.addEventListener('change', (e) => {
    if (!e.target.classList.contains('tipo-sel')) return;
    const codigo = e.target.dataset.asiento;
    const item = data.asientos.find(a => a.asiento === codigo);
    if (!item) return;
    item.tipo_boleto = e.target.value;
    cotizar();
  });

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
    const r = await fetch(API_ORD + '?action=crear', {
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

    if (!r.success) {
      leavingToPay = false;
      msg.textContent = r.error || 'No se pudo crear la orden';
      msg.className = 'msg mt-2 text-danger';
      btn.disabled = false;
      TeatroCompraTimer.start({ onExpire: expirarSesion, arm: true });
      return;
    }

    msg.textContent = 'Iniciando pago…';
    const p = await fetch(API_PAGOS + '?action=iniciar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ codigo_publico: r.codigo_publico }),
    }).then(x => x.json());

    if (!p.success || !p.init_point) {
      leavingToPay = false;
      msg.textContent = p.error || 'No se pudo iniciar el pago. Orden: ' + r.codigo_publico;
      msg.className = 'msg mt-2 text-danger';
      document.getElementById('resultado').innerHTML = `
        <div class="alert alert-warning mb-0">
          Orden creada: <strong>${r.codigo_publico}</strong> (pendiente de pago).
          <a href="orden.php?codigo=${encodeURIComponent(r.codigo_publico)}">Ver orden</a>
        </div>`;
      btn.disabled = false;
      TeatroCompraTimer.start({ onExpire: expirarSesion, arm: true });
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
    if (!e.target.reportValidity()) return;
    const fd = new FormData(e.target);
    const nombre = ((fd.get('nombre') || '') + ' ' + (fd.get('apellido') || '')).trim();
    abrirConfirm({
      nombre,
      email: String(fd.get('email') || '').trim(),
      telefono: String(fd.get('telefono') || '').trim(),
    });
  });

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
