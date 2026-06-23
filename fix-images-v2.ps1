# =========================================================
# Script mejorado para corregir rutas de imágenes
# Ejecutar desde: D:\corcovado\Corcovado_Auditpadre\
# =========================================================

$base = "D:\corcovado\Corcovado_Auditpadre"

# Configuración por archivo
$config = @(
    @{
        Path       = "$base\assets\js\team-data.js"
        TargetPath = "assets/img/"
        Label      = "INGLÉS (team-data.js)"
    },
    @{
        Path       = "$base\assets\js\team-data-es.js"
        TargetPath = "../assets/img/"
        Label      = "ESPAÑOL (team-data-es.js)"
    }
)

foreach ($item in $config) {
    $ruta = $item.Path
    $rutaCorrecta = $item.TargetPath
    Write-Host "`n========================================" -ForegroundColor Yellow
    Write-Host "Procesando: $($item.Label)" -ForegroundColor Cyan
    Write-Host "Archivo: $ruta" -ForegroundColor Gray

    if (-not (Test-Path $ruta)) {
        Write-Host "✗ ERROR: El archivo no existe" -ForegroundColor Red
        continue
    }

    # Leer con encoding UTF8 sin BOM
    $contenido = [System.IO.File]::ReadAllText($ruta, [System.Text.Encoding]::UTF8)
    $original = $contenido

    # Contar ocurrencias antes de reemplazar
    $absolutas = ([regex]::Matches($contenido, "'/assets/img/")).Count
    $relativasSub = ([regex]::Matches($contenido, "'\.\./assets/img/")).Count
    $relativasRaiz = ([regex]::Matches($contenido, "'assets/img/")).Count

    Write-Host "  Rutas absolutas (/assets/img/):      $absolutas" -ForegroundColor White
    Write-Host "  Rutas relativas (../assets/img/):    $relativasSub" -ForegroundColor White
    Write-Host "  Rutas relativas (assets/img/):       $relativasRaiz" -ForegroundColor White

    # Reemplazar TODOS los tipos de rutas por la correcta
    # 1. Rutas absolutas → relativa correcta
    $contenido = $contenido -replace "'/assets/img/", "'$rutaCorrecta"
    # 2. Rutas relativas con ../ → relativa correcta (si el archivo no la necesita)
    if ($rutaCorrecta -eq "assets/img/") {
        $contenido = $contenido -replace "'\.\./assets/img/", "'assets/img/"
    }
    # 3. Rutas relativas sin ../ → relativa correcta (si el archivo la necesita)
    if ($rutaCorrecta -eq "../assets/img/") {
        $contenido = $contenido -replace "(?<!\.\.)(?<!/)'assets/img/", "'../assets/img/"
    }

    if ($contenido -eq $original) {
        Write-Host "✓ Sin cambios necesarios (ya está correcto)" -ForegroundColor Green
    } else {
        # Guardar con UTF8 sin BOM
        $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
        [System.IO.File]::WriteAllText($ruta, $contenido, $utf8NoBom)
        Write-Host "✓ Archivo corregido y guardado" -ForegroundColor Green
    }

    # Verificación final
    $verificacion = [System.IO.File]::ReadAllText($ruta, [System.Text.Encoding]::UTF8)
    $patronCorrecto = "'$rutaCorrecta"
    $totalCorrectas = ([regex]::Matches($verificacion, [regex]::Escape($patronCorrecto))).Count
    Write-Host "  Total de rutas correctas ahora:    $totalCorrectas" -ForegroundColor Cyan
}

Write-Host "`n========================================" -ForegroundColor Yellow
Write-Host "Proceso completado." -ForegroundColor Green
Write-Host "Recuerda: Ctrl + F5 en el navegador para limpiar caché." -ForegroundColor Magenta