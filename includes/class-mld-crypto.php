<?php
/**
 * Encrypt sensitive option values at rest.
 *
 * @package MaximumLocalDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MLD_Crypto
 *
 * AES-256-CBC via OpenSSL. Key derived from WordPress salts.
 * Values without the mld1: prefix are treated as legacy plaintext.
 */
class MLD_Crypto {

	const PREFIX = 'mld1:';

	/**
	 * Whether OpenSSL encryption is available.
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'openssl_encrypt' )
			&& function_exists( 'openssl_decrypt' )
			&& function_exists( 'random_bytes' );
	}

	/**
	 * Derive a 32-byte key from WordPress salts.
	 *
	 * @return string
	 */
	private static function key() {
		$material = wp_salt( 'auth' ) . '|maximum-local-dashboard|sa-v1';
		return hash( 'sha256', $material, true );
	}

	/**
	 * Encrypt a UTF-8 string. Falls back to plaintext if OpenSSL is unavailable.
	 *
	 * @param string $plaintext Plaintext.
	 * @return string
	 */
	public static function encrypt( $plaintext ) {
		$plaintext = (string) $plaintext;
		if ( '' === $plaintext || ! self::available() ) {
			return $plaintext;
		}

		$iv = random_bytes( 16 );
		$ciphertext = openssl_encrypt( $plaintext, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv );
		if ( false === $ciphertext ) {
			return $plaintext;
		}

		return self::PREFIX . base64_encode( $iv . $ciphertext );
	}

	/**
	 * Decrypt a stored value. Legacy plaintext (no prefix) is returned as-is.
	 *
	 * @param string $stored Stored option value.
	 * @return string|WP_Error
	 */
	public static function decrypt( $stored ) {
		$stored = (string) $stored;
		if ( '' === $stored ) {
			return '';
		}

		if ( 0 !== strpos( $stored, self::PREFIX ) ) {
			return $stored;
		}

		if ( ! self::available() ) {
			return new WP_Error(
				'mld_crypto',
				__( 'OpenSSL is required to read the encrypted service account key.', 'maximum-local-dashboard' )
			);
		}

		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true );
		if ( false === $raw || strlen( $raw ) < 17 ) {
			return new WP_Error(
				'mld_crypto',
				__( 'Stored service account data could not be decoded.', 'maximum-local-dashboard' )
			);
		}

		$iv         = substr( $raw, 0, 16 );
		$ciphertext = substr( $raw, 16 );
		$plain      = openssl_decrypt( $ciphertext, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv );
		if ( false === $plain ) {
			return new WP_Error(
				'mld_crypto',
				__( 'Stored service account data could not be decrypted. Re-paste the JSON under Settings.', 'maximum-local-dashboard' )
			);
		}

		return $plain;
	}

	/**
	 * Whether a stored value uses the encrypted prefix.
	 *
	 * @param string $stored Stored value.
	 * @return bool
	 */
	public static function is_encrypted( $stored ) {
		return is_string( $stored ) && 0 === strpos( $stored, self::PREFIX );
	}
}
