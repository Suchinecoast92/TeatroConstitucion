<?php
/**
 * Recálculo de precios en backend (nunca confiar en precios del navegador).
 */

if (defined('PRECIO_HELPER_INCLUDED')) {
    return;
}
define('PRECIO_HELPER_INCLUDED', true);

require_once __DIR__ . '/catalogo_boletos_helper.php';

/**
 * Precio por tipo de boleto para un evento, alineado a la lógica de taquilla.
 *
 * @return array<string,float>
 */
function obtener_precios_tipo_evento(mysqli $conn, int $idEvento): array
{
    $precios = [
        'adulto' => 0.0,
        'general' => 0.0,
        'nino' => 0.0,
        'adulto_mayor' => 0.0,
        'discapacitado' => 0.0,
        'cortesia' => 0.0,
    ];

    foreach (obtener_categorias_evento_completas($conn, $idEvento) as $cat) {
        $tipo = tipo_boleto_desde_nombre_categoria($cat['nombre_categoria']);
        if ($tipo !== null) {
            $precios[$tipo] = (float) $cat['precio'];
        }
        if ($tipo === 'general') {
            $precios['adulto'] = (float) $cat['precio'];
        }
    }

    $check = $conn->query("SHOW TABLES LIKE 'precios_tipo_boleto'");
    if ($check && $check->num_rows > 0) {
        $stmt = $conn->prepare(
            'SELECT tipo_boleto, precio FROM precios_tipo_boleto WHERE id_evento = ? AND activo = 1'
        );
        $stmt->bind_param('i', $idEvento);
        $stmt->execute();
        $res = $stmt->get_result();
        $tieneEvento = $res->num_rows > 0;
        while ($row = $res->fetch_assoc()) {
            $precios[$row['tipo_boleto']] = (float) $row['precio'];
        }
        $stmt->close();

        if (!$tieneEvento) {
            $resG = $conn->query(
                'SELECT tipo_boleto, precio FROM precios_tipo_boleto WHERE id_evento IS NULL AND activo = 1'
            );
            if ($resG) {
                while ($row = $resG->fetch_assoc()) {
                    $precios[$row['tipo_boleto']] = (float) $row['precio'];
                }
            }
        }

        // Categorías del mapa tienen prioridad para tipos estándar
        foreach (obtener_categorias_evento_completas($conn, $idEvento) as $cat) {
            $tipo = tipo_boleto_desde_nombre_categoria($cat['nombre_categoria']);
            if ($tipo !== null) {
                $precios[$tipo] = (float) $cat['precio'];
            }
            if ($tipo === 'general') {
                $precios['adulto'] = (float) $cat['precio'];
            }
        }
    }

    $precios['cortesia'] = 0.0;
    return $precios;
}

/**
 * Resuelve categoría de un asiento desde mapa_json del evento.
 */
function resolver_categoria_asiento(mysqli $conn, int $idEvento, string $codigoAsiento): ?array
{
    $stmt = $conn->prepare('SELECT mapa_json FROM evento WHERE id_evento = ? LIMIT 1');
    $stmt->bind_param('i', $idEvento);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }

    $mapa = json_decode($row['mapa_json'] ?? '{}', true);
    if (!is_array($mapa) || !isset($mapa[$codigoAsiento])) {
        return null;
    }

    $idCat = (int) $mapa[$codigoAsiento];
    $stmt = $conn->prepare(
        'SELECT id_categoria, nombre_categoria, precio, color FROM categorias WHERE id_categoria = ? AND id_evento = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $idCat, $idEvento);
    $stmt->execute();
    $cat = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$cat) {
        return null;
    }

    return [
        'id_categoria' => (int) $cat['id_categoria'],
        'nombre_categoria' => $cat['nombre_categoria'],
        'precio' => (float) $cat['precio'],
        'color' => $cat['color'],
    ];
}

/**
 * Tipo de boleto online según la categoría mapeada del asiento.
 * El cliente no puede elegir otro tipo (evita cambiar VIP/General a Discapacitado gratis).
 */
function tipo_boleto_desde_categoria_asiento(array $cat): string
{
    $tipo = tipo_boleto_desde_nombre_categoria((string) ($cat['nombre_categoria'] ?? ''));
    if ($tipo && !in_array($tipo, ['cortesia', 'general', 'adulto'], true)) {
        return $tipo;
    }
    return 'adulto';
}

/**
 * Precio unitario según tipo (adulto usa precio de categoría del asiento).
 */
function calcular_precio_unitario_tipo(string $tipoBoleto, float $precioCategoria, array $preciosTipo): float
{
    if ($tipoBoleto === 'cortesia') {
        return 0.0;
    }
    if ($tipoBoleto === 'adulto' || $tipoBoleto === 'general') {
        return $precioCategoria;
    }
    if (isset($preciosTipo[$tipoBoleto])) {
        return (float) $preciosTipo[$tipoBoleto];
    }
    return $precioCategoria;
}

/**
 * Aplica una promoción válida (opcional). Recalcula en servidor.
 *
 * @return array{descuento:float,id_promocion:?int,nombre:?string}
 */
function aplicar_promocion_item(
    mysqli $conn,
    int $idEvento,
    ?int $idCategoria,
    float $precioBase,
    ?int $idPromocionSolicitada,
    int $cantidadCarrito
): array {
    $out = ['descuento' => 0.0, 'id_promocion' => null, 'nombre' => null];
    if (!$idPromocionSolicitada || $precioBase <= 0) {
        return $out;
    }

    $stmt = $conn->prepare("
        SELECT id_promocion, nombre, id_evento, id_categoria, min_cantidad,
               fecha_desde, fecha_hasta, modo_calculo, valor, activo
        FROM promociones
        WHERE id_promocion = ? AND activo = 1
        LIMIT 1
    ");
    $stmt->bind_param('i', $idPromocionSolicitada);
    $stmt->execute();
    $promo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$promo) {
        return $out;
    }

    if ($promo['id_evento'] !== null && (int) $promo['id_evento'] !== $idEvento) {
        return $out;
    }
    if ($promo['id_categoria'] !== null && $idCategoria !== null && (int) $promo['id_categoria'] !== $idCategoria) {
        return $out;
    }
    if ((int) $promo['min_cantidad'] > $cantidadCarrito) {
        return $out;
    }
    $now = time();
    if (!empty($promo['fecha_desde']) && strtotime($promo['fecha_desde']) > $now) {
        return $out;
    }
    if (!empty($promo['fecha_hasta']) && strtotime($promo['fecha_hasta']) < $now) {
        return $out;
    }

    $valor = (float) $promo['valor'];
    $descuento = 0.0;
    if ($promo['modo_calculo'] === 'porcentaje') {
        $descuento = round($precioBase * ($valor / 100), 2);
    } else {
        $descuento = min($precioBase, $valor);
    }

    return [
        'descuento' => $descuento,
        'id_promocion' => (int) $promo['id_promocion'],
        'nombre' => $promo['nombre'],
    ];
}

/**
 * Calcula ítems y total desde BD.
 *
 * Entrada de asientos: [{asiento, tipo_boleto?, id_promocion?}, ...]
 * Ignora cualquier precio enviado por el cliente.
 *
 * @return array{success:bool,error?:string,items?:array,total?:float,tipos_permitidos?:string[]}
 */
function calcular_cotizacion_online(mysqli $conn, int $idEvento, array $asientosInput): array
{
    if (empty($asientosInput)) {
        return ['success' => false, 'error' => 'No hay asientos'];
    }
    if (count($asientosInput) > 20) {
        return ['success' => false, 'error' => 'Máximo 20 asientos por orden'];
    }

    $tiposPermitidos = array_values(array_filter(
        tipos_boleto_venta_evento($conn, $idEvento),
        static fn($t) => $t !== 'cortesia'
    ));
    if (empty($tiposPermitidos)) {
        $tiposPermitidos = ['adulto'];
    }

    $preciosTipo = obtener_precios_tipo_evento($conn, $idEvento);
    $cantidad = count($asientosInput);
    $items = [];
    $total = 0.0;
    $vistos = [];

    foreach ($asientosInput as $raw) {
        $codigo = is_array($raw) ? trim((string) ($raw['asiento'] ?? $raw['codigo_asiento'] ?? '')) : trim((string) $raw);
        if ($codigo === '') {
            return ['success' => false, 'error' => 'Asiento inválido'];
        }
        if (isset($vistos[$codigo])) {
            return ['success' => false, 'error' => "Asiento duplicado: $codigo"];
        }
        $vistos[$codigo] = true;

        // Online: el tipo lo fija el mapa del asiento, no el navegador
        $cat = resolver_categoria_asiento($conn, $idEvento, $codigo);
        if (!$cat) {
            return ['success' => false, 'error' => "Asiento no está en el mapa: $codigo"];
        }
        if (es_categoria_no_venta($cat['nombre_categoria'])) {
            return ['success' => false, 'error' => "Asiento no disponible para venta: $codigo"];
        }

        $tipo = tipo_boleto_desde_categoria_asiento($cat);
        if (!in_array($tipo, $tiposPermitidos, true)) {
            // Zona especial no listada como tipo de venta → vender como adulto al precio del asiento
            $tipo = 'adulto';
        }

        $precioBase = calcular_precio_unitario_tipo($tipo, (float) $cat['precio'], $preciosTipo);
        $idPromoReq = is_array($raw) && !empty($raw['id_promocion']) ? (int) $raw['id_promocion'] : null;
        $promo = aplicar_promocion_item(
            $conn,
            $idEvento,
            $cat['id_categoria'],
            $precioBase,
            $idPromoReq,
            $cantidad
        );
        $precioFinal = max(0, round($precioBase - $promo['descuento'], 2));

        $items[] = [
            'codigo_asiento' => $codigo,
            'id_categoria' => $cat['id_categoria'],
            'nombre_categoria' => $cat['nombre_categoria'],
            'tipo_boleto' => $tipo,
            'precio_base' => $precioBase,
            'descuento_aplicado' => $promo['descuento'],
            'precio_final' => $precioFinal,
            'id_promocion' => $promo['id_promocion'],
            'promocion_nombre' => $promo['nombre'],
        ];
        $total += $precioFinal;
    }

    return [
        'success' => true,
        'items' => $items,
        'total' => round($total, 2),
        'tipos_permitidos' => $tiposPermitidos,
    ];
}
