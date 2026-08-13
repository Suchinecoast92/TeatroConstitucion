<?php
/**
 * Helper de Respaldo y Protección de Ventas
 * ==========================================
 * - Replica ventas a la base de datos de respaldo trt_25_backup
 * - Marca boletos vendidos como protegidos contra borrado
 * - Verifica integridad antes de operaciones destructivas
 * - Registra metadatos de venta detallados (lugar, método pago, etc.)
 *
 * Uso: incluir en procesar_compra.php, eliminar_evento.php, etc.
 */

if (!function_exists('getBackupConnection')) {
    function getBackupConnection() {
        static $conn = null;
        if ($conn !== null) return $conn;

        @mysqli_report(MYSQLI_REPORT_OFF);
        try {
            $c = @new mysqli('localhost', 'root', '', 'trt_25_backup');
            if ($c->connect_error) {
                error_log('[Backup] No se pudo conectar a trt_25_backup: ' . $c->connect_error);
                return null;
            }
            $c->set_charset('utf8mb4');
            $conn = $c;
            return $conn;
        } catch (Throwable $e) {
            error_log('[Backup] Error conexión: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('logSync')) {
    /**
     * Registra una operación de sincronización en el log de la BD backup.
     */
    function logSync($direccion, $tipo, $id_origen, $accion, $mensaje = '') {
        $conn = getBackupConnection();
        if (!$conn) return;
        try {
            $stmt = $conn->prepare("INSERT INTO sync_log (direccion, tipo, id_origen, accion, mensaje) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('ssiss', $direccion, $tipo, $id_origen, $accion, $mensaje);
            @$stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            error_log('[Backup logSync] ' . $e->getMessage());
        }
    }
}

if (!function_exists('respaldarEvento')) {
    /**
     * Crea/actualiza un respaldo del evento en trt_25_backup.
     */
    function respaldarEvento($conn_local, $id_evento, $origen = 'local') {
        $conn_b = getBackupConnection();
        if (!$conn_b) return false;

        try {
            // Datos completos
            $stmt = $conn_local->prepare("SELECT * FROM evento WHERE id_evento = ? LIMIT 1");
            $stmt->bind_param('i', $id_evento);
            $stmt->execute();
            $ev = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$ev) return false;

            // Imagen como BLOB si existe
            $img_blob = null;
            $img_mime = null;
            if (!empty($ev['imagen'])) {
                $rutas = [
                    __DIR__ . '/../evt_interfaz/' . $ev['imagen'],
                    __DIR__ . '/../' . $ev['imagen'],
                    $ev['imagen'],
                ];
                foreach ($rutas as $r) {
                    if (file_exists($r) && is_file($r)) {
                        $img_blob = file_get_contents($r);
                        $info = @getimagesize($r);
                        $img_mime = ($info && isset($info['mime'])) ? $info['mime'] : 'image/jpeg';
                        break;
                    }
                }
            }

            // Snapshot completo
            $snapshot = [
                'evento' => $ev,
                'funciones' => [],
                'categorias' => [],
            ];
            $r1 = $conn_local->query("SELECT * FROM funciones WHERE id_evento = " . (int)$id_evento);
            while ($r1 && $f = $r1->fetch_assoc()) $snapshot['funciones'][] = $f;
            $r2 = $conn_local->query("SELECT * FROM categorias WHERE id_evento = " . (int)$id_evento);
            while ($r2 && $c = $r2->fetch_assoc()) $snapshot['categorias'][] = $c;
            $snapshot_json = json_encode($snapshot, JSON_UNESCAPED_UNICODE);

            $stmt = $conn_b->prepare("
                INSERT INTO evento_backup
                (origen, id_evento_origen, titulo, descripcion, tipo, imagen, imagen_mime, mapa_json, finalizado, snapshot_json)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $null = null;
            $tipo = (int)$ev['tipo'];
            $finalizado = (int)$ev['finalizado'];
            $stmt->bind_param('sississsis',
                $origen, $id_evento, $ev['titulo'], $ev['descripcion'],
                $tipo, $null, $img_mime, $ev['mapa_json'], $finalizado, $snapshot_json
            );
            if ($img_blob !== null) $stmt->send_long_data(5, $img_blob);
            $stmt->execute();
            $stmt->close();

            logSync('to_backup', 'evento', $id_evento, 'insert', "Evento '$ev[titulo]' respaldado");
            return true;
        } catch (Throwable $e) {
            logSync('to_backup', 'evento', $id_evento, 'error', $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('respaldarBoleto')) {
    /**
     * Respalda un boleto vendido y lo protege contra borrado.
     */
    function respaldarBoleto($conn_local, $id_boleto, $origen = 'local') {
        $conn_b = getBackupConnection();
        if (!$conn_b) return false;

        try {
            $stmt = $conn_local->prepare("
                SELECT b.*, a.codigo_asiento, e.titulo as titulo_evento, f.fecha_hora as fecha_funcion,
                       c.nombre_categoria as categoria
                FROM boletos b
                LEFT JOIN asientos a ON b.id_asiento = a.id_asiento
                LEFT JOIN evento e ON b.id_evento = e.id_evento
                LEFT JOIN funciones f ON b.id_funcion = f.id_funcion
                LEFT JOIN categorias c ON b.id_categoria = c.id_categoria
                WHERE b.id_boleto = ? LIMIT 1
            ");
            $stmt->bind_param('i', $id_boleto);
            $stmt->execute();
            $bo = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$bo) return false;

            // QR como BLOB
            $qr_blob = null;
            $rutas_qr = [
                __DIR__ . '/../boletos_qr/' . $bo['codigo_unico'] . '.png',
                __DIR__ . '/../qr/' . $bo['codigo_unico'] . '.png',
            ];
            foreach ($rutas_qr as $r) {
                if (file_exists($r)) { $qr_blob = file_get_contents($r); break; }
            }

            $stmt = $conn_b->prepare("
                INSERT INTO boleto_backup
                (origen, id_boleto_origen, id_evento_origen, id_funcion_origen,
                 codigo_unico, codigo_asiento, titulo_evento, fecha_funcion,
                 categoria, precio_base, precio_final, tipo_boleto, estatus, qr_code, fecha_compra)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $null = null;
            $id_funcion = $bo['id_funcion'] ? (int)$bo['id_funcion'] : null;
            $precio_base = (float)($bo['precio_base'] ?? 0);
            $precio_final = (float)($bo['precio_final'] ?? 0);
            $estatus = (int)$bo['estatus'];
            $tipo = $bo['tipo_boleto'] ?? 'adulto';

            $stmt->bind_param('siiisssssddsiss',
                $origen, $id_boleto, $bo['id_evento'], $id_funcion,
                $bo['codigo_unico'], $bo['codigo_asiento'], $bo['titulo_evento'], $bo['fecha_funcion'],
                $bo['categoria'], $precio_base, $precio_final, $tipo, $estatus,
                $null, $bo['fecha_compra']
            );
            if ($qr_blob !== null) $stmt->send_long_data(13, $qr_blob);
            $stmt->execute();
            $stmt->close();

            // Proteger contra borrado
            protegerBorrado('boleto', $id_boleto, "Boleto vendido $bo[codigo_unico]");
            protegerBorrado('evento', (int)$bo['id_evento'], "Evento con boletos vendidos");
            if ($id_funcion) protegerBorrado('funcion', $id_funcion, "Función con boletos vendidos");

            logSync('to_backup', 'boleto', $id_boleto, 'insert', "Boleto $bo[codigo_unico] respaldado");
            return true;
        } catch (Throwable $e) {
            logSync('to_backup', 'boleto', $id_boleto, 'error', $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('registrarVentaDetallada')) {
    /**
     * Registra una venta completa con todos los metadatos (lugar, método pago, etc.).
     *
     * @param array $datos Estructura:
     *   - origen: 'local'|'online'
     *   - id_evento, id_funcion, titulo_evento, fecha_funcion
     *   - cliente_nombre, cliente_email, cliente_telefono
     *   - id_usuario_vendedor, nombre_vendedor, lugar_venta
     *   - metodo_pago, tarjeta_terminacion, referencia_pago, cuenta_destino
     *   - cantidad_boletos, subtotal, descuento_aplicado, total
     *   - asientos[], codigos_boletos[], boletos_ids[]
     *   - notas, ip_cliente, user_agent
     */
    function registrarVentaDetallada(array $datos) {
        $conn_b = getBackupConnection();
        if (!$conn_b) return false;

        try {
            $defaults = [
                'origen' => 'local',
                'id_funcion' => null,
                'titulo_evento' => null,
                'fecha_funcion' => null,
                'cliente_nombre' => 'Cliente General',
                'cliente_email' => null,
                'cliente_telefono' => null,
                'id_usuario_vendedor' => null,
                'nombre_vendedor' => null,
                'lugar_venta' => 'Taquilla',
                'metodo_pago' => 'efectivo',
                'tarjeta_terminacion' => null,
                'referencia_pago' => null,
                'cuenta_destino' => null,
                'descuento_aplicado' => 0,
                'asientos' => [],
                'codigos_boletos' => [],
                'boletos_ids' => [],
                'notas' => null,
                'ip_cliente' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ];
            $d = array_merge($defaults, $datos);

            $sql = "INSERT INTO venta_detallada (
                origen, id_evento, id_funcion, titulo_evento, fecha_funcion,
                cliente_nombre, cliente_email, cliente_telefono,
                id_usuario_vendedor, nombre_vendedor, lugar_venta,
                metodo_pago, tarjeta_terminacion, referencia_pago, cuenta_destino,
                cantidad_boletos, subtotal, descuento_aplicado, total,
                asientos, codigos_boletos, boletos_ids,
                notas, ip_cliente, user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $asientos_json = json_encode($d['asientos'], JSON_UNESCAPED_UNICODE);
            $codigos_json = json_encode($d['codigos_boletos'], JSON_UNESCAPED_UNICODE);
            $ids_json = json_encode($d['boletos_ids'], JSON_UNESCAPED_UNICODE);

            // 25 parámetros (en orden de columnas):
            // 1.origen=s 2.id_evento=i 3.id_funcion=i 4.titulo=s 5.fecha=s
            // 6.nombre=s 7.email=s 8.telefono=s
            // 9.id_vendedor=i 10.nombre_vendedor=s 11.lugar=s
            // 12.metodo=s 13.tarjeta=s 14.referencia=s 15.cuenta=s
            // 16.cantidad=i 17.subtotal=d 18.descuento=d 19.total=d
            // 20.asientos=s 21.codigos=s 22.ids=s
            // 23.notas=s 24.ip=s 25.ua=s
            $types = 'siisssssissssss' . 'iddd' . 'ssssss';
            if (strlen($types) !== 25) {
                error_log('[Backup] tipos incorrectos len=' . strlen($types));
            }

            $stmt = $conn_b->prepare($sql);
            $stmt->bind_param(
                $types,
                $d['origen'], $d['id_evento'], $d['id_funcion'], $d['titulo_evento'], $d['fecha_funcion'],
                $d['cliente_nombre'], $d['cliente_email'], $d['cliente_telefono'],
                $d['id_usuario_vendedor'], $d['nombre_vendedor'], $d['lugar_venta'],
                $d['metodo_pago'], $d['tarjeta_terminacion'], $d['referencia_pago'], $d['cuenta_destino'],
                $d['cantidad_boletos'], $d['subtotal'], $d['descuento_aplicado'], $d['total'],
                $asientos_json, $codigos_json, $ids_json,
                $d['notas'], $d['ip_cliente'], $d['user_agent']
            );
            $stmt->execute();
            $id = $stmt->insert_id;
            $stmt->close();

            logSync('to_backup', 'venta', $id, 'insert',
                "Venta {$d['cantidad_boletos']} boletos - {$d['titulo_evento']} - \${$d['total']}");
            return $id;
        } catch (Throwable $e) {
            error_log('[Backup registrarVentaDetallada] ' . $e->getMessage());
            logSync('to_backup', 'venta', 0, 'error', $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('protegerBorrado')) {
    /**
     * Marca un evento/boleto/funcion como protegido (no se puede borrar).
     */
    function protegerBorrado($tipo, $id_origen, $razon = 'Boleto vendido - protegido', $bloqueado_por = null) {
        $conn = getBackupConnection();
        if (!$conn) return false;

        if ($bloqueado_por === null && isset($_SESSION['usuario_id'])) {
            $bloqueado_por = (int)$_SESSION['usuario_id'];
        }

        try {
            $stmt = $conn->prepare("
                INSERT INTO proteccion_borrado (tipo, id_origen, razon, bloqueado_por)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE razon = VALUES(razon), fecha = CURRENT_TIMESTAMP
            ");
            $stmt->bind_param('sisi', $tipo, $id_origen, $razon, $bloqueado_por);
            @$stmt->execute();
            $stmt->close();
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('estaProtegido')) {
    /**
     * Verifica si un evento/boleto/funcion está protegido contra borrado.
     */
    function estaProtegido($tipo, $id_origen) {
        $conn = getBackupConnection();
        if (!$conn) return false;
        try {
            $stmt = $conn->prepare("SELECT id_proteccion, razon FROM proteccion_borrado WHERE tipo = ? AND id_origen = ? LIMIT 1");
            $stmt->bind_param('si', $tipo, $id_origen);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $row ?: false;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('verificarPuedeBorrarEvento')) {
    /**
     * Verifica si un evento puede ser borrado (no tiene boletos vendidos).
     * Devuelve ['puede' => bool, 'razon' => string, 'boletos_vendidos' => int]
     */
    function verificarPuedeBorrarEvento($conn_local, $id_evento) {
        // Verificar boletos vendidos en BD local
        $stmt = $conn_local->prepare("SELECT COUNT(*) as n FROM boletos WHERE id_evento = ? AND estatus IN (1,2)");
        $stmt->bind_param('i', $id_evento);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $vendidos_local = (int)($row['n'] ?? 0);

        $total = $vendidos_local;

        // Verificar protección explícita
        $protegido = estaProtegido('evento', $id_evento);

        if ($total > 0 || $protegido) {
            return [
                'puede' => false,
                'razon' => $protegido ? $protegido['razon']
                    : "Hay $total boleto(s) vendido(s). No se puede borrar.",
                'boletos_vendidos' => $total,
            ];
        }
        return [
            'puede' => true,
            'razon' => 'Sin boletos vendidos',
            'boletos_vendidos' => 0,
        ];
    }
}

if (!function_exists('verificarPuedeBorrarBoleto')) {
    function verificarPuedeBorrarBoleto($id_boleto) {
        $protegido = estaProtegido('boleto', $id_boleto);
        if ($protegido) {
            return ['puede' => false, 'razon' => $protegido['razon']];
        }
        return ['puede' => true, 'razon' => 'No protegido'];
    }
}
