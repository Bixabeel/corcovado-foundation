@echo off
chcp 65001 >nul
echo.
echo ========================================
echo  PROCESADOR DE IMAGENES CORCOVADO
echo ========================================
echo.

cd /d "D:\CORCOVADO\CORCOVADO_AUDIT_COLORS\CORCOVADO_LAST_REVIEWS\CORCOVADO_AUDIT\assets\img"

echo Ruta actual: %CD%
echo.
echo --- PASO 1: RENOMBRANDO ARCHIVOS ---
echo.

REM Logo
if exist "logo.png" (
    ren "logo.png" "logo-funcorco.png"
    echo   (OK) logo.png -^> logo-funcorco.png
) else (
    echo   (SKIP) logo.png ya no existe o fue renombrado
)

REM Hero
if exist "corcovado.jpg" (
    ren "corcovado.jpg" "hero-corcovado.jpg"
    echo   (OK) corcovado.jpg -^> hero-corcovado.jpg
) else (
    echo   (SKIP) corcovado.jpg ya no existe
)

REM Programas
if exist "causa1.jpg" (
    ren "causa1.jpg" "program-education.jpg"
    echo   (OK) causa1.jpg -^> program-education.jpg
) else (
    echo   (SKIP) causa1.jpg ya no existe
)

if exist "causa3.jpg" (
    ren "causa3.jpg" "program-restoration.jpg"
    echo   (OK) causa3.jpg -^> program-restoration.jpg
) else (
    echo   (SKIP) causa3.jpg ya no existe
)

if exist "2.webp" (
    ren "2.webp" "program-turtles.webp"
    echo   (OK) 2.webp -^> program-turtles.webp
) else (
    echo   (SKIP) 2.webp ya no existe
)

if exist "3.webp" (
    ren "3.webp" "program-park.webp"
    echo   (OK) 3.webp -^> program-park.webp
) else (
    echo   (SKIP) 3.webp ya no existe
)

echo.
echo --- PASO 2: VERIFICANDO ARCHIVOS ---
echo.

if exist "logo-funcorco.png"       (echo   (OK) logo-funcorco.png) else (echo   (FALTA) logo-funcorco.png)
if exist "hero-corcovado.jpg"      (echo   (OK) hero-corcovado.jpg) else (echo   (FALTA) hero-corcovado.jpg)
if exist "program-education.jpg"   (echo   (OK) program-education.jpg) else (echo   (FALTA) program-education.jpg)
if exist "program-restoration.jpg" (echo   (OK) program-restoration.jpg) else (echo   (FALTA) program-restoration.jpg)
if exist "program-turtles.webp"    (echo   (OK) program-turtles.webp) else (echo   (FALTA) program-turtles.webp)
if exist "program-park.webp"       (echo   (OK) program-park.webp) else (echo   (FALTA) program-park.webp)

echo.
echo ========================================
echo  RENOMBRADO COMPLETADO
echo ========================================
echo.
echo.
echo ========================================
echo  PASO 3: CONVERSION DE FORMATOS (MANUAL)
echo ========================================
echo.
echo Los siguientes archivos necesitan cambio de formato:
echo.
echo  1. logo-funcorco.png     -^> Convertir a SVG
echo     Opcion A (rapida): Usar https://www.pngtosvg.com/
echo     Opcion B (mejor): Cambiar en los HTML de "logo-funcorco.svg" a "logo-funcorco.png"
echo.
echo  2. program-turtles.webp  -^> Convertir a JPG
echo     Usar: https://cloudconvert.com/webp-to-jpg
echo.
echo  3. program-park.webp     -^> Convertir a JPG
echo     Usar: https://cloudconvert.com/webp-to-jpg
echo.
echo ========================================
echo  ALTERNATIVA RAPIDA (SIN CONVERTIR)
echo ========================================
echo.
echo En VS Code, presiona Ctrl+Shift+H y reemplaza:
echo.
echo   Buscar:  logo-funcorco.svg     Reemplazar: logo-funcorco.png
echo   Buscar:  program-turtles.jpg   Reemplazar: program-turtles.webp
echo   Buscar:  program-park.jpg      Reemplazar: program-park.webp
echo.
echo Esto afecta: index.html, es/index.html, donate-now.html, es/donate-now.html
echo.
echo ========================================
pause