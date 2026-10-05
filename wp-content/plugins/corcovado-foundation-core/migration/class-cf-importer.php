<?php
/**
 * Importer for the content extracted from the static site (migration/data/content.json).
 *
 * - Idempotent: every imported item carries "_cf_source_key"; running the importer again
 *   finds it instead of creating a duplicate.
 * - Non-destructive: it never deletes anything. Existing imported items are skipped unless
 *   the administrator explicitly asks to refresh them with the original content.
 * - Dry run: the same code path, without writes, reporting what would happen.
 *
 * @package CorcovadoFoundationCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Importer.
 */
class CF_Importer {

	const KEY = '_cf_source_key';

	/**
	 * Dry run.
	 *
	 * @var bool
	 */
	private $dry;

	/**
	 * Options.
	 *
	 * @var array
	 */
	private $opt;

	/**
	 * Log lines.
	 *
	 * @var array
	 */
	private $log = array();

	/**
	 * Counters.
	 *
	 * @var array
	 */
	private $stats = array(
		'created' => 0,
		'updated' => 0,
		'skipped' => 0,
		'errors'  => 0,
	);

	/**
	 * Source key → ID.
	 *
	 * @var array
	 */
	private $ids = array();

	/**
	 * Data.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Whether this run changed the permalink structure.
	 *
	 * @var bool
	 */
	private $permalinks_changed = false;

	/**
	 * Constructor.
	 *
	 * @param bool  $dry Dry run.
	 * @param array $opt Options: update (bool), configure (bool), menus (bool).
	 */
	public function __construct( $dry, $opt ) {
		$this->dry = (bool) $dry;
		$this->opt = wp_parse_args(
			$opt,
			array(
				'update'    => false,
				'configure' => false,
				'menus'     => true,
			)
		);
	}

	/**
	 * Data file path.
	 */
	public static function data_file() {
		return CF_CORE_DIR . 'migration/data/content.json';
	}

	/**
	 * Folder with the original files (images, PDFs), copied by tools/package.sh.
	 */
	public static function source_dir() {
		/**
		 * Filters the folder that contains the original site files (assets/, library/).
		 *
		 * @param string $dir Folder (with trailing slash).
		 */
		return trailingslashit( (string) apply_filters( 'cf_migration_source_dir', CF_CORE_DIR . 'migration/source/' ) );
	}

	/**
	 * Load data.
	 *
	 * @return array|WP_Error
	 */
	public static function load() {
		$file = self::data_file();
		if ( ! is_readable( $file ) ) {
			return new WP_Error( 'cf_no_data', __( 'migration/data/content.json is missing.', 'corcovado-foundation-core' ) );
		}
		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		if ( ! is_array( $data ) || empty( $data['pages'] ) ) {
			return new WP_Error( 'cf_bad_data', __( 'migration/data/content.json is not valid.', 'corcovado-foundation-core' ) );
		}
		return $data;
	}

	/**
	 * Log.
	 *
	 * @param string $level Level (ok, skip, warn, error, info).
	 * @param string $msg   Message.
	 */
	private function log( $level, $msg ) {
		$this->log[] = array( $level, $msg );
		if ( 'error' === $level ) {
			++$this->stats['errors'];
		}
	}

	/**
	 * Run.
	 *
	 * @return array{log:array,stats:array}
	 */
	public function run() {
		$data = self::load();
		if ( is_wp_error( $data ) ) {
			$this->log( 'error', $data->get_error_message() );
			return $this->result();
		}
		$this->data = $data;
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- may be disabled by the host.
		}
		wp_suspend_cache_invalidation( false );
		$this->log( 'info', sprintf( 'Source: commit %s, generated %s. Mode: %s%s.', $data['source_commit'] ?? '?', $data['generated'] ?? '?', $this->dry ? 'DRY RUN (nothing is written)' : 'IMPORT', $this->opt['update'] ? ', refresh existing items' : ', keep existing items' ) );

		$this->import_media();
		$this->import_partners();
		$this->import_terms( 'cf_resource_category', $data['resource_categories'] ?? array() );
		$this->import_terms( 'cf_team_group', $data['team_groups'] ?? array() );
		$this->import_pages();
		$this->import_news();
		$this->import_items( 'cf_event', $data['events'] ?? array() );
		$this->import_items( 'cf_team', $data['team'] ?? array() );
		$this->import_items( 'cf_resource', $data['resources'] ?? array() );
		$this->link_translations();
		$this->apply_settings();
		if ( $this->opt['menus'] ) {
			$this->import_menus_and_texts();
		}
		if ( $this->opt['configure'] ) {
			$this->configure_site();
		}
		if ( ! $this->dry ) {
			// A hard flush also writes the rewrite rules to .htaccess (Apache) when the
			// permalink structure was just changed; otherwise the rules in the database are enough.
			flush_rewrite_rules( $this->permalinks_changed );
			if ( $this->permalinks_changed ) {
				$this->check_server_rewrites();
			}
		}
		$this->log( 'info', sprintf( 'Done. Created %d, updated %d, skipped (already present) %d, errors %d.', $this->stats['created'], $this->stats['updated'], $this->stats['skipped'], $this->stats['errors'] ) );
		return $this->result();
	}

	/**
	 * Result.
	 */
	private function result() {
		return array(
			'log'   => $this->log,
			'stats' => $this->stats,
		);
	}

	/**
	 * Find an imported object by source key.
	 *
	 * @param string $key       Key.
	 * @param string $post_type Post type.
	 */
	public static function find( $key, $post_type ) {
		$ids = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => 'any' === $post_type ? 'any' : array( 'publish', 'draft', 'pending', 'private', 'future', 'inherit' ),
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'meta_key'         => self::KEY, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Find an imported term by source key.
	 *
	 * @param string $key      Key.
	 * @param string $taxonomy Taxonomy.
	 */
	private static function find_term( $key, $taxonomy ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
				'meta_key'   => self::KEY, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
				'number'     => 1,
			)
		);
		return ( ! is_wp_error( $terms ) && $terms ) ? (int) $terms[0] : 0;
	}

	// ---------------------------------------------------------------------
	// Media.
	// ---------------------------------------------------------------------

	/**
	 * Import images and documents into the Media Library (original files, no recompression).
	 */
	private function import_media() {
		$dir = self::source_dir();
		add_filter( 'big_image_size_threshold', '__return_false', 99 );
		add_filter( 'upload_mimes', array( __CLASS__, 'allow_svg_during_import' ), 99 );
		add_filter( 'wp_check_filetype_and_ext', array( __CLASS__, 'svg_filetype' ), 99, 4 );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		foreach ( $this->data['media'] as $m ) {
			$key      = $m['key'];
			$existing = self::find( $key, 'attachment' );
			if ( $existing ) {
				$this->ids[ 'm:' . $key ] = $existing;
				++$this->stats['skipped'];
				continue;
			}
			$path = $dir . $key;
			if ( ! is_readable( $path ) ) {
				$this->log( 'error', 'Missing source file: ' . $key . ' (run tools/package.sh or copy the original assets/ and library/ folders into migration/source/).' );
				continue;
			}
			if ( $this->dry ) {
				$this->log( 'ok', 'Would import file ' . $key );
				$this->ids[ 'm:' . $key ] = 0;
				++$this->stats['created'];
				continue;
			}
			$name = basename( $key );
			// tort.webp is really a PNG: keep the real format so browsers and WordPress agree.
			$info = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $info && 'image/png' === $info['mime'] && ! preg_match( '/\.png$/i', $name ) ) {
				$name = preg_replace( '/\.[a-z0-9]+$/i', '.png', $name );
				$this->log( 'warn', $key . ' is a PNG file with another extension; imported as ' . $name . '.' );
			}
			$tmp = wp_tempnam( $name );
			if ( ! $tmp || ! copy( $path, $tmp ) ) {
				$this->log( 'error', 'Could not copy ' . $key );
				continue;
			}
			$id = media_handle_sideload(
				array(
					'name'     => $name,
					'tmp_name' => $tmp,
				),
				0,
				null,
				array( 'post_title' => preg_replace( '/\.[a-z0-9]+$/i', '', trim( basename( $key ) ) ) )
			);
			if ( is_wp_error( $id ) ) {
				wp_delete_file( $tmp );
				$this->log( 'error', 'Import failed for ' . $key . ': ' . $id->get_error_message() );
				continue;
			}
			update_post_meta( $id, self::KEY, $key );
			if ( ! empty( $m['alt'] ) ) {
				update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $m['alt'] ) );
			}
			$this->ids[ 'm:' . $key ] = (int) $id;
			++$this->stats['created'];
			$this->log( 'ok', 'Imported file ' . $key . ' → #' . $id );
		}
		remove_filter( 'big_image_size_threshold', '__return_false', 99 );
		remove_filter( 'upload_mimes', array( __CLASS__, 'allow_svg_during_import' ), 99 );
		remove_filter( 'wp_check_filetype_and_ext', array( __CLASS__, 'svg_filetype' ), 99 );
	}

	/**
	 * SVG files of the original site are trusted repository files: allowed only while importing.
	 *
	 * @param array $mimes Mimes.
	 */
	public static function allow_svg_during_import( $mimes ) {
		$mimes['svg'] = 'image/svg+xml';
		return $mimes;
	}

	/**
	 * File type check for SVG during import.
	 *
	 * @param array  $data     Data.
	 * @param string $file     File.
	 * @param string $filename Name.
	 * @param array  $mimes    Mimes.
	 */
	public static function svg_filetype( $data, $file, $filename, $mimes ) {
		if ( preg_match( '/\.svg$/i', (string) $filename ) ) {
			return array(
				'ext'             => 'svg',
				'type'            => 'image/svg+xml',
				'proper_filename' => false,
			);
		}
		return $data;
	}

	/**
	 * Media ID from a key.
	 *
	 * @param string $key Key.
	 */
	private function media_id( $key ) {
		if ( '' === (string) $key ) {
			return 0;
		}
		if ( ! array_key_exists( 'm:' . $key, $this->ids ) ) {
			$this->ids[ 'm:' . $key ] = self::find( $key, 'attachment' );
		}
		return (int) $this->ids[ 'm:' . $key ];
	}

	/**
	 * Convert "media:KEY" links to "media:ID".
	 *
	 * @param string $url Link.
	 */
	private function link( $url ) {
		if ( is_string( $url ) && str_starts_with( $url, 'media:' ) ) {
			$id = $this->media_id( substr( $url, 6 ) );
			return $id ? 'media:' . $id : ( $this->dry ? $url : '' );
		}
		return $url;
	}

	/**
	 * Convert slots (media keys → IDs), recursively.
	 *
	 * @param array $slots Slots.
	 */
	private function convert_slots( $slots ) {
		foreach ( $slots as $i => $slot ) {
			switch ( $slot['k'] ?? '' ) {
				case 'img':
					$slots[ $i ]['v'] = $this->media_id( (string) $slot['v'] );
					break;
				case 'url':
					$slots[ $i ]['v'] = $this->link( (string) $slot['v'] );
					break;
				case 'list':
					foreach ( $slot['items'] as $n => $item ) {
						$slots[ $i ]['items'][ $n ]['slots'] = $this->convert_slots( $item['slots'] );
					}
					break;
				case 'dyn':
					if ( isset( $slot['o']['keys'] ) ) {
						$ids = array();
						foreach ( $slot['o']['keys'] as $pk ) {
							$ids[] = (int) ( $this->ids[ $pk ] ?? self::find( $pk, 'cf_partner' ) );
						}
						$slots[ $i ]['o']['ids'] = $ids;
						unset( $slots[ $i ]['o']['keys'] );
					}
					break;
			}
		}
		return $slots;
	}

	// ---------------------------------------------------------------------
	// Posts.
	// ---------------------------------------------------------------------

	/**
	 * Create or (optionally) refresh a post.
	 *
	 * @param string $key  Source key.
	 * @param array  $args wp_insert_post args.
	 * @param array  $meta Meta.
	 * @param int    $thumb Featured image ID.
	 * @return int ID (0 in dry run for new items).
	 */
	private function upsert( $key, $args, $meta = array(), $thumb = 0 ) {
		$existing = self::find( $key, $args['post_type'] );
		if ( $existing && ! $this->opt['update'] ) {
			$this->ids[ $key ] = $existing;
			++$this->stats['skipped'];
			return $existing;
		}
		$label = $args['post_type'] . ' "' . $args['post_title'] . '"';
		if ( $this->dry ) {
			$this->log( 'ok', ( $existing ? 'Would refresh ' : 'Would create ' ) . $label );
			++$this->stats[ $existing ? 'updated' : 'created' ];
			$this->ids[ $key ] = $existing;
			return $existing;
		}
		if ( $existing ) {
			$args['ID'] = $existing;
			$id         = wp_update_post( wp_slash( $args ), true );
		} else {
			$id = wp_insert_post( wp_slash( $args ), true );
		}
		if ( is_wp_error( $id ) ) {
			$this->log( 'error', 'Failed ' . $label . ': ' . $id->get_error_message() );
			return 0;
		}
		update_post_meta( $id, self::KEY, $key );
		foreach ( $meta as $mk => $mv ) {
			if ( '_cf_layout' === $mk ) {
				CF_Layout::update( $id, $mv );
			} elseif ( '' === $mv || null === $mv ) {
				delete_post_meta( $id, $mk );
			} else {
				update_post_meta( $id, $mk, wp_slash( $mv ) );
			}
		}
		if ( $thumb ) {
			set_post_thumbnail( $id, $thumb );
		}
		$this->ids[ $key ] = (int) $id;
		++$this->stats[ $existing ? 'updated' : 'created' ];
		$this->log( 'ok', ( $existing ? 'Refreshed ' : 'Created ' ) . $label . ' → #' . $id );
		return (int) $id;
	}

	/**
	 * Partners (language neutral).
	 */
	private function import_partners() {
		foreach ( $this->data['partners'] ?? array() as $p ) {
			$this->upsert(
				$p['key'],
				array(
					'post_type'   => 'cf_partner',
					'post_status' => 'publish',
					'post_title'  => $p['title'],
					'menu_order'  => (int) $p['order'],
				),
				array(
					'_cf_url'      => $p['fields']['url'] ?? '',
					'_cf_logo_alt' => $p['fields']['logo_alt'] ?? '',
				),
				$this->media_id( $p['image'] )
			);
		}
	}

	/**
	 * Taxonomy terms.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param array  $terms    Terms.
	 */
	private function import_terms( $taxonomy, $terms ) {
		foreach ( $terms as $t ) {
			$existing = self::find_term( $t['key'], $taxonomy );
			if ( $existing ) {
				$this->ids[ $t['key'] ] = $existing;
				++$this->stats['skipped'];
				continue;
			}
			if ( $this->dry ) {
				$this->log( 'ok', 'Would create ' . $taxonomy . ' "' . $t['name'] . '"' );
				$this->ids[ $t['key'] ] = 0;
				++$this->stats['created'];
				continue;
			}
			$slug = sanitize_title( $t['name'] ) . ( 'es' === $t['lang'] ? '-es' : '' );
			$res  = wp_insert_term( $t['name'], $taxonomy, array( 'slug' => $slug ) );
			if ( is_wp_error( $res ) ) {
				$this->log( 'error', 'Term "' . $t['name'] . '": ' . $res->get_error_message() );
				continue;
			}
			$id = (int) $res['term_id'];
			update_term_meta( $id, self::KEY, $t['key'] );
			update_term_meta( $id, '_cf_lang', $t['lang'] );
			update_term_meta( $id, '_cf_order', (int) $t['order'] );
			$this->ids[ $t['key'] ] = $id;
			++$this->stats['created'];
			$this->log( 'ok', 'Created ' . $taxonomy . ' "' . $t['name'] . '" → #' . $id );
		}
	}

	/**
	 * Pages: English top level, Spanish home (/es/) and its children.
	 */
	private function import_pages() {
		$pages = $this->data['pages'];
		// Spanish home first among Spanish pages, so children get their parent.
		usort(
			$pages,
			static function ( $a, $b ) {
				$rank = static fn( $p ) => 'en' === $p['lang'] ? 0 : ( ! empty( $p['es_root'] ) ? 1 : 2 );
				return $rank( $a ) <=> $rank( $b );
			}
		);
		foreach ( $pages as $order => $p ) {
			$parent = 0;
			if ( ! empty( $p['parent'] ) ) {
				$parent = (int) ( $this->ids[ $p['parent'] ] ?? self::find( $p['parent'], 'page' ) );
				if ( ! $parent && ! $this->dry ) {
					$this->log( 'error', 'Parent page missing for ' . $p['key'] );
					continue;
				}
			}
			$layout             = $p['layout'];
			$layout['sections'] = array_map(
				function ( $s ) {
					$s['slots'] = $this->convert_slots( $s['slots'] );
					return $s;
				},
				$layout['sections']
			);
			$this->upsert(
				$p['key'],
				array(
					'post_type'      => 'page',
					'post_status'    => ! empty( $p['is_404'] ) ? 'private' : 'publish',
					'post_title'     => $p['title'],
					'post_name'      => '' !== $p['slug'] ? $p['slug'] : 'home',
					'post_parent'    => $parent,
					'post_content'   => '',
					'menu_order'     => $order,
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				),
				array(
					'_cf_layout'          => $layout,
					'_cf_lang'            => $p['lang'],
					'_cf_seo_title'       => $p['seo_title'],
					'_cf_seo_description' => $p['seo_description'],
					'_cf_og_image'        => $this->media_id( $p['og_image'] ),
				)
			);
		}
	}

	/**
	 * News stories (the static site only had card texts: they become the excerpt and body).
	 */
	private function import_news() {
		$base = strtotime( '2026-09-29 12:00:00' ); // Last update of the static site (sitemap lastmod).
		foreach ( $this->data['news'] ?? array() as $n ) {
			$time = gmdate( 'Y-m-d H:i:s', $base - 60 * (int) $n['order'] );
			$this->upsert(
				$n['key'],
				array(
					'post_type'      => 'cf_news',
					'post_status'    => 'publish',
					'post_title'     => $n['title'],
					'post_name'      => sanitize_title( $n['title'] ),
					'post_excerpt'   => $n['excerpt'],
					'post_content'   => "<!-- wp:paragraph -->\n<p>" . esc_html( $n['excerpt'] ) . "</p>\n<!-- /wp:paragraph -->",
					'post_date'      => $time,
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				),
				array(
					'_cf_lang'        => $n['lang'],
					'_cf_hide_date'   => 1,
					'_cf_short_title' => $n['fields']['short_title'] ?? '',
					'_cf_short_excerpt' => $n['fields']['short_excerpt'] ?? '',
				)
			);
		}
	}

	/**
	 * Events, team members and resources.
	 *
	 * @param string $type  Post type.
	 * @param array  $items Items.
	 */
	private function import_items( $type, $items ) {
		$defs = CF_Post_Types::fields()[ $type ];
		foreach ( $items as $it ) {
			$meta = array( '_cf_lang' => $it['lang'] );
			foreach ( $defs as $field => $ftype ) {
				$value = $it['fields'][ $field ] ?? '';
				if ( 'url' === $ftype ) {
					$value = $this->link( (string) $value );
				}
				$meta[ '_cf_' . $field ] = ( 'bool' === $ftype ) ? ( $value ? 1 : '' ) : $value;
			}
			if ( 'cf_team' === $type ) {
				$meta['_cf_member_id']   = $it['slug'];
				$meta['_cf_modal_image'] = ! empty( $it['modal_image'] ) ? $this->media_id( $it['modal_image'] ) : '';
			}
			$args = array(
				'post_type'   => $type,
				'post_status' => 'publish',
				'post_title'  => $it['title'],
				'menu_order'  => (int) $it['order'],
			);
			if ( ! empty( $it['slug'] ) ) {
				$args['post_name'] = $it['slug'];
			}
			$id = $this->upsert( $it['key'], $args, $meta, ! empty( $it['image'] ) ? $this->media_id( $it['image'] ) : 0 );
			$term_key = 'cf_team' === $type ? ( $it['group'] ?? '' ) : ( $it['category'] ?? '' );
			if ( $id && $term_key && ! $this->dry ) {
				$term = (int) ( $this->ids[ $term_key ] ?? 0 );
				if ( $term ) {
					wp_set_object_terms( $id, array( $term ), 'cf_team' === $type ? 'cf_team_group' : 'cf_resource_category', false );
				}
			}
		}
	}

	/**
	 * Link EN ↔ ES translations (pages, news, events, team, resources).
	 */
	private function link_translations() {
		$sets = array(
			'page'        => $this->data['pages'],
			'cf_news'     => $this->data['news'] ?? array(),
			'cf_event'    => $this->data['events'] ?? array(),
			'cf_team'     => $this->data['team'] ?? array(),
			'cf_resource' => $this->data['resources'] ?? array(),
		);
		$count = 0;
		foreach ( $sets as $type => $items ) {
			foreach ( $items as $it ) {
				if ( empty( $it['translation'] ) ) {
					continue;
				}
				$a = (int) ( $this->ids[ $it['key'] ] ?? self::find( $it['key'], $type ) );
				$b = (int) ( $this->ids[ $it['translation'] ] ?? self::find( $it['translation'], $type ) );
				if ( ! $a || ! $b ) {
					continue;
				}
				if ( CF_Languages::translation_of( $a ) === $b ) {
					continue;
				}
				if ( CF_Languages::translation_of( $a ) || CF_Languages::translation_of( $b ) ) {
					if ( ! $this->opt['update'] ) {
						continue; // Respect links changed by editors.
					}
				}
				if ( ! $this->dry ) {
					CF_Languages::link( $a, $b );
				}
				++$count;
			}
		}
		$this->log( 'ok', sprintf( '%s %d translation links.', $this->dry ? 'Would create' : 'Created', $count ) );
	}

	/**
	 * Plugin settings that depend on imported IDs (only empty settings are filled).
	 */
	private function apply_settings() {
		$map = array( 'es_root' => null, 'page_404_en' => null, 'page_404_es' => null );
		foreach ( $this->data['pages'] as $p ) {
			if ( ! empty( $p['es_root'] ) ) {
				$map['es_root'] = $p['key'];
			}
			if ( ! empty( $p['is_404'] ) ) {
				$map[ 'page_404_' . $p['lang'] ] = $p['key'];
			}
		}
		foreach ( $map as $setting => $key ) {
			$id = $key ? (int) ( $this->ids[ $key ] ?? self::find( $key, 'page' ) ) : 0;
			if ( $id && ( ! CF_Settings::get( $setting ) || $this->opt['update'] ) ) {
				if ( ! $this->dry ) {
					CF_Settings::set( $setting, $id );
				}
				$this->log( 'ok', ( $this->dry ? 'Would set ' : 'Set ' ) . $setting . ' = #' . $id );
			}
		}
		$og = $this->media_id( 'assets/img/hero-corcovado.webp' );
		if ( $og && ! CF_Settings::get( 'default_og_image' ) ) {
			if ( ! $this->dry ) {
				CF_Settings::set( 'default_og_image', $og );
			}
			$this->log( 'ok', 'Default share image = hero-corcovado.webp' );
		}
	}

	/**
	 * Menus and header/footer texts (theme mods of the active theme).
	 */
	private function import_menus_and_texts() {
		$chrome = $this->data['chrome'] ?? array();
		if ( ! $chrome ) {
			return;
		}
		if ( 'corcovado-foundation' !== get_stylesheet() && 'corcovado-foundation' !== get_template() ) {
			$this->log( 'warn', 'Activate the "Corcovado Foundation" theme before importing menus and header/footer texts (skipped).' );
			return;
		}
		$locations = get_theme_mod( 'nav_menu_locations', array() );
		$names     = array(
			'en' => array( 'Main menu (EN)', 'Footer column 1 (EN)', 'Footer column 2 (EN)', 'Footer column 3 (EN)' ),
			'es' => array( 'Menú principal (ES)', 'Footer columna 1 (ES)', 'Footer columna 2 (ES)', 'Footer columna 3 (ES)' ),
		);
		foreach ( $chrome as $lang => $c ) {
			$menus = array( 'primary_' . $lang => array( $names[ $lang ][0], $c['nav'] ) );
			foreach ( $c['footer_columns'] as $n => $col ) {
				$menus[ 'footer_' . ( $n + 1 ) . '_' . $lang ] = array( $names[ $lang ][ $n + 1 ], $col['links'] );
			}
			foreach ( $menus as $location => $def ) {
				if ( ! empty( $locations[ $location ] ) && wp_get_nav_menu_object( $locations[ $location ] ) ) {
					++$this->stats['skipped'];
					continue;
				}
				if ( $this->dry ) {
					$this->log( 'ok', 'Would create menu "' . $def[0] . '"' );
					continue;
				}
				$menu = wp_get_nav_menu_object( $def[0] );
				$menu_id = $menu ? (int) $menu->term_id : (int) wp_create_nav_menu( $def[0] );
				if ( ! $menu ) {
					foreach ( $def[1] as $pos => $link ) {
						$this->add_menu_item( $menu_id, $link, $pos + 1 );
					}
				}
				$locations[ $location ] = $menu_id;
				$this->log( 'ok', 'Menu "' . $def[0] . '" → ' . $location );
			}
			$mods = $c['mods'];
			$mods['footer_col_1'] = $c['footer_columns'][0]['title'] ?? '';
			$mods['footer_col_2'] = $c['footer_columns'][1]['title'] ?? '';
			$mods['footer_col_3'] = $c['footer_columns'][2]['title'] ?? '';
			$labels               = $mods['social_labels'] ?? array();
			unset( $mods['social_labels'] );
			$mods['social_label_email'] = $labels[2] ?? 'Email';
			foreach ( $mods as $k => $v ) {
				$mod = 'cf_' . $k . '_' . $lang;
				if ( false === get_theme_mod( $mod, false ) || $this->opt['update'] ) {
					if ( ! $this->dry ) {
						set_theme_mod( $mod, $v );
					}
				}
			}
			foreach ( array( 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'email' => 'Email' ) as $k => $label ) {
				if ( isset( $c['social'][ $label ] ) && ( false === get_theme_mod( 'cf_social_' . $k, false ) || $this->opt['update'] ) && ! $this->dry ) {
					set_theme_mod( 'cf_social_' . $k, $c['social'][ $label ] );
				}
			}
			$this->log( 'ok', ( $this->dry ? 'Would set' : 'Set' ) . ' header/footer texts (' . strtoupper( $lang ) . ').' );
		}
		if ( ! $this->dry ) {
			set_theme_mod( 'nav_menu_locations', $locations );
		}
	}

	/**
	 * Add a menu item: page links become page items (they follow slug changes).
	 *
	 * @param int   $menu_id Menu.
	 * @param array $link    {label,url}.
	 * @param int   $pos     Position.
	 */
	private function add_menu_item( $menu_id, $link, $pos ) {
		$url  = (string) $link['url'];
		$page = 0;
		if ( str_starts_with( $url, '/' ) ) {
			$path = trim( strtok( $url, '#' ), '/' );
			if ( '' === $path ) {
				$page = (int) ( $this->ids['page:en:home'] ?? self::find( 'page:en:home', 'page' ) );
			} else {
				$obj  = get_page_by_path( $path );
				$page = $obj ? (int) $obj->ID : 0;
			}
		}
		$args = array(
			'menu-item-title'    => $link['label'],
			'menu-item-status'   => 'publish',
			'menu-item-position' => $pos,
		);
		if ( $page ) {
			$args['menu-item-object-id'] = $page;
			$args['menu-item-object']    = 'page';
			$args['menu-item-type']      = 'post_type';
		} else {
			$args['menu-item-type'] = 'custom';
			$args['menu-item-url']  = str_starts_with( $url, '/' ) ? home_url( $url ) : $url;
		}
		wp_update_nav_menu_item( $menu_id, 0, $args );
	}

	/**
	 * Warn when Apache cannot receive the rewrite rules (pages other than the home would 404).
	 */
	private function check_server_rewrites() {
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'got_mod_rewrite' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}
		if ( ! got_mod_rewrite() ) {
			return; // Nginx and other servers: rewrite configuration is done in the server.
		}
		$htaccess = get_home_path() . '.htaccess';
		$contents = is_readable( $htaccess ) ? (string) file_get_contents( $htaccess ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! str_contains( $contents, 'RewriteRule' ) ) {
			$this->log( 'warn', 'The .htaccess file could not be updated. Open Settings → Permalinks and press "Save Changes" (or copy the rules shown there into .htaccess).' );
		}
	}

	/**
	 * Front page, permalinks (only when the administrator ticks the option).
	 */
	private function configure_site() {
		$front = (int) ( $this->ids['page:en:home'] ?? self::find( 'page:en:home', 'page' ) );
		if ( $this->dry ) {
			$this->log( 'ok', 'Would set the front page, permalinks "/%postname%" and the site language options.' );
			return;
		}
		if ( $front ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $front );
		}
		if ( '/%postname%' !== get_option( 'permalink_structure' ) ) {
			global $wp_rewrite;
			$wp_rewrite->set_permalink_structure( '/%postname%' );
			$this->permalinks_changed = true;
		}
		update_option( 'default_comment_status', 'closed' );
		update_option( 'default_ping_status', 'closed' );
		$this->log( 'ok', 'Front page = Home, permalinks = /%postname%, comments closed by default.' );
	}
}
