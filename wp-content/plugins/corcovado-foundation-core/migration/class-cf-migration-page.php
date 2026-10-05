<?php
/**
 * Corcovado Foundation → Import / Migration screen.
 *
 * @package CorcovadoFoundationCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Migration admin page.
 */
class CF_Migration_Page {

	const LOG_OPTION = 'cf_migration_last_log';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_post_cf_migrate', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Submenu.
	 */
	public static function menu() {
		add_submenu_page(
			'corcovado-foundation',
			__( 'Import / Migration', 'corcovado-foundation-core' ),
			__( 'Import / Migration', 'corcovado-foundation-core' ),
			'manage_options',
			'cf-migration',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Run the importer.
	 */
	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'corcovado-foundation-core' ) );
		}
		check_admin_referer( 'cf_migrate' );
		$dry      = isset( $_POST['dry_run'] );
		$importer = new CF_Importer(
			$dry,
			array(
				'update'    => ! empty( $_POST['update'] ),
				'configure' => ! empty( $_POST['configure'] ),
				'menus'     => ! empty( $_POST['menus'] ),
			)
		);
		$result = $importer->run();
		update_option(
			self::LOG_OPTION,
			array(
				'time'  => time(),
				'user'  => get_current_user_id(),
				'dry'   => $dry,
				'log'   => $result['log'],
				'stats' => $result['stats'],
			),
			false
		);
		wp_safe_redirect( admin_url( 'admin.php?page=cf-migration&done=1' ) );
		exit;
	}

	/**
	 * Screen.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$data = CF_Importer::load();
		echo '<div class="wrap"><h1>' . esc_html__( 'Corcovado Foundation — Import / Migration', 'corcovado-foundation-core' ) . '</h1>';
		echo '<p>' . esc_html__( 'Imports the content of the original static website (pages, images, PDFs, news, events, team, library, partners, translations, menus). It can be run several times: items already imported are recognised and never duplicated. Nothing is ever deleted.', 'corcovado-foundation-core' ) . '</p>';

		if ( is_wp_error( $data ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $data->get_error_message() ) . '</p></div></div>';
			return;
		}

		$missing = array();
		foreach ( $data['media'] as $m ) {
			if ( ! is_readable( CF_Importer::source_dir() . $m['key'] ) ) {
				$missing[] = $m['key'];
			}
		}
		echo '<h2>' . esc_html__( 'Content to import', 'corcovado-foundation-core' ) . '</h2><table class="widefat striped" style="max-width:640px"><tbody>';
		$rows = array(
			__( 'Pages', 'corcovado-foundation-core' )             => count( $data['pages'] ),
			__( 'Images and documents', 'corcovado-foundation-core' ) => count( $data['media'] ),
			__( 'News', 'corcovado-foundation-core' )              => count( $data['news'] ?? array() ),
			__( 'Events', 'corcovado-foundation-core' )            => count( $data['events'] ?? array() ),
			__( 'Team members', 'corcovado-foundation-core' )      => count( $data['team'] ?? array() ),
			__( 'Library resources', 'corcovado-foundation-core' ) => count( $data['resources'] ?? array() ),
			__( 'Partners', 'corcovado-foundation-core' )          => count( $data['partners'] ?? array() ),
		);
		foreach ( $rows as $label => $n ) {
			echo '<tr><td>' . esc_html( $label ) . '</td><td>' . esc_html( (string) $n ) . '</td></tr>';
		}
		echo '<tr><td>' . esc_html__( 'Source', 'corcovado-foundation-core' ) . '</td><td><code>' . esc_html( (string) ( $data['source_commit'] ?? '' ) ) . '</code> · ' . esc_html( (string) ( $data['generated'] ?? '' ) ) . '</td></tr>';
		echo '</tbody></table>';

		if ( $missing ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html(
				sprintf(
					/* translators: %d: number of files */
					__( '%d original files are not in migration/source/. Use the packaged plugin ZIP (it contains them) or copy the assets/ and library/ folders of the static site there.', 'corcovado-foundation-core' ),
					count( $missing )
				)
			) . '</p></div>';
		}

		if ( 'corcovado-foundation' !== get_template() ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Activate the "Corcovado Foundation" theme first so that menus and header/footer texts can be imported.', 'corcovado-foundation-core' ) . '</p></div>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:16px">';
		wp_nonce_field( 'cf_migrate' );
		echo '<input type="hidden" name="action" value="cf_migrate">';
		echo '<p><label><input type="checkbox" name="menus" value="1" checked> ' . esc_html__( 'Create menus and header/footer texts (only where none exist yet)', 'corcovado-foundation-core' ) . '</label></p>';
		echo '<p><label><input type="checkbox" name="configure" value="1"> ' . esc_html__( 'Configure the site: front page = Home, permalinks "/%postname%", comments closed by default', 'corcovado-foundation-core' ) . '</label></p>';
		echo '<p><label><input type="checkbox" name="update" value="1"> ' . esc_html__( 'Refresh items that were already imported with the original content (overwrites edits made to those items)', 'corcovado-foundation-core' ) . '</label></p>';
		echo '<p>';
		submit_button( __( 'Dry run (simulate, write nothing)', 'corcovado-foundation-core' ), 'secondary', 'dry_run', false );
		echo ' ';
		submit_button( __( 'Run import', 'corcovado-foundation-core' ), 'primary', 'run', false );
		echo '</p></form>';

		$last = get_option( self::LOG_OPTION );
		if ( is_array( $last ) && ! empty( $last['log'] ) ) {
			echo '<h2>' . esc_html( $last['dry'] ? __( 'Last dry run', 'corcovado-foundation-core' ) : __( 'Last import', 'corcovado-foundation-core' ) ) . ' — ' . esc_html( wp_date( 'Y-m-d H:i', (int) $last['time'] ) ) . '</h2>';
			echo '<div style="max-height:480px;overflow:auto;background:#fff;border:1px solid #dcdcde;padding:8px 12px;font-family:monospace;font-size:12px">';
			$colors = array(
				'error' => '#b32d2e',
				'warn'  => '#996800',
				'ok'    => '#1d2327',
				'info'  => '#2271b1',
				'skip'  => '#8c8f94',
			);
			foreach ( $last['log'] as $line ) {
				echo '<div style="color:' . esc_attr( $colors[ $line[0] ] ?? '#1d2327' ) . '">[' . esc_html( strtoupper( $line[0] ) ) . '] ' . esc_html( $line[1] ) . '</div>';
			}
			echo '</div>';
		}
		echo '</div>';
	}
}
