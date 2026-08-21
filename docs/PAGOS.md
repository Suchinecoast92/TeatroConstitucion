# Pagos online

## Proveedor inicial

Mercado Pago.

## Regla de integración

No repartir llamadas del SDK/API de Mercado Pago por todo el proyecto. Encapsularlas en un servicio de pago.

Ejemplo conceptual:

```text
PaymentService
  -> crear pago
  -> consultar pago
  -> procesar webhook
  -> reembolsar
```

El resto del sistema debe trabajar con estados internos como `PENDING`, `PAID`, `FAILED`, `REFUNDED` y no depender de nombres concretos del proveedor.

## Webhook

La confirmación real debe entrar al backend mediante webhook/notificación y ser validada. La pantalla de retorno del cliente es solo UX.

## Idempotencia

El webhook puede llegar más de una vez. Procesar el mismo pago dos veces no debe emitir dos boletos ni registrar dos ventas.

La orden/pago debe tener referencias externas únicas.

## Tarjetas

Durante desarrollo usar sandbox/tarjetas de prueba. Las pruebas reales con la tarjeta personal solo deben ocurrir cuando el sistema esté en producción controlada y con un importe pequeño.

## Secretos

Access tokens, llaves privadas y credenciales:

- no en Git
- no en JavaScript público
- no en `.md`
- no en `trt_25.sql`

Usar variables de entorno o gestor de secretos.
