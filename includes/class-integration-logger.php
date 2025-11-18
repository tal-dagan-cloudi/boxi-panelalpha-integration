<?php
/**
 * Integration Logger
 *
 * Handles logging of integration events to custom database table.
 */

class Boxi_Integration_Logger {

	/**
	 * Database table name (without prefix)
	 */
	const TABLE_NAME = 'boxi_integration_logs';

	/**
	 * Log levels
	 */
	const LEVEL_DEBUG = 'debug';
	const LEVEL_INFO = 'info';
	const LEVEL_WARNING = 'warning';
	const LEVEL_ERROR = 'error';

	/**
	 * Log an event
	 *
	 * @param string $level Log level (debug, info, warning, error).
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return int|false Log ID on success, false on failure.
	 */
	public function log( $level, $message, $context = array() ) {
		global $wpdb;

		// Validate level
		$valid_levels = array( self::LEVEL_DEBUG, self::LEVEL_INFO, self::LEVEL_WARNING, self::LEVEL_ERROR );
		if ( ! in_array( $level, $valid_levels, true ) ) {
			$level = self::LEVEL_INFO;
		}

		// Skip debug logs unless debug mode is enabled
		if ( self::LEVEL_DEBUG === $level && ! $this->is_debug_mode() ) {
			return false;
		}

		// Extract common context fields
		$event_type = isset( $context['event_type'] ) ? sanitize_text_field( $context['event_type'] ) : 'general';
		$order_id = isset( $context['order_id'] ) ? absint( $context['order_id'] ) : null;
		$customer_email = isset( $context['customer_email'] ) ? sanitize_email( $context['customer_email'] ) : null;
		$status = isset( $context['status'] ) ? sanitize_text_field( $context['status'] ) : 'unknown';

		// Remove extracted fields from context to avoid duplication
		unset( $context['event_type'], $context['order_id'], $context['customer_email'], $context['status'] );

		// Prepare data for insertion
		$data = array(
			'level'          => $level,
			'event_type'     => $event_type,
			'order_id'       => $order_id,
			'customer_email' => $customer_email,
			'status'         => $status,
			'message'        => sanitize_text_field( $message ),
			'context'        => wp_json_encode( $context ),
		);

		$format = array(
			'%s', // level
			'%s', // event_type
			'%d', // order_id
			'%s', // customer_email
			'%s', // status
			'%s', // message
			'%s', // context
		);

		// Insert into database
		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$result = $wpdb->insert( $table_name, $data, $format );

		if ( false === $result ) {
			error_log( 'Boxi PanelAlpha: Failed to insert log entry: ' . $wpdb->last_error );
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get logs with filters
	 *
	 * @param array $filters Filter criteria.
	 * @param int   $limit   Maximum number of logs to return (optional).
	 * @param int   $offset  Offset for pagination (optional).
	 * @return array Array of log entries.
	 */
	public function get_logs( $filters = array(), $limit = null, $offset = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// Build WHERE clause
		$where = array( '1=1' );
		$where_values = array();

		// Filter by level
		if ( isset( $filters['level'] ) && ! empty( $filters['level'] ) ) {
			$where[] = 'level = %s';
			$where_values[] = $filters['level'];
		}

		// Filter by event type
		if ( isset( $filters['event_type'] ) && ! empty( $filters['event_type'] ) ) {
			$where[] = 'event_type = %s';
			$where_values[] = $filters['event_type'];
		}

		// Filter by order ID
		if ( isset( $filters['order_id'] ) && ! empty( $filters['order_id'] ) ) {
			$where[] = 'order_id = %d';
			$where_values[] = absint( $filters['order_id'] );
		}

		// Filter by status
		if ( isset( $filters['status'] ) && ! empty( $filters['status'] ) ) {
			$where[] = 'status = %s';
			$where_values[] = $filters['status'];
		}

		// Filter by date range
		if ( isset( $filters['date_from'] ) && ! empty( $filters['date_from'] ) ) {
			$where[] = 'timestamp >= %s';
			$where_values[] = $filters['date_from'];
		}

		if ( isset( $filters['date_to'] ) && ! empty( $filters['date_to'] ) ) {
			$where[] = 'timestamp <= %s';
			$where_values[] = $filters['date_to'];
		}

		// Build ORDER BY clause
		$order_by = 'timestamp DESC';
		if ( isset( $filters['order_by'] ) ) {
			$allowed_order = array( 'timestamp', 'level', 'event_type', 'status' );
			if ( in_array( $filters['order_by'], $allowed_order, true ) ) {
				$order_by = $filters['order_by'] . ' DESC';
			}
		}

		// Build LIMIT clause - allow override from parameters
		if ( null !== $limit ) {
			$limit = absint( $limit );
		} elseif ( isset( $filters['limit'] ) ) {
			$limit = absint( $filters['limit'] );
		} else {
			$limit = 100; // Default limit
		}

		if ( null !== $offset ) {
			$offset = absint( $offset );
		} elseif ( isset( $filters['offset'] ) ) {
			$offset = absint( $filters['offset'] );
		} else {
			$offset = 0;
		}

		// Build query
		$where_clause = implode( ' AND ', $where );
		$query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY $order_by LIMIT %d OFFSET %d";

		// Add limit and offset to prepared values
		$where_values[] = $limit;
		$where_values[] = $offset;

		// Prepare and execute query
		if ( ! empty( $where_values ) ) {
			$prepared_query = $wpdb->prepare( $query, $where_values );
		} else {
			$prepared_query = $query;
		}

		$results = $wpdb->get_results( $prepared_query, ARRAY_A );

		// Decode JSON context for each result
		foreach ( $results as &$result ) {
			if ( ! empty( $result['context'] ) ) {
				$result['context'] = json_decode( $result['context'], true );
			}
		}

		return $results;
	}

	/**
	 * Get log count with filters
	 *
	 * @param array $filters Filter criteria.
	 * @return int Total count of matching logs.
	 */
	public function get_log_count( $filters = array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// Build WHERE clause (same as get_logs)
		$where = array( '1=1' );
		$where_values = array();

		if ( isset( $filters['level'] ) && ! empty( $filters['level'] ) ) {
			$where[] = 'level = %s';
			$where_values[] = $filters['level'];
		}

		if ( isset( $filters['event_type'] ) && ! empty( $filters['event_type'] ) ) {
			$where[] = 'event_type = %s';
			$where_values[] = $filters['event_type'];
		}

		if ( isset( $filters['order_id'] ) && ! empty( $filters['order_id'] ) ) {
			$where[] = 'order_id = %d';
			$where_values[] = absint( $filters['order_id'] );
		}

		if ( isset( $filters['status'] ) && ! empty( $filters['status'] ) ) {
			$where[] = 'status = %s';
			$where_values[] = $filters['status'];
		}

		if ( isset( $filters['date_from'] ) && ! empty( $filters['date_from'] ) ) {
			$where[] = 'timestamp >= %s';
			$where_values[] = $filters['date_from'];
		}

		if ( isset( $filters['date_to'] ) && ! empty( $filters['date_to'] ) ) {
			$where[] = 'timestamp <= %s';
			$where_values[] = $filters['date_to'];
		}

		$where_clause = implode( ' AND ', $where );
		$query = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";

		if ( ! empty( $where_values ) ) {
			$prepared_query = $wpdb->prepare( $query, $where_values );
		} else {
			$prepared_query = $query;
		}

		return (int) $wpdb->get_var( $prepared_query );
	}

	/**
	 * Cleanup old logs based on retention setting
	 *
	 * @return int|false Number of deleted rows, or false on failure.
	 */
	public function cleanup_old_logs() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// Get retention setting
		$config = new Boxi_Config_Manager();
		$settings = $config->get_settings();
		$retention_days = isset( $settings['log_retention_days'] ) ? absint( $settings['log_retention_days'] ) : 90;

		// Delete old logs
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$retention_days
			)
		);

		if ( false !== $result ) {
			$this->log(
				self::LEVEL_INFO,
				sprintf( 'Cleaned up %d old log entries', $result ),
				array(
					'event_type' => 'cleanup',
					'status'     => 'success',
					'deleted'    => $result,
				)
			);
		}

		return $result;
	}

	/**
	 * Check if debug mode is enabled
	 *
	 * @return bool True if debug mode enabled.
	 */
	private function is_debug_mode() {
		return defined( 'BOXI_DEBUG' ) && BOXI_DEBUG;
	}

	/**
	 * Helper: Log info level
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return int|false Log ID on success, false on failure.
	 */
	public function info( $message, $context = array() ) {
		return $this->log( self::LEVEL_INFO, $message, $context );
	}

	/**
	 * Helper: Log warning level
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return int|false Log ID on success, false on failure.
	 */
	public function warning( $message, $context = array() ) {
		return $this->log( self::LEVEL_WARNING, $message, $context );
	}

	/**
	 * Helper: Log error level
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return int|false Log ID on success, false on failure.
	 */
	public function error( $message, $context = array() ) {
		return $this->log( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Helper: Log debug level
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return int|false Log ID on success, false on failure.
	 */
	public function debug( $message, $context = array() ) {
		return $this->log( self::LEVEL_DEBUG, $message, $context );
	}

	/**
	 * Alias for get_log_count() for backward compatibility
	 *
	 * @param array $filters Filter criteria.
	 * @return int Total count of matching logs.
	 */
	public function count_logs( $filters = array() ) {
		return $this->get_log_count( $filters );
	}
}
