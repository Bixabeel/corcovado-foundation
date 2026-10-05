<?php
/**
 * Page sections with a locked structure and editable content.
 *
 * Every page that comes from the static site keeps its original markup as a set of section
 * *templates* stored in the "_cf_layout" meta (JSON). In each template, every piece of content
 * (texts, inline rich text, links, images, alternative texts, counters…) has been replaced by
 * a numbered slot. Editors change slot values from the "Page content" box; the HTML structure,
 * the CSS classes and the inline styles of the original site are never exposed to the editor,
 * so the design cannot be broken from the admin.
 *
 * Template tokens:
 *   {{N}}    slot N rendered in text context (escaped text, filtered inline HTML, a list or a
 *            dynamic region).
 *   {{a:N}}  slot N rendered inside an attribute value (escaped attribute / URL).
 *
 * Slot kinds ("k"):
 *   text  plain text                    html  inline rich text (strong, em, a, br, span)
 *   url   link (see cf_url())           img   Media Library attachment ID
 *   num   number (animated counters)
 *   list  repeatable items: each item has its own template and slots. Editors can remove,
 *         duplicate and reorder items; new items are always copies of existing ones.
 *   dyn   dynamic region (news, events, library, partners, team…) rendered by the theme from
 *         the structured content types; only its labels are editable here.
 *
 * Templates are written by the importer (from the original HTML) and are never accepted from
 * a request: when a page is saved, the structure is always taken from the stored layout.
 *
 * @package CorcovadoFoundationCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Layout storage, sanitization and rendering engine.
 */
class CF_Layout {

	const META = '_cf_layout';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action(
			'init',
			static function () {
				register_post_meta(
					'page',
					self::META,
					array(
						'type'          => 'string',
						'single'        => true,
						'show_in_rest'  => false,
						'auth_callback' => static fn( $allowed, $key, $post_id ) => current_user_can( 'edit_post', $post_id ),
					)
				);
			}
		);
	}

	/**
	 * Page-level settings (logo variants used by the original pages).
	 */
	public static function page_settings_schema() {
		return array(
			'header_logo' => array(
				'default' => __( 'Default logo (logo.png)', 'corcovado-foundation-core' ),
				'home'    => __( 'Home logo (logo1.png)', 'corcovado-foundation-core' ),
				'svg'     => __( 'Vector logo (logo-funcorco.svg)', 'corcovado-foundation-core' ),
				'en'      => __( 'English logo (logo_en.png)', 'corcovado-foundation-core' ),
			),
			'footer_style' => array(
				'standard' => __( 'Footer with text icons (FB / IG / @)', 'corcovado-foundation-core' ),
				'icons'    => __( 'Footer with drawn social icons', 'corcovado-foundation-core' ),
			),
			'footer_logo' => array(
				'default' => __( 'Default footer logo (logo-border.webp)', 'corcovado-foundation-core' ),
				'home'    => __( 'Home logo (logo1.png)', 'corcovado-foundation-core' ),
				'svg'     => __( 'Vector logo (logo-funcorco.svg)', 'corcovado-foundation-core' ),
				'en'      => __( 'English logo (logo_en.png)', 'corcovado-foundation-core' ),
			),
		);
	}

	/**
	 * Get a page layout.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	public static function get( $post_id ) {
		$raw = get_post_meta( $post_id, self::META, true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || ! isset( $data['sections'] ) || ! is_array( $data['sections'] ) ) {
			return null;
		}
		$data['settings'] = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
		return $data;
	}

	/**
	 * Store a layout.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $layout  Layout.
	 */
	public static function update( $post_id, $layout ) {
		update_post_meta( $post_id, self::META, wp_slash( wp_json_encode( $layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
	}

	/**
	 * Sanitize a link value. See cf_url() for the accepted formats.
	 *
	 * @param string $value Raw.
	 */
	public static function sanitize_link( $value ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^(media|post):\d+$/', $value ) ) {
			return $value;
		}
		if ( str_starts_with( $value, '#' ) ) {
			return '#' . preg_replace( '/[^A-Za-z0-9_\-]/', '', substr( $value, 1 ) );
		}
		if ( str_starts_with( $value, '/' ) && ! str_starts_with( $value, '//' ) ) {
			return preg_replace( '/[^A-Za-z0-9_\-\/.#%~?=&]/', '', $value );
		}
		$clean = esc_url_raw( $value, array( 'http', 'https', 'mailto', 'tel' ) );
		return $clean ? $clean : '';
	}

	/**
	 * Sanitize one editable value.
	 *
	 * @param string $kind  Slot kind.
	 * @param mixed  $value Raw value (unslashed).
	 */
	public static function sanitize_value( $kind, $value ) {
		if ( is_array( $value ) ) {
			return '';
		}
		$value = (string) $value;
		switch ( $kind ) {
			case 'html':
				return trim( wp_kses( $value, cf_allowed_inline_html() ) );
			case 'url':
				return self::sanitize_link( $value );
			case 'img':
				return absint( $value );
			case 'num':
				$n = preg_replace( '/[^0-9.\-]/', '', $value );
				return is_numeric( $n ) ? $n : '0';
			case 'textarea':
				return sanitize_textarea_field( $value );
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Apply posted values onto stored slots (recursive). The stored slots define what exists.
	 *
	 * @param array $slots  Stored slots.
	 * @param mixed $posted Posted values, indexed like $slots (unslashed).
	 * @return array
	 */
	public static function apply_slots( $slots, $posted ) {
		$posted = is_array( $posted ) ? $posted : array();
		foreach ( $slots as $i => $slot ) {
			$kind = $slot['k'] ?? 'text';
			$p    = $posted[ $i ] ?? null;
			if ( 'list' === $kind ) {
				if ( ! is_array( $p ) || empty( $p['present'] ) ) {
					continue; // Not part of the request: keep as stored.
				}
				$items = array();
				$rows  = isset( $p['items'] ) && is_array( $p['items'] ) ? $p['items'] : array();
				foreach ( $rows as $row ) {
					if ( ! is_array( $row ) || ! isset( $row['src'] ) ) {
						continue;
					}
					$src = (int) $row['src'];
					if ( ! isset( $slot['items'][ $src ] ) ) {
						continue;
					}
					$item          = $slot['items'][ $src ]; // Structure always comes from storage.
					$item['slots'] = self::apply_slots( $item['slots'] ?? array(), $row['slots'] ?? array() );
					$items[]       = $item;
				}
				$slots[ $i ]['items'] = $items;
			} elseif ( 'dyn' === $kind ) {
				if ( ! empty( $slot['f'] ) && is_array( $p ) && isset( $p['f'] ) && is_array( $p['f'] ) ) {
					foreach ( $slot['f'] as $key => $field ) {
						if ( array_key_exists( $key, $p['f'] ) ) {
							$slots[ $i ]['f'][ $key ]['v'] = self::sanitize_value( $field['k'] ?? 'text', $p['f'][ $key ] );
						}
					}
				}
			} elseif ( null !== $p ) {
				$slots[ $i ]['v'] = self::sanitize_value( $kind, $p );
			}
		}
		return $slots;
	}

	/**
	 * Apply a whole posted layout onto the stored one.
	 *
	 * @param array $stored Stored layout.
	 * @param array $posted Posted data (unslashed).
	 * @return array
	 */
	public static function apply( $stored, $posted ) {
		$posted   = is_array( $posted ) ? $posted : array();
		$sections = isset( $posted['sections'] ) && is_array( $posted['sections'] ) ? $posted['sections'] : array();
		foreach ( $stored['sections'] as $si => $section ) {
			$ps = isset( $sections[ $si ] ) && is_array( $sections[ $si ] ) ? $sections[ $si ] : null;
			if ( null === $ps ) {
				continue;
			}
			$stored['sections'][ $si ]['slots']  = self::apply_slots( $section['slots'] ?? array(), $ps['slots'] ?? array() );
			$stored['sections'][ $si ]['hidden'] = ! empty( $ps['hidden'] );
		}
		foreach ( self::page_settings_schema() as $key => $choices ) {
			if ( isset( $posted['settings'][ $key ] ) ) {
				$v                          = (string) $posted['settings'][ $key ];
				$stored['settings'][ $key ] = array_key_exists( $v, $choices ) ? $v : array_key_first( $choices );
			}
		}
		return $stored;
	}

	/**
	 * Render a template with its slots.
	 *
	 * @param string $tpl     Template.
	 * @param array  $slots   Slots.
	 * @param array  $context Render context (post_id, lang).
	 * @return string
	 */
	public static function render_template( $tpl, $slots, $context ) {
		return preg_replace_callback(
			'/\{\{(a:)?(\d+)\}\}/',
			static function ( $m ) use ( $slots, $context ) {
				$slot = $slots[ (int) $m[2] ] ?? null;
				if ( ! is_array( $slot ) ) {
					return '';
				}
				return '' !== $m[1] ? self::render_attr( $slot ) : self::render_text( $slot, $context );
			},
			(string) $tpl
		);
	}

	/**
	 * Slot in attribute context.
	 *
	 * @param array $slot Slot.
	 */
	private static function render_attr( $slot ) {
		$v = $slot['v'] ?? '';
		switch ( $slot['k'] ?? 'text' ) {
			case 'url':
				return esc_url( cf_url( (string) $v ) );
			case 'img':
				$url = $v ? wp_get_attachment_url( (int) $v ) : '';
				return $url ? esc_url( $url ) : '';
			default:
				return esc_attr( is_scalar( $v ) ? (string) $v : '' );
		}
	}

	/**
	 * Slot in text context.
	 *
	 * @param array $slot    Slot.
	 * @param array $context Context.
	 */
	private static function render_text( $slot, $context ) {
		$v = $slot['v'] ?? '';
		switch ( $slot['k'] ?? 'text' ) {
			case 'html':
				return wp_kses( (string) $v, cf_allowed_inline_html() );
			case 'list':
				$out = '';
				foreach ( (array) ( $slot['items'] ?? array() ) as $item ) {
					$out .= self::render_template( $item['tpl'] ?? '', $item['slots'] ?? array(), $context );
				}
				return $out;
			case 'dyn':
				$fields = array();
				foreach ( (array) ( $slot['f'] ?? array() ) as $key => $field ) {
					$fields[ $key ] = $field['v'] ?? '';
				}
				/**
				 * Renders a dynamic region (implemented by the theme).
				 *
				 * @param string $html    HTML.
				 * @param string $type    Region type (news, events, resources, partners, sponsor_members, team).
				 * @param array  $options Locked options set by the migration.
				 * @param array  $fields  Editable labels.
				 * @param array  $context Context (post_id, lang).
				 */
				return (string) apply_filters( 'cf_render_dynamic', '', (string) ( $slot['type'] ?? '' ), (array) ( $slot['o'] ?? array() ), $fields, $context );
			case 'img':
				$url = $v ? wp_get_attachment_url( (int) $v ) : '';
				return $url ? esc_url( $url ) : '';
			case 'url':
				return esc_url( cf_url( (string) $v ) );
			default:
				return esc_html( is_scalar( $v ) ? (string) $v : '' );
		}
	}

	/**
	 * Render every visible section of a page.
	 *
	 * @param int $post_id Page ID.
	 * @return string
	 */
	public static function render( $post_id ) {
		$layout = self::get( $post_id );
		if ( ! $layout ) {
			return '';
		}
		$context = array(
			'post_id' => (int) $post_id,
			'lang'    => cf_lang_of( $post_id ),
		);
		$out     = '';
		foreach ( $layout['sections'] as $section ) {
			if ( ! empty( $section['hidden'] ) ) {
				continue;
			}
			$out .= self::render_template( $section['tpl'] ?? '', $section['slots'] ?? array(), $context ) . "\n";
		}
		return $out;
	}

	/**
	 * Does the layout contain a dynamic region of a given type (used to enqueue scripts)?
	 *
	 * @param int    $post_id Page ID.
	 * @param string $type    Region type.
	 */
	public static function has_dynamic( $post_id, $type ) {
		$layout = self::get( $post_id );
		if ( ! $layout ) {
			return false;
		}
		$found = false;
		$walk  = static function ( $slots ) use ( &$walk, $type, &$found ) {
			foreach ( (array) $slots as $slot ) {
				if ( 'dyn' === ( $slot['k'] ?? '' ) && $type === ( $slot['type'] ?? '' ) ) {
					$found = true;
				} elseif ( 'list' === ( $slot['k'] ?? '' ) ) {
					foreach ( (array) ( $slot['items'] ?? array() ) as $item ) {
						$walk( $item['slots'] ?? array() );
					}
				}
			}
		};
		foreach ( $layout['sections'] as $section ) {
			if ( empty( $section['hidden'] ) ) {
				$walk( $section['slots'] ?? array() );
			}
		}
		return $found;
	}

	/**
	 * Does any visible section template contain a marker (e.g. "data-donation-flow")?
	 *
	 * @param int    $post_id Page ID.
	 * @param string $marker  Substring.
	 */
	public static function has_marker( $post_id, $marker ) {
		$layout = self::get( $post_id );
		if ( ! $layout ) {
			return false;
		}
		foreach ( $layout['sections'] as $section ) {
			if ( empty( $section['hidden'] ) && str_contains( (string) ( $section['tpl'] ?? '' ), $marker ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * First image of the first section (used as default share image).
	 *
	 * @param int $post_id Page ID.
	 * @return int Attachment ID or 0.
	 */
	public static function first_image( $post_id ) {
		$layout = self::get( $post_id );
		if ( ! $layout || empty( $layout['sections'] ) ) {
			return 0;
		}
		foreach ( $layout['sections'] as $section ) {
			foreach ( (array) ( $section['slots'] ?? array() ) as $slot ) {
				if ( 'img' === ( $slot['k'] ?? '' ) && ! empty( $slot['v'] ) ) {
					return (int) $slot['v'];
				}
			}
		}
		return 0;
	}
}
