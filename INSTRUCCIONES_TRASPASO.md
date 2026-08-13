# Guía para pasar el proyecto a otra PC

Para mover este proyecto a otra computadora y que funcione correctamente, sigue estos pasos:

## 1. Copiar el Proyecto
Copia toda la carpeta `teatro` a la nueva PC (generalmente en `C:\wamp64\www\`).
*Nota: No es necesario copiar la carpeta `vendor` dentro de `vnt_interfaz` porque el instalador la descargará.*

## 2. Ejecutar el Instalador
1. Abre la carpeta del proyecto en la nueva PC.
2. Haz doble clic en el archivo `INSTALADOR.bat`.
3. El script verificará si tienes PHP y Composer.
   - Si no tienes Composer, intentará descargar una versión portable automáticamente.
4. El script descargará todas las librerías necesarias (QR, PDF, etc.).

## 3. Preparar la Base de Datos
1. Abre **phpMyAdmin** en la nueva PC.
2. Crea una nueva base de datos llamada `trt_25`.
3. Importa el archivo SQL del proyecto (si lo tienes). 
   - *Si no lo tienes, asegúrate de exportarlo de la PC original primero.*

## 4. Configuración
Revisa el archivo `vnt_interfaz/conexion.php` para asegurarte de que el usuario y la contraseña de la base de datos coincidan con la nueva PC.

---

### ¿Qué hace el INSTALADOR.bat?
*   Verifica la presencia de PHP.
*   Verifica o descarga Composer.
*   Ejecuta `composer install` para bajar las dependencias.
*   Crea la carpeta `qr_codes` necesaria para los boletos.
