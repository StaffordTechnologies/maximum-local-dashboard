<?php
/**
 * Plugin Name:       Maximum Local Dashboard
 * Plugin URI:        https://staffordtechnologies.net/maximum-local-dashboard/
 * Description:       Plain-English campaign dashboard for WordPress. Tracks conversions to GA4 and surfaces campaign results for administrators.
 * Version:           1.1.0
 * Author:            Stafford Technologies
 * Author URI:        https://staffordtechnologies.net/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       maximum-local-dashboard
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package MaximumLocalDashboard
 */

defined( 'ABSPATH' ) || exit;

define( 'MLD_VERSION', '1.1.0' );
define( 'MLD_PLUGIN_FILE', __FILE__ );
define( 'MLD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MLD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MLD_SUPPORT_EMAIL', 'helpdesk@staffordtechnologies.net' );

/**
 * Option keys kept from v1.0.0 (maximum-local) for upgrade compatibility.
 */
define( 'MLD_OPTION_SETTINGS', 'ml_settings' );
define( 'MLD_OPTION_SA_JSON', 'ml_service_account_json' );

require_once MLD_PLUGIN_DIR . 'includes/class-mld-crypto.php';
require_once MLD_PLUGIN_DIR . 'includes/class-mld-google-auth.php';
require_once MLD_PLUGIN_DIR . 'includes/class-mld-ga4.php';
require_once MLD_PLUGIN_DIR . 'includes/class-mld-search-console.php';
require_once MLD_PLUGIN_DIR . 'includes/class-mld-settings.php';
require_once MLD_PLUGIN_DIR . 'includes/class-mld-rest.php';
require_once MLD_PLUGIN_DIR . 'includes/class-mld-admin.php';
require_once MLD_PLUGIN_DIR . 'includes/class-mld-tracking.php';
require_once MLD_PLUGIN_DIR . 'includes/class-mld-plugin.php';

/**
 * Bootstrap.
 *
 * @return MLD_Plugin
 */
function mld() {
	return MLD_Plugin::instance();
}

mld();

register_activation_hook( __FILE__, array( 'MLD_Settings', 'activate' ) );

/**
 * Add Docs and Support links under this plugin on the Plugins screen.
 *
 * @param array  $links       Existing meta links.
 * @param string $plugin_file Plugin basename.
 * @return array
 */
function mld_plugin_row_meta( $links, $plugin_file ) {
	if ( plugin_basename( MLD_PLUGIN_FILE ) !== $plugin_file ) {
		return $links;
	}

	$page_url = 'https://staffordtechnologies.net/maximum-local-dashboard/';

	$links[] = sprintf(
		'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
		esc_url( $page_url ),
		esc_html__( 'Docs', 'maximum-local-dashboard' )
	);
	$links[] = sprintf(
		'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
		esc_url( $page_url ),
		esc_html__( 'Support', 'maximum-local-dashboard' )
	);

	return $links;
}
add_filter( 'plugin_row_meta', 'mld_plugin_row_meta', 10, 2 );
