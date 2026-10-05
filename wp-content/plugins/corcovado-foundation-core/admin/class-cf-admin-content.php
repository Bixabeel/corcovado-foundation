<?php
/**
 * Admin screens for languages, translations, structured fields and taxonomy fields.
 *
 * @package CorcovadoFoundationCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin content.
 */
class CF_Admin_Content {

	/**
	 * Post types with a language.
	 */
	public static function lang_types() {
		return array( 'page', 'cf_news', 'cf_event', 'cf_team', 'cf_resource' );
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ), 10, 2 );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
		foreach ( self::lang_types() as $pt ) {
			add_filter( "manage_{$pt}_posts_columns", array( __CLASS__, 'columns' ) );
			add_action( "manage_{$pt}_posts_custom_column", array( __CLASS__, 'column' ), 10, 2 );
		}
		add_filter( 'page_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'admin_post_cf_translate', array( __CLASS__, 'create_translation' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'lang_filter' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_lang_filter' ) );
		foreach ( array( 'cf_news_category', 'cf_team_group', 'cf_resource_category' ) as $tax ) {
			add_action( "{$tax}_add_form_fields", array( __CLASS__, 'term_add_fields' ) );
			add_action( "{$tax}_edit_form_fields", array( __CLASS__, 'term_edit_fields' ) );
			add_action( "created_{$tax}", array( __CLASS__, 'term_save' ) );
			add_action( "edited_{$tax}", array( __CLASS__, 'term_save' ) );
			add_filter( "manage_edit-{$tax}_columns", array( __CLASS__, 'term_columns' ) );
			add_filter( "manage_{$tax}_custom_column", array( __CLASS__, 'term_column' ), 10, 3 );
		}
	}

	/**
	 * Field labels.
	 */
	private static function labels() {
		return array(
			'short_title'      => __( 'Short title for the home page cards (optional)', 'corcovado-foundation-core' ),
			'short_excerpt'    => __( 'Short text for the home page cards (optional, empty = excerpt)', 'corcovado-foundation-core' ),
			'start_date'       => __( 'Start date', 'corcovado-foundation-core' ),
			'end_date'         => __( 'End date (optional)', 'corcovado-foundation-core' ),
			'time'             => __( 'Time (optional)', 'corcovado-foundation-core' ),
			'location'         => __( 'Location (optional)', 'corcovado-foundation-core' ),
			'date_label'       => __( 'Date as shown on the card (empty = formatted automatically)', 'corcovado-foundation-core' ),
			'description'      => __( 'Description', 'corcovado-foundation-core' ),
			'link_label'       => __( 'Button text', 'corcovado-foundation-core' ),
			'link_url'         => __( 'Button link (/volunteering, /es/contact-us, https://…)', 'corcovado-foundation-core' ),
			'role'             => __( 'Role (card)', 'corcovado-foundation-core' ),
			'name_note'        => __( 'Note next to the name (e.g. years)', 'corcovado-foundation-core' ),
			'summary'          => __( 'Short summary (card)', 'corcovado-foundation-core' ),
			'bio'              => __( 'Full biography (profile window)', 'corcovado-foundation-core' ),
			'modal_name'       => __( 'Name in profile window (empty = title)', 'corcovado-foundation-core' ),
			'modal_role'       => __( 'Role in profile window (empty = card role)', 'corcovado-foundation-core' ),
			'photo_alt'        => __( 'Photo description (alt, empty = name)', 'corcovado-foundation-core' ),
			'photo_fallback'   => __( 'Show the generic silhouette if the photo fails to load', 'corcovado-foundation-core' ),
			'file'             => __( 'File to download', 'corcovado-foundation-core' ),
			'file_info'        => __( 'File information (e.g. "PDF | EN | 4.2 MB")', 'corcovado-foundation-core' ),
			'pub_date'         => __( 'Publication date (used for ordering)', 'corcovado-foundation-core' ),
			'featured'         => __( 'Featured (shown in the first library grid)', 'corcovado-foundation-core' ),
			'thumb_alt'        => __( 'Image description (alt, empty = title)', 'corcovado-foundation-core' ),
			'url'              => __( 'Website', 'corcovado-foundation-core' ),
			'logo_alt'         => __( 'Logo description (alt, empty = name)', 'corcovado-foundation-core' ),
			'sponsor_category' => __( 'Sponsor category key (e.g. founding, supporters, gold, silver, bronze; empty = none)', 'corcovado-foundation-core' ),
		);
	}

	/**
	 * Meta boxes.
	 *
	 * @param string  $post_type Post type.
	 * @param WP_Post $post      Post.
	 */
	public static function meta_boxes( $post_type, $post ) {
		if ( in_array( $post_type, self::lang_types(), true ) ) {
			add_meta_box( 'cf-language', __( 'Language and translation', 'corcovado-foundation-core' ), array( __CLASS__, 'language_box' ), $post_type, 'side', 'high' );
		}
		$fields = CF_Post_Types::fields();
		if ( isset( $fields[ $post_type ] ) ) {
			add_meta_box( 'cf-fields', __( 'Details', 'corcovado-foundation-core' ), array( __CLASS__, 'fields_box' ), $post_type, 'normal', 'high' );
		}
	}

	/**
	 * Language + translation box.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function language_box( $post ) {
		wp_nonce_field( 'cf_content_save_' . $post->ID, 'cf_content_nonce' );
		$lang  = CF_Languages::lang_of( $post );
		$langs = cf_languages();
		if ( 'page' === $post->post_type ) {
			echo '<p>' . esc_html__( 'Language', 'corcovado-foundation-core' ) . ': <strong>' . esc_html( $langs[ $lang ]['name'] ) . '</strong></p>';
			echo '<p class="description">' . esc_html__( 'Spanish pages are the Spanish home page (/es/) and its child pages. Choose the parent in "Page Attributes".', 'corcovado-foundation-core' ) . '</p>';
		} else {
			echo '<p><label for="cf-lang">' . esc_html__( 'Language', 'corcovado-foundation-core' ) . '</label><br><select id="cf-lang" name="cf_lang">';
			foreach ( $langs as $code => $info ) {
				echo '<option value="' . esc_attr( $code ) . '"' . selected( $lang, $code, false ) . '>' . esc_html( $info['name'] ) . '</option>';
			}
			echo '</select></p>';
		}
		$other_lang = 'es' === $lang ? 'en' : 'es';
		$current    = CF_Languages::translation_of( $post->ID );
		$candidates = get_posts(
			array(
				'post_type'      => $post->post_type,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => 300,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post__not_in'   => array( $post->ID ),
				'no_found_rows'  => true,
			)
		);
		echo '<p><label for="cf-translation">' . esc_html__( 'Translation', 'corcovado-foundation-core' ) . '</label><br><select id="cf-translation" name="cf_translation" style="max-width:100%">';
		echo '<option value="0">' . esc_html__( '— None —', 'corcovado-foundation-core' ) . '</option>';
		foreach ( $candidates as $c ) {
			if ( CF_Languages::lang_of( $c ) !== $other_lang ) {
				continue;
			}
			$title = '' !== $c->post_title ? $c->post_title : '#' . $c->ID;
			if ( 'page' === $c->post_type ) {
				$title = str_repeat( '— ', count( get_post_ancestors( $c ) ) ) . $title;
			}
			echo '<option value="' . esc_attr( (string) $c->ID ) . '"' . selected( $current, $c->ID, false ) . '>' . esc_html( $title ) . '</option>';
		}
		echo '</select></p>';
		if ( $current ) {
			echo '<p><a href="' . esc_url( (string) get_edit_post_link( $current ) ) . '">' . esc_html__( 'Edit translation', 'corcovado-foundation-core' ) . '</a></p>';
		} elseif ( 'auto-draft' !== $post->post_status ) {
			echo '<p><a class="button" href="' . esc_url( self::translate_url( $post->ID ) ) . '">' . esc_html__( 'Create translation (draft)', 'corcovado-foundation-core' ) . '</a></p>';
		}
	}

	/**
	 * Structured fields box.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function fields_box( $post ) {
		$fields = CF_Post_Types::fields()[ $post->post_type ];
		$labels = self::labels();
		wp_nonce_field( 'cf_fields_save_' . $post->ID, 'cf_fields_nonce' );
		$help = array(
			'cf_team'     => __( 'Photo: use "Featured image". Group: choose it in "Team groups". Order: "Page Attributes → Order".', 'corcovado-foundation-core' ),
			'cf_partner'  => __( 'Logo: use "Featured image". Order: "Page Attributes → Order". Partners are shown in both languages.', 'corcovado-foundation-core' ),
			'cf_resource' => __( 'Thumbnail: use "Featured image". Category: choose it in "Resource categories". To archive a resource, switch it to Draft.', 'corcovado-foundation-core' ),
			'cf_event'    => __( 'Image: use "Featured image" (optional). To unpublish an event, switch it to Draft.', 'corcovado-foundation-core' ),
		);
		if ( isset( $help[ $post->post_type ] ) ) {
			echo '<p class="description">' . esc_html( $help[ $post->post_type ] ) . '</p>';
		}
		echo '<table class="cf-meta-table"><tbody>';
		foreach ( $fields as $key => $type ) {
			$value = get_post_meta( $post->ID, '_cf_' . $key, true );
			$id    = 'cf-field-' . $key;
			$name  = 'cf_fields[' . $key . ']';
			echo '<tr><th><label for="' . esc_attr( $id ) . '">' . esc_html( $labels[ $key ] ?? $key ) . '</label></th><td>';
			switch ( $type ) {
				case 'textarea':
					echo '<textarea class="widefat" rows="' . ( 'bio' === $key ? 8 : 3 ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( (string) $value ) . '</textarea>';
					break;
				case 'date':
					echo '<input type="date" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
					break;
				case 'bool':
					echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( (bool) $value, true, false ) . '>';
					break;
				case 'url':
					echo '<span class="cf-url"><input class="widefat code" type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
					echo '<button type="button" class="button cf-pick-file">' . esc_html__( 'Choose file', 'corcovado-foundation-core' ) . '</button></span>';
					break;
				default:
					echo '<input class="widefat" type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Save language, translation and fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['cf_content_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cf_content_nonce'] ) ), 'cf_content_save_' . $post_id ) ) {
			if ( 'page' !== $post->post_type && isset( $_POST['cf_lang'] ) ) {
				update_post_meta( $post_id, CF_Languages::META_LANG, 'es' === $_POST['cf_lang'] ? 'es' : 'en' );
			}
			if ( isset( $_POST['cf_translation'] ) ) {
				$other = absint( $_POST['cf_translation'] );
				if ( $other && ( get_post_type( $other ) !== $post->post_type || ! current_user_can( 'edit_post', $other ) || CF_Languages::lang_of( $other ) === CF_Languages::lang_of( $post_id ) ) ) {
					$other = (int) CF_Languages::translation_of( $post_id );
				}
				if ( $other !== (int) CF_Languages::translation_of( $post_id ) ) {
					CF_Languages::link( $post_id, $other );
				}
			}
		}
		$fields = CF_Post_Types::fields();
		if ( isset( $fields[ $post->post_type ], $_POST['cf_fields_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cf_fields_nonce'] ) ), 'cf_fields_save_' . $post_id ) ) {
			$posted = isset( $_POST['cf_fields'] ) && is_array( $_POST['cf_fields'] ) ? wp_unslash( $_POST['cf_fields'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
			foreach ( $fields[ $post->post_type ] as $key => $type ) {
				$value = CF_Post_Types::sanitize_field( $type, $posted[ $key ] ?? '' );
				CF_Admin_Layout::update_or_delete( $post_id, '_cf_' . $key, ( 'bool' === $type && ! $value ) ? '' : $value );
			}
		}
	}

	/**
	 * URL that creates a translation.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function translate_url( $post_id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=cf_translate&post=' . (int) $post_id ), 'cf_translate_' . (int) $post_id );
	}

	/**
	 * Row action.
	 *
	 * @param array   $actions Actions.
	 * @param WP_Post $post    Post.
	 */
	public static function row_actions( $actions, $post ) {
		if ( in_array( $post->post_type, self::lang_types(), true ) && current_user_can( 'edit_post', $post->ID ) && ! CF_Languages::translation_of( $post->ID ) ) {
			$actions['cf_translate'] = '<a href="' . esc_url( self::translate_url( $post->ID ) ) . '">' . esc_html__( 'Create translation', 'corcovado-foundation-core' ) . '</a>';
		}
		return $actions;
	}

	/**
	 * Duplicate a post as a draft in the other language and link both.
	 */
	public static function create_translation() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'cf_translate_' . $post_id );
		$post = get_post( $post_id );
		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) || ! in_array( $post->post_type, self::lang_types(), true ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'corcovado-foundation-core' ) );
		}
		$existing = CF_Languages::translation_of( $post_id );
		if ( $existing ) {
			wp_safe_redirect( get_edit_post_link( $existing, 'raw' ) );
			exit;
		}
		$target = 'es' === CF_Languages::lang_of( $post ) ? 'en' : 'es';
		$parent = 0;
		if ( 'page' === $post->post_type ) {
			$parent = $post->post_parent ? (int) CF_Languages::translation_of( $post->post_parent ) : 0;
			if ( ! $parent && 'es' === $target ) {
				$parent = CF_Languages::es_root();
			}
			if ( 'es' === $target && ! $parent ) {
				wp_die( esc_html__( 'Set the Spanish home page first (Corcovado Foundation → Settings).', 'corcovado-foundation-core' ) );
			}
		}
		$new_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => $post->post_type,
					'post_status'  => 'draft',
					'post_title'   => $post->post_title,
					'post_name'    => $post->post_name,
					'post_content' => $post->post_content,
					'post_excerpt' => $post->post_excerpt,
					'post_parent'  => $parent,
					'menu_order'   => $post->menu_order,
					'post_author'  => get_current_user_id(),
				)
			),
			true
		);
		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}
		$skip = array( '_cf_translation', '_cf_source_key', '_edit_lock', '_edit_last', '_wp_old_slug' );
		foreach ( get_post_meta( $post_id ) as $key => $values ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			foreach ( $values as $value ) {
				add_post_meta( $new_id, $key, wp_slash( maybe_unserialize( $value ) ) );
			}
		}
		update_post_meta( $new_id, CF_Languages::META_LANG, $target );
		CF_Languages::link( $post_id, $new_id );
		wp_safe_redirect( get_edit_post_link( $new_id, 'raw' ) );
		exit;
	}

	/**
	 * Language column.
	 *
	 * @param array $cols Columns.
	 */
	public static function columns( $cols ) {
		$out = array();
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'title' === $k ) {
				$out['cf_lang'] = __( 'Language', 'corcovado-foundation-core' );
			}
		}
		return $out;
	}

	/**
	 * Language column value.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Post ID.
	 */
	public static function column( $col, $post_id ) {
		if ( 'cf_lang' !== $col ) {
			return;
		}
		$lang = CF_Languages::lang_of( $post_id );
		echo esc_html( strtoupper( $lang ) );
		$t = CF_Languages::translation_of( $post_id );
		if ( $t ) {
			echo ' ↔ <a href="' . esc_url( (string) get_edit_post_link( $t ) ) . '">' . esc_html( strtoupper( 'es' === $lang ? 'en' : 'es' ) ) . '</a>';
		}
	}

	/**
	 * Language filter dropdown on list screens.
	 *
	 * @param string $post_type Post type.
	 */
	public static function lang_filter( $post_type ) {
		if ( ! in_array( $post_type, self::lang_types(), true ) || 'page' === $post_type ) {
			return;
		}
		$cur = isset( $_GET['cf_lang_filter'] ) ? sanitize_key( wp_unslash( $_GET['cf_lang_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<select name="cf_lang_filter"><option value="">' . esc_html__( 'All languages', 'corcovado-foundation-core' ) . '</option>';
		foreach ( cf_languages() as $code => $info ) {
			echo '<option value="' . esc_attr( $code ) . '"' . selected( $cur, $code, false ) . '>' . esc_html( $info['name'] ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Apply the language filter.
	 *
	 * @param WP_Query $q Query.
	 */
	public static function apply_lang_filter( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || empty( $_GET['cf_lang_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$lang = 'es' === sanitize_key( wp_unslash( $_GET['cf_lang_filter'] ) ) ? 'es' : 'en'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$mq   = (array) $q->get( 'meta_query' );
		$mq[] = CF_Languages::meta_query( $lang );
		$q->set( 'meta_query', $mq );
	}

	/**
	 * Term fields (add screen).
	 */
	public static function term_add_fields() {
		wp_nonce_field( 'cf_term_save', 'cf_term_nonce' );
		echo '<div class="form-field"><label for="cf-term-lang">' . esc_html__( 'Language', 'corcovado-foundation-core' ) . '</label><select id="cf-term-lang" name="cf_term_lang">';
		foreach ( cf_languages() as $code => $info ) {
			echo '<option value="' . esc_attr( $code ) . '">' . esc_html( $info['name'] ) . '</option>';
		}
		echo '</select></div>';
		echo '<div class="form-field"><label for="cf-term-order">' . esc_html__( 'Order', 'corcovado-foundation-core' ) . '</label><input id="cf-term-order" type="number" name="cf_term_order" value="0"></div>';
	}

	/**
	 * Term fields (edit screen).
	 *
	 * @param WP_Term $term Term.
	 */
	public static function term_edit_fields( $term ) {
		wp_nonce_field( 'cf_term_save', 'cf_term_nonce' );
		$lang  = get_term_meta( $term->term_id, '_cf_lang', true );
		$order = (int) get_term_meta( $term->term_id, '_cf_order', true );
		echo '<tr class="form-field"><th><label for="cf-term-lang">' . esc_html__( 'Language', 'corcovado-foundation-core' ) . '</label></th><td><select id="cf-term-lang" name="cf_term_lang">';
		foreach ( cf_languages() as $code => $info ) {
			echo '<option value="' . esc_attr( $code ) . '"' . selected( $lang ? $lang : 'en', $code, false ) . '>' . esc_html( $info['name'] ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr class="form-field"><th><label for="cf-term-order">' . esc_html__( 'Order', 'corcovado-foundation-core' ) . '</label></th><td><input id="cf-term-order" type="number" name="cf_term_order" value="' . esc_attr( (string) $order ) . '"></td></tr>';
	}

	/**
	 * Save term fields.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function term_save( $term_id ) {
		if ( ! isset( $_POST['cf_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cf_term_nonce'] ) ), 'cf_term_save' ) || ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		if ( isset( $_POST['cf_term_lang'] ) ) {
			update_term_meta( $term_id, '_cf_lang', 'es' === $_POST['cf_term_lang'] ? 'es' : 'en' );
		}
		if ( isset( $_POST['cf_term_order'] ) ) {
			update_term_meta( $term_id, '_cf_order', intval( $_POST['cf_term_order'] ) );
		}
	}

	/**
	 * Term list columns.
	 *
	 * @param array $cols Columns.
	 */
	public static function term_columns( $cols ) {
		$cols['cf_lang']  = __( 'Language', 'corcovado-foundation-core' );
		$cols['cf_order'] = __( 'Order', 'corcovado-foundation-core' );
		return $cols;
	}

	/**
	 * Term column values.
	 *
	 * @param string $out     Output.
	 * @param string $col     Column.
	 * @param int    $term_id Term.
	 */
	public static function term_column( $out, $col, $term_id ) {
		if ( 'cf_lang' === $col ) {
			$l = get_term_meta( $term_id, '_cf_lang', true );
			return esc_html( strtoupper( $l ? $l : 'en' ) );
		}
		if ( 'cf_order' === $col ) {
			return esc_html( (string) (int) get_term_meta( $term_id, '_cf_order', true ) );
		}
		return $out;
	}
}
