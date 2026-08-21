# Arquitectura objetivo

## 1. Fuente única de verdad

La disponibilidad de butacas no debe sincronizarse entre dos bases de datos independientes.

La aplicación web y la aplicación de taquilla deben consultar el mismo backend y la misma BD central.

```text
             Internet
                |
        +-------+--------+
        |                |
      Cliente         Mercado Pago
        |                |
        +-------+--------+
                |
             HTTPS
                |
         Backend / API
                |
          BD central
           /      \
      Taquilla   Admin
```

## 2. Reservas

El proyecto actual ya tiene `reservas_temporales` y `sync/reservas_helper.php`, con `session_id`, `origen`, `expira_en` y protección única por asiento/evento/función.

La primera opción será reutilizar esta estructura para el flujo online y local. No crear `ordenes_online_detalle` ni otras tablas duplicadas hasta demostrar que las tablas actuales no pueden cubrir el flujo requerido.

## 3. Venta online

Flujo previsto:

```text
Cartelera
  -> función
  -> mapa de butacas
  -> reservar temporalmente
  -> crear orden/pago
  -> checkout Mercado Pago
  -> webhook
  -> validar pago
  -> confirmar venta
  -> emitir boleto
  -> QR
```

## 4. Venta local

El flujo actual de taquilla deberá seguir funcionando. Cualquier refactor del procesamiento de venta debe hacerse detrás de una capa común, por ejemplo un servicio de emisión de venta/boleto, sin eliminar primero el flujo actual.

## 5. Internet del teatro

Si el teatro pierde Internet, la taquilla no debe seguir vendiendo contra un estado local obsoleto. El comportamiento inicial será mostrar estado de sistema no disponible y reanudar cuando vuelva la conexión.

Backups y una conexión de respaldo 4G/5G ayudan a la continuidad, pero no convierten el sistema en offline-first.
