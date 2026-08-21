<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}
if (($_SESSION['usuario_rol'] ?? '') !== 'admin') {
    die('Acceso denegado');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Órdenes online</title>
<link rel="icon" href="../../crt_interfaz/imagenes_teatro/nat.png" type="image/png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/teatro-style.css">
<style>
body { background: var(--bg-primary, #0f172a); color: var(--text-primary, #e2e8f0); min-height: 100vh; }
.page { max-width: 1200px; margin: 0 auto; padding: 24px 16px 48px; }
.filters { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
.filters input, .filters select {
  background: #1e293b; border: 1px solid #334155; color: #e2e8f0;
  border-radius: 8px; padding: 8px 12px;
}
.table-darkish { width: 100%; border-collapse: collapse; font-size: .92rem; }
.table-darkish th, .table-darkish td {
  padding: 10px 8px; border-bottom: 1px solid #334155; text-align: left; vertical-align: middle;
}
.badge-e {
  display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: .75rem; font-weight: 600;
}
.badge-pagada { background: #14532d; color: #86efac; }
.badge-pendiente { background: #1e3a5f; color: #93c5fd; }
.badge-reembolsada { background: #4c1d95; color: #ddd6fe; }
.badge-fallida, .badge-expirada, .badge-cancelada { background: #7f1d1d; color: #fecaca; }
.badge-alerta { background: #78350f; color: #fde68a; }
.card-panel {
  background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 16px; margin-top: 16px;
}
.mono { font-family: ui-monospace, Consolas, monospace; font-size: .85rem; }
</style>
</head>
<body>
<div class="page">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-1"><i class="bi bi-globe2"></i> Órdenes online</h1>
      <div class="text-secondary small">Pagos, estados, emisión y reembolsos</div>
    </div>
    <a class="btn btn-sm btn-outline-light" href="../Ajs_interfaz/index.php">← Ajustes</a>
  </div>

  <div class="filters">
    <input type="search" id="q" placeholder="Código, email o nombre…" style="min-width:220px">
    <select id="estado">
      <option value="">Todos los estados</option>
      <option value="pagada">pagada</option>
      <option value="pendiente">pendiente</option>
      <option value="reembolsada">reembolsada</option>
      <option value="fallida">fallida</option>
      <option value="expirada">expirada</option>
      <option value="cancelada">cancelada</option>
    </select>
    <button class="btn btn-primary btn-sm" id="btnBuscar">Buscar</button>
  </div>

  <div class="table-responsive">
    <table class="table-darkish">
      <thead>
        <tr>
          <th>Código</th>
          <th>Evento</th>
          <th>Cliente</th>
          <th>Total</th>
          <th>Orden</th>
          <th>Pago</th>
          <th>Boletos</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="tbody"><tr><td colspan="8" class="text-secondary">Cargando…</td></tr></tbody>
    </table>
  </div>

  <div class="card-panel" id="detalle" style="display:none"></div>
</div>

<script>
const API = 'api.php';

function badgeEstado(e) {
  const map = { PAID: 'pagada', PENDING: 'pendiente', FAILED: 'fallida', REFUNDED: 'reembolsada' };
  const raw = e || 'pendiente';
  const key = map[raw] || String(raw).toLowerCase();
  return `<span class="badge-e badge-${key}">${raw}</span>`;
}

async function cargar() {
  const q = document.getElementById('q').value.trim();
  const estado = document.getElementById('estado').value;
  const url = API + '?action=listar&q=' + encodeURIComponent(q) + '&estado=' + encodeURIComponent(estado);
  const r = await fetch(url).then(x => x.json());
  const tb = document.getElementById('tbody');
  if (!r.success) {
    tb.innerHTML = `<tr><td colspan="8" class="text-danger">${r.error || 'Error'}</td></tr>`;
    return;
  }
  if (!r.ordenes.length) {
    tb.innerHTML = '<tr><td colspan="8" class="text-secondary">Sin órdenes</td></tr>';
    return;
  }
  tb.innerHTML = r.ordenes.map(o => {
    const alerta = o.alerta_sin_boletos
      ? ' <span class="badge-e badge-alerta">sin boletos</span>'
      : '';
    const bol = (o.items_con_boleto || 0) + '/' + (o.items_total || 0);
    return `<tr>
      <td class="mono">${o.codigo_publico}</td>
      <td>${o.titulo_evento || ('#' + o.id_evento)}</td>
      <td>${o.nombre}<div class="small text-secondary">${o.email}</div></td>
      <td>$${Number(o.total).toFixed(2)}</td>
      <td>${badgeEstado(o.estado)}${alerta}</td>
      <td>${badgeEstado((o.pago_estado || '').toLowerCase() || '—')}<div class="small text-secondary">${o.proveedor || ''}</div></td>
      <td>${bol}</td>
      <td><button class="btn btn-sm btn-outline-info" data-cod="${o.codigo_publico}">Ver</button></td>
    </tr>`;
  }).join('');
}

async function ver(codigo) {
  const r = await fetch(API + '?action=detalle&codigo=' + encodeURIComponent(codigo)).then(x => x.json());
  const box = document.getElementById('detalle');
  if (!r.success) {
    box.style.display = '';
    box.innerHTML = `<div class="text-danger">${r.error}</div>`;
    return;
  }
  const o = r.orden;
  const items = (o.items || []).map(it =>
    `<tr><td>${it.codigo_asiento}</td><td>${it.tipo_boleto}</td><td>$${Number(it.precio_final).toFixed(2)}</td><td>${it.id_boleto || '—'}</td></tr>`
  ).join('');
  const bols = (r.boletos || []).map(b =>
    `<div class="mono small">${b.codigo_asiento}: ${b.codigo_unico}</div>`
  ).join('') || '<div class="text-secondary small">Sin boletos emitidos</div>';
  const pagos = (r.pagos || []).map(p =>
    `<div class="small">${p.proveedor} · ${p.estado_interno} · ref ${p.ref_pago_proveedor || p.ref_externa}</div>`
  ).join('');

  let actions = '';
  if (r.puede_reembolsar) {
    actions += `<button class="btn btn-warning btn-sm me-2" id="btnRefund">Reembolsar / cancelar boletos</button>`;
  }
  if (r.alerta_sin_boletos) {
    actions += `<button class="btn btn-success btn-sm me-2" id="btnReemit">Reintentar emisión</button>`;
  }
  actions += `<a class="btn btn-outline-light btn-sm" target="_blank" href="../../crt_interfaz/orden.php?codigo=${encodeURIComponent(o.codigo_publico)}">Ver como cliente</a>`;

  box.style.display = '';
  box.innerHTML = `
    <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
      <h2 class="h5 mb-0">Orden ${o.codigo_publico}</h2>
      ${badgeEstado(o.estado)}
    </div>
    <p class="mb-1">${r.titulo_evento} · Cliente: ${o.nombre} · ${o.email}</p>
    <p class="mb-2">Total: <strong>$${Number(o.total).toFixed(2)}</strong></p>
    ${r.alerta_sin_boletos ? '<div class="alert alert-warning py-2">Pagada pero faltan boletos. Reintenta emisión o reembolsa.</div>' : ''}
    <table class="table-darkish mb-3"><thead><tr><th>Asiento</th><th>Tipo</th><th>Precio</th><th>id_boleto</th></tr></thead><tbody>${items}</tbody></table>
    <div class="mb-2"><strong>Códigos</strong>${bols}</div>
    <div class="mb-3"><strong>Pagos</strong>${pagos || '<div class="text-secondary small">—</div>'}</div>
    <div>${actions}</div>
    <div class="mt-2 small text-info" id="msgAccion"></div>
  `;

  const msg = document.getElementById('msgAccion');
  const btnR = document.getElementById('btnRefund');
  if (btnR) {
    btnR.onclick = async () => {
      if (!confirm('¿Reembolsar pago y cancelar boletos de ' + o.codigo_publico + '?')) return;
      btnR.disabled = true;
      const motivo = prompt('Motivo (opcional):', 'Cancelación administrativa') || '';
      const rr = await fetch(API + '?action=reembolsar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ codigo: o.codigo_publico, motivo }),
      }).then(x => x.json());
      msg.textContent = rr.success
        ? ('OK: reembolsado. Boletos cancelados: ' + (rr.boletos_cancelados || 0))
        : ('Error: ' + (rr.error || ''));
      msg.className = 'mt-2 small ' + (rr.success ? 'text-success' : 'text-danger');
      cargar();
      if (rr.success) ver(o.codigo_publico);
      else btnR.disabled = false;
    };
  }
  const btnE = document.getElementById('btnReemit');
  if (btnE) {
    btnE.onclick = async () => {
      btnE.disabled = true;
      const rr = await fetch(API + '?action=reemitir', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ codigo: o.codigo_publico }),
      }).then(x => x.json());
      msg.textContent = rr.success
        ? ('Emisión OK. Nuevos: ' + (rr.emitidos || 0))
        : ('Error: ' + (rr.error || ''));
      msg.className = 'mt-2 small ' + (rr.success ? 'text-success' : 'text-danger');
      cargar();
      ver(o.codigo_publico);
    };
  }
}

document.getElementById('btnBuscar').onclick = cargar;
document.getElementById('q').addEventListener('keydown', e => { if (e.key === 'Enter') cargar(); });
document.getElementById('tbody').addEventListener('click', e => {
  const b = e.target.closest('[data-cod]');
  if (b) ver(b.getAttribute('data-cod'));
});
cargar();
</script>
</body>
</html>
