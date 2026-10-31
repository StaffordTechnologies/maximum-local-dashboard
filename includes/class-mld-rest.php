<?php
/**
 * Admin AJAX / REST data endpoints.
 *
 * @package MaximumLocalDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MLD_Rest
 */
class MLD_Rest {

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'wp_ajax_mld_dashboard_data', array( __CLASS__, 'dashboard_data' ) );
		add_action( 'wp_ajax_mld_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'wp_ajax_mld_save_service_account', array( __CLASS__, 'save_service_account' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * REST routes (capability-checked).
	 */
	public static function register_routes() {
		register_rest_route(
			'maximum-local-dashboard/v1',
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_status' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);
	}

	/**
	 * Capability check.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Connection status for Settings tab (never includes secrets).
	 *
	 * @return WP_REST_Response
	 */
	public static function rest_status() {
		return rest_ensure_response(
			array(
				'service_account' => MLD_Settings::service_account_status(),
				'settings'        => MLD_Settings::get_all(),
				'facebook'        => array(
					'connected' => false,
					'label'     => __( 'Not connected', 'maximum-local-dashboard' ),
				),
				'clarity'         => array(
					'connected' => false,
					'label'     => __( 'Not connected', 'maximum-local-dashboard' ),
				),
			)
		);
	}

	/**
	 * Verify AJAX nonce + capability.
	 *
	 * @return void Dies on failure.
	 */
	private static function guard_ajax() {
		if ( ! self::can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'maximum-local-dashboard' ) ), 403 );
		}
		check_ajax_referer( 'mld_dashboard', 'nonce' );
	}

	/**
	 * Parse and validate a date string.
	 *
	 * @param string $value    Raw.
	 * @param string $fallback Fallback YYYY-MM-DD.
	 * @return string
	 */
	private static function date_param( $value, $fallback ) {
		$value = sanitize_text_field( (string) $value );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return $fallback;
		}
		return $value;
	}

	/**
	 * Dashboard data dispatcher.
	 */
	public static function dashboard_data() {
		self::guard_ajax();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in guard_ajax() above.
		$tab      = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'overview';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in guard_ajax() above.
		$campaign = isset( $_POST['campaign'] ) ? sanitize_text_field( wp_unslash( $_POST['campaign'] ) ) : '';
		$end      = self::date_param( isset( $_POST['end'] ) ? wp_unslash( $_POST['end'] ) : '', gmdate( 'Y-m-d' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in guard_ajax(); sanitized in date_param.
		$days     = (int) MLD_Settings::get_all()['date_range_days'];
		$start    = self::date_param(
			isset( $_POST['start'] ) ? wp_unslash( $_POST['start'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in guard_ajax(); sanitized in date_param.
			gmdate( 'Y-m-d', strtotime( $end ) - ( $days * DAY_IN_SECONDS ) )
		);

		if ( ! MLD_Settings::has_service_account() && in_array( $tab, array( 'overview', 'campaigns', 'conversions', 'traffic', 'search' ), true ) ) {
			wp_send_json_success(
				array(
					'configured' => false,
					'message'    => __( 'Not configured. Paste a Google service account JSON under Settings.', 'maximum-local-dashboard' ),
					'tab'        => $tab,
				)
			);
		}

		$result = null;
		switch ( $tab ) {
			case 'overview':
				$result = MLD_GA4::overview( $start, $end, $campaign );
				break;
			case 'campaigns':
				$result = MLD_GA4::campaigns( $start, $end );
				break;
			case 'conversions':
				$result = MLD_GA4::conversions( $start, $end, $campaign );
				break;
			case 'traffic':
				$result = MLD_GA4::traffic( $start, $end, $campaign );
				break;
			case 'search':
				$result = MLD_Search_Console::queries( $start, $end );
				break;
			case 'facebook':
			case 'clarity':
				wp_send_json_success(
					array(
						'configured' => false,
						'connected'  => false,
						'message'    => __( 'Not connected', 'maximum-local-dashboard' ),
						'tab'        => $tab,
					)
				);
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Unknown tab', 'maximum-local-dashboard' ) ), 400 );
		}

		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			$http = ( 'mld_not_configured' === $code ) ? 200 : 500;
			if ( 200 === $http ) {
				wp_send_json_success(
					array(
						'configured' => false,
						'message'    => $result->get_error_message(),
						'tab'        => $tab,
					)
				);
			}
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $code,
				),
				$http
			);
		}

		wp_send_json_success(
			array(
				'configured' => true,
				'tab'        => $tab,
				'start'      => $start,
				'end'        => $end,
				'campaign'   => $campaign,
				'data'       => $result,
			)
		);
	}

	/**
	 * Save non-secret settings.
	 */
	public static function save_settings() {
		self::guard_ajax();

		$raw = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in guard_ajax(); sanitized via MLD_Settings::sanitize_settings.
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$clean = MLD_Settings::sanitize_settings( $raw );
		update_option( MLD_OPTION_SETTINGS, $clean, false );
		MLD_Settings::clear_token_cache();

		wp_send_json_success(
			array(
				'settings' => $clean,
				'message'  => __( 'Settings saved.', 'maximum-local-dashboard' ),
			)
		);
	}

	/**
	 * Save service account JSON (server-side only).
	 */
	public static function save_service_account() {
		self::guard_ajax();

		$json = isset( $_POST['service_account_json'] ) ? wp_unslash( $_POST['service_account_json'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in guard_ajax(); validated in save_service_account_json.
		$json = is_string( $json ) ? $json : '';

		$result = MLD_Settings::save_service_account_json( $json );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'message'         => '' === trim( $json ) ? __( 'Service account cleared.', 'maximum-local-dashboard' ) : __( 'Service account saved.', 'maximum-local-dashboard' ),
				'service_account' => MLD_Settings::service_account_status(),
			)
		);
	}
}
