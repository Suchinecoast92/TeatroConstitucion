# 🔐 Flujo de Acceso al Panel de Administración

## Diagrama de Flujo

```
┌─────────────────────────────────────────────────────────────┐
│                    INICIO DE SESIÓN                          │
│                    (login.php)                               │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
        ┌──────────────────────────────┐
        │  ¿Credenciales correctas?    │
        └──────────┬───────────────────┘
                   │
        ┌──────────┴──────────┐
        │                     │
       NO                    SÍ
        │                     │
        ▼                     ▼
   ┌─────────┐        ┌──────────────┐
   │ ERROR   │        │ Crear sesión │
   │ Login   │        │ PHP con rol  │
   └─────────┘        └──────┬───────┘
                             │
                             ▼
                    ┌────────────────┐
                    │ index.php      │
                    │ (Sistema)      │
                    └────────┬───────┘
                             │
                   ┌─────────┴──────────┐
                   │ Click en           │
                   │ "Administración"   │
                   └─────────┬──────────┘
                             │
                             ▼
                  ┌──────────────────────┐
                  │ ¿Cuál es el rol?     │
                  └──────┬───────────────┘
                         │
          ┌──────────────┴───────────────┐
          │                              │
     ROL: ADMIN                    ROL: EMPLEADO
          │                              │
          ▼                              ▼
┌──────────────────┐         ┌─────────────────────────┐
│ ACCESO DIRECTO   │         │ ¿Ya verificó password?  │
│ (Sin modal)      │         └──────┬──────────────────┘
└────────┬─────────┘                │
         │              ┌────────────┴────────────┐
         │             SÍ                        NO
         │              │                         │
         │              ▼                         ▼
         │    ┌─────────────────┐     ┌─────────────────┐
         │    │ ACCESO DIRECTO  │     │ MOSTRAR MODAL   │
         │    │ (Ya verificado) │     │ Pedir password  │
         │    └────────┬────────┘     └────────┬────────┘
         │             │                       │
         │             │              ┌────────┴────────┐
         │             │             │  ¿Password OK?  │
         │             │             └────────┬────────┘
         │             │                      │
         │             │          ┌───────────┴────────┐
         │             │         SÍ                   NO
         │             │          │                    │
         │             │          ▼                    ▼
         │             │  ┌────────────────┐  ┌──────────┐
         │             │  │ Marcar como    │  │  ERROR   │
         │             │  │ verificado en  │  │ Reintentar│
         │             │  │ sesión PHP     │  └──────────┘
         │             │  └───────┬────────┘
         │             │          │
         └─────────────┴──────────┘
                       │
                       ▼
         ┌──────────────────────────┐
         │  PANEL DE ADMINISTRACIÓN │
         │  (admin_interfaz/)       │
         └──────────────────────────┘
```

## 📊 Tabla de Permisos

| Acción | Admin | Empleado sin verificar | Empleado verificado |
|--------|-------|------------------------|---------------------|
| **Login** | ✅ | ✅ | ✅ |
| **Inicio** | ✅ | ✅ | ✅ |
| **Evento** | ✅ | ✅ | ✅ |
| **Venta** | ✅ | ✅ | ✅ |
| **Ajuste escenario** | ✅ | ✅ | ✅ |
| **Cartelera** | ✅ | ✅ | ✅ |
| **Administración** | ✅ Directo | ❌ Requiere password | ✅ Directo |
| **Registrar Empleado** | ✅ | ❌ No visible | ❌ No visible |

## 🎯 Escenarios de Uso

### Escenario 1: Admin accede a Administración
```
1. Login como admin con tus credenciales reales
2. Click en "Administración"
3. ✅ Acceso inmediato sin modal
4. Se muestra el panel de administración
```

### Escenario 2: Empleado accede a Administración (Primera vez)
```
1. Login como empleado con tus credenciales reales
2. Click en "Administración"
3. 🔒 Aparece modal pidiendo contraseña del admin
4. Ingresa la contraseña del administrador (no la del empleado)
5. Click en "Verificar"
6. ✅ Acceso concedido
7. Se marca como verificado en la sesión
8. Se muestra el panel de administración
```

### Escenario 3: Empleado accede a Administración (Ya verificado)
```
1. Ya está logueado como empleado
2. Ya ingresó la contraseña del admin anteriormente
3. Click en "Administración"
4. ✅ Acceso inmediato sin modal
5. La verificación es válida durante toda la sesión
```

### Escenario 4: Empleado intenta bypass (Cancelar modal)
```
1. Login como empleado
2. Click en "Administración"
3. 🔒 Aparece modal
4. Click en "Cancelar"
5. ❌ NO se concede acceso
6. Permanece en la pestaña actual
7. Protección en múltiples capas previene el acceso
```

### Escenario 5: Empleado cierra sesión y vuelve a entrar
```
1. Login como empleado (previamente verificado)
2. Click en "Cerrar Sesión"
3. → Sesión destruida (incluyendo admin_verificado)
4. Login nuevamente como empleado
5. Click en "Administración"
6. 🔒 Aparece modal nuevamente
7. Debe ingresar contraseña del admin otra vez
```

## 🔑 Variables de Sesión

### Al iniciar sesión
```php
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_nombre'] = "Juan";
$_SESSION['usuario_apellido'] = "Pérez";
$_SESSION['usuario_rol'] = "empleado"; // o "admin"
$_SESSION['login_time'] = time();
```

### Al verificar como admin (solo empleados)
```php
$_SESSION['admin_verificado'] = true;
$_SESSION['admin_verificado_time'] = time();
```

### Al cerrar sesión
```php
session_destroy(); // Elimina todas las variables
```

## 🛡️ Validaciones en Cada Capa

### JavaScript (Frontend)
```javascript
// 1. Validar en evento click del botón
if (usuario_rol === 'admin') {
    → Acceso directo
} else if (admin_verificado) {
    → Acceso directo
} else {
    → Mostrar modal
}

// 2. Validar en función cambiarPestana()
if (targetId === 'frame-admin' && rol === 'empleado' && !verificado) {
    → Mostrar modal
    → return false
}

// 3. Validar localStorage al cargar
if (pestanaGuardada === 'frame-admin' && rol === 'empleado' && !verificado) {
    → pestanaGuardada = null
    → Ir a inicio
}
```

### PHP (Backend)
```php
// 1. Validar sesión activa
if (!isset($_SESSION['usuario_id'])) {
    → die('Acceso denegado')
}

// 2. Validar acceso según rol
if ($_SESSION['usuario_rol'] !== 'admin') {
    if (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']) {
        → die('Requiere verificación')
    }
}
```

## 📝 Resumen

### ✅ Lo que SÍ puedes hacer

**Como Admin:**
- ✅ Acceso directo a TODAS las secciones
- ✅ NO se requiere contraseña adicional para administración
- ✅ Registrar nuevos empleados
- ✅ Ver panel de administración inmediatamente

**Como Empleado:**
- ✅ Acceso a todas las secciones básicas (Inicio, Evento, Venta, etc.)
- ✅ Acceso a administración DESPUÉS de ingresar contraseña del admin
- ✅ Una vez verificado, acceso directo durante toda la sesión

### ❌ Lo que NO puedes hacer

**Como Empleado sin verificar:**
- ❌ Acceder a panel de administración
- ❌ Ver opción "Registrar Empleado"
- ❌ Bypass del modal de verificación

**Como Empleado verificado:**
- ❌ Registrar nuevos empleados (solo admin)

## 🔒 Seguridad Implementada

1. ✅ **Validación de rol en sesión PHP** (no manipulable desde cliente)
2. ✅ **Doble validación en JavaScript** (preventDefault + stopPropagation)
3. ✅ **Validación en función cambiarPestana()**
4. ✅ **Validación de localStorage**
5. ✅ **Validación en backend PHP** (admin_interfaz/index.php)
6. ✅ **API de verificación** (auth/verificar_admin.php)
7. ✅ **Validación en base de datos** (solo admin activo)

---

**Última actualización:** 2025-11-12  
**Versión:** 3.0 - Admin con acceso directo
