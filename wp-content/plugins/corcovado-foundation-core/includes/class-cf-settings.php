<?php
/**
 * Plugin settings: Corcovado Foundation → Settings.
 *
 * @package CorcovadoFoundationCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings registry and admin page.
 */
class CF_Settings {

	const OPTION = 'cf_core_settings';

	/**
	 * Defaults. Only public identifiers live here; nothing secret.
	 */
	public static function defaults() {
		return array(
			'es_root'            => 0,
			'site_name_es'       => 'Fundación Corcovado',
			'page_404_en'        => 0,
			'page_404_es'        => 0,
			'classy_campaign_id' => '568425',
			'form_recipient'     => 'info@corcovadofoundation.org',
			'form_rate_limit'    => 5,
			'seo_output'         => 'auto',
			'org_schema'         => 0,
			'default_og_image'   => 0,
			'security_headers'   => 1,
			'legacy_redirects'   => 1,
		);
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Read a setting.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 */
	public static function get( $key, $default = '' ) {
		$all = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Update one setting (used by the importer).
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 */
	public static function set( $key, $value ) {
		$all         = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		$all[ $key ] = $value;
		update_option( self::OPTION, self::sanitize( $all ) );
	}

	/**
	 * Admin menu.
	 */
	public static function menu() {
		add_menu_page(
			__( 'Corcovado Foundation', 'corcovado-foundation-core' ),
			__( 'Corcovado Foundation', 'corcovado-foundation-core' ),
			'manage_options',
			'corcovado-foundation',
			array( __CLASS__, 'render' ),
			'dashicons-palmtree',
			3
		);
		add_submenu_page(
			'corcovado-foundation',
			__( 'Settings', 'corcovado-foundation-core' ),
			__( 'Settings', 'corcovado-foundation-core' ),
			'manage_options',
			'corcovado-foundation',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Register setting.
	 */
	public static function register() {
		register_setting(
			'cf_core_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize all settings.
	 *
	 * @param mixed $input Raw input.
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$d     = self::defaults();
		$out   = array();
		foreach ( array( 'es_root', 'page_404_en', 'page_404_es', 'default_og_image' ) as $k ) {
			$out[ $k ] = isset( $input[ $k ] ) ? absint( $input[ $k ] ) : $d[ $k ];
		}
		$out['site_name_es']       = isset( $input['site_name_es'] ) ? sanitize_text_field( $input['site_name_es'] ) : $d['site_name_es'];
		$campaign                  = isset( $input['classy_campaign_id'] ) ? preg_replace( '/[^0-9]/', '', (string) $input['classy_campaign_id'] ) : $d['classy_campaign_id'];
		$out['classy_campaign_id'] = $campaign;
		$email                     = isset( $input['form_recipient'] ) ? sanitize_email( $input['form_recipient'] ) : '';
		$out['form_recipient']     = is_email( $email ) ? $email : $d['form_recipient'];
		$out['form_rate_limit']    = isset( $input['form_rate_limit'] ) ? max( 1, min( 100, absint( $input['form_rate_limit'] ) ) ) : $d['form_rate_limit'];
		$seo                       = isset( $input['seo_output'] ) ? (string) $input['seo_output'] : 'auto';
		$out['seo_output']         = in_array( $seo, array( 'auto', 'on', 'off' ), true ) ? $seo : 'auto';
		foreach ( array( 'org_schema', 'security_headers', 'legacy_redirects' ) as $k ) {
			$out[ $k ] = empty( $input[ $k ] ) ? 0 : 1;
		}
		return $out;
	}

	/**
	 * Page dropdown helper.
	 *
	 * @param string $key   Setting key.
	 * @param int    $value Selected.
	 */
	private static function page_select( $key, $value ) {
		wp_dropdown_pages(
			array(
				'name'              => esc_attr( self::OPTION . '[' . $key . ']' ),
				'selected'          => (int) $value,
				'show_option_none'  => esc_html__( '— None —', 'corcovado-foundation-core' ),
				'option_none_value' => 0,
				'post_status'       => array( 'publish', 'private', 'draft' ),
			)
		);
	}

	/**
	 * Render settings page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Corcovado Foundation — Settings', 'corcovado-foundation-core' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'cf_core_settings_group' ); ?>
				<h2><?php esc_html_e( 'Languages', 'corcovado-foundation-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'English home page', 'corcovado-foundation-core' ); ?></th>
						<td><p><?php esc_html_e( 'The English home page is the WordPress front page (Settings → Reading).', 'corcovado-foundation-core' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Spanish home page (/es/)', 'corcovado-foundation-core' ); ?></th>
						<td><?php self::page_select( 'es_root', $s['es_root'] ); ?>
						<p class="description"><?php esc_html_e( 'Every page placed under this page (as a child page) is Spanish and is published under /es/.', 'corcovado-foundation-core' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="cf-site-es"><?php esc_html_e( 'Organization name in Spanish', 'corcovado-foundation-core' ); ?></label></th>
						<td><input id="cf-site-es" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[site_name_es]" value="<?php echo esc_attr( $s['site_name_es'] ); ?>">
						<p class="description"><?php esc_html_e( 'Used in titles and share tags of Spanish pages. The English name is the Site Title (Settings → General).', 'corcovado-foundation-core' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( '404 content (English)', 'corcovado-foundation-core' ); ?></th>
						<td><?php self::page_select( 'page_404_en', $s['page_404_en'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( '404 content (Spanish)', 'corcovado-foundation-core' ); ?></th>
						<td><?php self::page_select( 'page_404_es', $s['page_404_es'] ); ?>
						<p class="description"><?php esc_html_e( 'Keep these pages Private: their sections are shown on "page not found" errors.', 'corcovado-foundation-core' ); ?></p></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Donations (Classy)', 'corcovado-foundation-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cf-classy"><?php esc_html_e( 'Classy campaign ID', 'corcovado-foundation-core' ); ?></label></th>
						<td><input id="cf-classy" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[classy_campaign_id]" value="<?php echo esc_attr( $s['classy_campaign_id'] ); ?>" inputmode="numeric">
						<p class="description"><?php esc_html_e( 'Public campaign identifier used by the embedded donation form. Never paste API keys or secrets here.', 'corcovado-foundation-core' ); ?></p></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Forms', 'corcovado-foundation-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cf-recipient"><?php esc_html_e( 'Recipient email', 'corcovado-foundation-core' ); ?></label></th>
						<td><input id="cf-recipient" type="email" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[form_recipient]" value="<?php echo esc_attr( $s['form_recipient'] ); ?>">
						<p class="description"><?php esc_html_e( 'Contact and volunteer messages are emailed here. Messages are not stored in the database.', 'corcovado-foundation-core' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="cf-rate"><?php esc_html_e( 'Max. messages per visitor per hour', 'corcovado-foundation-core' ); ?></label></th>
						<td><input id="cf-rate" type="number" min="1" max="100" name="<?php echo esc_attr( self::OPTION ); ?>[form_rate_limit]" value="<?php echo esc_attr( $s['form_rate_limit'] ); ?>"></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'SEO', 'corcovado-foundation-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cf-seo"><?php esc_html_e( 'SEO tags output', 'corcovado-foundation-core' ); ?></label></th>
						<td><select id="cf-seo" name="<?php echo esc_attr( self::OPTION ); ?>[seo_output]">
							<option value="auto" <?php selected( $s['seo_output'], 'auto' ); ?>><?php esc_html_e( 'Automatic (off if an SEO plugin is active)', 'corcovado-foundation-core' ); ?></option>
							<option value="on" <?php selected( $s['seo_output'], 'on' ); ?>><?php esc_html_e( 'Always on', 'corcovado-foundation-core' ); ?></option>
							<option value="off" <?php selected( $s['seo_output'], 'off' ); ?>><?php esc_html_e( 'Off', 'corcovado-foundation-core' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Titles, meta description, Open Graph and Twitter Cards. hreflang/x-default are always printed (they depend on the translation links). Canonical URLs and the sitemap are provided by WordPress itself.', 'corcovado-foundation-core' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Organization structured data', 'corcovado-foundation-core' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[org_schema]" value="1" <?php checked( $s['org_schema'] ); ?>> <?php esc_html_e( 'Output NGO JSON-LD on the home pages', 'corcovado-foundation-core' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Default share image', 'corcovado-foundation-core' ); ?></th>
						<td><input type="number" min="0" name="<?php echo esc_attr( self::OPTION ); ?>[default_og_image]" value="<?php echo esc_attr( $s['default_og_image'] ); ?>">
						<p class="description"><?php esc_html_e( 'Media Library attachment ID used for og:image / twitter:image when a page has no hero image.', 'corcovado-foundation-core' ); ?></p></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Advanced', 'corcovado-foundation-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Legacy .html redirects', 'corcovado-foundation-core' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[legacy_redirects]" value="1" <?php checked( $s['legacy_redirects'] ); ?>> <?php esc_html_e( 'Redirect old static URLs (/about-us.html, /library/*.pdf…) to their WordPress equivalent with a single 301.', 'corcovado-foundation-core' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Security headers', 'corcovado-foundation-core' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[security_headers]" value="1" <?php checked( $s['security_headers'] ); ?>> <?php esc_html_e( 'Send X-Content-Type-Options, Referrer-Policy, Permissions-Policy and X-Frame-Options (same values as the static site).', 'corcovado-foundation-core' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
