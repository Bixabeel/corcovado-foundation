<?php
/**
 * SEO output: document title, meta description, Open Graph, Twitter Cards, hreflang,
 * robots, sitemap adjustments and optional Organization JSON-LD.
 *
 * Canonical URLs come from WordPress core (rel_canonical) and the sitemap is the core
 * sitemap (/wp-sitemap.xml), so there is a single source for each. Every URL is built from
 * home_url(): the domain is never hardcoded.
 *
 * @package CorcovadoFoundationCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * SEO.
 */
class CF_SEO {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'document_title' ), 20 );
		add_filter( 'document_title_separator', array( __CLASS__, 'separator' ) );
		add_filter( 'document_title_parts', array( __CLASS__, 'title_parts' ) );
		add_action( 'wp_head', array( __CLASS__, 'hreflang' ), 2 );
		add_action( 'wp_head', array( __CLASS__, 'meta_tags' ), 3 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 10, 2 );
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'sitemap_providers' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'sitemap_posts_args' ), 10, 2 );
	}

	/**
	 * Per-page SEO fields.
	 */
	public static function register_meta() {
		foreach ( array( 'page', 'cf_news' ) as $pt ) {
			foreach ( array( '_cf_seo_title', '_cf_seo_description' ) as $key ) {
				register_post_meta(
					$pt,
					$key,
					array(
						'type'              => 'string',
						'single'            => true,
						'sanitize_callback' => 'sanitize_text_field',
						'auth_callback'     => static fn( $allowed, $k, $post_id ) => current_user_can( 'edit_post', $post_id ),
					)
				);
			}
			register_post_meta(
				$pt,
				'_cf_og_image',
				array(
					'type'              => 'integer',
					'single'            => true,
					'sanitize_callback' => 'absint',
					'auth_callback'     => static fn( $allowed, $k, $post_id ) => current_user_can( 'edit_post', $post_id ),
				)
			);
		}
	}

	/**
	 * Is a dedicated SEO plugin active?
	 */
	public static function seo_plugin_active() {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_VERSION' );
	}

	/**
	 * Should this plugin print title/description/OG/Twitter tags?
	 */
	public static function enabled() {
		$mode = CF_Settings::get( 'seo_output', 'auto' );
		if ( 'off' === $mode ) {
			return false;
		}
		if ( 'auto' === $mode && self::seo_plugin_active() ) {
			return false;
		}
		return true;
	}

	/**
	 * Organization name per language.
	 *
	 * @param string $lang Language.
	 */
	public static function site_name( $lang ) {
		if ( 'es' === $lang ) {
			$name = (string) CF_Settings::get( 'site_name_es', '' );
			if ( '' !== $name ) {
				return $name;
			}
		}
		return (string) get_bloginfo( 'name' );
	}

	/**
	 * The original pages use " - " between page title and site name.
	 */
	public static function separator() {
		return '-';
	}

	/**
	 * Exact <title> for migrated pages.
	 *
	 * @param string $title Title.
	 */
	public static function document_title( $title ) {
		$id = is_singular() ? get_queried_object_id() : ( is_404() ? self::page_404_id() : 0 );
		if ( ! self::enabled() || ! $id ) {
			return $title;
		}
		$custom = (string) get_post_meta( $id, '_cf_seo_title', true );
		return '' !== $custom ? $custom : $title;
	}

	/**
	 * Use the Spanish organization name on Spanish URLs.
	 *
	 * @param array $parts Parts.
	 */
	public static function title_parts( $parts ) {
		if ( isset( $parts['site'] ) ) {
			$parts['site'] = self::site_name( CF_Languages::current() );
		}
		if ( isset( $parts['title'] ) && is_front_page() ) {
			unset( $parts['tagline'] );
		}
		return $parts;
	}

	/**
	 * Private page whose content is shown on "not found" errors in the current language.
	 */
	private static function page_404_id() {
		return (int) CF_Settings::get( 'page_404_' . CF_Languages::current(), 0 );
	}

	/**
	 * Meta description of the current request.
	 */
	public static function description() {
		if ( is_404() ) {
			$id = self::page_404_id();
			return $id ? trim( wp_strip_all_tags( (string) get_post_meta( $id, '_cf_seo_description', true ) ) ) : '';
		}
		if ( is_singular() ) {
			$id   = get_queried_object_id();
			$desc = (string) get_post_meta( $id, '_cf_seo_description', true );
			if ( '' === $desc && has_excerpt( $id ) ) {
				$desc = (string) get_the_excerpt( $id );
			}
			return trim( wp_strip_all_tags( $desc ) );
		}
		return '';
	}

	/**
	 * Share image URL of the current request.
	 */
	public static function image_url() {
		$id = 0;
		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			$id      = (int) get_post_meta( $post_id, '_cf_og_image', true );
			if ( ! $id && has_post_thumbnail( $post_id ) ) {
				$id = (int) get_post_thumbnail_id( $post_id );
			}
		}
		if ( ! $id ) {
			$id = (int) CF_Settings::get( 'default_og_image', 0 );
		}
		$url = $id ? wp_get_attachment_url( $id ) : '';
		return $url ? $url : '';
	}

	/**
	 * Canonical URL (same value WordPress prints in rel=canonical).
	 */
	public static function canonical() {
		if ( is_singular() ) {
			$url = wp_get_canonical_url( get_queried_object_id() );
			return $url ? $url : '';
		}
		return '';
	}

	/**
	 * hreflang + x-default. Printed even when an SEO plugin handles the other tags, because
	 * the language relations live in this plugin.
	 */
	public static function hreflang() {
		if ( is_404() || ! is_singular() ) {
			return;
		}
		$alts = CF_Languages::alternates();
		if ( count( $alts ) < 1 ) {
			return;
		}
		if ( isset( $alts['en'] ) ) {
			printf( '<link rel="alternate" hreflang="x-default" href="%s" />' . "\n", esc_url( $alts['en'] ) );
		}
		foreach ( array( 'es', 'en' ) as $lang ) {
			if ( isset( $alts[ $lang ] ) ) {
				printf( '<link rel="alternate" hreflang="%1$s" href="%2$s" />' . "\n", esc_attr( $lang ), esc_url( $alts[ $lang ] ) );
			}
		}
	}

	/**
	 * Description, Open Graph and Twitter tags.
	 */
	public static function meta_tags() {
		if ( ! self::enabled() ) {
			return;
		}
		if ( is_404() ) {
			// Like the static 404 pages: description only (no canonical, no share tags).
			$desc = self::description();
			if ( '' !== $desc ) {
				printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $desc ) );
			}
			return;
		}
		$lang  = CF_Languages::current();
		$langs = cf_languages();
		$title = wp_get_document_title();
		$desc  = self::description();
		$url   = self::canonical();
		$image = self::image_url();
		$type  = is_singular( 'cf_news' ) ? 'article' : 'website';

		if ( '' !== $desc ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $desc ) );
		}
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
		if ( '' !== $desc ) {
			printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $desc ) );
		}
		printf( '<meta property="og:type" content="%s" />' . "\n", esc_attr( $type ) );
		if ( '' !== $url ) {
			printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $url ) );
		}
		printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( self::site_name( $lang ) ) );
		printf( '<meta property="og:locale" content="%s" />' . "\n", esc_attr( $langs[ $lang ]['og'] ) );
		if ( '' !== $image ) {
			printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $image ) );
		}
		$alts = CF_Languages::alternates();
		foreach ( $langs as $code => $info ) {
			if ( $code !== $lang && isset( $alts[ $code ] ) ) {
				printf( '<meta property="og:locale:alternate" content="%s" />' . "\n", esc_attr( $info['og'] ) );
			}
		}
		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
		printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( $title ) );
		if ( '' !== $desc ) {
			printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $desc ) );
		}
		if ( '' !== $image ) {
			printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $image ) );
		}
		if ( CF_Settings::get( 'org_schema', 0 ) && ( is_front_page() || ( CF_Languages::es_root() && is_page( CF_Languages::es_root() ) ) ) ) {
			self::organization_schema( $lang );
		}
	}

	/**
	 * Organization JSON-LD built only from data already published on the site.
	 *
	 * @param string $lang Language.
	 */
	private static function organization_schema( $lang ) {
		$data = array(
			'@context' => 'https://schema.org',
			'@type'    => 'NGO',
			'name'     => self::site_name( $lang ),
			'url'      => CF_Languages::home_url( $lang ),
		);
		$logo = function_exists( 'cf_theme_logo_url' ) ? cf_theme_logo_url() : '';
		if ( $logo ) {
			$data['logo'] = $logo;
		}
		$email = (string) CF_Settings::get( 'form_recipient', '' );
		if ( is_email( $email ) ) {
			$data['email'] = $email;
		}
		/** Profiles listed in the footer (filterable by the theme). */
		$same = (array) apply_filters( 'cf_org_same_as', array() );
		if ( $same ) {
			$data['sameAs'] = array_values( array_map( 'esc_url_raw', $same ) );
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . "</script>\n";
	}

	/**
	 * Same robots directive as the static site: "index, follow, max-image-preview:large".
	 *
	 * @param array $robots Directives.
	 */
	public static function robots( $robots ) {
		if ( ! self::enabled() ) {
			return $robots;
		}
		if ( is_404() || is_search() ) {
			// Static 404 pages: noindex,follow.
			unset( $robots['index'], $robots['max-image-preview'] );
			$robots['noindex'] = true;
			$robots['follow']  = true;
			return $robots;
		}
		if ( ! get_option( 'blog_public' ) ) {
			return $robots;
		}
		return array_merge(
			array(
				'index'  => true,
				'follow' => true,
			),
			$robots
		);
	}

	/**
	 * Keep the original "Disallow: /library/" rule. WordPress adds the sitemap line itself.
	 *
	 * @param string $output Robots.txt.
	 * @param bool   $public Site is public.
	 */
	public static function robots_txt( $output, $public ) {
		if ( $public && ! str_contains( $output, 'Disallow: /library/' ) ) {
			$output = preg_replace( '/^(User-agent: \*\s*\n)/m', "$1Disallow: /library/\n", $output, 1 );
		}
		return $output;
	}

	/**
	 * No author archives in the sitemap (the site has none).
	 *
	 * @param WP_Sitemaps_Provider $provider Provider.
	 * @param string               $name     Name.
	 */
	public static function sitemap_providers( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	}

	/**
	 * Keep the 404 content pages (private) out of the sitemap.
	 *
	 * @param array  $args      Query args.
	 * @param string $post_type Post type.
	 */
	public static function sitemap_posts_args( $args, $post_type ) {
		if ( 'page' === $post_type ) {
			$exclude = array_filter( array( (int) CF_Settings::get( 'page_404_en', 0 ), (int) CF_Settings::get( 'page_404_es', 0 ) ) );
			if ( $exclude ) {
				$args['post__not_in'] = array_merge( $args['post__not_in'] ?? array(), $exclude );
			}
		}
		return $args;
	}
}
