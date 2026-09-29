<?php
/**
 * Consulta de pedido online: código de orden + correo de la compra → orden.php.
 */
require_once __DIR__ . '/_legal_layout.php';
require_once dirname(__DIR__) . '/api/online/_bootstrap.php';

$error = '';
$codigo = '';
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $codigo = strtoupper(preg_replace('/\s+/', '', (string) ($_POST['codigo'] ?? '')));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));

    if (!api_online_rate_limit('mi_pedido', 10, 600)) {
        $error = 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.';
    } elseif (!preg_match('/^ORD[0-9A-F]{16}$/', $codigo) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Revisa el código de orden (comienza con "ORD") y el correo.';
    } else {
        require_once dirname(__DIR__) . '/conexion.php';
        require_once dirname(__DIR__) . '/includes/ordenes_helper.php';
        $orden = obtenerOrdenPorCodigo($conn, $codigo);
        if ($orden && hash_equals(strtolower(trim((string) $orden['email'])), $email)) {
            header('Location: orden.php?codigo=' . rawurlencode($codigo), true, 303);
            exit;
        }
        $error = 'No encontramos un pedido con ese código y correo.';
    }
}

legal_page_open('Mi pedido');
?>
        <section class="terminos-hero">
            <div class="terminos-hero-panel">
                <h1 class="terminos-display">Mi pedido</h1>
                <p class="eyebrow">Consulta tus boletos</p>
                <p class="lead">Ingresa el código de orden que recibiste por correo y el correo con el que compraste para ver el estado de tu pedido y descargar tus boletos.</p>
            </div>
        </section>

        <div class="terminos-blocks">
<?php legal_bloque_open('consulta', 'bi-search', 'Consulta', 'Buscar mi pedido'); ?>
                <?php if ($error !== ''): ?>
                <div class="pedido-error" role="alert"><?= legal_h($error) ?></div>
                <?php endif; ?>
                <form method="post" class="pedido-form" autocomplete="on">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="codigo">Código de orden</label>
                            <input type="text" class="form-control" id="codigo" name="codigo" required maxlength="24"
                                placeholder="ORD…" value="<?= legal_h($codigo) ?>" autocapitalize="characters" spellcheck="false">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Correo de la compra</label>
                            <input type="email" class="form-control" id="email" name="email" required maxlength="180"
                                placeholder="correo@ejemplo.com" value="<?= legal_h($email) ?>" autocomplete="email">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn-pedido"><i class="bi bi-search"></i> Ver mi pedido</button>
                        </div>
                    </div>
                </form>
<?php legal_bloque_close(); ?>

<?php legal_bloque_open('ayuda', 'bi-question-circle', 'Ayuda', '¿No encuentras tu código?'); ?>
                <p>El código viene en el correo de confirmación que te enviamos al aprobarse el pago; revisa también tu carpeta de spam o correo no deseado.</p>
                <p>Si no lo encuentras, escríbenos a <a href="mailto:<?= legal_h(LEGAL_CORREO) ?>"><?= legal_h(LEGAL_CORREO) ?></a> con el nombre y correo de la compra, el evento y la fecha de la función.</p>
                <p>Para reembolsos y facturación consulta los <a href="terminos.php#reembolsos">Términos y condiciones</a>.</p>
<?php legal_bloque_close(); ?>
        </div>
<?php
legal_page_close();
