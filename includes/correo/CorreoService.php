<?php
/**
 * Correo de confirmación de compra online.
 *
 * Garantía de un solo envío: tabla `orden_notificaciones` con UNIQUE(id_orden, tipo).
 * Cada intento "reclama" la fila con un UPDATE condicional; solo quien la reclama envía,
 * aunque el webhook, la página de la orden y el admin emitan la orden a la vez.
 *
 * MAIL_MODE: mock (guarda .eml, por defecto fuera de producción) | smtp | off.
 */

if (defined('CORREO_SERVICE_INCLUDED')) {
    return;
}
define('CORREO_SERVICE_INCLUDED', true);

require_once dirname(__DIR__, 2) . '/config/env.php';
require_once dirname(__DIR__, 2) . '/config/legal.php';
require_once dirname(__DIR__) . '/ordenes_helper.php';
require_once __DIR__ . '/MailerInterface.php';
require_once __DIR__ . '/phpmailer_mensaje.php';
require_once __DIR__ . '/MockMailer.php';
require_once __DIR__ . '/SmtpMailer.php';

const CORREO_TIPO_CONFIRMACION = 'confirmacion_compra';
const CORREO_MAX_INTENTOS_AUTO = 5;
/** Un intento "enviando" más viejo que esto se considera caído y se puede reclamar. */
const CORREO_RECLAMO_VENCE_MIN = 10;

function correo_modo(): string
{
    $modo = strtolower(trim((string) teatro_env('MAIL_MODE', '')));
    if (in_array($modo, ['mock', 'smtp', 'off'], true)) {
        return $modo;
    }
    if ($modo !== '') {
        error_log('[correo] MAIL_MODE desconocido; correo desactivado');
        return 'off';
    }
    // Sin configurar: en producción no se simula en silencio.
    return teatro_es_produccion() ? 'off' : 'mock';
}

function correo_resolver_mailer(): ?MailerInterface
{
    switch (correo_modo()) {
        case 'smtp':
            return new SmtpMailer();
        case 'mock':
            return new MockMailer();
        default:
            return null;
    }
}

function asegurarTablaNotificaciones(mysqli $conn): void
{
    static $ok = false;
    if ($ok) {
        return;
    }
    asegurarTablasOrdenes($conn);
    $conn->query("
        CREATE TABLE IF NOT EXISTS orden_notificaciones (
            id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
            id_orden INT NOT NULL,
            tipo VARCHAR(40) NOT NULL,
            estado ENUM('pendiente','enviando','enviado','fallido') NOT NULL DEFAULT 'pendiente',
            proveedor VARCHAR(20) NULL,
            destinatario VARCHAR(180) NULL,
            intentos INT NOT NULL DEFAULT 0,
            ultimo_error VARCHAR(500) NULL,
            message_id VARCHAR(190) NULL,
            enviado_en DATETIME NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_notif_orden_tipo (id_orden, tipo),
            KEY idx_notif_estado (estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $ok = true;
}

function correo_app_url(): string
{
    $configured = rtrim((string) teatro_env('APP_URL', ''), '/');
    if ($configured !== '') {
        return $configured;
    }
    if (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST'])) {
        return 'http://localhost';
    }
    $scheme = teatro_request_is_https() ? 'https' : 'http';
    return $scheme . '://' . $_SERVER['HTTP_HOST'] . teatro_app_base_path();
}

/** Para logs: nunca el correo completo del cliente. */
function correo_mascara_email(string $email): string
{
    if (!str_contains($email, '@')) {
        return '***';
    }
    [$local, $dominio] = explode('@', $email, 2);
    return mb_substr($local, 0, 2) . '***@' . $dominio;
}

/**
 * @return array{orden:array, titulo:string, fecha_hora:?string, boletos:list<array>}|null
 */
function correo_datos_orden(mysqli $conn, int $idOrden): ?array
{
    $st = $conn->prepare('
        SELECT o.*, e.titulo, f.fecha_hora
        FROM ordenes o
        LEFT JOIN evento e ON e.id_evento = o.id_evento
        LEFT JOIN funciones f ON f.id_funcion = o.id_funcion
        WHERE o.id_orden = ?
        LIMIT 1
    ');
    $st->bind_param('i', $idOrden);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) {
        return null;
    }

    require_once dirname(__DIR__) . '/emision_helper.php';
    return [
        'orden' => $row,
        'titulo' => (string) ($row['titulo'] ?? ('Evento #' . $row['id_evento'])),
        'fecha_hora' => $row['fecha_hora'] ?? null,
        'boletos' => emision_listar_boletos_orden($conn, $idOrden),
    ];
}

function correo_fecha_legible(?string $fechaHora): string
{
    if (!$fechaHora) {
        return '';
    }
    // Se interpreta y formatea en UTC para no convertir la hora local guardada: el ICU de PHP
    // puede traer tablas viejas que aún aplican horario de verano en México.
    try {
        $dt = new DateTimeImmutable($fechaHora, new DateTimeZone('UTC'));
    } catch (Throwable $e) {
        return $fechaHora;
    }
    $ts = $dt->getTimestamp();
    if (class_exists('IntlDateFormatter')) {
        $fmt = new IntlDateFormatter(
            'es_MX',
            IntlDateFormatter::FULL,
            IntlDateFormatter::SHORT,
            'UTC',
            IntlDateFormatter::GREGORIAN,
            "EEEE d 'de' MMMM 'de' y, h:mm a"
        );
        $txt = $fmt->format($ts);
        if (is_string($txt) && $txt !== '') {
            return mb_strtoupper(mb_substr($txt, 0, 1)) . mb_substr($txt, 1);
        }
    }
    return $dt->format('d/m/Y H:i');
}

/**
 * Mensaje neutral (ver MailerInterface). Todo el contenido variable va escapado.
 *
 * @param array{orden:array, titulo:string, fecha_hora:?string, boletos:list<array>} $d
 */
function correo_confirmacion_mensaje(array $d): array
{
    $h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $o = $d['orden'];
    $codigo = (string) $o['codigo_publico'];
    $url = correo_app_url() . '/crt_interfaz/orden.php?codigo=' . rawurlencode($codigo);
    $fecha = correo_fecha_legible($d['fecha_hora']);
    $total = '$' . number_format((float) $o['total'], 2);
    $nombre = trim((string) $o['nombre']);
    $urlTerminos = correo_app_url() . '/crt_interfaz/terminos.php#reembolsos';
    $urlPrivacidad = correo_app_url() . '/crt_interfaz/privacidad.php';
    $etiquetaTipo = static fn($t) => ETIQUETAS_TIPO_BOLETO[(string) $t] ?? ucfirst((string) $t);

    require_once dirname(__DIR__) . '/qr_helper.php';
    $inline = [];
    $filasHtml = '';
    $filasTxt = '';
    foreach ($d['boletos'] as $b) {
        $cu = (string) $b['codigo_unico'];
        $qrHtml = '';
        $png = teatro_qr_asegurado($cu);
        if ($png !== null) {
            $cid = 'qr_' . $cu;
            $inline[] = ['path' => $png, 'cid' => $cid, 'name' => 'QR_' . $b['codigo_asiento'] . '.png'];
            $qrHtml = '<img src="cid:' . $h($cid) . '" width="140" height="140" alt="QR ' . $h($b['codigo_asiento']) . '" style="display:block;border:0">';
        }
        $filasHtml .= '<tr>'
            . '<td style="padding:12px;border-top:1px solid #e5e7eb;vertical-align:middle">'
            . '<div style="font-size:18px;font-weight:700">Asiento ' . $h($b['codigo_asiento']) . '</div>'
            . '<div style="color:#6b7280;font-size:13px">' . $h($etiquetaTipo($b['tipo_boleto'])) . ' · $' . $h(number_format((float) $b['precio_final'], 2)) . '</div>'
            . '<div style="font-family:Consolas,monospace;font-size:12px;color:#374151;margin-top:4px">' . $h($cu) . '</div>'
            . '</td>'
            . '<td style="padding:12px;border-top:1px solid #e5e7eb;text-align:right">' . $qrHtml . '</td>'
            . '</tr>';
        $filasTxt .= '- Asiento ' . $b['codigo_asiento'] . ' (' . $etiquetaTipo($b['tipo_boleto']) . ', $'
            . number_format((float) $b['precio_final'], 2) . ') código ' . $cu . "\n";
    }

    $subject = 'Tus boletos: ' . $d['titulo'] . ($fecha !== '' ? ' — ' . $fecha : '');

    $html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $h($subject) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f3f4f6;font-family:Segoe UI,Arial,sans-serif;color:#111827">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 0"><tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden">'
        . '<tr><td style="background:#111827;color:#ffffff;padding:20px 24px">'
        . '<div style="font-size:13px;letter-spacing:1px;text-transform:uppercase;color:#d1d5db">Teatro Constitución</div>'
        . '<div style="font-size:22px;font-weight:700;margin-top:4px">¡Compra confirmada!</div>'
        . '</td></tr>'
        . '<tr><td style="padding:20px 24px">'
        . '<p style="margin:0 0 12px">Hola' . ($nombre !== '' ? ' ' . $h($nombre) : '') . ', tu pago fue confirmado. Estos son tus boletos:</p>'
        . '<div style="font-size:20px;font-weight:700">' . $h($d['titulo']) . '</div>'
        . ($fecha !== '' ? '<div style="color:#374151;margin-top:2px">' . $h($fecha) . '</div>' : '')
        . '<div style="color:#6b7280;font-size:13px;margin-top:6px">Orden ' . $h($codigo) . ' · Total ' . $h($total) . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:0 12px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $filasHtml . '</table></td></tr>'
        . '<tr><td style="padding:20px 24px">'
        . '<p style="margin:0 0 16px">Presenta el código QR de cada boleto en la entrada, en el celular o impreso. '
        . 'También puedes ver y descargar tus boletos en cualquier momento:</p>'
        . '<a href="' . $h($url) . '" style="display:inline-block;background:#b91c1c;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:8px">Ver mis boletos</a>'
        . '<p style="margin:16px 0 0;color:#6b7280;font-size:12px">Cada QR es válido para una sola entrada. No compartas este correo.</p>'
        . '</td></tr>'
        . '<tr><td style="padding:14px 24px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:12px">'
        . 'Dudas, reembolsos o factura: <a href="mailto:' . $h(LEGAL_CORREO) . '" style="color:#374151">' . $h(LEGAL_CORREO) . '</a> '
        . 'indicando tu orden ' . $h($codigo) . '. '
        . '<a href="' . $h($urlTerminos) . '" style="color:#374151">Política de reembolsos</a> · '
        . '<a href="' . $h($urlPrivacidad) . '" style="color:#374151">Aviso de privacidad</a>'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';

    $text = "Teatro Constitución — Compra confirmada\n\n"
        . 'Hola' . ($nombre !== '' ? ' ' . $nombre : '') . ", tu pago fue confirmado.\n\n"
        . $d['titulo'] . "\n" . ($fecha !== '' ? $fecha . "\n" : '')
        . 'Orden ' . $codigo . ' · Total ' . $total . "\n\n"
        . $filasTxt . "\n"
        . "Ver y descargar tus boletos (con QR):\n" . $url . "\n\n"
        . "Cada QR es válido para una sola entrada. No compartas este correo.\n\n"
        . 'Dudas, reembolsos o factura: ' . LEGAL_CORREO . ' indicando tu orden ' . $codigo . ".\n"
        . 'Política de reembolsos: ' . $urlTerminos . "\n"
        . 'Aviso de privacidad: ' . $urlPrivacidad . "\n";

    return [
        'to' => (string) $o['email'],
        'to_name' => $nombre,
        'subject' => $subject,
        'html' => $html,
        'text' => $text,
        'inline' => $inline,
    ];
}

/**
 * Reclama la notificación para este proceso. Solo el que la reclama envía.
 */
function correo_reclamar(mysqli $conn, int $idOrden, string $tipo, bool $forzar): bool
{
    $ins = $conn->prepare("INSERT IGNORE INTO orden_notificaciones (id_orden, tipo, estado) VALUES (?, ?, 'pendiente')");
    $ins->bind_param('is', $idOrden, $tipo);
    $ins->execute();
    $ins->close();

    $estados = $forzar ? "estado IN ('pendiente','fallido','enviado')" : "estado IN ('pendiente','fallido')";
    $vence = (int) CORREO_RECLAMO_VENCE_MIN;
    $up = $conn->prepare("
        UPDATE orden_notificaciones
        SET estado = 'enviando', intentos = intentos + 1, actualizado_en = NOW()
        WHERE id_orden = ? AND tipo = ?
          AND ($estados OR (estado = 'enviando' AND actualizado_en < NOW() - INTERVAL $vence MINUTE))
    ");
    $up->bind_param('is', $idOrden, $tipo);
    $up->execute();
    $reclamada = $up->affected_rows === 1;
    $up->close();
    return $reclamada;
}

/**
 * Envía el correo de confirmación si la orden está pagada y con todos sus boletos.
 * $forzar = reenvío manual desde admin (aunque ya se haya enviado).
 *
 * @return array{ok:bool, estado:string, error?:string, archivo?:string}
 */
function correo_enviar_confirmacion_orden(mysqli $conn, int $idOrden, bool $forzar = false): array
{
    $mailer = correo_resolver_mailer();
    if ($mailer === null) {
        return ['ok' => false, 'estado' => 'desactivado', 'error' => 'Correo desactivado (MAIL_MODE)'];
    }
    asegurarTablaNotificaciones($conn);
    require_once dirname(__DIR__) . '/emision_helper.php';

    $datos = correo_datos_orden($conn, $idOrden);
    if (!$datos) {
        return ['ok' => false, 'estado' => 'omitido', 'error' => 'Orden no encontrada'];
    }
    if ((string) $datos['orden']['estado'] !== 'pagada') {
        return ['ok' => false, 'estado' => 'omitido', 'error' => 'La orden no está pagada'];
    }
    if (!emision_orden_completa($conn, $idOrden) || !$datos['boletos']) {
        return ['ok' => false, 'estado' => 'omitido', 'error' => 'La orden aún no tiene todos sus boletos'];
    }

    $tipo = CORREO_TIPO_CONFIRMACION;
    if (!correo_reclamar($conn, $idOrden, $tipo, $forzar)) {
        return ['ok' => false, 'estado' => 'en_curso_o_enviado', 'error' => 'Ya enviado o en envío por otro proceso'];
    }

    $destino = (string) $datos['orden']['email'];
    try {
        $r = $mailer->enviar(correo_confirmacion_mensaje($datos));
    } catch (Throwable $e) {
        $r = ['ok' => false, 'error' => $e->getMessage()];
    }

    $proveedor = $mailer->nombre();
    if (!empty($r['ok'])) {
        $mid = mb_substr((string) ($r['message_id'] ?? ''), 0, 190);
        $up = $conn->prepare("
            UPDATE orden_notificaciones
            SET estado = 'enviado', proveedor = ?, destinatario = ?, message_id = ?,
                ultimo_error = NULL, enviado_en = NOW()
            WHERE id_orden = ? AND tipo = ?
        ");
        $up->bind_param('sssis', $proveedor, $destino, $mid, $idOrden, $tipo);
        $up->execute();
        $up->close();
        error_log('[correo] confirmación orden ' . $idOrden . ' → ' . correo_mascara_email($destino) . ' (' . $proveedor . ')');
        $out = ['ok' => true, 'estado' => 'enviado'];
        if (!empty($r['archivo'])) {
            $out['archivo'] = (string) $r['archivo'];
        }
        return $out;
    }

    $err = mb_substr((string) ($r['error'] ?? 'Error desconocido'), 0, 500);
    $up = $conn->prepare("
        UPDATE orden_notificaciones
        SET estado = 'fallido', proveedor = ?, destinatario = ?, ultimo_error = ?
        WHERE id_orden = ? AND tipo = ?
    ");
    $up->bind_param('sssis', $proveedor, $destino, $err, $idOrden, $tipo);
    $up->execute();
    $up->close();
    error_log('[correo] fallo confirmación orden ' . $idOrden . ': ' . $err);
    return ['ok' => false, 'estado' => 'fallido', 'error' => $err];
}

/**
 * Programa el envío para después de responder (webhook de MP / página de la orden no esperan
 * al SMTP). Usa una conexión propia porque el script pudo cerrar la suya.
 */
function correo_programar_confirmacion(int $idOrden): void
{
    static $programadas = [];
    if ($idOrden <= 0 || isset($programadas[$idOrden]) || correo_modo() === 'off') {
        return;
    }
    $programadas[$idOrden] = true;

    register_shutdown_function(static function () use ($idOrden): void {
        if (PHP_SAPI !== 'cli' && function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        try {
            require_once dirname(__DIR__, 2) . '/config/database.php';
            $c = getLocalConnection();
            if (!$c) {
                error_log('[correo] sin BD para enviar confirmación de orden ' . $idOrden);
                return;
            }
            correo_enviar_confirmacion_orden($c, $idOrden);
            $c->close();
        } catch (Throwable $e) {
            error_log('[correo] envío diferido orden ' . $idOrden . ': ' . $e->getMessage());
        }
    });
}

/**
 * Reintentos: órdenes pagadas y completas de las últimas $horas cuya función no ha pasado,
 * sin correo enviado y con menos de CORREO_MAX_INTENTOS_AUTO intentos.
 * No toca órdenes anteriores a la ventana (no manda correos viejos al activar el servicio).
 *
 * @return array{revisadas:int, enviadas:int, fallidas:int, detalle:list<array>}
 */
function correo_enviar_pendientes(mysqli $conn, int $horas = 48, int $limite = 25): array
{
    asegurarTablaNotificaciones($conn);
    $tipo = CORREO_TIPO_CONFIRMACION;
    $max = (int) CORREO_MAX_INTENTOS_AUTO;
    $horas = max(1, $horas);
    $limite = max(1, $limite);
    $st = $conn->prepare("
        SELECT o.id_orden
        FROM ordenes o
        INNER JOIN funciones f ON f.id_funcion = o.id_funcion
        LEFT JOIN orden_notificaciones n ON n.id_orden = o.id_orden AND n.tipo = ?
        WHERE o.estado = 'pagada'
          AND o.actualizado_en >= NOW() - INTERVAL ? HOUR
          AND f.fecha_hora > NOW()
          AND NOT EXISTS (SELECT 1 FROM orden_items oi WHERE oi.id_orden = o.id_orden AND oi.id_boleto IS NULL)
          AND (n.id_notificacion IS NULL OR (n.estado IN ('pendiente','fallido','enviando') AND n.intentos < ?))
        ORDER BY o.id_orden ASC
        LIMIT ?
    ");
    $st->bind_param('siii', $tipo, $horas, $max, $limite);
    $st->execute();
    $ids = array_map('intval', array_column($st->get_result()->fetch_all(MYSQLI_ASSOC), 'id_orden'));
    $st->close();

    $out = ['revisadas' => count($ids), 'enviadas' => 0, 'fallidas' => 0, 'detalle' => []];
    foreach ($ids as $id) {
        $r = correo_enviar_confirmacion_orden($conn, $id);
        if ($r['ok']) {
            $out['enviadas']++;
        } elseif ($r['estado'] === 'fallido') {
            $out['fallidas']++;
        }
        $out['detalle'][] = ['id_orden' => $id] + $r;
    }
    return $out;
}

function correo_estado_notificacion(mysqli $conn, int $idOrden, string $tipo = CORREO_TIPO_CONFIRMACION): ?array
{
    asegurarTablaNotificaciones($conn);
    $st = $conn->prepare('
        SELECT estado, proveedor, intentos, ultimo_error, enviado_en, actualizado_en
        FROM orden_notificaciones WHERE id_orden = ? AND tipo = ? LIMIT 1
    ');
    $st->bind_param('is', $idOrden, $tipo);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}
