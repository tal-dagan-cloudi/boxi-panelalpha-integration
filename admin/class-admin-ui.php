<?php
/**
 * Admin UI Controller
 *
 * Handles admin interface, settings pages, and order metaboxes.
 */

class Boxi_Admin_UI {

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

		// Register admin hooks
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_order_metabox' ) );

		// AJAX handlers
		add_action( 'wp_ajax_boxi_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_boxi_save_credentials', array( $this, 'ajax_save_credentials' ) );
		add_action( 'wp_ajax_boxi_save_mapping', array( $this, 'ajax_save_mapping' ) );
		add_action( 'wp_ajax_boxi_delete_mapping', array( $this, 'ajax_delete_mapping' ) );
		add_action( 'wp_ajax_boxi_retry_provision', array( $this, 'ajax_retry_provision' ) );
		add_action( 'wp_ajax_boxi_reveal_credentials', array( $this, 'ajax_reveal_credentials' ) );
	}

	/**
	 * Add admin menu pages
	 */
	public function add_admin_menu() {
		// Main menu page
		add_menu_page(
			__( 'Boxi Integration', 'boxi-panelalpha' ),
			__( 'Boxi Integration', 'boxi-panelalpha' ),
			'manage_options',
			'boxi-integration',
			array( $this, 'render_settings_page' ),
			'dashicons-cloud',
			56
		);

		// Settings submenu
		add_submenu_page(
			'boxi-integration',
			__( 'Settings', 'boxi-panelalpha' ),
			__( 'Settings', 'boxi-panelalpha' ),
			'manage_options',
			'boxi-integration',
			array( $this, 'render_settings_page' )
		);

		// Event logs submenu
		add_submenu_page(
			'boxi-integration',
			__( 'Event Log', 'boxi-panelalpha' ),
			__( 'Event Log', 'boxi-panelalpha' ),
			'manage_options',
			'boxi-event-log',
			array( $this, 'render_logs_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our admin pages
		if ( ! strpos( $hook, 'boxi' ) ) {
			return;
		}

		// Enqueue admin CSS
		wp_enqueue_style(
			'boxi-admin-css',
			BOXI_PANELALPHA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BOXI_PANELALPHA_VERSION
		);

		// Enqueue admin JS
		wp_enqueue_script(
			'boxi-admin-js',
			BOXI_PANELALPHA_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BOXI_PANELALPHA_VERSION,
			true
		);

		// Localize script with AJAX URL and nonce
		wp_localize_script(
			'boxi-admin-js',
			'boxiAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'boxi_admin_nonce' ),
			)
		);
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'boxi-panelalpha' ) );
		}

		include BOXI_PANELALPHA_PLUGIN_DIR . 'admin/views/settings-page.php';
	}

	/**
	 * Render logs page
	 */
	public function render_logs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'boxi-panelalpha' ) );
		}

		include BOXI_PANELALPHA_PLUGIN_DIR . 'admin/views/logs-page.php';
	}

	/**
	 * Add order metabox
	 */
	public function add_order_metabox() {
		add_meta_box(
			'boxi_provisioning_status',
			__( 'Boxi Provisioning Status', 'boxi-panelalpha' ),
			array( $this, 'render_order_metabox' ),
			'shop_order',
			'side',
			'high'
		);
	}

	/**
	 * Render order metabox
	 *
	 * @param WP_Post $post Order post object.
	 */
	public function render_order_metabox( $post ) {
		$order_id = $post->ID;
		include BOXI_PANELALPHA_PLUGIN_DIR . 'admin/views/order-metabox.php';
	}

	/**
	 * AJAX: Test API connection
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'boxi_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$api_url = isset( $_POST['api_url'] ) ? esc_url_raw( $_POST['api_url'] ) : '';
		$api_token = isset( $_POST['api_token'] ) ? sanitize_text_field( $_POST['api_token'] ) : '';

		if ( empty( $api_url ) || empty( $api_token ) ) {
			wp_send_json_error( array( 'message' => 'API URL and token are required' ) );
		}

		// Create temporary API client
		$rate_limiter = new Boxi_Rate_Limiter();
		$temp_client = new Boxi_PanelAlpha_Client( $api_url, $api_token, $rate_limiter, $this->logger );

		// Test connection
		$result = $temp_client->test_connection();

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	/**
	 * AJAX: Save API credentials
	 */
	public function ajax_save_credentials() {
		check_ajax_referer( 'boxi_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$api_url = isset( $_POST['api_url'] ) ? esc_url_raw( $_POST['api_url'] ) : '';
		$api_token = isset( $_POST['api_token'] ) ? sanitize_text_field( $_POST['api_token'] ) : '';

		if ( empty( $api_url ) || empty( $api_token ) ) {
			wp_send_json_error( array( 'message' => 'API URL and token are required' ) );
		}

		$result = $this->config->save_panelalpha_credentials( $api_url, $api_token );

		if ( $result ) {
			wp_send_json_success( array( 'message' => 'Credentials saved successfully' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to save credentials' ) );
		}
	}

	/**
	 * AJAX: Save product mapping
	 */
	public function ajax_save_mapping() {
		check_ajax_referer( 'boxi_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$plan_id = isset( $_POST['plan_id'] ) ? sanitize_text_field( $_POST['plan_id'] ) : '';
		$auto_provision = isset( $_POST['auto_provision'] ) ? (bool) $_POST['auto_provision'] : true;

		if ( ! $product_id || empty( $plan_id ) ) {
			wp_send_json_error( array( 'message' => 'Product ID and Plan ID are required' ) );
		}

		$result = $this->config->save_product_mapping( $product_id, $plan_id, $auto_provision );

		if ( $result ) {
			wp_send_json_success( array( 'message' => 'Product mapping saved successfully' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to save product mapping' ) );
		}
	}

	/**
	 * AJAX: Delete product mapping
	 */
	public function ajax_delete_mapping() {
		check_ajax_referer( 'boxi_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => 'Product ID is required' ) );
		}

		$result = $this->config->delete_product_mapping( $product_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => 'Product mapping deleted successfully' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to delete product mapping' ) );
		}
	}

	/**
	 * AJAX: Retry provisioning for an order
	 */
	public function ajax_retry_provision() {
		check_ajax_referer( 'boxi_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => 'Order ID is required' ) );
		}

		// Clear processed flag to allow retry
		delete_post_meta( $order_id, '_panelalpha_processed' );

		// Get queue manager
		$plugin = Boxi_PanelAlpha::get_instance();
		$queue_manager = $plugin->get_queue_manager();

		// Enqueue provisioning job
		$job_id = $queue_manager->enqueue_provision( $order_id );

		if ( false === $job_id ) {
			wp_send_json_error( array( 'message' => 'Failed to enqueue provisioning job' ) );
		}

		wp_send_json_success( array( 'message' => 'Provisioning job queued successfully' ) );
	}

	/**
	 * AJAX: Reveal credentials for an order
	 */
	public function ajax_reveal_credentials() {
		check_ajax_referer( 'boxi_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => 'Order ID is required' ) );
		}

		// Get encrypted credentials
		$encrypted_credentials = get_post_meta( $order_id, '_panelalpha_credentials_encrypted', true );

		if ( empty( $encrypted_credentials ) ) {
			wp_send_json_error( array( 'message' => 'Credentials not found' ) );
		}

		// Decrypt credentials
		$decrypted = $this->config->decrypt_credential( $encrypted_credentials );

		if ( false === $decrypted ) {
			wp_send_json_error( array( 'message' => 'Failed to decrypt credentials' ) );
		}

		$credentials = json_decode( $decrypted, true );

		if ( null === $credentials ) {
			wp_send_json_error( array( 'message' => 'Invalid credentials format' ) );
		}

		// Log credential access
		$this->logger->info( 'Admin revealed credentials', array(
			'event_type' => 'credentials_revealed',
			'order_id'   => $order_id,
			'user_id'    => get_current_user_id(),
		));

		wp_send_json_success( array( 'credentials' => $credentials ) );
	}
}
