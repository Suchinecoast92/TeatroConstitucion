# Fase 4 — Emisión de boletos / QR

## Completado

- `includes/emision_helper.php`: `emitir_boletos_orden_pagada()` idempotente
- Al marcar pago `PAID` (webhook / mock) se insertan filas en `boletos` (misma tabla que taquilla)
- `codigo_unico` de 16 hex + PNG en `boletos_qr/`
- `orden_items.id_boleto` enlaza el boleto
- Se liberan holds de la sesión tras emitir
- Notifica `registrar_cambio('venta')` para refresco de mapas
- `crt_interfaz/orden.php` muestra QR y códigos (y reintenta emisión si faltaba)
- Webhook repetido no duplica boletos
- `SELECT … FOR UPDATE` al emitir; no reclama boletos ajenos activos

## Prueba

```bash
php sql/test_fase4_emision.php
```

## Revisión (antes de Fase 5)

### OK

- Misma tabla / `codigo_unico` / QR que taquilla → buscador y entrada compatibles
- Idempotencia de webhook + `id_boleto` en items
- Transacción en emisión; rollback si falla un asiento del lote
- Índice único `(id_evento, id_funcion, id_asiento)` evita doble venta a nivel BD
- `orden.php` puede completar emisión si el webhook falló a medias

### Corregido en revisión

- Ya no se “asocia” un boleto activo de taquilla/otra orden al item online
- Tras emitir se notifica cambio en tiempo real (antes el mapa de taquilla podía no enterarse)

### Pendiente / aceptado para Fase 5+

- Si el hold expiró y taquilla vendió el asiento **después** del cobro, la orden queda `pagada` sin boleto → hace falta alerta + reembolso (Fase 5)
- No se registra aún `registrar_transaccion` / `registrarVentaDetallada` / respaldo backup como en POS
- No hay envío de correo con QR
- `orden.php` es pública por `codigo_publico` (secreto práctico, no login)
- Emisión fallida responde 200 al webhook (MP no reintenta por eso); recuperación = reintento idempotente + abrir `orden.php`
