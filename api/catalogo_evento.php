<?php
/**
 * Catálogo de precios y categorías de un evento (para sincronización en vivo).
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/catalogo_boletos_helper.php';

$id_evento = isset($_GET['id_evento']) ? (int) $_GET['id_evento'] : 0;

if ($id_evento <= 0) {
    echo json_encode(['success' => false, 'error' => 'id_evento requerido']);
    exit;
}

try {
    $categorias = [];
    $stmt = $conn->prepare('SELECT id_categoria, nombre_categoria, precio, color FROM categorias WHERE id_evento = ? ORDER BY precio ASC');
    $stmt->bind_param('i', $id_evento);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $id = (int) $row['id_categoria'];
        $categorias[$id] = [
            'nombre' => $row['nombre_categoria'],
            'precio' => (float) $row['precio'],
            'color'  => $row['color'],
        ];
    }
    $stmt->close();

    $precios_tipo = [
        'adulto'         => 80,
        'general'        => 80,
        'nino'           => 50,
        'adulto_mayor'   => 60,
        'discapacitado'  => 40,
        'cortesia'       => 0,
    ];

    $mapa_cat_tipo = [
        'General'       => 'general',
        'Niño'          => 'nino',
        'Nino'          => 'nino',
        '3ra Edad'      => 'adulto_mayor',
        'Adulto Mayor'  => 'adulto_mayor',
        'Discapacitado' => 'discapacitado',
    ];

    foreach ($categorias as $cat) {
        $nombre = $cat['nombre'];
        if (isset($mapa_cat_tipo[$nombre])) {
            $precios_tipo[$mapa_cat_tipo[$nombre]] = $cat['precio'];
        }
    }

    $check_table = $conn->query("SHOW TABLES LIKE 'precios_tipo_boleto'");
    if ($check_table && $check_table->num_rows > 0) {
        $stmt_p = $conn->prepare('SELECT tipo_boleto, precio FROM precios_tipo_boleto WHERE id_evento = ?');
        $stmt_p->bind_param('i', $id_evento);
        $stmt_p->execute();
        $res_p = $stmt_p->get_result();
        if ($res_p->num_rows > 0) {
            while ($row = $res_p->fetch_assoc()) {
                $precios_tipo[$row['tipo_boleto']] = (float) $row['precio'];
            }
        } else {
            $res_g = $conn->query('SELECT tipo_boleto, precio FROM precios_tipo_boleto WHERE id_evento IS NULL');
            if ($res_g) {
                while ($row = $res_g->fetch_assoc()) {
                    $precios_tipo[$row['tipo_boleto']] = (float) $row['precio'];
                }
            }
        }
        $stmt_p->close();

        // Las categorías del mapa tienen prioridad para tipos estándar (fuente del mapa de asientos)
        foreach ($categorias as $cat) {
            $nombre = $cat['nombre'];
            if (isset($mapa_cat_tipo[$nombre])) {
                $precios_tipo[$mapa_cat_tipo[$nombre]] = $cat['precio'];
            }
        }
    }

    echo json_encode([
        'success'      => true,
        'id_evento'    => $id_evento,
        'categorias'   => $categorias,
        'precios_tipo' => $precios_tipo,
        'config_tipos_boleto' => config_tipos_boleto_js($conn, $id_evento),
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
