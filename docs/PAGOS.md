# Pagos online

## Proveedor inicial

Mercado Pago, con **Checkout Bricks (Payment Brick)** como flujo principal. Checkout Pro (redirección) se conserva como alternativa (`MP_CHECKOUT=redirect`).

## Regla de integración

No repartir llamadas del SDK/API de Mercado Pago por todo el proyecto. Todo pasa por el servicio de pago:

```text
includes/pagos/
  PaymentGatewayInterface.php   contrato común (crear preferencia, pago directo, consultar)
  MercadoPagoGateway.php        única clase que habla con api.mercadopago.com
  MockPaymentGateway.php        pruebas locales sin cuenta MP
  PaymentService.php            orquesta gateway + tabla pagos + estado de orden + emisión
```

El resto del sistema trabaja con estados internos `PENDING`, `PAID`, `FAILED`, `REFUNDED` y no depende de nombres del proveedor. Cambiar a Stripe/Conekta = nueva clase que implemente `PaymentGatewayInterface` + ajuste en `payment_resolve_gateway()`.

Mapeo Mercado Pago → interno (`MercadoPagoGateway::mapearEstado`):

| status MP | interno |
|---|---|
| approved | PAID |
| rejected, cancelled, charged_back | FAILED |
| refunded | REFUNDED |
| pending, in_process, authorized, otros | PENDING |

## Flujo Checkout Bricks

```text
checkout_online.php
  1. POST api/online/ordenes.php?action=crear      → orden pendiente (precios calculados en backend)
  2. POST api/online/pagos.php?action=brick_config  → public_key + monto DESDE BD + métodos
  3. SDK MP v2 renderiza Payment Brick (Secure Fields: la tarjeta nunca toca nuestro servidor)
  4. onSubmit → POST api/online/pagos.php?action=procesar {codigo_publico, session_id, form_data}
       backend: payment_procesar_brick()
         - GET_LOCK por orden (serializa doble clic / pestañas)
         - valida sesión dueña de la orden, ventana de venta y holds vigentes
         - rechaza si transaction_amount del navegador ≠ total en BD
         - X-Idempotency-Key determinista → POST /v1/payments (monto SIEMPRE de BD)
         - consulta el pago en MP y valida external_reference, monto y moneda
         - PAID → payment_aplicar_estado → orden pagada → boletos → QR (idempotente)
  5. Navegador → orden.php?codigo=…&espera=1 (solo UX; la orden ya está confirmada o en revisión)

Webhook (api/online/webhook_pagos.php) confirma o corrige el estado de forma asíncrona.
```

Reglas que se cumplen:

- El frontend nunca marca una orden como pagada. `PAID` solo lo aplica el backend tras consultar al proveedor y validar la orden.
- Nunca se confía en precios del navegador: el monto enviado a MP sale de `ordenes.total`.
- La Public Key es la única credencial en el navegador. El Access Token solo existe en el servidor.
- No se almacenan datos de tarjeta (el Brick tokeniza en los servidores de MP).
- Efectivo (OXXO…) desactivado por defecto (`MP_BRICK_TICKET=0`): acredita en días y los holds duran minutos.

## Idempotencia

| Escenario | Protección |
|---|---|
| Doble clic / dos pestañas | `GET_LOCK('teatro_pago_orden_<id>')` + guardia: si ya hay un intento PENDING o PAID no se inicia otro cobro |
| Reintento HTTP del mismo envío | `X-Idempotency-Key` derivada de (orden + token + método + cuotas): MP devuelve el mismo pago |
| Refresh tras pagar | la orden ya está pagada → respuesta idempotente, sin nuevo cobro |
| Webhook duplicado | `payment_aplicar_estado` no cambia nada si el estado ya es el mismo |
| Emisión repetida | `emitir_boletos_orden_pagada` solo emite items sin `id_boleto` |

Cada intento de Brick queda en `pagos` con `ref_externa = 'BRK-<uuid>'` (UNIQUE) y `ref_pago_proveedor` = id del pago en MP. No requirió cambios de esquema.

## Webhook

- URL: `https://<www>/api/online/webhook_pagos.php` (configurar en el panel de MP → Webhooks, evento *Pagos*).
- Autenticación: HMAC SHA-256 de `id:{data.id};request-id:{x-request-id};ts:{ts};` con `MP_WEBHOOK_SECRET`, comparada con `x-signature` (`payment_verificar_firma_mp`).
- Sin secreto en la URL. Sin firma válida → 401.
- `APP_ENV` distinto de `local` exige secreto configurado; producción exige además HTTPS.
- El webhook no confía en el cuerpo: consulta el pago en la API de MP, localiza el intento por `ref_pago_proveedor` o `external_reference`, valida monto/moneda/referencia y solo entonces aplica el estado. Discrepancias → alerta en `pagos.payload_resumen` + `error_log`, sin marcar pagada.
- `?mock=1` solo funciona con mock permitido **y** desde 127.0.0.1 / ::1.

## Configuración (variables de entorno)

| Variable | Dónde | Notas |
|---|---|---|
| `MP_MODE` | todos | `mock` (local), `sandbox` o `live` |
| `MP_ACCESS_TOKEN` | backend, SECRET | credencial de prueba en staging; real solo en producción |
| `MP_PUBLIC_KEY` | backend → navegador | activa Bricks junto con el Access Token |
| `MP_WEBHOOK_SECRET` | backend, SECRET | panel MP → Webhooks → firma secreta |
| `MP_CHECKOUT` | opcional | `redirect` fuerza Checkout Pro |
| `MP_BRICK_TICKET` | opcional | `1` habilita efectivo |
| `MP_MAX_CUOTAS` | opcional | 1 = contado |
| `APP_URL` | todos | base para `notification_url` y back_urls |

Modo mock: solo con `APP_ENV=local` sin token, o con `MP_MODE=mock` explícito fuera de producción. Nunca en producción.

## Pruebas

- `php sql/test_fase3_pagos.php` — flujo histórico (preferencia + webhook mock).
- `php sql/test_fase6_bricks.php` — 18 escenarios del flujo Bricks: aprobado, rechazado, pendiente, reembolso, doble clic, reintento HTTP, webhook duplicado, firma inválida/ausente, monto incorrecto, referencia de otra orden, aprobación con orden expirada, aprobado tras fallo, dos usuarios misma butaca, taquilla vs online (ambos sentidos), emisión duplicada, reembolso cancela boletos; más precio manipulado, sesión ajena y holds perdidos.
- Sandbox real: usar **usuarios de prueba** y tarjetas de prueba de MP (panel de desarrolladores). Nunca la tarjeta personal hasta producción controlada con importe pequeño.

## Activar cobros reales (NO hacer todavía)

1. Cuenta MP verificada del teatro y credenciales de producción.
2. Staging aprobado con sandbox (ver `MIGRACION_PRODUCCION.md`).
3. En App Platform (producción): `MP_MODE=live`, `MP_ACCESS_TOKEN` y `MP_WEBHOOK_SECRET` como SECRET, `MP_PUBLIC_KEY` de producción.
4. Webhook de producción apuntando a `https://www.<dominio>/api/online/webhook_pagos.php`.
5. Compra real de importe mínimo + reembolso desde admin.

## Secretos

Access tokens, llaves privadas y credenciales: no en Git, no en JavaScript público, no en `.md`, no en dumps. Variables de entorno (tipo SECRET en App Platform) o `.env` local ignorado por Git.
