# Mapa de URLs

Generado con `python3 tools/url-report.py http://localhost:8080` contra el entorno de pruebas (la columna Estado es la respuesta HTTP real). En producción el dominio es `https://corcovadofoundation.org`.

Las URLs no llevan `.html` ni barra final, salvo la portada en español (`/es/`), que es la forma canónica de la página raíz española (`/es` redirige a `/es/`).


| Static file | WordPress URL | Language | Status |
|---|---|---|---|
| `index.html` | `/` | EN | 200 |
| `about-us.html` | `/about-us` | EN | 200 |
| `allies.html` | `/allies` | EN | 200 |
| `contact-us.html` | `/contact-us` | EN | 200 |
| `donate-now.html` | `/donate-now` | EN | 200 |
| `ecosystem-restoration.html` | `/ecosystem-restoration` | EN | 200 |
| `environmental-education.html` | `/environmental-education` | EN | 200 |
| `events-calendar.html` | `/events-calendar` | EN | 200 |
| `national-park-support.html` | `/national-park-support` | EN | 200 |
| `news.html` | `/news` | EN | 200 |
| `our-impact.html` | `/our-impact` | EN | 200 |
| `programs.html` | `/programs` | EN | 200 |
| `sea-turtles.html` | `/sea-turtles` | EN | 200 |
| `sponsors.html` | `/sponsors` | EN | 200 |
| `virtual-library.html` | `/virtual-library` | EN | 200 |
| `volunteering.html` | `/volunteering` | EN | 200 |
| `es/index.html` | `/es/` | ES | 200 |
| `es/about-us.html` | `/es/about-us` | ES | 200 |
| `es/allies.html` | `/es/allies` | ES | 200 |
| `es/contact-us.html` | `/es/contact-us` | ES | 200 |
| `es/donate-now.html` | `/es/donate-now` | ES | 200 |
| `es/ecosystem-restoration.html` | `/es/ecosystem-restoration` | ES | 200 |
| `es/environmental-education.html` | `/es/environmental-education` | ES | 200 |
| `es/events-calendar.html` | `/es/events-calendar` | ES | 200 |
| `es/national-park-support.html` | `/es/national-park-support` | ES | 200 |
| `es/news.html` | `/es/news` | ES | 200 |
| `es/our-impact.html` | `/es/our-impact` | ES | 200 |
| `es/programs.html` | `/es/programs` | ES | 200 |
| `es/sea-turtles.html` | `/es/sea-turtles` | ES | 200 |
| `es/sponsors.html` | `/es/sponsors` | ES | 200 |
| `es/biblioteca-virtual.html` | `/es/biblioteca-virtual` | ES | 200 |
| `es/volunteering.html` | `/es/volunteering` | ES | 200 |


## Otras URLs

| URL | Contenido |
|---|---|
| `/news/{slug}` | Noticia en inglés |
| `/es/news/{slug}` | Noticia en español |
| `/wp-sitemap.xml` | Sitemap |
| `/robots.txt` | robots.txt (generado por WordPress) |
| `/wp-json/cf/v1/form-token`, `/wp-json/cf/v1/contact`, `/wp-json/cf/v1/volunteer` | Formularios (no indexables) |
| Cualquier otra ruta inexistente | 404 con el contenido de la página privada «Page not found» / «Página no encontrada» según el idioma de la ruta |
