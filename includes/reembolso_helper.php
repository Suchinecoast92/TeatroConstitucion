<?php
/**
 * Cancelación + reembolso de órdenes online (admin).
 */

if (defined('REEMBOLSO_HELPER_INCLUDED')) {
    return;
}
define('REEMBOLSO_HELPER_INCLUDED', true);

require_once __DIR__ . '/ordenes_helper.php';
require_once __DIR__ . '/emision_helper.php';
require_once __DIR__ . '/pagos/PaymentService.php';

/**
 * Lista órdenes con pago e indicador de alerta (pagada sin boletos).
 *
 * @return list<array>
 */
function admin_listar_ordenes(mysqli $conn, array $filtros = []): array
{
    asegurarTablasOrdenes($conn);
    asegurarTablaPagos($conn);
    asegurar_origen_boletos($conn);

    $where = ['1=1'];
    $types = '';
    $params = [];

    if (!empty($filtros['estado'])) {
        $where[] = 'o.estado = ?';
        $types .= 's';
        $params[] = $filtros['estado'];
    }
    if (!empty($filtros['q'])) {
        $where[] = '(o.codigo_publico LIKE ? OR o.email LIKE ? OR o.nombre LIKE ?)';
        $types .= 'sss';
        $q = '%' . $filtros['q'] . '%';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }
    if (!empty($filtros['id_evento'])) {
        $where[] = 'o.id_evento = ?';
        $types .= 'i';
        $params[] = (int) $filtros['id_evento'];
    }

    $sql = "
        SELECT o.*,
               e.titulo AS titulo_evento,
               f.fecha_hora AS fecha_funcion,
               p.id_pago, p.proveedor, p.ref_externa, p.ref_pago_proveedor,
               p.estado_interno AS pago_estado, p.monto AS pago_monto, p.init_point,
               (SELECT COUNT(*) FROM orden_items oi WHERE oi.id_orden = o.id_orden) AS items_total,
               (SELECT COUNT(*) FROM orden_items oi WHERE oi.id_orden = o.id_orden AND oi.id_boleto IS NOT NULL) AS items_con_boleto
        FROM ordenes o
        LEFT JOIN evento e ON e.id_evento = o.id_evento
        LEFT JOIN funciones f ON f.id_funcion = o.id_funcion
        LEFT JOIN pagos p ON p.id_pago = (
            SELECT p2.id_pago FROM pagos p2 WHERE p2.id_orden = o.id_orden ORDER BY p2.id_pago DESC LIMIT 1
        )
        WHERE " . implode(' AND ', $where) . "
        ORDER BY o.id_orden DESC
        LIMIT 200
    ";

    $st = $conn->prepare($sql);
    if ($types !== '') {
        $st->bind_param($types, ...$params);
    }
    $st->execute();
    $res = $st->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $itemsTotal = (int) $row['items_total'];
        $conBol = (int) $row['items_con_boleto'];
        $row['alerta_sin_boletos'] = ($row['estado'] === 'pagada' && $itemsTotal > 0 && $conBol < $itemsTotal);
        $row['origen'] = 'online';
        $out[] = $row;
    }
    $st->close();
    return $out;
}

/**
 * Detalle de orden + items + boletos + pago.
 */
function admin_detalle_orden(mysqli $conn, string $codigo): ?array
{
    asegurar_origen_boletos($conn);
    $orden = obtenerOrdenPorCodigo($conn, $codigo);
    if (!$orden) {
        return null;
    }
    $idOrden = (int) $orden['id_orden'];

    $st = $conn->prepare('SELECT * FROM pagos WHERE id_orden = ? ORDER BY id_pago DESC');
    $st->bind_param('i', $idOrden);
    $st->execute();
    $pagos = [];
    $r = $st->get_result();
    while ($row = $r->fetch_assoc()) {
        $pagos[] = $row;
    }
    $st->close();

    $boletos = emision_listar_boletos_orden($conn, $idOrden);
    $evt = null;
    $se = $conn->prepare('SELECT titulo FROM evento WHERE id_evento = ?');
    $eid = (int) $orden['id_evento'];
    $se->bind_param('i', $eid);
    $se->execute();
    $evt = $se->get_result()->fetch_assoc();
    $se->close();

    $itemsTotal = count($orden['items']);
    $conBol = count(array_filter($orden['items'], static fn($i) => !empty($i['id_boleto'])));

    return [
        'orden' => $orden,
        'titulo_evento' => $evt['titulo'] ?? '',
        'pagos' => $pagos,
        'boletos' => $boletos,
        'alerta_sin_boletos' => ($orden['estado'] === 'pagada' && $itemsTotal > 0 && $conBol < $itemsTotal),
        'puede_reembolsar' => $orden['estado'] === 'pagada',
    ];
}

/**
 * Cancela boletos activos ligados a una orden (estatus=2) y notifica mapa.
 * No habla con la pasarela.
 *
 * @return int boletos cancelados
 */
function cancelar_boletos_de_orden(mysqli $conn, int $idOrden, string $motivo = ''): int
{
    asegurar_origen_boletos($conn);
    $st = $conn->prepare('SELECT * FROM ordenes WHERE id_orden = ? LIMIT 1');
    $st->bind_param('i', $idOrden);
    $st->execute();
    $orden = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$orden) {
        return 0;
    }

    $si = $conn->prepare('SELECT id_boleto, codigo_asiento FROM orden_items WHERE id_orden = ? AND id_boleto IS NOT NULL');
    $si->bind_param('i', $idOrden);
    $si->execute();
    $res = $si->get_result();
    $cancelados = 0;
    $asientos = [];
    while ($it = $res->fetch_assoc()) {
        $idBol = (int) $it['id_boleto'];
        $up = $conn->prepare('UPDATE boletos SET estatus = 2 WHERE id_boleto = ? AND estatus = 1');
        $up->bind_param('i', $idBol);
        $up->execute();
        if ($up->affected_rows > 0) {
            $cancelados++;
            $asientos[] = $it['codigo_asiento'];
        }
        $up->close();
    }
    $si->close();

    if ($asientos && function_exists('registrar_cambio')) {
        $pathCh = dirname(__DIR__) . '/api/registrar_cambio.php';
        if (is_file($pathCh)) {
            require_once $pathCh;
        }
        $prev = $GLOBALS['conn'] ?? null;
        $GLOBALS['conn'] = $conn;
        try {
            registrar_cambio('cancelacion', (int) $orden['id_evento'], (int) $orden['id_funcion'], [
                'asientos' => $asientos,
                'origen' => 'online',
                'motivo' => $motivo,
            ]);
        } finally {
            if ($prev !== null) {
                $GLOBALS['conn'] = $prev;
            }
        }
    }

    return $cancelados;
}

/**
 * Cancela boletos activos de la orden y solicita reembolso al gateway.
 *
 * @return array{success:bool,error?:string,reembolso?:array,boletos_cancelados?:int}
 */
function reembolsar_orden_online(mysqli $conn, string $codigoPublico, ?string $motivo = null): array
{
    asegurar_origen_boletos($conn);
    asegurarTablaPagos($conn);

    $orden = obtenerOrdenPorCodigo($conn, $codigoPublico);
    if (!$orden) {
        return ['success' => false, 'error' => 'Orden no encontrada'];
    }
    $idOrden = (int) $orden['id_orden'];
    $estado = (string) $orden['estado'];

    if ($estado === 'reembolsada') {
        return ['success' => false, 'error' => 'La orden ya está reembolsada'];
    }
    if (!in_array($estado, ['pagada'], true)) {
        return ['success' => false, 'error' => 'Solo se reembolsan órdenes pagadas (estado actual: ' . $estado . ')'];
    }

    $st = $conn->prepare("SELECT * FROM pagos WHERE id_orden = ? AND estado_interno IN ('PAID','REFUNDED') ORDER BY id_pago DESC LIMIT 1");
    $st->bind_param('i', $idOrden);
    $st->execute();
    $pago = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$pago) {
        return ['success' => false, 'error' => 'No hay pago PAID asociado'];
    }
    if ($pago['estado_interno'] === 'REFUNDED') {
        // Alinear orden si quedó inconsistente
        $uo = $conn->prepare("UPDATE ordenes SET estado = 'reembolsada' WHERE id_orden = ?");
        $uo->bind_param('i', $idOrden);
        $uo->execute();
        $uo->close();
        return ['success' => false, 'error' => 'El pago ya está reembolsado'];
    }

    $refPay = trim((string) ($pago['ref_pago_proveedor'] ?? ''));
    $gateway = payment_resolve_gateway();
    $refundResult = ['success' => true, 'mock' => true];

    if ($refPay !== '' && method_exists($gateway, 'reembolsarPago')) {
        $refundResult = $gateway->reembolsarPago($refPay, (float) $pago['monto'], $motivo);
        if (empty($refundResult['success'])) {
            return [
                'success' => false,
                'error' => 'Pasarela: ' . ($refundResult['error'] ?? 'reembolso fallido'),
                'reembolso' => $refundResult,
            ];
        }
    }

    $cancelados = 0;
    $idEvento = (int) $orden['id_evento'];
    $idFuncion = (int) $orden['id_funcion'];

    $conn->begin_transaction();
    try {
        $cancelados = cancelar_boletos_de_orden($conn, $idOrden, $motivo ?: 'admin');

        $idPago = (int) $pago['id_pago'];
        $upP = $conn->prepare("UPDATE pagos SET estado_interno = 'REFUNDED', payload_resumen = CONCAT(IFNULL(payload_resumen,''), ?) WHERE id_pago = ?");
        $extra = "\nrefund:" . date('c') . ':' . ($motivo ?: 'admin');
        $upP->bind_param('si', $extra, $idPago);
        $upP->execute();
        $upP->close();

        $uo = $conn->prepare("UPDATE ordenes SET estado = 'reembolsada' WHERE id_orden = ?");
        $uo->bind_param('i', $idOrden);
        $uo->execute();
        $uo->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        return ['success' => false, 'error' => $e->getMessage(), 'reembolso' => $refundResult];
    }

    // Auditoría
    $pathTx = dirname(__DIR__) . '/transacciones_helper.php';
    if (is_file($pathTx)) {
        require_once $pathTx;
    }

    $desc = 'Reembolso online ' . $codigoPublico . ' — boletos cancelados: ' . $cancelados
        . ($motivo ? (' — ' . $motivo) : '');
    $datos = json_encode([
        'codigo_publico' => $codigoPublico,
        'id_orden' => $idOrden,
        'boletos_cancelados' => $cancelados,
        'motivo' => $motivo,
        'proveedor' => $pago['proveedor'] ?? null,
        'ref_pago' => $refPay,
        'gateway' => $refundResult,
    ], JSON_UNESCAPED_UNICODE);

    if (function_exists('registrar_transaccion_con_datos')) {
        registrar_transaccion_con_datos('reembolso_online', $desc, $datos);
    }

    return [
        'success' => true,
        'boletos_cancelados' => $cancelados,
        'reembolso' => $refundResult,
        'codigo_publico' => $codigoPublico,
    ];
}
