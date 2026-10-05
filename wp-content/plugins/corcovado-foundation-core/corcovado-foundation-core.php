<?php
/**
 * Plugin Name:       Corcovado Foundation Core
 * Plugin URI:        https://corcovadofoundation.org/
 * Description:       Structured content (News, Events, Team, Resources, Partners), English/Spanish relations, editable page sections, SEO output, secure forms, Classy settings and the content migration tool for the Corcovado Foundation website.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Corcovado Foundation
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       corcovado-foundation-core
 * Domain Path:       /languages
 *
 * @package CorcovadoFoundationCore
 */

defined( 'ABSPATH' ) || exit;

define( 'CF_CORE_VERSION', '1.0.0' );
define( 'CF_CORE_FILE', __FILE__ );
define( 'CF_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CF_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once CF_CORE_DIR . 'includes/functions.php';
require_once CF_CORE_DIR . 'includes/class-cf-settings.php';
require_once CF_CORE_DIR . 'includes/class-cf-languages.php';
require_once CF_CORE_DIR . 'includes/class-cf-post-types.php';
require_once CF_CORE_DIR . 'includes/class-cf-layout.php';
require_once CF_CORE_DIR . 'includes/class-cf-seo.php';
require_once CF_CORE_DIR . 'includes/class-cf-forms.php';
require_once CF_CORE_DIR . 'includes/class-cf-redirects.php';
require_once CF_CORE_DIR . 'includes/class-cf-security.php';

CF_Settings::init();
CF_Languages::init();
CF_Post_Types::init();
CF_Layout::init();
CF_SEO::init();
CF_Forms::init();
CF_Redirects::init();
CF_Security::init();

if ( is_admin() ) {
	require_once CF_CORE_DIR . 'admin/class-cf-admin-layout.php';
	require_once CF_CORE_DIR . 'admin/class-cf-admin-content.php';
	require_once CF_CORE_DIR . 'migration/class-cf-importer.php';
	require_once CF_CORE_DIR . 'migration/class-cf-migration-page.php';
	CF_Admin_Layout::init();
	CF_Admin_Content::init();
	CF_Migration_Page::init();
}

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'corcovado-foundation-core', false, dirname( plugin_basename( CF_CORE_FILE ) ) . '/languages' );
	},
	1
);

register_activation_hook(
	__FILE__,
	static function () {
		CF_Post_Types::register();
		CF_Languages::add_rewrite_rules();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		flush_rewrite_rules();
	}
);
