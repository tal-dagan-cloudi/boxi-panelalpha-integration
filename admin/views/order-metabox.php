<?php
/**
 * Order Metabox View
 *
 * Display provisioning status and controls on WooCommerce order detail page.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get provisioning status
$processed = get_post_meta( $order_id, '_panelalpha_processed', true );
$status = get_post_meta( $order_id, '_panelalpha_status', true );
$service_id = get_post_meta( $order_id, '_panelalpha_service_id', true );
$panelalpha_user_id = get_post_meta( $order_id, '_panelalpha_user_id', true );
$processed_date = get_post_meta( $order_id, '_panelalpha_processed_date', true );

// Check if order has hosting products
$order = wc_get_order( $order_id );
$has_hosting = false;

if ( $order ) {
	$plugin = Boxi_PanelAlpha::get_instance();
	$config = $plugin->get_config();

	foreach ( $order->get_items() as $item ) {
		$product_id = $item->get_product_id();
		$mapping = $config->get_product_mapping( $product_id );

		if ( $mapping && $mapping['auto_provision'] ) {
			$has_hosting = true;
			break;
		}
	}
}
?>

<div class="boxi-metabox">
	<?php if ( ! $has_hosting ) : ?>
		<!-- No Hosting Products -->
		<div class="boxi-status-card status-not-applicable">
			<p>
				<span class="dashicons dashicons-info"></span>
				<?php esc_html_e( 'This order does not contain any hosting products.', 'boxi-panelalpha' ); ?>
			</p>
		</div>
	<?php elseif ( ! $processed ) : ?>
		<!-- Not Yet Processed -->
		<div class="boxi-status-card status-pending">
			<p>
				<span class="dashicons dashicons-clock"></span>
				<strong><?php esc_html_e( 'Status:', 'boxi-panelalpha' ); ?></strong>
				<?php esc_html_e( 'Pending Provisioning', 'boxi-panelalpha' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'Hosting will be provisioned automatically when the order is marked as completed.', 'boxi-panelalpha' ); ?>
			</p>
		</div>
	<?php else : ?>
		<!-- Provisioned -->
		<div class="boxi-status-card status-<?php echo esc_attr( $status ); ?>">
			<!-- Status Header -->
			<div class="boxi-status-header">
				<?php if ( 'active' === $status ) : ?>
					<span class="dashicons dashicons-yes-alt"></span>
					<strong><?php esc_html_e( 'Status:', 'boxi-panelalpha' ); ?></strong>
					<span class="status-badge status-active"><?php esc_html_e( 'Active', 'boxi-panelalpha' ); ?></span>
				<?php elseif ( 'provisioning' === $status ) : ?>
					<span class="dashicons dashicons-update"></span>
					<strong><?php esc_html_e( 'Status:', 'boxi-panelalpha' ); ?></strong>
					<span class="status-badge status-provisioning"><?php esc_html_e( 'Provisioning', 'boxi-panelalpha' ); ?></span>
				<?php elseif ( 'suspended' === $status ) : ?>
					<span class="dashicons dashicons-warning"></span>
					<strong><?php esc_html_e( 'Status:', 'boxi-panelalpha' ); ?></strong>
					<span class="status-badge status-suspended"><?php esc_html_e( 'Suspended', 'boxi-panelalpha' ); ?></span>
				<?php elseif ( 'cancelled' === $status ) : ?>
					<span class="dashicons dashicons-dismiss"></span>
					<strong><?php esc_html_e( 'Status:', 'boxi-panelalpha' ); ?></strong>
					<span class="status-badge status-cancelled"><?php esc_html_e( 'Cancelled', 'boxi-panelalpha' ); ?></span>
				<?php else : ?>
					<span class="dashicons dashicons-info"></span>
					<strong><?php esc_html_e( 'Status:', 'boxi-panelalpha' ); ?></strong>
					<span class="status-badge status-unknown"><?php echo esc_html( ucfirst( $status ) ); ?></span>
				<?php endif; ?>
			</div>

			<!-- Service Details -->
			<div class="boxi-service-details">
				<?php if ( $service_id ) : ?>
					<p>
						<strong><?php esc_html_e( 'Service ID:', 'boxi-panelalpha' ); ?></strong>
						<code><?php echo esc_html( $service_id ); ?></code>
					</p>
				<?php endif; ?>

				<?php if ( $panelalpha_user_id ) : ?>
					<p>
						<strong><?php esc_html_e( 'PanelAlpha User ID:', 'boxi-panelalpha' ); ?></strong>
						<code><?php echo esc_html( $panelalpha_user_id ); ?></code>
					</p>
				<?php endif; ?>

				<?php if ( $processed_date ) : ?>
					<p>
						<strong><?php esc_html_e( 'Provisioned:', 'boxi-panelalpha' ); ?></strong>
						<?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $processed_date ) ); ?>
					</p>
				<?php endif; ?>
			</div>

			<!-- Actions -->
			<div class="boxi-actions">
				<?php if ( 'active' === $status && $service_id ) : ?>
					<button
						type="button"
						class="button button-primary boxi-reveal-credentials"
						data-order-id="<?php echo esc_attr( $order_id ); ?>"
					>
						<span class="dashicons dashicons-visibility"></span>
						<?php esc_html_e( 'Reveal Credentials', 'boxi-panelalpha' ); ?>
					</button>
				<?php endif; ?>

				<button
					type="button"
					class="button boxi-retry-provision"
					data-order-id="<?php echo esc_attr( $order_id ); ?>"
				>
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Retry Provisioning', 'boxi-panelalpha' ); ?>
				</button>

				<a
					href="<?php echo esc_url( admin_url( 'admin.php?page=boxi-event-log&order_id=' . $order_id ) ); ?>"
					class="button"
				>
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( 'View Event Log', 'boxi-panelalpha' ); ?>
				</a>
			</div>
		</div>
	<?php endif; ?>

	<!-- Credentials Display (hidden by default) -->
	<div id="boxi-credentials-display" class="boxi-credentials" style="display: none;">
		<div class="boxi-credentials-header">
			<h4><?php esc_html_e( 'Hosting Credentials', 'boxi-panelalpha' ); ?></h4>
			<button type="button" class="boxi-credentials-close">&times;</button>
		</div>
		<div class="boxi-credentials-content">
			<div class="credential-field">
				<strong><?php esc_html_e( 'Username:', 'boxi-panelalpha' ); ?></strong>
				<code class="credential-username"></code>
			</div>
			<div class="credential-field">
				<strong><?php esc_html_e( 'Password:', 'boxi-panelalpha' ); ?></strong>
				<code class="credential-password"></code>
			</div>
			<div class="credential-field">
				<strong><?php esc_html_e( 'Email:', 'boxi-panelalpha' ); ?></strong>
				<code class="credential-email"></code>
			</div>
		</div>
		<div class="boxi-credentials-warning">
			<span class="dashicons dashicons-warning"></span>
			<?php esc_html_e( 'These credentials are sensitive. Do not share them publicly.', 'boxi-panelalpha' ); ?>
		</div>
	</div>

	<!-- Action Status Messages -->
	<div id="boxi-action-status" class="notice" style="display: none; margin-top: 10px;"></div>
</div>

<style>
.boxi-metabox .boxi-status-card {
	padding: 15px;
	background: #f9f9f9;
	border-left: 4px solid #ddd;
	margin-bottom: 15px;
}

.boxi-metabox .boxi-status-card.status-active {
	border-color: #46b450;
	background: #f0fff4;
}

.boxi-metabox .boxi-status-card.status-provisioning {
	border-color: #00a0d2;
	background: #f0f8ff;
}

.boxi-metabox .boxi-status-card.status-suspended {
	border-color: #ffb900;
	background: #fffbf0;
}

.boxi-metabox .boxi-status-card.status-cancelled {
	border-color: #dc3232;
	background: #fff0f0;
}

.boxi-metabox .boxi-status-card.status-pending {
	border-color: #999;
	background: #f9f9f9;
}

.boxi-metabox .boxi-status-header {
	display: flex;
	align-items: center;
	gap: 5px;
	margin-bottom: 10px;
}

.boxi-metabox .boxi-status-header .dashicons {
	width: 20px;
	height: 20px;
	font-size: 20px;
}

.boxi-metabox .status-badge {
	display: inline-block;
	padding: 2px 8px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 600;
	text-transform: uppercase;
}

.boxi-metabox .status-badge.status-active {
	background: #46b450;
	color: #fff;
}

.boxi-metabox .status-badge.status-provisioning {
	background: #00a0d2;
	color: #fff;
}

.boxi-metabox .status-badge.status-suspended {
	background: #ffb900;
	color: #fff;
}

.boxi-metabox .status-badge.status-cancelled {
	background: #dc3232;
	color: #fff;
}

.boxi-metabox .boxi-service-details p {
	margin: 5px 0;
}

.boxi-metabox .boxi-actions {
	margin-top: 15px;
	display: flex;
	flex-wrap: wrap;
	gap: 5px;
}

.boxi-metabox .boxi-actions .button .dashicons {
	line-height: 1.4;
	margin-right: 3px;
}

.boxi-metabox .boxi-credentials {
	padding: 15px;
	background: #fff;
	border: 1px solid #ddd;
	margin-top: 15px;
}

.boxi-metabox .boxi-credentials-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 10px;
	padding-bottom: 10px;
	border-bottom: 1px solid #ddd;
}

.boxi-metabox .boxi-credentials-header h4 {
	margin: 0;
}

.boxi-metabox .boxi-credentials-close {
	background: none;
	border: none;
	font-size: 24px;
	cursor: pointer;
	padding: 0;
	line-height: 1;
}

.boxi-metabox .credential-field {
	margin: 10px 0;
}

.boxi-metabox .credential-field code {
	display: block;
	padding: 8px;
	background: #f0f0f1;
	margin-top: 5px;
	font-family: monospace;
	font-size: 13px;
}

.boxi-metabox .boxi-credentials-warning {
	margin-top: 15px;
	padding: 10px;
	background: #fff3cd;
	border-left: 4px solid #ffb900;
	display: flex;
	align-items: center;
	gap: 5px;
}

.boxi-metabox .boxi-credentials-warning .dashicons {
	color: #ffb900;
}
</style>
