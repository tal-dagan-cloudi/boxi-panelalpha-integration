<?php
/**
 * Event Logs Page View
 *
 * Display and filter integration event logs.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get plugin instance
$plugin = Boxi_PanelAlpha::get_instance();
$logger = $plugin->get_logger();

// Get filter parameters
$level_filter = isset( $_GET['level'] ) ? sanitize_text_field( $_GET['level'] ) : '';
$event_type_filter = isset( $_GET['event_type'] ) ? sanitize_text_field( $_GET['event_type'] ) : '';
$order_id_filter = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

// Pagination
$per_page = 50;
$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$offset = ( $paged - 1 ) * $per_page;

// Get logs
$filters = array();
if ( ! empty( $level_filter ) ) {
	$filters['level'] = $level_filter;
}
if ( ! empty( $event_type_filter ) ) {
	$filters['event_type'] = $event_type_filter;
}
if ( $order_id_filter > 0 ) {
	$filters['order_id'] = $order_id_filter;
}

$logs = $logger->get_logs( $filters, $per_page, $offset );
$total_logs = $logger->count_logs( $filters );
$total_pages = ceil( $total_logs / $per_page );

// Get available event types for filter
global $wpdb;
$event_types = $wpdb->get_col(
	"SELECT DISTINCT event_type FROM {$wpdb->prefix}boxi_integration_logs ORDER BY event_type"
);
?>

<div class="wrap boxi-logs">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<!-- Filters -->
	<div class="boxi-log-filters card">
		<form method="get" action="">
			<input type="hidden" name="page" value="boxi-event-log" />

			<div class="filter-group">
				<label for="level"><?php esc_html_e( 'Log Level:', 'boxi-panelalpha' ); ?></label>
				<select name="level" id="level">
					<option value=""><?php esc_html_e( 'All Levels', 'boxi-panelalpha' ); ?></option>
					<option value="debug" <?php selected( $level_filter, 'debug' ); ?>><?php esc_html_e( 'Debug', 'boxi-panelalpha' ); ?></option>
					<option value="info" <?php selected( $level_filter, 'info' ); ?>><?php esc_html_e( 'Info', 'boxi-panelalpha' ); ?></option>
					<option value="warning" <?php selected( $level_filter, 'warning' ); ?>><?php esc_html_e( 'Warning', 'boxi-panelalpha' ); ?></option>
					<option value="error" <?php selected( $level_filter, 'error' ); ?>><?php esc_html_e( 'Error', 'boxi-panelalpha' ); ?></option>
				</select>
			</div>

			<div class="filter-group">
				<label for="event_type"><?php esc_html_e( 'Event Type:', 'boxi-panelalpha' ); ?></label>
				<select name="event_type" id="event_type">
					<option value=""><?php esc_html_e( 'All Events', 'boxi-panelalpha' ); ?></option>
					<?php foreach ( $event_types as $event_type ) : ?>
						<option value="<?php echo esc_attr( $event_type ); ?>" <?php selected( $event_type_filter, $event_type ); ?>>
							<?php echo esc_html( ucwords( str_replace( '_', ' ', $event_type ) ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="filter-group">
				<label for="order_id"><?php esc_html_e( 'Order ID:', 'boxi-panelalpha' ); ?></label>
				<input
					type="number"
					name="order_id"
					id="order_id"
					value="<?php echo $order_id_filter > 0 ? esc_attr( $order_id_filter ) : ''; ?>"
					placeholder="<?php esc_attr_e( 'Filter by order...', 'boxi-panelalpha' ); ?>"
					class="small-text"
				/>
			</div>

			<div class="filter-group">
				<button type="submit" class="button">
					<?php esc_html_e( 'Filter', 'boxi-panelalpha' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=boxi-event-log' ) ); ?>" class="button">
					<?php esc_html_e( 'Clear Filters', 'boxi-panelalpha' ); ?>
				</a>
			</div>
		</form>
	</div>

	<!-- Log Statistics -->
	<div class="boxi-log-stats">
		<p>
			<?php
			/* translators: %d: number of log entries */
			printf( esc_html__( 'Showing %d log entries', 'boxi-panelalpha' ), $total_logs );
			?>
		</p>
	</div>

	<!-- Logs Table -->
	<?php if ( ! empty( $logs ) ) : ?>
		<table class="wp-list-table widefat fixed striped boxi-logs-table">
			<thead>
				<tr>
					<th class="column-timestamp"><?php esc_html_e( 'Timestamp', 'boxi-panelalpha' ); ?></th>
					<th class="column-level"><?php esc_html_e( 'Level', 'boxi-panelalpha' ); ?></th>
					<th class="column-event-type"><?php esc_html_e( 'Event Type', 'boxi-panelalpha' ); ?></th>
					<th class="column-order"><?php esc_html_e( 'Order', 'boxi-panelalpha' ); ?></th>
					<th class="column-message"><?php esc_html_e( 'Message', 'boxi-panelalpha' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Actions', 'boxi-panelalpha' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) : ?>
					<?php
					$level_class = 'log-level-' . esc_attr( $log['level'] );
					$context = json_decode( $log['context'], true );
					?>
					<tr class="<?php echo esc_attr( $level_class ); ?>">
						<td class="column-timestamp">
							<?php echo esc_html( mysql2date( 'Y-m-d H:i:s', $log['created_at'] ) ); ?>
						</td>
						<td class="column-level">
							<span class="log-level-badge log-level-<?php echo esc_attr( $log['level'] ); ?>">
								<?php echo esc_html( strtoupper( $log['level'] ) ); ?>
							</span>
						</td>
						<td class="column-event-type">
							<?php echo esc_html( ucwords( str_replace( '_', ' ', $log['event_type'] ) ) ); ?>
						</td>
						<td class="column-order">
							<?php if ( $log['order_id'] ) : ?>
								<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $log['order_id'] . '&action=edit' ) ); ?>">
									#<?php echo esc_html( $log['order_id'] ); ?>
								</a>
							<?php else : ?>
								<span class="text-muted">—</span>
							<?php endif; ?>
						</td>
						<td class="column-message">
							<?php echo esc_html( $log['message'] ); ?>
						</td>
						<td class="column-actions">
							<button
								type="button"
								class="button button-small view-context"
								data-context="<?php echo esc_attr( wp_json_encode( $context ) ); ?>"
							>
								<?php esc_html_e( 'View Context', 'boxi-panelalpha' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					$base_url = add_query_arg(
						array(
							'page'       => 'boxi-event-log',
							'level'      => $level_filter,
							'event_type' => $event_type_filter,
							'order_id'   => $order_id_filter,
						),
						admin_url( 'admin.php' )
					);

					echo paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%', $base_url ),
							'format'    => '',
							'current'   => $paged,
							'total'     => $total_pages,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'No log entries found matching your filters.', 'boxi-panelalpha' ); ?></p>
		</div>
	<?php endif; ?>
</div>

<!-- Context Modal -->
<div id="boxi-context-modal" class="boxi-modal" style="display: none;">
	<div class="boxi-modal-content">
		<div class="boxi-modal-header">
			<h2><?php esc_html_e( 'Log Context', 'boxi-panelalpha' ); ?></h2>
			<button type="button" class="boxi-modal-close">&times;</button>
		</div>
		<div class="boxi-modal-body">
			<pre id="context-data"></pre>
		</div>
	</div>
</div>
