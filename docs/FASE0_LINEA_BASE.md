# Fase 0 — línea base (venta online)

Completado en rama `feature/venta-online`:

- `.gitignore` en raíz (`.env`, vendor, logs, QR runtime, dumps).
- `.env.example` con variables de BD y placeholders de Mercado Pago.
- `config/env.php` carga opcional de `.env`.
- `config/database.php` lee `DB_HOST` / `DB_USER` / `DB_PASS` / `DB_NAME` con fallback WAMP.
- Conexiones de `sync/reservas_helper.php`, `sync/backup_helper.php` y `vnt_interfaz/conexion_api.php` alineadas a esa config.
- Verificado: conexión a `trt_25` OK; tablas `boletos`, `reservas_temporales`, `evento`, `funciones`, `asientos` presentes.

Siguiente: Fase 1 (mapa unificado + hold online bloquea taquilla).
