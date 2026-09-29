<?php
// Health check HTTP de App Platform. No toca la BD ni revela configuración.
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
echo 'ok';
