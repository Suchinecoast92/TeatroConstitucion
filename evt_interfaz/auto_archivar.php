<?php
/**
 * AUTO-ARCHIVO DE EVENTOS
 * Este script archiva automáticamente los eventos cuya última función
 * terminó y ya pasó la medianoche.
 * 
 * Se ejecuta automáticamente al cargar la página de eventos activos.
 */

date_default_timezone_set('America/Mexico_City'); // Zona horaria local

// Evitar ejecución directa
if (!isset($conn)) {
    die('Este archivo debe ser incluido, no ejecutado directamente.');
}

// Configuración
define('HORAS_PARA_ARCHIVAR', 4); // Horas después de la última función para archivar

/**
 * Función para archivar evento (copiada de act_evento.php para independencia)
 */
function archivar_evento_auto($id, $conn)
{
    $db_historico = 'trt_historico_evento';
    $db_principal = 'trt_25';

    // 1. COPIAR TODO A HISTÓRICO
    $conn->query("INSERT IGNORE INTO {$db_historico}.evento SELECT * FROM {$db_principal}.evento WHERE id_evento = $id");
    $conn->query("INSERT IGNORE INTO {$db_historico}.funciones SELECT * FROM {$db_principal}.funciones WHERE id_evento = $id");
    $conn->query("INSERT IGNORE INTO {$db_historico}.categorias SELECT * FROM {$db_principal}.categorias WHERE id_evento = $id");
    $conn->query("INSERT IGNORE INTO {$db_historico}.promociones SELECT * FROM {$db_principal}.promociones WHERE id_evento = $id");
    $conn->query("INSERT IGNORE INTO {$db_historico}.boletos SELECT * FROM {$db_principal}.boletos WHERE id_evento = $id");
    $conn->query("INSERT IGNORE INTO {$db_historico}.precios_tipo_boleto SELECT * FROM {$db_principal}.precios_tipo_boleto WHERE id_evento = $id");

    // 2. BORRAR DE PRODUCCIÓN
    $conn->query("DELETE FROM {$db_principal}.boletos WHERE id_evento = $id");
    $conn->query("DELETE FROM {$db_principal}.precios_tipo_boleto WHERE id_evento = $id");
    $conn->query("DELETE FROM {$db_principal}.promociones WHERE id_evento = $id");
    $conn->query("DELETE FROM {$db_principal}.categorias WHERE id_evento = $id");
    $conn->query("DELETE FROM {$db_principal}.funciones WHERE id_evento = $id");
    $conn->query("DELETE FROM {$db_principal}.evento WHERE id_evento = $id");

    return true;
}

/**
 * Ejecutar auto-archivado de eventos caducados
 * Los eventos se archivan a medianoche del día siguiente a la última función
 */
function ejecutar_auto_archivado($conn)
{
    $eventos_archivados = [];

    // Buscar eventos activos cuya ÚLTIMA función fue AYER o antes
    // Es decir, ya pasó la medianoche después de la última función
    $sql = "
        SELECT e.id_evento, e.titulo,
               MAX(f.fecha_hora) as ultima_funcion,
               DATE(MAX(f.fecha_hora)) as fecha_ultima_funcion
        FROM evento e
        INNER JOIN funciones f ON e.id_evento = f.id_evento
        WHERE e.finalizado = 0
        GROUP BY e.id_evento, e.titulo
        HAVING DATE(ultima_funcion) < CURDATE()
    ";

    $result = $conn->query($sql);
    if (!$result) {
        error_log("Error en consulta de auto-archivado: " . $conn->error);
        return [];
    }

    while ($evento = $result->fetch_assoc()) {
        $id_evento = $evento['id_evento'];
        $titulo = $evento['titulo'];

        $conn->begin_transaction();
        try {
            archivar_evento_auto($id_evento, $conn);
            $conn->commit();

            $eventos_archivados[] = [
                'id' => $id_evento,
                'titulo' => $titulo,
                'ultima_funcion' => $evento['ultima_funcion'],
                'fecha_ultima' => $evento['fecha_ultima_funcion']
            ];

            // Registrar en transacciones si está disponible
            if (function_exists('registrar_transaccion')) {
                registrar_transaccion(
                    'evento_auto_archivar',
                    "Auto-archivado a medianoche: \"$titulo\" (última función: {$evento['ultima_funcion']})"
                );
            }

            error_log("Auto-archivado evento ID $id_evento: $titulo (última función: {$evento['ultima_funcion']})");

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error auto-archivando evento $id_evento: " . $e->getMessage());
        }
    }

    return $eventos_archivados;
}

// ==========================================================
// AUTO-ARCHIVADO DESACTIVADO
// Los eventos ahora se archivan manualmente por el usuario.
// El código se mantiene por si se necesita reactivar.
// ==========================================================

// Ejecutar el auto-archivado (DESACTIVADO)
// $eventos_auto_archivados = ejecutar_auto_archivado($conn);
$eventos_auto_archivados = []; // Siempre vacío - sin auto-archivado

// Si se archivaron eventos, guardar en variable de sesión para notificar
if (!empty($eventos_auto_archivados)) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['eventos_auto_archivados'] = $eventos_auto_archivados;
}

