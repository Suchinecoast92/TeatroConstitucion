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
 * Precio por tipo en taquilla, idéntico a obtenerPrecioPorTipo() de vnt_interfaz/js/carrito.js:
 * un precio de tipo en 0 cae al precio de la categoría del asiento.
 */
function precio_tipo_taquilla(string $tipoBoleto, float $precioCategoria, array $preciosTipo): float
{
    if ($tipoBoleto === 'cortesia') {
        return 0.0;
    }
    if (in_array($tipoBoleto, ['nino', 'adulto_mayor', 'discapacitado'], true)) {
        $p = (float) ($preciosTipo[$tipoBoleto] ?? 0);
        return $p > 0 ? $p : $precioCategoria;
    }
    return $precioCategoria;
}

/**
 * Promoción vigente para el evento, con el mismo filtro que vnt_interfaz/obtener_descuentos.php.
 */
function promocion_vigente_taquilla(mysqli $conn, int $idEvento, int $idPromocion): ?array
{
    $stmt = $conn->prepare("
        SELECT p.id_promocion, p.nombre, p.modo_calculo, p.valor, p.id_categoria,
               p.min_cantidad, p.condiciones, c.nombre_categoria
        FROM promociones p
        LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
        WHERE p.id_promocion = ?
          AND p.activo = 1
          AND (p.fecha_desde IS NULL OR p.fecha_desde <= NOW())
          AND (p.fecha_hasta IS NULL OR p.fecha_hasta >= NOW())
          AND (p.id_evento = ? OR p.id_evento IS NULL)
          AND (p.id_categoria IS NULL OR c.id_evento = ? OR p.id_evento IS NULL)
        LIMIT 1
    ");
    $stmt->bind_param('iii', $idPromocion, $idEvento, $idEvento);
    $stmt->execute();
    $promo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$promo) {
        return null;
    }

    $promo['tipo_boleto_aplicable'] = null;
    $cond = (string) ($promo['condiciones'] ?? '');
    if (strpos($cond, 'TIPO_BOLETO:') === 0) {
        $promo['tipo_boleto_aplicable'] = str_replace('TIPO_BOLETO:', '', explode('|', $cond, 2)[0]);
    }
    return $promo;
}

/**
 * Recalcula en servidor una venta de taquilla (vnt_interfaz/procesar_compra.php).
 *
 * Replica lo que el cajero ve en pantalla (carrito.js): categoría del mapa del asiento,
 * precio por tipo, cortesía y promoción (porcentaje por boleto; monto fijo repartido
 * entre los boletos). Si el navegador manda un precio o descuento distinto al calculado
 * se rechaza, para que el ticket nunca muestre algo diferente a lo cobrado.
 *
 * Entrada: [{asiento, tipo_boleto?, id_promocion?, precio?, descuento_aplicado?}, ...]
 *
 * @return array{success:bool,error?:string,items?:array,total?:float}
 */
function calcular_cotizacion_taquilla(mysqli $conn, int $idEvento, array $asientosInput): array
{
    if (empty($asientosInput)) {
        return ['success' => false, 'error' => 'No hay asientos seleccionados'];
    }

    $categorias = [];
    $idGeneral = null;
    $idMasBarata = null;
    foreach (obtener_categorias_evento_completas($conn, $idEvento) as $cat) {
        $id = (int) $cat['id_categoria'];
        $categorias[$id] = [
            'id_categoria' => $id,
            'nombre_categoria' => (string) $cat['nombre_categoria'],
            'precio' => (float) $cat['precio'],
        ];
        if ($idGeneral === null && strtolower(trim((string) $cat['nombre_categoria'])) === 'general') {
            $idGeneral = $id;
        }
        if ($idMasBarata === null || (float) $cat['precio'] < $categorias[$idMasBarata]['precio']) {
            $idMasBarata = $id;
        }
    }
    if (empty($categorias)) {
        return ['success' => false, 'error' => 'El evento no tiene categorías configuradas. Por favor, configura las categorías antes de vender boletos.'];
    }
    $idPorDefecto = $idGeneral ?? $idMasBarata;

    $stmt = $conn->prepare('SELECT mapa_json FROM evento WHERE id_evento = ? LIMIT 1');
    $stmt->bind_param('i', $idEvento);
    $stmt->execute();
    $rowMapa = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $mapa = json_decode($rowMapa['mapa_json'] ?? '{}', true);
    if (!is_array($mapa)) {
        $mapa = [];
    }

    $preciosTipo = obtener_precios_tipo_evento($conn, $idEvento);
    $tiposValidos = ['adulto', 'nino', 'adulto_mayor', 'discapacitado', 'cortesia'];

    $idPromocion = null;
    foreach ($asientosInput as $raw) {
        $idp = is_array($raw) && !empty($raw['id_promocion']) ? (int) $raw['id_promocion'] : null;
        if ($idp === null) {
            continue;
        }
        if ($idPromocion !== null && $idp !== $idPromocion) {
            return ['success' => false, 'error' => 'Solo se puede aplicar una promoción por venta.'];
        }
        $idPromocion = $idp;
    }

    $items = [];
    $vistos = [];
    foreach ($asientosInput as $raw) {
        $codigo = is_array($raw) ? trim((string) ($raw['asiento'] ?? '')) : '';
        if ($codigo === '' || !preg_match('/^[A-Za-z0-9-]{1,20}$/', $codigo)) {
            return ['success' => false, 'error' => 'Asiento inválido'];
        }
        if (isset($vistos[$codigo])) {
            return ['success' => false, 'error' => "Asiento duplicado: $codigo"];
        }
        $vistos[$codigo] = true;

        $idCat = isset($mapa[$codigo]) ? (int) $mapa[$codigo] : 0;
        $cat = $categorias[$idCat] ?? $categorias[$idPorDefecto];
        if (es_categoria_no_venta($cat['nombre_categoria'])) {
            return ['success' => false, 'error' => "El asiento $codigo no está disponible para venta."];
        }

        $tipo = (string) ($raw['tipo_boleto'] ?? 'adulto');
        if ($tipo === '') {
            $tipo = 'adulto';
        }
        if (!in_array($tipo, $tiposValidos, true)) {
            return ['success' => false, 'error' => "Tipo de boleto inválido para el asiento $codigo."];
        }

        $items[] = [
            'codigo_asiento' => $codigo,
            'id_categoria' => $cat['id_categoria'],
            'precio_categoria' => $cat['precio'],
            'tipo_boleto' => $tipo,
            'precio_base' => precio_tipo_taquilla($tipo, $cat['precio'], $preciosTipo),
            'descuento_aplicado' => 0.0,
            'id_promocion' => null,
            'cliente_precio' => isset($raw['precio']) && is_numeric($raw['precio']) ? (float) $raw['precio'] : null,
            'cliente_descuento' => isset($raw['descuento_aplicado']) && is_numeric($raw['descuento_aplicado'])
                ? (float) $raw['descuento_aplicado'] : null,
        ];
    }

    if ($idPromocion !== null) {
        $promo = promocion_vigente_taquilla($conn, $idEvento, $idPromocion);
        if (!$promo) {
            return ['success' => false, 'error' => 'La promoción seleccionada ya no está vigente. Recarga la página de venta.'];
        }
        $nombre = $promo['nombre'];
        $minimo = max(1, (int) $promo['min_cantidad']);
        if (count($items) < $minimo) {
            return ['success' => false, 'error' => "El descuento \"$nombre\" requiere mínimo $minimo boleto(s)."];
        }
        foreach ($items as $it) {
            if ($it['tipo_boleto'] === 'cortesia') {
                return ['success' => false, 'error' => "No puedes aplicar el descuento \"$nombre\" porque hay boletos de Cortesía."];
            }
            if ($promo['tipo_boleto_aplicable'] && $it['tipo_boleto'] !== $promo['tipo_boleto_aplicable']) {
                return ['success' => false, 'error' => "El descuento \"$nombre\" no aplica al tipo de boleto del asiento {$it['codigo_asiento']}."];
            }
            if ($promo['id_categoria'] !== null && (int) $promo['id_categoria'] !== $it['id_categoria']) {
                return ['success' => false, 'error' => "El descuento \"$nombre\" no aplica a la categoría del asiento {$it['codigo_asiento']}."];
            }
        }

        $valor = (float) $promo['valor'];
        $porBoletoFijo = $valor / count($items);
        foreach ($items as &$it) {
            $d = $promo['modo_calculo'] === 'porcentaje'
                ? $it['precio_categoria'] * ($valor / 100)
                : $porBoletoFijo;
            $it['descuento_aplicado'] = round(min($d, $it['precio_categoria']), 2);
            $it['id_promocion'] = (int) $promo['id_promocion'];
        }
        unset($it);
    }

    $total = 0.0;
    foreach ($items as &$it) {
        if ($it['cliente_precio'] !== null && abs($it['cliente_precio'] - $it['precio_categoria']) > 0.01) {
            return ['success' => false, 'error' => sprintf(
                'El precio del asiento %s cambió ($%s en pantalla, $%s actual). Recarga la página de venta.',
                $it['codigo_asiento'],
                number_format($it['cliente_precio'], 2),
                number_format($it['precio_categoria'], 2)
            )];
        }
        if ($it['tipo_boleto'] !== 'cortesia' && $it['cliente_descuento'] !== null
            && abs($it['cliente_descuento'] - $it['descuento_aplicado']) > 0.01) {
            return ['success' => false, 'error' => "El descuento del asiento {$it['codigo_asiento']} no coincide con la promoción vigente. Recarga la página de venta."];
        }

        if ($it['tipo_boleto'] === 'cortesia') {
            // Convención histórica: base = precio de la categoría, descuento = base, final = 0
            $it['precio_base'] = $it['precio_categoria'];
            $it['descuento_aplicado'] = $it['precio_categoria'];
            $it['precio_final'] = 0.0;
        } else {
            $it['precio_final'] = max(0.0, round($it['precio_base'] - $it['descuento_aplicado'], 2));
        }
        unset($it['cliente_precio'], $it['cliente_descuento']);
        $total += $it['precio_final'];
    }
    unset($it);

    return ['success' => true, 'items' => $items, 'total' => round($total, 2)];
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
