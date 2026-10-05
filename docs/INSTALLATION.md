# Instalación

## Requisitos

| Requisito | Mínimo | Probado |
|---|---|---|
| WordPress | 6.5 (traducciones `.l10n.php`) | 7.1.2 (imagen Docker oficial `wordpress:php8.3-apache`) |
| PHP | 8.1 | 8.3 |
| Base de datos | MySQL 5.7+ / MariaDB 10.4+ | MariaDB 11 |
| Correo | El servidor debe poder enviar correo (`wp_mail`). Se recomienda SMTP del hosting o un plugin SMTP. | Se probó capturando `wp_mail` (sin envío real) |
| Enlaces permanentes | `/%postname%` (el importador puede configurarlo) | |

No se necesita ningún otro plugin.

## 1. Generar los ZIP

```bash
bash tools/package.sh
```

Genera en `dist/`:

- `corcovado-foundation.zip`: el tema.
- `corcovado-foundation-core.zip`: el plugin, con las imágenes y PDF originales dentro de `migration/source/` (unos 15 MB).

## 2. Instalar

1. Instalar WordPress con el dominio definitivo `https://corcovadofoundation.org` como **Dirección de WordPress** y **Dirección del sitio** (Ajustes → Generales). No escribir el dominio en ningún otro sitio: todo se genera con `home_url()`.
2. **Plugins → Añadir nuevo → Subir plugin**: `corcovado-foundation-core.zip` → Activar.
3. **Apariencia → Temas → Añadir nuevo → Subir tema**: `corcovado-foundation.zip` → Activar.
   - El tema declara `Requires Plugins: corcovado-foundation-core`; si el plugin falta, el tema muestra un aviso en el administrador y no rompe el sitio.
4. **Corcovado Foundation → Import / Migration**:
   1. Marcar «Crear menús y textos del encabezado/pie» y «Configurar el sitio».
   2. Pulsar **Simulación** y revisar el registro.
   3. Pulsar **Ejecutar importación**.
5. **Corcovado Foundation → Ajustes**: revisar correo destinatario, ID de campaña de Classy, páginas 404, etc.
6. **Ajustes → Lectura**: desmarcar «Disuade a los motores de búsqueda» al publicar.

Con WP-CLI se puede hacer lo mismo:

```bash
wp plugin install dist/corcovado-foundation-core.zip --activate
wp theme install dist/corcovado-foundation.zip --activate
# La importación se ejecuta desde el administrador (Corcovado Foundation → Import / Migration).
```

## 3. Ajustes del plugin

| Ajuste | Valor inicial | Notas |
|---|---|---|
| Página de inicio en español | Página `es` creada por el importador | Todas sus páginas hijas son españolas y viven en `/es/…` |
| Nombre de la organización en español | Fundación Corcovado | Se usa en títulos de las páginas en español |
| Contenido 404 (EN/ES) | Páginas privadas «Page not found» / «Página no encontrada» | Deben seguir en estado Privado |
| ID de campaña de Classy | `568425` | Identificador **público**. Nunca pegar claves de API |
| Correo destinatario | `info@corcovadofoundation.org` | Destino de contacto y voluntariado |
| Máx. mensajes por visitante por hora | 5 | Límite anti-abuso |
| Salida de etiquetas SEO | Automática | Se desactiva sola si se instala Yoast, Rank Math, AIOSEO, SEOPress o The SEO Framework |
| Datos estructurados | Desactivado | JSON-LD de ONG opcional |
| Redirecciones de .html antiguas | Activado | Ver [REDIRECT-MAP.md](REDIRECT-MAP.md) |
| Detrás de Cloudflare | Desactivado | Activar solo si el servidor acepta tráfico exclusivamente de Cloudflare |
| Cabeceras de seguridad | Activado | Mismos valores que `_headers` del sitio estático |

## 4. Puesta en producción (checklist)

- [ ] `home` y `siteurl` = `https://corcovadofoundation.org` (con HTTPS).
- [ ] Redirección `fundacioncorcovado.org → https://corcovadofoundation.org` configurada **en el DNS/servidor/Cloudflare**, en un solo salto y conservando la ruta. WordPress no redirige dominios.
- [ ] `corcovadofoundation.abundia.io` redirigido igual o dado de baja.
- [ ] Enviar un formulario de contacto real y comprobar que llega (SMTP).
- [ ] Probar una donación de Classy en la página de donación.
- [ ] «Disuade a los motores de búsqueda» desmarcado.
- [ ] Enviar `https://corcovadofoundation.org/wp-sitemap.xml` a Google Search Console.
- [ ] Copia de seguridad automática configurada en el hosting.
- [ ] Usuarios administradores con contraseñas fuertes y 2FA si el hosting lo ofrece.

## 5. Caché

El sitio funciona con caché de página completa. Los formularios piden un nonce nuevo por REST (`/wp-json/cf/v1/form-token`, `Cache-Control: no-store`) antes de enviar, así que una página guardada en caché no rompe los envíos. No guardar en caché las rutas `/wp-json/cf/v1/*`.
