@echo off
title Iniciar Sistema Teatro
echo.
echo ==========================================================
echo    Iniciando Sistema Teatro
echo ==========================================================
echo.

:: 1. Iniciar WAMP Server si no esta corriendo
tasklist /FI "IMAGENAME eq wampmanager.exe" 2>NUL | find /I /N "wampmanager.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [INFO] WAMP Server ya esta en ejecucion.
) else (
    echo [INFO] Iniciando WAMP Server...
    echo Por favor, acepta los permisos de administrador si se solicitan.
    start "" "c:\wamp64\wampmanager.exe"
    
    :: Esperar unos segundos para que los servicios arranquen
    echo Espere mientras se inician los servicios (15 segundos)...
    timeout /t 15 >nul
)

:: 2. Abrir el navegador en el proyecto
echo.
echo [INFO] Abriendo el sistema en el navegador...
start http://localhost/teatro
timeout /t 1 >nul
start http://localhost/teatro/control_entrada/index.php

:: 3. Finalizar este script
echo.
echo [OK] Sistema iniciado. Puede cerrar esta ventana.
timeout /t 5 >nul
exit
