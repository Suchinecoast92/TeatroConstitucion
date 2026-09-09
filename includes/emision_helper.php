<?php
/**
 * Emisión de boletos/QR para órdenes online pagadas.
 * Misma tabla `boletos` y mismo `codigo_unico` que taquilla (entrada compatible).
 */

if (defined('EMISION_HELPER_INCLUDED')) {
    return;
}
define('EMISION_HELPER_INCLUDED', true);

require_once __DIR__ . '/ordenes_helper.php';
require_once dirname(__DIR__) . '/sync/reservas_helper.php';

/**
 * Asegura columna boletos.origen (local|online).
 */
function asegurar_origen_boletos(mysqli $conn): void
{
    static $ok = false;
    if ($ok) {
        return;
    }
    $r = @$conn->query("SHOW COLUMNS FROM boletos LIKE 'origen'");
    if ($r && $r->num_rows === 0) {
        @$conn->query("
            ALTER TABLE boletos
            ADD COLUMN origen ENUM('local','online') NOT NULL DEFAULT 'local'
            AFTER tipo_boleto
        ");
        @$conn->query("
            UPDATE boletos b
            INNER JOIN orden_items oi ON oi.id_boleto = b.id_boleto
            SET b.origen = 'online'
            WHERE b.origen = 'local'
        ");
    }
    $ok = true;
}

/**
 * Carga Endroid QR desde el vendor de taquilla (vnt_interfaz).
 */
function emision_cargar_qr_autoload(): bool
{
    static $loaded = null;
    if ($loaded !== null) {
        return $loaded;
    }
    $autoload = dirname(__DIR__) . '/vnt_interfaz/vendor/autoload.php';
    if (!is_file($autoload)) {
        $loaded = false;
        return false;
    }
    require_once $autoload;
    $loaded = true;
    return true;
}

/**
 * Resuelve o crea id_asiento a partir del código (misma lógica que procesar_compra).
 */
function emision_resolver_id_asiento(mysqli $conn, string $codigoAsiento): int
{
    $st = $conn->prepare('SELECT id_asiento FROM asientos WHERE codigo_asiento = ? LIMIT 1');
    $st->bind_param('s', $codigoAsiento);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if ($row) {
        return (int) $row['id_asiento'];
    }

    preg_match('/^([A-Z]+\d*)[-]?(\d+)$/i', $codigoAsiento, $matches);
    $fila = isset($matches[1]) ? strtoupper($matches[1]) : strtoupper(substr($codigoAsiento, 0, 1));
    $numero = isset($matches[2]) ? (int) $matches[2] : (int) filter_var($codigoAsiento, FILTER_SANITIZE_NUMBER_INT);

    $ins = $conn->prepare('INSERT INTO asientos (codigo_asiento, fila, numero) VALUES (?, ?, ?)');
    $ins->bind_param('ssi', $codigoAsiento, $fila, $numero);
    if (!$ins->execute()) {
        $err = $ins->error;
        $ins->close();
        throw new RuntimeException("No se pudo crear asiento $codigoAsiento: $err");
    }
    $id = (int) $conn->insert_id;
    $ins->close();
    return $id;
}

/**
 * Genera PNG del QR en boletos_qr/{codigo_unico}.png. No falla la venta si el QR falla.
 */
function emision_generar_qr_png(string $codigoUnico): bool
{
    if (!emision_cargar_qr_autoload()) {
        error_log('[emision] vendor Endroid no disponible; omitiendo QR de ' . $codigoUnico);
        return false;
    }
    try {
        $qrDir = dirname(__DIR__) . '/boletos_qr/';
        if (!is_dir($qrDir)) {
            mkdir($qrDir, 0777, true);
        }
        $qrPath = $qrDir . $codigoUnico . '.png';

        $qrCode = \Endroid\QrCode\QrCode::create($codigoUnico)
            ->setEncoding(new \Endroid\QrCode\Encoding\Encoding('UTF-8'))
            ->setErrorCorrectionLevel(new \Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow())
            ->setSize(300)
            ->setMargin(10)
            ->setRoundBlockSizeMode(new \Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin());

        $writer = new \Endroid\QrCode\Writer\PngWriter();
        $writer->write($qrCode)->saveToFile($qrPath);
        return true;
    } catch (Throwable $e) {
        error_log('[emision] QR ' . $codigoUnico . ': ' . $e->getMessage());
        return false;
    }
}

/**
 * Indica si la orden ya tiene todos los items con boleto emitido.
 */
function emision_orden_completa(mysqli $conn, int $idOrden): bool
{
    $st = $conn->prepare('SELECT COUNT(*) AS total, SUM(id_boleto IS NOT NULL) AS con_boleto FROM orden_items WHERE id_orden = ?');
    $st->bind_param('i', $idOrden);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    $total = (int) ($row['total'] ?? 0);
    $con = (int) ($row['con_boleto'] ?? 0);
    return $total > 0 && $total === $con;
}

/**
 * Emite boletos para una orden pagada. Idempotente: no duplica si ya hay id_boleto.
 *
 * @return array{success:bool, emitidos:int, ya_emitidos:int, boletos:array, error?:string}
 */
function emitir_boletos_orden_pagada(mysqli $conn, int $idOrden): array
{
    asegurarTablasOrdenes($conn);
    asegurar_origen_boletos($conn);

    $st = $conn->prepare('SELECT * FROM ordenes WHERE id_orden = ? LIMIT 1');
    $st->bind_param('i', $idOrden);
    $st->execute();
    $orden = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$orden) {
        return ['success' => false, 'emitidos' => 0, 'ya_emitidos' => 0, 'boletos' => [], 'error' => 'Orden no encontrada'];
    }

    if ((string) $orden['estado'] !== 'pagada') {
        return ['success' => false, 'emitidos' => 0, 'ya_emitidos' => 0, 'boletos' => [], 'error' => 'Orden no está pagada'];
    }

    if (emision_orden_completa($conn, $idOrden)) {
        $existentes = emision_listar_boletos_orden($conn, $idOrden);
        return [
            'success' => true,
            'emitidos' => 0,
            'ya_emitidos' => count($existentes),
            'boletos' => $existentes,
            'idempotent' => true,
        ];
    }

    $si = $conn->prepare('SELECT * FROM orden_items WHERE id_orden = ? ORDER BY id_item ASC');
    $si->bind_param('i', $idOrden);
    $si->execute();
    $items = [];
    $res = $si->get_result();
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $si->close();

    if (!$items) {
        return ['success' => false, 'emitidos' => 0, 'ya_emitidos' => 0, 'boletos' => [], 'error' => 'Orden sin items'];
    }

    $idEvento = (int) $orden['id_evento'];
    $idFuncion = (int) $orden['id_funcion'];
    $sessionId = (string) $orden['session_id'];
    $emitidos = 0;
    $ya = 0;
    $boletosOut = [];
    $codigosLiberar = [];
    $qrsPendientes = [];

    $startedTx = false;
    try {
        if (!$conn->begin_transaction()) {
            throw new RuntimeException('No se pudo iniciar transacción');
        }
        $startedTx = true;

        foreach ($items as $it) {
            $idItem = (int) $it['id_item'];
            if (!empty($it['id_boleto'])) {
                $ya++;
                $codigosLiberar[] = $it['codigo_asiento'];
                continue;
            }

            $codigoAsiento = (string) $it['codigo_asiento'];
            $idAsiento = emision_resolver_id_asiento($conn, $codigoAsiento);
            $idCategoria = $it['id_categoria'] !== null ? (int) $it['id_categoria'] : null;
            $tipo = (string) ($it['tipo_boleto'] ?: 'adulto');
            $precioBase = (float) $it['precio_base'];
            $descuento = (float) $it['descuento_aplicado'];
            $precioFinal = (float) $it['precio_final'];
            $idPromo = $it['id_promocion'] !== null ? (int) $it['id_promocion'] : null;

            if ($idCategoria === null || $idCategoria <= 0) {
                $cat = resolver_categoria_asiento($conn, $idEvento, $codigoAsiento);
                $idCategoria = $cat ? (int) $cat['id_categoria'] : 0;
            }
            if ($idCategoria <= 0) {
                throw new RuntimeException("Sin categoría para asiento $codigoAsiento");
            }

            // ¿Ya hay boleto para este asiento/función?
            $chk = $conn->prepare('
                SELECT id_boleto, estatus, codigo_unico
                FROM boletos
                WHERE id_evento = ? AND id_funcion = ? AND id_asiento = ?
                LIMIT 1
                FOR UPDATE
            ');
            $chk->bind_param('iii', $idEvento, $idFuncion, $idAsiento);
            $chk->execute();
            $exist = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($exist && (int) $exist['estatus'] === 1) {
                $idBolExist = (int) $exist['id_boleto'];
                // Solo reutilizar si YA pertenece a esta orden (reintento parcial).
                // Nunca "reclamar" un boleto activo de taquilla u otra orden.
                $own = $conn->prepare('SELECT id_item FROM orden_items WHERE id_boleto = ? AND id_orden = ? LIMIT 1');
                $own->bind_param('ii', $idBolExist, $idOrden);
                $own->execute();
                $mine = $own->get_result()->fetch_assoc();
                $own->close();
                if ($mine) {
                    if (empty($it['id_boleto'])) {
                        $link = $conn->prepare('UPDATE orden_items SET id_boleto = ? WHERE id_item = ? AND id_boleto IS NULL');
                        $link->bind_param('ii', $idBolExist, $idItem);
                        $link->execute();
                        $link->close();
                    }
                    $ya++;
                    $codigosLiberar[] = $codigoAsiento;
                    continue;
                }
                throw new RuntimeException("Asiento $codigoAsiento ya vendido");
            }

            $codigoUnico = strtoupper(bin2hex(random_bytes(8)));

            if ($exist) {
                // Reutilizar cancelado/usado
                $idBoleto = (int) $exist['id_boleto'];
                if ($idPromo) {
                    $up = $conn->prepare('
                        UPDATE boletos SET
                            id_categoria = ?, id_promocion = ?, codigo_unico = ?,
                            precio_base = ?, descuento_aplicado = ?, precio_final = ?,
                            tipo_boleto = ?, origen = \'online\', id_usuario = NULL, fecha_compra = NOW(), estatus = 1
                        WHERE id_boleto = ?
                    ');
                    $up->bind_param(
                        'iisdddsi',
                        $idCategoria,
                        $idPromo,
                        $codigoUnico,
                        $precioBase,
                        $descuento,
                        $precioFinal,
                        $tipo,
                        $idBoleto
                    );
                } else {
                    $up = $conn->prepare('
                        UPDATE boletos SET
                            id_categoria = ?, id_promocion = NULL, codigo_unico = ?,
                            precio_base = ?, descuento_aplicado = ?, precio_final = ?,
                            tipo_boleto = ?, origen = \'online\', id_usuario = NULL, fecha_compra = NOW(), estatus = 1
                        WHERE id_boleto = ?
                    ');
                    $up->bind_param(
                        'isdddsi',
                        $idCategoria,
                        $codigoUnico,
                        $precioBase,
                        $descuento,
                        $precioFinal,
                        $tipo,
                        $idBoleto
                    );
                }
                if (!$up->execute()) {
                    $err = $up->error;
                    $up->close();
                    throw new RuntimeException("Error al actualizar boleto: $err");
                }
                $up->close();
            } else {
                if ($idPromo) {
                    $ins = $conn->prepare('
                        INSERT INTO boletos (
                            id_evento, id_funcion, id_asiento, id_categoria, id_promocion,
                            codigo_unico, precio_base, descuento_aplicado, precio_final,
                            tipo_boleto, origen, id_usuario, fecha_compra, estatus
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'online\', NULL, NOW(), 1)
                    ');
                    $ins->bind_param(
                        'iiiiisddds',
                        $idEvento,
                        $idFuncion,
                        $idAsiento,
                        $idCategoria,
                        $idPromo,
                        $codigoUnico,
                        $precioBase,
                        $descuento,
                        $precioFinal,
                        $tipo
                    );
                } else {
                    $ins = $conn->prepare('
                        INSERT INTO boletos (
                            id_evento, id_funcion, id_asiento, id_categoria,
                            codigo_unico, precio_base, descuento_aplicado, precio_final,
                            tipo_boleto, origen, id_usuario, fecha_compra, estatus
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'online\', NULL, NOW(), 1)
                    ');
                    $ins->bind_param(
                        'iiiisddds',
                        $idEvento,
                        $idFuncion,
                        $idAsiento,
                        $idCategoria,
                        $codigoUnico,
                        $precioBase,
                        $descuento,
                        $precioFinal,
                        $tipo
                    );
                }
                if (!$ins->execute()) {
                    $err = $ins->error;
                    $ins->close();
                    throw new RuntimeException("Error al crear boleto: $err");
                }
                $idBoleto = (int) $conn->insert_id;
                $ins->close();
            }

            $lk = $conn->prepare('UPDATE orden_items SET id_boleto = ? WHERE id_item = ? AND id_boleto IS NULL');
            $lk->bind_param('ii', $idBoleto, $idItem);
            $lk->execute();
            $lk->close();

            $emitidos++;
            $codigosLiberar[] = $codigoAsiento;
            $qrsPendientes[] = $codigoUnico;
            $boletosOut[] = [
                'id_boleto' => $idBoleto,
                'codigo_asiento' => $codigoAsiento,
                'codigo_unico' => $codigoUnico,
                'precio_final' => $precioFinal,
                'tipo_boleto' => $tipo,
            ];
        }

        $conn->commit();
        $startedTx = false;
    } catch (Throwable $e) {
        if ($startedTx) {
            $conn->rollback();
        }
        error_log('[emision] orden ' . $idOrden . ': ' . $e->getMessage());
        // Evitar que otro canal venda el asiento mientras se reintenta emisión / reembolso
        $prot = proteger_asientos_orden_sin_boleto($conn, $idOrden, 86400);
        return [
            'success' => false,
            'emitidos' => 0,
            'ya_emitidos' => $ya,
            'boletos' => [],
            'error' => $e->getMessage(),
            'holds_protegidos' => (int) ($prot['protegidos'] ?? 0),
            'holds_conflictos' => $prot['conflictos'] ?? [],
        ];
    }

    foreach ($qrsPendientes as $codigo) {
        emision_generar_qr_png($codigo);
    }

    if ($codigosLiberar) {
        liberarTrasVenta($sessionId, $idEvento, $idFuncion, $codigosLiberar);
    }
    // Liberar cualquier hold sobrante de la misma sesión (asientos apartados y no comprados)
    if ($sessionId !== '') {
        liberarReservasSesion($sessionId);
    }

    $todos = emision_listar_boletos_orden($conn, $idOrden);

    if ($emitidos > 0) {
        $asientosNotify = array_values(array_unique(array_column($todos, 'codigo_asiento')));
        if (!$asientosNotify) {
            $asientosNotify = $codigosLiberar;
        }
        emision_notificar_venta($conn, $idEvento, $idFuncion, $asientosNotify);
    }

    // Si quedó pagada a medias (algún item sin boleto), proteger esos asientos
    if (!emision_orden_completa($conn, $idOrden)) {
        proteger_asientos_orden_sin_boleto($conn, $idOrden, 86400);
    }

    return [
        'success' => true,
        'emitidos' => $emitidos,
        'ya_emitidos' => $ya,
        'boletos' => $todos ?: $boletosOut,
    ];
}

/**
 * Orden pagada sin boleto(s): re-aparte asientos libres bajo la sesión de la orden
 * para que no se revendan (taquilla/online) hasta reemitir o reembolsar.
 *
 * @return array{protegidos:int,conflictos:array<string,string>,error?:string}
 */
function proteger_asientos_orden_sin_boleto(mysqli $conn, int $idOrden, int $ttlSeg = 86400): array
{
    $st = $conn->prepare('SELECT id_orden, session_id, id_evento, id_funcion, estado FROM ordenes WHERE id_orden = ? LIMIT 1');
    $st->bind_param('i', $idOrden);
    $st->execute();
    $orden = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$orden || (string) $orden['estado'] !== 'pagada') {
        return ['protegidos' => 0, 'conflictos' => [], 'error' => 'orden no pagada'];
    }

    $si = $conn->prepare('SELECT codigo_asiento FROM orden_items WHERE id_orden = ? AND id_boleto IS NULL');
    $si->bind_param('i', $idOrden);
    $si->execute();
    $codigos = [];
    $res = $si->get_result();
    while ($row = $res->fetch_assoc()) {
        $codigos[] = (string) $row['codigo_asiento'];
    }
    $si->close();
    if (!$codigos) {
        return ['protegidos' => 0, 'conflictos' => []];
    }

    $sessionId = (string) $orden['session_id'];
    if ($sessionId === '') {
        $sessionId = 'pago_sin_boleto_' . $idOrden;
        $up = $conn->prepare('UPDATE ordenes SET session_id = ? WHERE id_orden = ? AND (session_id IS NULL OR session_id = \'\')');
        $up->bind_param('si', $sessionId, $idOrden);
        $up->execute();
        $up->close();
    }

    $ttlSeg = max(3600, (int) $ttlSeg);
    $r = reservarAsientos(
        (int) $orden['id_evento'],
        (int) $orden['id_funcion'],
        $codigos,
        $sessionId,
        'online',
        'pago-sin-boleto',
        $ttlSeg
    );

    return [
        'protegidos' => count($r['reservados'] ?? []),
        'conflictos' => $r['conflictos'] ?? [],
    ];
}

/**
 * Registra cambio en tiempo real para que taquilla/cartelera refresquen el mapa.
 */
function emision_notificar_venta(mysqli $conn, int $idEvento, int $idFuncion, array $asientos): void
{
    if (!$asientos) {
        return;
    }
    $path = dirname(__DIR__) . '/api/registrar_cambio.php';
    if (!is_file($path)) {
        return;
    }
    require_once $path;
    $prev = $GLOBALS['conn'] ?? null;
    $GLOBALS['conn'] = $conn;
    try {
        registrar_cambio('venta', $idEvento, $idFuncion, [
            'asientos' => $asientos,
            'cantidad' => count($asientos),
            'origen' => 'online',
        ]);
    } catch (Throwable $e) {
        error_log('[emision] registrar_cambio: ' . $e->getMessage());
    } finally {
        if ($prev !== null) {
            $GLOBALS['conn'] = $prev;
        }
    }
}

/**
 * @return list<array{id_boleto:int,codigo_asiento:string,codigo_unico:string,precio_final:float,tipo_boleto:string}>
 */
function emision_listar_boletos_orden(mysqli $conn, int $idOrden): array
{
    $st = $conn->prepare('
        SELECT oi.codigo_asiento, oi.tipo_boleto, oi.precio_final, oi.id_boleto,
               b.codigo_unico
        FROM orden_items oi
        INNER JOIN boletos b ON b.id_boleto = oi.id_boleto
        WHERE oi.id_orden = ?
        ORDER BY oi.id_item ASC
    ');
    $st->bind_param('i', $idOrden);
    $st->execute();
    $res = $st->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = [
            'id_boleto' => (int) $row['id_boleto'],
            'codigo_asiento' => (string) $row['codigo_asiento'],
            'codigo_unico' => (string) $row['codigo_unico'],
            'precio_final' => (float) $row['precio_final'],
            'tipo_boleto' => (string) $row['tipo_boleto'],
        ];
    }
    $st->close();
    return $out;
}
