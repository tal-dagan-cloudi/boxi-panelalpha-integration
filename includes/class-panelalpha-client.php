<?php
/**
 * PanelAlpha API Client
 *
 * Handles all communication with PanelAlpha REST API.
 */

class Boxi_PanelAlpha_Client {

	/**
	 * API base URL
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * API token
	 *
	 * @var string
	 */
	private $api_token;

	/**
	 * Rate limiter instance
	 *
	 * @var Boxi_Rate_Limiter
	 */
	private $rate_limiter;

	/**
	 * Logger instance
	 *
	 * @var Boxi_Integration_Logger
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @param string                  $api_url PanelAlpha API base URL.
	 * @param string                  $api_token PanelAlpha API token.
	 * @param Boxi_Rate_Limiter       $rate_limiter Rate limiter instance.
	 * @param Boxi_Integration_Logger $logger Logger instance.
	 */
	public function __construct( $api_url, $api_token, $rate_limiter, $logger ) {
		$this->api_url = untrailingslashit( $api_url );
		$this->api_token = $api_token;
		$this->rate_limiter = $rate_limiter;
		$this->logger = $logger;
	}

	/**
	 * Test API connection
	 *
	 * @return array Response with 'success' boolean and 'message'.
	 */
	public function test_connection() {
		$this->logger->debug( 'Testing PanelAlpha API connection' );

		$response = $this->make_request( 'GET', '/api/admin/plans', array(), array( 'limit' => 1 ) );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'API connection test failed', array(
				'event_type' => 'api_test',
				'status'     => 'failed',
				'error'      => $response->get_error_message(),
			));

			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$this->logger->info( 'API connection test successful', array(
			'event_type' => 'api_test',
			'status'     => 'success',
		));

		return array(
			'success' => true,
			'message' => 'Successfully connected to PanelAlpha API',
		);
	}

	/**
	 * Create user in PanelAlpha
	 *
	 * @param string $email User email address.
	 * @param string $password User password.
	 * @param string $first_name User first name.
	 * @param string $last_name User last name.
	 * @param string $company Company name (optional).
	 * @return array|WP_Error User data on success, WP_Error on failure.
	 */
	public function create_user( $email, $password, $first_name, $last_name, $company = '' ) {
		$data = array(
			'email'      => $email,
			'password'   => $password,
			'first_name' => $first_name,
			'last_name'  => $last_name,
		);

		if ( ! empty( $company ) ) {
			$data['company'] = $company;
		}

		$this->logger->debug( 'Creating PanelAlpha user', array(
			'event_type'     => 'user_create',
			'customer_email' => $email,
		));

		$response = $this->make_request( 'POST', '/api/admin/users', $data );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Failed to create PanelAlpha user', array(
				'event_type'     => 'user_create',
				'customer_email' => $email,
				'status'         => 'failed',
				'error'          => $response->get_error_message(),
			));

			return $response;
		}

		$this->logger->info( 'PanelAlpha user created successfully', array(
			'event_type'     => 'user_create',
			'customer_email' => $email,
			'status'         => 'success',
			'user_id'        => isset( $response['data']['id'] ) ? $response['data']['id'] : null,
		));

		return $response['data'];
	}

	/**
	 * Get user by email address
	 *
	 * @param string $email User email address.
	 * @return array|WP_Error User data on success, WP_Error on failure.
	 */
	public function get_user_by_email( $email ) {
		$this->logger->debug( 'Getting PanelAlpha user by email', array(
			'event_type'     => 'user_get',
			'customer_email' => $email,
		));

		$response = $this->make_request( 'GET', '/api/admin/users', array(), array(
			'email' => $email,
		));

		if ( is_wp_error( $response ) ) {
			$this->logger->warning( 'Failed to get PanelAlpha user', array(
				'event_type'     => 'user_get',
				'customer_email' => $email,
				'status'         => 'failed',
				'error'          => $response->get_error_message(),
			));

			return $response;
		}

		// Check if user exists
		if ( empty( $response['data'] ) || ! is_array( $response['data'] ) ) {
			return new WP_Error( 'user_not_found', 'User not found in PanelAlpha' );
		}

		// Return first matching user
		return $response['data'][0];
	}

	/**
	 * Update user in PanelAlpha
	 *
	 * @param int   $user_id PanelAlpha user ID.
	 * @param array $data User data to update.
	 * @return array|WP_Error Updated user data on success, WP_Error on failure.
	 */
	public function update_user( $user_id, $data ) {
		$this->logger->debug( 'Updating PanelAlpha user', array(
			'event_type' => 'user_update',
			'user_id'    => $user_id,
		));

		$response = $this->make_request( 'PUT', "/api/admin/users/{$user_id}", $data );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Failed to update PanelAlpha user', array(
				'event_type' => 'user_update',
				'status'     => 'failed',
				'user_id'    => $user_id,
				'error'      => $response->get_error_message(),
			));

			return $response;
		}

		$this->logger->info( 'PanelAlpha user updated successfully', array(
			'event_type' => 'user_update',
			'status'     => 'success',
			'user_id'    => $user_id,
		));

		return $response['data'];
	}

	/**
	 * Create service in PanelAlpha
	 *
	 * @param int    $user_id PanelAlpha user ID.
	 * @param string $plan_id PanelAlpha plan ID.
	 * @param string $domain Domain name for the service.
	 * @return array|WP_Error Service data on success, WP_Error on failure.
	 */
	public function create_service( $user_id, $plan_id, $domain ) {
		$data = array(
			'user_id' => $user_id,
			'plan_id' => $plan_id,
			'domain'  => $domain,
		);

		$this->logger->debug( 'Creating PanelAlpha service', array(
			'event_type' => 'service_create',
			'user_id'    => $user_id,
			'plan_id'    => $plan_id,
			'domain'     => $domain,
		));

		$response = $this->make_request( 'POST', '/api/admin/services', $data );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Failed to create PanelAlpha service', array(
				'event_type' => 'service_create',
				'status'     => 'failed',
				'user_id'    => $user_id,
				'plan_id'    => $plan_id,
				'domain'     => $domain,
				'error'      => $response->get_error_message(),
			));

			return $response;
		}

		$this->logger->info( 'PanelAlpha service created successfully', array(
			'event_type' => 'service_create',
			'status'     => 'success',
			'service_id' => isset( $response['data']['id'] ) ? $response['data']['id'] : null,
			'user_id'    => $user_id,
			'plan_id'    => $plan_id,
		));

		return $response['data'];
	}

	/**
	 * Get service details
	 *
	 * @param int $service_id PanelAlpha service ID.
	 * @return array|WP_Error Service data on success, WP_Error on failure.
	 */
	public function get_service( $service_id ) {
		$this->logger->debug( 'Getting PanelAlpha service', array(
			'event_type' => 'service_get',
			'service_id' => $service_id,
		));

		$response = $this->make_request( 'GET', "/api/admin/services/{$service_id}" );

		if ( is_wp_error( $response ) ) {
			$this->logger->warning( 'Failed to get PanelAlpha service', array(
				'event_type' => 'service_get',
				'status'     => 'failed',
				'service_id' => $service_id,
				'error'      => $response->get_error_message(),
			));

			return $response;
		}

		return $response['data'];
	}

	/**
	 * Get service credentials (WordPress login details)
	 *
	 * @param int $service_id PanelAlpha service ID.
	 * @return array|WP_Error Credentials array on success, WP_Error on failure.
	 */
	public function get_service_credentials( $service_id ) {
		$this->logger->debug( 'Getting service credentials', array(
			'event_type' => 'credentials_get',
			'service_id' => $service_id,
		));

		$response = $this->make_request( 'GET', "/api/admin/services/{$service_id}/credentials" );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Failed to get service credentials', array(
				'event_type' => 'credentials_get',
				'status'     => 'failed',
				'service_id' => $service_id,
				'error'      => $response->get_error_message(),
			));

			return $response;
		}

		$this->logger->info( 'Service credentials retrieved successfully', array(
			'event_type' => 'credentials_get',
			'status'     => 'success',
			'service_id' => $service_id,
		));

		return $response['data'];
	}

	/**
	 * Suspend service
	 *
	 * @param int $service_id PanelAlpha service ID.
	 * @return array|WP_Error Service data on success, WP_Error on failure.
	 */
	public function suspend_service( $service_id ) {
		$this->logger->info( 'Suspending PanelAlpha service', array(
			'event_type' => 'service_suspend',
			'service_id' => $service_id,
		));

		$response = $this->make_request( 'POST', "/api/admin/services/{$service_id}/suspend" );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Failed to suspend service', array(
				'event_type' => 'service_suspend',
				'status'     => 'failed',
				'service_id' => $service_id,
				'error'      => $response->get_error_message(),
			));

			return $response;
		}

		$this->logger->info( 'Service suspended successfully', array(
			'event_type' => 'service_suspend',
			'status'     => 'success',
			'service_id' => $service_id,
		));

		return $response['data'];
	}

	/**
	 * Unsuspend service
	 *
	 * @param int $service_id PanelAlpha service ID.
	 * @return array|WP_Error Service data on success, WP_Error on failure.
	 */
	public function unsuspend_service( $service_id ) {
		$this->logger->info( 'Unsuspending PanelAlpha service', array(
			'event_type' => 'service_unsuspend',
			'service_id' => $service_id,
		));

		$response = $this->make_request( 'POST', "/api/admin/services/{$service_id}/unsuspend" );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Failed to unsuspend service', array(
				'event_type' => 'service_unsuspend',
				'status'     => 'failed',
				'service_id' => $service_id,
				'error'      => $response->get_error_message(),
			));

			return $response;
		}

		$this->logger->info( 'Service unsuspended successfully', array(
			'event_type' => 'service_unsuspend',
			'status'     => 'success',
			'service_id' => $service_id,
		));

		return $response['data'];
	}

	/**
	 * Cancel service
	 *
	 * @param int $service_id PanelAlpha service ID.
	 * @return array|WP_Error Service data on success, WP_Error on failure.
	 */
	public function cancel_service( $service_id ) {
		$this->logger->info( 'Cancelling PanelAlpha service', array(
			'event_type' => 'service_cancel',
			'service_id' => $service_id,
		));

		$response = $this->make_request( 'DELETE', "/api/admin/services/{$service_id}" );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Failed to cancel service', array(
				'event_type' => 'service_cancel',
				'status'     => 'failed',
				'service_id' => $service_id,
				'error'      => $response->get_error_message(),
			));

			return $response;
		}

		$this->logger->info( 'Service cancelled successfully', array(
			'event_type' => 'service_cancel',
			'status'     => 'success',
			'service_id' => $service_id,
		));

		return $response['data'];
	}

	/**
	 * Get plan details
	 *
	 * @param string $plan_id PanelAlpha plan ID.
	 * @return array|WP_Error Plan data on success, WP_Error on failure.
	 */
	public function get_plan( $plan_id ) {
		$this->logger->debug( 'Getting PanelAlpha plan', array(
			'event_type' => 'plan_get',
			'plan_id'    => $plan_id,
		));

		$response = $this->make_request( 'GET', "/api/admin/plans/{$plan_id}" );

		if ( is_wp_error( $response ) ) {
			$this->logger->warning( 'Failed to get plan', array(
				'event_type' => 'plan_get',
				'status'     => 'failed',
				'plan_id'    => $plan_id,
				'error'      => $response->get_error_message(),
			));

			return $response;
		}

		return $response['data'];
	}

	/**
	 * Make API request with rate limiting and retry logic
	 *
	 * @param string $method HTTP method (GET, POST, PUT, DELETE).
	 * @param string $endpoint API endpoint path.
	 * @param array  $data Request body data.
	 * @param array  $query_params Query parameters.
	 * @return array|WP_Error Response data on success, WP_Error on failure.
	 */
	private function make_request( $method, $endpoint, $data = array(), $query_params = array() ) {
		// Wait for rate limiter
		$wait_time = $this->rate_limiter->get_wait_time();
		if ( $wait_time > 0 ) {
			$this->logger->debug( "Rate limit: waiting {$wait_time} seconds" );
			sleep( $wait_time );
		}

		// Consume rate limit token
		$this->rate_limiter->consume();

		// Build URL
		$url = $this->api_url . $endpoint;

		if ( ! empty( $query_params ) ) {
			$url = add_query_arg( $query_params, $url );
		}

		// Prepare request args
		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
			'timeout' => 30,
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		// Make request
		$response = wp_remote_request( $url, $args );

		// Handle errors
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'api_request_failed',
				sprintf( 'API request failed: %s', $response->get_error_message() )
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		// Parse JSON response
		$decoded = json_decode( $body, true );

		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'invalid_json',
				sprintf( 'Invalid JSON response: %s', json_last_error_msg() )
			);
		}

		// Handle HTTP errors
		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_message = isset( $decoded['message'] ) ? $decoded['message'] : 'Unknown API error';

			return new WP_Error(
				'api_error',
				sprintf( 'API returned error %d: %s', $status_code, $error_message ),
				array( 'status_code' => $status_code, 'response' => $decoded )
			);
		}

		return $decoded;
	}
}
