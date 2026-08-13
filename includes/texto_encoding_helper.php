<?php
/**
 * Codificación de texto: UTF-8 en la app, ISO-8859-1 para FPDF (boletos).
 */
if (defined('TEXTO_ENCODING_HELPER_INCLUDED')) {
    return;
}
define('TEXTO_ENCODING_HELPER_INCLUDED', true);

/**
 * Convierte texto a ISO-8859-1 para impresión PDF con fuentes estándar FPDF.
 */
function texto_pdf_latin1(?string $texto): string
{
    if ($texto === null || $texto === '') {
        return '';
    }

    $texto = (string) $texto;

    // Ya está en Latin-1 (p. ej. ñ como 0xF1) — no tocar
    if (function_exists('mb_check_encoding') && mb_check_encoding($texto, 'ISO-8859-1')) {
        // Si parece UTF-8 multibyte, sí hay que convertir
        if (!preg_match('/[\xC2-\xDF][\x80-\xBF]|[\xE0-\xEF][\x80-\xBF]{2}/', $texto)) {
            return $texto;
        }
    }

    // Normalizar a UTF-8 si viene en Windows-1252 / ISO-8859-1
    if (function_exists('mb_detect_encoding') && function_exists('mb_convert_encoding')) {
        $enc = mb_detect_encoding($texto, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($enc && $enc !== 'UTF-8') {
            $texto = mb_convert_encoding($texto, 'UTF-8', $enc);
        }
    }

    if (function_exists('mb_convert_encoding')) {
        $out = @mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
        if ($out !== false && $out !== '') {
            return $out;
        }
    }

    if (function_exists('iconv')) {
        $out = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $texto);
        if ($out !== false) {
            return $out;
        }
    }

    if (function_exists('utf8_decode')) {
        return utf8_decode($texto);
    }

    return $texto;
}

/** Alias usado en generadores de boleto PDF */
function convertirTexto(?string $texto): string
{
    return texto_pdf_latin1($texto);
}
