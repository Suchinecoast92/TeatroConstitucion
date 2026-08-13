<?php
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
require_once __DIR__ . '/../../transacciones_helper.php';
require_once __DIR__ . '/../../api/registrar_cambio.php';

// Conexión
include_once __DIR__ . '/../../evt_interfaz/conexion.php';

// Detectar conexión disponible
$mysqli = null;
$pdo    = null;

foreach (['conn','conexion','mysqli'] as $m) {
    if (isset($GLOBALS[$m]) && $GLOBALS[$m] instanceof mysqli) {
        $mysqli = $GLOBALS[$m];
    }
}
if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    $pdo = $GLOBALS['pdo'];
}

// Helpers ==================================================
function respond($ok, $data = []) {
    echo json_encode(
        $ok ? array_merge(['ok'=>true], $data)
            : array_merge(['ok'=>false], $data),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

function fetch_all_assoc_mysqli($res){
    $rows=[];
    while($row=$res->fetch_assoc()) $rows[]=$row;
    return $rows;
}

// Leer body JSON (para create/update)
$raw  = file_get_contents('php://input');
$body = json_decode($raw,true);
if(!is_array($body)) $body=[];

$action = $_GET['action'] ?? 'list';

// Normalizadores de fecha =================================
function dt_start($d){
    $d = trim((string)$d);
    if($d==='') return null;
    return $d.' 00:00:00';
}
function dt_end($d){
    $d = trim((string)$d);
    if($d==='') return null;
    return $d.' 23:59:59';
}

/*
Estructura de la tabla `promociones`:
id_promocion   INT AI PK
nombre         VARCHAR(100)
precio         INT(11)
id_evento      INT NULL
id_categoria   INT NULL
fecha_desde    DATETIME NULL
fecha_hasta    DATETIME NULL
min_cantidad   INT NOT NULL
tipo_regla     ENUM('automatica','codigo') DEFAULT 'automatica'
codigo         VARCHAR(50) NULL
modo_calculo   ENUM('porcentaje','fijo') NOT NULL
valor          DECIMAL(10,2) NOT NULL
condiciones    VARCHAR(255) NULL
activo         TINYINT(1) NOT NULL DEFAULT 1
*/

// ======================= BUILD PARA CREATE =======================
// (al crear SÍ generamos nombre automáticamente con el porcentaje/monto)
function buildPromoFromPayload($src){
    $id_evento    = isset($src['id_evento']) ? (int)$src['id_evento'] : null;
    $precio       = isset($src['precio_base']) ? (int)$src['precio_base'] : 0;

    $modo = ($src['modo'] ?? 'porcentaje');
    if ($modo!=='porcentaje' && $modo!=='fijo') {
        $modo='porcentaje';
    }

    $valor        = isset($src['valor']) ? (float)$src['valor'] : 0;
    $min_cantidad = isset($src['min_cantidad']) ? (int)$src['min_cantidad'] : 1;

    $desde_raw    = (string)($src['desde'] ?? '');
    $hasta_raw    = (string)($src['hasta'] ?? '');

    $condiciones  = trim((string)($src['condiciones'] ?? ''));
    
    // tipo_boleto_aplicable - guardar en condiciones con prefijo especial
    $tipo_boleto_aplicable = trim((string)($src['tipo_boleto_aplicable'] ?? ''));
    if ($tipo_boleto_aplicable !== '') {
        // Agregar prefijo para identificar el tipo de boleto aplicable
        $condiciones = 'TIPO_BOLETO:' . $tipo_boleto_aplicable . ($condiciones ? '|' . $condiciones : '');
    }

    // tipo_boleto se usa SOLO al crear para armar el nombre
    $tipo_boleto  = trim((string)($src['tipo_boleto'] ?? ''));
    if($tipo_boleto==='') $tipo_boleto='Promo';

    // nombre automático
    if($modo==='porcentaje'){
        $nombre_auto = $tipo_boleto.' '.$valor.'%';
    } else {
        $nombre_auto = $tipo_boleto.' -$'.number_format($valor,2,'.','');
    }
    $nombre_auto = trim($nombre_auto);
    if($nombre_auto==='') $nombre_auto='Promoción';

    return [
        'nombre'        => $nombre_auto,
        'precio'        => $precio,
        'id_evento'     => $id_evento ?: null,
        'id_categoria'  => null,
        'fecha_desde'   => dt_start($desde_raw),
        'fecha_hasta'   => dt_end($hasta_raw),
        'min_cantidad'  => $min_cantidad,
        'tipo_regla'    => 'automatica',
        'codigo'        => null,
        'modo_calculo'  => $modo,
        'valor'         => $valor,
        'condiciones'   => $condiciones,
        'activo'        => 1
    ];
}

// ======================= BUILD PARA UPDATE =======================
// (al editar YA NO cambiamos el nombre. Respeta el que ya tenía)
function buildPromoFromPayloadForUpdate($src){
    $id_evento    = isset($src['id_evento']) ? (int)$src['id_evento'] : null;
    $precio       = isset($src['precio_base']) ? (int)$src['precio_base'] : 0;

    $modo = ($src['modo'] ?? 'porcentaje');
    if ($modo!=='porcentaje' && $modo!=='fijo') {
        $modo='porcentaje';
    }

    $valor        = isset($src['valor']) ? (float)$src['valor'] : 0;
    $min_cantidad = isset($src['min_cantidad']) ? (int)$src['min_cantidad'] : 1;

    $desde_raw    = (string)($src['desde'] ?? '');
    $hasta_raw    = (string)($src['hasta'] ?? '');

    $condiciones  = trim((string)($src['condiciones'] ?? ''));

    // nombre_fijo viene tal cual de la promo existente (sin volver a pegar "25% 25%")
    $nombre_fijo  = trim((string)($src['nombre_fijo'] ?? ''));
    if ($nombre_fijo === '') {
        $nombre_fijo = 'Promoción';
    }

    return [
        'nombre'        => $nombre_fijo, // <- NO se recalcula
        'precio'        => $precio,
        'id_evento'     => $id_evento ?: null,
        'id_categoria'  => null,
        'fecha_desde'   => dt_start($desde_raw),
        'fecha_hasta'   => dt_end($hasta_raw),
        'min_cantidad'  => $min_cantidad,
        'tipo_regla'    => 'automatica',
        'codigo'        => null,
        'modo_calculo'  => $modo,
        'valor'         => $valor,
        'condiciones'   => $condiciones,
        'activo'        => 1
    ];
}

// ================= LIST =================
if($action==='list'){
    $sql = "SELECT pr.id_promocion,
                   pr.nombre,
                   pr.precio,
                   pr.id_evento,
                   pr.id_categoria,
                   pr.fecha_desde,
                   pr.fecha_hasta,
                   pr.min_cantidad,
                   pr.tipo_regla,
                   pr.codigo,
                   pr.modo_calculo,
                   pr.valor,
                   pr.condiciones,
                   pr.activo,
                   e.titulo AS evento_titulo
            FROM promociones pr
            LEFT JOIN evento e ON e.id_evento = pr.id_evento
            ORDER BY pr.fecha_desde ASC, pr.id_promocion ASC";

    if($mysqli){
        $res = $mysqli->query($sql);
        respond(true, ['items'=>fetch_all_assoc_mysqli($res)]);
    }elseif($pdo){
        $st = $pdo->query($sql);
        respond(true, ['items'=>$st ? $st->fetchAll(PDO::FETCH_ASSOC):[]]);
    }else{
        respond(false,['error'=>'No DB connection']);
    }
}

// ================= CREATE =================
if($action==='create'){
    $f = buildPromoFromPayload($body);

    // validaciones básicas
    if($f['precio']<=0){
        respond(false,['error'=>'Precio inválido']);
    }
    if($f['valor']<=0){
        respond(false,['error'=>'Valor de descuento inválido']);
    }
    if($f['min_cantidad']<1){
        respond(false,['error'=>'min_cantidad inválido']);
    }

    $sql = "INSERT INTO promociones
              (nombre,precio,id_evento,id_categoria,fecha_desde,fecha_hasta,
               min_cantidad,tipo_regla,codigo,modo_calculo,valor,condiciones,activo)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";

    if($mysqli){
        $stmt = $mysqli->prepare($sql);

        /*
        Tipos bind_param:
        nombre        s
        precio        i
        id_evento     i
        id_categoria  i
        fecha_desde   s
        fecha_hasta   s
        min_cantidad  i
        tipo_regla    s
        codigo        s
        modo_calculo  s
        valor         d
        condiciones   s
        activo        i

        => "siiississsdsi"
        */
        $stmt->bind_param(
            "siiississsdsi",
            $f['nombre'],
            $f['precio'],
            $f['id_evento'],
            $f['id_categoria'],
            $f['fecha_desde'],
            $f['fecha_hasta'],
            $f['min_cantidad'],
            $f['tipo_regla'],
            $f['codigo'],
            $f['modo_calculo'],
            $f['valor'],
            $f['condiciones'],
            $f['activo']
        );

        $stmt->execute();
        $id_promo = $stmt->insert_id;
        registrar_transaccion('promocion_crear', 'Creó promoción ID ' . $id_promo . ' para evento ID ' . ($f['id_evento'] ?? 'global'));
        
        // Notificar cambio en BD para SSE
        registrar_cambio('descuento', $f['id_evento'], null, ['id_promocion' => $id_promo, 'accion' => 'crear']);
        
        // Notificar cambio en descuentos
        if ($f['id_evento']) {
            echo json_encode([
                'ok' => true,
                'id_promocion' => $id_promo,
                'notify_change' => true,
                'id_evento' => $f['id_evento']
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        respond(true, ['id_promocion'=>$id_promo]);

    }elseif($pdo){
        $st = $pdo->prepare($sql);
        $st->execute([
            $f['nombre'],
            $f['precio'],
            $f['id_evento'],
            $f['id_categoria'],
            $f['fecha_desde'],
            $f['fecha_hasta'],
            $f['min_cantidad'],
            $f['tipo_regla'],
            $f['codigo'],
            $f['modo_calculo'],
            $f['valor'],
            $f['condiciones'],
            $f['activo']
        ]);
        respond(true, ['id_promocion'=>$pdo->lastInsertId()]);
    }else{
        respond(false,['error'=>'No DB connection']);
    }
}

// ================= UPDATE =================
if($action==='update'){
    $id = (int)($_GET['id'] ?? 0);
    if($id<=0) respond(false,['error'=>'Id inválido']);

    // usar la versión que respeta el nombre fijo
    $f = buildPromoFromPayloadForUpdate($body);

    $sql = "UPDATE promociones
            SET nombre=?,
                precio=?,
                id_evento=?,
                id_categoria=?,
                fecha_desde=?,
                fecha_hasta=?,
                min_cantidad=?,
                tipo_regla=?,
                codigo=?,
                modo_calculo=?,
                valor=?,
                condiciones=?,
                activo=?
            WHERE id_promocion=?";

    if($mysqli){
        $stmt = $mysqli->prepare($sql);

        /*
        orden/tipos:
        nombre        s
        precio        i
        id_evento     i
        id_categoria  i
        fecha_desde   s
        fecha_hasta   s
        min_cantidad  i
        tipo_regla    s
        codigo        s
        modo_calculo  s
        valor         d
        condiciones   s
        activo        i
        id_promocion  i

        => "siiississsdsii"
        */
        $stmt->bind_param(
            "siiississsdsii",
            $f['nombre'],
            $f['precio'],
            $f['id_evento'],
            $f['id_categoria'],
            $f['fecha_desde'],
            $f['fecha_hasta'],
            $f['min_cantidad'],
            $f['tipo_regla'],
            $f['codigo'],
            $f['modo_calculo'],
            $f['valor'],
            $f['condiciones'],
            $f['activo'],
            $id
        );
        $stmt->execute();
        registrar_transaccion('promocion_actualizar', 'Actualizó promoción ID ' . $id);
        
        // Notificar cambio en BD para SSE
        registrar_cambio('descuento', $f['id_evento'], null, ['id_promocion' => $id, 'accion' => 'actualizar']);
        
        // Notificar cambio en descuentos
        if ($f['id_evento']) {
            echo json_encode([
                'ok' => true,
                'notify_change' => true,
                'id_evento' => $f['id_evento']
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        respond(true);

    }elseif($pdo){
        $st = $pdo->prepare($sql);
        $st->execute([
            $f['nombre'],
            $f['precio'],
            $f['id_evento'],
            $f['id_categoria'],
            $f['fecha_desde'],
            $f['fecha_hasta'],
            $f['min_cantidad'],
            $f['tipo_regla'],
            $f['codigo'],
            $f['modo_calculo'],
            $f['valor'],
            $f['condiciones'],
            $f['activo'],
            $id
        ]);
        
        // Notificar cambio en descuentos
        if ($f['id_evento']) {
            echo json_encode([
                'ok' => true,
                'notify_change' => true,
                'id_evento' => $f['id_evento']
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        respond(true);
    }else{
        respond(false,['error'=>'No DB connection']);
    }
}

// ================= EXPORT (CSV) =================
if ($action === 'export') {
    // Sacamos las promos, parecido a list, pero con las columnas que queremos en el CSV
    $sql = "SELECT pr.id_promocion,
                   pr.nombre,
                   pr.precio,
                   pr.modo_calculo,
                   pr.valor,
                   pr.min_cantidad,
                   pr.condiciones,
                   pr.fecha_desde,
                   pr.fecha_hasta,
                   pr.activo,
                   e.titulo AS evento_titulo
            FROM promociones pr
            LEFT JOIN evento e ON e.id_evento = pr.id_evento
            ORDER BY pr.fecha_desde ASC, pr.id_promocion ASC";

    if ($mysqli) {
        $res = $mysqli->query($sql);
        $rows = fetch_all_assoc_mysqli($res);
    } elseif ($pdo) {
        $st   = $pdo->query($sql);
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    } else {
        // no hay conexión -> devolvemos JSON de error igual que el resto
        header('Content-Type: application/json; charset=utf-8');
        respond(false,['error'=>'No DB connection']);
    }

    // Armamos CSV en memoria
    $cols = [
        'id_promocion',
        'evento',
        'nombre',
        'precio_base',
        'tipo_descuento',
        'valor_descuento',
        'min_cantidad',
        'condiciones',
        'fecha_desde',
        'fecha_hasta',
        'activo'
    ];

    // headers HTTP para descarga
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="promociones.csv"');

    // escribir CSV directo a la salida
    $out = fopen('php://output', 'w');

    // encabezado
    fputcsv($out, $cols);

    // filas
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id_promocion'],
            $r['evento_titulo'] ?? '',
            $r['nombre'] ?? '',
            $r['precio'] ?? '',
            $r['modo_calculo'] ?? '',
            $r['valor'] ?? '',
            $r['min_cantidad'] ?? '',
            $r['condiciones'] ?? '',
            $r['fecha_desde'] ?? '',
            $r['fecha_hasta'] ?? '',
            ($r['activo'] ?? 0) ? '1' : '0'
        ]);
    }

    fclose($out);
    exit;
}

// ================= DELETE =================
if($action==='delete'){
    $id = (int)($_GET['id'] ?? 0);
    if($id<=0) respond(false,['error'=>'Id inválido']);

    $id_evento_promo = null;
    if ($mysqli) {
        $stEvt = $mysqli->prepare("SELECT id_evento FROM promociones WHERE id_promocion = ? LIMIT 1");
        $stEvt->bind_param('i', $id);
        $stEvt->execute();
        if ($rowEvt = $stEvt->get_result()->fetch_assoc()) {
            $id_evento_promo = $rowEvt['id_evento'] ? (int)$rowEvt['id_evento'] : null;
        }
        $stEvt->close();
    } elseif ($pdo) {
        $stEvt = $pdo->prepare("SELECT id_evento FROM promociones WHERE id_promocion = ? LIMIT 1");
        $stEvt->execute([$id]);
        if ($rowEvt = $stEvt->fetch(PDO::FETCH_ASSOC)) {
            $id_evento_promo = $rowEvt['id_evento'] ? (int)$rowEvt['id_evento'] : null;
        }
    }

    if($mysqli){
        $stmt = $mysqli->prepare("DELETE FROM promociones WHERE id_promocion=?");
        $stmt->bind_param("i",$id);
        $stmt->execute();
        registrar_transaccion('promocion_eliminar', 'Eliminó promoción ID ' . $id);
        if (function_exists('registrar_cambio')) {
            registrar_cambio('descuento', $id_evento_promo, null, ['id_promocion' => $id, 'accion' => 'eliminar']);
        }
        respond(true);

    }elseif($pdo){
        $st = $pdo->prepare("DELETE FROM promociones WHERE id_promocion=?");
        $st->execute([$id]);
        if (function_exists('registrar_cambio')) {
            registrar_cambio('descuento', $id_evento_promo, null, ['id_promocion' => $id, 'accion' => 'eliminar']);
        }
        respond(true);

    }else{
        respond(false,['error'=>'No DB connection']);
    }
}

// Acción desconocida
respond(false,['error'=>'Acción no soportada']);
