# Auditoría previa a la migración a WordPress — Corcovado Foundation

Fecha de la auditoría: 2026-10-05
Rama auditada: `main` (commit `82c057c`, "Actualiza sitio Corcovado")
Rama de trabajo de la migración: `wordpress-migration`

Este documento describe **solo lo que se puede demostrar leyendo el repositorio**. Cada hallazgo indica el archivo donde se observa. Donde algo es una interpretación o una recomendación, se indica explícitamente.

---

## 1. Estructura encontrada

Sitio estático puro (HTML + CSS + JavaScript vanilla), sin sistema de build, sin dependencias de npm/composer, sin tests y sin CI. El despliegue es en **Cloudflare Pages** (archivo `_headers` con sintaxis de Cloudflare Pages, comentario "Fingerprinted CSS" en `_headers`).

```
/                      → 16 páginas en inglés (*.html) + 404.html + events-calendar.htm (obsoleto)
/es/                   → 16 páginas en español + es/404.html
/assets/css/           → styles-20260929.css (activo), styles.css (antiguo, no usado), team.css
/assets/js/            → main.js, team.js, team-data.js, team-data-es.js, donation-embed.js
/assets/img/           → 57 archivos (imágenes, logos, SVG, un .txt)
/assets/volunteer-guide.pdf  → no referenciado
/library/              → 21 PDF + 1 .docx de trabajo (9 MB)
/robots.txt, /sitemap.xml, /_headers, /.gitignore
```

Ramas remotas: `main` (fuente de verdad) y `master` (versión antigua, junio 2026, historia no relacionada; no se toca).

## 2. Archivos principales

| Archivo | Rol | Evidencia |
|---|---|---|
| `assets/css/styles-20260929.css` | **CSS activo** (1 767 líneas). Todas las páginas lo enlazan. | `<link href="assets/css/styles-20260929.css">` en las 34 páginas |
| `assets/css/styles.css` | Versión anterior. **No la enlaza ninguna página.** Difiere solo en las 14 líneas finales (regla "Deployment-safe header CTA"). | `diff styles.css styles-20260929.css` |
| `assets/css/team.css` | Estilos de la sección Equipo y del modal. Solo en `about-us.html` y `es/about-us.html`. | |
| `assets/js/main.js` | Header sticky, menú móvil, reveal, contadores, formulario de voluntariado (mailto + copiar), formulario de contacto (mailto). Cargado en todas las páginas. | |
| `assets/js/team.js` + `team-data.js` / `team-data-es.js` | Modal del equipo; datos de biografías en objetos JS. | |
| `assets/js/donation-embed.js` | Carga el SDK de Classy (`https://sdk.classy.org/embedded-giving.js`) e inicializa la campaña `568425`. Solo en las páginas de donación. | |

## 3. Páginas

32 páginas reales + 2 páginas 404 + 1 archivo obsoleto.

| Inglés | Español | Título EN | Notas |
|---|---|---|---|
| `index.html` | `es/index.html` | Corcovado Foundation - Guardian of Biodiversity | Contenidos EN/ES **distintos** (ES tiene 6 estadísticas, EN 4; textos de programas diferentes). |
| `about-us.html` | `es/about-us.html` | About Us - Corcovado Foundation | Equipo con modal. Único par que carga Google Fonts y `team.css`. |
| `allies.html` | `es/allies.html` | Our Allies & Partners | ES **no** tiene la sección "Meet Our Partners" (Partner 1…6). |
| `contact-us.html` | `es/contact-us.html` | Contact Us | Formulario de contacto (mailto). |
| `donate-now.html` | `es/donate-now.html` | Donate Now | Classy embebido. |
| `ecosystem-restoration.html` | `es/ecosystem-restoration.html` | Ecosystem Restoration | Textos EN/ES no equivalentes (son redacciones distintas). |
| `environmental-education.html` | `es/environmental-education.html` | Environmental Education | Ídem. |
| `events-calendar.html` | `es/events-calendar.html` | Events Calendar | 3 eventos estáticos. |
| `national-park-support.html` | `es/national-park-support.html` | National Park Support | EN y ES con cifras diferentes. |
| `news.html` | `es/news.html` | News & Stories | 3 historias estáticas; "Read full story" apunta a `#`. |
| `our-impact.html` | `es/our-impact.html` | Our Impact \| Corcovado Foundation | 3 descargas de PDF. |
| `programs.html` | `es/programs.html` | Our Programs | ES es una página mucho más larga (incluye el detalle de los 4 programas). |
| `sea-turtles.html` | `es/sea-turtles.html` | Sea Turtle Conservation | Hero ES usa `tort.webp`, EN `3.webp`. |
| `sponsors.html` | `es/sponsors.html` | Sponsors | Niveles, categorías vacías, grid de logos. |
| `virtual-library.html` | `es/biblioteca-virtual.html` | Virtual Library | 8 recursos por idioma. **Slug distinto** entre idiomas. |
| `volunteering.html` | `es/volunteering.html` | Volunteering | Formulario de voluntariado (mailto + copiar). |
| `404.html` | `es/404.html` | Page Not Found | `noindex,follow`, sin canonical. |
| `events-calendar.htm` | — | "Redirecting…" | **Obsoleto** (meta refresh a `events-calendar.html`). Se elimina en la rama de migración. |

Importante: las versiones EN y ES **no son traducciones literales** en muchas páginas. La migración conserva cada idioma con su propio contenido; no se "sincronizan" textos.

## 4. Componentes identificados

| Componente | Clases | Observaciones |
|---|---|---|
| Header sticky | `.site-header`, `.header-shell`, `.brand` | El logo **varía por página**: `logo.png` (mayoría), `logo1.png` (inicio; idéntico byte a byte a `logo.png`), `logo-funcorco.svg` (About), `logo_en.png` (Donate). |
| Navegación | `.site-nav > a.nav-link`, `.nav-top` | Los enlaces son hijos directos de `<nav>` (sin `ul/li`). El CSS depende de `.site-nav > a`. Activo: `aria-current="page" class="nav-link active"` solo en páginas de primer nivel. |
| Menú móvil | `.nav-toggle`, `.site-nav.open` | Panel lateral bajo 980 px; cierre con Escape y clic fuera (`main.js`). |
| Selector de idioma | `.lang-switch`, `.lang-link.is-active` | Enlaza a la página equivalente. Fallos: `es/donate-now.html` "Volver al inicio" enlaza a `../index.html` (inicio EN); el footer de `allies.html` enlaza "English/Spanish" a `allies.html`. |
| Breadcrumb | `.breadcrumb`, `.sep` | En las 4 páginas de programa, News, Events, Library. |
| Hero | `.hero`, `.hero-media`, `.hero-overlay`, `.hero-grid`, `.hero-copy`, `.eyebrow`, `.hero-lead`, `.hero-actions` | Tres variantes de contenedor: `container hero-inner`, `container shell`, `shell hero-inner`. Animación `heroPremium`. |
| Encabezado de sección | `.section-heading` | A veces con `style` inline (centrado). |
| Tarjetas | `.trust-card`, `.program-card`, `.story-card`, `.vol-card`, `.contact-card`, `.resource-card`, `.stat-card`, `.sponsor-category-card`, `.team-card` | |
| Estadísticas / contadores | `[data-count]`, `data-prefix`, `data-suffix`, `data-format` | Animados por `main.js` (IntersectionObserver). `es/programs.html` tiene stats estáticas sin `data-count`. |
| CTA final | `.final-cta`, `.cta-row` | Con o sin `<div>` interno envolviendo h2+p. |
| Split | `.split-grid-wrap`, `.split-copy`, `.split-media` | |
| Logos | `.logo-marquee` + `.logo-slide` (animado, lista duplicada), `.logo-grid` + `.logo-grid-item` | |
| FAQ | `.faq-grid`, `details.faq-item` | Voluntariado. |
| Formularios | `.contact-form`, `.form-row`, `.field-grid`, `.field-stack`, `.form-status` | |
| Donación | `.donation-panel`, `.donation-flow`, `.classy-*`, `.trust-band-inline`, `.donation-summary` | |
| Equipo + modal | `.team-section`, `.team-category`, `.team-grid`, `.team-card`, `.team-modal` | Accesible: `role="button"`, `tabindex="0"`, Enter/Espacio, focus trap, Escape, retorno del foco, `aria-modal`. |
| Footer | `.site-footer`, `.footer-grid`, `.footer-bottom` | **15 variantes** (ver §14). |
| Reveal | `.reveal`, `.reveal-delay-1…5`, `.is-visible` | `prefers-reduced-motion` respetado en CSS y JS. |

## 5. JavaScript

| Archivo | Funcionalidad | Decisión para WordPress |
|---|---|---|
| `main.js` bloque 1 | Header `.scrolled`, menú móvil, reveal, contadores con easing, reduced-motion | **Se conserva sin cambios** en el Theme. |
| `main.js` bloque 2 | Formulario de voluntariado: construye un `mailto:` y botón "Copiar aplicación" | Se mantiene el botón Copiar; el envío pasa a servidor (ver §8). |
| `main.js` bloque 3 | Formulario de contacto: validación y `mailto:` | Se reemplaza por envío seguro al servidor con los mismos mensajes de estado. |
| `team.js` | Modal del equipo | Se conserva; solo cambia la ruta del placeholder (era relativa `../assets/...`). |
| `team-data.js`, `team-data-es.js` | Datos del equipo embebidos en JS | Se convierten en contenido de WordPress; el Theme genera el mismo objeto `TEAM_MEMBERS` desde la base de datos. |
| `donation-embed.js` | Classy SDK, reintentos, timeout 15 s, fallback | Se conserva; el ID de campaña pasa a ser un ajuste. |
| `<script>` inline en `volunteering.html` y `es/volunteering.html` | "Multi-step form": busca `#volunteerForm`, `.form-step`, `.progress-step` | **Código muerto**: ninguno de esos elementos existe en la página. No se migra. |

Contenido embebido en JavaScript: biografías del equipo (24 miembros × 2 idiomas), mensajes de los formularios (EN/ES), correo de destino `info@corcovadofoundation.org`, ID de campaña Classy `568425`.

**Equipo, inconsistencias reales entre tarjeta y modal (EN):** en 14 de 24 miembros, el nombre o el cargo del modal (`team-data.js`) difiere del de la tarjeta (`about-us.html`). Ejemplos: tarjeta "William MacLachlan / President" vs modal "Bill MacLachlan / President of the Board"; tarjeta "Bradd Jhonson / Treasurer" vs modal "Bradd Johnson / Board Member"; tarjeta "Terri Peterson / Secretary" vs modal "Advisory Committee". En español no hay diferencias. La migración conserva ambos valores (campos opcionales "nombre/cargo en el modal") para no cambiar contenido.

## 6. CSS

- CSS activo: `assets/css/styles-20260929.css`. Variables en `:root` (`--forest #173528`, `--olive`, `--muted`, radios `--r-*`, `--max: 1180px`, sombras).
- Breakpoints: 1200, 980, 900, 768, 640 (team), 560, 480 px; `prefers-reduced-motion`; `print`.
- Tipografías declaradas: Inter y "Cormorant Garamond". **Solo `about-us.html` y `es/about-us.html` cargan Google Fonts.** En el resto de páginas el navegador usa las alternativas (system-ui y Georgia). Es el comportamiento visual actual.
- **Errores tipográficos** dentro del CSS activo (espacios dentro de palabras). El navegador ignora esas declaraciones, por lo que **hoy no tienen efecto**:

| Línea | Texto | Efecto actual |
|---|---|---|
| 757 | `.flow-hea d p` | selector no coincide (sin efecto visible) |
| 776 | `padd ing-top` | `.flow-section` (no usada) |
| 794 | `border: 1px so lid` | `.amount-btn` (no usada) |
| 799 | `border-color .18s e ase` | transición inválida (no usada) |
| 810 | `rgba (23,53,40,.12)` | sombra inválida (no usada) |
| 814 | `"Cormorant Garamond "` | fuente con espacio final (no usada) |
| 828 | `displa y: flex` | `.route-meta` (no usada) |
| 855 | `color: r gba(...)` | `.donation-summary h4` hereda blanco en vez del 78 % |
| 871 | `.copy-b tn {` | **el botón "Copy application" queda sin estilo** (botón nativo del navegador) |
| 880 | `.copy-btn: hover` | ídem |
| 887 | `dis play: grid` | `.field-stack` no es grid (no hay `gap` de 8 px entre label y campo) |
| 893 | `te xtarea`, `input :focus` | selectores parcialmente inválidos |
| 909 | `linear-gradie nt` | `.support-card` **sin fondo** |
| 1428 | `r gba(0,0,0,.15)` | sombra del menú móvil no se aplica |
| 1435 | `align-items: fl ex-start` | menú móvil mantiene `align-items: center` |
| 1446 | `flex-direct ion` | `.nav-links` (no usada) |
| 1465 | `tr ansparent` | `.drop-menu` (no usada) |
| 1486 | `1r em` | `.hero-lead` 980 px conserva el `clamp()` |
| 1498 | `.h ero-points` | no usada |
| 1501 | `.st ory-grid` | **`.story-grid` no pasa a 1 columna** bajo 980 px (se queda en 2) |
| 1517 | `.donat ion-flow` | `order` no aplicado |
| 1524 | `gri d-template-columns` | formulario de contacto a 2 columnas entre 769 y 980 px |
| 1529 | `anim ation-duration` | marquee no cambia de velocidad a 980 px |
| 1532 | `min-width:1 300px` | `.logo-slide` conserva 280 px |

Corregir cualquiera de estas líneas **cambia la apariencia actual**. Por la regla de fidelidad, el Theme copia el CSS **byte a byte** y estas correcciones quedan documentadas y pendientes de decisión del cliente (ver §17).

## 7. Imágenes y SVG

57 archivos en `assets/img/`. Hallazgos:

- **Duplicados idénticos (mismo MD5):** `logo.png` = `logo1.png`; `team-placeholder-1.webp` = `Jim-Damalas-150x150.webp`; `program-restoration.webp` = `volunteer-groups.webp`.
- **Extensión incorrecta:** `tort.webp` es en realidad un **PNG** (1600×1200, 2,4 MB). WordPress valida extensión/MIME; el importador lo guarda como `tort.png`.
- **Imagen muy grande:** `3.webp` mide 4284×5712 (4,5 MB). WordPress la reescalaría (`-scaled`) por el umbral de 2560 px; el importador desactiva ese umbral para no recodificar el original.
- **Nombre con espacio:** `CR-Decouverte .jpg`.
- **No referenciadas por ninguna página:** `Mask-General.webp`, `sponsor-placeholder-1.webp`, `volunteer-internship.webp`, `REQUESTED_TEAM_PHOTOS.txt` (nota interna, ya obsoleta: las fotos de Helena y Mariel existen).
- Los nombres `*-150x150.webp` no corresponden a su tamaño real (p. ej. `Francisco-Delgado-150x150.webp` mide 1600×1600).
- SVG: `logo-funcorco.svg` (contiene un PNG en base64), `team-placeholder-generic.svg`, `resource-placeholder.svg`. Son elementos de diseño: viven en el Theme (WordPress no permite subir SVG por defecto, y no se habilita por seguridad).
- Texto ALT: presente en todas las imágenes de contenido. Iconos SVG sociales sin `aria-hidden` en la mayoría de páginas (se conserva el `aria-label` del enlace).

## 8. Formularios

| Formulario | Página | Funcionamiento actual | Riesgo |
|---|---|---|---|
| Contacto | `contact-us.html`, `es/contact-us.html` | JS valida y abre `mailto:info@corcovadofoundation.org` | Depende de que el visitante tenga cliente de correo; nada llega si no lo envía. |
| Voluntariado | `volunteering.html`, `es/volunteering.html` | Botón "Submit Application" (type=button) abre `mailto:`; botón "Copy application" copia al portapapeles | Ídem; el enlace del hero "Apply Now" apunta a `#volunteer-form`, pero la sección tiene `id="formulario-voluntario"` (ancla rota). |

No hay endpoints de servidor, ni almacenamiento de datos personales. La migración implementa envío en servidor con nonce, sanitización, validación, honeypot, trampa de tiempo, límite de envíos por IP (hash) y `wp_mail()` con `Reply-To` seguro, **sin almacenar** los mensajes.

## 9. Integraciones externas

| Servicio | Uso | Archivo |
|---|---|---|
| Classy (GoFundMe Pro) | Formulario de donación embebido, campaña `568425` (identificador público, no secreto) | `donation-embed.js` |
| Amigos of Costa Rica | Enlace de respaldo de donación (`/affiliates/fundacion-corcovado`) y patrocinador fiscal | `donate-now.html` |
| Google Fonts | Inter + Cormorant Garamond, solo en About | `about-us.html` |
| Facebook | `https://www.facebook.com/funcorco/` | footers |
| Instagram | `https://instagram.com` — **es la portada genérica de Instagram, no una cuenta**. Se conserva y se señala para que la Fundación indique la URL real. | footers |
| Abundia | `https://abundia.io/` como aliado/patrocinador y crédito "Abundia Tech Design" en el footer. **No es el dominio antiguo** del sitio. | |

No hay analítica, píxeles ni JSON-LD.

## 10. PDFs y carpeta `library/`

- 21 PDF. **Los 21 son el mismo archivo** (MD5 `7f1cfe7614925b03fc79091860a06346`, 71 445 bytes), igual que `assets/volunteer-guide.pdf`. Son **marcadores de posición**, no documentos reales. Los metadatos mostrados ("PDF | EN | 4.2 MB", "Published: March 2026") no corresponden al archivo.
- Referenciados: 16 en las bibliotecas EN/ES y 6 en Our Impact (algunos repetidos). **No referenciados:** `catalogo-especies.pdf`, `native-species-catalog.pdf`, `corcovado-foundation-resource.pdf`.
- `library/Corcovado Nuevo Contenido.docx` (9 MB): documento de trabajo, no enlazado. Público hoy en `/library/…docx` aunque `robots.txt` desaconseje indexar `/library/`. **No se migra.**
- Inconsistencia: Our Impact muestra "Financial Audit 2025" pero descarga `financial-audit-2024.pdf`.

Equivalencias EN ↔ ES detectadas (por nombre y por su uso en las páginas):

| EN | ES |
|---|---|
| annual-report-2025.pdf | reporte-anual-2025.pdf |
| financial-audit-2024.pdf | auditoria-financiera-2024.pdf |
| sea-turtle-manual.pdf | manual-tortugas.pdf |
| volunteer-guide.pdf | guia-voluntarios.pdf |
| education-toolkit.pdf | kit-educativo.pdf |
| restoration-guide.pdf | guia-restauracion.pdf |
| community-handbook.pdf | manual-comunitario.pdf |
| research-methodology.pdf | metodologia-investigacion.pdf |
| conservation-protocols.pdf | protocolos-conservacion.pdf |
| native-species-catalog.pdf | catalogo-especies.pdf (ninguno enlazado) |
| corcovado-foundation-resource.pdf | — (no enlazado) |

## 11. Idiomas

- Inglés en `/`, español en `/es/`. `<html lang="en">` / `<html lang="es">`.
- Cada página tiene `hreflang="en"`, `hreflang="es"` y `hreflang="x-default"` (apunta a EN).
- Selector EN/ES en el header que enlaza a la página equivalente (con las excepciones indicadas en §4).
- Biblioteca: `virtual-library.html` ↔ `es/biblioteca-virtual.html` (slug distinto). El resto comparte slug.
- Interfaz traducida en HTML: skip link ("Skip to content"/"Saltar al contenido"), aria-labels del menú, textos del footer.

## 12. URLs actuales

Producción en Cloudflare Pages sirve `/about-us.html` y, por su función "pretty URLs", también `/about-us`. Las 32 URLs indicadas por el cliente coinciden con los archivos existentes. Mapa completo en `docs/URL-MAP.md` (se genera en la fase de SEO).

## 13. SEO actual

- `<title>` y `meta description` únicos por página (EN/ES).
- `rel=canonical`, `hreflang` y `og:url` **apuntan a `https://corcovadofoundation.abundia.io/`** en las 32 páginas (7 apariciones por página).
- `og:image` y `twitter:image` también en el dominio antiguo (`hero-corcovado.webp` en todas las páginas).
- `og:locale` `en_US` / `es_CR` con `og:locale:alternate`.
- `robots`: `index,follow,max-image-preview:large`; 404: `noindex,follow`.
- `sitemap.xml`: 32 URLs, **todas en `corcovadofoundation.abundia.io` y con `.html`**.
- `robots.txt`: `Disallow: /library/` y `Sitemap:` en el dominio antiguo.
- Encabezados: un `h1` por página (hero). Las tarjetas usan `h2`/`h3`/`h4` de forma variable; se conserva.

### Dominio antiguo `corcovadofoundation.abundia.io`

257 apariciones: 7 por cada una de las 32 páginas (canonical, 3 hreflang, og:url, og:image, twitter:image), 32 en `sitemap.xml` y 1 en `robots.txt`. **Ninguna** en JS ni CSS. En WordPress todas se sustituyen por URLs generadas dinámicamente con `home_url()` / `get_permalink()`; el dominio canónico final es `https://corcovadofoundation.org/`, configurado como `home`/`siteurl` de WordPress. `fundacioncorcovado.org` no aparece en el repositorio.

## 14. Footer: variantes

Las 34 páginas tienen 15 variantes de footer: cambia el logo (`logo-border.webp` en la mayoría, `logo1.png`, `logo-funcorco.svg`, `logo_en.png`), la columna "Explore" (mayoría: About / Our Programs / Get Involved / Contact; Inicio, About, Impact y Programs: About / Our Impact / Volunteering / Contact), `<p>` o `<span>` en el copyright, y en dos páginas los iconos sociales sin `target="_blank"`. Son inconsistencias de edición, no diseño intencional. En WordPress el footer es **un solo componente** con menús editables; se usa la variante mayoritaria como valor inicial y la diferencia queda documentada en el informe de diferencias visuales.

## 15. Contenido dinámico vs. estático

| Contenido | Frecuencia de cambio esperada | Destino |
|---|---|---|
| Noticias (3 historias EN + 3 ES) | Alta | Plugin: tipo de contenido News |
| Eventos (3 EN + 3 ES) | Alta | Plugin: tipo de contenido Events |
| Equipo (24 EN + 24 ES) | Media | Plugin: tipo de contenido Team |
| Recursos / Biblioteca (8 EN + 8 ES) | Media | Plugin: tipo de contenido Resources |
| Logos de aliados/patrocinadores (9) | Media | Plugin: tipo de contenido Partners (neutro de idioma: logo, nombre y URL son iguales) |
| Textos, imágenes y botones de cada página | Baja/Media | Campos administrables por página (estructura bloqueada) |
| Header, footer, menús | Baja | Theme + menús de WordPress |
| Diseño, CSS, JS | Muy baja | Theme |

## 16. Riesgos

1. **Fidelidad visual**: el sitio tiene inconsistencias reales (logos, footers, CSS con errores, HTML mal cerrado en `about-us.html` y `es/index.html`). Unificar componentes produce diferencias pequeñas pero visibles; se documentan una a una.
2. **`es/index.html`**: la sección "Nuestros aliados" no se cierra, y el CTA final ("El futuro de Corcovado…") queda **dentro del carrusel de logos**, desplazado fuera de la pantalla: hoy **no es visible**. Reproducirlo es reproducir un fallo.
3. **PDFs de marcador de posición** presentados como documentos reales (informe anual, auditoría financiera).
4. **Correo**: el servidor WordPress debe poder enviar correo (SMTP). Si no, los formularios fallarían en silencio; el plugin informa del error al visitante y ofrece el correo directo.
5. **Caché de página completa + nonces**: el plugin renueva el nonce por REST si caducó.
6. **Redirecciones**: `fundacioncorcovado.org → corcovadofoundation.org` ocurre fuera de WordPress; WordPress no debe redirigir dominios (evita bucles).
7. **Entorno de pruebas**: wordpress.org no es accesible desde el entorno de Claude; WordPress 7.1.2 se probó con la imagen Docker oficial. Polylang no se pudo descargar ni probar.

## 17. Decisiones que requieren al cliente

- Corregir o no los errores tipográficos del CSS (§6) y los fallos de HTML (§16.2). Por defecto: **se mantiene idéntico**.
- URL real de Instagram.
- Sustituir los PDFs de marcador de posición por documentos reales.

## 18. Arquitectura recomendada

- **Theme clásico/híbrido `corcovado-foundation`** (PHP, sin build): reproduce el HTML y las clases originales, incluye el CSS original sin modificar, `main.js`, `team.js`, `donation-embed.js`, `theme.json` mínimo (solo para el editor; no altera el frontend).
- **Plugin `corcovado-foundation-core`**: tipos de contenido (News, Events, Team, Resources, Partners), taxonomías, idiomas y traducciones, campos de página con estructura bloqueada, SEO (title, description, canonical, hreflang, OG, Twitter, JSON-LD), formularios seguros, ajustes (Classy, correo), redirecciones heredadas y herramienta **Corcovado Foundation → Import / Migration** (dry run, logs, idempotente, no destructiva).
- **Páginas**: plantilla PHP + campos. Cada página guarda su composición original como una lista de secciones; el editor puede cambiar textos, imágenes, botones y añadir/quitar tarjetas, pero **no** reordenar ni romper la estructura.
- **Gutenberg**: solo para el cuerpo de las Noticias (contenido largo). Las páginas institucionales no se convierten a bloques.

## 19. Idiomas: elección técnica

Se evaluó **Polylang** (gratuito) frente a una implementación propia mínima.

- Polylang gratuito **no permite el mismo slug en dos idiomas** ("Share slugs" es de Polylang Pro). Las URLs requeridas (`/about-us` y `/es/about-us`, `/news` y `/es/news`…) comparten slug, por lo que con Polylang gratuito las páginas españolas tendrían slugs distintos (`about-us-2`) o requerirían Pro.
- La jerarquía nativa de páginas de WordPress ya resuelve `/es/…`: una página raíz `es` (inicio en español) con las páginas españolas como hijas permite slugs idénticos sin reglas de reescritura.
- Solo hay 2 idiomas fijos; las necesidades (relación de traducciones, selector, hreflang, idioma en contenidos) se cubren con ~500 líneas en el plugin, sin dependencia externa.
- Polylang no se pudo descargar en el entorno de pruebas, por lo que tampoco se habría podido validar.

**Decisión:** implementación propia en el plugin. Si en el futuro se necesita un tercer idioma, Polylang Pro o WPML son alternativas; el modelo de datos (meta de idioma + relación de traducción) es migrable.

## 20. Dependencias recomendadas

Ninguna dependencia de plugins de terceros. Requisitos: WordPress ≥ 6.5 (probado en 7.1.2), PHP ≥ 8.1 (probado en 8.3), MySQL/MariaDB, envío de correo funcional (SMTP del hosting).

## 21. Estrategia de migración

1. Extraer el contenido del HTML original a JSON con un script reproducible (`tools/extract-content.py`), versionado en el plugin (`migration/data/`).
2. Empaquetar imágenes y PDFs originales junto al importador.
3. El importador crea/actualiza medios, páginas (con jerarquía `/es/`), contenidos estructurados y relaciones de traducción. Identifica cada elemento por una clave de origen (`_cf_source_key`), por lo que se puede ejecutar varias veces sin duplicar y nunca borra contenido.
4. Configura enlaces permanentes `/%postname%`, página de inicio, menús y ajustes, solo si el administrador marca esas opciones.
5. Validación: comparación automática del HTML de `<main>` y capturas de pantalla página por página (original vs WordPress) en 1440, 1280, 1024, 980, 768, 480, 390 y 375 px.
