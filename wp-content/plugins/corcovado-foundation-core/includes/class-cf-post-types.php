<?php
/**
 * Structured content types. Registered in the plugin (not the theme) so the content
 * survives a theme change.
 *
 * @package CorcovadoFoundationCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post types, taxonomies and meta.
 */
class CF_Post_Types {

	/**
	 * Meta fields per post type: key => sanitize type.
	 */
	public static function fields() {
		return array(
			'cf_news'     => array(
				'short_title'   => 'text',
				'short_excerpt' => 'textarea',
			),
			'cf_event'    => array(
				'start_date'  => 'date',
				'end_date'    => 'date',
				'time'        => 'text',
				'location'    => 'text',
				'date_label'  => 'text',
				'description' => 'textarea',
				'link_label'  => 'text',
				'link_url'    => 'url',
			),
			'cf_team'     => array(
				'role'       => 'text',
				'name_note'  => 'text',
				'summary'    => 'textarea',
				'bio'        => 'textarea',
				'modal_name' => 'text',
				'modal_role' => 'text',
				'photo_alt'  => 'text',
				'photo_fallback' => 'bool',
			),
			'cf_resource' => array(
				'description' => 'textarea',
				'file'        => 'url',
				'file_info'   => 'text',
				'pub_date'    => 'date',
				'date_label'  => 'text',
				'featured'    => 'bool',
				'thumb_alt'   => 'text',
			),
			'cf_partner'  => array(
				'url'              => 'url',
				'logo_alt'         => 'text',
				'sponsor_category' => 'key',
			),
		);
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register everything.
	 */
	public static function register() {
		$common = array(
			'show_ui'       => true,
			'show_in_menu'  => true,
			'menu_position' => 21,
			'map_meta_cap'  => true,
		);

		register_post_type(
			'cf_news',
			array_merge(
				$common,
				array(
					'labels'             => self::labels( __( 'News', 'corcovado-foundation-core' ), __( 'News story', 'corcovado-foundation-core' ) ),
					'public'             => true,
					'publicly_queryable' => true,
					'has_archive'        => false,
					'rewrite'            => array(
						'slug'       => 'news',
						'with_front' => false,
					),
					'menu_icon'          => 'dashicons-megaphone',
					'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ),
					'show_in_rest'       => true,
				)
			)
		);

		register_post_type(
			'cf_event',
			array_merge(
				$common,
				array(
					'labels'              => self::labels( __( 'Events', 'corcovado-foundation-core' ), __( 'Event', 'corcovado-foundation-core' ) ),
					'public'              => false,
					'publicly_queryable'  => false,
					'exclude_from_search' => true,
					'menu_icon'           => 'dashicons-calendar-alt',
					'supports'            => array( 'title', 'thumbnail', 'page-attributes' ),
				)
			)
		);

		register_post_type(
			'cf_team',
			array_merge(
				$common,
				array(
					'labels'              => self::labels( __( 'Team', 'corcovado-foundation-core' ), __( 'Team member', 'corcovado-foundation-core' ) ),
					'public'              => false,
					'publicly_queryable'  => false,
					'exclude_from_search' => true,
					'menu_icon'           => 'dashicons-groups',
					'supports'            => array( 'title', 'thumbnail', 'page-attributes' ),
				)
			)
		);

		register_post_type(
			'cf_resource',
			array_merge(
				$common,
				array(
					'labels'              => self::labels( __( 'Library', 'corcovado-foundation-core' ), __( 'Resource', 'corcovado-foundation-core' ) ),
					'public'              => false,
					'publicly_queryable'  => false,
					'exclude_from_search' => true,
					'menu_icon'           => 'dashicons-media-document',
					'supports'            => array( 'title', 'thumbnail', 'page-attributes' ),
				)
			)
		);

		register_post_type(
			'cf_partner',
			array_merge(
				$common,
				array(
					'labels'              => self::labels( __( 'Partners', 'corcovado-foundation-core' ), __( 'Partner', 'corcovado-foundation-core' ) ),
					'public'              => false,
					'publicly_queryable'  => false,
					'exclude_from_search' => true,
					'menu_icon'           => 'dashicons-heart',
					'supports'            => array( 'title', 'thumbnail', 'page-attributes' ),
				)
			)
		);

		$tax = array(
			'hierarchical'       => true,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'show_in_rest'       => true,
			'rewrite'            => false,
		);
		register_taxonomy( 'cf_news_category', 'cf_news', array_merge( $tax, array( 'labels' => self::tax_labels( __( 'News categories', 'corcovado-foundation-core' ), __( 'News category', 'corcovado-foundation-core' ) ) ) ) );
		register_taxonomy( 'cf_team_group', 'cf_team', array_merge( $tax, array( 'labels' => self::tax_labels( __( 'Team groups', 'corcovado-foundation-core' ), __( 'Team group', 'corcovado-foundation-core' ) ) ) ) );
		register_taxonomy( 'cf_resource_category', 'cf_resource', array_merge( $tax, array( 'labels' => self::tax_labels( __( 'Resource categories', 'corcovado-foundation-core' ), __( 'Resource category', 'corcovado-foundation-core' ) ) ) ) );

		foreach ( array( 'cf_news_category', 'cf_team_group', 'cf_resource_category' ) as $t ) {
			register_term_meta(
				$t,
				'_cf_lang',
				array(
					'type'              => 'string',
					'single'            => true,
					'sanitize_callback' => static fn( $v ) => 'es' === $v ? 'es' : 'en',
					'auth_callback'     => static fn() => current_user_can( 'manage_categories' ),
				)
			);
			register_term_meta(
				$t,
				'_cf_order',
				array(
					'type'              => 'integer',
					'single'            => true,
					'sanitize_callback' => static fn( $v ) => (int) $v,
					'auth_callback'     => static fn() => current_user_can( 'manage_categories' ),
				)
			);
		}

		foreach ( array( 'cf_news', 'cf_event', 'cf_team', 'cf_resource', 'page' ) as $pt ) {
			register_post_meta(
				$pt,
				'_cf_lang',
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => 'cf_news' === $pt,
					'sanitize_callback' => static fn( $v ) => 'es' === $v ? 'es' : 'en',
					'auth_callback'     => static fn( $allowed, $key, $post_id ) => current_user_can( 'edit_post', $post_id ),
				)
			);
		}
	}

	/**
	 * Post type labels.
	 *
	 * @param string $plural   Plural.
	 * @param string $singular Singular.
	 */
	private static function labels( $plural, $singular ) {
		return array(
			'name'               => $plural,
			'singular_name'      => $singular,
			'menu_name'          => $plural,
			/* translators: %s: content type name */
			'add_new_item'       => sprintf( __( 'Add %s', 'corcovado-foundation-core' ), $singular ),
			/* translators: %s: content type name */
			'edit_item'          => sprintf( __( 'Edit %s', 'corcovado-foundation-core' ), $singular ),
			/* translators: %s: content type name */
			'new_item'           => sprintf( __( 'New %s', 'corcovado-foundation-core' ), $singular ),
			/* translators: %s: content type name */
			'view_item'          => sprintf( __( 'View %s', 'corcovado-foundation-core' ), $singular ),
			/* translators: %s: content type name */
			'search_items'       => sprintf( __( 'Search %s', 'corcovado-foundation-core' ), $plural ),
			'not_found'          => __( 'Nothing found.', 'corcovado-foundation-core' ),
			'not_found_in_trash' => __( 'Nothing found in Trash.', 'corcovado-foundation-core' ),
			'all_items'          => $plural,
		);
	}

	/**
	 * Taxonomy labels.
	 *
	 * @param string $plural   Plural.
	 * @param string $singular Singular.
	 */
	private static function tax_labels( $plural, $singular ) {
		return array(
			'name'          => $plural,
			'singular_name' => $singular,
			'menu_name'     => $plural,
			/* translators: %s: taxonomy name */
			'add_new_item'  => sprintf( __( 'Add %s', 'corcovado-foundation-core' ), $singular ),
			/* translators: %s: taxonomy name */
			'edit_item'     => sprintf( __( 'Edit %s', 'corcovado-foundation-core' ), $singular ),
			'all_items'     => $plural,
		);
	}

	/**
	 * Sanitize a structured field.
	 *
	 * @param string $type  Type.
	 * @param mixed  $value Raw value.
	 */
	public static function sanitize_field( $type, $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		switch ( $type ) {
			case 'date':
				return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'url':
				return CF_Layout::sanitize_link( $value );
			case 'int':
				return absint( $value );
			case 'bool':
				return '' !== $value && '0' !== $value ? 1 : 0;
			case 'key':
				return sanitize_key( $value );
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Terms of a taxonomy in a language, ordered by "_cf_order".
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $lang     Language.
	 * @return WP_Term[]
	 */
	public static function terms( $taxonomy, $lang ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'meta_query' => array( CF_Languages::meta_query( $lang ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		usort(
			$terms,
			static fn( $a, $b ) => (int) get_term_meta( $a->term_id, '_cf_order', true ) <=> (int) get_term_meta( $b->term_id, '_cf_order', true )
		);
		return $terms;
	}
}
