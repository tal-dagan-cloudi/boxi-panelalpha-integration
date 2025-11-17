<?php
/**
 * Customer Sync
 *
 * Helper functions for synchronizing customer data between WooCommerce and PanelAlpha.
 */

class Boxi_Customer_Sync {

	/**
	 * Config manager instance
	 *
	 * @var Boxi_Config_Manager
	 */
	private $config;

	/**
	 * Logger instance
	 *
	 * @var Boxi_Integration_Logger
	 */
	private $logger;

	/**
	 * API client instance
	 *
	 * @var Boxi_PanelAlpha_Client
	 */
	private $api_client;

	/**
	 * Constructor
	 *
	 * @param Boxi_Config_Manager     $config Configuration manager.
	 * @param Boxi_Integration_Logger $logger Logger instance.
	 * @param Boxi_PanelAlpha_Client  $api_client API client instance.
	 */
	public function __construct( $config, $logger, $api_client ) {
		$this->config = $config;
		$this->logger = $logger;
		$this->api_client = $api_client;
	}

	/**
	 * Get or create PanelAlpha user for WooCommerce customer
	 *
	 * @param int $customer_id WooCommerce customer ID.
	 * @return int|WP_Error PanelAlpha user ID on success, WP_Error on failure.
	 */
	public function get_or_create_user( $customer_id ) {
		// Check if user already linked
		$panelalpha_user_id = get_user_meta( $customer_id, '_panelalpha_user_id', true );

		if ( $panelalpha_user_id ) {
			return absint( $panelalpha_user_id );
		}

		// Get customer data
		$customer = new WC_Customer( $customer_id );
		$email = $customer->get_email();

		if ( empty( $email ) ) {
			return new WP_Error( 'no_email', 'Customer email not found' );
		}

		// Try to find existing user in PanelAlpha
		$user = $this->api_client->get_user_by_email( $email );

		if ( ! is_wp_error( $user ) ) {
			// Link existing user
			update_user_meta( $customer_id, '_panelalpha_user_id', $user['id'] );

			$this->logger->info( 'Linked existing PanelAlpha user', array(
				'event_type'          => 'customer_sync',
				'customer_id'         => $customer_id,
				'panelalpha_user_id'  => $user['id'],
				'customer_email'      => $email,
			));

			return $user['id'];
		}

		// Create new user in PanelAlpha
		$password = wp_generate_password( 16, true, true );
		$user = $this->api_client->create_user(
			$email,
			$password,
			$customer->get_first_name(),
			$customer->get_last_name(),
			$customer->get_billing_company()
		);

		if ( is_wp_error( $user ) ) {
			$this->logger->error( 'Failed to create PanelAlpha user', array(
				'event_type'     => 'customer_sync',
				'customer_id'    => $customer_id,
				'customer_email' => $email,
				'status'         => 'failed',
				'error'          => $user->get_error_message(),
			));

			return $user;
		}

		// Link new user
		update_user_meta( $customer_id, '_panelalpha_user_id', $user['id'] );

		$this->logger->info( 'Created and linked PanelAlpha user', array(
			'event_type'          => 'customer_sync',
			'customer_id'         => $customer_id,
			'panelalpha_user_id'  => $user['id'],
			'customer_email'      => $email,
		));

		return $user['id'];
	}

	/**
	 * Sync customer data to PanelAlpha
	 *
	 * @param int $customer_id WooCommerce customer ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function sync_customer_data( $customer_id ) {
		// Get PanelAlpha user ID
		$panelalpha_user_id = get_user_meta( $customer_id, '_panelalpha_user_id', true );

		if ( ! $panelalpha_user_id ) {
			return new WP_Error( 'not_linked', 'Customer not linked to PanelAlpha user' );
		}

		// Get customer data
		$customer = new WC_Customer( $customer_id );

		// Prepare update data
		$update_data = array(
			'first_name' => $customer->get_first_name(),
			'last_name'  => $customer->get_last_name(),
			'company'    => $customer->get_billing_company(),
		);

		// Update user in PanelAlpha
		$result = $this->api_client->update_user( $panelalpha_user_id, $update_data );

		if ( is_wp_error( $result ) ) {
			$this->logger->error( 'Failed to sync customer data', array(
				'event_type'          => 'customer_sync',
				'customer_id'         => $customer_id,
				'panelalpha_user_id'  => $panelalpha_user_id,
				'status'              => 'failed',
				'error'               => $result->get_error_message(),
			));

			return $result;
		}

		$this->logger->info( 'Customer data synced', array(
			'event_type'          => 'customer_sync',
			'customer_id'         => $customer_id,
			'panelalpha_user_id'  => $panelalpha_user_id,
			'status'              => 'success',
		));

		return true;
	}

	/**
	 * Get PanelAlpha user ID for WooCommerce customer
	 *
	 * @param int $customer_id WooCommerce customer ID.
	 * @return int|null PanelAlpha user ID or null if not linked.
	 */
	public function get_panelalpha_user_id( $customer_id ) {
		$user_id = get_user_meta( $customer_id, '_panelalpha_user_id', true );

		if ( $user_id ) {
			return absint( $user_id );
		}

		return null;
	}

	/**
	 * Link WooCommerce customer to PanelAlpha user
	 *
	 * @param int $customer_id WooCommerce customer ID.
	 * @param int $panelalpha_user_id PanelAlpha user ID.
	 * @return bool True on success.
	 */
	public function link_customer( $customer_id, $panelalpha_user_id ) {
		update_user_meta( $customer_id, '_panelalpha_user_id', $panelalpha_user_id );

		$this->logger->info( 'Linked customer to PanelAlpha user', array(
			'event_type'          => 'customer_sync',
			'customer_id'         => $customer_id,
			'panelalpha_user_id'  => $panelalpha_user_id,
		));

		return true;
	}

	/**
	 * Unlink WooCommerce customer from PanelAlpha user
	 *
	 * @param int $customer_id WooCommerce customer ID.
	 * @return bool True on success.
	 */
	public function unlink_customer( $customer_id ) {
		delete_user_meta( $customer_id, '_panelalpha_user_id' );

		$this->logger->info( 'Unlinked customer from PanelAlpha user', array(
			'event_type'  => 'customer_sync',
			'customer_id' => $customer_id,
		));

		return true;
	}
}
