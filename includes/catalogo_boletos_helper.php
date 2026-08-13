<?php
/**
 * Catálogo de tipos de boleto y categorías por evento.
 * Solo muestra en UI los tipos/categorías que realmente existen en el evento.
 */
if (defined('CATALOGO_BOLETOS_HELPER_INCLUDED')) {
    return;
}
define('CATALOGO_BOLETOS_HELPER_INCLUDED', true);

/** Nombres de categoría en BD → clave de tipo de boleto / precio */
const MAPA_NOMBRE_CATEGORIA_A_TIPO = [
    'General' => 'general',
    'Niño' => 'nino',
    'Nino' => 'nino',
    '3ra Edad' => 'adulto_mayor',
    'Adulto Mayor' => 'adulto_mayor',
    'Discapacitado' => 'discapacitado',
    'Cortesía' => 'cortesia',
    'Cortesia' => 'cortesia',
];

const NOMBRES_CATEGORIA_NO_VENTA = ['No Venta', 'NoVenta', 'No venta', 'Bloqueado', 'No disponible'];

const ETIQUETAS_TIPO_BOLETO = [
    'adulto' => 'Adulto',
    'general' => 'General',
    'nino' => 'Niño',
    'adulto_mayor' => '3ra Edad',
    'discapacitado' => 'Discapacitado',
    'cortesia' => 'Cortesía',
];

/**
 * @return array<int, array{id_categoria:int, nombre_categoria:string, precio:float, color:string}>
 */
function obtener_categorias_evento_completas(mysqli $conn, int $id_evento): array
{
    $stmt = $conn->prepare('SELECT id_categoria, nombre_categoria, precio, color FROM categorias WHERE id_evento = ? ORDER BY precio ASC, nombre_categoria ASC');
    $stmt->bind_param('i', $id_evento);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = [
            'id_categoria' => (int) $row['id_categoria'],
            'nombre_categoria' => $row['nombre_categoria'],
            'precio' => (float) $row['precio'],
            'color' => $row['color'],
        ];
    }
    $stmt->close();
    return $out;
}

function es_categoria_no_venta(string $nombre): bool
{
    $lower = strtolower($nombre);
    foreach (NOMBRES_CATEGORIA_NO_VENTA as $nv) {
        if (strtolower($nv) === $lower || str_contains($lower, 'no venta') || str_contains($lower, 'noventa')) {
            return true;
        }
    }
    return false;
}

function tipo_boleto_desde_nombre_categoria(string $nombre): ?string
{
    if (isset(MAPA_NOMBRE_CATEGORIA_A_TIPO[$nombre])) {
        return MAPA_NOMBRE_CATEGORIA_A_TIPO[$nombre];
    }
    $lower = strtolower($nombre);
    foreach (MAPA_NOMBRE_CATEGORIA_A_TIPO as $nom => $tipo) {
        if (strtolower($nom) === $lower) {
            return $tipo;
        }
    }
    return null;
}

/**
 * Tipos estándar de precio (admin) presentes como categoría en el evento.
 * @return string[] ej. ['general', 'nino', 'discapacitado']
 */
function tipos_precio_estandar_evento(mysqli $conn, int $id_evento): array
{
    $tipos = [];
    foreach (obtener_categorias_evento_completas($conn, $id_evento) as $cat) {
        $tipo = tipo_boleto_desde_nombre_categoria($cat['nombre_categoria']);
        if ($tipo && $tipo !== 'cortesia' && !in_array($tipo, $tipos, true)) {
            $tipos[] = $tipo;
        }
    }
    return $tipos;
}

/**
 * Tipos seleccionables en el punto de venta para este evento.
 * @return string[] ej. ['adulto', 'cortesia'] o ['adulto', 'nino', 'discapacitado', 'cortesia']
 */
function tipos_boleto_venta_evento(mysqli $conn, int $id_evento): array
{
    $categorias = obtener_categorias_evento_completas($conn, $id_evento);
    $tipos_std = [];
    $tiene_vendible = false;

    foreach ($categorias as $cat) {
        if (es_categoria_no_venta($cat['nombre_categoria'])) {
            continue;
        }
        $tiene_vendible = true;
        $tipo = tipo_boleto_desde_nombre_categoria($cat['nombre_categoria']);
        if ($tipo && $tipo !== 'cortesia') {
            $tipos_std[] = $tipo;
        }
    }

    $tipos = [];
    // Adulto: categoría General o cualquier zona vendible (VIP, etc.)
    if (in_array('general', $tipos_std, true) || $tiene_vendible) {
        $tipos[] = 'adulto';
    }
    foreach (['nino', 'adulto_mayor', 'discapacitado'] as $t) {
        if (in_array($t, $tipos_std, true)) {
            $tipos[] = $t;
        }
    }
    // Cortesía siempre disponible como tipo de venta (distinto de evento gratuito)
    $tipos[] = 'cortesia';

    return array_values(array_unique($tipos));
}

/**
 * Categorías personalizadas (no estándar) para CRUD en admin.
 */
function categorias_personalizadas_evento(mysqli $conn, int $id_evento): array
{
    $out = [];
    foreach (obtener_categorias_evento_completas($conn, $id_evento) as $cat) {
        if (tipo_boleto_desde_nombre_categoria($cat['nombre_categoria']) === null
            && !es_categoria_no_venta($cat['nombre_categoria'])) {
            $out[] = $cat;
        }
    }
    return $out;
}

/**
 * Evento gratuito: todas las categorías vendibles tienen precio 0.
 */
function evento_es_gratuito(mysqli $conn, int $id_evento): bool
{
    $categorias = obtener_categorias_evento_completas($conn, $id_evento);
    $vendibles = array_filter($categorias, fn($c) => !es_categoria_no_venta($c['nombre_categoria']));
    if (empty($vendibles)) {
        return false;
    }
    foreach ($vendibles as $cat) {
        if ($cat['precio'] > 0) {
            return false;
        }
    }
    return true;
}

/**
 * Configuración para JavaScript del punto de venta.
 */
function config_tipos_boleto_js(mysqli $conn, ?int $id_evento): array
{
    if (!$id_evento) {
        return [
            'tipos' => ['adulto', 'nino', 'adulto_mayor', 'discapacitado', 'cortesia'],
            'evento_gratuito' => false,
            'etiquetas' => ETIQUETAS_TIPO_BOLETO,
        ];
    }
    return [
        'tipos' => tipos_boleto_venta_evento($conn, $id_evento),
        'evento_gratuito' => evento_es_gratuito($conn, $id_evento),
        'etiquetas' => ETIQUETAS_TIPO_BOLETO,
    ];
}
