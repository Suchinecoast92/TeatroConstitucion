# Sistema de Actualización en Tiempo Real

## Descripción
Sistema que actualiza automáticamente el selector de eventos en la interfaz de venta cuando se crean o editan eventos desde otras pestañas o ventanas.

## Archivos Modificados/Creados

### 1. `vnt_interfaz/obtener_eventos.php` (NUEVO)
- Endpoint API que devuelve la lista de eventos activos en formato JSON
- Usado para actualizar el selector sin recargar la página completa

### 2. `vnt_interfaz/js/evento-sync.js` (NUEVO)
- Script JavaScript que maneja la sincronización en tiempo real
- Escucha cambios en localStorage y mensajes entre ventanas
- Actualiza el selector automáticamente cada 30 segundos
- Recarga la página si se edita el evento que se está visualizando
- **NUEVO:** También detecta cambios en categorías y descuentos

### 3. `evt_interfaz/editar_evento.php` (MODIFICADO)
- Al guardar cambios, notifica a otras pestañas usando:
  - `postMessage` para ventanas popup
  - `localStorage` para otras pestañas del navegador
- Usa una página intermedia para ejecutar el JavaScript antes de redirigir

### 4. `evt_interfaz/act_evento.php` (MODIFICADO)
- Función `borrar_evento()` ahora usa `INSERT IGNORE` para evitar errores de duplicados
- Soluciona el error: "Duplicate entry '29' for key 'evento.PRIMARY'"

### 5. `vnt_interfaz/index.php` (MODIFICADO)
- Incluye el nuevo script `evento-sync.js`
- Código de sincronización movido a archivo separado para mejor organización

### 6. `admin_interfaz/ctg_boletos/action.php` (MODIFICADO)
- Notifica cuando se crean, editan o eliminan categorías
- Guarda en `localStorage` con key `categorias_actualizadas`

### 7. `admin_interfaz/dsc_boletos/promos_api.php` (MODIFICADO)
- Notifica cuando se crean o editan descuentos
- Devuelve `notify_change: true` en la respuesta JSON

### 8. `admin_interfaz/dsc_boletos/index.php` (MODIFICADO)
- Detecta la respuesta del API y guarda en `localStorage` con key `descuentos_actualizados`

### 9. `mp_interfaz/ajax_guardar_mapa.php` (MODIFICADO)
- Notifica cuando se guarda el mapa de asientos
- Devuelve `notify_change: true` en la respuesta JSON

### 10. `mp_interfaz/index.php` (MODIFICADO)
- Detecta la respuesta del guardado y guarda en `localStorage` con key `mapa_actualizado`
- **NUEVO:** Escucha cambios en `categorias_actualizadas` para recargar cuando se crean/editan categorías

## Cómo Funciona

### Cuando se EDITA un evento:
1. `editar_evento.php` guarda los cambios en la BD
2. Ejecuta JavaScript que:
   - Envía mensaje `postMessage` a ventana padre (si existe)
   - Guarda en `localStorage` con key `evento_actualizado`
3. `evento-sync.js` detecta el cambio y:
   - Si es el evento actual → Recarga la página
   - Si es otro evento → Solo actualiza el selector

### Cuando se CREA un evento:
1. `act_evento.php` guarda en `localStorage` con key `evt_upd`
2. `evento-sync.js` detecta el cambio
3. Actualiza el selector con el nuevo evento

### Cuando se CREAN/EDITAN categorías:
1. `action.php` guarda los cambios en la BD
2. Ejecuta JavaScript que guarda en `localStorage` con key `categorias_actualizadas`
3. `evento-sync.js` detecta el cambio y:
   - Si es el evento actual en venta → Recarga la página de venta
   - Si es otro evento → No hace nada
4. **NUEVO:** `mp_interfaz/index.php` también detecta el cambio y:
   - Si es el evento actual en el mapeador → Recarga para actualizar la paleta de categorías
   - Si es otro evento → No hace nada

### Cuando se CREAN/EDITAN descuentos:
1. `promos_api.php` guarda los cambios en la BD
2. Devuelve `notify_change: true` en la respuesta
3. `index.php` (descuentos) guarda en `localStorage` con key `descuentos_actualizados`
4. `evento-sync.js` detecta el cambio y:
   - Si es el evento actual → Recarga la página de venta
   - Si es otro evento → No hace nada

### Cuando se GUARDA el mapa de asientos:
1. `ajax_guardar_mapa.php` guarda los cambios en la BD
2. Devuelve `notify_change: true` en la respuesta
3. `index.php` (mapeador) guarda en `localStorage` con key `mapa_actualizado`
4. `evento-sync.js` detecta el cambio y:
   - Si es el evento actual → Recarga la página de venta
   - Si es otro evento → No hace nada

### Sincronización Periódica:
- Cada 30 segundos consulta `obtener_eventos.php`
- Detecta cambios en títulos o eventos eliminados
- Actualiza el selector si hay diferencias

## Beneficios

✅ No necesitas recargar manualmente la página de venta
✅ Los cambios se reflejan automáticamente en todas las pestañas abiertas
✅ Si editas el evento que estás viendo, la página se recarga automáticamente
✅ **NUEVO:** Si creas/editas categorías del evento actual, la venta Y el mapeador se actualizan automáticamente
✅ **NUEVO:** Si creas/editas descuentos del evento actual, la venta se actualiza automáticamente
✅ **NUEVO:** Si guardas el mapa de asientos del evento actual, la venta se actualiza automáticamente
✅ Funciona entre múltiples pestañas y ventanas del navegador
✅ Sincronización periódica como respaldo

## Solución al Error de Duplicados

El error "Duplicate entry '29' for key 'evento.PRIMARY'" ocurría cuando se intentaba archivar un evento que ya existía en el histórico.

**Solución:** Usar `INSERT IGNORE` en lugar de `INSERT` para que MySQL ignore los duplicados automáticamente.

```php
// Antes:
INSERT INTO trt_historico_evento.evento SELECT * FROM trt_25.evento WHERE id_evento = $id_evento

// Ahora:
INSERT IGNORE INTO trt_historico_evento.evento SELECT * FROM trt_25.evento WHERE id_evento = $id_evento
```

Esto permite que el archivado funcione correctamente incluso si el evento ya existe en el histórico.
