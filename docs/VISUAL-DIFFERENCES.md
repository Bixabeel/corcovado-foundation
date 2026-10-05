# Diferencias visuales: sitio original vs WordPress

## Método

- Capturas de página completa con Chromium (Playwright) de las 34 páginas, original (servidor estático local) frente a WordPress (entorno de pruebas), en **8 anchos**: 1440, 1280, 1024, 980, 768, 480, 390 y 375 px. Total: 272 pares.
- Peticiones externas bloqueadas en ambos lados (Google Fonts, Classy) para que la comparación no dependa de la red; animaciones de aparición forzadas al estado final.
- Diferencia = porcentaje de píxeles distintos (umbral 24/255 por canal, para ignorar el suavizado de texto) sobre la altura mayor de las dos capturas; se compara también la altura total.
- Scripts: `tools/visual-compare.js` (capturas) y `tools/visual-diff.py` (diferencias).
- Además, comparación del HTML de `<main>` página a página (`tools/compare-dom.py`).

Esto no es una garantía de «pixel perfect» en todos los navegadores: solo se probó Chromium.

## Resultado

| Resultado | Capturas |
|---|---|
| Sin diferencias (0,00 % redondeado, misma altura) | **193 de 272** |
| Diferencia ≤ 0,2 % (solo en el pie de página) | 71 |
| Diferencia por corrección intencionada (`/es/`) | 8 |

Detalle por página (máximo entre los 8 anchos):

| Página | Máx. diferencia | Causa |
|---|---|---|
| 24 páginas | 0,00 % | — |
| `/`, `/allies` | 0,01 % | Columna «Explore» del pie |
| `/programs`, `/es/programs` | 0,05 % | Columna «Explore» del pie |
| `/our-impact`, `/es/our-impact` | 0,20 % | Columna «Explore» del pie |
| `/volunteering` | 0,04 % | Textos del pie |
| `/about-us`, `/es/about-us` | 0,09 %, página 32 px más baja | Estructura y columna «Explore» del pie |
| `/es/` | 10–20 %, página 80–180 px más alta | CTA final que antes no se veía (ver abajo) |

## Diferencias, una por una

### 1. Pie de página unificado

El sitio original tenía 15 variantes del pie (auditoría §14). WordPress usa **un solo pie** con menús editables, con la variante mayoritaria como valor inicial. El logo y el estilo de los iconos sociales se pueden elegir por página (Opciones de la página), así que esas variantes se conservan. Lo que cambia:

| Página | Original | WordPress |
|---|---|---|
| `/`, `/about-us`, `/allies`, `/our-impact`, `/programs` | Explore: About Us, **Our Impact**, **Volunteering**, Contact | About Us, **Our Programs**, **Get Involved**, Contact |
| `/es/`, `/es/our-impact` | Explorar: Nosotros, **Nuestro Impacto**, **Voluntariado**, Contáctanos | Nosotros, **Nuestros Programas**, **Participar**, Contáctanos |
| `/es/programs` | Explorar: … **Nuestro Impacto** … | … **Nuestros Programas** … |
| `/es/about-us` | **Acerca de Nosotros**, Nuestro Impacto, Voluntariado, **Contáctenos** | Nosotros, Nuestros Programas, Participar, Contáctanos |
| `/volunteering` | «community partnership**s**», «Donate **N**ow», «**Español**» | «community partnership», «Donate now», «Spanish» |
| `/about-us`, `/es/about-us` | El pie tenía otra estructura (32 px más alto) | Pie estándar |

Si la Fundación quiere conservar alguna variante exacta, se puede añadir como opción por página.

### 2. Portada en español: CTA final visible

En `es/index.html` la sección de aliados no se cierra y el CTA final («El futuro de Corcovado…») queda **dentro del carrusel de logos**, desplazado fuera de la pantalla: en el sitio original no se ve. En WordPress se muestra como sección propia después de los logos (los textos son los originales). Es la única diferencia visible grande, y es la corrección de un fallo de HTML. Se puede ocultar con «Ocultar esta sección» si se prefiere el aspecto anterior.

### 3. Diferencias de HTML sin efecto visual

- Las tarjetas de noticias enlazan a la noticia (`/news/slug`) en vez de a `#`.
- Botón «Apply Now» / «Aplicar ahora» de voluntariado: `#volunteer-form` → `#formulario-voluntario` (el ancla original no existía).
- «Volver al inicio» en `/es/donate-now` lleva a `/es/` (antes al inicio en inglés).
- `alt` del logo unificado por idioma («Corcovado Foundation Logo» / «Logo Fundación Corcovado»).
- El filtro de seguridad de WordPress quita un `;` final en algunos atributos `style` en línea (mismo resultado).
- Formulario de voluntariado: se añade un elemento de estado (vacío hasta que se envía) para los mensajes de éxito o error.
- WordPress añade `<link>`, `<meta>` y scripts propios en `<head>` (REST API, hreflang, etc.).

### 4. Diferencias que no se ven en las capturas

- El formulario de donación de Classy se bloqueó en las capturas en ambos lados; su aspecto depende del SDK de Classy, que no cambia.
- Fuentes: solo `/about-us` y `/es/about-us` cargan Google Fonts, igual que el original (en el resto de páginas el navegador usa las fuentes de sistema, como hoy).

## Lo que se mantuvo a propósito, aunque sea un fallo

Los errores tipográficos del CSS original (auditoría §6) se copian **sin corregir**, porque corregirlos cambia el aspecto actual (por ejemplo, el botón «Copy application» sin estilo, `.support-card` sin fondo o `.story-grid` en 2 columnas bajo 980 px). Corregirlos es una decisión de la Fundación.
