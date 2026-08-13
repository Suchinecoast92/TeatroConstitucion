<?php
// Reemplazado por panel_admin.php (panel unificado de Sincronización y Ventas)
$qs = $_SERVER['QUERY_STRING'] ? ('&' . $_SERVER['QUERY_STRING']) : '';
header('Location: panel_admin.php?tab=ventas' . $qs);
exit;
