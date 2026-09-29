<?php
// Crea un evento "[PRUEBA] …" con una función futura y venta abierta, para las pruebas de venta
// online/taquilla sin alterar eventos reales. Se borra con: php sql/limpiar_datos_prueba.php --aplicar
// Uso: php sql/crear_evento_prueba.php [días hasta la función, por defecto 7]
// Si ya existe uno con venta abierta, no crea otro. Se niega a correr con APP_ENV=production.

require_once __DIR__ . '/../includes/auth_guard.php';
teatro_require_cli();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ventas.php';

const EVENTO_PRUEBA_PREFIJO = '[PRUEBA]';

if (teatro_es_produccion()) {
    fwrite(STDERR, "No se ejecuta en producción.\n");
    exit(1);
}

$conn = getLocalConnection();
if (!$conn) {
    fwrite(STDERR, "Sin conexión a la BD.\n");
    exit(1);
}

$prefijo = EVENTO_PRUEBA_PREFIJO . '%';
$stmt = $conn->prepare("
    SELECT e.id_evento, e.titulo, MAX(f.fecha_hora) funcion
    FROM evento e JOIN funciones f ON f.id_evento = e.id_evento
    WHERE e.titulo LIKE ? AND e.finalizado = 0 AND f.estado = 0 AND f.fecha_hora > NOW()
    GROUP BY e.id_evento, e.titulo
");
$stmt->bind_param('s', $prefijo);
$stmt->execute();
if ($ya = $stmt->get_result()->fetch_assoc()) {
    echo "Ya existe: #{$ya['id_evento']} {$ya['titulo']} (función {$ya['funcion']})\n";
    exit(0);
}
$stmt->close();

$dias = max(1, (int) ($argv[1] ?? 7));
$funcion = date('Y-m-d 19:00:00', strtotime("+$dias days"));
$inicio = date('Y-m-d H:i:s', time() - 3600);
$cierre = date('Y-m-d H:i:s', strtotime($funcion) + SEGUNDOS_CIERRE_VENTAS_POST_FUNCION);
$titulo = EVENTO_PRUEBA_PREFIJO . ' Evento de pruebas';
$desc = 'Evento de desarrollo para probar venta online y taquilla. Se borra con sql/limpiar_datos_prueba.php.';
$precio = 150.0;
$tipo = 1;

$conn->begin_transaction();
try {
    $stmt = $conn->prepare('INSERT INTO evento (titulo, descripcion, imagen, tipo, inicio_venta, cierre_venta, finalizado) VALUES (?, ?, NULL, ?, ?, ?, 0)');
    $stmt->bind_param('ssiss', $titulo, $desc, $tipo, $inicio, $cierre);
    $stmt->execute();
    $idEvento = (int) $conn->insert_id;

    $stmt = $conn->prepare('INSERT INTO funciones (id_evento, fecha_hora, estado) VALUES (?, ?, 0)');
    $stmt->bind_param('is', $idEvento, $funcion);
    $stmt->execute();

    // Mismas categorías y mapa que crea evt_interfaz/crear_evento.php para un evento de pago.
    $stmt = $conn->prepare('INSERT INTO categorias (id_evento, nombre_categoria, precio, color) VALUES (?, ?, ?, ?)');
    $idGeneral = 0;
    foreach ([['General', $precio, '#cbd5e1'], ['Discapacitado', $precio, '#2563eb'], ['No Venta', 0.0, '#0f172a']] as [$nom, $p, $col]) {
        $stmt->bind_param('isds', $idEvento, $nom, $p, $col);
        $stmt->execute();
        $idGeneral = $idGeneral ?: (int) $conn->insert_id;
    }

    $mapa = [];
    foreach (range('A', 'O') as $l) {
        for ($a = 1; $a <= 26; $a++) {
            $mapa["$l$a"] = $idGeneral;
        }
    }
    for ($a = 1; $a <= 30; $a++) {
        $mapa["P$a"] = $idGeneral;
    }
    $json = json_encode($mapa);
    $stmt = $conn->prepare('UPDATE evento SET mapa_json = ? WHERE id_evento = ?');
    $stmt->bind_param('si', $json, $idEvento);
    $stmt->execute();

    $stmt = $conn->prepare('INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) VALUES (?, ?, ?, 0)');
    foreach (['general' => $precio, 'nino' => $precio, 'adulto_mayor' => $precio, 'discapacitado' => $precio, 'cortesia' => 0.0, 'adulto' => $precio] as $tb => $p) {
        $stmt->bind_param('isd', $idEvento, $tb, $p);
        $stmt->execute();
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, 'No se creó: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Creado: #$idEvento $titulo | función $funcion | venta abierta hasta $cierre | \$" . number_format($precio, 2) . "\n";
