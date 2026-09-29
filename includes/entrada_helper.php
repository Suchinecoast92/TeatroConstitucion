<?php
/**
 * Validación de acceso en la puerta: el boleto debe ser de una función que esté
 * por empezar o en curso (ventana ENTRADA_MINUTOS_* de config/ventas.php).
 * El tiempo se compara con NOW() de MySQL, igual que la venta.
 */

if (defined('ENTRADA_HELPER_INCLUDED')) {
    return;
}
define('ENTRADA_HELPER_INCLUDED', true);

require_once __DIR__ . '/../config/ventas.php';

/**
 * Datos del boleto para la puerta.
 *
 * @return array|null boleto + clave 'entrada' => {permitida, motivo, mensaje}
 */
function entrada_obtener_boleto(mysqli $conn, string $codigoUnico, bool $bloquear = false): ?array
{
    $sql = "
        SELECT
            b.id_boleto, b.codigo_unico, b.precio_final, b.estatus, b.id_evento, b.id_funcion,
            a.codigo_asiento, c.nombre_categoria,
            e.titulo AS evento_titulo, e.tipo AS evento_tipo,
            f.fecha_hora,
            TIMESTAMPDIFF(MINUTE, NOW(), f.fecha_hora) AS minutos_para_funcion
        FROM boletos b
        INNER JOIN asientos a ON b.id_asiento = a.id_asiento
        INNER JOIN evento e ON b.id_evento = e.id_evento
        LEFT JOIN categorias c ON b.id_categoria = c.id_categoria
        LEFT JOIN funciones f ON b.id_funcion = f.id_funcion
        WHERE b.codigo_unico = ?
        LIMIT 1
    " . ($bloquear ? ' FOR UPDATE' : '');
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $codigoUnico);
    $stmt->execute();
    $boleto = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$boleto) {
        return null;
    }
    $boleto['entrada'] = entrada_evaluar($boleto);
    return $boleto;
}

/**
 * @return array{permitida:bool,motivo:string,mensaje:string}
 */
function entrada_evaluar(array $boleto): array
{
    $estatus = (int) $boleto['estatus'];
    if ($estatus === 0) {
        return ['permitida' => false, 'motivo' => 'usado', 'mensaje' => 'Este boleto ya fue usado.'];
    }
    if ($estatus === 2) {
        return ['permitida' => false, 'motivo' => 'cancelado', 'mensaje' => 'Este boleto está cancelado.'];
    }
    if ($boleto['minutos_para_funcion'] === null) {
        // Boletos antiguos sin función asignada: no hay fecha contra la cual validar
        return ['permitida' => true, 'motivo' => 'sin_funcion', 'mensaje' => ''];
    }

    $min = (int) $boleto['minutos_para_funcion'];
    $fecha = entrada_fecha_legible((string) $boleto['fecha_hora']);
    if ($min > ENTRADA_MINUTOS_ANTES_FUNCION) {
        return ['permitida' => false, 'motivo' => 'funcion_futura',
            'mensaje' => "Este boleto es para una función posterior: $fecha."];
    }
    if ($min < -ENTRADA_MINUTOS_DESPUES_FUNCION) {
        return ['permitida' => false, 'motivo' => 'funcion_pasada',
            'mensaje' => "Este boleto es de una función que ya pasó: $fecha."];
    }
    return ['permitida' => true, 'motivo' => 'ok', 'mensaje' => ''];
}

function entrada_fecha_legible(string $fechaHora): string
{
    $ts = strtotime($fechaHora);
    if (!$ts) {
        return $fechaHora;
    }
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
        'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return sprintf(
        '%s %d de %s de %d, %s h',
        $dias[(int) date('w', $ts)],
        (int) date('j', $ts),
        $meses[(int) date('n', $ts) - 1],
        (int) date('Y', $ts),
        date('H:i', $ts)
    );
}

/**
 * Marca el boleto como usado. Fuera de la ventana solo procede con $forzar (queda en el log).
 *
 * @return array{success:bool,message:string,requiere_confirmacion?:bool}
 */
function entrada_confirmar(mysqli $conn, string $codigoUnico, bool $forzar, ?int $idUsuario): array
{
    $conn->begin_transaction();
    try {
        $boleto = entrada_obtener_boleto($conn, $codigoUnico, true);
        if (!$boleto) {
            $conn->rollback();
            return ['success' => false, 'message' => 'El boleto no existe.'];
        }
        $ent = $boleto['entrada'];
        if (in_array($ent['motivo'], ['usado', 'cancelado'], true)) {
            $conn->rollback();
            return ['success' => false, 'message' => $ent['mensaje']];
        }
        if (!$ent['permitida'] && !$forzar) {
            $conn->rollback();
            return ['success' => false, 'requiere_confirmacion' => true, 'motivo' => $ent['motivo'], 'message' => $ent['mensaje']];
        }

        $stmt = $conn->prepare('UPDATE boletos SET estatus = 0 WHERE id_boleto = ? AND estatus = 1');
        $id = (int) $boleto['id_boleto'];
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        if (!$ok) {
            $conn->rollback();
            return ['success' => false, 'message' => 'El boleto ya fue usado o no existe.'];
        }
        $conn->commit();

        if (!$ent['permitida']) {
            error_log(sprintf(
                '[Entrada] Acceso autorizado fuera de horario: boleto %d (%s), usuario %s, motivo %s',
                $id,
                $boleto['codigo_asiento'],
                $idUsuario ?? '-',
                $ent['motivo']
            ));
        }
        return ['success' => true, 'message' => 'Entrada confirmada exitosamente'];
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
