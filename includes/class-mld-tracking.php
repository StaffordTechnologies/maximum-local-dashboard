<?php
/**
 * Front-end conversion tracking (gtag events).
 *
 * @package MaximumLocalDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MLD_Tracking
 */
class MLD_Tracking {

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Whether the current host matches the configured production host.
	 *
	 * @return bool
	 */
	public static function is_production() {
		$prod = strtolower( MLD_Settings::get_production_host() );
		if ( '' === $prod ) {
			return false;
		}
		$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
		$host = preg_replace( '/:\d+$/', '', $host );
		return $prod === $host || ( 'www.' . $prod ) === $host;
	}

	/**
	 * Whether tracking should fire on this request.
	 * Non-production (or empty production host): only when ?ml_debug=1 / ?mld_debug=1.
	 *
	 * @return bool
	 */
	public static function should_track() {
		if ( self::is_production() ) {
			return true;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public debug flag, not a form submission.
		$debug = isset( $_GET['mld_debug'] ) ? sanitize_text_field( wp_unslash( $_GET['mld_debug'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- legacy flag from v1.0.0.
		if ( '' === $debug && isset( $_GET['ml_debug'] ) ) {
			$debug = sanitize_text_field( wp_unslash( $_GET['ml_debug'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		return '1' === $debug;
	}

	/**
	 * Enqueue tracking script on the front end.
	 */
	public static function enqueue() {
		if ( is_admin() ) {
			return;
		}

		$measurement = MLD_Settings::get_measurement_id();
		if ( '' === $measurement ) {
			return;
		}

		$should = self::should_track();
		$debug  = ! self::is_production();
		$s      = MLD_Settings::get_all();

		// Load gtag.js when a measurement ID is configured (admin may also load it separately).
		wp_enqueue_script(
			'mld-gtag',
			'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $measurement ),
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google-hosted gtag.js versioned by query string.
			true
		);
		wp_add_inline_script(
			'mld-gtag',
			'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",' . wp_json_encode( $measurement ) . ');',
			'before'
		);

		wp_enqueue_script(
			'mld-tracking',
			MLD_PLUGIN_URL . 'assets/js/tracking.js',
			array( 'mld-gtag' ),
			MLD_VERSION,
			true
		);

		wp_localize_script(
			'mld-tracking',
			'mldTracking',
			array(
				'measurementId' => $measurement,
				'debugMode'     => (bool) $debug,
				'enabled'       => (bool) $should,
				'isProduction'  => self::is_production(),
				'cookieDays'    => 30,
				'ctaSelectors'  => isset( $s['cta_selectors'] ) ? array_values( $s['cta_selectors'] ) : MLD_Settings::default_cta_selectors(),
				'formSelectors' => isset( $s['form_selectors'] ) ? array_values( $s['form_selectors'] ) : MLD_Settings::default_form_selectors(),
				'downloadExt'   => isset( $s['download_ext'] ) ? array_values( $s['download_ext'] ) : MLD_Settings::default_download_ext(),
			)
		);
	}
}
