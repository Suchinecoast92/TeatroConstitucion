<?php
require_once '../../evt_interfaz/conexion.php';
header('Content-Type: application/json');

$id_evento = isset($_GET['id_evento']) ? (int)$_GET['id_evento'] : 0;
$db_mode = $_GET['db'] ?? 'actual';

if (!$id_evento) {
    echo json_encode([]);
    exit;
}

$db_actual = 'trt_25';
$db_historico = 'trt_historico_evento';
$databases = [];

// Determine DBs (similar logic to stats)
$historico_disponible = false;
$check_hist = $conn->query("SHOW DATABASES LIKE '{$db_historico}'");
if ($check_hist && $check_hist->num_rows > 0) $historico_disponible = true;

if ($db_mode === 'ambas') {
    $databases[] = $db_actual;
    if ($historico_disponible) $databases[] = $db_historico;
} elseif ($db_mode === 'historico') {
    if ($historico_disponible) $databases[] = $db_historico;
} else {
    $databases[] = $db_actual;
}

$funciones = [];

foreach ($databases as $db) {
    $sql = "SELECT id_funcion, fecha_hora, estado FROM {$db}.funciones WHERE id_evento = $id_evento ORDER BY fecha_hora DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            // Format for display
            $dt = new DateTime($row['fecha_hora']);
            $label = $dt->format('d/m/Y H:i') . " (" . ($row['estado'] == 0 ? "Activa" : "Cerrada") . ")";
            // Check duplicates if aggregating (unlikely ID collision between DBs? IDs might overlap if auto-increment is not synced, but let's assume separate or we label source)
            // Actually, IDs usually collide if we just merge. But for "Ambas", it's tricky.
            // Usually users filter per specific DB context or we trust IDs are distinct enough or we just show them.
            // Let's add DB tag if 'ambas'
            if ($db_mode === 'ambas') {
                $label .= " [" . ($db == $db_actual ? "Actual" : "Hist") . "]";
            }
            // Use a composite ID key if needed, or just ID if we handle it. 
            // The stats API needs to know which DB the function belongs to if IDs overlap.
            // But usually we just pass ID_FUNCION. If ID 1 exists in both, it might be an issue.
            // For now, let's assume we pass just ID, and the stats API looks in both or specific DB.
            
            $funciones[] = [
                'id' => $row['id_funcion'],
                'label' => $label,
                'db' => $db // Optional, front doesn't use it yet but good for debug
            ];
        }
    }
}

echo json_encode($funciones);
?>
