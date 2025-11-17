<?php
/**
 * Event Listener
 *
 * Hooks into WooCommerce events and triggers provisioning workflows.
 */

class Boxi_Event_Listener {

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
	 * Queue manager instance
	 *
	 * @var Boxi_Queue_Manager
	 */
	private $queue_manager;

	/**
	 * Constructor
	 *
	 * @param Boxi_Config_Manager     $config Configuration manager.
	 * @param Boxi_Integration_Logger $logger Logger instance.
	 * @param Boxi_Queue_Manager      $queue_manager Queue manager instance.
	 */
	public function __construct( $config, $logger, $queue_manager ) {
		$this->config = $config;
		$this->logger = $logger;
		$this->queue_manager = $queue_manager;

		// Register WooCommerce hooks
		$this->register_hooks();
	}

	/**
	 * Register WooCommerce event hooks
	 */
	private function register_hooks() {
		// Order completion - triggers provisioning
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_order_completed' ), 10, 1 );

		// Subscription events
		add_action( 'woocommerce_subscription_payment_failed', array( $this, 'on_payment_failed' ), 10, 1 );
		add_action( 'woocommerce_subscription_renewal_payment_complete', array( $this, 'on_payment_complete' ), 10, 1 );
		add_action( 'woocommerce_subscription_status_cancelled', array( $this, 'on_subscription_cancelled' ), 10, 1 );
		add_action( 'woocommerce_subscription_status_expired', array( $this, 'on_subscription_cancelled' ), 10, 1 );

		// Customer data changes
		add_action( 'woocommerce_customer_save_address', array( $this, 'on_customer_address_changed' ), 10, 2 );
	}

	/**
	 * Handle order completion
	 *
	 * @param int $order_id Order ID.
	 */
	public function on_order_completed( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$this->logger->info( 'Order completed event received', array(
			'event_type'     => 'order_completed',
			'order_id'       => $order_id,
			'customer_email' => $order->get_billing_email(),
		));

		// Check if order contains hosting products with auto-provisioning enabled
		$has_hosting_product = false;

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$mapping = $this->config->get_product_mapping( $product_id );

			if ( $mapping && $mapping['auto_provision'] ) {
				$has_hosting_product = true;
				break;
			}
		}

		if ( ! $has_hosting_product ) {
			$this->logger->debug( 'Order does not contain hosting products', array(
				'event_type' => 'order_completed',
				'order_id'   => $order_id,
				'status'     => 'skipped',
			));
			return;
		}

		// Enqueue provisioning job
		$job_id = $this->queue_manager->enqueue_provision( $order_id );

		if ( false === $job_id ) {
			$this->logger->error( 'Failed to enqueue provisioning job', array(
				'event_type' => 'order_completed',
				'order_id'   => $order_id,
				'status'     => 'failed',
			));
		}
	}

	/**
	 * Handle subscription payment failure
	 *
	 * @param WC_Subscription $subscription Subscription object.
	 */
	public function on_payment_failed( $subscription ) {
		$this->logger->info( 'Subscription payment failed', array(
			'event_type'       => 'payment_failed',
			'subscription_id'  => $subscription->get_id(),
			'customer_email'   => $subscription->get_billing_email(),
		));

		// Get parent order to find service ID
		$parent_order_id = $subscription->get_parent_id();

		if ( ! $parent_order_id ) {
			return;
		}

		$service_id = get_post_meta( $parent_order_id, '_panelalpha_service_id', true );

		if ( ! $service_id ) {
			$this->logger->warning( 'No service ID found for subscription', array(
				'event_type'       => 'payment_failed',
				'subscription_id'  => $subscription->get_id(),
				'status'           => 'skipped',
			));
			return;
		}

		// Enqueue suspension job
		$this->queue_manager->enqueue_suspend( $service_id, $parent_order_id );
	}

	/**
	 * Handle subscription payment completion (renewal)
	 *
	 * @param WC_Subscription $subscription Subscription object.
	 */
	public function on_payment_complete( $subscription ) {
		$this->logger->info( 'Subscription payment completed', array(
			'event_type'       => 'payment_complete',
			'subscription_id'  => $subscription->get_id(),
			'customer_email'   => $subscription->get_billing_email(),
		));

		// Get parent order to find service ID
		$parent_order_id = $subscription->get_parent_id();

		if ( ! $parent_order_id ) {
			return;
		}

		$service_id = get_post_meta( $parent_order_id, '_panelalpha_service_id', true );

		if ( ! $service_id ) {
			return;
		}

		// Check if service is suspended
		$status = get_post_meta( $parent_order_id, '_panelalpha_status', true );

		if ( 'suspended' === $status ) {
			// Enqueue unsuspension job
			$this->queue_manager->enqueue_unsuspend( $service_id, $parent_order_id );
		}
	}

	/**
	 * Handle subscription cancellation
	 *
	 * @param WC_Subscription $subscription Subscription object.
	 */
	public function on_subscription_cancelled( $subscription ) {
		$this->logger->info( 'Subscription cancelled', array(
			'event_type'       => 'subscription_cancelled',
			'subscription_id'  => $subscription->get_id(),
			'customer_email'   => $subscription->get_billing_email(),
		));

		// Get parent order to find service ID
		$parent_order_id = $subscription->get_parent_id();

		if ( ! $parent_order_id ) {
			return;
		}

		$service_id = get_post_meta( $parent_order_id, '_panelalpha_service_id', true );

		if ( ! $service_id ) {
			$this->logger->warning( 'No service ID found for subscription', array(
				'event_type'       => 'subscription_cancelled',
				'subscription_id'  => $subscription->get_id(),
				'status'           => 'skipped',
			));
			return;
		}

		// Enqueue cancellation job
		$this->queue_manager->enqueue_cancel( $service_id, $parent_order_id );
	}

	/**
	 * Handle customer address change
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $load_address Address type (billing or shipping).
	 */
	public function on_customer_address_changed( $user_id, $load_address ) {
		// Only sync billing address changes
		if ( 'billing' !== $load_address ) {
			return;
		}

		$this->logger->debug( 'Customer address changed', array(
			'event_type' => 'customer_update',
			'user_id'    => $user_id,
		));

		// Check if user has PanelAlpha account
		$panelalpha_user_id = get_user_meta( $user_id, '_panelalpha_user_id', true );

		if ( ! $panelalpha_user_id ) {
			return;
		}

		// Get customer data
		$customer = new WC_Customer( $user_id );

		// Prepare update data
		$update_data = array(
			'first_name' => $customer->get_billing_first_name(),
			'last_name'  => $customer->get_billing_last_name(),
			'company'    => $customer->get_billing_company(),
		);

		// Get API client
		$plugin = Boxi_PanelAlpha::get_instance();
		$api_client = $plugin->get_api_client();

		if ( ! $api_client ) {
			$this->logger->warning( 'API client not initialized', array(
				'event_type' => 'customer_update',
				'user_id'    => $user_id,
				'status'     => 'skipped',
			));
			return;
		}

		// Update user in PanelAlpha
		$result = $api_client->update_user( $panelalpha_user_id, $update_data );

		if ( is_wp_error( $result ) ) {
			$this->logger->error( 'Failed to update customer in PanelAlpha', array(
				'event_type'          => 'customer_update',
				'user_id'             => $user_id,
				'panelalpha_user_id'  => $panelalpha_user_id,
				'status'              => 'failed',
				'error'               => $result->get_error_message(),
			));
		} else {
			$this->logger->info( 'Customer updated in PanelAlpha', array(
				'event_type'          => 'customer_update',
				'user_id'             => $user_id,
				'panelalpha_user_id'  => $panelalpha_user_id,
				'status'              => 'success',
			));
		}
	}
}
