@echo off
setlocal enabledelayedexpansion
title Instalador de Dependencias - Proyecto Teatro

:: Colores (solo funcionan en Windows 10+ o terminales modernos)
set "green=[32m"
set "red=[31m"
set "yellow=[33m"
set "blue=[34m"
set "reset=[0m"

echo.
echo ==========================================================
echo    Instalador de Dependencias para el Proyecto Teatro
echo ==========================================================
echo.

:: 1. Verificar PHP
echo Verificando PHP...
php -v >nul 2>&1
if !errorlevel! neq 0 (
    echo %red%[ERROR] PHP no esta instalado o no esta en el PATH.%reset%
    echo Por favor instala WAMP/XAMPP y asegurate de que PHP funcione.
    pause
    exit /b
)
echo %green%[OK] PHP detectado.%reset%

:: 2. Verificar Composer
echo Verificando Composer...
composer -v >nul 2>&1
if !errorlevel! neq 0 (
    echo %yellow%[AVISO] Composer no esta instalado o no esta en el PATH.%reset%
    echo Intentando descargar Composer portable...
    
    :: Descargar composer.phar si no existe
    if not exist "composer.phar" (
        echo Descargando composer.phar...
        powershell -Command "Invoke-WebRequest -Uri 'https://getcomposer.org/composer-stable.phar' -OutFile 'composer.phar'"
    )
    
    set COMPOSER_CMD=php composer.phar
) else (
    set COMPOSER_CMD=composer
)
echo %green%[OK] Composer listo.%reset%

:: 3. Instalar dependencias en vnt_interfaz
echo.
echo ==========================================================
echo    Instalando dependencias en vnt_interfaz...
echo ==========================================================
echo.

if exist "vnt_interfaz\composer.json" (
    cd vnt_interfaz
    echo Ejecutando: !COMPOSER_CMD! install
    call !COMPOSER_CMD! install --no-interaction
    
    if !errorlevel! neq 0 (
        echo.
        echo %red%[ERROR] Hubo un problema al instalar las dependencias.%reset%
        cd ..
        pause
        exit /b
    )
    
    :: 4. Crear carpeta de QR si no existe
    if not exist "qr_codes" (
        echo Creando carpeta qr_codes...
        mkdir qr_codes
    )
    
    cd ..
) else (
    echo %red%[ERROR] No se encontro vnt_interfaz\composer.json%reset%
)

echo.
echo ==========================================================
echo    %green%Instalacion completada con exito.%reset%
echo ==========================================================
echo.
echo %blue%Pasos siguientes:%reset%
echo 1. Asegurate de tener WAMP/XAMPP encendido.
echo 2. Crea la base de datos "trt_25" en phpMyAdmin.
echo 3. Verifica la conexion en: vnt_interfaz/conexion.php
echo.
echo Presiona cualquier tecla para salir.
pause >nul
exit /b
