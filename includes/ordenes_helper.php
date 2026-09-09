<?php
/**
 * Órdenes online. La emisión de boletos está en emision_helper.php (al pagar).
 */

if (defined('ORDENES_HELPER_INCLUDED')) {
    return;
}
define('ORDENES_HELPER_INCLUDED', true);

require_once __DIR__ . '/precio_helper.php';
require_once dirname(__DIR__) . '/sync/reservas_helper.php';
require_once dirname(__DIR__) . '/config/ventas.php';
require_once dirname(__DIR__) . '/config/env.php';

/** TTL de orden pendiente / hold renovado al crear checkout (segundos). */
if (!defined('ORDEN_TTL_SEG')) {
    define('ORDEN_TTL_SEG', 300); // 5 min (mismo ritmo que Cinépolis / selección)
}

/**
 * Ventana en pasarela: holds + orden no se liberan/expiran mientras hay pago PENDING reciente.
 * Evita cobrar sin emitir por timer del navegador o TTL corto de checkout.
 */
if (!defined('PAGO_EN_CURSO_TTL_SEG')) {
    $ttlEnv = 2700;
    if (function_exists('teatro_env')) {
        $ttlEnv = (int) teatro_env('PAGO_EN_CURSO_TTL_SEG', 2700);
    }
    define('PAGO_EN_CURSO_TTL_SEG', max(900, $ttlEnv)); // mínimo 15 min
}

function asegurarTablasOrdenes(mysqli $conn): void
{
    static $ok = false;
    if ($ok) {
        return;
    }
    $conn->query("
        CREATE TABLE IF NOT EXISTS ordenes (
            id_orden INT AUTO_INCREMENT PRIMARY KEY,
            codigo_publico VARCHAR(24) NOT NULL,
            id_evento INT NOT NULL,
            id_funcion INT NOT NULL,
            session_id VARCHAR(100) NOT NULL,
            email VARCHAR(180) NOT NULL,
            nombre VARCHAR(150) NOT NULL,
            telefono VARCHAR(40) NULL,
            total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            estado ENUM('pendiente','pagada','fallida','expirada','cancelada','reembolsada') NOT NULL DEFAULT 'pendiente',
            expira_en DATETIME NOT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_codigo_publico (codigo_publico),
            KEY idx_orden_evento_funcion (id_evento, id_funcion),
            KEY idx_orden_session (session_id),
            KEY idx_orden_estado_expira (estado, expira_en)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $conn->query("
        CREATE TABLE IF NOT EXISTS orden_items (
            id_item INT AUTO_INCREMENT PRIMARY KEY,
            id_orden INT NOT NULL,
            codigo_asiento VARCHAR(20) NOT NULL,
            id_categoria INT NULL,
            tipo_boleto VARCHAR(30) NOT NULL DEFAULT 'adulto',
            precio_base DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            descuento_aplicado DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            precio_final DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            id_promocion INT NULL,
            id_boleto INT NULL,
            KEY idx_item_orden (id_orden),
            KEY idx_item_asiento (codigo_asiento),
            CONSTRAINT fk_item_orden FOREIGN KEY (id_orden) REFERENCES ordenes (id_orden) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $ok = true;
}

function generar_codigo_orden(): string
{
    return 'ORD' . strtoupper(bin2hex(random_bytes(8)));
}

/**
 * True si la orden tiene un pago PENDING dentro de la ventana de pasarela.
 */
function orden_tiene_pago_pendiente_activo(mysqli $conn, int $idOrden): bool
{
    $ttl = (int) PAGO_EN_CURSO_TTL_SEG;
    $st = $conn->prepare("
        SELECT id_pago FROM pagos
        WHERE id_orden = ? AND estado_interno = 'PENDING'
          AND creado_en > DATE_SUB(NOW(), INTERVAL ? SECOND)
        LIMIT 1
    ");
    if (!$st) {
        return false; // tabla pagos aún no existe
    }
    $st->bind_param('ii', $idOrden, $ttl);
    $st->execute();
    $ok = (bool) $st->get_result()->fetch_assoc();
    $st->close();
    return $ok;
}

/**
 * True si la sesión tiene alguna orden con pago PENDING activo (no liberar holds).
 */
function sesion_tiene_pago_pendiente_activo(mysqli $conn, string $sessionId): bool
{
    if ($sessionId === '') {
        return false;
    }
    $ttl = (int) PAGO_EN_CURSO_TTL_SEG;
    $st = $conn->prepare("
        SELECT o.id_orden
        FROM ordenes o
        INNER JOIN pagos p ON p.id_orden = o.id_orden
          AND p.estado_interno = 'PENDING'
          AND p.creado_en > DATE_SUB(NOW(), INTERVAL ? SECOND)
        WHERE o.session_id = ?
        LIMIT 1
    ");
    if (!$st) {
        return false;
    }
    $st->bind_param('is', $ttl, $sessionId);
    $st->execute();
    $ok = (bool) $st->get_result()->fetch_assoc();
    $st->close();
    return $ok;
}

/**
 * Marca como FAILED pagos PENDING fuera de la ventana de pasarela (abandono).
 */
function marcarPagosPendientesCaducados(mysqli $conn, int $idOrden): void
{
    $ttl = (int) PAGO_EN_CURSO_TTL_SEG;
    $st = $conn->prepare("
        UPDATE pagos
        SET estado_interno = 'FAILED'
        WHERE id_orden = ? AND estado_interno = 'PENDING'
          AND creado_en <= DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    if (!$st) {
        return;
    }
    $st->bind_param('ii', $idOrden, $ttl);
    $st->execute();
    $st->close();
}

/**
 * Expira órdenes pendientes vencidas y libera sus holds.
 * No toca órdenes con pago PENDING activo (cliente en la pasarela).
 * @return int cantidad de órdenes expiradas
 */
function expirarOrdenesPendientes(?mysqli $conn = null): int
{
    $conn = $conn ?: getReservasConnection();
    if (!$conn) {
        return 0;
    }
    asegurarTablasOrdenes($conn);

    $res = $conn->query("
        SELECT id_orden, id_evento, id_funcion, session_id
        FROM ordenes
        WHERE estado = 'pendiente' AND expira_en < NOW()
        LIMIT 100
    ");
    if (!$res) {
        return 0;
    }

    $n = 0;
    while ($ord = $res->fetch_assoc()) {
        $idOrden = (int) $ord['id_orden'];

        // Cobro en curso: no expirar ni liberar asientos
        if (orden_tiene_pago_pendiente_activo($conn, $idOrden)) {
            continue;
        }
        marcarPagosPendientesCaducados($conn, $idOrden);

        $codigos = [];
        $st = $conn->prepare('SELECT codigo_asiento FROM orden_items WHERE id_orden = ?');
        $st->bind_param('i', $idOrden);
        $st->execute();
        $ri = $st->get_result();
        while ($it = $ri->fetch_assoc()) {
            $codigos[] = $it['codigo_asiento'];
        }
        $st->close();

        $up = $conn->prepare("UPDATE ordenes SET estado = 'expirada' WHERE id_orden = ? AND estado = 'pendiente'");
        $up->bind_param('i', $idOrden);
        $up->execute();
        if ($up->affected_rows > 0) {
            $n++;
            if ($codigos) {
                liberarAsientos(
                    $ord['session_id'],
                    (int) $ord['id_evento'],
                    (int) $ord['id_funcion'],
                    $codigos
                );
            }
        }
        $up->close();
    }
    return $n;
}

function obtenerOrdenPorCodigo(mysqli $conn, string $codigo): ?array
{
    asegurarTablasOrdenes($conn);
    $stmt = $conn->prepare('SELECT * FROM ordenes WHERE codigo_publico = ? LIMIT 1');
    $stmt->bind_param('s', $codigo);
    $stmt->execute();
    $orden = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$orden) {
        return null;
    }

    $id = (int) $orden['id_orden'];
    $stmt = $conn->prepare('SELECT * FROM orden_items WHERE id_orden = ? ORDER BY id_item ASC');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $items = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    $orden['items'] = $items;
    return $orden;
}

/**
 * Vista pública de una orden para API JSON.
 * Nunca expone session_id. PII completa solo si session_id coincide con el dueño.
 *
 * @param array $orden fila de obtenerOrdenPorCodigo
 * @param string|null $sessionClaim session_id aportado por el cliente (opcional)
 */
function orden_para_respuesta_publica(array $orden, ?string $sessionClaim = null): array
{
    $ownerSid = (string) ($orden['session_id'] ?? '');
    $claim = trim((string) ($sessionClaim ?? ''));
    $esDueno = $claim !== '' && $ownerSid !== '' && hash_equals($ownerSid, $claim);

    $out = $orden;
    unset($out['session_id']);

    if (!$esDueno) {
        $email = (string) ($out['email'] ?? '');
        if ($email !== '' && str_contains($email, '@')) {
            [$local, $domain] = explode('@', $email, 2);
            $localMask = $local !== '' ? (substr($local, 0, 1) . '***') : '***';
            $out['email'] = $localMask . '@' . $domain;
        }
        $tel = (string) ($out['telefono'] ?? '');
        if ($tel !== '') {
            $out['telefono'] = strlen($tel) <= 3 ? '***' : ('***' . substr($tel, -2));
        }
        $out['pii_redactada'] = true;
    }

    return $out;
}

/**
 * Verifica que los asientos estén en hold de esta sesión online.
 */
function verificarHoldsSesionOnline(
    mysqli $conn,
    int $idEvento,
    int $idFuncion,
    string $sessionId,
    array $codigos
): array {
    limpiarReservasExpiradas($conn);
    $faltantes = [];
    foreach ($codigos as $codigo) {
        $stmt = $conn->prepare("
            SELECT id_reserva FROM reservas_temporales
            WHERE codigo_asiento = ? AND id_evento = ? AND id_funcion = ?
              AND session_id = ? AND origen = 'online' AND expira_en > NOW()
            LIMIT 1
        ");
        $stmt->bind_param('siis', $codigo, $idEvento, $idFuncion, $sessionId);
        $stmt->execute();
        $ok = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$ok) {
            $faltantes[] = $codigo;
        }
    }
    return $faltantes;
}

/**
 * Valida datos del comprador online (fuente de verdad del servidor).
 * @return array{ok:bool,error?:string,nombre?:string,email?:string,telefono?:string}
 */
function validar_datos_cliente_online(string $nombre, string $email, string $telefono = ''): array
{
    $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? '');
    $email = trim($email);
    $telefono = trim($telefono);

    $lenNombre = function_exists('mb_strlen') ? mb_strlen($nombre) : strlen($nombre);
    if ($nombre === '' || $lenNombre < 3 || $lenNombre > 80) {
        return ['ok' => false, 'error' => 'Datos del cliente no válidos'];
    }
    if (!preg_match("/^[A-Za-zÁÉÍÓÚÜáéíóúüÑñ'’\\- ]{3,80}$/u", $nombre)) {
        return ['ok' => false, 'error' => 'Datos del cliente no válidos'];
    }
    if (!preg_match('/[A-Za-zÁÉÍÓÚÜáéíóúüÑñ]{2,}/u', $nombre)) {
        return ['ok' => false, 'error' => 'Datos del cliente no válidos'];
    }

    if ($email === '' || strlen($email) > 180 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Datos del cliente no válidos'];
    }
    if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/', $email)) {
        return ['ok' => false, 'error' => 'Datos del cliente no válidos'];
    }

    if ($telefono !== '') {
        $digits = preg_replace('/\D+/', '', $telefono);
        if ($digits === null || !preg_match('/^[0-9]{10}$/', $digits)) {
            return ['ok' => false, 'error' => 'Datos del cliente no válidos'];
        }
        $telefono = $digits;
    }

    return [
        'ok' => true,
        'nombre' => $nombre,
        'email' => $email,
        'telefono' => $telefono,
    ];
}

/**
 * Crea orden pendiente + items. Renueva holds.
 *
 * @param array $payload {
 *   id_evento, id_funcion, session_id, email, nombre, telefono?,
 *   asientos: [{asiento, tipo_boleto?, id_promocion?}]
 * }
 */
function crearOrdenOnline(mysqli $conn, array $payload): array
{
    asegurarTablasOrdenes($conn);
    expirarOrdenesPendientes($conn);

    $idEvento = (int) ($payload['id_evento'] ?? 0);
    $idFuncion = (int) ($payload['id_funcion'] ?? 0);
    $sessionId = trim((string) ($payload['session_id'] ?? ''));
    $asientos = $payload['asientos'] ?? [];

    if ($idEvento <= 0 || $idFuncion <= 0 || $sessionId === '') {
        return ['success' => false, 'error' => 'Faltan evento, función o sesión'];
    }

    $cli = validar_datos_cliente_online(
        (string) ($payload['nombre'] ?? ''),
        (string) ($payload['email'] ?? ''),
        (string) ($payload['telefono'] ?? '')
    );
    if (empty($cli['ok'])) {
        return ['success' => false, 'error' => $cli['error'] ?? 'Datos del cliente inválidos'];
    }
    $nombre = $cli['nombre'];
    $email = $cli['email'];
    $telefono = $cli['telefono'];

    // Ventana de venta abierta
    $horas = (int) HORAS_CIERRE_VENTAS_POST_FUNCION;
    $stmt = $conn->prepare("
        SELECT (fecha_hora > (NOW() - INTERVAL {$horas} HOUR)) AS abierta
        FROM funciones WHERE id_funcion = ? AND id_evento = ?
    ");
    $stmt->bind_param('ii', $idFuncion, $idEvento);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || (int) $row['abierta'] === 0) {
        return ['success' => false, 'error' => 'La venta para esta función ya no está disponible'];
    }

    $cotizacion = calcular_cotizacion_online($conn, $idEvento, $asientos);
    if (!$cotizacion['success']) {
        return $cotizacion;
    }

    $codigos = array_column($cotizacion['items'], 'codigo_asiento');

    // Rescatar holds si el timer del navegador iba en 1–2 s (latencia / reloj)
    $ttlPago = defined('PAGO_EN_CURSO_TTL_SEG') ? (int) PAGO_EN_CURSO_TTL_SEG : max(ORDEN_TTL_SEG, 900);
    renovarReservasSesionConGracia($sessionId, $idEvento, $idFuncion, $ttlPago, 45);

    $faltantes = verificarHoldsSesionOnline($conn, $idEvento, $idFuncion, $sessionId, $codigos);
    if ($faltantes) {
        return [
            'success' => false,
            'error' => 'Algunos asientos no están reservados por esta sesión: ' . implode(', ', $faltantes),
            'asientos_sin_hold' => $faltantes,
        ];
    }

    // Cancelar otras órdenes pendientes de la misma sesión/evento
    $stOld = $conn->prepare("
        SELECT id_orden FROM ordenes
        WHERE session_id = ? AND id_evento = ? AND id_funcion = ? AND estado = 'pendiente'
    ");
    $stOld->bind_param('sii', $sessionId, $idEvento, $idFuncion);
    $stOld->execute();
    $oldIds = [];
    $ro = $stOld->get_result();
    while ($o = $ro->fetch_assoc()) {
        $oldIds[] = (int) $o['id_orden'];
    }
    $stOld->close();
    foreach ($oldIds as $oldId) {
        $up = $conn->prepare("UPDATE ordenes SET estado = 'cancelada' WHERE id_orden = ? AND estado = 'pendiente'");
        $up->bind_param('i', $oldId);
        $up->execute();
        $up->close();
    }

    $codigo = generar_codigo_orden();
    $expCalc = calcularExpiraReserva($conn, ORDEN_TTL_SEG);
    $expira = $expCalc['db'];
    $total = (float) $cotizacion['total'];
    $tel = $telefono !== '' ? $telefono : '';
    // Guardar NULL real en BD si no hay teléfono
    $telDb = $telefono !== '' ? $telefono : null;

    $conn->begin_transaction();
    try {
        $ins = $conn->prepare("
            INSERT INTO ordenes
                (codigo_publico, id_evento, id_funcion, session_id, email, nombre, telefono, total, estado, expira_en)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?)
        ");
        $ins->bind_param(
            'siissssds',
            $codigo,
            $idEvento,
            $idFuncion,
            $sessionId,
            $email,
            $nombre,
            $telDb,
            $total,
            $expira
        );
        if (!$ins->execute()) {
            throw new Exception('No se pudo crear la orden');
        }
        $idOrden = (int) $conn->insert_id;
        $ins->close();

        $insItem = $conn->prepare("
            INSERT INTO orden_items
                (id_orden, codigo_asiento, id_categoria, tipo_boleto, precio_base, descuento_aplicado, precio_final, id_promocion)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($cotizacion['items'] as $it) {
            $idCat = $it['id_categoria'];
            $tipo = $it['tipo_boleto'];
            $pb = $it['precio_base'];
            $desc = $it['descuento_aplicado'];
            $pf = $it['precio_final'];
            $idPromo = $it['id_promocion'];
            $codAs = $it['codigo_asiento'];
            $insItem->bind_param('isisdddi', $idOrden, $codAs, $idCat, $tipo, $pb, $desc, $pf, $idPromo);
            if (!$insItem->execute()) {
                throw new Exception('No se pudo guardar ítem ' . $codAs);
            }
        }
        $insItem->close();

        renovarReservasSesion($sessionId, $idEvento, $idFuncion, ORDEN_TTL_SEG);

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }

    return [
        'success' => true,
        'id_orden' => $idOrden,
        'codigo_publico' => $codigo,
        'total' => $total,
        'estado' => 'pendiente',
        'expira_en' => $expira,
        'ttl_segundos' => ORDEN_TTL_SEG,
        'items' => $cotizacion['items'],
        'message' => 'Orden creada. Continúe con el pago.',
    ];
}
