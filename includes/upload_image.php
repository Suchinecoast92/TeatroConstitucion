<?php
/**
 * Validación segura de imágenes subidas (MIME real + getimagesize).
 */

if (defined('TEATRO_UPLOAD_INCLUDED')) {
    return;
}
define('TEATRO_UPLOAD_INCLUDED', true);

/**
 * @return array{ok:bool,ext?:string,error?:string}
 */
function teatro_validar_imagen_subida(string $tmpPath, string $originalName = ''): array
{
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return ['ok' => false, 'error' => 'Archivo de imagen no válido.'];
    }

    $info = @getimagesize($tmpPath);
    if (!is_array($info) || empty($info[2])) {
        return ['ok' => false, 'error' => 'El archivo no es una imagen válida.'];
    }

    $typeMap = [
        IMAGETYPE_JPEG => ['ext' => 'jpg', 'mimes' => ['image/jpeg']],
        IMAGETYPE_PNG => ['ext' => 'png', 'mimes' => ['image/png']],
        IMAGETYPE_GIF => ['ext' => 'gif', 'mimes' => ['image/gif']],
    ];
    $imgType = (int) $info[2];
    if (!isset($typeMap[$imgType])) {
        return ['ok' => false, 'error' => 'Formato no permitido. Usa JPG, PNG o GIF.'];
    }

    $mime = null;
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath) ?: null;
    } elseif (function_exists('mime_content_type')) {
        $mime = mime_content_type($tmpPath) ?: null;
    }
    if ($mime !== null && !in_array($mime, $typeMap[$imgType]['mimes'], true)) {
        return ['ok' => false, 'error' => 'El tipo MIME de la imagen no coincide.'];
    }

    // Extensión del nombre original solo como pista; la extensión final sale del tipo real.
    if ($originalName !== '') {
        $extName = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedNames = ['jpg', 'jpeg', 'png', 'gif'];
        if ($extName !== '' && !in_array($extName, $allowedNames, true)) {
            return ['ok' => false, 'error' => 'Extensión de imagen no permitida.'];
        }
    }

    return ['ok' => true, 'ext' => $typeMap[$imgType]['ext']];
}
