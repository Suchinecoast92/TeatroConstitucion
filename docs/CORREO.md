# Correo de confirmación de compra

Cuando una orden online queda pagada y con todos sus boletos emitidos, el cliente recibe un correo con:

- evento, fecha y hora de la función, código de orden y total;
- un bloque por boleto: asiento, tipo, precio, código único y su QR embebido (sirve en la entrada aunque el cliente no tenga internet);
- el botón "Ver mis boletos" → `APP_URL/crt_interfaz/orden.php?codigo=<codigo_publico>` (misma página de siempre, con descarga de boletos).

El pago se sigue confirmando solo por webhook/verificación en backend; el correo sale después de la emisión, nunca desde el navegador.

## Arquitectura

Igual que los pagos, el transporte está aislado detrás de una interfaz (`includes/correo/`):

| Archivo | Qué hace |
|---|---|
| `MailerInterface.php` | Contrato del transporte (`enviar(mensaje)`) |
| `MockMailer.php` | Modo simulado: no se conecta a nada; guarda el correo completo como `.eml` |
| `SmtpMailer.php` | Envío real por SMTP con PHPMailer (cualquier proveedor) |
| `CorreoService.php` | Arma el mensaje, garantiza un solo envío, reintentos y estado |

Cambiar a un proveedor por API HTTP (Resend, SendGrid, Mailgun…) es agregar otra clase que implemente la interfaz y un valor nuevo de `MAIL_MODE`.

**Un solo envío:** tabla `orden_notificaciones` con `UNIQUE(id_orden, tipo)`. Cada intento reclama la fila con un `UPDATE` condicional; solo quien la reclama envía, aunque el webhook, la página de la orden y el admin emitan la misma orden al mismo tiempo. Un intento que quedó "enviando" más de 10 minutos (proceso caído) se puede volver a reclamar.

**Sin retrasar el webhook:** la emisión solo programa el envío; el correo sale al terminar la petición (`register_shutdown_function` + `fastcgi_finish_request` en App Platform), con una conexión a BD propia. Si el correo falla, la venta no se afecta: queda `fallido` con el error.

## Variables

| Variable | Uso |
|---|---|
| `MAIL_MODE` | `mock` (simulado) · `smtp` · `off`. Sin definir: `mock` en local, `off` en producción |
| `MAIL_MOCK_DIR` | Carpeta de los `.eml` simulados (por defecto `<temp>/teatro_correos`, fuera del sitio público) |
| `MAIL_FROM`, `MAIL_FROM_NAME` | Remitente. Dominio verificado en el proveedor (SPF/DKIM) o el correo cae en spam |
| `MAIL_REPLY_TO` | Opcional: a dónde responden los clientes |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION` | SMTP del proveedor. `tls` (STARTTLS), `ssl` o `none` |
| `MAIL_USER`, `MAIL_PASS` | Credenciales SMTP. `MAIL_PASS` solo como **SECRET** en App Platform, nunca en Git |

DigitalOcean suele bloquear la salida por los puertos 25/465/587 en cuentas nuevas: usar el puerto alterno del proveedor (normalmente **2525**) o pedir el desbloqueo a soporte.

## Operación

- **Admin → Órdenes online → Ver:** muestra el estado del correo (enviado / fallido con el error / intentos) y el botón **Enviar / Reenviar correo** (reenvía aunque ya se haya enviado; pide confirmación).
- **Reintentos:** `php sql/enviar_correos_pendientes.php` reintenta las órdenes pagadas de las últimas 48 h (`--horas=N`) cuya función no ha pasado, hasta 5 intentos. No manda correos de órdenes viejas al activar el servicio.
- **Reenviar una orden desde consola:** `php sql/enviar_correos_pendientes.php --orden=<id_orden>`.
- Los logs solo registran el correo enmascarado (`pr***@dominio`), nunca contraseñas.

## Pruebas

`php sql/test_correo_confirmacion.php` (modo simulado, requiere una función con venta abierta: `php sql/crear_evento_prueba.php`). Comprueba contenido y QR, un solo envío, reclamo concurrente, reenvío, fallo SMTP sin exponer la contraseña con reintento, orden no pagada y envío automático tras el webhook. Limpieza: `php sql/limpiar_datos_prueba.php --aplicar` (incluye `orden_notificaciones`).

Para ver un correo simulado: abrir el `.eml` con Outlook o Thunderbird.

## Activar el envío real (cuando se decida)

1. Elegir proveedor SMTP y verificar el dominio (registros SPF y DKIM que da el proveedor).
2. En App Platform: `MAIL_HOST`, `MAIL_PORT=2525`, `MAIL_USER`, `MAIL_FROM` y `MAIL_PASS` como SECRET.
3. Probar en staging con `MAIL_MODE=smtp` y un correo propio (botón Reenviar en admin).
4. Cambiar `MAIL_MODE=smtp` en producción.

Reversible: `MAIL_MODE=off` detiene los envíos sin tocar código; `DROP TABLE orden_notificaciones` solo pierde el historial de envíos.
