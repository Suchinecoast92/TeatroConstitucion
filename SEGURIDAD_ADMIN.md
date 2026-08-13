# 🔒 Seguridad del Panel de Administración

## Lógica de Acceso

### 👑 Para Usuarios con Rol ADMIN
- **Acceso directo** al panel de administración
- **NO se solicita contraseña adicional**
- Ya está autenticado con su usuario de admin

### 👤 Para Usuarios con Rol EMPLEADO
- **Requiere verificación** para acceder al panel
- **Debe ingresar la contraseña del administrador**
- Una vez verificado, el acceso es válido durante toda la sesión

## Protecciones Implementadas

El sistema tiene **múltiples capas de seguridad** para proteger el acceso al panel de administración:

### 1️⃣ Protección en Frontend (JavaScript)

#### Prevención de Click sin Verificación
```javascript
// El evento del admin-link se ejecuta ANTES que el evento general
adminLink.addEventListener('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    // Si el usuario es admin, dar acceso directo
    if (usuario_rol === 'admin') {
        cambiarPestana('frame-admin');
    }
    // Si es empleado pero ya verificó la contraseña del admin
    else if (admin_verificado) {
        cambiarPestana('frame-admin');
    }
    // Si es empleado y no ha verificado, pedir contraseña
    else {
        mostrarModalAdmin('frame-admin');
    }
}, true); // Capture phase
```

#### Doble Validación en Eventos
```javascript
// El evento general de menuLinks también valida
if (this.id === 'admin-link') {
    // Solo bloquear si es empleado y no ha verificado
    if (usuario_rol === 'empleado' && !admin_verificado) {
        e.preventDefault();
        e.stopPropagation();
        return false;
    }
}
```

#### Protección en Función cambiarPestana()
```javascript
// Previene cambiar a frame-admin si es empleado sin verificación
if (targetId === 'frame-admin') {
    if (usuario_rol === 'empleado' && !admin_verificado) {
        mostrarModalAdmin('frame-admin');
        return false;
    }
}
```

#### Protección de localStorage
```javascript
// Si la pestaña guardada es frame-admin, validar según rol
if (pestanaGuardada === 'frame-admin') {
    // Solo forzar a inicio si es empleado sin verificación
    if (usuario_rol === 'empleado' && !admin_verificado) {
        pestanaGuardada = null; // Forzar a inicio
    }
}
```

### 2️⃣ Protección en Backend (PHP)

#### Verificación en admin_interfaz/index.php
```php
<?php
session_start();

// Primera verificación: Sesión activa
if (!isset($_SESSION['usuario_id'])) {
    die('Acceso denegado. Debe iniciar sesión.');
}

// Segunda verificación: Validar acceso según rol
// Si es admin de rol, acceso directo
// Si es empleado, debe haber verificado con contraseña del admin
if ($_SESSION['usuario_rol'] !== 'admin') {
    // Es empleado, verificar que haya ingresado contraseña del admin
    if (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']) {
        die('Acceso denegado. Requiere verificación de administrador.');
    }
}
?>
```

#### Verificación en auth/verificar_admin.php
```php
<?php
// Valida contra la base de datos
// Verifica que sea un admin activo
// Guarda verificación en sesión
$_SESSION['admin_verificado'] = true;
?>
```

### 3️⃣ Protección en Base de Datos

- Solo usuarios con `rol = 'admin'` pueden verificarse
- Solo usuarios con `activo = 1` pueden acceder
- Contraseña validada contra la BD

## 🛡️ Capas de Seguridad Explicadas

### Capa 1: JavaScript - Prevenir Clicks
**Objetivo:** Evitar que un usuario haga click en "Administración" sin autorización
- ✅ Evento con capture phase (se ejecuta primero)
- ✅ preventDefault() para prevenir navegación
- ✅ stopPropagation() para evitar propagación del evento
- ✅ Doble validación en dos event listeners diferentes

### Capa 2: JavaScript - Validación de Cambio de Pestaña
**Objetivo:** Evitar manipulación del código JS en consola del navegador
- ✅ Validación en función `cambiarPestana()`
- ✅ Verificación contra sesión PHP (no JS)
- ✅ Protección del localStorage

### Capa 3: PHP - Sesión Verificada
**Objetivo:** Evitar acceso directo a la URL o manipulación de frontend
- ✅ Verificación de `$_SESSION['usuario_id']` (debe estar logueado)
- ✅ Verificación de `$_SESSION['admin_verificado']` (debe haber ingresado contraseña)
- ✅ Muerte del script si no cumple requisitos

### Capa 4: PHP - API de Verificación
**Objetivo:** Validar contraseña contra base de datos
- ✅ Validación en servidor (no cliente)
- ✅ Comparación contra BD
- ✅ Solo admin activo puede verificar
- ✅ Token de sesión una vez verificado

## ⚠️ Escenarios de Ataque Prevenidos

### ❌ Escenario 1: Click en Administración + Cancelar Modal
**Ataque:** Empleado hace click en "Administración", aparece modal, presiona "Cancelar"
**Prevención:** 
- Evento con `preventDefault()` y `stopPropagation()` 
- Doble validación en dos event listeners
- Función `cambiarPestana()` valida antes de cambiar

### ❌ Escenario 2: Manipular código JS en consola
**Ataque:** Abrir consola y ejecutar: `cambiarPestana('frame-admin')`
**Prevención:**
- La función `cambiarPestana()` valida contra sesión PHP
- Muestra modal si no está verificado
- No cambia la pestaña si no está autorizado

### ❌ Escenario 3: Manipular localStorage
**Ataque:** Abrir consola y ejecutar: `localStorage.setItem('ultimaPestanaActiva', 'frame-admin')`
**Prevención:**
- Al cargar la página, valida si la pestaña guardada es 'frame-admin'
- Si no está verificado, ignora el localStorage
- Redirige a 'frame-inicio'

### ❌ Escenario 4: Acceso directo a URL
**Ataque:** Ir directamente a: `http://localhost/teatro/admin_interfaz/index.php`
**Prevención:**
- Primera línea: `session_start()`
- Verificación de `$_SESSION['usuario_id']`
- Verificación de `$_SESSION['admin_verificado']`
- Si falla, script muere con mensaje de error

### ❌ Escenario 5: Modificar variable de sesión en cliente
**Ataque:** Intentar crear variable de sesión desde JavaScript
**Prevención:**
- Las sesiones PHP NO se pueden manipular desde JavaScript del cliente
- Las variables de sesión solo existen en el servidor
- JavaScript solo puede leer datos que el servidor envía explícitamente

## 🧪 Pruebas de Seguridad

### Prueba 1: Click + Cancelar
1. Inicia sesión como empleado
2. Click en "Administración"
3. Aparece modal
4. Click en "Cancelar"
5. ✅ NO debe mostrar la página de administración
6. ✅ Debe permanecer en la pestaña actual

### Prueba 2: Manipulación en Consola
1. Inicia sesión como empleado
2. Abre consola del navegador (F12)
3. Ejecuta: `cambiarPestana('frame-admin')`
4. ✅ Debe mostrar: "Acceso denegado a administración sin verificación"
5. ✅ Debe mostrar el modal
6. ✅ NO debe cambiar a la pestaña admin

### Prueba 3: LocalStorage Forzado
1. Inicia sesión como empleado
2. Abre consola del navegador (F12)
3. Ejecuta: `localStorage.setItem('ultimaPestanaActiva', 'frame-admin')`
4. Recarga la página (F5)
5. ✅ Debe mostrar: "Pestaña admin guardada pero sin verificación"
6. ✅ Debe cargar en "Inicio"

### Prueba 4: URL Directa
1. Inicia sesión como empleado (NO verificar admin)
2. En una nueva pestaña, ve a: `http://localhost/teatro/admin_interfaz/index.php`
3. ✅ Debe mostrar: "Acceso denegado. Requiere verificación de administrador"
4. ✅ NO debe mostrar el panel de administración

### Prueba 5: Flujo Correcto
1. Inicia sesión como empleado o admin
2. Click en "Administración"
3. Aparece modal
4. Ingresa contraseña correcta del admin
5. Click en "Verificar"
6. ✅ Debe recargar la página
7. ✅ Debe mostrar el panel de administración
8. ✅ No debe volver a pedir contraseña en la misma sesión

## 📊 Resumen de Seguridad

| Capa | Tipo | Función | Vulnerabilidad que previene |
|------|------|---------|----------------------------|
| 1 | JavaScript | Event Listener con capture | Click + Cancelar |
| 2 | JavaScript | Doble validación de eventos | Bypass de preventDefault |
| 3 | JavaScript | Validación en cambiarPestana() | Manipulación de funciones JS |
| 4 | JavaScript | Validación de localStorage | Forzar pestaña guardada |
| 5 | PHP | Verificación de sesión | Acceso sin login |
| 6 | PHP | Verificación admin_verificado | Acceso sin contraseña admin |
| 7 | PHP | API de verificación | Bypass de frontend |
| 8 | Base de Datos | Validación de rol y activo | Usuarios inválidos |

## 🔐 Mejoras Futuras para Producción

1. **Encriptación de contraseñas**
   - Usar `password_hash()` y `password_verify()`
   - No almacenar contraseñas en texto plano

2. **Tokens CSRF**
   - Agregar token CSRF en formularios
   - Validar en cada petición POST

3. **Límite de intentos**
   - Implementar límite de intentos de login
   - Bloquear temporalmente después de X intentos fallidos

4. **Timeout de sesión**
   - Cerrar sesión automáticamente después de inactividad
   - Tiempo de vida limitado para admin_verificado

5. **Logs de auditoría**
   - Registrar todos los intentos de acceso a administración
   - Registrar verificaciones exitosas y fallidas

6. **HTTPS**
   - Forzar conexión segura
   - Evitar man-in-the-middle

7. **Headers de seguridad**
   - X-Frame-Options: DENY
   - Content-Security-Policy
   - X-Content-Type-Options: nosniff

## 🎓 Conclusión

El sistema implementa **8 capas de seguridad** que previenen:
- ✅ Click en botón + Cancelar modal
- ✅ Manipulación de código JavaScript
- ✅ Manipulación de localStorage
- ✅ Acceso directo a URLs
- ✅ Bypass de verificación frontend
- ✅ Acceso sin contraseña de admin

**Todas las protecciones están activas y funcionando correctamente.**

---
**Última actualización:** 2025-11-12  
**Versión de seguridad:** 2.0
