<?php
/**
 * Corcovado Foundation theme.
 *
 * @package CorcovadoFoundation
 */

defined( 'ABSPATH' ) || exit;

define( 'CF_THEME_VERSION', '1.0.0' );

require_once get_template_directory() . '/inc/template-tags.php';
require_once get_template_directory() . '/inc/dynamic.php';
require_once get_template_directory() . '/inc/customizer.php';

/**
 * Is the companion plugin active?
 */
function cf_core_active() {
	return class_exists( 'CF_Layout' );
}

/**
 * Theme setup.
 */
function cf_theme_setup() {
	load_theme_textdomain( 'corcovado-foundation', get_template_directory() . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	register_nav_menus(
		array(
			'primary_en'  => __( 'Main menu (English)', 'corcovado-foundation' ),
			'primary_es'  => __( 'Main menu (Spanish)', 'corcovado-foundation' ),
			'footer_1_en' => __( 'Footer column 1 (English)', 'corcovado-foundation' ),
			'footer_2_en' => __( 'Footer column 2 (English)', 'corcovado-foundation' ),
			'footer_3_en' => __( 'Footer column 3 (English)', 'corcovado-foundation' ),
			'footer_1_es' => __( 'Footer column 1 (Spanish)', 'corcovado-foundation' ),
			'footer_2_es' => __( 'Footer column 2 (Spanish)', 'corcovado-foundation' ),
			'footer_3_es' => __( 'Footer column 3 (Spanish)', 'corcovado-foundation' ),
		)
	);
}
add_action( 'after_setup_theme', 'cf_theme_setup' );

/**
 * The static site never converted emoji to images (donation icons 📚 🌱 🐢 🛡️ must stay text).
 */
function cf_disable_emoji() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
}
add_action( 'init', 'cf_disable_emoji' );

/**
 * Asset URL with cache busting by file modification time (keeps the original file names).
 *
 * @param string $rel Path relative to the theme.
 */
function cf_asset_version( $rel ) {
	$file = get_template_directory() . '/' . $rel;
	return file_exists( $file ) ? (string) filemtime( $file ) : CF_THEME_VERSION;
}

/**
 * Styles and scripts. Only what each page of the original site loaded is loaded here.
 */
function cf_enqueue_assets() {
	$uri     = get_template_directory_uri();
	$page_id = is_singular( 'page' ) ? get_queried_object_id() : 0;
	if ( ! cf_core_active() ) {
		$page_id = 0;
	} elseif ( is_404() ) {
		$page_id = (int) cf_setting( 'page_404_' . cf_current_lang(), 0 );
	}
	$layout   = ( $page_id && cf_core_active() ) ? cf_get_layout( $page_id ) : null;
	$settings = $layout ? $layout['settings'] : array();

	if ( ! empty( $settings['fonts'] ) ) {
		wp_enqueue_style( 'cf-fonts', 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Inter:wght@400;600;700;800;900&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceVersion.MissingVersion -- Google Fonts URL must stay unversioned.
	}
	wp_enqueue_style( 'cf-styles', $uri . '/assets/css/styles-20260929.css', array(), cf_asset_version( 'assets/css/styles-20260929.css' ) );
	wp_enqueue_style( 'cf-wp', $uri . '/assets/css/wp.css', array( 'cf-styles' ), cf_asset_version( 'assets/css/wp.css' ) );

	$has_team = $page_id && CF_Layout::has_dynamic( $page_id, 'team' );
	if ( $has_team ) {
		wp_enqueue_style( 'cf-team', $uri . '/assets/css/team.css', array( 'cf-styles' ), cf_asset_version( 'assets/css/team.css' ) );
	}

	$has_donation = $page_id && CF_Layout::has_marker( $page_id, 'data-donation-flow' );
	if ( $has_donation ) {
		wp_enqueue_script( 'cf-donation', $uri . '/assets/js/donation-embed.js', array(), cf_asset_version( 'assets/js/donation-embed.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_add_inline_script( 'cf-donation', 'window.CF_DONATION=' . wp_json_encode( array( 'campaignId' => (string) cf_setting( 'classy_campaign_id', '' ) ) ) . ';', 'before' );
	}

	wp_enqueue_script( 'cf-main', $uri . '/assets/js/main.js', array(), cf_asset_version( 'assets/js/main.js' ), array( 'in_footer' => true ) );

	if ( $page_id && ( CF_Layout::has_marker( $page_id, 'data-contact-form' ) || CF_Layout::has_marker( $page_id, 'data-volunteer-form' ) ) ) {
		wp_enqueue_script( 'cf-forms', $uri . '/assets/js/forms.js', array(), cf_asset_version( 'assets/js/forms.js' ), array( 'in_footer' => true ) );
		$token = CF_Forms::client_token();
		$email = cf_public_email();
		wp_localize_script(
			'cf-forms',
			'CF_FORMS',
			array(
				'contactUrl'   => esc_url_raw( rest_url( 'cf/v1/contact' ) ),
				'volunteerUrl' => esc_url_raw( rest_url( 'cf/v1/volunteer' ) ),
				'tokenUrl'     => esc_url_raw( rest_url( 'cf/v1/form-token' ) ),
				'nonce'        => $token['nonce'],
				'token'        => $token['token'],
				'messages'     => array(
					'required'         => __( 'Please complete the required fields before continuing.', 'corcovado-foundation' ),
					'sending'          => __( 'Sending…', 'corcovado-foundation' ),
					'contactSuccess'   => __( 'Thank you! Your message has been sent.', 'corcovado-foundation' ),
					'volunteerSuccess' => __( 'Thank you! Your application has been sent. We will get back to you within 48 hours.', 'corcovado-foundation' ),
					/* translators: %s: public email address */
					'error'            => sprintf( __( 'The message could not be sent. Please try again or write to us at %s.', 'corcovado-foundation' ), $email ),
					/* translators: %s: public email address */
					'rate'             => sprintf( __( 'Too many messages were sent from this connection. Please try again later or write to us at %s.', 'corcovado-foundation' ), $email ),
				),
			)
		);
	}

	if ( $has_team ) {
		wp_enqueue_script( 'cf-team', $uri . '/assets/js/team.js', array(), cf_asset_version( 'assets/js/team.js' ), array( 'in_footer' => true ) );
		// TEAM_MEMBERS is added by the team renderer (inc/dynamic.php) while the page is built.
	}

	// The approved pages do not use block styles; keep them only where block content is shown.
	if ( ! is_singular( 'cf_news' ) && ! ( is_singular( 'page' ) && ! $layout ) ) {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'classic-theme-styles' );
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'core-block-supports' );
	}
}
add_action( 'wp_enqueue_scripts', 'cf_enqueue_assets', 20 );

/**
 * Editor styles for News (so the editor resembles the front end).
 */
function cf_editor_styles() {
	add_editor_style( 'assets/css/editor.css' );
}
add_action( 'after_setup_theme', 'cf_editor_styles' );

/**
 * The plugin is required: show a notice in the admin when it is missing.
 */
function cf_require_plugin_notice() {
	if ( cf_core_active() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>' . esc_html__( 'The Corcovado Foundation theme needs the "Corcovado Foundation Core" plugin. Please install and activate it.', 'corcovado-foundation' ) . '</p></div>';
}
add_action( 'admin_notices', 'cf_require_plugin_notice' );

/**
 * Pages built from the original site use their own markup; no automatic paragraphs elsewhere either.
 */
add_filter( 'cf_org_same_as', static fn() => array_filter( array( (string) get_theme_mod( 'cf_social_facebook', '' ) ) ) );
