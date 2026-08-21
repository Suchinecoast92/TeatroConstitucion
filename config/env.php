<?php
/**
 * Carga opcional de variables desde un archivo `.env` en la raíz del proyecto.
 * No sustituye un gestor de secretos en producción; solo facilita desarrollo local.
 * Si no existe `.env`, el sistema sigue con los valores por defecto de config/.
 */

if (defined('ENV_LOADER_INCLUDED')) {
    return;
}
define('ENV_LOADER_INCLUDED', true);

if (!function_exists('teatro_load_env')) {
    function teatro_load_env(?string $envPath = null): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $path = $envPath ?: (dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            // Quitar comillas simples o dobles envolventes
            if (
                strlen($value) >= 2
                && (
                    ($value[0] === '"' && substr($value, -1) === '"')
                    || ($value[0] === "'" && substr($value, -1) === "'")
                )
            ) {
                $value = substr($value, 1, -1);
            }

            // No sobrescribir variables ya definidas en el entorno del servidor
            if (getenv($name) === false) {
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
            }
        }
    }
}

if (!function_exists('teatro_env')) {
    /**
     * Lee una variable de entorno con valor por defecto.
     */
    function teatro_env(string $key, $default = null)
    {
        $val = getenv($key);
        if ($val === false || $val === '') {
            return $default;
        }
        return $val;
    }
}

teatro_load_env();

// Alinear PHP con la zona del servidor MySQL (México). Los holds usan reloj MySQL;
// esto evita otros date() desfasados en reportes/UI.
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set((string) teatro_env('APP_TIMEZONE', 'America/Mexico_City'));
}
