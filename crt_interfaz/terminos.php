<?php
require_once __DIR__ . '/_legal_layout.php';

$correo = legal_h(LEGAL_CORREO);
$mailto = '<a href="mailto:' . $correo . '">' . $correo . '</a>';

legal_page_open('Términos y condiciones');
?>
        <section class="terminos-hero">
            <div class="terminos-hero-panel">
                <h1 class="terminos-display">Términos y condiciones</h1>
                <p class="eyebrow">Teatro Constitución de Apatzingán</p>
                <p class="lead">El acceso y uso del sitio web del Teatro Constitución de Apatzingán (en adelante, "El Teatro") implica la aceptación plena y sin reserva de los presentes Términos y Condiciones. Si el usuario no está de acuerdo con su contenido, deberá abstenerse de utilizar este sitio.</p>
            </div>
        </section>

        <nav class="terminos-toc-wrap" aria-label="Índice de términos">
            <div class="terminos-toc">
                <a href="#uso"><i class="bi bi-globe2"></i> Uso</a>
                <a href="#compra"><i class="bi bi-ticket-perforated"></i> Compra</a>
                <a href="#reembolsos"><i class="bi bi-arrow-counterclockwise"></i> Reembolsos</a>
                <a href="#facturacion"><i class="bi bi-receipt"></i> Facturación</a>
                <a href="#acceso"><i class="bi bi-door-open"></i> Acceso</a>
                <a href="#propiedad"><i class="bi bi-c-circle"></i> Propiedad</a>
                <a href="#datos"><i class="bi bi-shield-lock"></i> Datos</a>
                <a href="#enlaces"><i class="bi bi-link-45deg"></i> Enlaces</a>
                <a href="#responsabilidad"><i class="bi bi-exclamation-triangle"></i> Responsabilidad</a>
                <a href="#modificaciones"><i class="bi bi-pencil-square"></i> Modificaciones</a>
                <a href="#legislacion"><i class="bi bi-bank"></i> Legislación</a>
            </div>
        </nav>

        <div class="terminos-blocks">
<?php legal_bloque_open('uso', 'bi-globe2', 'Sección 01', 'Uso del Sitio Web'); ?>
                <p>El usuario se obliga a utilizar el sitio de manera lícita, adecuada y conforme a la legislación aplicable. Queda estrictamente prohibido alterar, modificar o interferir en el funcionamiento del sitio, así como utilizar herramientas automatizadas (incluidos bots) para realizar compras o cualquier otra actividad dentro del mismo.</p>
<?php legal_bloque_close(); ?>

<?php legal_bloque_open('compra', 'bi-ticket-perforated', 'Sección 02', 'Compra de Boletos'); ?>
                <p>Todos los precios publicados en este sitio son netos, es decir, ya incluyen impuestos y no están sujetos a cargos adicionales por servicio.</p>
                <p>Los pagos en línea se procesan a través de Mercado Pago. El Teatro no recibe ni almacena los datos de tu tarjeta.</p>
                <p>Tu compra queda confirmada cuando el pago es aprobado. En ese momento recibirás un correo con tus boletos y un código de orden (comienza con "ORD"), con el que también puedes consultarlos en <a href="mi_pedido.php">Mi pedido</a>.</p>
                <p>Es responsabilidad exclusiva del usuario revisar con atención la fecha, función, horario, zona y asiento antes de confirmar su compra.</p>
                <p>El Teatro no se hace responsable por boletos adquiridos a través de terceros no autorizados.</p>
<?php legal_bloque_close(); ?>

<?php legal_bloque_open('reembolsos', 'bi-arrow-counterclockwise', 'Sección 03', 'Cancelaciones, reprogramaciones y reembolsos'); ?>
                <p>Las compras son definitivas. No se hacen reembolsos por inasistencia, llegada tarde, cambio de planes o error del usuario al elegir la función o el asiento.</p>
                <p>El reembolso procede en los siguientes casos:</p>
                <ul>
                    <li><strong>Cancelación del evento</strong> por parte del Teatro.</li>
                    <li><strong>Reprogramación del evento.</strong> Los boletos adquiridos son válidos para la nueva fecha; si no puedes o no deseas asistir en esa fecha, puedes solicitar el reembolso antes de que se realice la función reprogramada.</li>
                </ul>
                <p>Cualquier otro caso queda a criterio del Teatro.</p>
                <p>Cuando un evento se cancele o reprograme, el Teatro avisará al correo registrado en la compra.</p>
                <p><strong>Cómo solicitarlo:</strong> escribe a <?= $mailto ?> indicando tu código de orden y el nombre y correo con los que compraste. Las compras en línea se reembolsan por el total pagado al mismo medio de pago, a través de Mercado Pago; el tiempo en que el reembolso se refleje depende de tu banco o del medio de pago que usaste. Las compras en taquilla se reembolsan en la taquilla presentando el boleto.</p>
                <p>Al reembolsarse, los boletos quedan cancelados y pierden su validez para el acceso.</p>
<?php legal_bloque_close(); ?>

<?php legal_bloque_open('facturacion', 'bi-receipt', 'Sección 04', 'Facturación'); ?>
<?php if (LEGAL_EMITE_FACTURA === true): ?>
                <p>Si necesitas factura (CFDI), solicítala a <?= $mailto ?> dentro de los <?= (int) LEGAL_DIAS_SOLICITAR_FACTURA ?> días naturales posteriores a tu compra, indicando tu código de orden, RFC, nombre o razón social, régimen fiscal, código postal y uso del CFDI.</p>
<?php elseif (LEGAL_EMITE_FACTURA === false): ?>
                <p>El Teatro no emite facturas por la venta de boletos. El comprobante de tu compra es el correo de confirmación y la página de tu pedido.</p>
<?php else: ?>
                <p>Si necesitas factura, escríbenos a <?= $mailto ?> con tu código de orden y tus datos fiscales lo antes posible después de tu compra; te indicaremos si procede y cómo obtenerla.</p>
<?php endif; ?>
<?php legal_bloque_close(); ?>

<?php legal_bloque_open('acceso', 'bi-door-open', 'Sección 05', 'Acceso y Admisión al Teatro'); ?>
                <p>Para ingresar a las instalaciones es indispensable presentar un boleto válido en formato físico o digital. Cada boleto permite un solo acceso y únicamente para la función indicada en él.</p>
                <p>El Teatro se reserva el derecho de admisión por razones de seguridad, incumplimiento de normas internas o comportamientos que afecten la experiencia de otros asistentes.</p>
                <p>No se permitirá el acceso con alimentos, bebidas, cámaras profesionales, objetos punzocortantes, sustancias ilícitas o cualquier artículo que el personal de seguridad considere riesgoso.</p>
                <p>La puntualidad es responsabilidad del usuario; una vez iniciada la función, el acceso podrá estar restringido o condicionado al criterio del personal autorizado.</p>
<?php legal_bloque_close(); ?>

<?php legal_bloque_open('propiedad', 'bi-c-circle', 'Sección 06', 'Propiedad Intelectual'); ?>
                <p>Todos los contenidos del sitio, incluidos textos, imágenes, logotipos, diseños, materiales gráficos y audiovisuales, son propiedad del Teatro o cuentan con las licencias correspondientes, y están protegidos por la legislación mexicana e internacional en materia de propiedad intelectual. Queda prohibida su reproducción total o parcial sin autorización previa y por escrito.</p>
<?php legal_bloque_close(); ?>

<?php legal_bloque_open('datos', 'bi-shield-lock', 'Sección 07', 'Protección de Datos Personales'); ?>
                <p>Los datos personales que proporcionas al comprar se tratan conforme a nuestro <a href="privacidad.php">Aviso de privacidad</a>, donde se explica qué datos recabamos, para qué los usamos, con quién los compartimos y cómo ejercer tus derechos de acceso, rectificación, cancelación y oposición (ARCO).</p>
<?php legal_bloque_close(); ?>

<?php legal_bloque_open('enlaces', 'bi-link-45deg', 'Sección 08', 'Enlaces a Sitios de Terceros'); ?>
                <p>El sitio puede contener enlaces a páginas externas o plataformas de terceros. El Teatro no se hace responsable del contenido, prácticas o políticas de privacidad de dichos sitios, ya que operan de manera independiente.</p>
<?php legal_bloque_close(); ?>

<?php legal_bloque_open('responsabilidad', 'bi-exclamation-triangle', 'Sección 09', 'Limitación de Responsabilidad'); ?>
                <p>El Teatro no garantiza la disponibilidad continua y libre de errores del sitio web, y no será responsable por daños derivados del uso o imposibilidad de uso del mismo, ya sea por fallas técnicas, mantenimiento, actualizaciones o causas ajenas al control del Teatro.</p>
<?php legal_bloque_close(); ?>

<?php legal_bloque_open('modificaciones', 'bi-pencil-square', 'Sección 10', 'Modificaciones a los Términos y Condiciones'); ?>
                <p>El Teatro se reserva el derecho de modificar, actualizar o complementar el contenido de los presentes Términos y Condiciones. Las modificaciones entrarán en vigor a partir de su publicación en este sitio y no afectan las compras realizadas antes de su publicación.</p>
                <p class="muted">Última actualización: <?= legal_h(LEGAL_FECHA_ACTUALIZACION) ?>.</p>
<?php legal_bloque_close(); ?>

<?php legal_bloque_open('legislacion', 'bi-bank', 'Sección 11', 'Legislación Aplicable y Jurisdicción'); ?>
                <p>Los presentes Términos y Condiciones se rigen por las leyes de los Estados Unidos Mexicanos. Cualquier controversia derivada de su interpretación o cumplimiento se someterá a los tribunales competentes del Estado de Michoacán, sin perjuicio de los derechos que la legislación de protección al consumidor otorga al usuario.</p>
<?php legal_bloque_close(); ?>
        </div>
<?php
legal_page_close();
