<?php
/**
 * Admin menu and dashboard UI.
 *
 * @package MaximumLocalDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MLD_Admin
 */
class MLD_Admin {

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Sidebar menu (administrators only).
	 */
	public static function menu() {
		add_menu_page(
			__( 'Campaign Dashboard', 'maximum-local-dashboard' ),
			__( 'Campaign Dashboard', 'maximum-local-dashboard' ),
			'manage_options',
			'maximum-local-dashboard',
			array( __CLASS__, 'render' ),
			'dashicons-chart-area',
			58
		);
	}

	/**
	 * Enqueue admin assets on our page only.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function assets( $hook ) {
		if ( 'toplevel_page_maximum-local-dashboard' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'mld-admin',
			MLD_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			MLD_VERSION
		);

		wp_enqueue_script(
			'mld-admin',
			MLD_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			MLD_VERSION,
			true
		);

		wp_localize_script(
			'mld-admin',
			'mldDashboard',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'mld_dashboard' ),
				'settings' => MLD_Settings::get_all(),
				'sa'       => MLD_Settings::service_account_status(),
				'i18n'     => array(
					'notConfigured' => __( 'Not configured. Paste a Google service account JSON under Settings.', 'maximum-local-dashboard' ),
					'notConnected'  => __( 'Not connected', 'maximum-local-dashboard' ),
					'loading'       => __( 'Loading…', 'maximum-local-dashboard' ),
					'error'         => __( 'Something went wrong loading this tab.', 'maximum-local-dashboard' ),
					'saved'         => __( 'Saved.', 'maximum-local-dashboard' ),
				),
			)
		);
	}

	/**
	 * Render the dashboard shell.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'maximum-local-dashboard' ) );
		}

		$tabs = array(
			'overview'    => __( 'Overview', 'maximum-local-dashboard' ),
			'campaigns'   => __( 'Campaigns', 'maximum-local-dashboard' ),
			'conversions' => __( 'Conversions', 'maximum-local-dashboard' ),
			'traffic'     => __( 'Traffic', 'maximum-local-dashboard' ),
			'search'      => __( 'Search', 'maximum-local-dashboard' ),
			'facebook'    => __( 'Facebook', 'maximum-local-dashboard' ),
			'clarity'     => __( 'Clarity', 'maximum-local-dashboard' ),
			'settings'    => __( 'Settings', 'maximum-local-dashboard' ),
		);
		?>
		<div class="wrap ml-wrap">
			<header class="ml-header">
				<div class="ml-brand">
					<span class="ml-mark" aria-hidden="true">ML</span>
					<div>
						<h1><?php esc_html_e( 'Campaign Dashboard', 'maximum-local-dashboard' ); ?></h1>
						<p class="ml-sub"><?php esc_html_e( 'Powered by Maximum Local', 'maximum-local-dashboard' ); ?></p>
					</div>
				</div>
				<div class="ml-filters">
					<label class="screen-reader-text" for="ml-campaign-filter"><?php esc_html_e( 'Campaign', 'maximum-local-dashboard' ); ?></label>
					<select id="ml-campaign-filter">
						<option value="all"><?php esc_html_e( 'All campaigns', 'maximum-local-dashboard' ); ?></option>
					</select>
					<label class="screen-reader-text" for="ml-start"><?php esc_html_e( 'Start date', 'maximum-local-dashboard' ); ?></label>
					<input type="date" id="ml-start" />
					<label class="screen-reader-text" for="ml-end"><?php esc_html_e( 'End date', 'maximum-local-dashboard' ); ?></label>
					<input type="date" id="ml-end" />
					<button type="button" class="button" id="ml-refresh"><?php esc_html_e( 'Refresh', 'maximum-local-dashboard' ); ?></button>
				</div>
			</header>

			<nav class="ml-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Dashboard tabs', 'maximum-local-dashboard' ); ?>">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<button
						type="button"
						class="ml-tab<?php echo 'overview' === $slug ? ' is-active' : ''; ?>"
						role="tab"
						aria-selected="<?php echo 'overview' === $slug ? 'true' : 'false'; ?>"
						data-tab="<?php echo esc_attr( $slug ); ?>"
					><?php echo esc_html( $label ); ?></button>
				<?php endforeach; ?>
			</nav>

			<div id="ml-panel" class="ml-panel" role="tabpanel">
				<p class="ml-muted"><?php esc_html_e( 'Loading…', 'maximum-local-dashboard' ); ?></p>
			</div>
		</div>
		<?php
	}
}
