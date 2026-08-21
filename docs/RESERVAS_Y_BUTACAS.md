# Reservas y disponibilidad de butacas

## Recurso existente

El proyecto ya tiene `reservas_temporales` y `sync/reservas_helper.php`.

El helper actual ya contempla:

- expiración
- sesión
- origen local/online
- conflicto con taquilla
- liberación por sesión
- consulta de reservas activas

## Objetivo

Convertir este mecanismo en la pieza central de disponibilidad temporal.

## Disponibilidad final

Para una función, una butaca puede estar:

- disponible
- reservada temporalmente
- vendida
- bloqueada por administración
- usada/cancelada según el flujo del sistema

## Regla crítica

No confiar solo en una consulta previa del tipo `SELECT disponibilidad` seguida de `INSERT` sin control de concurrencia.

La reserva debe ser atómica respecto al asiento/evento/función.

## Interacción taquilla/online

- Reserva local activa debe bloquear compra online.
- Reserva online activa debe bloquear venta de taquilla.
- Venta confirmada debe bloquear nuevas reservas.
- Reserva expirada debe quedar liberada.

## Funciones existentes a revisar

- `sync/reservas_helper.php`
- `api/reservas.php`
- `vnt_interfaz/obtener_asientos_vendidos.php`
- `vnt_interfaz/procesar_compra.php`

## Importante

`obtener_asientos_vendidos.php` actualmente consulta boletos activos, pero no constituye por sí solo el estado completo de disponibilidad porque también existen reservas temporales. El mapa final debe contemplar ambas fuentes y los bloqueos administrativos.
