# Proyecto Teatro Constitución

## Propósito

Sistema web para operación de un teatro, con venta presencial en taquilla y evolución planificada hacia venta de boletos en línea con pasarela de pago.

## Estado actual observado

El repositorio actual contiene módulos de administración, API, autenticación, control de entrada, cartelera, eventos, mapa/taquilla, ventas, sincronización, recursos y scripts de instalación/arranque. También incluye documentación interna existente y scripts `.bat` para entorno local.

La base de datos de producción entregada para análisis (`trt_25 (3).sql`) es un dump de MySQL 8.4.7 generado el 10-08-2026. Contiene, entre otras, las tablas `evento`, `funciones`, `asientos`, `categorias`, `promociones`, `precios_tipo_boleto`, `boletos`, `ventas`, `transacciones`, `cambios_log`, `reservas_temporales`, `conexion_estado`, `configuracion` y `usuarios`.

## Funcionalidad existente que debe preservarse

- Cartelera.
- Obras/eventos.
- Funciones.
- Mapa de butacas.
- Estados y disponibilidad de butacas.
- Selección y carrito.
- Venta presencial.
- Emisión de boletos.
- QR y control de entrada.
- Administración de obras, funciones y precios.
- Bloqueo de butacas.
- Consulta de ventas y ocupación.
- Cancelaciones y devoluciones existentes.

## Objetivo de evolución

Agregar venta online sin duplicar la lógica de negocio ni crear una segunda fuente de verdad para la disponibilidad de butacas.

Arquitectura objetivo:

Cliente web / Taquilla / Administración
            |
          HTTPS
            |
       Backend / API
            |
      Base de datos central
            |
       Pagos online

La base de datos central será la fuente de verdad para funciones, butacas, reservas, ventas y boletos.

## Principios

1. No romper la venta presencial existente.
2. No crear una segunda base de datos para sincronizar disponibilidad.
3. Reutilizar `reservas_temporales` y la lógica existente antes de crear tablas nuevas.
4. La confirmación del pago online debe venir del backend/webhook del proveedor, nunca de datos enviados por el navegador.
5. El backend calcula precios y descuentos a partir de la BD, no confía en precios enviados por el cliente.
6. MySQL nunca debe quedar expuesto directamente a Internet.
7. Secretos y credenciales nunca deben entrar al repositorio.
8. Las migraciones de BD deben ser reversibles y probarse primero en desarrollo.
9. Producción y desarrollo deben estar separadas.
10. Los backups son para recuperación; no sustituyen un mecanismo offline de venta.

## Proveedor de pagos inicial

Mercado Pago es el candidato inicial. El diseño deberá desacoplar el proveedor mediante un servicio/interfaz para permitir Stripe u otro proveedor posteriormente sin rehacer la lógica de ventas.

## Infraestructura objetivo

- Cloudflare como DNS, TLS, capa de protección perimetral y WAF/CDN cuando corresponda.
- DigitalOcean para aplicación y base de datos administrada en producción.
- Taquilla y administración accediendo al backend central por HTTPS.
- Backup automático y copia externa.
- Conectividad de respaldo del teatro recomendada para continuidad operativa.

## Nota de seguridad sobre el repositorio

El repositorio GitHub analizado está actualmente público. El código público no debe contener contraseñas, tokens, API keys, dumps de producción ni datos personales reales. La BD real debe mantenerse fuera de GitHub.
