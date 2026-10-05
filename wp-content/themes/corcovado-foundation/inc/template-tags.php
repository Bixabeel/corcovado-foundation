<?php
/**
 * Template helpers.
 *
 * @package CorcovadoFoundation
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'cf_current_lang' ) ) {
	/**
	 * Fallback when the plugin is inactive.
	 */
	function cf_current_lang() {
		return 'en';
	}
}

/**
 * Page options (logos, footer style) of the current view.
 */
function cf_view_settings() {
	$defaults = array(
		'header_logo'  => 'default',
		'footer_logo'  => 'default',
		'footer_style' => 'standard',
	);
	if ( ! cf_core_active() ) {
		return $defaults;
	}
	$id = 0;
	if ( is_singular( 'page' ) ) {
		$id = get_queried_object_id();
	} elseif ( is_404() ) {
		$id = (int) cf_setting( 'page_404_' . cf_current_lang(), 0 );
	}
	$layout = $id ? cf_get_layout( $id ) : null;
	return $layout ? wp_parse_args( $layout['settings'], $defaults ) : $defaults;
}

/**
 * Brand image URL.
 *
 * @param string $variant default|home|svg|en.
 * @param string $where   header|footer.
 */
function cf_logo_url( $variant, $where = 'header' ) {
	$files = array(
		'home' => 'logo1.png',
		'svg'  => 'logo-funcorco.svg',
		'en'   => 'logo_en.png',
	);
	$file  = $files[ $variant ] ?? ( 'footer' === $where ? 'logo-border.webp' : 'logo.png' );
	return get_template_directory_uri() . '/assets/img/brand/' . $file;
}

/**
 * Logo used in structured data.
 */
function cf_theme_logo_url() {
	return cf_logo_url( 'default' );
}

/**
 * URL of a page of the current language by its static-site path ("/news", "/es/news").
 *
 * @param string $path Path.
 */
function cf_path_url( $path ) {
	if ( function_exists( 'cf_url' ) ) {
		return cf_url( $path );
	}
	return home_url( $path );
}

/**
 * Is this menu item the page being viewed?
 *
 * @param WP_Post $item Menu item.
 */
function cf_menu_item_current( $item ) {
	if ( 'post_type' === $item->type && is_singular() && (int) $item->object_id === get_queried_object_id() ) {
		return true;
	}
	return false;
}

/**
 * Print the links of a menu location with the original markup: <a class="…" href="…">.
 *
 * @param string $location Location.
 * @param string $class    Link class.
 * @param bool   $active   Mark the current page (header only).
 */
function cf_menu_links( $location, $class, $active = false ) {
	$locations = get_nav_menu_locations();
	if ( empty( $locations[ $location ] ) ) {
		return;
	}
	$items = wp_get_nav_menu_items( $locations[ $location ] );
	if ( ! $items ) {
		return;
	}
	$sep = 'nav-link' === $class ? '' : "\n";
	foreach ( $items as $item ) {
		if ( (int) $item->menu_item_parent ) {
			continue;
		}
		$is_current = $active && cf_menu_item_current( $item );
		$attrs      = $is_current ? ' aria-current="page"' : '';
		$cls        = $class . ( $is_current ? ' active' : '' );
		$target     = $item->target ? ' target="' . esc_attr( $item->target ) . '" rel="noopener"' : '';
		printf( '<a%1$s class="%2$s" href="%3$s"%4$s>%5$s</a>%6$s', $attrs, esc_attr( $cls ), esc_url( $item->url ), $target, esc_html( $item->title ), $sep ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
	}
}

/**
 * Language switch links (to the translation of the current content).
 */
function cf_lang_switch() {
	$cur = cf_current_lang();
	foreach ( array(
		'en' => 'EN',
		'es' => 'ES',
	) as $lang => $label ) {
		$url = class_exists( 'CF_Languages' ) ? CF_Languages::switch_url( $lang ) : home_url( '/' );
		if ( $lang === $cur ) {
			printf( '<a aria-current="page" class="lang-link is-active" href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $label ) );
		} else {
			printf( '<a class="lang-link" href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $label ) );
		}
	}
}

/**
 * Home URL of the current language.
 */
function cf_lang_home() {
	return function_exists( 'cf_home_url' ) ? cf_home_url() : home_url( '/' );
}

/**
 * Social icons (text glyphs on most pages, drawn icons on the others).
 *
 * @param bool $icons Drawn icons.
 */
function cf_social_links( $icons ) {
	$svg   = array(
		'facebook'  => '<svg fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"></path></svg>',
		'instagram' => '<svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"></path></svg>',
		'email'     => '<svg fill="currentColor" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"></path></svg>',
	);
	$items = array(
		'facebook'  => array( 'Facebook', 'FB', true ),
		'instagram' => array( 'Instagram', 'IG', true ),
		'email'     => array( cf_mod( 'social_label_email' ), '@', false ),
	);
	foreach ( $items as $key => $def ) {
		$url = cf_shared_mod( 'social_' . $key );
		if ( '' === $url ) {
			continue;
		}
		$blank = $def[2] ? ' rel="noopener" target="_blank"' : '';
		$inner = $icons ? "\n" . $svg[ $key ] . "\n" : esc_html( $def[1] );
		printf( '<a aria-label="%1$s" class="social-icon" href="%2$s"%3$s>%4$s</a>' . "\n", esc_attr( $def[0] ), esc_url( $url, array( 'http', 'https', 'mailto' ) ), $blank, $inner ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG / escaped text.
	}
}

/**
 * URL of the News page of a language.
 *
 * @param string $lang Language.
 */
function cf_news_page_url( $lang ) {
	$page = get_page_by_path( 'es' === $lang ? 'es/news' : 'news' );
	return $page ? get_permalink( $page ) : cf_lang_home();
}
