<?php
/**
 * Queue Manager
 *
 * Handles asynchronous provisioning using WordPress Action Scheduler.
 */

class Boxi_Queue_Manager {

	/**
	 * Action hook for provisioning job
	 */
	const ACTION_PROVISION = 'boxi_provision_hosting';

	/**
	 * Action hook for service suspension
	 */
	const ACTION_SUSPEND = 'boxi_suspend_service';

	/**
	 * Action hook for service unsuspension
	 */
	const ACTION_UNSUSPEND = 'boxi_unsuspend_service';

	/**
	 * Action hook for service cancellation
	 */
	const ACTION_CANCEL = 'boxi_cancel_service';

	/**
	 * Logger instance
	 *
	 * @var Boxi_Integration_Logger
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @param Boxi_Integration_Logger $logger Logger instance.
	 */
	public function __construct( $logger ) {
		$this->logger = $logger;

		// Register action handlers
		add_action( self::ACTION_PROVISION, array( $this, 'process_provision_job' ), 10, 1 );
		add_action( self::ACTION_SUSPEND, array( $this, 'process_suspend_job' ), 10, 1 );
		add_action( self::ACTION_UNSUSPEND, array( $this, 'process_unsuspend_job' ), 10, 1 );
		add_action( self::ACTION_CANCEL, array( $this, 'process_cancel_job' ), 10, 1 );
	}

	/**
	 * Enqueue provisioning job
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return int|false Job ID on success, false on failure.
	 */
	public function enqueue_provision( $order_id ) {
		// Check if job already exists for this order
		if ( $this->has_pending_job( self::ACTION_PROVISION, $order_id ) ) {
			$this->logger->warning( 'Provisioning job already queued', array(
				'event_type' => 'queue',
				'order_id'   => $order_id,
				'status'     => 'duplicate',
			));
			return false;
		}

		// Schedule async action
		$job_id = as_enqueue_async_action(
			self::ACTION_PROVISION,
			array( 'order_id' => $order_id ),
			'boxi-panelalpha'
		);

		$this->logger->info( 'Provisioning job queued', array(
			'event_type' => 'queue',
			'order_id'   => $order_id,
			'status'     => 'queued',
			'job_id'     => $job_id,
		));

		return $job_id;
	}

	/**
	 * Enqueue service suspension job
	 *
	 * @param int $service_id PanelAlpha service ID.
	 * @param int $order_id WooCommerce order ID.
	 * @return int|false Job ID on success, false on failure.
	 */
	public function enqueue_suspend( $service_id, $order_id ) {
		$args = array(
			'service_id' => $service_id,
			'order_id'   => $order_id,
		);

		$job_id = as_enqueue_async_action(
			self::ACTION_SUSPEND,
			$args,
			'boxi-panelalpha'
		);

		$this->logger->info( 'Suspension job queued', array(
			'event_type' => 'queue',
			'order_id'   => $order_id,
			'status'     => 'queued',
			'job_id'     => $job_id,
			'service_id' => $service_id,
		));

		return $job_id;
	}

	/**
	 * Enqueue service unsuspension job
	 *
	 * @param int $service_id PanelAlpha service ID.
	 * @param int $order_id WooCommerce order ID.
	 * @return int|false Job ID on success, false on failure.
	 */
	public function enqueue_unsuspend( $service_id, $order_id ) {
		$args = array(
			'service_id' => $service_id,
			'order_id'   => $order_id,
		);

		$job_id = as_enqueue_async_action(
			self::ACTION_UNSUSPEND,
			$args,
			'boxi-panelalpha'
		);

		$this->logger->info( 'Unsuspension job queued', array(
			'event_type' => 'queue',
			'order_id'   => $order_id,
			'status'     => 'queued',
			'job_id'     => $job_id,
			'service_id' => $service_id,
		));

		return $job_id;
	}

	/**
	 * Enqueue service cancellation job
	 *
	 * @param int $service_id PanelAlpha service ID.
	 * @param int $order_id WooCommerce order ID.
	 * @return int|false Job ID on success, false on failure.
	 */
	public function enqueue_cancel( $service_id, $order_id ) {
		$args = array(
			'service_id' => $service_id,
			'order_id'   => $order_id,
		);

		$job_id = as_enqueue_async_action(
			self::ACTION_CANCEL,
			$args,
			'boxi-panelalpha'
		);

		$this->logger->info( 'Cancellation job queued', array(
			'event_type' => 'queue',
			'order_id'   => $order_id,
			'status'     => 'queued',
			'job_id'     => $job_id,
			'service_id' => $service_id,
		));

		return $job_id;
	}

	/**
	 * Process provisioning job
	 *
	 * @param array $args Job arguments with 'order_id'.
	 */
	public function process_provision_job( $args ) {
		$order_id = isset( $args['order_id'] ) ? absint( $args['order_id'] ) : 0;

		if ( ! $order_id ) {
			$this->logger->error( 'Invalid provision job: missing order_id', array(
				'event_type' => 'queue',
				'status'     => 'failed',
			));
			return;
		}

		$this->logger->info( 'Processing provisioning job', array(
			'event_type' => 'queue',
			'order_id'   => $order_id,
			'status'     => 'processing',
		));

		// Get orchestrator instance
		$orchestrator = $this->get_orchestrator();

		if ( ! $orchestrator ) {
			$this->logger->error( 'Failed to initialize orchestrator', array(
				'event_type' => 'queue',
				'order_id'   => $order_id,
				'status'     => 'failed',
			));
			return;
		}

		// Run provisioning workflow
		$result = $orchestrator->provision_hosting( $order_id );

		if ( is_wp_error( $result ) ) {
			$this->logger->error( 'Provisioning job failed', array(
				'event_type' => 'queue',
				'order_id'   => $order_id,
				'status'     => 'failed',
				'error'      => $result->get_error_message(),
			));

			// Job will automatically retry via Action Scheduler
			throw new Exception( $result->get_error_message() );
		}

		$this->logger->info( 'Provisioning job completed', array(
			'event_type' => 'queue',
			'order_id'   => $order_id,
			'status'     => 'completed',
		));
	}

	/**
	 * Process suspension job
	 *
	 * @param array $args Job arguments with 'service_id' and 'order_id'.
	 */
	public function process_suspend_job( $args ) {
		$service_id = isset( $args['service_id'] ) ? absint( $args['service_id'] ) : 0;
		$order_id = isset( $args['order_id'] ) ? absint( $args['order_id'] ) : 0;

		if ( ! $service_id ) {
			$this->logger->error( 'Invalid suspend job: missing service_id', array(
				'event_type' => 'queue',
				'order_id'   => $order_id,
				'status'     => 'failed',
			));
			return;
		}

		$this->logger->info( 'Processing suspension job', array(
			'event_type' => 'queue',
			'order_id'   => $order_id,
			'service_id' => $service_id,
			'status'     => 'processing',
		));

		// Get API client
		$api_client = $this->get_api_client();

		if ( ! $api_client ) {
			$this->logger->error( 'Failed to initialize API client', array(
				'event_type' => 'queue',
				'order_id'   => $order_id,
				'status'     => 'failed',
			));
			return;
		}

		// Suspend service
		$result = $api_client->suspend_service( $service_id );

		if ( is_wp_error( $result ) ) {
			$this->logger->error( 'Suspension job failed', array(
				'event_type' => 'queue',
				'order_id'   => $order_id,
				'service_id' => $service_id,
				'status'     => 'failed',
				'error'      => $result->get_error_message(),
			));

			throw new Exception( $result->get_error_message() );
		}

		$this->logger->info( 'Suspension job completed', array(
			'event_type' => 'queue',
			'order_id'   => $order_id,
			'service_id' => $service_id,
			'status'     => 'completed',
		));
	}

	/**
	 * Process unsuspension job
	 *
	 * @param array $args Job arguments with 'service_id' and 'order_id'.
	 */
	public function process_unsuspend_job( $args ) {
		$service_id = isset( $args['service_id'] ) ? absint( $args['service_id'] ) : 0;
		$order_id = isset( $args['order_id'] ) ? absint( $args['order_id'] ) : 0;

		if ( ! $service_id ) {
			$this->logger->error( 'Invalid unsuspend job: missing service_id', array(
				'event_type' => 'queue',
				'order_id'   => $order_id,
				'status'     => 'failed',
			));
			return;
		}

		$this->logger->info( 'Processing unsuspension job', array(
			'event_type' => 'queue',
			'order_id'   => $order_id,
			'service_id' => $service_id,
			'status'     => 'processing',
		));

		// Get API client
		$api_client = $this->get_api_client();

		if ( ! $api_client ) {
			$this->logger->error( 'Failed to initialize API client', array(
				'event_type' => 'queue',
				'order_id'   => $order_id,
				'status'     => 'failed',
			));
			return;
		}

		// Unsuspend service
		$result = $api_client->unsuspend_service( $service_id );

		if ( is_wp_error( $result ) ) {
			$this->logger->error( 'Unsuspension job failed', array(
				'event_type' => 'queue',
				'order_id'   => $order_id,
				'service_id' => $service_id,
				'status'     => 'failed',
				'error'      => $result->get_error_message(),
			));

			throw new Exception( $result->get_error_message() );
		}

		$this->logger->info( 'Unsuspension job completed', array(
			'event_type' => 'queue',
			'order_id'   => $order_id,
			'service_id' => $service_id,
			'status'     => 'completed',
		));
	}

	/**
	 * Process cancellation job
	 *
	 * @param array $args Job arguments with 'service_id' and 'order_id'.
	 */
	public function process_cancel_job( $args ) {
		$service_id = isset( $args['service_id'] ) ? absint( $args['service_id'] ) : 0;
		$order_id = isset( $args['order_id'] ) ? absint( $args['order_id'] ) : 0;

		if ( ! $service_id ) {
			$this->logger->error( 'Invalid cancel job: missing service_id', array(
				'event_type' => 'queue',
				'order_id'   => $order_id,
				'status'     => 'failed',
			));
			return;
		}

		$this->logger->info( 'Processing cancellation job', array(
			'event_type' => 'queue',
			'order_id'   => $order_id,
			'service_id' => $service_id,
			'status'     => 'processing',
		));

		// Get API client
		$api_client = $this->get_api_client();

		if ( ! $api_client ) {
			$this->logger->error( 'Failed to initialize API client', array(
				'event_type' => 'queue',
				'order_id'   => $order_id,
				'status'     => 'failed',
			));
			return;
		}

		// Cancel service
		$result = $api_client->cancel_service( $service_id );

		if ( is_wp_error( $result ) ) {
			$this->logger->error( 'Cancellation job failed', array(
				'event_type' => 'queue',
				'order_id'   => $order_id,
				'service_id' => $service_id,
				'status'     => 'failed',
				'error'      => $result->get_error_message(),
			));

			throw new Exception( $result->get_error_message() );
		}

		$this->logger->info( 'Cancellation job completed', array(
			'event_type' => 'queue',
			'order_id'   => $order_id,
			'service_id' => $service_id,
			'status'     => 'completed',
		));
	}

	/**
	 * Check if a pending job exists for an order
	 *
	 * @param string $action Action hook name.
	 * @param int    $order_id Order ID.
	 * @return bool True if pending job exists.
	 */
	private function has_pending_job( $action, $order_id ) {
		$pending = as_get_scheduled_actions(
			array(
				'hook'     => $action,
				'args'     => array( 'order_id' => $order_id ),
				'status'   => array( 'pending', 'in-progress' ),
				'per_page' => 1,
			),
			'ids'
		);

		return ! empty( $pending );
	}

	/**
	 * Get orchestrator instance
	 *
	 * @return Boxi_Provisioning_Orchestrator|null
	 */
	private function get_orchestrator() {
		$plugin = Boxi_PanelAlpha::get_instance();
		$config = $plugin->get_config();
		$logger = $plugin->get_logger();
		$api_client = $plugin->get_api_client();

		if ( ! $api_client ) {
			return null;
		}

		return new Boxi_Provisioning_Orchestrator( $config, $logger, $api_client );
	}

	/**
	 * Get API client instance
	 *
	 * @return Boxi_PanelAlpha_Client|null
	 */
	private function get_api_client() {
		$plugin = Boxi_PanelAlpha::get_instance();
		return $plugin->get_api_client();
	}
}
