# Migración de local a producción web

## Etapa 1: desarrollo

Origen:

- copia del código real
- copia de BD real para pruebas

Destino:

- BD de desarrollo
- datos anonimizados cuando sea necesario

No realizar ventas reales.

## Etapa 2: staging

Desplegar el proyecto en un entorno de pruebas parecido a producción.

Probar:

- login
- cartelera
- funciones
- butacas
- reserva temporal
- venta local simulada
- compra online sandbox
- webhook
- emisión de boleto
- QR
- cancelación
- devolución
- concurrencia

## Etapa 3: producción

1. Backup completo de la BD real.
2. Ventana de mantenimiento.
3. Migraciones de esquema.
4. Despliegue de código.
5. Configuración de secretos.
6. Restauración/validación de datos.
7. Pruebas de humo.
8. Apertura a taquilla.
9. Apertura gradual de venta online.

## Entorno de taquilla

La PC de taquilla puede pasar de `localhost/teatro` a una URL HTTPS del sistema central.

El `.bat` `INICIAR_TEATRO.bat` actualmente arranca WAMP y abre `localhost`; sirve para desarrollo/instalación local, pero no será parte del flujo normal cuando el teatro use el servidor web.

## Offline

El diseño inicial no venderá boletos offline contra una copia antigua de la BD. Si no hay conexión, mostrar estado de sistema no disponible.

Los backups son para recuperación, no para sincronización en tiempo real.
