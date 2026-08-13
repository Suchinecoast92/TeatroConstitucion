# Sistema de Autenticación - Teatro

## 📋 Descripción

Sistema de login y registro para empleados del teatro con roles diferenciados (Admin y Empleado).

## 🚀 Instalación

### 1. Actualizar la Base de Datos

Ejecuta el siguiente script SQL en phpMyAdmin:

```sql
-- Agregar columnas necesarias a la tabla usuarios
ALTER TABLE usuarios 
ADD COLUMN rol ENUM('empleado', 'admin') NOT NULL DEFAULT 'empleado' AFTER password;

ALTER TABLE usuarios 
ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER rol;

ALTER TABLE usuarios 
ADD COLUMN fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER activo;

-- Crear usuario administrador por defecto
INSERT INTO usuarios (nombre, apellido, password, rol, activo) 
VALUES ('Administrador', 'Sistema', '123456', 'admin', 1);
```

**Nota:** También puedes ejecutar el archivo: `sql_updates/agregar_rol_usuarios.sql`

### 2. Verificar Archivos Creados

Asegúrate de que existen los siguientes archivos:

```
teatro/
├── login.php                          # Página de inicio de sesión
├── logout.php                         # Cerrar sesión
├── conexion.php                       # Conexión a base de datos (ya existente)
├── index.php                          # Sistema principal (modificado)
├── auth/
│   ├── registrar_empleado.php        # Registro de nuevos empleados
│   └── verificar_admin.php           # API para verificar contraseña admin
└── sql_updates/
    └── agregar_rol_usuarios.sql      # Script SQL de instalación
```

## 👤 Credenciales por Defecto

Después de ejecutar el script SQL, se crea un usuario administrador:

- **Usuario:** Administrador
- **Contraseña:** 123456
- **Rol:** admin

## 🔐 Funcionalidades

### Para Todos los Usuarios

1. **Inicio de Sesión** (`login.php`)
   - Ingresar con nombre de usuario y contraseña
   - Solo usuarios activos pueden acceder
   - Redirección automática si ya hay sesión activa

2. **Cerrar Sesión**
   - Botón "Cerrar Sesión" en el menú lateral
   - Destruye la sesión y redirige al login

### Para Empleados

- Acceso a todas las secciones del sistema excepto:
  - Administración (requiere verificación del admin)
  - Registro de empleados (solo admin)

### Para Administradores

1. **Acceso Completo**
   - Todas las funcionalidades de empleado
   - Panel de administración sin restricciones
   - Registro de nuevos empleados

2. **Registro de Empleados** (`auth/registrar_empleado.php`)
   - Crear nuevos usuarios (empleados o admins)
   - Ver lista de todos los empleados
   - Contraseñas de 6 caracteres
   - Validación de usuarios duplicados

3. **Protección del Panel de Administración**
   - Al hacer clic en "Administración", se solicita la contraseña del admin
   - La verificación es válida durante toda la sesión
   - Solo se verifica una vez por sesión

## 🔒 Seguridad Implementada

1. **Verificación de Sesión**
   - `index.php` requiere sesión activa
   - Redirección automática a login si no hay sesión

2. **Roles y Permisos**
   - Sistema de roles: `admin` y `empleado`
   - Verificación de rol en cada funcionalidad sensible

3. **Verificación de Contraseña Admin**
   - Modal de verificación antes de acceder a administración
   - Validación en servidor (no en cliente)
   - Token de sesión para verificación única

4. **Usuarios Activos/Inactivos**
   - Campo `activo` para habilitar/deshabilitar usuarios
   - Solo usuarios activos pueden iniciar sesión

## 📝 Uso del Sistema

### Primer Ingreso

1. Abre el navegador y accede a: `http://localhost/teatro/`
2. Serás redirigido automáticamente a `login.php`
3. Ingresa las credenciales del admin:
   - Usuario: `Administrador`
   - Contraseña: `123456`
4. Click en "Iniciar Sesión"

### Registrar Nuevos Empleados (Solo Admin)

1. Una vez logueado como admin, verás el menú "Registrar Empleado"
2. Click en "Registrar Empleado"
3. Completa el formulario:
   - Nombre de usuario (único)
   - Apellido
   - Contraseña (6 caracteres)
   - Confirmar contraseña
   - Rol (Empleado o Administrador)
4. Click en "Registrar Empleado"
5. El nuevo empleado aparecerá en la lista

### Acceder al Panel de Administración

**Si eres Admin:**
1. Click en "Administración" en el menú
2. ✅ **Acceso directo** - NO se solicita contraseña adicional
3. Ya estás autenticado con tu usuario administrador

**Si eres Empleado:**
1. Click en "Administración" en el menú
2. Aparece un modal solicitando la contraseña del administrador
3. Ingresa la contraseña del administrador
4. Click en "Verificar"
5. ✅ Acceso concedido (válido durante toda la sesión)

### Cerrar Sesión

1. Click en el botón "Cerrar Sesión" en la parte superior del menú lateral
2. Serás redirigido automáticamente al login

## ⚠️ Notas Importantes

### Seguridad en Producción

**IMPORTANTE:** Este sistema usa contraseñas en texto plano para desarrollo. 

Para producción, debes:

1. **Encriptar contraseñas** usando `password_hash()` en PHP:
   ```php
   // Al registrar
   $password_hash = password_hash($password, PASSWORD_DEFAULT);
   
   // Al verificar
   if (password_verify($password, $password_hash)) {
       // Contraseña correcta
   }
   ```

2. **Usar HTTPS** en lugar de HTTP
3. **Implementar límite de intentos** de login
4. **Agregar tokens CSRF** en formularios
5. **Implementar timeout de sesión**

### Personalización

**Cambiar contraseña del admin:**
```sql
UPDATE usuarios 
SET password = 'nueva_password' 
WHERE rol = 'admin' AND id_usuario = 1;
```

**Desactivar un empleado:**
```sql
UPDATE usuarios 
SET activo = 0 
WHERE id_usuario = [ID_DEL_EMPLEADO];
```

## 🐛 Resolución de Problemas

### "Error de conexión a base de datos"
- Verifica que XAMPP esté ejecutándose
- Verifica las credenciales en `conexion.php`
- Asegúrate de que la base de datos `trt_25` exista

### "Usuario no encontrado o inactivo"
- Verifica que el usuario exista en la tabla `usuarios`
- Verifica que el campo `activo` sea 1
- Ejecuta el script SQL de instalación

### "Contraseña incorrecta"
- Verifica que la contraseña sea correcta
- Las contraseñas son case-sensitive
- Verifica que no haya espacios al inicio o final

### No puedo acceder a "Administración"
- Verifica que exista al menos un usuario con rol 'admin'
- Ingresa la contraseña del administrador (no la tuya si eres empleado)
- Verifica que el archivo `auth/verificar_admin.php` exista

## 📁 Estructura de la Base de Datos

### Tabla: usuarios

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id_usuario | INT(11) | ID único del usuario |
| nombre | VARCHAR(50) | Nombre de usuario (login) |
| apellido | VARCHAR(50) | Apellido del usuario |
| password | CHAR(6) | Contraseña (6 caracteres) |
| rol | ENUM | 'empleado' o 'admin' |
| activo | TINYINT(1) | 1 = activo, 0 = inactivo |
| fecha_registro | DATETIME | Fecha de creación del usuario |

## 💡 Consejos

1. **Cambiar contraseña del admin** inmediatamente después de la instalación
2. **Crear usuarios empleados** con contraseñas únicas para cada persona
3. **Desactivar usuarios** en lugar de eliminarlos para mantener el historial
4. **Hacer backup** de la base de datos regularmente
5. **No compartir** la contraseña del administrador

## 🎨 Características de la Interfaz

- ✅ Diseño moderno y responsivo
- ✅ Animaciones suaves
- ✅ Mensajes de error claros
- ✅ Validación en tiempo real
- ✅ Modal elegante para verificación admin
- ✅ Información de usuario en el menú lateral
- ✅ Indicador de rol (👑 Admin / 👤 Empleado)
- ✅ Botón de logout visible

## 📞 Soporte

Si tienes problemas con la implementación:
1. Verifica que todos los archivos estén en su lugar
2. Ejecuta el script SQL completo
3. Limpia la caché del navegador
4. Verifica los logs de error de PHP en XAMPP

---

**Versión:** 1.0  
**Fecha:** 2025  
**Sistema:** Teatro TRT_25
