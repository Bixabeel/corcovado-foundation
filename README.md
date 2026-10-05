# Corcovado Foundation — sitio web

Este repositorio contiene dos cosas:

1. **El sitio estático original** (raíz del repositorio: `*.html`, `es/`, `assets/`, `library/`). Es la referencia visual y de contenido aprobada. En la rama `main` es lo que está publicado hoy.
2. **La migración a WordPress** (rama `wordpress-migration`):
   - `wp-content/themes/corcovado-foundation/`: tema clásico/híbrido que reproduce el HTML, el CSS y el JavaScript originales.
   - `wp-content/plugins/corcovado-foundation-core/`: plugin con el contenido administrable (páginas, noticias, eventos, equipo, biblioteca, aliados), idiomas, SEO, formularios seguros, redirecciones y la herramienta de importación.
   - `tools/`: scripts para extraer el contenido del sitio estático, empaquetar los ZIP y comparar el resultado con el original.
   - `docs/`: documentación e informes.

No se usa ningún constructor visual (Elementor, Divi, etc.), ni React, Vue, Tailwind o herramientas de build. El CSS original se copia sin cambios.

## Inicio rápido

```bash
bash tools/package.sh          # genera dist/corcovado-foundation.zip y dist/corcovado-foundation-core.zip
```

Luego, en WordPress (≥ 6.5, PHP ≥ 8.1): subir e activar el plugin, subir y activar el tema, y ejecutar **Corcovado Foundation → Import / Migration** (primero en modo simulación). Pasos completos en [docs/INSTALLATION.md](docs/INSTALLATION.md).

## Documentación

| Documento | Contenido |
|---|---|
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Requisitos, instalación, configuración inicial, puesta en producción |
| [docs/CONTENT-MANAGEMENT.md](docs/CONTENT-MANAGEMENT.md) | Cómo editar páginas, noticias, eventos, equipo, biblioteca, aliados, menús y traducciones |
| [docs/MIGRATION.md](docs/MIGRATION.md) | Cómo funciona la importación, cómo repetirla y qué hace cada opción |
| [docs/SECURITY.md](docs/SECURITY.md) | Medidas de seguridad e informe de seguridad |
| [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md) | Problemas frecuentes y soluciones |
| [docs/SEO-REPORT.md](docs/SEO-REPORT.md) | Informe SEO |
| [docs/TEST-REPORT.md](docs/TEST-REPORT.md) | Pruebas realizadas y resultados reales |
| [docs/VISUAL-DIFFERENCES.md](docs/VISUAL-DIFFERENCES.md) | Comparación visual original vs WordPress |
| [docs/URL-MAP.md](docs/URL-MAP.md) | Mapa de URLs |
| [docs/REDIRECT-MAP.md](docs/REDIRECT-MAP.md) | Mapa de redirecciones |
| [docs/MIGRATED-CONTENT.md](docs/MIGRATED-CONTENT.md) | Contenido migrado y contenido no migrado |
| [docs/ARCHITECTURE-DECISIONS.md](docs/ARCHITECTURE-DECISIONS.md) | Decisiones de arquitectura |
| [docs/DEPENDENCIES.md](docs/DEPENDENCIES.md) | Dependencias |
| [WORDPRESS-MIGRATION-AUDIT.md](WORDPRESS-MIGRATION-AUDIT.md) | Auditoría previa del sitio estático |
