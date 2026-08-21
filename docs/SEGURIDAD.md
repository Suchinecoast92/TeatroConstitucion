# Seguridad para producción

## Antes de cobrar dinero real

- HTTPS obligatorio.
- MySQL no expuesto públicamente.
- PHP y dependencias actualizadas según compatibilidad.
- Queries parametrizadas.
- Validación de entrada en backend.
- Control de sesión y autorización por rol.
- Protección CSRF en formularios sensibles.
- Escapado de salida contra XSS.
- Rate limiting en endpoints públicos sensibles.
- Webhooks verificados e idempotentes.
- Secretos fuera del repositorio.
- Logs sin datos de tarjeta ni secretos.
- Backups probados mediante restauración.

## Precios

Nunca confiar en el precio enviado por el cliente.

## Butacas

Nunca confiar en que el cliente dice que una butaca está libre.

## Pago

Nunca considerar pagado porque el navegador regresó a una página de éxito.

## Datos reales

La BD de producción no se usa directamente para desarrollo.

Los dumps de producción no se suben a GitHub.

## Repositorio

El repositorio actual es público. Antes de publicar cualquier cambio, revisar:

- `.env`
- credenciales
- tokens
- logs
- datos reales
- dumps SQL
- archivos de backup
- archivos temporales

No publicar secretos históricos si alguna vez fueron comprometidos.
