<?php
/**
 * QR de boletos en boletos_qr/{codigo_unico}.png.
 *
 * El PNG solo codifica codigo_unico (guardado en BD), así que es regenerable:
 * en hosting con disco efímero (DigitalOcean App Platform) el archivo puede
 * desaparecer tras un redeploy y se vuelve a crear al pedirlo.
 */

if (defined('TEATRO_QR_HELPER_INCLUDED')) {
    return;
}
define('TEATRO_QR_HELPER_INCLUDED', true);

function teatro_qr_dir(): string
{
    return dirname(__DIR__) . '/boletos_qr/';
}

function teatro_qr_codigo_valido(string $codigoUnico): bool
{
    return (bool) preg_match('/^[A-Za-z0-9_-]{6,64}$/', $codigoUnico);
}

function teatro_qr_generar(string $codigoUnico): bool
{
    if (!teatro_qr_codigo_valido($codigoUnico)) {
        return false;
    }
    if (!class_exists(\Endroid\QrCode\QrCode::class)) {
        $autoload = dirname(__DIR__) . '/vnt_interfaz/vendor/autoload.php';
        if (!is_file($autoload)) {
            error_log('[qr] vendor Endroid no disponible; omitiendo QR de ' . $codigoUnico);
            return false;
        }
        require_once $autoload;
    }
    try {
        $dir = teatro_qr_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $qrCode = \Endroid\QrCode\QrCode::create($codigoUnico)
            ->setEncoding(new \Endroid\QrCode\Encoding\Encoding('UTF-8'))
            ->setErrorCorrectionLevel(new \Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow())
            ->setSize(300)
            ->setMargin(10)
            ->setRoundBlockSizeMode(new \Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin());
        (new \Endroid\QrCode\Writer\PngWriter())->write($qrCode)->saveToFile($dir . $codigoUnico . '.png');
        return true;
    } catch (Throwable $e) {
        error_log('[qr] ' . $codigoUnico . ': ' . $e->getMessage());
        return false;
    }
}

/**
 * Ruta del PNG, regenerándolo si no existe. null si no se pudo.
 */
function teatro_qr_asegurado(string $codigoUnico): ?string
{
    if (!teatro_qr_codigo_valido($codigoUnico)) {
        return null;
    }
    $path = teatro_qr_dir() . $codigoUnico . '.png';
    if (!is_file($path)) {
        teatro_qr_generar($codigoUnico);
    }
    return is_file($path) ? $path : null;
}
