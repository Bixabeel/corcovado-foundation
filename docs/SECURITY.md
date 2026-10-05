# Seguridad

Este documento describe las medidas implementadas y el informe de seguridad de la migración. Cada medida indica si se **probó** en el entorno de pruebas (WordPress 7.1.2, PHP 8.3, Docker) o si solo se revisó en el código.

## Formularios (Contacto y Voluntariado)

El sitio estático abría el cliente de correo del visitante (`mailto:`). En WordPress los formularios se envían al servidor por la REST API (`/wp-json/cf/v1/contact` y `/wp-json/cf/v1/volunteer`) y se reenvían por correo con `wp_mail()`.

| Capa | Detalle | Probado |
|---|---|---|
| Nonce de WordPress | `cf_form`. Si caduca en una página en caché, el navegador pide uno nuevo (`/wp-json/cf/v1/form-token`, `Cache-Control: no-store`) y reintenta una vez | Sí: nonce falso → 403 |
| Token de tiempo firmado | `timestamp.HMAC` con un secreto del sitio (`wp_salt`). Rechaza envíos en menos de 3 s y tokens de más de 24 h | Sí: envío inmediato → 429 `too_fast`; token alterado → 403 |
| Honeypot | Campo oculto `website`. Si viene relleno, se responde «ok» y no se envía nada | Sí |
| Validación | Campos obligatorios, longitud máxima por campo, `is_email()`, `sanitize_text_field` / `sanitize_textarea_field` | Sí: faltantes y correo inválido → 400 |
| Límite de envíos | 5 por visitante y hora (ajustable). Se guarda solo un **hash HMAC** de la IP en un transient que caduca en 1 h | Sí |
| Inyección de cabeceras | Asunto y nombre del Reply-To sin saltos de línea ni caracteres especiales; correo en texto plano | Sí: intento con `\r\nBcc:` neutralizado |
| Datos personales | Los mensajes **no se guardan** en la base de datos; solo se envían por correo | Revisado en código |
| Destinatario | Configurable en Ajustes, nunca enviado al navegador | Revisado en código |

IP del visitante: se usa `REMOTE_ADDR`. `CF-Connecting-IP` solo se usa si se activa «Detrás de Cloudflare» (si se activa sin que el servidor acepte solo tráfico de Cloudflare, un atacante podría falsear su IP para saltarse el límite). El filtro `cf_client_ip` permite adaptarlo a otro proxy.

## Contenido editable

| Medida | Detalle | Probado |
|---|---|---|
| Permisos | Guardar la caja «Contenido de la página» exige `edit_post` y el nonce `cf_layout_save_{id}` | Revisado en código |
| Escape de salida | Textos con `esc_html`, atributos con `esc_attr`, URLs con `esc_url`. Textos con formato filtrados con `wp_kses` (solo `strong`, `b`, `em`, `i`, `br`, `span[class]`, `a[href,target,rel,style]`) | Sí: `<script>` en un campo de texto no llega al HTML |
| Estructura bloqueada | La plantilla HTML de cada sección viene siempre de la base de datos, nunca del formulario: el editor solo envía valores | Sí: duplicar/eliminar/reordenar no permite inyectar estructura |
| Enlaces | Esquemas permitidos `http`, `https`, `mailto`, `tel`, rutas relativas, anclas, `media:ID`, `post:ID` | Revisado en código |
| «Crear traducción» | Acción con nonce y comprobación de permisos | Sí: sin nonce → 403 |
| SVG | No se permite subir SVG. Solo el importador los admite temporalmente para los 3 SVG originales | Revisado en código |

## Endurecimiento de WordPress

| Medida | Probado |
|---|---|
| Cabeceras como en `_headers` del sitio estático: `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`, `X-Frame-Options: SAMEORIGIN` | Sí |
| `Strict-Transport-Security: max-age=31536000` solo con HTTPS (filtro `cf_hsts`). El original añadía `includeSubDomains`; se deja para que el dueño del dominio lo decida a nivel de DNS/hosting | Revisado en código (el entorno de prueba es HTTP) |
| Cabecera `X-Powered-By` eliminada | Sí |
| XML-RPC sin métodos (sin pingbacks ni métodos autenticados); cabecera `X-Pingback` eliminada. `xmlrpc.php` aún responde a los métodos `system.*` internos, que no hacen nada: para bloquearlo del todo, hacerlo en el servidor | Sí |
| `/wp-json/wp/v2/users` oculto a visitantes anónimos | Sí: 404 |
| `?author=N` y `/author/…` devuelven 404 (evita enumerar usuarios) | Sí |
| Meta `generator` eliminada | Sí |
| Comentarios cerrados por defecto (opción del importador) | Sí |

## Secretos y credenciales

- No hay secretos en el código ni en el frontend.
- El ID de campaña de Classy (`568425`) es un **identificador público** (aparece en el HTML del sitio original). Se guarda en Ajustes y se imprime en la página de donación. El campo indica que no se peguen claves de API.
- El correo destinatario no se imprime en el HTML de los formularios. El correo `info@corcovadofoundation.org` sí aparece en el pie y en la página de contacto porque así lo publicaba el sitio original.
- El HMAC del token y del límite de envíos usa las sales de `wp-config.php`.
- `.env` sigue en `.gitignore`.

## Importador

- Solo administradores (`manage_options`), con nonce.
- Nunca borra contenido. La opción que sobrescribe elementos importados está desactivada por defecto.
- Lee solo archivos de `migration/source/` del propio plugin; no descarga nada de Internet.

## Recomendaciones para producción

1. HTTPS obligatorio y redirecciones de dominio en un solo salto (ver [REDIRECT-MAP.md](REDIRECT-MAP.md)).
2. SMTP autenticado para `wp_mail` (SPF/DKIM del dominio).
3. Actualizaciones automáticas de seguridad de WordPress.
4. Contraseñas fuertes y 2FA para administradores.
5. Copias de seguridad diarias.
6. Opcional: bloquear `xmlrpc.php` en el servidor.

## Riesgos conocidos

- El formulario depende de que el servidor envíe correo. Si `wp_mail()` falla, el visitante ve un mensaje de error con el correo de contacto directo. **No se probó un envío real** (en pruebas el correo se capturó en un registro).
- Los PDF de la biblioteca son marcadores de posición (todos el mismo archivo), igual que en el sitio original.
