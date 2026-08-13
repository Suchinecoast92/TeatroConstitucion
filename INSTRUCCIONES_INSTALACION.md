# Instrucciones de Instalación - Sistema de Boletos con QR

## Requisitos Previos
- PHP 7.4 o superior
- MySQL
- Composer (gestor de dependencias de PHP)
- Servidor web (Apache/XAMPP recomendado)

## Pasos de Instalación

### 1. Instalar Composer (si no lo tienes)
Descarga e instala Composer desde: https://getcomposer.org/download/

### 2. Instalar Dependencias (Método Automático - RECOMENDADO)
Si estás en Windows, simplemente haz doble clic en el archivo:
**`INSTALADOR.bat`** en la raíz del proyecto.

Este script hará todo por ti: verificará PHP, Composer, instalará las dependencias y creará las carpetas necesarias.

### 2b. Instalar Dependencias (Método Manual)
Si prefieres hacerlo manualmente o no estás en Windows:
Abre una terminal en la carpeta raíz del proyecto y ejecuta:

```bash
cd vnt_interfaz
composer install
mkdir qr_codes
```

Este comando instalará:
- `endroid/qr-code`: Para generar códigos QR
- `dompdf/dompdf`: Para generar PDFs de los boletos

### 3. Configurar Permisos
Asegúrate de que la carpeta `vnt_interfaz/qr_codes/` tenga permisos de escritura:

```bash
mkdir vnt_interfaz/qr_codes
chmod 777 vnt_interfaz/qr_codes
```

En Windows (XAMPP), esto se hace automáticamente.

### 4. Verificar Conexión a Base de Datos
Revisa el archivo `vnt_interfaz/conexion.php` y asegúrate de que los datos de conexión sean correctos:
- Servidor: localhost
- Usuario: root
- Contraseña: (vacía por defecto en XAMPP)
- Base de datos: trt_25

### 5. Estructura de la Base de Datos
Asegúrate de que tu base de datos `trt_25` tenga todas las tablas necesarias según la estructura proporcionada.

## Funcionalidades Implementadas

### 1. Compra de Boletos
- Selecciona asientos disponibles en el mapa
- Los asientos vendidos se muestran en rojo y no se pueden seleccionar
- Al comprar, se generan boletos en la base de datos con código único
- Se genera automáticamente un código QR para cada boleto

### 2. Generación de QR
- Cada boleto tiene un código único (formato: TRT-XXXXXX-TIMESTAMP)
- El código QR se guarda en `vnt_interfaz/qr_codes/`
- El QR contiene el código único del boleto

### 3. Descarga de PDF
- Cada boleto se puede descargar como PDF
- El PDF incluye:
  - Información del evento
  - Datos del asiento
  - Código QR
  - Precio e información de compra

### 4. Escaneo de Boletos
- Botón "Escanear Boleto" en la interfaz principal
- Ingresa el código del boleto para verificarlo
- Muestra toda la información del boleto
- Indica si el boleto es válido o ya fue usado

### 5. Confirmación de Acceso
- Botón "Confirmar Acceso" para boletos válidos
- Cambia el estatus del boleto de 1 (válido) a 0 (usado)
- Los boletos usados no pueden volver a utilizarse

## Archivos Creados

### Backend (PHP)
- `vnt_interfaz/conexion.php` - Conexión a base de datos
- `vnt_interfaz/procesar_compra.php` - Procesa la compra y genera QR
- `vnt_interfaz/verificar_boleto.php` - Verifica el código del boleto
- `vnt_interfaz/confirmar_acceso.php` - Marca el boleto como usado
- `vnt_interfaz/generar_pdf.php` - Genera el PDF del boleto

### Frontend
- `vnt_interfaz/index.php` - Interfaz principal actualizada con todas las funcionalidades

### Configuración
- `composer.json` - Dependencias del proyecto

## Uso del Sistema

### Para Comprar Boletos:
1. Accede a `vnt_interfaz/index.php`
2. Selecciona un evento
3. Haz clic en los asientos disponibles (verdes)
4. Clic en "Comprar Boletos"
5. Se mostrarán los boletos generados con opción de descargar PDF

### Para Escanear Boletos:
1. En la misma interfaz, clic en "Escanear Boleto"
2. Ingresa el código del boleto (ej: TRT-65ABC123-1234567890)
3. Clic en "Verificar Boleto"
4. Si es válido, aparecerá el botón "Confirmar Acceso"
5. Al confirmar, el boleto queda marcado como usado

## Notas Importantes

- Los códigos QR se guardan en `vnt_interfaz/qr_codes/`
- El campo `estatus` en la tabla `boletos`: 1 = válido, 0 = usado
- El campo `codigo_unico` es único para cada boleto
- Los asientos vendidos no se pueden volver a seleccionar
- El sistema respeta el menú de `ind_menu/inicio.php`

## Solución de Problemas

### Error: "Class not found"
Ejecuta `composer install` en la raíz del proyecto.

### Error: "Permission denied" al generar QR
Verifica los permisos de la carpeta `vnt_interfaz/qr_codes/`.

### Los QR no se generan
Asegúrate de que la librería `endroid/qr-code` esté instalada correctamente.

### El PDF no se descarga
Verifica que `dompdf/dompdf` esté instalado y que no haya errores en PHP.
