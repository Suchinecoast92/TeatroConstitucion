<?php
/**
 * Pantalla de sesión / tiempo de compra expirado (flujo online).
 */
$inicio = 'cartelera_cliente.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tu sesión expiró</title>
<link rel="icon" href="imagenes_teatro/nat.png" type="image/png">
<style>
  :root {
    --title: #0f1c3f;
    --muted: #8b93a7;
    --btn: #4a86ff;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    padding: 24px;
  }
  .box {
    width: 100%;
    max-width: 420px;
    text-align: center;
  }
  h1 {
    margin: 0 0 16px;
    color: var(--title);
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: -0.02em;
  }
  p {
    margin: 0 0 8px;
    color: var(--muted);
    font-size: 1.05rem;
    line-height: 1.45;
  }
  .btn {
    display: inline-block;
    margin-top: 28px;
    background: var(--btn);
    color: #fff;
    text-decoration: none;
    font-weight: 700;
    font-size: 1.05rem;
    padding: 14px 36px;
    border-radius: 999px;
    box-shadow: 0 10px 24px rgba(74, 134, 255, 0.35);
    transition: transform .15s ease, box-shadow .15s ease;
  }
  .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 28px rgba(74, 134, 255, 0.45);
    color: #fff;
  }
</style>
</head>
<body>
  <div class="box">
    <h1>Tu sesión expiró</h1>
    <p>Lo sentimos, pero tu sesión ha terminado.</p>
    <p>Puedes comenzar nuevamente, dando clic en el siguiente botón.</p>
    <a class="btn" href="<?= htmlspecialchars($inicio, ENT_QUOTES, 'UTF-8') ?>">Ir al Inicio</a>
  </div>
  <script>
    try {
      Object.keys(sessionStorage).forEach((k) => {
        if (k.startsWith('teatro_checkout_') || k === 'teatro_online_hold_expires' || k === 'teatro_online_session_id') {
          sessionStorage.removeItem(k);
        }
      });
    } catch (e) {}
  </script>
</body>
</html>
