# Fase 3 — Pagos (Mercado Pago / mock)

## Completado

- Tabla `pagos` + `includes/pagos/` (`PaymentService`, `MercadoPagoGateway`, `MockPaymentGateway`)
- API: `api/online/pagos.php` (`iniciar`, `simular`, `estado`)
- Webhook: `api/online/webhook_pagos.php` (idempotente)
- Return UX: `crt_interfaz/pago_retorno.php` (no confirma el pago por sí sola)
- Checkout crea orden → inicia pago → redirige a `init_point`

## Modo local (sin token)

Sin `MP_ACCESS_TOKEN` se usa **mock**: el “checkout” vuelve a `pago_retorno` y marca la orden como `pagada`.

## Mercado Pago real (sandbox)

1. Copia `.env.example` → `.env`
2. Pon `MP_ACCESS_TOKEN` de prueba
3. Pon `APP_URL` público (ngrok si quieres webhook real)
4. `MP_MODE=live` (o deja el token y no uses mock)

## Prueba

```bash
php sql/test_fase3_pagos.php
```

## Pendiente Fase 6

Staging / producción (DigitalOcean, Cloudflare, backups).

Fase 5 (admin) está en `docs/FASE5_ADMIN.md`.
