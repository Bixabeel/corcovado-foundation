# Migración

## Flujo

```
sitio estático (HTML)  ──tools/extract-content.py──▶  migration/data/content.json
assets/, library/      ──tools/package.sh──────────▶  migration/source/ (dentro del ZIP del plugin)
                                                       │
                       Corcovado Foundation → Import / Migration
                                                       ▼
                                                  WordPress
```

1. `tools/extract-content.py` lee las 34 páginas HTML y genera `content.json`: páginas (cada sección como plantilla con huecos editables), noticias, eventos, equipo, biblioteca, aliados, menús y textos del pie. No modifica el HTML original. `--check` solo valida.
2. `tools/package.sh` ejecuta el extractor, copia las imágenes y PDF referenciados a `migration/source/` y crea los ZIP.
3. El importador (`migration/class-cf-importer.php`) crea el contenido en WordPress.

## Orden de importación

medios → aliados → categorías de recursos y grupos del equipo → páginas (EN, inicio ES, páginas ES) → noticias → eventos, equipo, recursos → relaciones de traducción → ajustes → menús y textos del tema → configuración del sitio.

## Garantías

- **Simulación (dry run)**: recorre todo y escribe el registro, sin escribir nada en la base de datos ni en `uploads/`.
- **Idempotente**: cada elemento guarda su clave de origen (`_cf_source_key`). Si ya existe, se omite. Probado: una segunda ejecución creó 0 elementos y omitió 211.
- **No destructivo**: el importador nunca borra nada. La opción «Actualizar los elementos ya importados» sobrescribe solo los elementos con clave de origen (los creados a mano no se tocan) y está desactivada por defecto.
- **Registro**: cada ejecución guarda su registro (se ve en la misma pantalla, «Última simulación» / «Última importación»).
- **Medios sin recompresión**: el archivo original se copia tal cual a `uploads/`. Se desactiva el umbral de 2560 px de WordPress durante la importación para no generar `-scaled`. WordPress sí genera sus tamaños intermedios (miniaturas), pero el sitio usa el original.
- `tort.webp` es un PNG con extensión incorrecta: se importa como `tort.png` (mismo contenido).
- Los SVG del contenido se permiten **solo durante la importación** (WordPress los bloquea por defecto y así sigue).

## Opciones

| Opción | Qué hace | Por defecto |
|---|---|---|
| Crear menús y textos del encabezado/pie | Crea los menús EN/ES y los textos del Personalizador solo si no existen | No |
| Configurar el sitio | Portada = Inicio, enlaces `/%postname%`, comentarios cerrados | No |
| Actualizar los elementos ya importados | Reescribe los elementos importados con el contenido original | No |

## Correcciones aplicadas durante la extracción

Solo se corrigieron fallos técnicos (enlaces rotos y HTML mal cerrado), sin cambiar diseño ni textos:

| Página | Problema | Corrección |
|---|---|---|
| `volunteering.html`, `es/volunteering.html` | El botón del hero apuntaba a `#volunteer-form`, que no existe | `#formulario-voluntario` |
| `es/donate-now.html` | «Volver al inicio» llevaba al inicio en inglés | `/es/` |
| `es/index.html` | El CTA final estaba dentro del carrusel de logos (etiqueta sin cerrar) y nunca se veía | Se muestra como sección propia después de los logos |

## Repetir la importación

- Para importar contenido nuevo del HTML estático: actualizar el HTML, ejecutar `bash tools/package.sh`, subir el plugin nuevo y ejecutar la importación (sin «Actualizar»): solo se crea lo que no existe.
- Para restaurar un elemento importado a su contenido original: borrarlo (o moverlo a la papelera y vaciarla) y volver a importar, o usar «Actualizar» (afecta a **todos** los elementos importados).

## Lo que no hace

- No borra las páginas de ejemplo de WordPress («Sample Page», «Hello world!»). Borrarlas a mano en un sitio nuevo.
- No configura SMTP, DNS ni redirecciones de dominio.
