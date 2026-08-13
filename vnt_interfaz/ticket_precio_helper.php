<?php
/**
 * Texto de precio para tickets impresos / PDF / QZ
 */
function texto_precio_boleto_ticket(
    float $precio_final,
    ?string $nombre_categoria = '',
    ?string $tipo_boleto = null
): string {
    $tipo = strtolower(trim((string) ($tipo_boleto ?? '')));

    // Cortesía explícita (tipo de boleto) — distinto de evento gratuito
    if ($tipo === 'cortesia') {
        return 'CORTESÍA';
    }

    if ($precio_final > 0) {
        return '$' . number_format($precio_final, 2);
    }

    $cat = strtolower($nombre_categoria ?? '');
    if (str_contains($cat, 'cortes')) {
        return 'CORTESÍA';
    }
    if (str_contains($cat, 'no venta') || str_contains($cat, 'noventa')) {
        return '$0.00';
    }

    return 'EVENTO GRATUITO';
}
