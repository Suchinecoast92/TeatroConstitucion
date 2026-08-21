# Fase 2 — Órdenes y precios

## Completado

- Tablas `ordenes` / `orden_items` (migración + auto-create).
- `includes/precio_helper.php`: cotización solo desde BD (ignora precios del cliente).
- `includes/ordenes_helper.php`: crear orden pendiente, expirar, renovar hold 5 min.
- API: `api/online/ordenes.php` (`cotizar`, `crear`, `obtener`, `limpiar`).
- UI: `crt_interfaz/comprar.php` + `orden.php`; enlace en cartelera.
- Hold online confirmado en servidor antes de agregar al carrito.
- Sin cobro todavía (Fase 3 = Mercado Pago).

## Prueba

```bash
php sql/test_fase2_ordenes.php
```

## Siguiente

Fase 3 — PaymentService + Mercado Pago sandbox + webhook.
