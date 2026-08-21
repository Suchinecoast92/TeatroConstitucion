# Plan de implementación de venta online

## Fase 0 - Seguridad y línea base

- Crear rama `feature/venta-online`.
- Congelar una copia de la versión local funcional.
- Crear BD de desarrollo.
- Verificar que taquilla actual sigue funcionando.
- Revisar secretos del repositorio.

## Fase 1 - Reservas

- Reutilizar `reservas_temporales`.
- Endurecer concurrencia.
- Asegurar expiración.
- Unificar estado de disponibilidad del mapa.
- Probar local vs online simultáneo.

## Fase 2 - Orden

- Decidir si `ventas` representa la orden online o si se agrega una entidad de orden.
- No duplicar información innecesariamente.
- Registrar artículos/asientos y total calculado por backend.

## Fase 3 - Pago sandbox

- Encapsular Mercado Pago.
- Crear checkout.
- Crear webhook.
- Implementar idempotencia.
- Simular aprobados/rechazados/pendientes.

## Fase 4 - Emisión

- Convertir pago aprobado en venta confirmada.
- Reutilizar emisión de boletos y QR existente.
- Registrar auditoría.

## Fase 5 - Administración

- Mostrar origen local/online.
- Consultar pagos y estados.
- Integrar cancelación y reembolso.

## Fase 6 - Producción

- DigitalOcean.
- Cloudflare.
- MySQL administrado.
- Backups.
- Monitoreo.
- Dominio/HTTPS.
- Migración controlada.

## Criterio de aceptación

La venta online solo se considera lista cuando:

- No existe doble venta de butaca.
- Taquilla y web reflejan el mismo inventario.
- Una reserva expirada vuelve a estar disponible.
- Un webhook repetido no duplica el boleto.
- Un pago rechazado no genera venta.
- Un pago aprobado genera exactamente los boletos correctos.
- El sistema de entrada valida los boletos online igual que los locales.
- Restaurar un backup funciona de verdad.
