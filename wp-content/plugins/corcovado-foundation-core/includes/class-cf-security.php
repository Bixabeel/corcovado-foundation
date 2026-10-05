<?php
/**
 * Security headers (same policy as the static site's _headers file) and light hardening
 * that does not change the front end.
 *
 * @package CorcovadoFoundationCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Security.
 */
class CF_Security {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'send_headers', array( __CLASS__, 'headers' ) );
		add_filter( 'xmlrpc_enabled', '__return_false' );
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );
		add_filter( 'rest_endpoints', array( __CLASS__, 'hide_user_endpoints' ) );
		add_action( 'template_redirect', array( __CLASS__, 'block_author_scan' ), 0 );
	}

	/**
	 * Same headers as the static site (_headers). HSTS is only sent over HTTPS and without
	 * includeSubDomains: the domain owner should decide that at the DNS/hosting level.
	 */
	public static function headers() {
		if ( is_admin() || ! CF_Settings::get( 'security_headers', 1 ) || headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		if ( is_ssl() ) {
			/**
			 * Filters the Strict-Transport-Security value ('' disables it).
			 *
			 * @param string $value Header value.
			 */
			$hsts = (string) apply_filters( 'cf_hsts', 'max-age=31536000' );
			if ( '' !== $hsts ) {
				header( 'Strict-Transport-Security: ' . $hsts );
			}
		}
	}

	/**
	 * Do not list site users to anonymous visitors through the REST API.
	 *
	 * @param array $endpoints Endpoints.
	 */
	public static function hide_user_endpoints( $endpoints ) {
		if ( ! is_user_logged_in() ) {
			unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}

	/**
	 * The site has no author pages: ?author=N and /author/name return 404 instead of
	 * revealing user names.
	 */
	public static function block_author_scan() {
		if ( is_author() || ( isset( $_GET['author'] ) && ! is_admin() ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}
}
