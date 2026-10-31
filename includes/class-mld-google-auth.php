<?php
/**
 * Google service-account JWT auth (RS256 via OpenSSL, no Composer).
 *
 * @package MaximumLocalDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MLD_Google_Auth
 */
class MLD_Google_Auth {

	const TOKEN_TRANSIENT = 'mld_google_access_token';

	/**
	 * Return a bearer access token for the given scopes, or WP_Error.
	 *
	 * @param array $scopes OAuth scopes.
	 * @return string|WP_Error
	 */
	public static function get_access_token( array $scopes ) {
		$sa = MLD_Settings::get_service_account();
		if ( is_wp_error( $sa ) ) {
			return $sa;
		}

		$cache_key = self::TOKEN_TRANSIENT . '_' . md5( implode( ' ', $scopes ) );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$now  = time();
		$claim = array(
			'iss'   => $sa['client_email'],
			'scope' => implode( ' ', $scopes ),
			'aud'   => 'https://oauth2.googleapis.com/token',
			'iat'   => $now,
			'exp'   => $now + 3600,
		);

		$jwt = self::sign_jwt( $claim, $sa['private_key'] );
		if ( is_wp_error( $jwt ) ) {
			return $jwt;
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 20,
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code || empty( $body['access_token'] ) ) {
			$msg = isset( $body['error_description'] ) ? $body['error_description'] : __( 'Token exchange failed.', 'maximum-local-dashboard' );
			return new WP_Error( 'mld_token_error', $msg, array( 'status' => $code ) );
		}

		$ttl = isset( $body['expires_in'] ) ? max( 60, (int) $body['expires_in'] - 60 ) : 3300;
		set_transient( $cache_key, $body['access_token'], $ttl );

		return $body['access_token'];
	}

	/**
	 * Build and sign an RS256 JWT.
	 *
	 * @param array  $payload Claims.
	 * @param string $private_key PEM private key.
	 * @return string|WP_Error
	 */
	private static function sign_jwt( array $payload, $private_key ) {
		$header = array(
			'alg' => 'RS256',
			'typ' => 'JWT',
		);

		$segments = array(
			self::base64url_encode( wp_json_encode( $header ) ),
			self::base64url_encode( wp_json_encode( $payload ) ),
		);
		$signing_input = implode( '.', $segments );

		$pkey = openssl_pkey_get_private( $private_key );
		if ( false === $pkey ) {
			return new WP_Error( 'mld_openssl', __( 'Invalid service account private key.', 'maximum-local-dashboard' ) );
		}

		$signature = '';
		$ok        = openssl_sign( $signing_input, $signature, $pkey, OPENSSL_ALGO_SHA256 );
		if ( ! $ok ) {
			return new WP_Error( 'mld_openssl', __( 'Failed to sign JWT.', 'maximum-local-dashboard' ) );
		}

		$segments[] = self::base64url_encode( $signature );
		return implode( '.', $segments );
	}

	/**
	 * Base64url encode without padding.
	 *
	 * @param string $data Raw bytes.
	 * @return string
	 */
	private static function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}
