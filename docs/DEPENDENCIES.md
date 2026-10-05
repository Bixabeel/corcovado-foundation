# Dependencias

## En el servidor

| Dependencia | Versión | Obligatoria | Notas |
|---|---|---|---|
| WordPress | ≥ 6.5 | Sí | Probado en 7.1.2 |
| PHP | ≥ 8.1 | Sí | Probado en 8.3. Extensiones estándar de WordPress |
| MySQL / MariaDB | La que exija WordPress | Sí | Probado con MariaDB 11 |
| Envío de correo (SMTP) | — | Sí, para los formularios | Del hosting o con un plugin SMTP |
| Plugins de terceros | — | **No** | Ninguno |

## Servicios externos (los mismos que el sitio original)

| Servicio | Uso | Dónde |
|---|---|---|
| Classy (`sdk.classy.org`) | Formulario de donación | Solo en las páginas de donación |
| Google Fonts | Inter y Cormorant Garamond | Solo en `/about-us` y `/es/about-us`, como el original |
| Facebook, Instagram | Enlaces del pie | Solo enlaces |

No se añadió analítica, píxeles de seguimiento ni CDN.

## En el frontend

Ninguna librería: JavaScript sin dependencias (`main.js`, `team.js`, `donation-embed.js`, `forms.js`). Nada de React, Vue, jQuery en el frontend, Tailwind ni build.

En el administrador, `admin.js` usa jQuery y `wp.media`, que vienen con WordPress.

## Herramientas de desarrollo (opcionales, no se instalan en el servidor)

| Herramienta | Uso |
|---|---|
| Python 3 + BeautifulSoup 4 | `tools/extract-content.py` (extraer contenido del HTML) |
| Python 3 + Pillow | `tools/visual-diff.py` |
| Node.js + Playwright | `tools/visual-compare.js` (capturas) |
| `zip` | `tools/package.sh` |
| Docker (`wordpress:php8.3-apache`, `mariadb:11`, `wordpress:cli`) | Entorno de pruebas usado durante la migración |
