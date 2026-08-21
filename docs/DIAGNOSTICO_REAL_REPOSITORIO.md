# Diagnóstico real del repositorio y BD

## Repositorio

Repositorio analizado: `Suchinecoast92/TeatroConstitucion`.

El árbol actual incluye, entre otros:

- `admin_interfaz/`
- `api/`
- `auth/`
- `boletos_qr/`
- `config/`
- `control_entrada/`
- `crt_interfaz/`
- `evt_interfaz/`
- `includes/`
- `mp_interfaz/`
- `resources/`
- `sync/`
- `ventas/`
- `vnt_interfaz/`
- scripts de diagnóstico
- documentación `.md`
- `INICIAR_TEATRO.bat`
- `INSTALADOR.bat`

## Hallazgos importantes

### 1. `conexion.php`

Actualmente el repositorio declara explícitamente un modo standalone/local y obtiene la conexión con `getPrimaryConnection()`. La arquitectura final web deberá cambiar la configuración, no necesariamente todo el código de consumo de BD.

### 2. `INICIAR_TEATRO.bat`

Actualmente inicia `c:\wamp64\wampmanager.exe` y abre `http://localhost/teatro` y el control de entrada. Debe considerarse herramienta local de desarrollo/instalación, no componente de producción web.

### 3. `vnt_interfaz/procesar_compra.php`

Es un archivo grande (494 líneas según GitHub) y concentra bastante lógica de venta y emisión. Debe analizarse antes de refactorizar. No reemplazarlo de golpe.

### 4. `vnt_interfaz/obtener_asientos_vendidos.php`

Actualmente consulta boletos activos (`estatus = 1`) y filtra por evento/función. Debe complementarse con `reservas_temporales` para que el mapa represente disponibilidad en tiempo real.

### 5. `sync/reservas_helper.php`

Ya contempla reservas, expiraciones, origen local/online, conflictos y liberación. Es uno de los componentes más valiosos para la nueva venta online.

### 6. `api/reservas.php`

Ya existe una API específica para reservas. Debe evaluarse como base del flujo público, reforzando autenticación/anti-abuso donde corresponda.

### 7. `sync/backup_helper.php`

Ya registra ventas detalladas en el sistema de respaldo con origen (`local`/`online`), método de pago, referencias y metadatos. Esto debe revisarse antes de crear una segunda tabla de auditoría financiera.

### 8. `trt_25_online.sql`

Esquema alternativo con IDs locales/online y `cambios_pendientes`. No mezclar con la BD de producción sin rastrear el código que lo usa.

## Prioridad de análisis posterior

1. Modelo exacto de `ventas` y su uso en PHP.
2. Flujo completo de `reservas_temporales`.
3. Flujo de `api/reservas.php`.
4. Todos los consumidores de `procesar_compra.php`.
5. Flujo de `sync/backup_helper.php`.
6. Contratos de API ya existentes.
7. Autenticación/autorización.
8. Inventario de secretos y archivos que no deben publicarse.
