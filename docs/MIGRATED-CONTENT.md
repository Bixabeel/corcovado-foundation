# Contenido migrado y no migrado

Fuente: `wp-content/plugins/corcovado-foundation-core/migration/data/content.json`, generado desde el HTML de `main` (commit `82c057c`). Cantidades comprobadas en el entorno de pruebas después de importar.

## Migrado

| Contenido | Cantidad | Destino en WordPress |
|---|---|---|
| Páginas en inglés | 16 | Páginas (`/…`) con «Contenido de la página» |
| Páginas en español | 16 | Páginas hijas de `es` (`/es/…`) |
| Páginas 404 | 2 | Páginas privadas «Page not found» / «Página no encontrada» |
| Noticias | 6 (3 EN + 3 ES) | Noticias (`cf_news`), enlazadas como traducciones |
| Eventos | 6 (3 EN + 3 ES) | Eventos (`cf_event`) |
| Miembros del equipo | 48 (24 EN + 24 ES), con biografías de `team-data.js` / `team-data-es.js` | Equipo (`cf_team`) en 8 grupos (4 por idioma) |
| Recursos de la biblioteca | 16 (8 EN + 8 ES) | Biblioteca (`cf_resource`) en 12 categorías (6 por idioma) |
| Aliados / patrocinadores | 9 logos | Aliados (`cf_partner`), comunes a ambos idiomas |
| Imágenes, SVG y PDF | 64 archivos | Biblioteca de medios, sin recomprimir |
| Menús | Principal EN/ES y 3 columnas del pie por idioma | Apariencia → Menús |
| Textos del encabezado y pie | EN/ES | Personalizador → Header & footer |
| Ajustes | ID de Classy `568425`, correo `info@corcovadofoundation.org` | Corcovado Foundation → Ajustes |
| Títulos, descripciones e imagen para compartir | 34 páginas | Caja «Buscadores y redes sociales» |
| Relaciones de traducción | Páginas, noticias, eventos, equipo, recursos, categorías | Caja «Idioma y traducción» |

Noticias: Record-breaking sea turtle nesting season in Punta Mala · Local schools lead the way in environmental education · New trail infrastructure improves visitor experience (y sus versiones en español). El cuerpo de cada noticia es el texto de su tarjeta; el sitio original no tenía páginas de noticia. **No se añadieron noticias ni textos nuevos.**

Eventos: Sea Turtle Nesting Season · Community Reforestation Day · Educational Workshops (y en español).

Aliados: Costa Rica découverte · Abundia · Águila de Osa · Amerisol Marketing Services · Jinca Foods · Morpho Costa Rica · Planetary Responsibility · Sí Como No Resort & Wildlife Refuge · Regenwald Österreich.

### Elementos de diseño (en el tema, no en Medios)

`logo.png`, `logo1.png`, `logo-border.webp`, `logo-funcorco.svg`, `logo_en.png`, `team-placeholder-generic.svg`, `resource-placeholder.svg`, `styles-20260929.css`, `team.css`, `main.js` (parte de animaciones y menú), `team.js`, `donation-embed.js`.

## No migrado

| Elemento | Motivo |
|---|---|
| `events-calendar.htm` | Obsoleto (redirección antigua). Eliminado; `/events-calendar.htm` redirige a `/events-calendar` |
| `assets/css/styles.css` | No lo usaba ninguna página |
| `assets/js/team-data.js`, `team-data-es.js` | Su contenido pasó a Equipo; el tema genera el mismo objeto `TEAM_MEMBERS` desde la base de datos |
| Bloques 2 y 3 de `main.js` (formularios `mailto:`) | Sustituidos por envío seguro al servidor (`forms.js`) con los mismos mensajes |
| Script en línea «multi-step form» de `volunteering.html` | Código muerto: los elementos que busca no existen |
| `assets/img/Mask-General.webp`, `sponsor-placeholder-1.webp`, `volunteer-internship.webp` | No los usaba ninguna página |
| `assets/img/REQUESTED_TEAM_PHOTOS.txt` | Nota interna obsoleta |
| `assets/volunteer-guide.pdf` | No enlazado (idéntico a los demás PDF de marcador) |
| `library/catalogo-especies.pdf`, `native-species-catalog.pdf`, `corcovado-foundation-resource.pdf` | No enlazados en ninguna página |
| `library/Corcovado Nuevo Contenido.docx` | Documento de trabajo interno (9 MB), no enlazado |
| `sitemap.xml`, `robots.txt`, `_headers` | Los genera WordPress / el plugin |
| `master` (rama) | Versión antigua sin relación; no se toca |

## Contenido que la Fundación debe revisar (se migró tal cual)

- **PDF de marcador de posición**: los 18 PDF importados son el mismo archivo de 71 KB. Los datos que muestran las tarjetas («PDF | EN | 4.2 MB», «Published: March 2026») no corresponden al archivo.
- **«Financial Audit 2025»** en Our Impact descarga `financial-audit-2024.pdf`.
- **Instagram**: el enlace es `https://instagram.com` (portada genérica, no una cuenta).
- **Equipo**: en 14 de 24 miembros en inglés, el nombre o cargo de la ventana de perfil difiere del de la tarjeta (p. ej. «William MacLachlan / President» vs «Bill MacLachlan / President of the Board»). Se conservaron ambos.
- **Fechas de noticias**: no existían; se importaron con fecha 29/09/2026 (para el orden) y la fecha oculta.
- **Eventos**: no tenían imagen, hora ni lugar estructurados; los campos existen y están vacíos.
