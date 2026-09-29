# Migración de local a producción web

Destino elegido: DigitalOcean App Platform + Managed MySQL (ver `DEPLOY_DIGITALOCEAN.md`). No se migra a PostgreSQL.

Orden obligatorio: **primero resolver PHP, MySQL, archivos y rutas; después migrar datos.** No migrar la BD a ciegas.

## Etapa 0: preparación en código (hecha)

- PHP 8.3 y extensiones declaradas en `composer.json` raíz. `vendor` se instala en `vnt_interfaz/vendor`.
- Conexión MySQL con puerto, TLS, zona horaria por conexión y `sql_mode` configurable.
- HTTPS, IP real y cookies detrás de proxy (`TRUST_PROXY`).
- Errores ocultos en producción.
- QR regenerables. Imágenes de eventos: pendiente la decisión de persistencia.
- Scripts de mantenimiento solo por CLI; credenciales fuera del código.
- Suite QA: 8 archivos de prueba en verde, también con el `sql_mode` estricto de Managed MySQL.

## Etapa 1: desarrollo

Origen: copia del código y copia de la BD para pruebas. Destino: BD de desarrollo con datos anonimizados cuando sea necesario. No hacer ventas reales. **Nunca** usar la BD de producción como desarrollo.

## Etapa 2: staging (App Platform)

Una app de staging separada, con su propio cluster o base, su propio dominio (por ejemplo `staging.<dominio>`) y credenciales de **prueba** de Mercado Pago:

- `APP_ENV=staging` → el webhook exige firma y el mock no se activa salvo con `MP_MODE=mock` explícito.
- Datos de prueba o anonimizados, sin datos personales reales.

Probar (QA completa):

- [ ] `health.php` responde, el build instaló las extensiones y `vendor` quedó en `vnt_interfaz/vendor`
- [ ] `NOW()` de MySQL coincide con la hora de México (zona horaria por conexión)
- [ ] login único en `gestion`, redirección por rol (admin, empleado, control de entrada)
- [ ] `www` redirige login y paneles a `gestion`; `api` solo sirve `/api/`
- [ ] cartelera, funciones, mapa de butacas
- [ ] reserva temporal online y local, liberación al expirar
- [ ] **venta en taquilla** e impresión con QZ Tray desde una PC real (certificado y firma)
- [ ] reportes y panel de ventas de admin (sensibles a `ONLY_FULL_GROUP_BY`)
- [ ] compra online con Payment Brick sandbox: aprobada, rechazada, pendiente
- [ ] webhook firmado desde MP sandbox (y rechazo sin firma)
- [ ] emisión de boleto, QR en página de orden, PDF e imagen **después de un redeploy** (regeneración)
- [ ] control de entrada escaneando QR
- [ ] cancelación y reembolso (cancela boletos)
- [ ] concurrencia: dos compradores y taquilla contra online
- [ ] subir imagen de evento y redesplegar (confirma el problema de persistencia hasta decidir A o B)
- [ ] `php sql/test_fase*.php` contra la BD de staging (por consola de App Platform o job)

## Etapa 3: producción

1. Backup completo de la BD real (y guardar el dump **fuera** de Git).
2. Ventana de mantenimiento: taquilla sin vender.
3. Crear las bases (`trt_25`, `trt_25_backup`, `trt_historico_evento`) y el usuario de app en Managed MySQL.
4. Importar el dump por TLS desde una IP autorizada temporalmente; luego quitar esa IP de trusted sources.
5. Migraciones de esquema pendientes (las tablas online se crean o ajustan solas con `asegurarTabla*`).
6. Desplegar el código y configurar los secretos (SECRET en el panel).
7. Validar datos: conteos por tabla, últimos boletos, funciones futuras y su estado.
8. Pruebas de humo (lista corta de staging).
9. Apertura a taquilla (`https://gestion.<dominio>`).
10. Apertura gradual de la venta online: primero en sandbox en producción oculta; **cobros reales solo con aprobación explícita** (ver `PAGOS.md`).

Rollback: la BD local del teatro queda intacta hasta confirmar producción. Si algo falla en la ventana, se vuelve a taquilla local con el dump previo.

## Entorno de taquilla

La PC de taquilla pasa de `localhost/TeatroConstitucion` a `https://gestion.<dominio>`. `INICIAR_TEATRO.bat` arranca WAMP y abre `localhost`: sirve para desarrollo o instalación local, pero no forma parte del flujo normal con el servidor web.

## Offline

El diseño inicial no vende boletos offline contra una copia antigua de la BD. Sin conexión, se muestra el estado de sistema no disponible.

Los backups son para recuperación, no para sincronización en tiempo real.
