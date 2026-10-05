# Decisiones de arquitectura

Prioridad aplicada en cada decisión: fidelidad > integridad del contenido > seguridad > mantenibilidad > facilidad de administración > SEO > rendimiento > simplicidad.

## AD-1. Tema clásico/híbrido con el HTML original

**Decisión:** tema PHP clásico (`header.php`, `footer.php`, `page.php`…) con `theme.json` mínimo solo para el editor de noticias.
**Por qué:** el HTML y las clases originales se reproducen exactamente; un tema de bloques obligaría a convertir el marcado y cambiaría el resultado.
**Consecuencia:** el diseño no se edita desde el Editor del sitio. Los cambios de diseño se hacen en el código del tema.

## AD-2. CSS y JavaScript originales sin modificar

`styles-20260929.css`, `team.css` y `team.js` se copian tal cual (`team.js` y `donation-embed.js` con cambios mínimos de rutas/ajustes). Los estilos propios de WordPress van aparte en `wp.css`. Los errores del CSS original se conservan (ver [VISUAL-DIFFERENCES.md](VISUAL-DIFFERENCES.md)). Los estilos de bloques de WordPress solo se cargan en noticias y en páginas nuevas sin plantilla.

## AD-3. Páginas como plantillas con huecos editables (no Gutenberg)

**Decisión:** cada sección de cada página se guarda como su HTML original con marcadores (`{{N}}`) en lugar de textos, enlaces, imágenes y números. El editor ve un formulario por sección; la estructura viene siempre de la base de datos.
**Alternativas descartadas:**
- *Conversión a bloques*: prohibida por el encargo y produce HTML distinto.
- *Una plantilla PHP por página con campos fijos*: 32 plantillas distintas, difícil de mantener y sin listas flexibles.
- *Constructores visuales (ACF Flexible Content, Elementor…)*: dependencias externas o prohibidas.
**Consecuencia:** fidelidad exacta (comprobada con comparación de HTML y capturas) y edición segura. Las listas de elementos repetidos (tarjetas, estadísticas, FAQ…) admiten duplicar, eliminar y reordenar. Añadir un tipo de sección nuevo requiere código.

## AD-4. Contenido estructurado en tipos de contenido propios

Noticias, Eventos, Equipo, Biblioteca y Aliados son tipos de contenido del plugin con campos propios. Las páginas los muestran en «zonas dinámicas» que reproducen el marcado original de las tarjetas. El contenido sobrevive a un cambio de tema porque vive en el plugin.

## AD-5. Idiomas propios en lugar de Polylang/WPML

Ver auditoría §19. Las URLs pedidas comparten slug entre idiomas (`/about-us` y `/es/about-us`), lo que Polylang gratuito no permite. Se usa la jerarquía nativa de páginas (`/es/` es una página y las españolas son hijas) más dos metadatos (`_cf_lang`, `_cf_translation`). Las noticias usan una regla de reescritura `es/news/{slug}`. Es migrable a Polylang Pro/WPML si algún día se añade un tercer idioma.

## AD-6. Formularios por REST API, sin almacenamiento

Envío a `/wp-json/cf/v1/*` con nonce, token de tiempo firmado, honeypot, validación y límite por IP con hash; correo por `wp_mail`. No se guardan mensajes (no hay datos personales en la base de datos). La REST API se eligió frente a `admin-post.php` para poder renovar el nonce en páginas cacheadas y responder en JSON sin recargar la página, conservando los mensajes de estado originales.

## AD-7. Classy sin cambios

El formulario de donación sigue siendo el embebido de Classy. Solo el ID de campaña pasa a Ajustes. No se usa WooCommerce ni GiveWP.

## AD-8. SEO propio, desactivable

El plugin imprime title, description, Open Graph, Twitter y hreflang con los valores originales; canonical y sitemap son los nativos de WordPress (una sola fuente de cada uno). Si se instala un plugin de SEO, el plugin cede automáticamente (salvo hreflang, que depende de sus traducciones).

## AD-9. Importador idempotente y no destructivo

Clave de origen por elemento, simulación, registro, nunca borra. Los archivos originales viajan dentro del ZIP del plugin para que la importación no dependa de Internet ni del repositorio.

## AD-10. Medios sin recompresión

Se desactiva el umbral de imágenes grandes durante la importación; el sitio usa el archivo original. WordPress genera sus miniaturas (no se usan en las páginas migradas).

## AD-11. Pie de página único

El pie se unifica en un componente con menús editables (variante mayoritaria). El logo y el estilo de iconos siguen siendo elegibles por página. Las diferencias resultantes están listadas en [VISUAL-DIFFERENCES.md](VISUAL-DIFFERENCES.md#1-pie-de-página-unificado).

## AD-12. Correcciones mínimas de HTML

Solo se corrigieron fallos que rompían algo (ancla inexistente, enlace a otro idioma, sección mal cerrada que ocultaba un CTA). Están listadas en [MIGRATION.md](MIGRATION.md#correcciones-aplicadas-durante-la-extracción).

## AD-13. Sin herramientas de build

PHP, CSS y JavaScript sin compilar. Los scripts de `tools/` (Python y Node) solo se usan para extraer, empaquetar y comparar; no son necesarios en el servidor.
