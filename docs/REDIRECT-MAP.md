# Mapa de redirecciones

## Dentro de WordPress (plugin)

Todas son **301 en un solo salto** hacia la URL final, y solo actúan cuando la ruta no existe (WordPress daría 404), así que nunca tapan una página real. Se desactivan en Ajustes → «Redirecciones de .html antiguas». La consulta (`?…`) se conserva.

Resultado real en el entorno de pruebas (`python3 tools/url-report.py`):


| Old URL | Redirects to | Status |
|---|---|---|
| `/index.html` | `/` | 301 |
| `/about-us.html` | `/about-us` | 301 |
| `/allies.html` | `/allies` | 301 |
| `/contact-us.html` | `/contact-us` | 301 |
| `/donate-now.html` | `/donate-now` | 301 |
| `/ecosystem-restoration.html` | `/ecosystem-restoration` | 301 |
| `/environmental-education.html` | `/environmental-education` | 301 |
| `/events-calendar.html` | `/events-calendar` | 301 |
| `/national-park-support.html` | `/national-park-support` | 301 |
| `/news.html` | `/news` | 301 |
| `/our-impact.html` | `/our-impact` | 301 |
| `/programs.html` | `/programs` | 301 |
| `/sea-turtles.html` | `/sea-turtles` | 301 |
| `/sponsors.html` | `/sponsors` | 301 |
| `/virtual-library.html` | `/virtual-library` | 301 |
| `/volunteering.html` | `/volunteering` | 301 |
| `/es/index.html` | `/es/` | 301 |
| `/es/about-us.html` | `/es/about-us` | 301 |
| `/es/allies.html` | `/es/allies` | 301 |
| `/es/contact-us.html` | `/es/contact-us` | 301 |
| `/es/donate-now.html` | `/es/donate-now` | 301 |
| `/es/ecosystem-restoration.html` | `/es/ecosystem-restoration` | 301 |
| `/es/environmental-education.html` | `/es/environmental-education` | 301 |
| `/es/events-calendar.html` | `/es/events-calendar` | 301 |
| `/es/national-park-support.html` | `/es/national-park-support` | 301 |
| `/es/news.html` | `/es/news` | 301 |
| `/es/our-impact.html` | `/es/our-impact` | 301 |
| `/es/programs.html` | `/es/programs` | 301 |
| `/es/sea-turtles.html` | `/es/sea-turtles` | 301 |
| `/es/sponsors.html` | `/es/sponsors` | 301 |
| `/es/biblioteca-virtual.html` | `/es/biblioteca-virtual` | 301 |
| `/es/volunteering.html` | `/es/volunteering` | 301 |
| `/events-calendar.htm` | `/events-calendar` | 301 |
| `/es/virtual-library` | `/es/biblioteca-virtual` | 301 |
| `/es` | `/es/` | 301 |
| `/sitemap.xml` | `/wp-sitemap.xml` | 301 |
| `/library/annual-report-2025.pdf` | `/wp-content/uploads/2026/10/annual-report-2025.pdf` | 301 |
| `/library/auditoria-financiera-2024.pdf` | `/wp-content/uploads/2026/10/auditoria-financiera-2024.pdf` | 301 |
| `/library/community-handbook.pdf` | `/wp-content/uploads/2026/10/community-handbook.pdf` | 301 |

Reglas:

| Patrón | Destino |
|---|---|
| `/{slug}.html`, `/{slug}.htm` | `/{slug}` (`index` → `/`) |
| `/es/{slug}.html` | `/es/{slug}` (`index` → `/es/`) |
| `/es/virtual-library` | `/es/biblioteca-virtual` (el slug español es distinto) |
| `/library/{archivo}.pdf` | Archivo actual del recurso de la Biblioteca importado de ese PDF; si no hay recurso, el archivo importado en la Biblioteca de medios |
| `/assets/img/{archivo}` y otros archivos originales | El archivo importado en la Biblioteca de medios |
| `/es` | `/es/` (WordPress + plugin) |
| `/sitemap.xml` | `/wp-sitemap.xml` (WordPress) |

Archivos **sin** redirección (no se migran, dan 404): `library/catalogo-especies.pdf`, `library/native-species-catalog.pdf`, `library/corcovado-foundation-resource.pdf` (no estaban enlazados) y `library/Corcovado Nuevo Contenido.docx` (documento de trabajo interno).

## Fuera de WordPress (DNS / hosting / Cloudflare)

WordPress no redirige dominios: hacerlo en dos sitios crea cadenas y bucles. Configurar en el proveedor, conservando ruta y consulta, en **un solo salto** al destino final:

| Origen | Destino | Tipo |
|---|---|---|
| `http://corcovadofoundation.org/*` | `https://corcovadofoundation.org/*` | 301 |
| `https://www.corcovadofoundation.org/*` (si existe) | `https://corcovadofoundation.org/*` | 301 |
| `http(s)://fundacioncorcovado.org/*` y `www.` | `https://corcovadofoundation.org/*` | 301 |
| `http(s)://corcovadofoundation.abundia.io/*` | `https://corcovadofoundation.org/*` | 301 |

Ejemplo de cadena evitada: `http://fundacioncorcovado.org/about-us.html` debería ir a `https://corcovadofoundation.org/about-us.html` (proveedor) y de ahí a `/about-us` (WordPress): son dos saltos inevitables cuando cambian dominio y ruta a la vez. Para dejarlo en uno, se puede añadir en el proveedor la regla `*.html → sin .html` para los dominios antiguos; no es necesario para el SEO.

Ejemplo con Cloudflare (Redirect Rules → Single Redirects, «Dynamic»):

```
When: (http.host in {"fundacioncorcovado.org" "www.fundacioncorcovado.org" "corcovadofoundation.abundia.io"})
Then: concat("https://corcovadofoundation.org", http.request.uri.path)   status 301, preserve query string
```
