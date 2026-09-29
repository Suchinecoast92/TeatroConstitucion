# Migración de local a producción web

Destino elegido: DigitalOcean App Platform + Managed MySQL (ver `DEPLOY_DIGITALOCEAN.md`). No se migra a PostgreSQL.

Orden obligatorio: **primero resolver PHP, MySQL, archivos y rutas; después migrar datos.** No migrar la BD a ciegas.

## Etapa 0: preparación en código (hecha)

- PHP 8.3 y extensiones declaradas en `composer.json` raíz. `vendor` se instala en `vnt_interfaz/vendor`.
- Conexión MySQL con puerto, TLS, zona horaria por conexión y `sql_mode` configurable.
- HTTPS, IP real y cookies detrás de proxy (`TRUST_PROXY`).
- Errores ocultos en producción.
- QR regenerables. Carteles de eventos guardados en la BD (`evento_imagenes`).
- Scripts de mantenimiento solo por CLI; credenciales fuera del código.
- Suite QA: 8 archivos de prueba en verde, también con el `sql_mode` estricto de Managed MySQL.

## Etapa 1: desarrollo

Origen: copia del código y copia de la BD para pruebas. Destino: BD de desarrollo con datos anonimizados cuando sea necesario. No hacer ventas reales. **Nunca** usar la BD de producción como desarrollo.

La BD local de desarrollo = respaldo del teatro + migraciones (restaurada así el 29-sep-2026 desde el respaldo del 10-ago; idéntica en datos salvo contraseñas de desarrollo). Para mantenerla así:

- No alterar eventos reales para probar. Usar el evento de prueba: `php sql/crear_evento_prueba.php` (crea "[PRUEBA] Evento de pruebas" con función a 7 días y venta abierta; no duplica).
- Correr pruebas: `php sql/test_fase*.php` (usan ese evento).
- Limpiar al terminar: `php sql/limpiar_datos_prueba.php` (muestra) y `--aplicar` (borra órdenes simuladas y eventos `[PRUEBA]` con sus funciones, boletos, reservas y copias en histórico/respaldo; guarda JSON en `%TEMP%`).
- `cambios_log` se vacía solo (registros de más de 1 hora, `api/registrar_cambio.php`): no es pérdida de datos.

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
- [ ] control de entrada escaneando QR (un boleto de otra función muestra el aviso y pide autorizar)
- [ ] cancelación y reembolso (cancela boletos)
- [ ] `Mi pedido`, `Términos` y `Aviso de privacidad` se ven en `www` y el correo de confirmación enlaza a ellos
- [ ] concurrencia: dos compradores y taquilla contra online
- [ ] subir imagen de evento, redesplegar y confirmar que el cartel sigue visible (restaurado desde la BD)
- [ ] `php sql/test_fase*.php` contra la BD de staging (por consola de App Platform o job)

## Etapa 3: producción

**Los datos salen de la PC del teatro, nunca de la BD de desarrollo.** La de desarrollo tiene eventos de prueba y fechas alteradas, y el teatro sigue vendiendo mientras tanto. A producción solo llevamos el **código** y las **migraciones de estructura**.

### Antes del día (con la infraestructura ya creada y staging aprobado)

- Ensayo completo con un dump reciente del teatro, igual que el día real, hasta el paso 7. Anotar cuánto tarda.
- Cartel de cada evento creado en el teatro después del 10-ago: sus imágenes solo existen en la PC del teatro (`evt_interfaz/imagenes`). Copiarlas al repo y hacer commit (son públicas), para que `bin/iniciar.sh` las cargue a la BD al arrancar.
- Avisar al teatro la hora de la ventana y dejar QZ Tray con `override.crt` en la PC de taquilla.

### El día del despliegue

Orden pensado para que el teatro venda con el sistema nuevo ese mismo día. La taquilla local sigue funcionando hasta el paso 9.

1. **Ventana de mantenimiento:** la taquilla deja de vender en la PC local (lo vendido después del dump se perdería).
2. **Dump de la PC del teatro** de `trt_25`, `trt_historico_evento`, `trt_25_backup` y `trt_25_online` (phpMyAdmin → Exportar → SQL, o `mysqldump --single-transaction --routines --events --default-character-set=utf8mb4`). Guardarlo **fuera** de Git y de la carpeta del proyecto. Copiar también `evt_interfaz/imagenes` si hubo carteles nuevos desde el ensayo.
3. **Ensayo local rápido** con ese dump: restaurar en tu PC, `php sql/aplicar_migraciones.php` y guardar la salida (conteos de referencia).
4. **Quitar `DEFINER` del dump** de `trt_25` (el evento `limpiar_reservas_temporales` viene con `DEFINER=root@localhost`, usuario que no existe en Managed MySQL). Importar por TLS desde una IP autorizada temporalmente en *trusted sources* y quitarla al terminar:
   `mysql --ssl-mode=REQUIRED -h <host> -P 25060 -u <usuario> -p --default-character-set=utf8mb4 trt_25 < trt_25.sql` (igual para las otras 3 bases).
5. **Migraciones de estructura:** desde la consola de App Platform (usa la conexión de la app), `php sql/aplicar_migraciones.php`. Aplica en este orden, es idempotente y al final imprime conteos:

   | # | Archivo | Base |
   |---|---|---|
   | 1 | `sql/migracion_ordenes_online.sql` | `trt_25` |
   | 2 | `sql/migracion_pagos.sql` | `trt_25` |
   | 3 | `sql/migracion_origen_boletos.sql` (usa `orden_items`: va después de 1) | `trt_25` |
   | 4 | `sql/migracion_evento_imagenes.sql` | `trt_25` |
   | 5 | `sql/migracion_orden_notificaciones.sql` (correos enviados; va después de 1) | `trt_25` |
   | 6 | `sql/migracion_origen_boletos_historico.sql` | `trt_historico_evento` |

   `trt_25_backup` y `trt_25_online` no cambian. El código también crea o ajusta estas tablas si faltan (`asegurar*`), pero en producción se aplican explícitamente para no depender de la primera petición. Las tablas online no llevan FK a `evento`/`funciones` porque el archivado borra esas filas. Con cliente `mysql` se pueden aplicar los archivos a mano en ese orden con `--default-character-set=utf8mb4`.
6. **Desplegar** el código (o reiniciar la app si ya estaba) con los secretos configurados. Al arrancar, `bin/iniciar.sh` sube a `evento_imagenes` los carteles que vienen en Git. Confirmar en el log `[imagenes] importadas a BD: N`.
7. **Validar:** la salida de `aplicar_migraciones.php` en producción debe coincidir con la del paso 3 (boletos por evento, última venta, suma de ventas, funciones futuras, usuarios). Confirmar que el evento `limpiar_reservas_temporales` existe (`SHOW EVENTS`).
8. **Pruebas de humo** (lista corta de staging): login, cartelera con carteles, mapa de una función futura, una venta de taquilla de prueba con impresión QZ, escanear su QR y cancelarla.
9. **Apertura a taquilla** en `https://gestion.<dominio>`. Desde aquí la PC local ya no vende: dejar WAMP apagado para evitar ventas en la BD vieja.
10. **Venta online:** apertura gradual, primero en sandbox en producción oculta; **cobros reales solo con aprobación explícita** (ver `PAGOS.md`). Antes, completar los textos legales (sección siguiente).

### Textos legales (antes de abrir la venta online)

Los datos están en `config/legal.php` (públicos, se versionan en Git). Mientras falten, las páginas muestran el nombre del teatro y la ciudad:

- [ ] `LEGAL_RESPONSABLE` y `LEGAL_DOMICILIO`: quién opera el teatro y recibe los pagos. Si es un organismo público (p. ej. el Ayuntamiento), aplica la ley de datos personales para sujetos obligados en lugar de la LFPDPPP: revisar el aviso de privacidad con quien lleve lo jurídico.
- [ ] `LEGAL_EMITE_FACTURA`: `true` (CFDI a solicitud, con plazo `LEGAL_DIAS_SOLICITAR_FACTURA`), `false` (no se factura) o `null` (texto general de contacto).
- [ ] `LEGAL_CORREO`: buzón que realmente se atiende (ARCO, reembolsos, factura).
- [ ] Actualizar `LEGAL_FECHA_ACTUALIZACION` cuando cambien los textos.

Política de reembolsos publicada en `crt_interfaz/terminos.php#reembolsos`: solo si el teatro cancela el evento o lo reprograma y el cliente no acepta la nueva fecha; no por inasistencia o cambio de planes; otros casos a criterio del teatro. Los reembolsos online se hacen desde Administración → Órdenes online (reembolsa por Mercado Pago y cancela los boletos).

No ejecutar en producción `sql/test_*.php` (crean órdenes y boletos) ni `sql/limpiar_datos_prueba.php` (se niega a correr con `APP_ENV=production`).

Rollback: la BD local del teatro queda intacta hasta confirmar producción. Si algo falla en la ventana, se vuelve a taquilla local con el dump previo.

## Entorno de taquilla

La PC de taquilla pasa de `localhost/TeatroConstitucion` a `https://gestion.<dominio>`. `INICIAR_TEATRO.bat` arranca WAMP y abre `localhost`: sirve para desarrollo o instalación local, pero no forma parte del flujo normal con el servidor web.

Reglas de operación que cambian con esta versión:

- **Entrada:** un boleto solo pasa directo si su función empieza en menos de 3 h o empezó hace menos de 4 h (`ENTRADA_MINUTOS_*` en `config/ventas.php`). Fuera de esa ventana el escáner avisa "Boleto de otra función" y solo entra si el operador autoriza; la autorización queda en el log.
- **Cancelar en taquilla:** los boletos comprados en línea no se cancelan desde la taquilla; se cancelan con reembolso en Administración → Órdenes online.
- **Archivar un evento** se bloquea mientras tenga órdenes online con pago en curso, pagadas sin boletos emitidos o pagadas para funciones que aún no terminan.
- **Precios de taquilla:** el servidor los recalcula; si la pantalla quedó con un precio o promoción viejos, la venta se rechaza con "Recarga la página de venta".

## Offline

El diseño inicial no vende boletos offline contra una copia antigua de la BD. Sin conexión, se muestra el estado de sistema no disponible.

Los backups son para recuperación, no para sincronización en tiempo real.
