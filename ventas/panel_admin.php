<?php
/**
 * Panel Admin: Registro de Ventas (versión STANDALONE)
 * ====================================================
 * Sistema independiente. Se eliminó la pestaña de Sincronización y los
 * "cambios pendientes" del sistema online. Muestra:
 *   - Registro detallado de ventas (desde trt_25_backup.venta_detallada)
 *   - Asientos apartados ahora mismo (reservas temporales locales)
 */

session_start();
require_once __DIR__ . '/../conexion.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// ─────────────────────────────────────────────────────────────────────
// RESERVAS ACTIVAS (anti doble-venta) — solo BD local
// ─────────────────────────────────────────────────────────────────────
$reservas_activas = [];
$reservas_count = ['total' => 0, 'local' => 0, 'online' => 0];
$cR = @new mysqli('localhost', 'root', '', 'trt_25');
if (!$cR->connect_error) {
    @$cR->query("DELETE FROM reservas_temporales WHERE expira_en < NOW()");
    $r = @$cR->query("
        SELECT r.codigo_asiento, r.id_evento, r.id_funcion, r.origen,
               r.session_id, r.cliente_info, r.expira_en,
               e.titulo,
               TIMESTAMPDIFF(SECOND, NOW(), r.expira_en) AS segundos_restantes
        FROM reservas_temporales r
        LEFT JOIN evento e ON e.id_evento = r.id_evento
        WHERE r.expira_en > NOW()
        ORDER BY r.expira_en ASC
        LIMIT 50
    ");
    if ($r) while ($row = $r->fetch_assoc()) {
        $reservas_activas[] = $row;
        $reservas_count['total']++;
        $origen = $row['origen'] ?? 'local';
        if (isset($reservas_count[$origen])) $reservas_count[$origen]++;
    }
    $cR->close();
}

// ─────────────────────────────────────────────────────────────────────
// SECCIÓN VENTAS (desde trt_25_backup.venta_detallada)
// ─────────────────────────────────────────────────────────────────────
$conn_b = @new mysqli('localhost', 'root', '', 'trt_25_backup');
$bd_disponible = !$conn_b->connect_error;
if ($bd_disponible) $conn_b->set_charset('utf8mb4');

$filtro_metodo = $_GET['metodo']  ?? '';
$filtro_lugar  = $_GET['lugar']   ?? '';
$filtro_desde  = $_GET['desde']   ?? '';
$filtro_hasta  = $_GET['hasta']   ?? '';
$filtro_email  = $_GET['email']   ?? '';

$ventas = [];
$totales = ['cantidad' => 0, 'monto' => 0, 'boletos' => 0];

if ($bd_disponible) {
    $where = ['1=1'];
    $params = [];
    $types = '';
    if ($filtro_metodo) { $where[] = 'metodo_pago = ?';   $params[] = $filtro_metodo;                 $types .= 's'; }
    if ($filtro_lugar)  { $where[] = 'lugar_venta = ?';   $params[] = $filtro_lugar;                  $types .= 's'; }
    if ($filtro_desde)  { $where[] = 'fecha_venta >= ?';  $params[] = $filtro_desde . ' 00:00:00';    $types .= 's'; }
    if ($filtro_hasta)  { $where[] = 'fecha_venta <= ?';  $params[] = $filtro_hasta . ' 23:59:59';    $types .= 's'; }
    if ($filtro_email)  { $where[] = 'cliente_email LIKE ?'; $params[] = "%$filtro_email%";          $types .= 's'; }

    $sql = "SELECT * FROM venta_detallada WHERE " . implode(' AND ', $where) . " ORDER BY fecha_venta DESC LIMIT 500";
    $stmt = $conn_b->prepare($sql);
    if ($stmt) {
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $ventas[] = $r;
            $totales['cantidad']++;
            $totales['monto']   += (float)$r['total'];
            $totales['boletos'] += (int)$r['cantidad_boletos'];
        }
        $stmt->close();
    }
}

$tabActiva = $_GET['tab'] ?? 'ventas';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registro de Ventas · Teatro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --bg-primary: #131313;
        --bg-secondary: #1c1c1e;
        --bg-tertiary: #2b2b2b;
        --bg-hover: #3a3a3c;
        --text-primary: #ffffff;
        --text-secondary: #a1a1a6;
        --text-muted: #86868b;
        --accent-blue: #1561f0;
        --accent-purple: #a855f7;
        --success: #32d74b;
        --warning: #ff9f0a;
        --danger: #ff453a;
        --info: #64d2ff;
        --border-color: #3a3a3c;
        --gradient-primary: linear-gradient(135deg, #1561f0 0%, #a855f7 100%);
        --gradient-success: linear-gradient(135deg, #32d74b 0%, #30b048 100%);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: var(--bg-primary); font-family: 'Inter', -apple-system, sans-serif; color: var(--text-primary); padding: 24px; min-height: 100vh; -webkit-font-smoothing: antialiased; }
    .wrap { max-width: 1500px; margin: 0 auto; }
    .header-card { background: var(--gradient-primary); color: #fff; border-radius: 18px; padding: 28px 32px; margin-bottom: 24px; box-shadow: 0 12px 32px rgba(21, 97, 240, 0.25); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; }
    .header-card h1 { font-size: 1.6rem; font-weight: 800; margin: 0; display: flex; align-items: center; gap: 12px; }
    .header-card p { margin: 6px 0 0; opacity: 0.85; font-size: 0.95rem; }
    .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .btn-glass { background: rgba(255, 255, 255, 0.18); color: #fff; border: 1px solid rgba(255, 255, 255, 0.25); padding: 10px 18px; border-radius: 10px; font-weight: 600; text-decoration: none; transition: 0.2s; backdrop-filter: blur(10px); display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.95rem; }
    .btn-glass:hover { background: rgba(255, 255, 255, 0.28); color: #fff; transform: translateY(-2px); }
    .tabs { display: flex; gap: 4px; background: var(--bg-secondary); padding: 6px; border-radius: 14px; border: 1px solid var(--border-color); margin-bottom: 24px; max-width: max-content; }
    .tab-btn { background: transparent; color: var(--text-secondary); border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 0.95rem; }
    .tab-btn:hover { background: var(--bg-tertiary); color: var(--text-primary); }
    .tab-btn.active { background: var(--gradient-primary); color: #fff; box-shadow: 0 4px 12px rgba(21, 97, 240, 0.3); }
    .tab-panel { display: none; }
    .tab-panel.active { display: block; animation: fadeUp 0.3s ease; }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .card-dark { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25); }
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .stat-box { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 14px; padding: 22px; position: relative; overflow: hidden; transition: 0.2s; }
    .stat-box:hover { transform: translateY(-3px); border-color: var(--accent-blue); }
    .stat-box .lbl { color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
    .stat-box .num { font-size: 2.1rem; font-weight: 800; margin-top: 6px; line-height: 1; }
    .stat-box .num.blue { color: var(--accent-blue); }
    .stat-box .num.green { color: var(--success); }
    .stat-box .num.purple { color: var(--accent-purple); }
    .filtros-card { margin-bottom: 18px; }
    .filtros-card label { color: var(--text-secondary); font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; display: block; }
    .filtros-card .form-control, .filtros-card .form-select { background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 10px; padding: 10px 14px; font-size: 0.9rem; transition: 0.2s; }
    .filtros-card .form-control:focus, .filtros-card .form-select:focus { background: var(--bg-tertiary); color: var(--text-primary); border-color: var(--accent-blue); box-shadow: 0 0 0 3px rgba(21, 97, 240, 0.2); outline: none; }
    .filtros-card .form-select option { background: var(--bg-tertiary); color: var(--text-primary); }
    .btn-primary-dark { background: var(--gradient-primary); color: #fff; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 0.9rem; }
    .btn-primary-dark:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(21, 97, 240, 0.4); }
    .btn-ghost { background: var(--bg-tertiary); color: var(--text-secondary); border: 1px solid var(--border-color); padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 0.9rem; }
    .btn-ghost:hover { background: var(--bg-hover); color: var(--text-primary); }
    .btn-export { background: var(--gradient-success); color: #131313; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 0.9rem; }
    .btn-export:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(50, 215, 75, 0.4); }
    .table-card { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; }
    .table-card .table { margin: 0; color: var(--text-primary); background: transparent; }
    .table-card thead { background: var(--bg-tertiary); }
    .table-card thead th { background: transparent; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; border-bottom: 1px solid var(--border-color); padding: 14px 12px; }
    .table-card tbody td { background: transparent; color: var(--text-secondary); border-color: var(--border-color); font-size: 0.88rem; padding: 12px; }
    .table-card tbody tr:hover td { background: var(--bg-tertiary); color: var(--text-primary); }
    .table-card tbody td strong { color: var(--text-primary); }
    .badge-pill { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .b-metodo-efectivo { background: rgba(50, 215, 75, 0.18); color: var(--success); }
    .b-metodo-tarjeta { background: rgba(255, 159, 10, 0.18); color: var(--warning); }
    .b-metodo-transferencia { background: rgba(100, 210, 255, 0.18); color: var(--info); }
    .b-metodo-cortesia { background: rgba(134, 134, 139, 0.18); color: var(--text-muted); }
    .b-metodo-otro { background: rgba(134, 134, 139, 0.18); color: var(--text-muted); }
    .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
    .empty-state i { font-size: 3.5rem; opacity: 0.3; }
    .empty-state p { margin-top: 10px; }
    .alert-warning-dark { background: rgba(255, 159, 10, 0.1); border: 1px solid rgba(255, 159, 10, 0.4); border-radius: 12px; padding: 16px 20px; color: #ffd589; margin-bottom: 16px; }
    @media (max-width: 768px) { body { padding: 14px; } .header-card { padding: 20px; } .header-card h1 { font-size: 1.25rem; } .tabs { width: 100%; max-width: 100%; } .tab-btn { flex: 1; justify-content: center; padding: 10px 12px; font-size: 0.85rem; } .stat-box .num { font-size: 1.6rem; } }
</style>
</head>
<body>

<div class="wrap">

    <div class="header-card">
        <div>
            <h1><i class="bi bi-clipboard-data"></i> Registro de Ventas</h1>
            <p>Historial detallado de ventas · Asientos apartados en tiempo real</p>
        </div>
        <div class="header-actions">
            <button class="btn-glass" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise"></i> Actualizar
            </button>
        </div>
    </div>

    <div class="tabs">
        <button class="tab-btn <?= $tabActiva === 'ventas' ? 'active' : '' ?>" data-tab="ventas">
            <i class="bi bi-clipboard-data"></i> Registro de Ventas
        </button>
        <button class="tab-btn <?= $tabActiva === 'reservas' ? 'active' : '' ?>" data-tab="reservas">
            <i class="bi bi-stopwatch"></i> Asientos apartados
            <?php if ($reservas_count['total'] > 0): ?>
                <span style="background:rgba(21,97,240,.2);color:var(--accent-blue);padding:2px 8px;border-radius:999px;font-size:.72rem;margin-left:6px;">
                    <?= $reservas_count['total'] ?>
                </span>
            <?php endif; ?>
        </button>
    </div>

    <!-- ─────────────────────── PANEL: VENTAS ─────────────────────── -->
    <div id="tab-ventas" class="tab-panel <?= $tabActiva === 'ventas' ? 'active' : '' ?>">

        <?php if (!$bd_disponible): ?>
            <div class="alert-warning-dark">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>BD de Respaldo no disponible.</strong>
                Crea la BD ejecutando <code>sql/create_backup_db.sql</code>.
            </div>
        <?php else: ?>

        <div class="stat-grid">
            <div class="stat-box">
                <div class="lbl">Total ventas</div>
                <div class="num blue"><?= number_format($totales['cantidad']) ?></div>
            </div>
            <div class="stat-box">
                <div class="lbl">Boletos vendidos</div>
                <div class="num green"><?= number_format($totales['boletos']) ?></div>
            </div>
            <div class="stat-box">
                <div class="lbl">Monto total</div>
                <div class="num purple">$<?= number_format($totales['monto'], 2) ?></div>
            </div>
        </div>

        <div class="card-dark filtros-card">
            <h6 style="color: var(--text-primary); font-weight: 700; margin-bottom: 16px;">
                <i class="bi bi-funnel"></i> Filtros
            </h6>
            <form method="GET" class="row g-3">
                <input type="hidden" name="tab" value="ventas">
                <div class="col-md-3">
                    <label>Método</label>
                    <select name="metodo" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="efectivo"      <?= $filtro_metodo=='efectivo'?'selected':'' ?>>Efectivo</option>
                        <option value="tarjeta"       <?= $filtro_metodo=='tarjeta'?'selected':'' ?>>Tarjeta</option>
                        <option value="transferencia" <?= $filtro_metodo=='transferencia'?'selected':'' ?>>Transferencia</option>
                        <option value="cortesia"      <?= $filtro_metodo=='cortesia'?'selected':'' ?>>Cortesía</option>
                        <option value="otro"          <?= $filtro_metodo=='otro'?'selected':'' ?>>Otro</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label>Lugar</label>
                    <input type="text" name="lugar" class="form-control form-control-sm" value="<?= htmlspecialchars($filtro_lugar) ?>" placeholder="Taquilla…">
                </div>
                <div class="col-md-2">
                    <label>Desde</label>
                    <input type="date" name="desde" class="form-control form-control-sm" value="<?= htmlspecialchars($filtro_desde) ?>">
                </div>
                <div class="col-md-2">
                    <label>Hasta</label>
                    <input type="date" name="hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($filtro_hasta) ?>">
                </div>
                <div class="col-md-3">
                    <label>Email</label>
                    <input type="text" name="email" class="form-control form-control-sm" value="<?= htmlspecialchars($filtro_email) ?>" placeholder="@correo">
                </div>
                <div class="col-12 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn-primary-dark">
                        <i class="bi bi-search"></i> Aplicar filtros
                    </button>
                    <a href="?tab=ventas" class="btn-ghost">
                        <i class="bi bi-x-circle"></i> Limpiar
                    </a>
                    <button type="button" class="btn-export ms-auto" onclick="exportarCSV()">
                        <i class="bi bi-download"></i> Exportar CSV
                    </button>
                </div>
            </form>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover m-0" id="tablaVentas">
                    <thead>
                        <tr>
                            <th>Fecha/Hora</th>
                            <th>Evento</th>
                            <th>Cliente</th>
                            <th>Email</th>
                            <th>Lugar</th>
                            <th>Vendedor</th>
                            <th>Método</th>
                            <th>Tarjeta</th>
                            <th>Cant.</th>
                            <th>Total</th>
                            <th>Asientos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$ventas): ?>
                        <tr><td colspan="11">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p>No hay ventas registradas con esos filtros.</p>
                            </div>
                        </td></tr>
                        <?php else: foreach ($ventas as $v): ?>
                        <tr>
                            <td><small><?= date('d/m/Y H:i', strtotime($v['fecha_venta'])) ?></small></td>
                            <td>
                                <strong><?= htmlspecialchars($v['titulo_evento'] ?? '—') ?></strong>
                                <?php if ($v['fecha_funcion']): ?>
                                    <br><small style="color: var(--text-muted);"><?= date('d/m H:i', strtotime($v['fecha_funcion'])) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><small><?= htmlspecialchars($v['cliente_nombre']) ?></small></td>
                            <td><small><?= htmlspecialchars($v['cliente_email'] ?? '—') ?></small></td>
                            <td><small><?= htmlspecialchars($v['lugar_venta']) ?></small></td>
                            <td><small><?= htmlspecialchars($v['nombre_vendedor'] ?? '—') ?></small></td>
                            <td><span class="badge-pill b-metodo-<?= $v['metodo_pago'] ?>"><?= strtoupper($v['metodo_pago']) ?></span></td>
                            <td><small><?= $v['tarjeta_terminacion'] ? '****'.htmlspecialchars($v['tarjeta_terminacion']) : '—' ?></small></td>
                            <td class="text-center"><strong><?= $v['cantidad_boletos'] ?></strong></td>
                            <td><strong style="color: var(--success);">$<?= number_format($v['total'], 2) ?></strong></td>
                            <td><small><?= htmlspecialchars(implode(', ', json_decode($v['asientos'], true) ?: [])) ?></small></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php endif; ?>
    </div>

    <!-- ─────────────────────── PANEL: RESERVAS ─────────────────────── -->
    <div id="tab-reservas" class="tab-panel <?= $tabActiva === 'reservas' ? 'active' : '' ?>">
        <div class="card-dark">
            <h5 class="mb-3" style="color: var(--text-primary); font-weight: 700;">
                <i class="bi bi-stopwatch"></i> Asientos apartados ahora mismo
                <small class="text-muted ms-2" style="font-weight: 400; font-size: 0.85rem;">
                    <?= $reservas_count['total'] ?> total
                </small>
            </h5>
            <?php if (!$reservas_activas): ?>
                <div class="empty-state" style="padding:1rem;">
                    <i class="bi bi-check-circle"></i>
                    <p class="mb-0">No hay reservas temporales activas.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0" style="font-size:0.85rem;">
                    <thead>
                        <tr>
                            <th>Asiento</th><th>Evento</th>
                            <th>Cliente / Vendedor</th><th>Sesión</th><th>Expira en</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($reservas_activas as $rsv):
                        $secs = (int)$rsv['segundos_restantes'];
                        $mm = floor($secs/60); $ss = $secs%60;
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($rsv['codigo_asiento']) ?></strong></td>
                            <td><?= htmlspecialchars($rsv['titulo'] ?? ('#' . $rsv['id_evento'])) ?></td>
                            <td><?= htmlspecialchars($rsv['cliente_info'] ?? '—') ?></td>
                            <td><code style="font-size:0.75rem;color:var(--text-secondary);"><?= htmlspecialchars(substr($rsv['session_id'], 0, 18)) ?>…</code></td>
                            <td>
                                <span style="color: <?= $secs < 60 ? 'var(--danger)' : 'var(--accent-blue)' ?>;">
                                    <?= sprintf('%02d:%02d', $mm, $ss) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-' + tab).classList.add('active');
        history.replaceState(null, '', '?tab=' + tab);
    });
});

function exportarCSV() {
    const filas = document.querySelectorAll('#tablaVentas tr');
    let csv = '';
    filas.forEach(fila => {
        const celdas = fila.querySelectorAll('th, td');
        const cols = Array.from(celdas).map(c => '"' + c.innerText.replace(/"/g, '""').replace(/\n/g, ' ').trim() + '"');
        csv += cols.join(',') + '\n';
    });
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'ventas_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
    URL.revokeObjectURL(url);
}
</script>

</body>
</html>
