<?php
/**
 * Google Search Console API client.
 *
 * @package MaximumLocalDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MLD_Search_Console
 */
class MLD_Search_Console {

	const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

	/**
	 * Query Search Console searchAnalytics.
	 *
	 * @param string $start YYYY-MM-DD.
	 * @param string $end   YYYY-MM-DD.
	 * @return array|WP_Error
	 */
	public static function queries( $start, $end ) {
		$site = MLD_Settings::get_search_console_site();
		if ( '' === $site ) {
			return new WP_Error( 'mld_not_configured', __( 'Search Console site URL is not set.', 'maximum-local-dashboard' ) );
		}

		$token = MLD_Google_Auth::get_access_token( array( self::SCOPE ) );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$url = sprintf(
			'https://www.googleapis.com/webmasters/v3/sites/%s/searchAnalytics/query',
			rawurlencode( $site )
		);

		$body = array(
			'startDate'  => $start,
			'endDate'    => $end,
			'dimensions' => array( 'query' ),
			'rowLimit'   => 100,
		);

		$cache_key = 'mld_gsc_' . md5( $url . wp_json_encode( $body ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Search Console request failed.', 'maximum-local-dashboard' );
			return new WP_Error( 'mld_gsc_error', $msg, array( 'status' => $code ) );
		}

		set_transient( $cache_key, $data, 15 * MINUTE_IN_SECONDS );
		return $data;
	}
}
