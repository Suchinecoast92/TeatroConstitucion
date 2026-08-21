# Base de datos: diagnóstico y estrategia

## BD de referencia

Archivo analizado: `trt_25 (3).sql`.

Motor/entorno del dump: MySQL 8.4.7, generado 10-08-2026.

## Tablas clave observadas

- `evento`
- `funciones`
- `asientos`
- `categorias`
- `precios_tipo_boleto`
- `promociones`
- `boletos`
- `ventas`
- `transacciones`
- `cambios_log`
- `reservas_temporales`
- `conexion_estado`
- `configuracion`
- `usuarios`

## `boletos`

`boletos` ya contiene `id_evento`, `id_funcion`, `id_asiento`, categoría, promoción, precio base, descuento, precio final, tipo de boleto, usuario, fecha de compra y estado.

Existe una clave única sobre `(id_evento, id_funcion, id_asiento)`, que es una protección importante contra duplicidad de asiento por función.

No convertir `boletos` en una tabla de reservas temporales.

## `reservas_temporales`

Ya existe con:

- `codigo_asiento`
- `id_evento`
- `id_funcion`
- `origen` (`local` / `online`)
- `session_id`
- `cliente_info`
- `fecha_reserva`
- `expira_en`

Existe una clave única por `(codigo_asiento, id_evento, id_funcion)`.

La lógica del repositorio ya incluye:

- limpieza de reservas expiradas
- creación de reservas
- detección de conflictos
- liberación por sesión
- listado de reservas activas
- diferenciación de origen local/online

Esto es una señal fuerte de que debemos ampliar/reutilizar el mecanismo existente, no duplicarlo.

## `ventas`

La BD actual ya tiene una tabla `ventas` con al menos:

- `id_venta`
- `id_usuario`
- `fecha_venta`
- `total`
- `pagado`
- `metodo_pago`

Antes de introducir una tabla `ordenes_online`, estudiar los usos actuales de `ventas` y decidir si se amplía para representar la orden comercial o si se agrega una entidad de orden separada.

## `transacciones`

Debe mantenerse como auditoría/registro operativo. No usarla como reemplazo de una entidad financiera de pagos.

## `cambios_log`

Ya contempla tipos como `reserva` y `liberacion`, por lo que puede servir para auditoría de sincronización y cambios de disponibilidad.

## `conexion_estado`

Ya existe para registrar estados de componentes como online/local/backup. Debe reutilizarse para health checks y monitoreo local si su diseño actual cumple el objetivo.

## `trt_25_online.sql`

Este archivo NO debe asumirse automáticamente como esquema de producción. Es un esquema alternativo que contiene columnas de correspondencia local/online (`id_evento_local`, `id_funcion_local`, etc.) y tablas adicionales como `cambios_pendientes`. Debe tratarse como referencia histórica/prototipo hasta rastrear qué código depende de él.

No fusionar ambos esquemas a ciegas.

## Regla de migración

Antes de cambiar una tabla existente:

1. Identificar todos sus usos en PHP/JS.
2. Revisar datos actuales.
3. Crear migración reversible.
4. Probar contra una copia de la BD real.
5. Verificar integridad y duplicados.
6. Solo después aplicar en staging/producción.
