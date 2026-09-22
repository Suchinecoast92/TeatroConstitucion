<?php
/**
 * Configuración de ventana de ventas post-función.
 * Las ventas permanecen abiertas hasta N horas después de cada función.
 */
if (defined('VENTAS_CONFIG_INCLUDED')) {
    return;
}
define('VENTAS_CONFIG_INCLUDED', true);

/** Horas después de la función en que aún se permite vender */
define('HORAS_CIERRE_VENTAS_POST_FUNCION', 24);

define('SEGUNDOS_CIERRE_VENTAS_POST_FUNCION', HORAS_CIERRE_VENTAS_POST_FUNCION * 3600);
define('MS_CIERRE_VENTAS_POST_FUNCION', SEGUNDOS_CIERRE_VENTAS_POST_FUNCION * 1000);

/**
 * Mensaje estándar cuando la ventana de venta de la función ya cerró.
 */
function teatro_mensaje_venta_cerrada(): string
{
    return 'La venta para esta función ya no está disponible';
}

/**
 * True si la función aún admite venta (misma ventana que taquilla / crear orden).
 * Criterio: evento no finalizado y fecha_hora > NOW() - HORAS_CIERRE_VENTAS_POST_FUNCION.
 */
function teatro_funcion_venta_abierta(mysqli $conn, int $idEvento, int $idFuncion): bool
{
    if ($idEvento <= 0 || $idFuncion <= 0) {
        return false;
    }
    $horas = (int) HORAS_CIERRE_VENTAS_POST_FUNCION;
    if ($horas < 0) {
        $horas = 0;
    }
    $sql = "
        SELECT
            (f.fecha_hora > (NOW() - INTERVAL {$horas} HOUR)) AS abierta,
            COALESCE(e.finalizado, 0) AS finalizado
        FROM funciones f
        INNER JOIN evento e ON e.id_evento = f.id_evento
        WHERE f.id_funcion = ? AND f.id_evento = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $idFuncion, $idEvento);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return false;
    }
    if ((int) $row['finalizado'] === 1) {
        return false;
    }
    return (int) $row['abierta'] === 1;
}
