# Solución de problemas

## Las páginas dan 404 (salvo la portada)

Los enlaces permanentes no están en `/%postname%`. Ajustes → Enlaces permanentes → «Nombre de la entrada» → Guardar. (La opción «Configurar el sitio» del importador lo hace.)

## `/es/` muestra la página en inglés o 404

- Comprobar en **Corcovado Foundation → Ajustes** que «Página de inicio en español» es la página `es`.
- Las páginas españolas deben ser hijas de esa página (Atributos de página → Superior).
- Guardar una vez los enlaces permanentes para regenerar las reglas.

## El selector EN/ES lleva a la portada en vez de a la página traducida

La página no tiene traducción enlazada. Abrir la página → caja **Idioma y traducción** → elegir la traducción → Actualizar.

## Los formularios dicen que no se pudo enviar

1. El servidor no envía correo. Instalar/configurar SMTP y probar con un plugin de prueba de correo.
2. «Correo destinatario» vacío o inválido en Ajustes.
3. Un plugin de caché o un firewall bloquea `/wp-json/cf/v1/*`. Excluir esas rutas de la caché y permitir POST.
4. Demasiados envíos desde la misma conexión (límite por hora). Esperar o subir el límite en Ajustes.

## El formulario de donación no aparece

- Revisar el ID de campaña de Classy en Ajustes.
- Un bloqueador de anuncios o una política CSP pueden bloquear `sdk.classy.org`. El script muestra el enlace de respaldo tras 15 s, como en el sitio original.

## El aspecto no coincide con el original

- Activar el tema **Corcovado Foundation**.
- Desactivar plugins que añadan CSS global (constructores, optimizadores que combinan CSS).
- Si un optimizador minifica `styles-20260929.css`, puede «corregir» los errores tipográficos del CSS original y cambiar el aspecto (ver [VISUAL-DIFFERENCES.md](VISUAL-DIFFERENCES.md)). Excluir ese archivo.

## Imágenes con sufijo `-1` (p. ej. `Jim-Damalas-150x150-1.webp`)

WordPress renombra los archivos cuyo nombre parece un tamaño intermedio (`-150x150`). Es normal; el archivo es el original sin recomprimir.

## Una sección desapareció de una página

Probablemente se marcó **Ocultar esta sección**. Abrir la página y desmarcarla. Si se eliminó una tarjeta por error y la página aún no se ha actualizado, salir sin guardar. Si ya se guardó, usar **Revisiones** no sirve (el contenido está en un campo personalizado): volver a escribirla duplicando otra tarjeta, o restaurar la página entera con el importador (ver [MIGRATION.md](MIGRATION.md#repetir-la-importación)).

## El importador avisa de archivos que faltan

El plugin se instaló desde el repositorio sin `migration/source/`. Usar el ZIP generado con `bash tools/package.sh` (incluye las imágenes y PDF), o copiar `assets/` y `library/` del sitio estático a `wp-content/plugins/corcovado-foundation-core/migration/source/`.

## Se instaló un plugin de SEO y hay etiquetas duplicadas

Con «Salida de etiquetas SEO» en Automática, el plugin desactiva sus etiquetas cuando detecta Yoast, Rank Math, AIOSEO, SEOPress o The SEO Framework. Las etiquetas `hreflang` se siguen imprimiendo (dependen de las traducciones del plugin). Si el plugin de SEO también imprime `hreflang`, poner la salida en «Desactivada» no las quita: desactivar el `hreflang` del plugin de SEO.

## Enlaces antiguos `.html` o `/library/*.pdf` dan 404

Activar «Redirecciones de .html antiguas» en Ajustes. Solo actúan cuando WordPress devolvería 404, así que no interfieren con páginas reales.

## El administrador está en inglés

Las traducciones al español del tema y del plugin se cargan si el idioma del usuario (Usuarios → Perfil → Idioma) o del sitio es Español. Para el resto del administrador, instalar el paquete de idioma español de WordPress (Ajustes → Generales → Idioma del sitio).
