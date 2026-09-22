<?php
/**
 * Helpers compartidos para scripts sql/test_*.php
 */

require_once dirname(__DIR__) . '/config/ventas.php';
require_once dirname(__DIR__) . '/includes/ordenes_helper.php';
require_once dirname(__DIR__) . '/sync/reservas_helper.php';

/**
 * Datos de cliente válidos según validar_datos_cliente_online().
 * @return array{nombre:string,email:string,telefono:string}
 */
function test_cliente_valido(string $emailLocal = 'test'): array
{
    return [
        'nombre' => 'Prueba Automatizada',
        'email' => $emailLocal . '@example.com',
        'telefono' => '5512345678',
    ];
}

/**
 * Elige evento/función con venta aún abierta (misma regla que crearOrdenOnline).
 * @return array{id_evento:int,id_funcion:int}|null
 */
function test_pick_funcion_vendible(mysqli $conn): ?array
{
    $horas = defined('HORAS_CIERRE_VENTAS_POST_FUNCION') ? (int) HORAS_CIERRE_VENTAS_POST_FUNCION : 24;
    $sql = "
        SELECT f.id_funcion, f.id_evento
        FROM funciones f
        INNER JOIN evento e ON e.id_evento = f.id_evento
        WHERE e.finalizado = 0
          AND f.estado = 0
          AND f.fecha_hora > (NOW() - INTERVAL {$horas} HOUR)
        ORDER BY f.fecha_hora DESC
        LIMIT 20
    ";
    $res = $conn->query($sql);
    if (!$res) {
        return null;
    }
    while ($row = $res->fetch_assoc()) {
        return [
            'id_evento' => (int) $row['id_evento'],
            'id_funcion' => (int) $row['id_funcion'],
        ];
    }
    return null;
}

/**
 * Busca un asiento libre vendible. Prefiere la fila indicada, luego A–F.
 */
function test_pick_asiento_libre(mysqli $conn, int $idEvento, int $idFuncion, string $filaPreferida = 'B'): ?string
{
    $disp = obtenerDisponibilidadFuncion($idEvento, $idFuncion, null, $conn);
    $ocupados = array_flip($disp['ocupados'] ?? []);
    $filas = array_values(array_unique(array_merge(
        [strtoupper(substr($filaPreferida, 0, 1))],
        range('A', 'F')
    )));

    foreach ($filas as $fila) {
        for ($n = 1; $n <= 30; $n++) {
            $c = $fila . $n;
            if (isset($ocupados[$c])) {
                continue;
            }
            $cat = resolver_categoria_asiento($conn, $idEvento, $c);
            if ($cat && !es_categoria_no_venta($cat['nombre_categoria'])) {
                return $c;
            }
        }
    }
    return null;
}

/**
 * Contexto listo para crear orden de prueba.
 * @return array{id_evento:int,id_funcion:int,asiento:string,session:string,cliente:array}
 */
function test_contexto_orden(mysqli $conn, string $fila = 'B', string $sessionPrefix = 'test_'): array
{
    $pick = test_pick_funcion_vendible($conn);
    if (!$pick) {
        fwrite(STDERR, "FAIL sin función con venta abierta\n");
        exit(1);
    }
    $asiento = test_pick_asiento_libre($conn, $pick['id_evento'], $pick['id_funcion'], $fila);
    if (!$asiento) {
        fwrite(STDERR, "FAIL sin asiento libre\n");
        exit(1);
    }
    return [
        'id_evento' => $pick['id_evento'],
        'id_funcion' => $pick['id_funcion'],
        'asiento' => $asiento,
        'session' => $sessionPrefix . bin2hex(random_bytes(3)),
        'cliente' => test_cliente_valido($sessionPrefix . 'user'),
    ];
}
