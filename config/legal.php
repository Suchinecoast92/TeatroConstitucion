<?php
/**
 * Datos públicos de los textos legales (aviso de privacidad, términos, reembolsos, facturación).
 * No son secretos: se editan aquí y se versionan en Git.
 *
 * Antes de abrir la venta online, completar los marcados como PENDIENTE
 * (ver docs/MIGRACION_PRODUCCION.md → "Textos legales"). Mientras estén vacíos,
 * las páginas muestran el nombre del teatro y la ciudad.
 */
if (defined('LEGAL_CONFIG_INCLUDED')) {
    return;
}
define('LEGAL_CONFIG_INCLUDED', true);

/** PENDIENTE: nombre o razón social de quien opera el teatro y recibe los pagos (p. ej. el Ayuntamiento o la A.C.). */
define('LEGAL_RESPONSABLE', '');

/** PENDIENTE: domicilio completo para oír y recibir notificaciones. */
define('LEGAL_DOMICILIO', '');

/** Correo para derechos ARCO, aclaraciones, reembolsos y facturación. */
define('LEGAL_CORREO', 'teatroconstitucion@outlook.es');

define('LEGAL_TELEFONO', '+52 (453) 534 5751');

/**
 * PENDIENTE: política de facturación. null = aún no definida (texto general de contacto);
 * true = se emite CFDI a solicitud; false = no se emite factura.
 */
define('LEGAL_EMITE_FACTURA', null);

/** Días naturales tras la compra para pedir factura (solo si LEGAL_EMITE_FACTURA === true). */
define('LEGAL_DIAS_SOLICITAR_FACTURA', 30);

/** Fecha de la última actualización de los textos (se muestra al pie). */
define('LEGAL_FECHA_ACTUALIZACION', '29 de septiembre de 2026');

function legal_responsable(): string
{
    return LEGAL_RESPONSABLE !== '' ? LEGAL_RESPONSABLE : 'Teatro Constitución de Apatzingán';
}

function legal_domicilio(): string
{
    return LEGAL_DOMICILIO !== '' ? LEGAL_DOMICILIO : 'Apatzingán, Michoacán, México';
}

/** @return string[] datos que faltan por completar */
function legal_datos_pendientes(): array
{
    $faltan = [];
    if (LEGAL_RESPONSABLE === '') {
        $faltan[] = 'LEGAL_RESPONSABLE';
    }
    if (LEGAL_DOMICILIO === '') {
        $faltan[] = 'LEGAL_DOMICILIO';
    }
    if (LEGAL_EMITE_FACTURA === null) {
        $faltan[] = 'LEGAL_EMITE_FACTURA';
    }
    return $faltan;
}
