# Gestión de contenidos

Todo se edita desde el administrador de WordPress. El diseño está en el tema y no se puede romper desde el editor: se editan textos, imágenes, enlaces y elementos de listas.

## Idiomas

- El inglés vive en `/` y el español en `/es/`.
- Una página es española si es **hija** de la página «Inicio en español» (`/es/`). Para crear una página en español: Atributos de página → Superior = la página `es`.
- Las noticias, eventos, miembros del equipo, recursos y categorías tienen un campo **Idioma**.
- La caja **Idioma y traducción** une cada contenido con su traducción. El selector EN/ES del encabezado y las etiquetas `hreflang` usan esa relación.
- **Crear traducción (borrador)** duplica el contenido en el otro idioma como borrador y deja ambos enlazados. Después hay que traducir los textos y publicar.
- Los Aliados (logos) son comunes a los dos idiomas.

## Páginas

Las 32 páginas importadas usan la caja **Contenido de la página**, no el editor de bloques.

- Cada sección de la página original aparece como un bloque desplegable numerado.
- **Ocultar esta sección**: la quita del sitio sin borrarla.
- Campos:
  - **Texto**: texto sin formato.
  - **Texto con formato**: admite `<strong>`, `<em>`, `<br>` y enlaces.
  - **Enlace**: `/about-us`, `/es/contact-us`, `#seccion`, `https://…`, `mailto:…` o un archivo de la Biblioteca de medios (botón «Elegir archivo»).
  - **Imagen**: botón «Elegir imagen»; la descripción (alt) se edita al lado.
  - **Número (contador animado)**: el número al que llega la animación.
- **Listas** (tarjetas, estadísticas, preguntas frecuentes, opciones…): cada elemento tiene ↑ ↓ (reordenar), **Duplicar** y **Eliminar**. Para añadir una tarjeta, duplicar una existente y cambiar sus textos.
- **Opciones de la página**: logo del encabezado, logo del pie y estilo del pie (las variantes que tenía el sitio original).
- Las zonas marcadas con ↻ se cargan solas desde otro tipo de contenido (noticias, eventos, biblioteca, aliados, equipo). Su enlace **Gestionar contenido** lleva a ese listado. Algunas tienen textos editables (títulos, etiquetas de botones).
- **Buscadores y redes sociales**: título y descripción para Google y la imagen para compartir.

Las páginas 404 («Page not found» / «Página no encontrada») se editan igual pero deben quedarse en estado **Privado**.

## Noticias (News)

Noticias → Añadir. Se usa el editor de bloques para el cuerpo de la noticia.

- **Título**, **contenido**, **extracto** (texto de la tarjeta), **imagen destacada** (imagen de la tarjeta y de la noticia).
- **Detalles**: título corto y texto corto para las tarjetas de la página de inicio (opcionales).
- **Idioma** y **traducción**.
- Las tarjetas de Inicio y de News muestran las noticias publicadas más recientes del idioma.
- URL: `/news/slug` y `/es/news/slug`.
- **Detalles → Ocultar la fecha de publicación**: las 6 noticias importadas no tenían fecha en el sitio original, así que se importan con esta casilla marcada.

## Eventos

Eventos → Añadir: título, fecha de inicio/fin, hora y lugar (opcionales), «fecha tal como se muestra» (si se deja vacía se genera), descripción, texto y enlace del botón, imagen destacada opcional. Para quitar un evento del sitio, pasarlo a Borrador.

## Equipo

Equipo → Añadir: nombre (título), foto (imagen destacada), grupo (Grupos del equipo), orden (Atributos de página → Orden), cargo y resumen de la tarjeta, biografía completa de la ventana de perfil, y nombre/cargo distintos en la ventana si hace falta. El orden de los grupos se define en Equipo → Grupos del equipo (campo Orden).

## Biblioteca

Biblioteca → Añadir: título, descripción, **archivo para descargar** (botón «Elegir archivo»), información del archivo (ej. «PDF | EN | 4.2 MB»), fecha, categoría, «Destacado» y miniatura (imagen destacada).

Para **reemplazar un PDF**: subir el nuevo a Medios y elegirlo en el recurso («Elegir archivo»). El enlace antiguo `/library/archivo.pdf` del recurso redirige automáticamente al archivo nuevo. Los PDF enlazados desde una página (por ejemplo, Our Impact) se cambian en el campo de enlace de esa página.

## Aliados y patrocinadores

Aliados → Añadir: nombre, logo (imagen destacada), sitio web, descripción del logo, orden y categoría de patrocinio (`founding`, `supporters`, `gold`, `silver`, `bronze` o vacío). Cada página que muestra logos conserva la selección original; la opción «Mostrar todos los aliados publicados» la sustituye por todos.

No hay patrocinadores inventados: las categorías vacías del sitio original siguen mostrando su texto «sin miembros».

## Menús y textos del encabezado/pie

- **Apariencia → Menús**: menú principal EN/ES y las tres columnas del pie por idioma.
- **Apariencia → Personalizar → Header & footer**: textos del pie (descripción, títulos de columna, copyright, crédito), botón «Donate» del encabezado, enlaces de Facebook, Instagram y correo.

## Donaciones

La página de donación carga el formulario de Classy con el ID de campaña de los Ajustes. Los textos de la página se editan como en cualquier otra página.

## Formularios

Los formularios de Contacto y Voluntariado se envían por correo a la dirección de los Ajustes. **No se guardan** en la base de datos. Los textos de las etiquetas y opciones se editan en la página.
