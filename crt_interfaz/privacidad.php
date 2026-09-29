<?php
require_once __DIR__ . '/_legal_layout.php';

$correo = legal_h(LEGAL_CORREO);
$mailto = '<a href="mailto:' . $correo . '">' . $correo . '</a>';
$responsable = legal_h(legal_responsable());
$domicilio = legal_h(legal_domicilio());

legal_page_open('Aviso de privacidad');
?>
        <section class="terminos-hero">
            <div class="terminos-hero-panel">
                <h1 class="terminos-display">Aviso de privacidad</h1>
                <p class="eyebrow">Teatro Constitución de Apatzingán</p>
                <p class="lead">Este aviso explica qué datos personales recabamos cuando compras boletos, para qué los usamos, con quién los compartimos y cómo puedes ejercer tus derechos, conforme a la legislación mexicana aplicable en materia de protección de datos personales.</p>
            </div>
        </section>

        <nav class="terminos-toc-wrap" aria-label="Índice del aviso">
            <div class="terminos-toc">
                <a href="#responsable"><i class="bi bi-building"></i> Responsable</a>
                <a href="#datos"><i class="bi bi-person-vcard"></i> Datos</a>
                <a href="#finalidades"><i class="bi bi-bullseye"></i> Finalidades</a>
                <a href="#terceros"><i class="bi bi-share"></i> Terceros</a>
                <a href="#conservacion"><i class="bi bi-archive"></i> Conservación</a>
                <a href="#arco"><i class="bi bi-person-check"></i> Derechos ARCO</a>
                <a href="#cookies"><i class="bi bi-sliders"></i> Cookies</a>
                <a href="#cambios"><i class="bi bi-pencil-square"></i> Cambios</a>
            </div>
        </nav>

        <div class="terminos-blocks">
<?php legal_bloque_open('responsable', 'bi-building', 'Sección 01', 'Responsable del tratamiento'); ?>
                <p><?= $responsable ?>, con domicilio en <?= $domicilio ?>, es responsable del tratamiento de los datos personales que se recaban a través de este sitio y de la taquilla del Teatro Constitución.</p>
                <p>Para cualquier asunto relacionado con tus datos personales puedes escribir a <?= $mailto ?>.</p>
<?php legal_bloque_close(); ?>

<?php legal_bloque_open('datos', 'bi-person-vcard', 'Sección 02', 'Datos personales que recabamos'); ?>
                <p>Al comprar en línea te pedimos:</p>
                <ul>
                    <li>Nombre y apellido.</li>
                    <li>Correo electrónico.</li>
                    <li>Teléfono (opcional).</li>
                </ul>
                <p>Del pago solo recibimos su resultado: identificador de la operación, estado y monto. <strong>No recibimos ni guardamos los datos de tu tarjeta</strong>; los captura y procesa directamente Mercado Pago.</p>
                <p>En la taquilla solo recabamos tus datos de contacto si decides proporcionarlos.</p>
                <p>No recabamos datos personales sensibles. La compra en línea debe hacerla una persona mayor de edad.</p>
<?php legal_bloque_close(); ?>

<?php legal_bloque_open('finalidades', 'bi-bullseye', 'Sección 03', 'Para qué usamos tus datos'); ?>
                <p>Usamos tus datos únicamente para:</p>
                <ul>
                    <li>Procesar tu compra y confirmar el pago.</li>
                    <li>Emitir tus boletos y enviártelos por correo.</li>
                    <li>Validar tu acceso a la función.</li>
                    <li>Avisarte si el evento se cancela o se reprograma.</li>
                    <li>Atender aclaraciones, reembolsos y, en su caso, solicitudes de factura.</li>
                    <li>Cumplir obligaciones legales, fiscales y contables.</li>
                </ul>
                <p>Estas finalidades son necesarias para la compra. <strong>No usamos tus datos para publicidad ni los vendemos.</strong> Si en el futuro quisiéramos enviarte promociones, te pediríamos antes tu consentimiento.</p>
<?php legal_bloque_close(); ?>

<?php legal_bloque_open('terceros', 'bi-share', 'Sección 04', 'Con quién compartimos tus datos'); ?>
                <p>Para operar la venta en línea nos apoyamos en proveedores que tratan tus datos solo por cuenta nuestra y para los fines de este aviso:</p>
                <ul>
                    <li><strong>Mercado Pago</strong>, que procesa el pago conforme a su propio aviso de privacidad.</li>
                    <li><strong>Proveedores de alojamiento en la nube y de envío de correo</strong>, cuyos servidores pueden ubicarse fuera de México.</li>
                </ul>
                <p>Fuera de estos casos, solo compartimos tus datos con autoridades competentes cuando la ley nos obligue a hacerlo.</p>
<?php legal_bloque_close(); ?>

<?php legal_bloque_open('conservacion', 'bi-archive', 'Sección 05', 'Conservación y seguridad'); ?>
                <p>Conservamos tus datos durante el tiempo necesario para las finalidades descritas y para cumplir los plazos que exigen las obligaciones fiscales y contables. Después, se eliminan o se conservan de forma que no te identifiquen.</p>
                <p>Aplicamos medidas de seguridad administrativas, técnicas y físicas para proteger tus datos contra daño, pérdida, alteración o acceso no autorizado, como conexiones cifradas y acceso restringido al personal autorizado.</p>
<?php legal_bloque_close(); ?>

<?php legal_bloque_open('arco', 'bi-person-check', 'Sección 06', 'Tus derechos (ARCO) y revocación del consentimiento'); ?>
                <p>Puedes acceder a tus datos, rectificarlos, cancelarlos u oponerte a su tratamiento (derechos ARCO), así como revocar tu consentimiento o limitar su uso, escribiendo a <?= $mailto ?> con:</p>
                <ul>
                    <li>Tu nombre y un medio para responderte.</li>
                    <li>Un documento que acredite tu identidad (o la de tu representante).</li>
                    <li>La descripción clara de lo que solicitas y, si aplica, tu código de orden.</li>
                </ul>
                <p>Te responderemos dentro de los plazos que establece la legislación aplicable. Ten en cuenta que no podremos cancelar datos que debamos conservar por obligación legal, como los relacionados con pagos.</p>
                <p>También puedes presentar tu solicitud directamente en la taquilla del Teatro.</p>
<?php legal_bloque_close(); ?>

<?php legal_bloque_open('cookies', 'bi-sliders', 'Sección 07', 'Cookies y almacenamiento en el navegador'); ?>
                <p>Usamos una cookie de sesión y el almacenamiento local de tu navegador solo para que funcione la compra: mantener los asientos que elegiste mientras pagas y el tiempo restante para completar el pago. No usamos cookies de publicidad ni de rastreo.</p>
                <p>El formulario de pago de Mercado Pago puede usar sus propias cookies para prevenir fraudes. Puedes borrar las cookies desde la configuración de tu navegador; si las bloqueas, es posible que no puedas completar la compra.</p>
<?php legal_bloque_close(); ?>

<?php legal_bloque_open('cambios', 'bi-pencil-square', 'Sección 08', 'Cambios a este aviso'); ?>
                <p>Cualquier cambio a este aviso de privacidad se publicará en esta página.</p>
                <p class="muted">Última actualización: <?= legal_h(LEGAL_FECHA_ACTUALIZACION) ?>.</p>
<?php legal_bloque_close(); ?>
        </div>
<?php
legal_page_close();
