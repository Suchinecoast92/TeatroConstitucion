# Diseño de venta online

## Flujo funcional

1. Cliente entra a la cartelera.
2. Selecciona una función.
3. Consulta butacas vendidas y reservadas.
4. Selecciona butacas.
5. El backend crea reserva temporal.
6. El backend calcula precios y promociones desde la BD.
7. Se crea orden/operación de pago.
8. El cliente realiza el pago en la pasarela.
9. El proveedor notifica al backend por webhook.
10. El backend verifica el pago.
11. Se confirma la venta.
12. Se emiten boletos usando el mecanismo existente.
13. Se genera/entrega el QR.

## Estados mínimos

Reserva:

- activa
- expirada
- liberada
- convertida en venta

Orden/pago:

- pendiente
- pagado/aprobado
- rechazado
- cancelado
- reembolsado

Boleto existente:

Mantener la semántica actual mientras sea compatible con el sistema de acceso. No mezclar estado de reserva con estado de boleto.

## Concurrencia

La BD es la autoridad final. Una butaca no puede ser adquirida por dos sesiones.

El frontend nunca debe considerarse garantía de disponibilidad.

## Precios

La petición pública debe contener identificadores de función/asiento/categoría y tipo de boleto, no un precio confiable.

El backend debe recalcular:

- precio base
- promoción
- descuento
- precio final
- total

## Reembolsos

El estado de la venta y el estado financiero deben quedar auditados. No invalidar un boleto únicamente porque el frontend muestre una devolución; la decisión debe venir del backend.
