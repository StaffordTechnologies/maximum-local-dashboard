<?php
/**
 * Plugin settings storage and helpers.
 *
 * @package MaximumLocalDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MLD_Settings
 */
class MLD_Settings {

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * On activate: re-encrypt a legacy plaintext service account if present.
	 */
	public static function activate() {
		$raw = get_option( MLD_OPTION_SA_JSON, '' );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return;
		}
		if ( MLD_Crypto::is_encrypted( $raw ) ) {
			return;
		}
		// Looks like legacy plaintext JSON — encrypt in place.
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return;
		}
		update_option( MLD_OPTION_SA_JSON, MLD_Crypto::encrypt( $raw ), false );
	}

	/**
	 * Register settings.
	 */
	public static function register() {
		register_setting(
			'mld_settings_group',
			MLD_OPTION_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Default non-secret settings (no client-specific values).
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'ga4_property_id'     => '',
			'ga4_measurement_id'  => '',
			'search_console_site' => '',
			'production_host'     => '',
			'date_range_days'     => 28,
			'cta_selectors'       => self::default_cta_selectors(),
			'form_selectors'      => self::default_form_selectors(),
			'download_ext'        => self::default_download_ext(),
		);
	}

	/**
	 * Safe default CTA selectors (no client-specific hosts or paths).
	 *
	 * @return array
	 */
	public static function default_cta_selectors() {
		return array(
			'.mld-cta',
			'.ml-cta',
			'[data-mld-cta]',
			'[data-ml-cta]',
			'a.cta-button',
		);
	}

	/**
	 * Safe default form selectors (no bare "form").
	 *
	 * @return array
	 */
	public static function default_form_selectors() {
		return array(
			'form.wpcf7-form',
			'.gform_wrapper form',
			'form.wpforms-form',
			'form[data-mld-track]',
			'form[data-ml-track]',
		);
	}

	/**
	 * Default download extensions.
	 *
	 * @return array
	 */
	public static function default_download_ext() {
		return array( 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'csv' );
	}

	/**
	 * Sanitize a newline/comma-separated selector list into a clean array.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	private static function sanitize_selector_list( $input ) {
		if ( is_array( $input ) ) {
			$parts = $input;
		} else {
			$parts = preg_split( '/[\r\n,]+/', (string) $input );
		}
		$out = array();
		foreach ( $parts as $part ) {
			$part = trim( (string) $part );
			if ( '' === $part ) {
				continue;
			}
			// Allow common CSS selector characters only.
			$part = preg_replace( '/[^a-zA-Z0-9_\-\.\#\[\]=\"\'\*\^\|\~\s:>+~]/', '', $part );
			$part = trim( $part );
			if ( '' !== $part && strlen( $part ) <= 200 ) {
				$out[] = $part;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Sanitize download extension list.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	private static function sanitize_ext_list( $input ) {
		if ( is_array( $input ) ) {
			$parts = $input;
		} else {
			$parts = preg_split( '/[\r\n,\s]+/', (string) $input );
		}
		$out = array();
		foreach ( $parts as $part ) {
			$part = strtolower( preg_replace( '/[^a-z0-9]/', '', (string) $part ) );
			if ( '' !== $part && strlen( $part ) <= 10 ) {
				$out[] = $part;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Sanitize settings array (never stores the service account JSON here).
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$out   = self::get_all();
		$input = is_array( $input ) ? $input : array();

		if ( isset( $input['ga4_property_id'] ) ) {
			$out['ga4_property_id'] = preg_replace( '/[^0-9]/', '', (string) $input['ga4_property_id'] );
		}
		if ( isset( $input['ga4_measurement_id'] ) ) {
			$out['ga4_measurement_id'] = strtoupper( preg_replace( '/[^A-Za-z0-9\-]/', '', (string) $input['ga4_measurement_id'] ) );
		}
		if ( isset( $input['search_console_site'] ) ) {
			$out['search_console_site'] = esc_url_raw( (string) $input['search_console_site'] );
		}
		if ( isset( $input['production_host'] ) ) {
			$out['production_host'] = strtolower( preg_replace( '/[^a-z0-9\.\-]/', '', (string) $input['production_host'] ) );
		}
		if ( isset( $input['date_range_days'] ) ) {
			$out['date_range_days'] = max( 1, min( 90, (int) $input['date_range_days'] ) );
		}
		if ( isset( $input['cta_selectors'] ) ) {
			$out['cta_selectors'] = self::sanitize_selector_list( $input['cta_selectors'] );
		}
		if ( isset( $input['form_selectors'] ) ) {
			$out['form_selectors'] = self::sanitize_selector_list( $input['form_selectors'] );
		}
		if ( isset( $input['download_ext'] ) ) {
			$out['download_ext'] = self::sanitize_ext_list( $input['download_ext'] );
		}

		return $out;
	}

	/**
	 * All non-secret settings.
	 *
	 * @return array
	 */
	public static function get_all() {
		$stored = get_option( MLD_OPTION_SETTINGS, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$merged = array_merge( self::defaults(), $stored );

		// Ensure list fields are arrays even if older installs stored strings.
		$merged['cta_selectors']  = self::sanitize_selector_list( $merged['cta_selectors'] );
		$merged['form_selectors'] = self::sanitize_selector_list( $merged['form_selectors'] );
		$merged['download_ext']   = self::sanitize_ext_list( $merged['download_ext'] );

		return $merged;
	}

	/**
	 * GA4 property ID.
	 *
	 * @return string
	 */
	public static function get_ga4_property_id() {
		$s = self::get_all();
		return isset( $s['ga4_property_id'] ) ? (string) $s['ga4_property_id'] : '';
	}

	/**
	 * Measurement ID for front-end tracking.
	 *
	 * @return string
	 */
	public static function get_measurement_id() {
		$s = self::get_all();
		return isset( $s['ga4_measurement_id'] ) ? (string) $s['ga4_measurement_id'] : '';
	}

	/**
	 * Search Console site URL.
	 *
	 * @return string
	 */
	public static function get_search_console_site() {
		$s = self::get_all();
		return isset( $s['search_console_site'] ) ? (string) $s['search_console_site'] : '';
	}

	/**
	 * Production hostname used to decide debug_mode.
	 *
	 * @return string
	 */
	public static function get_production_host() {
		$s = self::get_all();
		return isset( $s['production_host'] ) ? (string) $s['production_host'] : '';
	}

	/**
	 * Whether a service account JSON is stored.
	 *
	 * @return bool
	 */
	public static function has_service_account() {
		$raw = get_option( MLD_OPTION_SA_JSON, '' );
		return is_string( $raw ) && '' !== $raw;
	}

	/**
	 * Masked summary for the Settings UI (never the key itself).
	 *
	 * @return array
	 */
	public static function service_account_status() {
		if ( ! self::has_service_account() ) {
			return array(
				'configured' => false,
				'email'      => '',
				'project'    => '',
			);
		}
		$sa = self::get_service_account();
		if ( is_wp_error( $sa ) ) {
			return array(
				'configured' => false,
				'email'      => '',
				'project'    => '',
				'error'      => $sa->get_error_message(),
			);
		}
		return array(
			'configured' => true,
			'email'      => isset( $sa['client_email'] ) ? self::mask_email( $sa['client_email'] ) : '',
			'project'    => isset( $sa['project_id'] ) ? (string) $sa['project_id'] : '',
		);
	}

	/**
	 * Decoded service account array, or WP_Error.
	 *
	 * @return array|WP_Error
	 */
	public static function get_service_account() {
		$raw = get_option( MLD_OPTION_SA_JSON, '' );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return new WP_Error( 'mld_not_configured', __( 'Service account JSON is not configured. Paste it under Settings.', 'maximum-local-dashboard' ) );
		}

		$plain = MLD_Crypto::decrypt( $raw );
		if ( is_wp_error( $plain ) ) {
			return $plain;
		}

		$data = json_decode( $plain, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'mld_sa_invalid', __( 'Stored service account JSON is invalid.', 'maximum-local-dashboard' ) );
		}
		if ( empty( $data['client_email'] ) || empty( $data['private_key'] ) ) {
			return new WP_Error( 'mld_sa_invalid', __( 'Service account JSON is missing client_email or private_key.', 'maximum-local-dashboard' ) );
		}

		return $data;
	}

	/**
	 * Persist service account JSON after validation. Encrypted at rest. Never exposed to REST/JS.
	 *
	 * @param string $json Raw JSON string.
	 * @return true|WP_Error
	 */
	public static function save_service_account_json( $json ) {
		$json = trim( (string) $json );
		if ( '' === $json ) {
			delete_option( MLD_OPTION_SA_JSON );
			self::clear_token_cache();
			return true;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'mld_sa_invalid', __( 'That does not look like valid JSON.', 'maximum-local-dashboard' ) );
		}
		if ( empty( $data['type'] ) || 'service_account' !== $data['type'] ) {
			return new WP_Error( 'mld_sa_invalid', __( 'JSON type must be "service_account".', 'maximum-local-dashboard' ) );
		}
		if ( empty( $data['client_email'] ) || empty( $data['private_key'] ) ) {
			return new WP_Error( 'mld_sa_invalid', __( 'JSON must include client_email and private_key.', 'maximum-local-dashboard' ) );
		}

		// Normalize escaped newlines in private_key.
		$data['private_key'] = str_replace( '\\n', "\n", $data['private_key'] );

		$pkey = openssl_pkey_get_private( $data['private_key'] );
		if ( false === $pkey ) {
			return new WP_Error( 'mld_sa_invalid', __( 'private_key could not be parsed by OpenSSL.', 'maximum-local-dashboard' ) );
		}

		$normalized = wp_json_encode( $data );
		if ( false === $normalized ) {
			return new WP_Error( 'mld_sa_invalid', __( 'Could not encode service account JSON.', 'maximum-local-dashboard' ) );
		}

		update_option( MLD_OPTION_SA_JSON, MLD_Crypto::encrypt( $normalized ), false );
		self::clear_token_cache();
		return true;
	}

	/**
	 * Clear cached Google tokens and report caches.
	 */
	public static function clear_token_cache() {
		global $wpdb;

		$like_patterns = array(
			$wpdb->esc_like( '_transient_ml_google_access_token' ) . '%',
			$wpdb->esc_like( '_transient_timeout_ml_google_access_token' ) . '%',
			$wpdb->esc_like( '_transient_ml_ga4_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_ml_ga4_' ) . '%',
			$wpdb->esc_like( '_transient_ml_gsc_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_ml_gsc_' ) . '%',
			$wpdb->esc_like( '_transient_mld_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_mld_' ) . '%',
		);

		foreach ( $like_patterns as $like ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- clearing plugin-owned transients by prefix.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$like
				)
			);
		}
	}

	/**
	 * Mask an email for display.
	 *
	 * @param string $email Email.
	 * @return string
	 */
	private static function mask_email( $email ) {
		$parts = explode( '@', $email, 2 );
		if ( 2 !== count( $parts ) ) {
			return '***';
		}
		$local = $parts[0];
		$show  = substr( $local, 0, min( 3, strlen( $local ) ) );
		return $show . '***@' . $parts[1];
	}
}
