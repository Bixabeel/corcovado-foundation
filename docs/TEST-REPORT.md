# Informe de pruebas

Entorno: Docker, WordPress 7.1.2 (`wordpress:php8.3-apache`), PHP 8.3, MariaDB 11, Chromium (Playwright). Sitio original servido localmente desde la rama `main`. Fecha: 2026-10-05.

Aquí solo figura lo que se ejecutó de verdad. Lo que **no** se probó está al final.

## Instalación limpia desde los ZIP

| Prueba | Resultado |
|---|---|
| WordPress nuevo → instalar `corcovado-foundation-core.zip` y `corcovado-foundation.zip` → activar | OK |
| Import / Migration desde el administrador: Simulación y después Ejecutar importación (con «Configurar el sitio») | OK: 203 elementos creados, 0 errores, 1 aviso (`tort.webp` es un PNG → `tort.png`) |
| Las 32 URLs responden 200 y las 2 rutas 404 responden 404 | OK (34/34) |
| HTML completo de las 34 páginas comparado con el entorno de desarrollo (normalizando dominio e IDs) | Idéntico en 34/34 |
| Fallo encontrado y corregido: en la primera instalación limpia las páginas daban 404 porque el importador no escribía `.htaccess`. Ahora fuerza la escritura y, si no puede, avisa en el registro | Corregido y vuelto a probar |

## Importador

| Prueba | Resultado |
|---|---|
| Simulación no escribe nada | OK (0 páginas creadas tras la simulación) |
| Segunda ejecución | OK: 0 creados, 211 omitidos, nada duplicado |
| Medios sin recomprimir (`3.webp` 4284×5712 sin `-scaled`) | OK |
| `extract-content.py --check` (el JSON corresponde al HTML) | OK |

## Fidelidad

| Prueba | Resultado |
|---|---|
| Capturas en 8 anchos × 34 páginas (272 pares) | 193 sin diferencias; 71 con ≤ 0,2 % (pie unificado); 8 de `/es/` por la corrección del CTA. Detalle en [VISUAL-DIFFERENCES.md](VISUAL-DIFFERENCES.md) |
| HTML de `<main>` original vs WordPress | Solo las diferencias intencionadas documentadas |
| Ventana del equipo (abrir con clic, Enter y Espacio, cerrar con Escape y clic fuera, foco atrapado, datos de cada miembro) | Igual que el original, incluido el comportamiento del foco al cerrar |
| Menú móvil (abrir, cerrar, Escape) | OK |
| Contadores animados y aparición `.reveal` (`/our-impact`, original vs WordPress) | Mismos valores finales y 12/12 elementos visibles en ambos |

## URLs, idiomas y redirecciones

| Prueba | Resultado |
|---|---|
| 32 URLs sin `.html` | 200 |
| 32 URLs `.html` antiguas + `.htm` + `/es/virtual-library` + `/es` + `/sitemap.xml` + PDF | 301 en un salto (ver [REDIRECT-MAP.md](REDIRECT-MAP.md)) |
| Archivos con mayúsculas o espacios (`/assets/img/CR-Decouverte .jpg`, `Jim-Damalas-150x150.webp`) | 301 al archivo importado |
| Reemplazar el PDF de un recurso → `/library/{pdf}` lleva al archivo nuevo | OK (y restaurado) |
| Selector EN/ES lleva a la traducción en todas las páginas | OK |
| Noticias en `/news/slug` y `/es/news/slug` | 200 |
| Ruta inexistente en `/es/…` muestra el 404 en español | OK |

## Formularios

| Prueba | Resultado |
|---|---|
| Envío correcto de Contacto (EN y ES) y Voluntariado | Correo capturado con el texto esperado (mismo formato que el `mailto:` original) |
| Envío en menos de 3 s | 429 `too_fast`; el navegador espera y reintenta |
| Nonce o token falsos | 403 |
| Campos obligatorios vacíos / correo inválido | 400 con la lista de campos |
| Honeypot relleno | Respuesta «ok», no se envía correo |
| `\r\nBcc:` en nombre y asunto | Neutralizado |
| Más de 5 envíos por hora | 429 `rate_limited` |
| Formulario de contacto en español en el navegador | Mensajes de error y éxito en español |

## SEO

| Prueba | Resultado |
|---|---|
| `<title>` y `meta description` de las 34 páginas vs original | Idénticos |
| robots, canonical, hreflang (en/es/x-default), Open Graph, Twitter | Correctos, en el dominio del sitio |
| 404: `noindex, follow`, título y descripción originales, código 404 | OK (corregido durante las pruebas: antes faltaba el `noindex`) |
| `robots.txt` con `Disallow: /library/` y Sitemap | OK |
| `/wp-sitemap.xml` con las 32 páginas y las noticias, sin usuarios ni 404 | OK |
| El dominio antiguo no aparece en tema ni plugin | OK |

## Administrador

| Prueba | Resultado |
|---|---|
| Página migrada: editor clásico con «Contenido de la página», sin editor de bloques | OK |
| Cambiar un texto → se ve en el sitio; volver al original | OK |
| `<script>` en un campo de texto | Eliminado; no llega al HTML |
| Duplicar una tarjeta (6 → 7), eliminarla (7 → 6) | OK; el sitio queda idéntico al de antes |
| Reordenar tarjetas | OK |
| Ocultar una sección (4 → 3 secciones) y volver a mostrarla | OK |
| «Crear traducción (borrador)» en una noticia | Crea el borrador en español enlazado en ambos sentidos |
| «Crear traducción» sin nonce | 403 |
| Casilla «Ocultar la fecha» de una noticia guardada desde el editor de bloques | OK |
| Interfaz del plugin en español (idioma de usuario Español) | OK |

El contenido de prueba se borró y la página modificada se restauró a su estado importado.

## Seguridad (comprobado por HTTP)

Cabeceras de seguridad, `X-Powered-By` eliminado, XML-RPC sin métodos, `/wp-json/wp/v2/users` 404 para anónimos, `?author=1` 404, sin `generator`. Detalle en [SECURITY.md](SECURITY.md).

## Calidad de código

- `php -l` sin errores en todos los archivos PHP del tema y del plugin.
- No se ejecutó PHPCS (WordPress Coding Standards) porque no está instalado en el entorno.

## No probado

- **Envío real de correo** (SMTP): en pruebas `wp_mail` se capturó en un registro.
- **Donación real con Classy**: el SDK externo se bloqueó en las capturas; solo se comprobó que la página imprime la configuración de la campaña y carga `donation-embed.js`.
- **HTTPS y HSTS** (el entorno es HTTP).
- **Navegadores distintos de Chromium** (Firefox, Safari) y dispositivos reales.
- **Caché de página completa** de un hosting real con los formularios.
- **Plugins de SEO** reales (la detección se revisó en el código, no se instaló ninguno).
- **Polylang/WPML**: no se usan.
- **Lectores de pantalla**: solo se comprobó que el marcado ARIA es el original.
