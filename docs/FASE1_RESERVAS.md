# Fase 1 — Reservas y mapa unificado

## Cambios

- `sync/reservas_helper.php`: la taquilla ya **no** puede quitar holds `online`. Conflicto = `online` / `taquilla` / `reservado` / `vendido`.
- `verificarVentaAtomica`: ya no borra apartados online al vender en taquilla.
- Nuevas funciones: `obtenerDisponibilidadFuncion()`, `renovarReservasSesion()`.
- API pública:
  - `api/online/disponibilidad.php`
  - `api/online/reservas.php` (`origen=online`, rate limit, sin CORS abierto)
- Taquilla: `obtener_asientos_vendidos.php` y `carrito.js` usan vendidos + reservados.
- Cartelera: `crt_interfaz/disponibles.php` muestra asientos apartados.
- Prueba: `php sql/test_fase1_holds.php`

## Siguiente

Fase 2 — órdenes + recálculo de precios en backend.
