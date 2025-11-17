<?php
/**
 * Main Plugin Class
 *
 * Singleton class that coordinates all plugin components
 */

class Boxi_PanelAlpha {

	/**
	 * Singleton instance
	 */
	private static $instance = null;

	/**
	 * Configuration manager instance
	 */
	private $config = null;

	/**
	 * Logger instance
	 */
	private $logger = null;

	/**
	 * API client instance
	 */
	private $api_client = null;

	/**
	 * Event listener instance
	 */
	private $event_listener = null;

	/**
	 * Queue manager instance
	 */
	private $queue_manager = null;

	/**
	 * Get singleton instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor (singleton pattern)
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize plugin components
	 */
	private function init() {
		// Initialize configuration manager
		$this->config = new Boxi_Config_Manager();

		// Initialize logger
		$this->logger = new Boxi_Integration_Logger();

		// Initialize API client (if credentials configured)
		$credentials = $this->config->get_panelalpha_credentials();
		if ( $credentials && ! empty( $credentials['api_url'] ) && ! empty( $credentials['api_token'] ) ) {
			$rate_limiter = new Boxi_Rate_Limiter();
			$this->api_client = new Boxi_PanelAlpha_Client(
				$credentials['api_url'],
				$credentials['api_token'],
				$rate_limiter,
				$this->logger
			);
		}

		// Initialize queue manager
		$this->queue_manager = new Boxi_Queue_Manager( $this->logger );

		// Initialize event listener (hooks into WooCommerce)
		$this->event_listener = new Boxi_Event_Listener(
			$this->config,
			$this->logger,
			$this->queue_manager
		);

		// Initialize admin UI (if in admin)
		if ( is_admin() ) {
			new Boxi_Admin_UI( $this->config, $this->logger, $this->api_client );
		}

		// Log plugin initialization
		$this->logger->log( 'info', 'Boxi PanelAlpha Integration initialized', array(
			'version' => BOXI_PANELALPHA_VERSION,
			'has_credentials' => ! is_null( $this->api_client ),
		));
	}

	/**
	 * Get configuration manager
	 */
	public function get_config() {
		return $this->config;
	}

	/**
	 * Get logger
	 */
	public function get_logger() {
		return $this->logger;
	}

	/**
	 * Get API client
	 */
	public function get_api_client() {
		return $this->api_client;
	}

	/**
	 * Get queue manager
	 */
	public function get_queue_manager() {
		return $this->queue_manager;
	}
}
