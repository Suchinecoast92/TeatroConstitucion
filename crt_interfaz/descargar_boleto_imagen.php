<?php
/**
 * Descarga boleto(s) online como imagen PNG (o ZIP si hay varios).
 * ?codigo=CODIGO_UNICO  → un PNG
 * ?orden=CODIGO_PUBLICO → un PNG o ZIP con todos los de la orden pagada
 */
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/ordenes_helper.php';
require_once __DIR__ . '/../includes/emision_helper.php';

$codigoBoleto = isset($_GET['codigo']) ? trim((string) $_GET['codigo']) : '';
$codigoOrden = isset($_GET['orden']) ? trim((string) $_GET['orden']) : '';

if ($codigoBoleto === '' && $codigoOrden === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Falta código de boleto u orden.';
    exit;
}

/**
 * @return list<array{codigo_unico:string,codigo_asiento:string,tipo_boleto:string,precio_final:float,titulo:string,fecha_hora:?string}>
 */
function boletos_para_descarga(mysqli $conn, string $codigoBoleto, string $codigoOrden): array
{
    if ($codigoOrden !== '') {
        $orden = obtenerOrdenPorCodigo($conn, $codigoOrden);
        if (!$orden || ($orden['estado'] ?? '') !== 'pagada') {
            return [];
        }
        emitir_boletos_orden_pagada($conn, (int) $orden['id_orden']);
        $boletos = emision_listar_boletos_orden($conn, (int) $orden['id_orden']);
        $titulo = '';
        $fecha = null;
        $st = $conn->prepare('SELECT titulo FROM evento WHERE id_evento = ?');
        $eid = (int) $orden['id_evento'];
        $st->bind_param('i', $eid);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        $titulo = (string) ($row['titulo'] ?? ('Evento #' . $eid));

        $st2 = $conn->prepare('SELECT fecha_hora FROM funciones WHERE id_funcion = ?');
        $fid = (int) $orden['id_funcion'];
        $st2->bind_param('i', $fid);
        $st2->execute();
        $frow = $st2->get_result()->fetch_assoc();
        $st2->close();
        $fecha = $frow['fecha_hora'] ?? null;

        $out = [];
        foreach ($boletos as $b) {
            if ($b['codigo_unico'] === '') {
                continue;
            }
            $out[] = [
                'codigo_unico' => $b['codigo_unico'],
                'codigo_asiento' => $b['codigo_asiento'],
                'tipo_boleto' => $b['tipo_boleto'],
                'precio_final' => $b['precio_final'],
                'titulo' => $titulo,
                'fecha_hora' => $fecha,
            ];
        }
        return $out;
    }

    // Un boleto: debe existir y preferiblemente estar ligado a orden online pagada
    $st = $conn->prepare('
        SELECT b.codigo_unico, a.codigo_asiento, b.tipo_boleto, b.precio_final,
               e.titulo, f.fecha_hora
        FROM boletos b
        INNER JOIN asientos a ON a.id_asiento = b.id_asiento
        INNER JOIN evento e ON e.id_evento = b.id_evento
        INNER JOIN funciones f ON f.id_funcion = b.id_funcion
        WHERE b.codigo_unico = ? AND b.estatus = 1
        LIMIT 1
    ');
    $st->bind_param('s', $codigoBoleto);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) {
        return [];
    }
    return [[
        'codigo_unico' => (string) $row['codigo_unico'],
        'codigo_asiento' => (string) $row['codigo_asiento'],
        'tipo_boleto' => (string) $row['tipo_boleto'],
        'precio_final' => (float) $row['precio_final'],
        'titulo' => (string) $row['titulo'],
        'fecha_hora' => $row['fecha_hora'] ?? null,
    ]];
}

function ticket_font_path(): ?string
{
    $candidates = [
        'C:/Windows/Fonts/arial.ttf',
        'C:/Windows/Fonts/segoeui.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
    ];
    foreach ($candidates as $p) {
        if (is_readable($p)) {
            return $p;
        }
    }
    return null;
}

function ticket_draw_text($im, int $size, int $x, int $y, string $text, int $color, string $align = 'left'): void
{
    $font = ticket_font_path();
    if ($font) {
        $bbox = imagettfbbox($size, 0, $font, $text);
        $w = (int) abs($bbox[2] - $bbox[0]);
        if ($align === 'center') {
            $x = (int) ($x - $w / 2);
        }
        imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
        return;
    }
    $fw = imagefontwidth(5);
    $fh = imagefontheight(5);
    $safe = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
    if ($align === 'center') {
        $x = (int) ($x - (strlen($safe) * $fw) / 2);
    }
    imagestring($im, 5, $x, $y - $fh + 2, $safe, $color);
}

/**
 * @param array{codigo_unico:string,codigo_asiento:string,tipo_boleto:string,precio_final:float,titulo:string,fecha_hora:?string} $b
 */
function generar_png_boleto(array $b): ?string
{
    if (!function_exists('imagecreatetruecolor')) {
        return null;
    }

    $qrPath = dirname(__DIR__) . '/boletos_qr/' . $b['codigo_unico'] . '.png';
    if (!is_file($qrPath)) {
        emision_generar_qr_png($b['codigo_unico']);
    }
    if (!is_file($qrPath)) {
        return null;
    }

    $qr = @imagecreatefrompng($qrPath);
    if (!$qr) {
        return null;
    }

    $W = 720;
    $H = 980;
    $im = imagecreatetruecolor($W, $H);
    imagealphablending($im, true);
    imagesavealpha($im, true);

    $bg = imagecolorallocate($im, 11, 17, 32);
    $card = imagecolorallocate($im, 30, 41, 59);
    $white = imagecolorallocate($im, 248, 250, 252);
    $muted = imagecolorallocate($im, 148, 163, 184);
    $accent = imagecolorallocate($im, 37, 99, 235);
    $line = imagecolorallocate($im, 71, 85, 105);

    imagefilledrectangle($im, 0, 0, $W, $H, $bg);
    imagefilledrectangle($im, 36, 36, $W - 36, $H - 36, $card);
    imagerectangle($im, 36, 36, $W - 36, $H - 36, $line);

    ticket_draw_text($im, 18, (int) ($W / 2), 78, 'Teatro Constitucion', $muted, 'center');
    ticket_draw_text($im, 26, (int) ($W / 2), 120, 'BOLETO DE ENTRADA', $white, 'center');

    $titulo = mb_substr($b['titulo'], 0, 42, 'UTF-8');
    ticket_draw_text($im, 22, (int) ($W / 2), 175, $titulo, $white, 'center');

    if (!empty($b['fecha_hora'])) {
        try {
            $dt = new DateTime((string) $b['fecha_hora']);
            $fechaTxt = $dt->format('d/m/Y H:i');
        } catch (Throwable $e) {
            $fechaTxt = (string) $b['fecha_hora'];
        }
        ticket_draw_text($im, 16, (int) ($W / 2), 210, $fechaTxt, $muted, 'center');
    }

    imageline($im, 80, 235, $W - 80, 235, $line);

    ticket_draw_text($im, 20, (int) ($W / 2), 280, 'Asiento ' . $b['codigo_asiento'], $white, 'center');
    $tipoPrecio = $b['tipo_boleto'] . ' · $' . number_format((float) $b['precio_final'], 2);
    ticket_draw_text($im, 16, (int) ($W / 2), 315, $tipoPrecio, $muted, 'center');

    $qrSize = 360;
    $qrX = (int) (($W - $qrSize) / 2);
    $qrY = 350;
    imagefilledrectangle($im, $qrX - 16, $qrY - 16, $qrX + $qrSize + 16, $qrY + $qrSize + 16, $white);
    imagecopyresampled($im, $qr, $qrX, $qrY, 0, 0, $qrSize, $qrSize, imagesx($qr), imagesy($qr));
    imagedestroy($qr);

    ticket_draw_text($im, 14, (int) ($W / 2), $qrY + $qrSize + 50, $b['codigo_unico'], $white, 'center');
    ticket_draw_text($im, 13, (int) ($W / 2), $H - 70, 'Presenta este QR en la entrada', $accent, 'center');

    ob_start();
    imagepng($im, null, 6);
    $bin = ob_get_clean();
    imagedestroy($im);
    return $bin !== false ? $bin : null;
}

$lista = boletos_para_descarga($conn, $codigoBoleto, $codigoOrden);
if (!$lista) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Boleto u orden no disponible para descarga.';
    exit;
}

// Un solo boleto → PNG
if (count($lista) === 1) {
    $png = generar_png_boleto($lista[0]);
    if ($png === null) {
        // Fallback: QR crudo
        $qrPath = dirname(__DIR__) . '/boletos_qr/' . $lista[0]['codigo_unico'] . '.png';
        if (!is_file($qrPath)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'No se pudo generar la imagen del boleto.';
            exit;
        }
        $name = 'Boleto_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $lista[0]['codigo_asiento']) . '.png';
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($qrPath));
        readfile($qrPath);
        exit;
    }
    $name = 'Boleto_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $lista[0]['codigo_asiento']) . '.png';
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . strlen($png));
    echo $png;
    exit;
}

// Varios → ZIP de PNG
if (!class_exists('ZipArchive')) {
    // Sin zip: descarga el primero (el botón puede usarse por boleto)
    $png = generar_png_boleto($lista[0]);
    if ($png === null) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'No se pudo generar la imagen.';
        exit;
    }
    $name = 'Boleto_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $lista[0]['codigo_asiento']) . '.png';
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    echo $png;
    exit;
}

$tmp = tempnam(sys_get_temp_dir(), 'bolzip');
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No se pudo crear el archivo ZIP.';
    exit;
}

foreach ($lista as $b) {
    $png = generar_png_boleto($b);
    $fname = 'Boleto_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $b['codigo_asiento']) . '.png';
    if ($png !== null) {
        $zip->addFromString($fname, $png);
    } else {
        $qrPath = dirname(__DIR__) . '/boletos_qr/' . $b['codigo_unico'] . '.png';
        if (is_file($qrPath)) {
            $zip->addFile($qrPath, $fname);
        }
    }
}
$zip->close();

$zipName = 'Boletos_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $codigoOrden !== '' ? $codigoOrden : 'orden') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
exit;
