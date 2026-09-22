<?php
/**
 * Antiguo bypass de login eliminado por seguridad (auditoría WEBSEC).
 * Cualquier acceso responde 410 Gone.
 */
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
echo "Gone\n";
exit;
