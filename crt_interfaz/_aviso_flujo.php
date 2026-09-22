<?php
/**
 * Pantalla centrada de aviso (mismo lenguaje visual que sesion_expirada.php).
 *
 * Variables esperadas:
 *   $aviso_titulo (string)
 *   $aviso_lineas (string[])
 *   $aviso_btn (string) texto del botón
 *   $aviso_href (string) destino
 *   $aviso_icon (string) carácter o HTML corto dentro del círculo
 *   $aviso_doc_title (string|null) <title>
 */
$aviso_titulo = (string) ($aviso_titulo ?? 'Aviso');
$aviso_lineas = isset($aviso_lineas) && is_array($aviso_lineas) ? $aviso_lineas : [];
$aviso_btn = (string) ($aviso_btn ?? 'Ir a cartelera');
$aviso_href = (string) ($aviso_href ?? 'cartelera_cliente.php');
$aviso_icon = (string) ($aviso_icon ?? '!');
$aviso_doc_title = (string) ($aviso_doc_title ?? $aviso_titulo);

$h = static function ($s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $h($aviso_doc_title) ?></title>
<link rel="icon" href="imagenes_teatro/nat.png" type="image/png">
<style>
  :root {
    --text: #e8e8ea;
    --muted: #a1a1aa;
    --stroke: rgba(255, 255, 255, 0.14);
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    color: var(--text);
    font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif;
    background-image:
      linear-gradient(180deg, rgba(0, 0, 0, 0.58) 0%, rgba(0, 0, 0, 0.75) 50%, rgba(0, 0, 0, 0.85) 100%),
      url('imagenes_teatro/TeatroNoche1.jpg');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
  }
  .box {
    width: 100%;
    max-width: 440px;
    text-align: center;
    padding: 32px 28px 28px;
    background: linear-gradient(160deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.04) 40%, rgba(0, 0, 0, 0.35));
    backdrop-filter: blur(22px) saturate(115%);
    -webkit-backdrop-filter: blur(22px) saturate(115%);
    border-radius: 20px;
    border: 1px solid var(--stroke);
    box-shadow: 0 28px 64px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.12);
    animation: avisoIn .45s ease both;
  }
  @keyframes avisoIn {
    from { opacity: 0; transform: translateY(12px) scale(0.98); }
    to { opacity: 1; transform: none; }
  }
  .icon {
    width: 56px;
    height: 56px;
    margin: 0 auto 18px;
    border-radius: 999px;
    display: grid;
    place-items: center;
    font-size: 1.45rem;
    color: #fafafa;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.18);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
  }
  h1 {
    margin: 0 0 14px;
    color: #fafafa;
    font-size: clamp(1.55rem, 4vw, 1.9rem);
    font-weight: 750;
    letter-spacing: -0.02em;
  }
  p {
    margin: 0 0 8px;
    color: var(--muted);
    font-size: 1.02rem;
    line-height: 1.5;
  }
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-top: 26px;
    background: linear-gradient(160deg, rgba(255, 255, 255, 0.92), rgba(220, 220, 224, 0.88));
    color: #0a0a0a !important;
    text-decoration: none;
    font-weight: 750;
    font-size: 1.02rem;
    padding: 14px 34px;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.28);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.7);
    transition: transform .15s ease, filter .15s ease;
  }
  .btn:hover {
    transform: translateY(-1px);
    filter: brightness(1.05);
    color: #000 !important;
  }
</style>
</head>
<body>
  <div class="box" role="alert">
    <div class="icon" aria-hidden="true"><?= $h($aviso_icon) ?></div>
    <h1><?= $h($aviso_titulo) ?></h1>
    <?php foreach ($aviso_lineas as $linea): ?>
      <?php if (trim((string) $linea) === '') continue; ?>
      <p><?= $h($linea) ?></p>
    <?php endforeach; ?>
    <a class="btn" href="<?= $h($aviso_href) ?>"><?= $h($aviso_btn) ?></a>
  </div>
</body>
</html>
