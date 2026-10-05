<?php
/**
 * Public API used by the theme. Keeping these functions in the plugin means the
 * content keeps working (and stays queryable) if the theme is replaced.
 *
 * @package CorcovadoFoundationCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Supported languages. English is the default language and lives at "/".
 *
 * @return array<string,array{name:string,locale:string,og:string}>
 */
function cf_languages() {
	return array(
		'en' => array(
			'name'   => 'English',
			'locale' => 'en_US',
			'og'     => 'en_US',
		),
		'es' => array(
			'name'   => 'Español',
			'locale' => 'es_ES',
			'og'     => 'es_CR',
		),
	);
}

/**
 * Language of the current request ("en" or "es").
 */
function cf_current_lang() {
	return CF_Languages::current();
}

/**
 * Language of a post (pages derive it from the Spanish root page).
 *
 * @param int|WP_Post|null $post Post.
 */
function cf_lang_of( $post = null ) {
	return CF_Languages::lang_of( $post );
}

/**
 * ID of the post that is the translation of $post_id, or 0.
 *
 * @param int $post_id Post ID.
 */
function cf_translation_id( $post_id ) {
	return CF_Languages::translation_of( (int) $post_id );
}

/**
 * Home URL for a language.
 *
 * @param string|null $lang Language code.
 */
function cf_home_url( $lang = null ) {
	return CF_Languages::home_url( $lang ?? cf_current_lang() );
}

/**
 * Resolves a link stored in page sections.
 *
 * Stored formats:
 * - "/path"        a path relative to the site home (domain added dynamically)
 * - "#anchor"      same-page anchor
 * - "media:123"    URL of attachment 123
 * - "post:123"     permalink of post 123
 * - mailto:, tel:, http(s):// external links are returned unchanged.
 *
 * @param string $value Stored value.
 */
function cf_url( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	if ( str_starts_with( $value, 'media:' ) ) {
		$url = wp_get_attachment_url( (int) substr( $value, 6 ) );
		return $url ? $url : '';
	}
	if ( str_starts_with( $value, 'post:' ) ) {
		$url = get_permalink( (int) substr( $value, 5 ) );
		return $url ? $url : '';
	}
	if ( str_starts_with( $value, '/' ) && ! str_starts_with( $value, '//' ) ) {
		$parts = explode( '#', $value, 2 );
		$url   = home_url( $parts[0] );
		if ( '/' === $parts[0] || str_ends_with( $parts[0], '/' ) ) {
			$url = trailingslashit( $url );
		}
		return isset( $parts[1] ) ? $url . '#' . $parts[1] : $url;
	}
	return $value;
}

/**
 * Page sections (layout) of a page, or null if the page has none.
 *
 * @param int $post_id Post ID.
 * @return array|null
 */
function cf_get_layout( $post_id ) {
	return CF_Layout::get( (int) $post_id );
}

/**
 * Plugin setting.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Default.
 */
function cf_setting( $key, $default = '' ) {
	return CF_Settings::get( $key, $default );
}

/**
 * Published partners (sponsors / allies), in admin order.
 *
 * @param int[] $ids Optional ordered selection. Empty = all.
 * @return WP_Post[]
 */
function cf_get_partners( $ids = array() ) {
	$ids = array_filter( array_map( 'intval', (array) $ids ) );
	$args = array(
		'post_type'      => 'cf_partner',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		),
		'no_found_rows'  => true,
	);
	if ( $ids ) {
		$args['post__in'] = $ids;
		$args['orderby']  = 'post__in';
	}
	return get_posts( $args );
}

/**
 * Published items of a structured content type for a language.
 *
 * @param string $post_type Post type.
 * @param string $lang      Language.
 * @param array  $extra     Extra WP_Query args.
 * @return WP_Post[]
 */
function cf_get_items( $post_type, $lang, $extra = array() ) {
	$args = array_merge(
		array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
			'meta_query'     => array( CF_Languages::meta_query( $lang ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			'no_found_rows'  => true,
		),
		$extra
	);
	return get_posts( $args );
}

/**
 * Meta value helper.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key without the "_cf_" prefix.
 */
function cf_meta( $post_id, $key ) {
	return get_post_meta( $post_id, '_cf_' . $key, true );
}

/**
 * Allowed inline HTML in editable text fields.
 */
function cf_allowed_inline_html() {
	return array(
		'strong' => array(),
		'b'      => array(),
		'em'     => array(),
		'i'      => array(),
		'br'     => array(),
		'span'   => array( 'class' => true ),
		'a'      => array(
			'href'   => true,
			'target' => true,
			'rel'    => true,
			'style'  => true,
		),
	);
}

/**
 * Escapes inline HTML for output.
 *
 * @param string $html HTML.
 */
function cf_kses_inline( $html ) {
	return wp_kses( (string) $html, cf_allowed_inline_html() );
}
