<?php
/**
 * English / Spanish support without third-party plugins.
 *
 * Model (see WORDPRESS-MIGRATION-AUDIT.md §19):
 * - English pages are top-level pages; the English home is the WordPress front page ("/").
 * - The Spanish home is a page with the slug "es" ("/es/"); every Spanish page is one of its
 *   descendants ("/es/about-us"). WordPress allows the same slug under different parents, so
 *   both languages keep identical slugs without rewrite tricks.
 * - Structured content stores its language in the "_cf_lang" meta.
 * - Translations are linked both ways through the "_cf_translation" meta.
 *
 * @package CorcovadoFoundationCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Languages.
 */
class CF_Languages {

	const META_LANG  = '_cf_lang';
	const META_TRANS = '_cf_translation';

	/**
	 * Cached current language.
	 *
	 * @var string|null
	 */
	private static $current = null;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'locale', array( __CLASS__, 'frontend_locale' ) );
		add_filter( 'language_attributes', array( __CLASS__, 'language_attributes' ), 20 );
		add_filter( 'page_link', array( __CLASS__, 'page_link' ), 10, 2 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'redirect_canonical' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'es_root_slash' ), 5 );
		add_filter( 'post_type_link', array( __CLASS__, 'post_type_link' ), 10, 2 );
		add_filter( 'wp_unique_post_slug', array( __CLASS__, 'unique_slug' ), 10, 6 );
		add_action( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ) );
		add_action( 'save_post', array( __CLASS__, 'sync_page_language' ), 20, 2 );
		add_action( 'before_delete_post', array( __CLASS__, 'unlink_on_delete' ) );
		add_action( 'wp', array( __CLASS__, 'reset_current' ) );
	}

	/**
	 * Rewrite rule for Spanish news: /es/news/{slug}.
	 */
	public static function add_rewrite_rules() {
		add_rewrite_rule( '^es/news/([^/]+)/?$', 'index.php?cf_news=$matches[1]&cf_lang=es', 'top' );
	}

	/**
	 * Query vars.
	 *
	 * @param array $vars Vars.
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'cf_lang';
		return $vars;
	}

	/**
	 * Spanish home page ID.
	 */
	public static function es_root() {
		return (int) CF_Settings::get( 'es_root', 0 );
	}

	/**
	 * Meta query fragment for a language. English also matches content without language meta.
	 *
	 * @param string $lang Language.
	 */
	public static function meta_query( $lang ) {
		if ( 'es' === $lang ) {
			return array(
				'key'   => self::META_LANG,
				'value' => 'es',
			);
		}
		return array(
			'relation' => 'OR',
			array(
				'key'   => self::META_LANG,
				'value' => 'en',
			),
			array(
				'key'     => self::META_LANG,
				'compare' => 'NOT EXISTS',
			),
		);
	}

	/**
	 * Language of a post.
	 *
	 * @param int|WP_Post|null $post Post.
	 */
	public static function lang_of( $post = null ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return self::current();
		}
		if ( 'page' === $post->post_type ) {
			$root = self::es_root();
			if ( $root && ( (int) $post->ID === $root || in_array( $root, array_map( 'intval', get_post_ancestors( $post ) ), true ) ) ) {
				return 'es';
			}
			$meta = get_post_meta( $post->ID, self::META_LANG, true );
			return 'es' === $meta && ! $root ? 'es' : 'en';
		}
		$meta = get_post_meta( $post->ID, self::META_LANG, true );
		return 'es' === $meta ? 'es' : 'en';
	}

	/**
	 * Language from the request path (works before the main query runs).
	 */
	public static function lang_from_request() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return 'en';
		}
		$path      = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		$home_path = (string) wp_parse_url( get_option( 'home' ), PHP_URL_PATH );
		$home_path = '/' . trim( $home_path, '/' );
		$rel       = '/' . ltrim( substr( $path, strlen( rtrim( $home_path, '/' ) ) ), '/' );
		return ( '/es' === rtrim( $rel, '/' ) || str_starts_with( $rel, '/es/' ) ) ? 'es' : 'en';
	}

	/**
	 * Current language.
	 */
	public static function current() {
		if ( null !== self::$current ) {
			return self::$current;
		}
		$lang = null;
		if ( did_action( 'wp' ) ) {
			if ( is_singular() ) {
				$obj = get_queried_object();
				if ( $obj instanceof WP_Post ) {
					$lang = self::lang_of( $obj );
				}
			}
			if ( null === $lang && 'es' === get_query_var( 'cf_lang' ) ) {
				$lang = 'es';
			}
		}
		if ( null === $lang ) {
			$lang = self::lang_from_request();
		}
		if ( did_action( 'wp' ) ) {
			self::$current = $lang;
		}
		return $lang;
	}

	/**
	 * Recompute after the main query.
	 */
	public static function reset_current() {
		self::$current = null;
	}

	/**
	 * Load Spanish translations of the theme/plugin on Spanish front-end requests.
	 *
	 * @param string $locale Locale.
	 */
	public static function frontend_locale( $locale ) {
		if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return $locale;
		}
		return 'es' === self::lang_from_request() ? 'es_ES' : $locale;
	}

	/**
	 * <html lang="en"> / <html lang="es"> exactly like the static site.
	 *
	 * @param string $output Attributes.
	 */
	public static function language_attributes( $output ) {
		if ( is_admin() ) {
			return $output;
		}
		$output = preg_replace( '/lang="[^"]*"/', 'lang="' . esc_attr( self::current() ) . '"', $output );
		return $output;
	}

	/**
	 * Home URL of a language.
	 *
	 * @param string $lang Language.
	 */
	public static function home_url( $lang ) {
		if ( 'es' === $lang ) {
			$root = self::es_root();
			if ( $root && 'publish' === get_post_status( $root ) ) {
				return get_permalink( $root );
			}
			return home_url( '/es/' );
		}
		return home_url( '/' );
	}

	/**
	 * The Spanish home keeps its trailing slash: /es/.
	 *
	 * @param string $link    Link.
	 * @param int    $post_id Page ID.
	 */
	public static function page_link( $link, $post_id ) {
		if ( (int) $post_id === self::es_root() && (int) get_option( 'page_on_front' ) !== (int) $post_id ) {
			return trailingslashit( $link );
		}
		return $link;
	}

	/**
	 * "/es/" is the canonical address of the Spanish home (WordPress would strip the slash).
	 *
	 * @param string|false $redirect  Redirect URL.
	 * @param string       $requested Requested URL.
	 */
	public static function redirect_canonical( $redirect, $requested ) {
		$root = self::es_root();
		if ( $root && is_page( $root ) ) {
			$target = get_permalink( $root );
			$path   = (string) wp_parse_url( $requested, PHP_URL_PATH );
			return str_ends_with( $path, '/' ) ? false : $target;
		}
		return $redirect;
	}

	/**
	 * Redirect "/es" to "/es/" with a single 301.
	 */
	public static function es_root_slash() {
		$root = self::es_root();
		if ( ! $root || ! is_page( $root ) || (int) get_option( 'page_on_front' ) === $root || empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$path = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		if ( ! str_ends_with( $path, '/' ) ) {
			wp_safe_redirect( get_permalink( $root ), 301 );
			exit;
		}
	}

	/**
	 * Spanish news live at /es/news/{slug}.
	 *
	 * @param string  $link Link.
	 * @param WP_Post $post Post.
	 */
	public static function post_type_link( $link, $post ) {
		if ( 'cf_news' === $post->post_type && 'es' === self::lang_of( $post ) && '' !== $post->post_name && get_option( 'permalink_structure' ) ) {
			return user_trailingslashit( home_url( '/es/news/' . $post->post_name ) );
		}
		return $link;
	}

	/**
	 * Allow the same news slug in English and Spanish.
	 *
	 * @param string $slug          Unique slug.
	 * @param int    $post_id       Post ID.
	 * @param string $post_status   Status.
	 * @param string $post_type     Type.
	 * @param int    $post_parent   Parent.
	 * @param string $original_slug Requested slug.
	 */
	public static function unique_slug( $slug, $post_id, $post_status, $post_type, $post_parent, $original_slug ) {
		if ( 'cf_news' !== $post_type || $slug === $original_slug ) {
			return $slug;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only use during a save already nonce-checked by core.
		$lang = isset( $_POST['cf_lang'] ) ? sanitize_key( wp_unslash( $_POST['cf_lang'] ) ) : self::lang_of( $post_id );
		$lang = 'es' === $lang ? 'es' : 'en';
		$same = get_posts(
			array(
				'post_type'      => 'cf_news',
				'name'           => $original_slug,
				'post_status'    => 'any',
				'posts_per_page' => 5,
				'fields'         => 'ids',
				'post__not_in'   => array( (int) $post_id ),
				'no_found_rows'  => true,
			)
		);
		foreach ( $same as $other ) {
			if ( self::lang_of( $other ) === $lang ) {
				return $slug;
			}
		}
		return $original_slug;
	}

	/**
	 * Resolve /news/{slug} and /es/news/{slug} to the right language.
	 *
	 * @param WP_Query $q Query.
	 */
	public static function pre_get_posts( $q ) {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( $q->get( 'cf_news' ) || 'cf_news' === $q->get( 'post_type' ) ) {
			$lang = 'es' === $q->get( 'cf_lang' ) ? 'es' : 'en';
			$mq   = (array) $q->get( 'meta_query' );
			$mq[] = self::meta_query( $lang );
			$q->set( 'meta_query', $mq );
		}
	}

	/**
	 * Keep "_cf_lang" of pages in sync with their position in the hierarchy.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function sync_page_language( $post_id, $post ) {
		if ( 'page' !== $post->post_type || wp_is_post_revision( $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, self::META_LANG, self::lang_of( $post ) );
	}

	/**
	 * Translation of a post (only if it still exists).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function translation_of( $post_id ) {
		$other = (int) get_post_meta( $post_id, self::META_TRANS, true );
		if ( ! $other || $other === $post_id ) {
			return 0;
		}
		$status = get_post_status( $other );
		if ( ! $status || 'trash' === $status ) {
			return 0;
		}
		return $other;
	}

	/**
	 * Link two posts as translations (and unlink their previous partners).
	 *
	 * @param int $a Post ID.
	 * @param int $b Post ID (0 to unlink).
	 */
	public static function link( $a, $b ) {
		$a   = (int) $a;
		$b   = (int) $b;
		$old = (int) get_post_meta( $a, self::META_TRANS, true );
		if ( $old && $old !== $b ) {
			delete_post_meta( $old, self::META_TRANS );
		}
		if ( ! $b ) {
			delete_post_meta( $a, self::META_TRANS );
			return;
		}
		$old_b = (int) get_post_meta( $b, self::META_TRANS, true );
		if ( $old_b && $old_b !== $a ) {
			delete_post_meta( $old_b, self::META_TRANS );
		}
		update_post_meta( $a, self::META_TRANS, $b );
		update_post_meta( $b, self::META_TRANS, $a );
	}

	/**
	 * Remove the link when a post is deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function unlink_on_delete( $post_id ) {
		$other = (int) get_post_meta( $post_id, self::META_TRANS, true );
		if ( $other && (int) get_post_meta( $other, self::META_TRANS, true ) === (int) $post_id ) {
			delete_post_meta( $other, self::META_TRANS );
		}
	}

	/**
	 * URL of the current content in another language. Falls back to that language's home
	 * only when the content has no published translation.
	 *
	 * @param string $lang Target language.
	 */
	public static function switch_url( $lang ) {
		if ( is_singular() ) {
			$id = (int) get_queried_object_id();
			if ( self::lang_of( $id ) === $lang ) {
				return get_permalink( $id );
			}
			$other = self::translation_of( $id );
			if ( $other && 'publish' === get_post_status( $other ) ) {
				return get_permalink( $other );
			}
		}
		if ( is_front_page() && 'en' === $lang ) {
			return home_url( '/' );
		}
		return self::home_url( $lang );
	}

	/**
	 * Alternate URLs for hreflang: [ 'en' => url, 'es' => url ] (only existing ones).
	 */
	public static function alternates() {
		$out = array();
		if ( is_singular() ) {
			$id              = (int) get_queried_object_id();
			$lang            = self::lang_of( $id );
			$out[ $lang ]    = get_permalink( $id );
			$other           = self::translation_of( $id );
			if ( $other && 'publish' === get_post_status( $other ) ) {
				$out[ 'es' === $lang ? 'en' : 'es' ] = get_permalink( $other );
			}
		}
		return $out;
	}
}
