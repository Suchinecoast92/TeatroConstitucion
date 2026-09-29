# Dominios de producción

Una sola app (un backend, una BD central) atendida bajo tres subdominios. Ejemplo con `teatro.mx` (sustituir por el real):

| Subdominio | Uso | Quién |
|---|---|---|
| `www.teatro.mx` | cartelera, compra online, página de orden, API pública (`/api/online/`), webhook MP | público |
| `gestion.teatro.mx` | login único → panel según rol (admin, taquilla/empleado, control de entrada) | personal |
| `api.teatro.mx` | solo `/api/` (webhooks e integraciones futuras) | máquinas |

**La seguridad NO depende del subdominio.** Cada página de gestión sigue exigiendo sesión, rol y validación en backend (`usuario_id`, `usuario_rol`, CSRF). La separación por dominio es defensa en profundidad y orden para el usuario: ocultar una URL no protege nada.

## Cómo se aplica

Dos capas complementarias:

1. `.htaccess` (mod_rewrite), por **prefijo** de host, sin escribir el dominio real:
   - `api.*` → 404 fuera de `/api/`.
   - `www.*` raíz → `/crt_interfaz/`.
   - `www.*` fuera de las rutas públicas → 302 a `gestion.*` con la misma ruta y query.
   - Rutas públicas en `www`: `/crt_interfaz/`, `/api/online/`, `/assets/`, `/evt_interfaz/imagenes/`, `/boletos_qr/`, `/favicon.ico`, `/robots.txt`, `/health.php`.
   - `localhost` e IPs de LAN no se ven afectados (desarrollo y taquilla local siguen igual).
2. `config/runtime.php` → `teatro_host_guard()`, con dominios exactos (`HOST_WWW`, `HOST_GESTION`, `HOST_API`). Sin esas variables no hace nada.

`HOST_REDIRECT_UNKNOWN=1` (opcional) redirige hosts desconocidos, como `*.ondigitalocean.app`, al dominio oficial. Activarlo solo en el esquema recomendado (dominios dados de alta en App Platform), nunca con un proxy que reescriba `Host` sin `X-Forwarded-Host`.

## Sesiones y cookies

- Cookie de sesión sin atributo `Domain` → cada subdominio tiene su propia sesión. El login solo existe en `gestion`; `www` no comparte sesión con el personal.
- `HttpOnly`, `SameSite=Lax`, `use_strict_mode` (`.user.ini` + `config/runtime.php`); `Secure` cuando la petición es HTTPS.
- Algunos archivos antiguos llaman a `session_start()` antes de cargar la configuración: en ellos `Secure` no se aplica. Riesgo acotado porque el sitio solo se sirve por HTTPS (redirección + HSTS en el borde); corregir al tocar esos archivos.

## CORS

No se necesita: la web llama a `/api/online/` en su propio origen (`www`). Los webhooks son servidor a servidor. `vnt_interfaz/sign_message.php` ya no envía `Access-Control-Allow-Origin: *`.

## Cloudflare: esquema recomendado

App Platform ya pasa todo el tráfico por su propia CDN de Cloudflare y gestiona los certificados TLS.

**Recomendado:** dar de alta los tres dominios en App Platform (Settings → Domains). En el DNS (Cloudflare u otro), crear un CNAME por subdominio hacia `<app>.ondigitalocean.app` **sin proxy** (nube gris), como indique el asistente de App Platform.

- La app recibe el `Host` real, y el control por dominio y las cookies funcionan.
- La IP real llega en `CF-Connecting-IP` / `X-Forwarded-For` y HTTPS en `X-Forwarded-Proto`, con `TRUST_PROXY=1`.

**Alternativa (Cloudflare propio con proxy, nube naranja).** Según la documentación de DigitalOcean (*Configure an external CDN*):

- No agregar el dominio en App Platform.
- Origin Rule hacia `<app>.ondigitalocean.app` **sin** reenviar el `Host` original.
- TLS Full (strict).

Como la app vería siempre `*.ondigitalocean.app`, habría que agregar una Transform Rule que envíe `X-Forwarded-Host: {http.host}`. `teatro_host_guard()` la usa si `TRUST_PROXY=1`. Más piezas que mantener; usar solo si se necesita WAF/reglas propias de Cloudflare.

## Taquilla

Las PCs de taquilla pasan de `http://localhost/TeatroConstitucion` a `https://gestion.teatro.mx`. QZ Tray sigue corriendo en cada PC; la firma de QZ la hace el servidor (`sign_message.php`, solo con sesión). Los `.bat` quedan para instalación local o desarrollo.
