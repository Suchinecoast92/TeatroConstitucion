<?php
/**
 * Helper de Reservas Temporales (Holds)
 * ======================================
 * Lógica común usada por:
 *   - api/reservas.php
 *   - vnt_interfaz/procesar_compra.php
 *
 * Operación principal: verificarVentaAtomica() que verifica reservas y boletos
 * con SELECT ... FOR UPDATE (transacción) para evitar doble venta.
 */

if (defined('RESERVAS_HELPER_INCLUDED')) return;
define('RESERVAS_HELPER_INCLUDED', true);

// TTL por defecto en segundos. Tiempo que el cliente tiene para completar la compra.
if (!defined('RESERVA_TTL_SEG')) define('RESERVA_TTL_SEG', 300); // 5 min

/**
 * Devuelve una conexión a trt_25 (donde vive la tabla compartida).
 * Usa config/database.php (.env opcional) en lugar de credenciales fijas.
 */
if (!function_exists('getReservasConnection')) {
    function getReservasConnection() {
        static $c = null;
        if ($c !== null && !$c->connect_errno) {
            return $c;
        }

        require_once dirname(__DIR__) . '/config/database.php';
        $c = getLocalConnection();
        if (!$c) {
            return null;
        }
        asegurarTablaReservasTemporales($c);
        return $c;
    }
}

if (!function_exists('asegurarTablaReservasTemporales')) {
    function asegurarTablaReservasTemporales(mysqli $conn): void {
        static $verificada = false;
        if ($verificada) return;
        $conn->query("CREATE TABLE IF NOT EXISTS reservas_temporales (
            id_reserva INT AUTO_INCREMENT PRIMARY KEY,
            codigo_asiento VARCHAR(20) NOT NULL,
            id_evento INT NOT NULL,
            id_funcion INT NULL,
            origen ENUM('local','online') NOT NULL,
            session_id VARCHAR(100) NOT NULL,
            cliente_info VARCHAR(150) NULL,
            fecha_reserva TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expira_en TIMESTAMP NOT NULL,
            UNIQUE KEY uk_asiento_funcion (codigo_asiento, id_evento, id_funcion),
            INDEX idx_expira (expira_en),
            INDEX idx_session (session_id),
            INDEX idx_evento_funcion (id_evento, id_funcion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $verificada = true;
    }
}

/**
 * Limpia reservas expiradas. Se llama implícitamente en otras funciones,
 * pero también puede invocarse directamente desde un cron / botón.
 */
if (!function_exists('limpiarReservasExpiradas')) {
    function limpiarReservasExpiradas(?mysqli $conn = null): int {
        $conn = $conn ?: getReservasConnection();
        if (!$conn) return 0;
        $r = @$conn->query("DELETE FROM reservas_temporales WHERE expira_en < NOW()");
        return $r ? $conn->affected_rows : 0;
    }
}

/**
 * Calcula expira_en con el reloj de MySQL (evita desfase PHP UTC vs MySQL local).
 *
 * @return array{db:string,iso:string,ttl:int}
 */
if (!function_exists('calcularExpiraReserva')) {
    function calcularExpiraReserva(mysqli $conn, int $ttlSeg): array
    {
        $ttlSeg = max(1, (int) $ttlSeg);
        $st = $conn->prepare('SELECT DATE_ADD(NOW(), INTERVAL ? SECOND) AS e, UNIX_TIMESTAMP(DATE_ADD(NOW(), INTERVAL ? SECOND)) AS u');
        $st->bind_param('ii', $ttlSeg, $ttlSeg);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        $db = (string) ($row['e'] ?? '');
        $unix = (int) ($row['u'] ?? (time() + $ttlSeg));
        // ISO en UTC para el timer del navegador
        $iso = gmdate('Y-m-d\TH:i:s\Z', $unix);
        return ['db' => $db, 'iso' => $iso, 'ttl' => $ttlSeg];
    }
}

/**
 * Reserva (apartado) de un conjunto de asientos para un cliente.
 *
 * @return array {
 *     success: bool,
 *     reservados: string[],   // asientos efectivamente reservados
 *     conflictos: array<string,string>,  // codigo_asiento => razón ('vendido'|'reservado')
 *     expira_en: string ISO8601,
 * }
 */
if (!function_exists('reservarAsientos')) {
    function reservarAsientos(
        int $idEvento,
        ?int $idFuncion,
        array $asientos,
        string $sessionId,
        string $origen = 'online',
        ?string $clienteInfo = null,
        int $ttlSeg = RESERVA_TTL_SEG
    ): array {
        $conn = getReservasConnection();
        if (!$conn) return ['success'=>false,'error'=>'No DB','reservados'=>[],'conflictos'=>[],'expira_en'=>null];

        limpiarReservasExpiradas($conn);

        $reservados = [];
        $conflictos = [];
        $expCalc = calcularExpiraReserva($conn, $ttlSeg);
        $expira = $expCalc['db'];
        $expiraIso = $expCalc['iso'];

        // Consulta sobre boletos vendidos (sistema local únicamente)
        $bdsBoletos = ['trt_25'];

        // Normalizamos id_funcion a 0 (no NULL) para que la UNIQUE detecte conflictos
        $idFuncionSql = $idFuncion ?: 0;

        foreach ($asientos as $asiento) {
            $codigo = is_array($asiento) ? ($asiento['asiento'] ?? '') : $asiento;
            if (!$codigo) continue;

            // Buscar SOLO reservas vigentes (no expiradas) para este asiento.
            $stmt = $conn->prepare("
                SELECT id_reserva, session_id, origen
                FROM reservas_temporales
                WHERE codigo_asiento = ? AND id_evento = ? AND id_funcion = ?
                  AND expira_en > NOW()
                LIMIT 1
            ");
            $stmt->bind_param('sii', $codigo, $idEvento, $idFuncionSql);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Hold ajeno (local u online) bloquea: nadie "roba" la butaca.
            if ($existing) {
                if ($existing['session_id'] === $sessionId) {
                    // Renovar TTL de la propia reserva (extender hasta `expira`)
                    $up = $conn->prepare("UPDATE reservas_temporales SET expira_en = ? WHERE id_reserva = ?");
                    $up->bind_param('si', $expira, $existing['id_reserva']);
                    $up->execute();
                    $up->close();
                    $reservados[] = $codigo;
                } else {
                    $origenExistente = $existing['origen'] ?? '';
                    if ($origenExistente === 'local') {
                        $conflictos[$codigo] = 'taquilla';
                    } elseif ($origenExistente === 'online') {
                        $conflictos[$codigo] = 'online';
                    } else {
                        $conflictos[$codigo] = 'reservado';
                    }
                }
                continue;
            }

            // Verificar que no esté ya VENDIDO (solo estatus activo = 1; cancelados se pueden revender)
            $vendidoEn = null;
            foreach ($bdsBoletos as $bd) {
                $sql = "
                    SELECT 1 FROM `$bd`.boletos b
                    INNER JOIN `$bd`.asientos a ON a.id_asiento = b.id_asiento
                    WHERE a.codigo_asiento = ?
                      AND b.id_evento = ?
                      AND b.estatus = 1
                ";
                if ($idFuncion) {
                    $sql .= ' AND b.id_funcion = ' . (int) $idFuncion;
                }
                $sql .= ' LIMIT 1';
                $stm = @$conn->prepare($sql);
                if (!$stm) continue;
                $stm->bind_param('si', $codigo, $idEvento);
                $stm->execute();
                if ($stm->get_result()->fetch_row()) { $vendidoEn = $bd; }
                $stm->close();
                if ($vendidoEn) break;
            }

            if ($vendidoEn) {
                $conflictos[$codigo] = 'vendido';
                continue;
            }

            // INSERT con ON DUPLICATE KEY UPDATE: si existe una reserva expirada
            // para esta combinación (codigo, evento, función), la sobreescribimos.
            // Si la existente ESTÁ vigente y es de otra sesión, no la pisamos:
            // solo actualiza si la fila vencida tiene expira_en <= NOW().
            $ins = $conn->prepare("
                INSERT INTO reservas_temporales
                    (codigo_asiento, id_evento, id_funcion, origen, session_id, cliente_info, expira_en)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    origen       = IF(expira_en <= NOW(), VALUES(origen),       origen),
                    session_id   = IF(expira_en <= NOW(), VALUES(session_id),   session_id),
                    cliente_info = IF(expira_en <= NOW(), VALUES(cliente_info), cliente_info),
                    expira_en    = IF(expira_en <= NOW(), VALUES(expira_en),    expira_en)
            ");
            $ins->bind_param('siissss',
                $codigo, $idEvento, $idFuncionSql, $origen, $sessionId, $clienteInfo, $expira
            );
            if (@$ins->execute()) {
                // Verificar si quedó nuestra: el ON DUPLICATE puede haber dejado la del otro
                $vf = $conn->prepare("
                    SELECT session_id FROM reservas_temporales
                    WHERE codigo_asiento = ? AND id_evento = ? AND id_funcion = ?
                      AND expira_en > NOW()
                    LIMIT 1
                ");
                $vf->bind_param('sii', $codigo, $idEvento, $idFuncionSql);
                $vf->execute();
                $rowVf = $vf->get_result()->fetch_assoc();
                $vf->close();
                if ($rowVf && $rowVf['session_id'] === $sessionId) {
                    $reservados[] = $codigo;
                } else {
                    $chk = $conn->prepare("
                        SELECT origen FROM reservas_temporales
                        WHERE codigo_asiento = ? AND id_evento = ? AND id_funcion = ?
                          AND expira_en > NOW() LIMIT 1
                    ");
                    $chk->bind_param('sii', $codigo, $idEvento, $idFuncionSql);
                    $chk->execute();
                    $rowChk = $chk->get_result()->fetch_assoc();
                    $chk->close();
                    $origenChk = $rowChk['origen'] ?? '';
                    if ($origenChk === 'local') {
                        $conflictos[$codigo] = 'taquilla';
                    } elseif ($origenChk === 'online') {
                        $conflictos[$codigo] = 'online';
                    } else {
                        $conflictos[$codigo] = 'reservado';
                    }
                }
            } else {
                $conflictos[$codigo] = 'reservado';
            }
            $ins->close();
        }

        return [
            'success'    => count($conflictos) === 0,
            'reservados' => $reservados,
            'conflictos' => $conflictos,
            'expira_en'  => $expiraIso,
        ];
    }
}

/**
 * Libera (cancela) las reservas de una sesión, ya sea por asientos específicos
 * o todas las de un evento/función para esa sesión.
 */
if (!function_exists('liberarAsientos')) {
    function liberarAsientos(
        string $sessionId,
        int $idEvento,
        ?int $idFuncion,
        array $asientos = []  // si vacío, libera TODAS de la sesión para ese evento/función
    ): int {
        $conn = getReservasConnection();
        if (!$conn) return 0;

        $idFunc = $idFuncion ?: 0;
        if (empty($asientos)) {
            $stmt = $conn->prepare("
                DELETE FROM reservas_temporales
                WHERE session_id = ? AND id_evento = ? AND id_funcion = ?
            ");
            $stmt->bind_param('sii', $sessionId, $idEvento, $idFunc);
            $stmt->execute();
            $n = $stmt->affected_rows;
            $stmt->close();
            return $n;
        }

        $borrados = 0;
        foreach ($asientos as $a) {
            $codigo = is_array($a) ? ($a['asiento'] ?? '') : $a;
            if (!$codigo) continue;
            $stmt = $conn->prepare("
                DELETE FROM reservas_temporales
                WHERE session_id = ? AND id_evento = ? AND codigo_asiento = ? AND id_funcion = ?
            ");
            $stmt->bind_param('sisi', $sessionId, $idEvento, $codigo, $idFunc);
            $stmt->execute();
            $borrados += $stmt->affected_rows;
            $stmt->close();
        }
        return $borrados;
    }
}

/**
 * Devuelve los asientos actualmente reservados (no expirados) por OTRAS sesiones,
 * para mostrarlos en el mapa como "ocupados temporalmente".
 *
 * @return string[] lista de codigo_asiento
 */
if (!function_exists('listarReservasActivas')) {
    function listarReservasActivas(int $idEvento, ?int $idFuncion, ?string $excluirSession = null): array {
        $conn = getReservasConnection();
        if (!$conn) return [];
        limpiarReservasExpiradas($conn);

        $idFunc = $idFuncion ?: 0;
        $sql = "
            SELECT codigo_asiento, session_id, origen, expira_en
            FROM reservas_temporales
            WHERE id_evento = ? AND id_funcion = ?
              AND expira_en > NOW()
        ";
        $params = [$idEvento, $idFunc];
        $types  = 'ii';
        if ($excluirSession) {
            $sql .= " AND session_id <> ? ";
            $params[] = $excluirSession;
            $types   .= 's';
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $r = $stmt->get_result();
        $out = [];
        while ($row = $r->fetch_assoc()) $out[] = $row['codigo_asiento'];
        $stmt->close();
        return $out;
    }
}

/**
 * Igual que listarReservasActivas pero con origen (local|online) por asiento.
 *
 * @return array<int,array{codigo:string,origen:string}>
 */
if (!function_exists('listarReservasActivasDetalle')) {
    function listarReservasActivasDetalle(int $idEvento, ?int $idFuncion, ?string $excluirSession = null): array {
        $conn = getReservasConnection();
        if (!$conn) return [];
        limpiarReservasExpiradas($conn);

        $idFunc = $idFuncion ?: 0;
        $sql = "
            SELECT codigo_asiento, origen
            FROM reservas_temporales
            WHERE id_evento = ? AND id_funcion = ?
              AND expira_en > NOW()
        ";
        $params = [$idEvento, $idFunc];
        $types  = 'ii';
        if ($excluirSession) {
            $sql .= " AND session_id <> ? ";
            $params[] = $excluirSession;
            $types   .= 's';
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $r = $stmt->get_result();
        $out = [];
        while ($row = $r->fetch_assoc()) {
            $out[] = [
                'codigo' => $row['codigo_asiento'],
                'origen' => $row['origen'] ?? 'online',
            ];
        }
        $stmt->close();
        return $out;
    }
}

if (!function_exists('obtenerSessionReservaLocal')) {
    /**
     * Obtiene el session_id de reservas locales vigentes para un conjunto de asientos.
     * Útil cuando procesar_compra no recibe session_id del navegador.
     */
    function obtenerSessionReservaLocal(int $idEvento, ?int $idFuncion, array $codigos): ?string {
        if (empty($codigos)) return null;
        $conn = getReservasConnection();
        if (!$conn) return null;

        limpiarReservasExpiradas($conn);
        $idFunc = $idFuncion ?: 0;
        $placeholders = implode(',', array_fill(0, count($codigos), '?'));
        $types = 'ii' . str_repeat('s', count($codigos));
        $params = array_merge([$idEvento, $idFunc], $codigos);

        $sql = "
            SELECT DISTINCT session_id
            FROM reservas_temporales
            WHERE id_evento = ? AND id_funcion = ?
              AND origen = 'local'
              AND codigo_asiento IN ($placeholders)
              AND expira_en > NOW()
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $sessions = [];
        while ($row = $res->fetch_assoc()) {
            $sessions[] = $row['session_id'];
        }
        $stmt->close();

        return count($sessions) === 1 ? $sessions[0] : null;
    }
}

/**
 * comprueba que NINGUNO de los asientos esté ya vendido en local ni online,
 * y que los que están reservados sean de la propia sesión (o no estén reservados).
 *
 * Lanza excepción si hay conflicto. Si todo OK, no devuelve nada.
 *
 * Esta función debe llamarse SIEMPRE antes del INSERT en boletos.
 */
if (!function_exists('verificarVentaAtomica')) {
    function verificarVentaAtomica(
        mysqli $connVenta,         // conexión a la BD donde se hace el INSERT (con BEGIN ya hecho)
        int $idEvento,
        ?int $idFuncion,
        array $codigosAsientos,    // ['A1','A2',...]
        string $sessionId,
        string $origen
    ): void {
        // Bloquear filas de boletos activos o cancelados (FOR UPDATE) para reventa segura
        $placeholders = implode(',', array_fill(0, count($codigosAsientos), '?'));
        $sql = "
            SELECT a.codigo_asiento, b.estatus
            FROM asientos a
            LEFT JOIN boletos b
              ON b.id_asiento = a.id_asiento
             AND b.id_evento  = ?
             AND " . ($idFuncion ? "b.id_funcion = " . (int)$idFuncion : "(b.id_funcion IS NULL OR b.id_funcion = 0)") . "
             AND b.estatus IN (1, 2)
            WHERE a.codigo_asiento IN ($placeholders)
            FOR UPDATE
        ";
        $types = 'i' . str_repeat('s', count($codigosAsientos));
        $params = [$idEvento, ...$codigosAsientos];
        $stmt = $connVenta->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $vendidosLocal = [];
        while ($row = $res->fetch_assoc()) {
            // Solo estatus 1 = vendido; estatus 2 = cancelado → disponible para revender
            if ((int) ($row['estatus'] ?? 0) === 1) {
                $vendidosLocal[] = $row['codigo_asiento'];
            }
        }
        $stmt->close();
        if ($vendidosLocal) {
            throw new Exception("Asiento(s) ya vendido(s): " . implode(', ', $vendidosLocal));
        }

        // 2) Verificar reservas activas de OTRAS sesiones (local u online bloquean por igual)
        $connRes = getReservasConnection();
        if ($connRes) {
            limpiarReservasExpiradas($connRes);
            $idFunc = $idFuncion ?: 0;

            $sql = "
                SELECT codigo_asiento, session_id, origen
                FROM reservas_temporales
                WHERE id_evento = ? AND id_funcion = ?
                  AND codigo_asiento IN ($placeholders)
                  AND expira_en > NOW()
                  AND session_id <> ?
            ";
            $types = 'ii' . str_repeat('s', count($codigosAsientos)) . 's';
            $params = [$idEvento, $idFunc, ...$codigosAsientos, $sessionId];
            $stmt = $connRes->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            $reservadosOtros = [];
            while ($row = $res->fetch_assoc()) {
                $reservadosOtros[] = $row['codigo_asiento'];
            }
            $stmt->close();
            if ($reservadosOtros) {
                throw new Exception("Asiento(s) reservado(s) por otro usuario: " . implode(', ', $reservadosOtros));
            }
        }
    }
}

/**
 * Disponibilidad unificada de una función: vendidos + holds ajenos.
 *
 * @return array{
 *   success: bool,
 *   vendidos: string[],
 *   reservados: string[],
 *   reservados_detalle: array<int,array{codigo:string,origen:string}>,
 *   ocupados: string[]
 * }
 */
if (!function_exists('obtenerDisponibilidadFuncion')) {
    function obtenerDisponibilidadFuncion(
        int $idEvento,
        ?int $idFuncion = null,
        ?string $excluirSession = null,
        ?mysqli $connBoletos = null
    ): array {
        $conn = $connBoletos ?: getReservasConnection();
        if (!$conn) {
            return [
                'success' => false,
                'vendidos' => [],
                'reservados' => [],
                'reservados_detalle' => [],
                'ocupados' => [],
            ];
        }

        limpiarReservasExpiradas($conn);

        $vendidos = [];
        if ($idFuncion) {
            $stmt = $conn->prepare("
                SELECT a.codigo_asiento
                FROM boletos b
                INNER JOIN asientos a ON b.id_asiento = a.id_asiento
                WHERE b.id_evento = ? AND b.id_funcion = ? AND b.estatus = 1
            ");
            $stmt->bind_param('ii', $idEvento, $idFuncion);
        } else {
            $stmt = $conn->prepare("
                SELECT a.codigo_asiento
                FROM boletos b
                INNER JOIN asientos a ON b.id_asiento = a.id_asiento
                WHERE b.id_evento = ? AND b.estatus = 1
            ");
            $stmt->bind_param('i', $idEvento);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $vendidos[] = $row['codigo_asiento'];
        }
        $stmt->close();

        $detalle = listarReservasActivasDetalle($idEvento, $idFuncion, $excluirSession);
        $reservados = array_values(array_unique(array_map(static function ($r) {
            return $r['codigo'];
        }, $detalle)));

        $ocupados = array_values(array_unique(array_merge($vendidos, $reservados)));

        return [
            'success' => true,
            'vendidos' => $vendidos,
            'reservados' => $reservados,
            'reservados_detalle' => $detalle,
            'ocupados' => $ocupados,
        ];
    }
}

/**
 * Renueva el TTL de todos los holds activos de una sesión (p. ej. al crear orden).
 */
if (!function_exists('renovarReservasSesion')) {
    function renovarReservasSesion(
        string $sessionId,
        int $idEvento,
        ?int $idFuncion = null,
        int $ttlSeg = RESERVA_TTL_SEG
    ): int {
        $conn = getReservasConnection();
        if (!$conn || $sessionId === '') {
            return 0;
        }
        limpiarReservasExpiradas($conn);
        $idFunc = $idFuncion ?: 0;
        $expCalc = calcularExpiraReserva($conn, $ttlSeg);
        $expira = $expCalc['db'];
        $stmt = $conn->prepare("
            UPDATE reservas_temporales
            SET expira_en = ?
            WHERE session_id = ? AND id_evento = ? AND id_funcion = ?
              AND expira_en > NOW()
        ");
        $stmt->bind_param('ssii', $expira, $sessionId, $idEvento, $idFunc);
        $stmt->execute();
        $n = $stmt->affected_rows;
        $stmt->close();
        return $n;
    }
}

/**
 * Renueva holds de la sesión aunque acaben de caducar (gracia en segundos).
 * Útil al clic en Pagar con 1–2 s en el timer: la latencia no debe perder el apartado.
 */
if (!function_exists('renovarReservasSesionConGracia')) {
    function renovarReservasSesionConGracia(
        string $sessionId,
        int $idEvento,
        ?int $idFuncion = null,
        int $ttlSeg = RESERVA_TTL_SEG,
        int $graciaSeg = 45
    ): int {
        $conn = getReservasConnection();
        if (!$conn || $sessionId === '') {
            return 0;
        }
        $ttlSeg = max(60, $ttlSeg);
        $graciaSeg = max(0, min(120, $graciaSeg));
        $idFunc = $idFuncion ?: 0;
        $expCalc = calcularExpiraReserva($conn, $ttlSeg);
        $expira = $expCalc['db'];
        // No llamar limpiarReservasExpiradas: necesitamos rescatar filas recién vencidas
        $stmt = $conn->prepare("
            UPDATE reservas_temporales
            SET expira_en = ?
            WHERE session_id = ? AND id_evento = ? AND id_funcion = ?
              AND origen = 'online'
              AND expira_en > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->bind_param('ssiii', $expira, $sessionId, $idEvento, $idFunc, $graciaSeg);
        $stmt->execute();
        $n = $stmt->affected_rows;
        $stmt->close();
        return $n;
    }
}

/**
 * Borra las reservas de la sesión correspondientes a los códigos vendidos.
 * Se llama después del COMMIT de la venta.
 */
if (!function_exists('liberarTrasVenta')) {
    function liberarTrasVenta(string $sessionId, int $idEvento, ?int $idFuncion, array $codigos): void {
        liberarAsientos($sessionId, $idEvento, $idFuncion, $codigos);
    }
}

/** Elimina TODAS las reservas temporales de una sesión (recarga, cierre de pestaña, etc.) */
if (!function_exists('liberarReservasSesion')) {
    function liberarReservasSesion(string $sessionId): int {
        $conn = getReservasConnection();
        if (!$conn || $sessionId === '') return 0;

        $stmt = $conn->prepare('DELETE FROM reservas_temporales WHERE session_id = ?');
        $stmt->bind_param('s', $sessionId);
        $stmt->execute();
        $n = $stmt->affected_rows;
        $stmt->close();
        return $n;
    }
}
