<?php
/**
 * Configuration Manager
 *
 * Handles secure storage and retrieval of API credentials and product mappings.
 */

class Boxi_Config_Manager {

	/**
	 * Option name for API URL
	 */
	const OPTION_API_URL = 'boxi_panelalpha_api_url';

	/**
	 * Option name for encrypted API token
	 */
	const OPTION_API_TOKEN = 'boxi_panelalpha_api_token_encrypted';

	/**
	 * Option name for product mappings
	 */
	const OPTION_PRODUCT_MAPPINGS = 'boxi_panelalpha_product_mappings';

	/**
	 * Option name for general settings
	 */
	const OPTION_SETTINGS = 'boxi_panelalpha_settings';

	/**
	 * Save PanelAlpha API credentials
	 *
	 * @param string $api_url PanelAlpha API base URL.
	 * @param string $api_token PanelAlpha API token.
	 * @return bool True on success, false on failure.
	 */
	public function save_panelalpha_credentials( $api_url, $api_token ) {
		// Sanitize API URL (remove trailing slash)
		$api_url = untrailingslashit( esc_url_raw( $api_url ) );

		// Encrypt API token before storage
		$encrypted_token = $this->encrypt_credential( $api_token );

		if ( false === $encrypted_token ) {
			return false;
		}

		// Save to WordPress options
		update_option( self::OPTION_API_URL, $api_url );
		update_option( self::OPTION_API_TOKEN, $encrypted_token );

		return true;
	}

	/**
	 * Get PanelAlpha API credentials
	 *
	 * @return array|null Array with 'api_url' and 'api_token' keys, or null if not configured.
	 */
	public function get_panelalpha_credentials() {
		$api_url = get_option( self::OPTION_API_URL );
		$encrypted_token = get_option( self::OPTION_API_TOKEN );

		if ( empty( $api_url ) || empty( $encrypted_token ) ) {
			return null;
		}

		// Decrypt API token
		$api_token = $this->decrypt_credential( $encrypted_token );

		if ( false === $api_token ) {
			return null;
		}

		return array(
			'api_url'   => $api_url,
			'api_token' => $api_token,
		);
	}

	/**
	 * Encrypt a credential using WordPress AUTH_KEY
	 *
	 * @param string $plaintext The plaintext credential to encrypt.
	 * @return string|false Encrypted credential (base64 encoded), or false on failure.
	 */
	public function encrypt_credential( $plaintext ) {
		if ( ! defined( 'AUTH_KEY' ) || empty( AUTH_KEY ) ) {
			error_log( 'Boxi PanelAlpha: AUTH_KEY not defined in wp-config.php' );
			return false;
		}

		// Use AES-256-CBC encryption
		$method = 'AES-256-CBC';

		// Derive key from AUTH_KEY
		$key = hash( 'sha256', AUTH_KEY, true );

		// Generate random IV
		$iv_length = openssl_cipher_iv_length( $method );
		$iv = openssl_random_pseudo_bytes( $iv_length );

		// Encrypt
		$encrypted = openssl_encrypt( $plaintext, $method, $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $encrypted ) {
			error_log( 'Boxi PanelAlpha: Encryption failed' );
			return false;
		}

		// Combine IV and encrypted data, then base64 encode
		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt a credential using WordPress AUTH_KEY
	 *
	 * @param string $ciphertext The encrypted credential (base64 encoded).
	 * @return string|false Decrypted plaintext, or false on failure.
	 */
	public function decrypt_credential( $ciphertext ) {
		if ( ! defined( 'AUTH_KEY' ) || empty( AUTH_KEY ) ) {
			error_log( 'Boxi PanelAlpha: AUTH_KEY not defined in wp-config.php' );
			return false;
		}

		// Use AES-256-CBC encryption
		$method = 'AES-256-CBC';

		// Derive key from AUTH_KEY
		$key = hash( 'sha256', AUTH_KEY, true );

		// Decode base64
		$data = base64_decode( $ciphertext, true );

		if ( false === $data ) {
			error_log( 'Boxi PanelAlpha: Base64 decode failed' );
			return false;
		}

		// Extract IV and encrypted data
		$iv_length = openssl_cipher_iv_length( $method );
		$iv = substr( $data, 0, $iv_length );
		$encrypted = substr( $data, $iv_length );

		// Decrypt
		$plaintext = openssl_decrypt( $encrypted, $method, $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $plaintext ) {
			error_log( 'Boxi PanelAlpha: Decryption failed' );
			return false;
		}

		return $plaintext;
	}

	/**
	 * Save product mapping
	 *
	 * @param int    $product_id WooCommerce product ID.
	 * @param string $plan_id PanelAlpha plan ID.
	 * @param bool   $auto_provision Enable automatic provisioning.
	 * @return bool True on success, false on failure.
	 */
	public function save_product_mapping( $product_id, $plan_id, $auto_provision = true ) {
		$mappings = $this->get_all_mappings();

		$mappings[ $product_id ] = array(
			'plan_id'        => sanitize_text_field( $plan_id ),
			'auto_provision' => (bool) $auto_provision,
		);

		return update_option( self::OPTION_PRODUCT_MAPPINGS, $mappings );
	}

	/**
	 * Get product mapping
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array|null Mapping array with 'plan_id' and 'auto_provision', or null if not mapped.
	 */
	public function get_product_mapping( $product_id ) {
		$mappings = $this->get_all_mappings();

		if ( isset( $mappings[ $product_id ] ) ) {
			return $mappings[ $product_id ];
		}

		return null;
	}

	/**
	 * Get all product mappings
	 *
	 * @return array Array of mappings indexed by product ID.
	 */
	public function get_all_mappings() {
		$mappings = get_option( self::OPTION_PRODUCT_MAPPINGS, array() );

		if ( ! is_array( $mappings ) ) {
			return array();
		}

		return $mappings;
	}

	/**
	 * Delete product mapping
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_product_mapping( $product_id ) {
		$mappings = $this->get_all_mappings();

		if ( isset( $mappings[ $product_id ] ) ) {
			unset( $mappings[ $product_id ] );
			return update_option( self::OPTION_PRODUCT_MAPPINGS, $mappings );
		}

		return false;
	}

	/**
	 * Validate plan ID exists in PanelAlpha
	 *
	 * Note: This requires an API client instance. Validation should be done
	 * by the admin UI using the API client's get_plan() method.
	 *
	 * @param string $plan_id PanelAlpha plan ID.
	 * @return bool True if valid (for now, always returns true - validation done externally).
	 */
	public function validate_plan_id( $plan_id ) {
		// Basic validation - not empty
		if ( empty( $plan_id ) ) {
			return false;
		}

		// Full validation requires API call - should be done by admin UI
		// using API client's get_plan() method
		return true;
	}

	/**
	 * Alias for get_all_mappings() for backward compatibility
	 *
	 * @return array Array of all product mappings.
	 */
	public function get_all_product_mappings() {
		return $this->get_all_mappings();
	}

	/**
	 * Get general settings
	 *
	 * @return array Settings array.
	 */
	public function get_settings() {
		$defaults = array(
			'rate_limit'            => 55,
			'provisioning_timeout'  => 300,
			'retry_attempts'        => 5,
			'log_retention_days'    => 90,
		);

		$settings = get_option( self::OPTION_SETTINGS, $defaults );

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Update general settings
	 *
	 * @param array $settings Settings array.
	 * @return bool True on success, false on failure.
	 */
	public function update_settings( $settings ) {
		$current = $this->get_settings();
		$updated = wp_parse_args( $settings, $current );

		// Sanitize values
		$updated['rate_limit'] = absint( $updated['rate_limit'] );
		$updated['provisioning_timeout'] = absint( $updated['provisioning_timeout'] );
		$updated['retry_attempts'] = absint( $updated['retry_attempts'] );
		$updated['log_retention_days'] = absint( $updated['log_retention_days'] );

		return update_option( self::OPTION_SETTINGS, $updated );
	}
}
