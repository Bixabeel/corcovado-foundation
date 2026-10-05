# Informe SEO

Comparación automática de la `<head>` de las 34 páginas originales con las de WordPress (entorno de pruebas, `http://localhost:8080`). En producción el dominio será el de `home_url()`: `https://corcovadofoundation.org`.

## Resultado por etiqueta

| Etiqueta | Original | WordPress | Resultado |
|---|---|---|---|
| `<title>` | Único por página | Mismo texto (campo «Título en resultados de búsqueda») | **Idéntico en 34/34** |
| `meta description` | Única por página | Mismo texto (campo «Descripción») | **Idéntico en 34/34** |
| `meta robots` | `index,follow,max-image-preview:large`; 404: `noindex,follow` | `index, follow, max-image-preview:large`; 404: `noindex, follow` | Equivalente (WordPress separa con «, ») |
| `rel=canonical` | `https://corcovadofoundation.abundia.io/…html` | URL de WordPress sin `.html` en el dominio actual | **Corregido**: dominio y URL definitivos |
| `hreflang` en / es / x-default | Las 3 en cada página | Las 3 en cada página, desde la relación de traducción; x-default = inglés | Igual estructura, dominio corregido |
| `og:title`, `og:description`, `og:type`, `og:url`, `og:locale` (`en_US`/`es_CR`), `og:locale:alternate`, `og:site_name` | Sí | Sí | Mismos valores salvo dominio |
| `og:image`, `twitter:image` | `hero-corcovado.webp` en todas las páginas, dominio antiguo | Imagen para compartir de la página (importada: `hero-corcovado.webp`, como el original) → imagen destacada → imagen predeterminada de Ajustes | Dominio corregido |
| `twitter:card` | `summary_large_image` | `summary_large_image` | Idéntico |
| `<html lang>` | `en` / `es` | `en` / `es` | Idéntico |
| JSON-LD | No había | Opcional (desactivado por defecto) | Sin cambio por defecto |
| Páginas 404 | `noindex,follow`, título y descripción propios, sin canonical | Igual, con código HTTP 404 | Equivalente (ver nota) |

Nota sobre 404: el `es/404.html` original tenía `hreflang="en"` hacia el 404 inglés. En WordPress las páginas 404 no imprimen `hreflang` (una página `noindex` no lo necesita).

## Dominio antiguo

`corcovadofoundation.abundia.io` aparecía 257 veces en el sitio estático. En WordPress **no aparece en ningún archivo del tema ni del plugin**: todas las URLs se generan con `home_url()` / `get_permalink()`. `fundacioncorcovado.org` tampoco aparece. El dominio se fija una sola vez en Ajustes → Generales.

```bash
grep -rn "abundia.io/\|fundacioncorcovado" wp-content/   # solo el enlace de crédito «Abundia Tech Design» (https://abundia.io/), que es contenido del pie
```

## Sitemap y robots.txt

- Un solo sistema de sitemap: el nativo de WordPress (`/wp-sitemap.xml`). `/sitemap.xml` redirige ahí con un 301.
- Incluye páginas publicadas (EN y ES) y noticias. Excluye usuarios y las páginas 404 privadas.
- Si se instala un plugin de SEO, su sitemap reemplaza al nativo (el plugin de la Fundación desactiva sus etiquetas automáticamente).
- `robots.txt` (generado por WordPress): conserva `Disallow: /library/` del original y añade `Sitemap: https://corcovadofoundation.org/wp-sitemap.xml`.
- En el entorno de pruebas aparecían en el sitemap «Sample Page», «Hello world!» y la categoría «Uncategorized»: son el contenido de ejemplo de una instalación nueva de WordPress. Al borrar ese contenido desaparecen del sitemap.

## URLs

Todas las URLs pedidas se conservan sin `.html` (ver [URL-MAP.md](URL-MAP.md)). Las antiguas `.html` y los PDF redirigen con un único 301 (ver [REDIRECT-MAP.md](REDIRECT-MAP.md)).

## Cambios de contenido que afectan al SEO

Ninguno: títulos, descripciones, encabezados (`h1`–`h4`) y textos son los originales. Las noticias ahora tienen página propia (`/news/slug`), cuando antes «Read full story» apuntaba a `#`.

## Pendiente en producción

- Desmarcar «Disuade a los motores de búsqueda».
- Dar de alta `https://corcovadofoundation.org/` en Google Search Console y enviar el sitemap.
- Redirigir `corcovadofoundation.abundia.io` y `fundacioncorcovado.org` a `https://corcovadofoundation.org` conservando la ruta (un salto).
