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

Flujo implementado (Checkout Bricks; detalle en `PAGOS.md`):

```text
Cartelera
  -> función
  -> mapa de butacas
  -> reservar temporalmente (reservas_temporales)
  -> crear orden (precio calculado en backend)
  -> Payment Brick (tarjeta tokenizada por Mercado Pago)
  -> backend crea el pago con X-Idempotency-Key y valida monto/moneda/referencia
  -> webhook firmado confirma o corrige
  -> PAID -> emitir boleto -> QR
```

El pago se integra solo a través de `includes/pagos/` (interfaz + gateway + servicio), para poder cambiar de proveedor.

## 4. Venta local

El flujo actual de taquilla deberá seguir funcionando. Cualquier refactor del procesamiento de venta debe hacerse detrás de una capa común, por ejemplo un servicio de emisión de venta/boleto, sin eliminar primero el flujo actual.

## 5. Internet del teatro

Si el teatro pierde Internet, la taquilla no debe seguir vendiendo contra un estado local obsoleto. El comportamiento inicial será mostrar estado de sistema no disponible y reanudar cuando vuelva la conexión.

Backups y una conexión de respaldo 4G/5G ayudan a la continuidad, pero no convierten el sistema en offline-first.

## 6. Producción (DigitalOcean)

```text
www.<dominio>      público: cartelera, compra, /api/online/, webhook
gestion.<dominio>  personal: login único → panel por rol
api.<dominio>      solo /api/
        │ (todos)
        ▼
App Platform: 1 servicio PHP 8.3 + Apache (CDN y TLS gestionados)
        │ red privada + TLS
        ▼
Managed MySQL 8.x (trusted sources = la app; sin acceso público)
```

- Una sola app y una sola BD central: la taquilla, el admin y la web usan el mismo backend.
- Los subdominios ordenan el acceso, pero la seguridad es login + rol + validación backend (`DOMINIOS_PRODUCCION.md`).
- Disco efímero: los QR se regeneran; las imágenes de eventos necesitan persistencia (decisión pendiente, `DEPLOY_DIGITALOCEAN.md`).
- Configuración por variables de entorno (`.env.example`); secretos como SECRET en App Platform.
- Sin microservicios, Redis ni Kubernetes.
