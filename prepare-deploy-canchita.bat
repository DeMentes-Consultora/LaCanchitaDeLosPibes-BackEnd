@echo off
setlocal

echo.
echo ================================================
echo   Preparando La Canchita para InfinityFree
echo ================================================
echo.

set "BACKEND_DIR=%~dp0"
cd /d "%BACKEND_DIR%"
set "FRONTEND_DIR=%BACKEND_DIR%..\LaCanchitaDeLosPibes-FrontEnd"
set "DEPLOY_DIR=%BACKEND_DIR%deploy_canchita"

if not exist "%BACKEND_DIR%composer.json" (
    echo ERROR: Ejecuta este script desde LaCanchitaDeLosPibes-BackEnd
    pause
    exit /b 1
)

if not exist "%FRONTEND_DIR%\package.json" (
    echo ERROR: No se encontro el frontend en %FRONTEND_DIR%
    pause
    exit /b 1
)

if not exist "%BACKEND_DIR%\.env.production" (
    echo ERROR: No existe .env.production en el backend
    echo Completa ese archivo antes de preparar el deploy.
    pause
    exit /b 1
)

echo [1/5] Instalando dependencias del Backend...
if exist "%BACKEND_DIR%composer.phar" (
    call "%BACKEND_DIR%composer.bat" install --no-dev --optimize-autoloader
    if errorlevel 1 (
        echo ERROR: No se pudieron instalar las dependencias del Backend
        pause
        exit /b 1
    )
) else (
    if exist "%BACKEND_DIR%vendor\autoload.php" (
        echo AVISO: composer.phar no existe. Se reutiliza vendor ya instalado.
    ) else (
        echo ERROR: No existe composer.phar ni vendor\autoload.php
        echo Instala Composer o genera vendor antes de preparar el deploy.
        pause
        exit /b 1
    )
)
echo OK - Dependencias del Backend instaladas
echo.

echo [2/5] Instalando dependencias del Frontend...
cd /d "%FRONTEND_DIR%"
call npm install
if errorlevel 1 (
    echo ERROR: No se pudieron instalar las dependencias del Frontend
    cd /d "%BACKEND_DIR%"
    pause
    exit /b 1
)
echo OK - Dependencias del Frontend instaladas
echo.

echo [3/5] Compilando Frontend Angular en modo SPA...
call npm run build -- --configuration spa
if errorlevel 1 (
    echo ERROR: No se pudo compilar el Frontend
    cd /d "%BACKEND_DIR%"
    pause
    exit /b 1
)
cd /d "%BACKEND_DIR%"
echo OK - Frontend compilado
echo.

echo [4/5] Creando carpeta de despliegue...
if exist "%DEPLOY_DIR%" (
    rmdir /s /q "%DEPLOY_DIR%"
)
mkdir "%DEPLOY_DIR%"
mkdir "%DEPLOY_DIR%\src"

echo    - Copiando backend...
xcopy /E /I /Y "%BACKEND_DIR%vendor" "%DEPLOY_DIR%\vendor" >nul
xcopy /E /I /Y "%BACKEND_DIR%src" "%DEPLOY_DIR%\src" >nul
copy /Y "%BACKEND_DIR%\.env.production" "%DEPLOY_DIR%\.env" >nul
copy /Y "%BACKEND_DIR%\check-server.php" "%DEPLOY_DIR%\check-server.php" >nul

echo    - Creando .htaccess...
copy /Y "%BACKEND_DIR%\infinityfree.htaccess" "%DEPLOY_DIR%\.htaccess" >nul

echo    - Copiando frontend compilado...
powershell -ExecutionPolicy Bypass -Command "$paths=@('%FRONTEND_DIR%\dist\front-end-canchita-angular\browser','%FRONTEND_DIR%\dist\front-end-canchita-angular','%FRONTEND_DIR%\dist\FrontEnd-Canchita-Angular\browser','%FRONTEND_DIR%\dist\FrontEnd-Canchita-Angular'); $source=$paths | Where-Object { Test-Path $_ } | Select-Object -First 1; if (-not $source) { exit 1 }; Copy-Item -Path (Join-Path $source '*') -Destination '%DEPLOY_DIR%' -Recurse -Force; Remove-Item -Path '%DEPLOY_DIR%\browser' -Recurse -Force -ErrorAction SilentlyContinue; Remove-Item -Path '%DEPLOY_DIR%\server' -Recurse -Force -ErrorAction SilentlyContinue; Remove-Item -Path '%DEPLOY_DIR%\prerendered-routes.json' -Force -ErrorAction SilentlyContinue; Remove-Item -Path '%DEPLOY_DIR%\3rdpartylicenses.txt' -Force -ErrorAction SilentlyContinue" 2>nul
if errorlevel 1 (
    echo ERROR: No se pudo copiar el frontend compilado
    echo Verifica la salida en dist/ del proyecto Angular
    pause
    exit /b 1
)

if exist "%DEPLOY_DIR%\index.csr.html" (
    copy /Y "%DEPLOY_DIR%\index.csr.html" "%DEPLOY_DIR%\index.html" >nul
)

echo OK - Carpeta de despliegue creada
echo.

echo [5/5] Creando instrucciones...
(
echo ================================================================
echo        INSTRUCCIONES DE DESPLIEGUE - LA CANCHITA
echo ================================================================
echo.
echo 1. Edita el archivo .env dentro de deploy_canchita:
echo    - Completa DB_HOST, DB_USERNAME, DB_PASSWORD y DB_NAME de InfinityFree
echo    - Verifica CLOUDINARY_API_SECRET real
echo    - Completa MAIL_USERNAME, MAIL_PASSWORD y MAIL_FROM_ADDRESS reales
echo.
echo 2. Conecta por FTP a InfinityFree y sube TODO el contenido de deploy_canchita/ a htdocs/
echo.
echo 3. Importa la base de datos en phpMyAdmin desde VistaPanel.
echo.
echo 4. Verifica el servidor:
echo    https://TU-DOMINIO/check-server.php
echo.
echo 5. Prueba la API:
echo    https://TU-DOMINIO/api/canchas.php
echo.
echo 6. Prueba el sitio:
echo    https://TU-DOMINIO/
echo.
echo 7. SEGURIDAD: elimina check-server.php del hosting cuando termines.
echo ================================================================
) > "%DEPLOY_DIR%\INSTRUCCIONES.txt"

echo OK - Instrucciones creadas
echo.
echo ================================================
echo   PREPARACION COMPLETADA
echo ================================================
echo.
echo Revisa: deploy_canchita\.env
echo Luego sube el contenido a htdocs/ en InfinityFree
echo.
pause
