<?php
/**
 * Legacy URLs of the static site → WordPress URLs, always with a single 301 to the final URL.
 *
 * Only paths on the current domain are handled here. Domain-level redirects
 * (fundacioncorcovado.org → corcovadofoundation.org, http → https, www) belong to the DNS /
 * hosting layer and are documented in docs/REDIRECT-MAP.md.
 *
 * @package CorcovadoFoundationCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Redirects.
 */
class CF_Redirects {

	/**
	 * Static-site slugs whose WordPress slug differs.
	 */
	private static function aliases() {
		return array(
			'es/virtual-library' => 'es/biblioteca-virtual',
		);
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 1 );
	}

	/**
	 * Path of the request relative to the site home, without leading/trailing slashes.
	 */
	private static function request_path() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}
		$path = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		$path = rawurldecode( $path );
		$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		$path = trim( $path, '/' );
		if ( '' !== $home && str_starts_with( $path, $home . '/' ) ) {
			$path = substr( $path, strlen( $home ) + 1 );
		}
		return $path;
	}

	/**
	 * Find the destination of a legacy path, or ''.
	 *
	 * @param string $path Relative path.
	 */
	public static function destination( $path ) {
		// /library/{file}.pdf and other original files → current file in the Media Library.
		if ( preg_match( '#^(library/[a-z0-9._\- ]+\.(pdf|docx?)|assets/[a-z0-9._\-/ ]+\.(pdf|webp|png|jpe?g|svg))$#i', $path ) ) {
			return self::file_destination( $path );
		}

		$path = strtolower( $path );

		// Pages: about-us.html, es/about-us.html, index.html, events-calendar.htm…
		if ( preg_match( '#^((?:es/)?)([a-z0-9\-]+)\.html?$#', $path, $m ) ) {
			$lang = 'es/' === $m[1] ? 'es' : 'en';
			if ( 'index' === $m[2] ) {
				return CF_Languages::home_url( $lang );
			}
			$target = $m[1] . $m[2];
		} elseif ( isset( self::aliases()[ $path ] ) ) {
			$target = $path;
		} else {
			return '';
		}
		$target = self::aliases()[ $target ] ?? $target;
		$page   = get_page_by_path( $target, OBJECT, 'page' );
		if ( $page && 'publish' === $page->post_status ) {
			return get_permalink( $page );
		}
		return '';
	}

	/**
	 * URL of an original file. A Library resource that was imported from this PDF wins, so
	 * replacing the file of the resource also updates the old /library/… link.
	 *
	 * @param string $path Relative path (original case).
	 */
	private static function file_destination( $path ) {
		if ( str_starts_with( strtolower( $path ), 'library/' ) ) {
			$res = get_posts(
				array(
					'post_type'      => 'cf_resource',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
						array(
							'key'     => '_cf_source_key',
							'value'   => array( 'resource:en:' . basename( $path ), 'resource:es:' . basename( $path ) ),
							'compare' => 'IN',
						),
					),
					'no_found_rows'  => true,
				)
			);
			if ( $res ) {
				$file = (string) get_post_meta( $res[0]->ID, '_cf_file', true );
				$url  = '' !== $file && function_exists( 'cf_url' ) ? cf_url( $file ) : '';
				// Only files on this site (wp_safe_redirect() refuses other hosts).
				if ( '' !== $url && '' !== wp_validate_redirect( $url, '' ) ) {
					return $url;
				}
			}
		}
		foreach ( array_unique( array( $path, strtolower( $path ) ) ) as $key ) {
			$ids = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => '_cf_source_key', // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
					'no_found_rows'  => true,
				)
			);
			if ( $ids ) {
				$url = wp_get_attachment_url( $ids[0] );
				return $url ? $url : '';
			}
		}
		return '';
	}

	/**
	 * Redirect when the request is a known legacy URL.
	 */
	public static function maybe_redirect() {
		if ( ! CF_Settings::get( 'legacy_redirects', 1 ) || ! is_404() ) {
			return;
		}
		$path = self::request_path();
		if ( '' === $path ) {
			return;
		}
		$dest = self::destination( $path );
		if ( '' === $dest ) {
			return;
		}
		$query = isset( $_SERVER['QUERY_STRING'] ) ? sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';
		if ( '' !== $query && ! str_contains( $dest, '?' ) ) {
			$dest .= '?' . $query;
		}
		wp_safe_redirect( $dest, 301, 'Corcovado Foundation' );
		exit;
	}
}
