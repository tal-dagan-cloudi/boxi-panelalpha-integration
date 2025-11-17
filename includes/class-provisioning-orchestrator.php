<?php
/**
 * Provisioning Orchestrator
 *
 * Coordinates the multi-step provisioning workflow.
 */

class Boxi_Provisioning_Orchestrator {

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
	 * Provision hosting for an order
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array|WP_Error Provisioning result on success, WP_Error on failure.
	 */
	public function provision_hosting( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'invalid_order', 'Order not found' );
		}

		$this->logger->info( 'Starting provisioning workflow', array(
			'event_type'     => 'provisioning',
			'order_id'       => $order_id,
			'status'         => 'started',
			'customer_email' => $order->get_billing_email(),
		));

		// Step 1: Check if already processed
		if ( $this->is_order_processed( $order_id ) ) {
			$this->logger->warning( 'Order already processed', array(
				'event_type' => 'provisioning',
				'order_id'   => $order_id,
				'status'     => 'duplicate',
			));

			return new WP_Error( 'already_processed', 'Order already processed' );
		}

		// Step 2: Validate product mapping
		$mapping = $this->get_product_mapping( $order );

		if ( ! $mapping ) {
			$this->logger->error( 'No product mapping found', array(
				'event_type' => 'provisioning',
				'order_id'   => $order_id,
				'status'     => 'failed',
			));

			return new WP_Error( 'no_mapping', 'Product not mapped to PanelAlpha plan' );
		}

		// Step 3: Ensure PanelAlpha user exists
		$user_id = $this->ensure_user_exists( $order );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// Step 4: Get domain from order
		$domain = $this->get_domain_from_order( $order );

		if ( ! $domain ) {
			return new WP_Error( 'no_domain', 'Domain not specified in order' );
		}

		// Step 5: Create service in PanelAlpha
		$service = $this->api_client->create_service( $user_id, $mapping['plan_id'], $domain );

		if ( is_wp_error( $service ) ) {
			return $service;
		}

		$service_id = $service['id'];

		// Save service ID to order meta
		update_post_meta( $order_id, '_panelalpha_service_id', $service_id );
		update_post_meta( $order_id, '_panelalpha_user_id', $user_id );
		update_post_meta( $order_id, '_panelalpha_status', 'provisioning' );

		$this->logger->info( 'Service created in PanelAlpha', array(
			'event_type' => 'provisioning',
			'order_id'   => $order_id,
			'status'     => 'service_created',
			'service_id' => $service_id,
			'user_id'    => $user_id,
		));

		// Step 6: Poll for provisioning completion
		$credentials = $this->wait_for_provisioning( $service_id, $order_id );

		if ( is_wp_error( $credentials ) ) {
			// Rollback: Cancel the service
			$this->api_client->cancel_service( $service_id );

			$this->logger->error( 'Provisioning timeout, service cancelled', array(
				'event_type' => 'provisioning',
				'order_id'   => $order_id,
				'status'     => 'failed',
				'service_id' => $service_id,
				'error'      => $credentials->get_error_message(),
			));

			return $credentials;
		}

		// Step 7: Save credentials to order meta (encrypted)
		$encrypted_credentials = $this->config->encrypt_credential( wp_json_encode( $credentials ) );
		update_post_meta( $order_id, '_panelalpha_credentials_encrypted', $encrypted_credentials );
		update_post_meta( $order_id, '_panelalpha_status', 'active' );

		$this->logger->info( 'Credentials retrieved and saved', array(
			'event_type' => 'provisioning',
			'order_id'   => $order_id,
			'status'     => 'credentials_saved',
			'service_id' => $service_id,
		));

		// Step 8: Send credentials email
		$email_sent = $this->send_credentials_email( $order, $credentials, $domain );

		if ( ! $email_sent ) {
			$this->logger->warning( 'Failed to send credentials email', array(
				'event_type'     => 'provisioning',
				'order_id'       => $order_id,
				'status'         => 'email_failed',
				'customer_email' => $order->get_billing_email(),
			));
		} else {
			$this->logger->info( 'Credentials email sent', array(
				'event_type'     => 'provisioning',
				'order_id'       => $order_id,
				'status'         => 'email_sent',
				'customer_email' => $order->get_billing_email(),
			));
		}

		// Step 9: Mark order as processed
		update_post_meta( $order_id, '_panelalpha_processed', true );
		update_post_meta( $order_id, '_panelalpha_processed_date', current_time( 'mysql' ) );

		$this->logger->info( 'Provisioning workflow completed', array(
			'event_type' => 'provisioning',
			'order_id'   => $order_id,
			'status'     => 'completed',
			'service_id' => $service_id,
		));

		return array(
			'service_id'  => $service_id,
			'user_id'     => $user_id,
			'credentials' => $credentials,
			'domain'      => $domain,
		);
	}

	/**
	 * Check if order already processed
	 *
	 * @param int $order_id Order ID.
	 * @return bool True if processed.
	 */
	private function is_order_processed( $order_id ) {
		return (bool) get_post_meta( $order_id, '_panelalpha_processed', true );
	}

	/**
	 * Get product mapping for order
	 *
	 * @param WC_Order $order Order object.
	 * @return array|null Mapping data or null if not found.
	 */
	private function get_product_mapping( $order ) {
		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$mapping = $this->config->get_product_mapping( $product_id );

			if ( $mapping && $mapping['auto_provision'] ) {
				return $mapping;
			}
		}

		return null;
	}

	/**
	 * Ensure PanelAlpha user exists for customer
	 *
	 * @param WC_Order $order Order object.
	 * @return int|WP_Error User ID on success, WP_Error on failure.
	 */
	private function ensure_user_exists( $order ) {
		$customer_email = $order->get_billing_email();

		// Check if we already have user ID stored
		$wp_user_id = $order->get_customer_id();
		if ( $wp_user_id ) {
			$stored_user_id = get_user_meta( $wp_user_id, '_panelalpha_user_id', true );
			if ( $stored_user_id ) {
				return absint( $stored_user_id );
			}
		}

		// Try to find existing user in PanelAlpha
		$user = $this->api_client->get_user_by_email( $customer_email );

		if ( ! is_wp_error( $user ) ) {
			// User exists, save ID for future use
			if ( $wp_user_id ) {
				update_user_meta( $wp_user_id, '_panelalpha_user_id', $user['id'] );
			}

			return $user['id'];
		}

		// Create new user in PanelAlpha
		$password = wp_generate_password( 16, true, true );
		$user = $this->api_client->create_user(
			$customer_email,
			$password,
			$order->get_billing_first_name(),
			$order->get_billing_last_name(),
			$order->get_billing_company()
		);

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		// Save user ID for future use
		if ( $wp_user_id ) {
			update_user_meta( $wp_user_id, '_panelalpha_user_id', $user['id'] );
		}

		return $user['id'];
	}

	/**
	 * Get domain from order
	 *
	 * @param WC_Order $order Order object.
	 * @return string|null Domain name or null if not found.
	 */
	private function get_domain_from_order( $order ) {
		// Try to get from order meta (set by checkout field)
		$domain = get_post_meta( $order->get_id(), '_hosting_domain', true );

		if ( ! empty( $domain ) ) {
			return sanitize_text_field( $domain );
		}

		// Fallback: use customer email domain
		$email = $order->get_billing_email();
		$parts = explode( '@', $email );

		if ( isset( $parts[1] ) ) {
			return sanitize_text_field( $parts[1] );
		}

		return null;
	}

	/**
	 * Wait for provisioning to complete
	 *
	 * @param int $service_id Service ID.
	 * @param int $order_id Order ID.
	 * @return array|WP_Error Credentials on success, WP_Error on failure.
	 */
	private function wait_for_provisioning( $service_id, $order_id ) {
		$settings = $this->config->get_settings();
		$timeout = isset( $settings['provisioning_timeout'] ) ? absint( $settings['provisioning_timeout'] ) : 300;
		$start_time = time();

		while ( ( time() - $start_time ) < $timeout ) {
			// Get service status
			$service = $this->api_client->get_service( $service_id );

			if ( is_wp_error( $service ) ) {
				return $service;
			}

			// Check if provisioning is complete
			if ( isset( $service['status'] ) && 'active' === $service['status'] ) {
				// Get credentials
				$credentials = $this->api_client->get_service_credentials( $service_id );

				if ( is_wp_error( $credentials ) ) {
					return $credentials;
				}

				return $credentials;
			}

			// Check if provisioning failed
			if ( isset( $service['status'] ) && 'failed' === $service['status'] ) {
				return new WP_Error(
					'provisioning_failed',
					'Service provisioning failed in PanelAlpha',
					array( 'service' => $service )
				);
			}

			// Wait before next poll (10 seconds)
			sleep( 10 );
		}

		return new WP_Error( 'provisioning_timeout', 'Provisioning timeout exceeded' );
	}

	/**
	 * Send credentials email to customer
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $credentials Service credentials.
	 * @param string   $domain Service domain.
	 * @return bool True on success.
	 */
	private function send_credentials_email( $order, $credentials, $domain ) {
		$to = $order->get_billing_email();
		$subject = sprintf( 'Your WordPress Hosting Credentials - %s', get_bloginfo( 'name' ) );

		// Build email message
		$message = $this->build_credentials_email( $order, $credentials, $domain );

		// Email headers
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', get_bloginfo( 'name' ), get_option( 'admin_email' ) ),
		);

		// Send email
		$sent = wp_mail( $to, $subject, $message, $headers );

		return $sent;
	}

	/**
	 * Build credentials email HTML
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $credentials Service credentials.
	 * @param string   $domain Service domain.
	 * @return string Email HTML.
	 */
	private function build_credentials_email( $order, $credentials, $domain ) {
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<title>Your WordPress Hosting Credentials</title>
		</head>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
				<h2 style="color: #0073aa;">Your WordPress Hosting is Ready!</h2>

				<p>Hi <?php echo esc_html( $order->get_billing_first_name() ); ?>,</p>

				<p>Thank you for your purchase! Your WordPress hosting has been successfully provisioned.</p>

				<div style="background-color: #f7f7f7; padding: 15px; margin: 20px 0; border-left: 4px solid #0073aa;">
					<h3 style="margin-top: 0;">Login Credentials</h3>
					<p><strong>WordPress URL:</strong> <a href="https://<?php echo esc_html( $domain ); ?>/wp-admin">https://<?php echo esc_html( $domain ); ?>/wp-admin</a></p>
					<p><strong>Username:</strong> <?php echo esc_html( $credentials['username'] ); ?></p>
					<p><strong>Password:</strong> <?php echo esc_html( $credentials['password'] ); ?></p>
					<p><strong>Email:</strong> <?php echo esc_html( $credentials['email'] ); ?></p>
				</div>

				<p><strong>Important:</strong> Please change your password after your first login for security.</p>

				<h3>What's Next?</h3>
				<ol>
					<li>Log in to your WordPress admin panel using the credentials above</li>
					<li>Change your password (recommended)</li>
					<li>Start building your website!</li>
				</ol>

				<p>If you need any assistance, please don't hesitate to contact our support team.</p>

				<p>Best regards,<br><?php echo esc_html( get_bloginfo( 'name' ) ); ?> Team</p>

				<hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">

				<p style="font-size: 12px; color: #666;">
					Order #<?php echo esc_html( $order->get_order_number() ); ?> |
					<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">View Order</a>
				</p>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}
}
