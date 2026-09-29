# Despliegue en DigitalOcean (App Platform + Managed MySQL)

Estado: **preparado, no desplegado**. No hay credenciales, dominio ni infraestructura creados todavía. Nada de esto activa cobros reales.

## Topología

```text
Clientes / Taquilla / Admin ──HTTPS──> App Platform (CDN Cloudflare integrada, TLS gestionado)
                                         └─ servicio "web": PHP 8.3 + Apache (buildpack Heroku PHP)
                                                 │  red privada (VPC), TLS
                                                 ▼
                                   Managed MySQL 8.x (sin acceso público)
                                     trt_25 · trt_25_backup · trt_historico_evento
Mercado Pago ──webhook HTTPS──> www/api …/api/online/webhook_pagos.php
```

Sin microservicios, Redis ni Kubernetes. Una app, una BD central.

## Qué se preparó en el código

| Tema | Archivo | Detalle |
|---|---|---|
| Build | `composer.json` + `composer.lock` (raíz) | PHP `~8.3.0`, extensiones detectadas del código, mismas dependencias que `vnt_interfaz/composer.json`, instaladas en `vnt_interfaz/vendor` (`config.vendor-dir`), así que ningún `require` cambia |
| Extensiones | `composer.json` | ctype, curl, fileinfo, gd, iconv, intl, json, mbstring, mysqli, openssl, zip, zlib |
| MySQL | `config/database.php` | `DB_PORT`, TLS (`DB_SSL`, `DB_SSL_CA` / `DB_SSL_CA_CERT`), timeout antes de conectar, zona horaria por conexión, `DB_SQL_MODE` opcional, `teatro_db_connect()` común |
| Proxy/HTTPS | `config/runtime.php` | `TRUST_PROXY=1`: HTTPS desde `X-Forwarded-Proto`, IP real desde `CF-Connecting-IP`/`X-Forwarded-For` |
| Errores | `config/runtime.php` | `APP_ENV=production`: sin errores en pantalla, excepciones → mensaje genérico + `error_log` |
| Cookies | `.user.ini` + `runtime.php` | HttpOnly, SameSite=Lax, strict mode; Secure en HTTPS |
| Dominios | `.htaccess` + `runtime.php` | ver `DOMINIOS_PRODUCCION.md` |
| QR | `includes/qr_helper.php`, `crt_interfaz/qr_boleto.php` | regeneración bajo demanda (disco efímero) |
| Health check | `health.php` | responde `ok` sin tocar la BD |
| Plantilla | `.do/app.yaml` | App Spec sin secretos |

Web server: `heroku-php-apache2` (Apache + PHP-FPM) con document root en la raíz del repo, así que `.htaccess` sigue aplicando.

## Riesgos detectados y resueltos

1. **Zona horaria.** Managed MySQL corre en UTC. Holds, órdenes y cierre de venta comparan `NOW()` contra horarios guardados en hora de México: habría un desfase de 6 h. `teatro_db_alinear_zona()` fija `time_zone` de cada conexión al offset de `APP_TIMEZONE`.
2. **sql_mode.** El WAMP local tiene `sql_mode` vacío; Managed MySQL usa `ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,…`. La suite online completa pasa con ese modo (probado con `DB_SQL_MODE`). Falta revisar en staging taquilla, admin y reportes; si algo falla, la salida temporal es `DB_SQL_MODE=` (vacío) mientras se corrige.
3. **Mayúsculas en nombres de tabla.** Windows usa `lower_case_table_names=1` y Linux usa 0. Revisado: el código usa nombres en minúsculas.
4. **Llave primaria obligatoria** (`sql_require_primary_key`). Las 28 tablas de las 3 BD ya tienen PK y son InnoDB.
5. **Credenciales en código.** `ventas/panel_admin.php` usaba `root` sin contraseña; ahora usa la conexión común. `sync/backup_helper.php` también.
6. **Scripts de mantenimiento públicos.** `fix_schema`, `fix_funciones`, `fix_boletos_index`, `inspect_db`, `sync_historico_schema`, `evt_interfaz/fix_historico`, `setup_historico`: ahora solo CLI y bloqueados en `.htaccess`.
7. **Llave privada de QZ Tray** descargable por HTTP y con oráculo de firma público. Ahora `.key` bloqueado, `sign_message.php` exige sesión y la llave puede venir de `QZ_PRIVATE_KEY` (SECRET). **Pendiente del dueño:** sacarla de Git y rotarla (ver abajo).

## Persistencia de archivos (disco efímero)

App Platform pierde los archivos escritos en disco en cada despliegue o reinicio.

| Archivo | ¿Regenerable? | Solución |
|---|---|---|
| `boletos_qr/*.png` | Sí (solo codifica `codigo_unico` de la BD) | Resuelto: `teatro_qr_asegurado()` lo recrea al pedir PDF, imagen o página de orden |
| PDF de boletos | Sí (se generan al vuelo) | Sin cambios |
| **`evt_interfaz/imagenes/`** (carteles subidos desde admin) | **No** | **Necesita persistencia. Decisión pendiente** |
| Sesiones PHP, rate limit (`/tmp`) | No es necesario | 1 instancia; un despliegue cierra sesiones del personal |

Queda demostrado que hace falta almacenamiento persistente para las imágenes de eventos. Opciones:

- **A. DigitalOcean Spaces** (S3, unos 5 USD/mes). Subida desde admin a Spaces y URL pública o CDN en la BD. Es lo estándar; agrega credenciales (SECRET) y una dependencia (SDK S3 o firma v4 con curl).
- **B. Imagen en la BD** (tabla `evento_imagenes` con BLOB, servida por un PHP con caché). Sin infraestructura nueva y entra en los backups de la BD; tiene sentido porque los carteles son pocos y pequeños.

Mientras no se decida: las imágenes que ya están en Git se despliegan con el código; las **nuevas** subidas en producción desaparecerían en el siguiente despliegue.

## Pasos (cuando existan cuenta, dominio y credenciales)

### 1. Base de datos (Managed MySQL)

1. Crear el cluster MySQL (misma región que la app; plan básico de 1 nodo para empezar). Anotar la versión 8.x ofrecida.
2. **Trusted sources:** solo la app. Nunca `0.0.0.0/0`. Para importar, agregar temporalmente la IP del administrador y quitarla al terminar.
3. Crear las bases `trt_25`, `trt_25_backup`, `trt_historico_evento` y un usuario de app (no usar `doadmin` en la app). Dar al usuario privilegios sobre las 3 bases; el código consulta entre bases (histórico/respaldo).
4. Importar **solo esquema + datos necesarios** con `mysqldump --single-transaction --routines --set-gtid-purged=OFF` desde el origen, por TLS. Nunca subir el dump a Git.
5. Configurar backups diarios (incluidos) y confirmar la retención de point-in-time recovery.

### 2. App

1. Conectar el repo (rama de despliegue), crear la app desde `.do/app.yaml` (ajustado) o desde el panel con los mismos valores.
2. Variables SECRET en el panel: `DB_PASS` (vinculada), `MP_ACCESS_TOKEN`, `MP_WEBHOOK_SECRET`, `QZ_PRIVATE_KEY`.
3. `instance_count: 1` (ver persistencia).
4. Primer despliegue; comprobar logs de build: extensiones instaladas y `composer install` en `vnt_interfaz/vendor`.
5. `https://<app>.ondigitalocean.app/health.php` debe responder `ok`.

### 3. Dominios

Ver `DOMINIOS_PRODUCCION.md`. Recomendado: agregar `www`, `gestion` y `api` en App Platform con DNS sin proxy.

### 4. Mercado Pago (sandbox primero)

Ver `PAGOS.md`. En staging: credenciales de prueba y webhook de prueba apuntando a staging.

## Sacar la llave de QZ de Git (acción del dueño)

La llave `vnt_interfaz/utils/qz_private.key` está versionada, así que cualquiera con acceso al repo la tiene.

1. Generar un par nuevo de llave y certificado de QZ Tray, e instalar el certificado nuevo en las PCs de taquilla.
2. En producción, poner la llave nueva en `QZ_PRIVATE_KEY` (SECRET). En local, guardarla en `QZ_PRIVATE_KEY_PATH` fuera del repo.
3. `git rm --cached vnt_interfaz/utils/qz_private.key` (el archivo local se conserva; `.gitignore` ya excluye `*.key`).
4. El historial de Git conserva la llave vieja; por eso hay que rotarla, no basta con borrarla.

Igual con `boletos_qr/*.png` (1064 imágenes QR rastreadas desde el primer commit): `git rm -r --cached boletos_qr` y conservar `.gitkeep`. Son regenerables y no deben estar en el repo.

## Lo que NO se hizo (a propósito)

- No se creó infraestructura, no hay credenciales reales y no se activaron cobros.
- No se migró la BD.
- No se agregó Spaces (pendiente de decisión).
- Los `.bat` quedan como herramientas locales (bloqueados por HTTP).
