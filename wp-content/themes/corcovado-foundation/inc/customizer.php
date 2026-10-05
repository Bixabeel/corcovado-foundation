<?php
/**
 * Header and footer texts (Appearance → Customize → Header & footer).
 * Defaults are the texts of the approved static site.
 *
 * @package CorcovadoFoundation
 */

defined( 'ABSPATH' ) || exit;

/**
 * Per-language texts: key => [label, EN default, ES default, type].
 */
function cf_text_mods() {
	return array(
		'donate_label'    => array( __( 'Donate button text', 'corcovado-foundation' ), 'Donate Now', 'Donar Ahora', 'text' ),
		'donate_url'      => array( __( 'Donate button link', 'corcovado-foundation' ), '/donate-now', '/es/donate-now', 'link' ),
		'brand_alt'       => array( __( 'Logo description (alt)', 'corcovado-foundation' ), 'Corcovado Foundation Logo', 'Logo Fundación Corcovado', 'text' ),
		'menu_open_label' => array( __( 'Mobile menu button label', 'corcovado-foundation' ), 'Open menu', 'Abrir menú', 'text' ),
		'nav_label'       => array( __( 'Main navigation label', 'corcovado-foundation' ), 'Main navigation', 'Navegación principal', 'text' ),
		'lang_label'      => array( __( 'Language selector label', 'corcovado-foundation' ), 'Language selector', 'Selector de idioma', 'text' ),
		'skip_label'      => array( __( '"Skip to content" link', 'corcovado-foundation' ), 'Skip to content', 'Saltar al contenido', 'text' ),
		'footer_alt'      => array( __( 'Footer logo description (alt)', 'corcovado-foundation' ), 'Corcovado Foundation', 'Fundación Corcovado', 'text' ),
		'footer_text'     => array( __( 'Footer text', 'corcovado-foundation' ), 'Conservation, education, and community partnership in Costa Rica.', 'Conservación, educación y alianzas comunitarias en Costa Rica.', 'text' ),
		'footer_col_1'    => array( __( 'Footer column 1 title', 'corcovado-foundation' ), 'Explore', 'Explorar', 'text' ),
		'footer_col_2'    => array( __( 'Footer column 2 title', 'corcovado-foundation' ), 'Donate', 'Donar', 'text' ),
		'footer_col_3'    => array( __( 'Footer column 3 title', 'corcovado-foundation' ), 'Languages', 'Idiomas', 'text' ),
		'copyright'       => array( __( 'Copyright line', 'corcovado-foundation' ), '© 2026 Corcovado Foundation. All rights reserved', '© 2026 Fundación Corcovado. Todos los derechos reservados', 'text' ),
		'credit_label'    => array( __( 'Credit link text', 'corcovado-foundation' ), 'Abundia Tech Design', 'Abundia Tech Design', 'text' ),
		'credit_url'      => array( __( 'Credit link', 'corcovado-foundation' ), 'https://abundia.io/', 'https://abundia.io/', 'link' ),
		'top_label'       => array( __( '"Back to top" link', 'corcovado-foundation' ), 'Back to top', 'Volver arriba', 'text' ),
		'social_label_email' => array( __( 'Email icon label', 'corcovado-foundation' ), 'Email', 'Email', 'text' ),
	);
}

/**
 * Shared (language-neutral) settings.
 */
function cf_shared_mods() {
	return array(
		'social_facebook'  => array( __( 'Facebook URL', 'corcovado-foundation' ), 'https://www.facebook.com/funcorco/' ),
		'social_instagram' => array( __( 'Instagram URL', 'corcovado-foundation' ), 'https://instagram.com' ),
		'social_email'     => array( __( 'Email link (mailto:)', 'corcovado-foundation' ), 'mailto:info@corcovadofoundation.org' ),
	);
}

/**
 * Read a per-language text.
 *
 * @param string      $key  Key.
 * @param string|null $lang Language.
 */
function cf_mod( $key, $lang = null ) {
	$lang = $lang ?? ( function_exists( 'cf_current_lang' ) ? cf_current_lang() : 'en' );
	$defs = cf_text_mods();
	$def  = isset( $defs[ $key ] ) ? $defs[ $key ][ 'es' === $lang ? 2 : 1 ] : '';
	return (string) get_theme_mod( 'cf_' . $key . '_' . $lang, $def );
}

/**
 * Read a shared setting.
 *
 * @param string $key Key.
 */
function cf_shared_mod( $key ) {
	$defs = cf_shared_mods();
	return (string) get_theme_mod( 'cf_' . $key, $defs[ $key ][1] ?? '' );
}

/**
 * Public contact email (from the footer email icon), used in form error messages.
 */
function cf_public_email() {
	$mail = cf_shared_mod( 'social_email' );
	$mail = preg_replace( '/^mailto:/i', '', $mail );
	return is_email( $mail ) ? $mail : '';
}

/**
 * Sanitize a link setting (same formats as page sections).
 *
 * @param string $value Value.
 */
function cf_sanitize_link_mod( $value ) {
	return class_exists( 'CF_Layout' ) ? CF_Layout::sanitize_link( $value ) : esc_url_raw( $value );
}

/**
 * Register Customizer controls.
 *
 * @param WP_Customize_Manager $wp_customize Manager.
 */
function cf_customize_register( $wp_customize ) {
	$wp_customize->add_panel(
		'cf_chrome',
		array(
			'title'    => __( 'Header & footer', 'corcovado-foundation' ),
			'priority' => 30,
		)
	);
	foreach ( array(
		'en' => __( 'English texts', 'corcovado-foundation' ),
		'es' => __( 'Spanish texts', 'corcovado-foundation' ),
	) as $lang => $title ) {
		$wp_customize->add_section(
			'cf_chrome_' . $lang,
			array(
				'title' => $title,
				'panel' => 'cf_chrome',
			)
		);
		foreach ( cf_text_mods() as $key => $def ) {
			$id = 'cf_' . $key . '_' . $lang;
			$wp_customize->add_setting(
				$id,
				array(
					'default'           => $def[ 'es' === $lang ? 2 : 1 ],
					'sanitize_callback' => 'link' === $def[3] ? 'cf_sanitize_link_mod' : 'sanitize_text_field',
				)
			);
			$wp_customize->add_control(
				$id,
				array(
					'label'   => $def[0],
					'section' => 'cf_chrome_' . $lang,
					'type'    => 'text',
				)
			);
		}
	}
	$wp_customize->add_section(
		'cf_chrome_social',
		array(
			'title'       => __( 'Social links', 'corcovado-foundation' ),
			'panel'       => 'cf_chrome',
			'description' => __( 'Menus of the header and footer columns are edited in Appearance → Menus.', 'corcovado-foundation' ),
		)
	);
	foreach ( cf_shared_mods() as $key => $def ) {
		$wp_customize->add_setting(
			'cf_' . $key,
			array(
				'default'           => $def[1],
				'sanitize_callback' => static fn( $v ) => esc_url_raw( $v, array( 'http', 'https', 'mailto' ) ),
			)
		);
		$wp_customize->add_control(
			'cf_' . $key,
			array(
				'label'   => $def[0],
				'section' => 'cf_chrome_social',
				'type'    => 'url',
			)
		);
	}
}
add_action( 'customize_register', 'cf_customize_register' );
