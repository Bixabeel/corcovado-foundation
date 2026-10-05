<?php
/**
 * "Page content" editor for pages with a locked layout.
 *
 * @package CorcovadoFoundationCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI for CF_Layout.
 */
class CF_Admin_Layout {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'use_block_editor_for_post', array( __CLASS__, 'disable_block_editor' ), 10, 2 );
		add_action( 'add_meta_boxes_page', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'add_meta_boxes_cf_news', array( __CLASS__, 'seo_box_only' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'load-post.php', array( __CLASS__, 'hide_editor' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'post_states' ), 10, 2 );
	}

	/**
	 * Pages with a layout use this editor instead of the block editor.
	 *
	 * @param bool    $use  Use block editor.
	 * @param WP_Post $post Post.
	 */
	public static function disable_block_editor( $use, $post ) {
		if ( $post && 'page' === $post->post_type && CF_Layout::get( $post->ID ) ) {
			return false;
		}
		return $use;
	}

	/**
	 * Hide the (unused) classic content editor on layout pages.
	 */
	public static function hide_editor() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $id && 'page' === get_post_type( $id ) && CF_Layout::get( $id ) ) {
			remove_post_type_support( 'page', 'editor' );
		}
	}

	/**
	 * Mark layout pages in the page list.
	 *
	 * @param array   $states States.
	 * @param WP_Post $post   Post.
	 */
	public static function post_states( $states, $post ) {
		if ( 'page' === $post->post_type ) {
			if ( (int) $post->ID === CF_Languages::es_root() ) {
				$states['cf_es_root'] = __( 'Spanish home (/es/)', 'corcovado-foundation-core' );
			}
			if ( in_array( (int) $post->ID, array( (int) CF_Settings::get( 'page_404_en' ), (int) CF_Settings::get( 'page_404_es' ) ), true ) ) {
				$states['cf_404'] = __( '404 content', 'corcovado-foundation-core' );
			}
		}
		return $states;
	}

	/**
	 * Meta boxes for pages.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function meta_boxes( $post ) {
		if ( CF_Layout::get( $post->ID ) ) {
			add_meta_box( 'cf-layout', __( 'Page content', 'corcovado-foundation-core' ), array( __CLASS__, 'render_box' ), 'page', 'normal', 'high' );
		}
		add_meta_box( 'cf-seo', __( 'Search engines and sharing', 'corcovado-foundation-core' ), array( __CLASS__, 'render_seo_box' ), 'page', 'normal', 'low' );
	}

	/**
	 * SEO box for news.
	 */
	public static function seo_box_only() {
		add_meta_box( 'cf-seo', __( 'Search engines and sharing', 'corcovado-foundation-core' ), array( __CLASS__, 'render_seo_box' ), 'cf_news', 'normal', 'low' );
	}

	/**
	 * Scripts.
	 *
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php', 'term.php', 'edit-tags.php' ), true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'cf-admin', CF_CORE_URL . 'assets/admin.css', array(), CF_CORE_VERSION );
		wp_enqueue_script( 'cf-admin', CF_CORE_URL . 'assets/admin.js', array( 'jquery' ), CF_CORE_VERSION, true );
		wp_localize_script(
			'cf-admin',
			'CF_ADMIN',
			array(
				'chooseImage' => __( 'Choose image', 'corcovado-foundation-core' ),
				'chooseFile'  => __( 'Choose file', 'corcovado-foundation-core' ),
				'use'         => __( 'Use this file', 'corcovado-foundation-core' ),
				'confirmDel'  => __( 'Remove this item? (It disappears from the page when you update.)', 'corcovado-foundation-core' ),
			)
		);
	}

	/**
	 * Human labels of dynamic regions.
	 *
	 * @param string $type Type.
	 */
	private static function dynamic_help( $type ) {
		$map = array(
			'news'            => __( 'Stories are loaded automatically from News (latest published in this language).', 'corcovado-foundation-core' ),
			'events'          => __( 'Cards are loaded automatically from Events.', 'corcovado-foundation-core' ),
			'resources'       => __( 'Cards are loaded automatically from Library.', 'corcovado-foundation-core' ),
			'partners'        => __( 'Logos are loaded automatically from Partners (order = "Order" field).', 'corcovado-foundation-core' ),
			'sponsor_members' => __( 'Members are loaded from Partners assigned to this sponsor category. When there are none, the text below is shown.', 'corcovado-foundation-core' ),
			'team'            => __( 'Team groups and members are loaded automatically from Team.', 'corcovado-foundation-core' ),
		);
		return $map[ $type ] ?? '';
	}

	/**
	 * Edit link of the content type behind a dynamic region.
	 *
	 * @param string $type Type.
	 */
	private static function dynamic_link( $type ) {
		$map = array(
			'news'            => 'cf_news',
			'events'          => 'cf_event',
			'resources'       => 'cf_resource',
			'partners'        => 'cf_partner',
			'sponsor_members' => 'cf_partner',
			'team'            => 'cf_team',
		);
		return isset( $map[ $type ] ) ? admin_url( 'edit.php?post_type=' . $map[ $type ] ) : '';
	}

	/**
	 * Render the "Page content" box.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_box( $post ) {
		$layout = CF_Layout::get( $post->ID );
		wp_nonce_field( 'cf_layout_save_' . $post->ID, 'cf_layout_nonce' );
		echo '<p class="description">' . esc_html__( 'Edit texts, images, links and buttons of each section. The structure and the design of the page are locked so the layout always matches the approved design. Lists of cards can be reordered, duplicated or removed.', 'corcovado-foundation-core' ) . '</p>';

		$settings = $layout['settings'];
		echo '<details class="cf-section"><summary>' . esc_html__( 'Page options', 'corcovado-foundation-core' ) . '</summary><div class="cf-section-body">';
		foreach ( CF_Layout::page_settings_schema() as $key => $choices ) {
			$labels = array(
				'header_logo'  => __( 'Header logo', 'corcovado-foundation-core' ),
				'footer_logo'  => __( 'Footer logo', 'corcovado-foundation-core' ),
				'footer_style' => __( 'Footer style', 'corcovado-foundation-core' ),
			);
			$label  = $labels[ $key ] ?? $key;
			echo '<p><label>' . esc_html( $label ) . '<br><select name="cf_layout[settings][' . esc_attr( $key ) . ']">';
			foreach ( $choices as $value => $text ) {
				echo '<option value="' . esc_attr( $value ) . '"' . selected( $settings[ $key ] ?? array_key_first( $choices ), $value, false ) . '>' . esc_html( $text ) . '</option>';
			}
			echo '</select></label></p>';
		}
		echo '</div></details>';

		foreach ( $layout['sections'] as $si => $section ) {
			$base   = 'cf_layout[sections][' . $si . ']';
			$label  = (string) ( $section['label'] ?? '' );
			$hidden = ! empty( $section['hidden'] );
			echo '<details class="cf-section' . ( $hidden ? ' is-hidden' : '' ) . '"><summary>' . esc_html( ( $si + 1 ) . '. ' . ( '' !== $label ? $label : __( 'Section', 'corcovado-foundation-core' ) ) ) . ( $hidden ? ' <em>(' . esc_html__( 'hidden', 'corcovado-foundation-core' ) . ')</em>' : '' ) . '</summary><div class="cf-section-body">';
			echo '<p><label><input type="checkbox" name="' . esc_attr( $base . '[hidden]' ) . '" value="1"' . checked( $hidden, true, false ) . '> ' . esc_html__( 'Hide this section', 'corcovado-foundation-core' ) . '</label></p>';
			self::render_slots( (array) ( $section['slots'] ?? array() ), $base . '[slots]' );
			echo '</div></details>';
		}
	}

	/**
	 * Render slot fields.
	 *
	 * @param array  $slots Slots.
	 * @param string $base  Input name prefix.
	 */
	private static function render_slots( $slots, $base ) {
		foreach ( $slots as $i => $slot ) {
			$name  = $base . '[' . $i . ']';
			$kind  = $slot['k'] ?? 'text';
			$label = self::label( (string) ( $slot['l'] ?? '' ) );
			$v     = $slot['v'] ?? '';
			$id    = 'cf-' . md5( $name );
			switch ( $kind ) {
				case 'list':
					echo '<fieldset class="cf-list"><legend>' . esc_html( $label ) . '</legend>';
					echo '<input type="hidden" name="' . esc_attr( $name . '[present]' ) . '" value="1">';
					echo '<div class="cf-list-items" data-base="' . esc_attr( $name . '[items]' ) . '">';
					foreach ( (array) ( $slot['items'] ?? array() ) as $n => $item ) {
						$uid   = 'i' . $n;
						$iname = $name . '[items][' . $uid . ']';
						echo '<div class="cf-item" data-uid="' . esc_attr( $uid ) . '">';
						echo '<div class="cf-item-bar"><strong class="cf-item-title">' . esc_html( self::item_title( $item, $n ) ) . '</strong>';
						echo '<span class="cf-item-actions">';
						echo '<button type="button" class="button-link cf-up" aria-label="' . esc_attr__( 'Move up', 'corcovado-foundation-core' ) . '">↑</button> ';
						echo '<button type="button" class="button-link cf-down" aria-label="' . esc_attr__( 'Move down', 'corcovado-foundation-core' ) . '">↓</button> ';
						echo '<button type="button" class="button-link cf-dup">' . esc_html__( 'Duplicate', 'corcovado-foundation-core' ) . '</button> ';
						echo '<button type="button" class="button-link cf-del">' . esc_html__( 'Remove', 'corcovado-foundation-core' ) . '</button>';
						echo '</span></div><div class="cf-item-body">';
						echo '<input type="hidden" name="' . esc_attr( $iname . '[src]' ) . '" value="' . esc_attr( (string) $n ) . '">';
						self::render_slots( (array) ( $item['slots'] ?? array() ), $iname . '[slots]' );
						echo '</div></div>';
					}
					echo '</div></fieldset>';
					break;
				case 'dyn':
					$type = (string) ( $slot['type'] ?? '' );
					$link = self::dynamic_link( $type );
					echo '<div class="cf-dyn"><p><span class="dashicons dashicons-update"></span> ' . esc_html( self::dynamic_help( $type ) );
					if ( $link ) {
						echo ' <a href="' . esc_url( $link ) . '">' . esc_html__( 'Manage content', 'corcovado-foundation-core' ) . '</a>';
					}
					echo '</p>';
					foreach ( (array) ( $slot['f'] ?? array() ) as $key => $field ) {
						$fid    = $id . '-' . sanitize_key( $key );
						$flabel = self::label( (string) ( $field['l'] ?? $key ) );
						if ( 'bool' === ( $field['k'] ?? '' ) ) {
							echo '<p><input type="hidden" name="' . esc_attr( $name . '[f][' . $key . ']' ) . '" value=""><label><input type="checkbox" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name . '[f][' . $key . ']' ) . '" value="1"' . checked( ! empty( $field['v'] ), true, false ) . '> ' . esc_html( $flabel ) . '</label></p>';
							continue;
						}
						echo '<p><label for="' . esc_attr( $fid ) . '">' . esc_html( $flabel ) . '</label><input class="widefat" id="' . esc_attr( $fid ) . '" type="text" name="' . esc_attr( $name . '[f][' . $key . ']' ) . '" value="' . esc_attr( (string) ( $field['v'] ?? '' ) ) . '"></p>';
					}
					echo '</div>';
					break;
				case 'img':
					$url = $v ? wp_get_attachment_image_url( (int) $v, 'medium' ) : '';
					echo '<div class="cf-field cf-image"><span class="cf-label">' . esc_html( $label ) . '</span>';
					echo '<img class="cf-preview" src="' . esc_url( (string) $url ) . '" alt=""' . ( $url ? '' : ' hidden' ) . '>';
					echo '<input type="hidden" class="cf-image-id" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $v ) . '">';
					echo '<button type="button" class="button cf-pick-image">' . esc_html__( 'Choose image', 'corcovado-foundation-core' ) . '</button></div>';
					break;
				case 'url':
					echo '<p class="cf-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
					echo '<span class="cf-url"><input class="widefat code" id="' . esc_attr( $id ) . '" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $v ) . '">';
					echo '<button type="button" class="button cf-pick-file">' . esc_html__( 'Choose file', 'corcovado-foundation-core' ) . '</button></span>';
					echo '<span class="description">' . esc_html__( 'Examples: /about-us, /es/contact-us, #section, https://…, mailto:…, media:ID (file).', 'corcovado-foundation-core' ) . '</span></p>';
					break;
				case 'html':
					echo '<p class="cf-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
					echo '<textarea class="widefat" rows="3" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( (string) $v ) . '</textarea>';
					echo '<span class="description">' . esc_html__( 'Allowed formatting: <strong>, <em>, <br>, <a href="…">.', 'corcovado-foundation-core' ) . '</span></p>';
					break;
				case 'num':
					echo '<p class="cf-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
					echo '<input id="' . esc_attr( $id ) . '" type="number" step="any" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $v ) . '"></p>';
					break;
				default:
					$long = strlen( (string) $v ) > 90;
					echo '<p class="cf-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
					if ( $long ) {
						echo '<textarea class="widefat" rows="3" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( (string) $v ) . '</textarea></p>';
					} else {
						echo '<input class="widefat" id="' . esc_attr( $id ) . '" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $v ) . '"></p>';
					}
			}
		}
	}

	/**
	 * Human label of a slot label key (keys are written by the extractor).
	 *
	 * @param string $key Key.
	 */
	public static function label( $key ) {
		$map = array(
			'title_main'    => __( 'Main title', 'corcovado-foundation-core' ),
			'title'         => __( 'Title', 'corcovado-foundation-core' ),
			'subtitle'      => __( 'Subtitle / card title', 'corcovado-foundation-core' ),
			'text'          => __( 'Text', 'corcovado-foundation-core' ),
			'small_text'    => __( 'Short text', 'corcovado-foundation-core' ),
			'highlight'     => __( 'Highlighted text', 'corcovado-foundation-core' ),
			'label'         => __( 'Label', 'corcovado-foundation-core' ),
			'button'        => __( 'Button text', 'corcovado-foundation-core' ),
			'button_after'  => __( 'Button text after copying', 'corcovado-foundation-core' ),
			'link_text'     => __( 'Link text', 'corcovado-foundation-core' ),
			'link'          => __( 'Link', 'corcovado-foundation-core' ),
			'image'         => __( 'Image', 'corcovado-foundation-core' ),
			'alt'           => __( 'Image description (alt text)', 'corcovado-foundation-core' ),
			'list_item'     => __( 'List item', 'corcovado-foundation-core' ),
			'field_label'   => __( 'Form field label', 'corcovado-foundation-core' ),
			'option'        => __( 'Option', 'corcovado-foundation-core' ),
			'placeholder'   => __( 'Example text inside the field', 'corcovado-foundation-core' ),
			'aria'          => __( 'Accessibility label (screen readers)', 'corcovado-foundation-core' ),
			'tooltip'       => __( 'Tooltip', 'corcovado-foundation-core' ),
			'number'        => __( 'Number (animated counter)', 'corcovado-foundation-core' ),
			'prefix'        => __( 'Before the number', 'corcovado-foundation-core' ),
			'suffix'        => __( 'After the number', 'corcovado-foundation-core' ),
			'items'         => __( 'Items', 'corcovado-foundation-core' ),
			'pending'       => __( 'Text shown when the category has no members', 'corcovado-foundation-core' ),
			'partners_all'  => __( 'Show all published partners (instead of the original selection of this page)', 'corcovado-foundation-core' ),
			'team_aria'     => __( 'Accessibility label of each card (%s = name)', 'corcovado-foundation-core' ),
		);
		return $map[ $key ] ?? $key;
	}

	/**
	 * Title of a list item: its label or its first text.
	 *
	 * @param array $item Item.
	 * @param int   $n    Index.
	 */
	private static function item_title( $item, $n ) {
		foreach ( (array) ( $item['slots'] ?? array() ) as $slot ) {
			if ( in_array( $slot['k'] ?? '', array( 'text', 'html' ), true ) && '' !== trim( wp_strip_all_tags( (string) ( $slot['v'] ?? '' ) ) ) ) {
				return wp_html_excerpt( wp_strip_all_tags( (string) $slot['v'] ), 60, '…' );
			}
		}
		/* translators: %d: item number */
		return sprintf( __( 'Item %d', 'corcovado-foundation-core' ), $n + 1 );
	}

	/**
	 * SEO box.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_seo_box( $post ) {
		wp_nonce_field( 'cf_seo_save_' . $post->ID, 'cf_seo_nonce' );
		$title = (string) get_post_meta( $post->ID, '_cf_seo_title', true );
		$desc  = (string) get_post_meta( $post->ID, '_cf_seo_description', true );
		$img   = (int) get_post_meta( $post->ID, '_cf_og_image', true );
		$url   = $img ? wp_get_attachment_image_url( $img, 'medium' ) : '';
		if ( ! CF_SEO::enabled() ) {
			echo '<p class="notice notice-info inline">' . esc_html__( 'An SEO plugin is handling titles and descriptions (or the output is disabled in Corcovado Foundation → Settings).', 'corcovado-foundation-core' ) . '</p>';
		}
		echo '<p><label for="cf-seo-title">' . esc_html__( 'Title in search results (empty = page title)', 'corcovado-foundation-core' ) . '</label><input class="widefat" id="cf-seo-title" name="cf_seo_title" value="' . esc_attr( $title ) . '"></p>';
		echo '<p><label for="cf-seo-desc">' . esc_html__( 'Description in search results and when shared', 'corcovado-foundation-core' ) . '</label><textarea class="widefat" rows="2" id="cf-seo-desc" name="cf_seo_description">' . esc_textarea( $desc ) . '</textarea></p>';
		echo '<div class="cf-field cf-image"><span class="cf-label">' . esc_html__( 'Share image (empty = default share image)', 'corcovado-foundation-core' ) . '</span>';
		echo '<img class="cf-preview" src="' . esc_url( (string) $url ) . '" alt=""' . ( $url ? '' : ' hidden' ) . '>';
		echo '<input type="hidden" class="cf-image-id" name="cf_og_image" value="' . esc_attr( $img ? (string) $img : '' ) . '">';
		echo '<button type="button" class="button cf-pick-image">' . esc_html__( 'Choose image', 'corcovado-foundation-core' ) . '</button> <button type="button" class="button-link cf-clear-image">' . esc_html__( 'Remove', 'corcovado-foundation-core' ) . '</button></div>';
	}

	/**
	 * Save layout + SEO fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( 'page' === $post->post_type && isset( $_POST['cf_layout_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cf_layout_nonce'] ) ), 'cf_layout_save_' . $post_id ) ) {
			$stored = CF_Layout::get( $post_id );
			if ( $stored && isset( $_POST['cf_layout'] ) && is_array( $_POST['cf_layout'] ) ) {
				// Values are sanitized field by field in CF_Layout::apply() according to the stored schema.
				$posted = wp_unslash( $_POST['cf_layout'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				CF_Layout::update( $post_id, CF_Layout::apply( $stored, $posted ) );
			}
		}
		if ( in_array( $post->post_type, array( 'page', 'cf_news' ), true ) && isset( $_POST['cf_seo_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cf_seo_nonce'] ) ), 'cf_seo_save_' . $post_id ) ) {
			$title = isset( $_POST['cf_seo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['cf_seo_title'] ) ) : '';
			$desc  = isset( $_POST['cf_seo_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cf_seo_description'] ) ) : '';
			$img   = isset( $_POST['cf_og_image'] ) ? absint( $_POST['cf_og_image'] ) : 0;
			self::update_or_delete( $post_id, '_cf_seo_title', $title );
			self::update_or_delete( $post_id, '_cf_seo_description', $desc );
			self::update_or_delete( $post_id, '_cf_og_image', $img ? $img : '' );
		}
	}

	/**
	 * Store or remove a meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Key.
	 * @param mixed  $value   Value.
	 */
	public static function update_or_delete( $post_id, $key, $value ) {
		if ( '' === $value || null === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}
}
