# Sistema de Transacciones en Tiempo Real

## Descripción
Sistema de monitoreo de transacciones de usuarios con actualización automática en tiempo real usando AJAX.

## Características

### ✨ Actualización en Tiempo Real
- **Polling cada 3 segundos:** Consulta automática al servidor para obtener nuevas transacciones
- **Sin recargar la página:** Las nuevas transacciones aparecen automáticamente
- **Animación visual:** Las nuevas transacciones se destacan con una animación al aparecer
- **Indicador de estado:** Muestra si la actualización está activa o pausada

### 🎛️ Controles
- **Botón Pausar/Reanudar:** Permite detener temporalmente la actualización automática
- **Filtros de fecha:** Filtra transacciones por rango de fechas
- **Contador en vivo:** Muestra el número total de transacciones cargadas

### 🔒 Seguridad
- Verificación de sesión en cada petición
- Solo usuarios admin o admin verificados pueden acceder
- Validación de permisos en el endpoint API

## Archivos

### 1. `index.php`
Página principal que muestra las transacciones con:
- Interfaz de usuario mejorada
- Controles de filtrado
- Indicador de estado en tiempo real
- JavaScript para actualización automática
- **NUEVO:** Filas clickeables que abren modal con detalles

### 2. `api_transacciones.php`
Endpoint API que:
- Devuelve transacciones en formato JSON
- Soporta filtros por fecha
- Permite obtener solo transacciones nuevas (por ID)
- Limita resultados a 500 registros

### 3. `api_detalle_transaccion.php` (NUEVO)
Endpoint API que:
- Devuelve detalles completos de una transacción
- Incluye datos JSON adicionales
- Información del usuario que realizó la acción
- Validación de permisos

## Cómo Funciona

### Flujo de Actualización
1. La página carga las transacciones iniciales desde PHP
2. JavaScript inicia un intervalo que consulta el API cada 3 segundos
3. El API devuelve solo las transacciones nuevas (ID mayor al último conocido)
4. Las nuevas transacciones se insertan al inicio de la tabla con animación
5. El contador se actualiza automáticamente

### Optimización
- Solo se consultan transacciones nuevas (no todas)
- Límite de 500 transacciones en pantalla
- Las transacciones antiguas se eliminan automáticamente
- El polling se puede pausar para ahorrar recursos

## Uso

### Ver Transacciones en Tiempo Real
1. Accede a `admin_interfaz/transacciones/index.php`
2. Las transacciones se actualizarán automáticamente cada 3 segundos
3. Las nuevas aparecerán con un fondo azul claro

### Filtrar por Fecha
1. Selecciona fecha "Desde" y/o "Hasta"
2. Haz clic en "Filtrar"
3. La actualización automática continuará con los filtros aplicados

### Pausar Actualización
1. Haz clic en el botón con icono de pausa (⏸)
2. El indicador cambiará a "Actualización pausada"
3. Haz clic nuevamente para reanudar (▶)

### Ver Detalles de una Transacción
1. Haz clic en cualquier fila de transacción
2. Se abrirá un modal con los detalles completos:
   - ID de la transacción
   - Fecha y hora exacta
   - Información del usuario
   - Email del usuario
   - Tipo de acción
   - Descripción
   - Datos adicionales en formato JSON (si existen)
3. Haz clic en "Cerrar" para cerrar el modal

## Personalización

### Cambiar Intervalo de Actualización
Edita la línea en `index.php`:
```javascript
updateInterval = setInterval(cargarNuevasTransacciones, 3000); // 3000 = 3 segundos
```

### Cambiar Límite de Registros
Edita en `api_transacciones.php`:
```php
$sql .= " ORDER BY t.fecha_hora DESC LIMIT 500"; // Cambiar 500 por el límite deseado
```

## Beneficios

✅ Monitoreo en tiempo real de la actividad del sistema
✅ No necesitas recargar la página manualmente
✅ Interfaz moderna y responsiva
✅ Bajo consumo de recursos (solo consulta nuevos registros)
✅ Control total sobre la actualización automática
✅ Filtros flexibles por fecha
✅ **NUEVO:** Detalles completos de cada transacción en modal
✅ **NUEVO:** Filas interactivas con hover effect
✅ **NUEVO:** Visualización de datos JSON adicionales
