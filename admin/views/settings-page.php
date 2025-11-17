<?php
/**
 * Settings Page View
 *
 * Admin interface for PanelAlpha credentials and product mappings.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get plugin instance
$plugin = Boxi_PanelAlpha::get_instance();
$config = $plugin->get_config();

// Get current settings
$credentials = $config->get_panelalpha_credentials();
$api_url = isset( $credentials['api_url'] ) ? $credentials['api_url'] : '';
$has_token = ! empty( $credentials['api_token'] );

// Get all WooCommerce products
$products = wc_get_products( array(
	'limit'   => -1,
	'status'  => 'publish',
	'orderby' => 'name',
	'order'   => 'ASC',
) );

// Get current product mappings
$all_mappings = $config->get_all_product_mappings();
?>

<div class="wrap boxi-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<!-- Tabs -->
	<h2 class="nav-tab-wrapper">
		<a href="#credentials" class="nav-tab nav-tab-active"><?php esc_html_e( 'API Credentials', 'boxi-panelalpha' ); ?></a>
		<a href="#mappings" class="nav-tab"><?php esc_html_e( 'Product Mappings', 'boxi-panelalpha' ); ?></a>
		<a href="#settings" class="nav-tab"><?php esc_html_e( 'General Settings', 'boxi-panelalpha' ); ?></a>
	</h2>

	<!-- API Credentials Tab -->
	<div id="credentials" class="tab-content active">
		<form id="boxi-credentials-form">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="api_url"><?php esc_html_e( 'PanelAlpha API URL', 'boxi-panelalpha' ); ?></label>
					</th>
					<td>
						<input
							type="url"
							id="api_url"
							name="api_url"
							value="<?php echo esc_attr( $api_url ); ?>"
							class="regular-text"
							placeholder="https://panel.boxi.co.il:8443"
						/>
						<p class="description">
							<?php esc_html_e( 'Your PanelAlpha API base URL (without trailing slash).', 'boxi-panelalpha' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="api_token"><?php esc_html_e( 'PanelAlpha API Token', 'boxi-panelalpha' ); ?></label>
					</th>
					<td>
						<input
							type="password"
							id="api_token"
							name="api_token"
							value=""
							class="regular-text"
							placeholder="<?php echo $has_token ? esc_attr__( '(token is set)', 'boxi-panelalpha' ) : ''; ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'Your PanelAlpha API authentication token.', 'boxi-panelalpha' ); ?>
							<?php if ( $has_token ) : ?>
								<br>
								<strong><?php esc_html_e( 'Leave blank to keep current token.', 'boxi-panelalpha' ); ?></strong>
							<?php endif; ?>
						</p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="button" id="test-connection" class="button">
					<?php esc_html_e( 'Test Connection', 'boxi-panelalpha' ); ?>
				</button>
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Credentials', 'boxi-panelalpha' ); ?>
				</button>
			</p>

			<div id="connection-status" class="notice" style="display: none;"></div>
		</form>
	</div>

	<!-- Product Mappings Tab -->
	<div id="mappings" class="tab-content">
		<p><?php esc_html_e( 'Map your WooCommerce products to PanelAlpha hosting plans.', 'boxi-panelalpha' ); ?></p>

		<!-- Add New Mapping Form -->
		<div class="boxi-mapping-form card">
			<h3><?php esc_html_e( 'Add New Mapping', 'boxi-panelalpha' ); ?></h3>
			<form id="boxi-add-mapping-form">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="product_id"><?php esc_html_e( 'WooCommerce Product', 'boxi-panelalpha' ); ?></label>
						</th>
						<td>
							<select id="product_id" name="product_id" class="regular-text" required>
								<option value=""><?php esc_html_e( 'Select a product...', 'boxi-panelalpha' ); ?></option>
								<?php foreach ( $products as $product ) : ?>
									<option value="<?php echo esc_attr( $product->get_id() ); ?>">
										<?php echo esc_html( $product->get_name() ); ?> (#<?php echo esc_html( $product->get_id() ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="plan_id"><?php esc_html_e( 'PanelAlpha Plan ID', 'boxi-panelalpha' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="plan_id"
								name="plan_id"
								class="regular-text"
								placeholder="e.g., plan_123abc"
								required
							/>
							<p class="description">
								<?php esc_html_e( 'The PanelAlpha plan ID from your PanelAlpha dashboard.', 'boxi-panelalpha' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="auto_provision"><?php esc_html_e( 'Auto-Provision', 'boxi-panelalpha' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" id="auto_provision" name="auto_provision" value="1" checked />
								<?php esc_html_e( 'Automatically provision hosting when order is completed', 'boxi-panelalpha' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Add Mapping', 'boxi-panelalpha' ); ?>
					</button>
				</p>
			</form>
		</div>

		<!-- Existing Mappings Table -->
		<div class="boxi-mappings-list">
			<h3><?php esc_html_e( 'Current Mappings', 'boxi-panelalpha' ); ?></h3>
			<?php if ( ! empty( $all_mappings ) ) : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Product', 'boxi-panelalpha' ); ?></th>
							<th><?php esc_html_e( 'PanelAlpha Plan ID', 'boxi-panelalpha' ); ?></th>
							<th><?php esc_html_e( 'Auto-Provision', 'boxi-panelalpha' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'boxi-panelalpha' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $all_mappings as $product_id => $mapping ) : ?>
							<?php
							$product = wc_get_product( $product_id );
							if ( ! $product ) {
								continue;
							}
							?>
							<tr>
								<td>
									<strong><?php echo esc_html( $product->get_name() ); ?></strong>
									<br>
									<small>#<?php echo esc_html( $product_id ); ?></small>
								</td>
								<td>
									<code><?php echo esc_html( $mapping['plan_id'] ); ?></code>
								</td>
								<td>
									<?php if ( $mapping['auto_provision'] ) : ?>
										<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
										<?php esc_html_e( 'Enabled', 'boxi-panelalpha' ); ?>
									<?php else : ?>
										<span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>
										<?php esc_html_e( 'Disabled', 'boxi-panelalpha' ); ?>
									<?php endif; ?>
								</td>
								<td>
									<button
										type="button"
										class="button button-small delete-mapping"
										data-product-id="<?php echo esc_attr( $product_id ); ?>"
									>
										<?php esc_html_e( 'Delete', 'boxi-panelalpha' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No product mappings configured yet.', 'boxi-panelalpha' ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<!-- General Settings Tab -->
	<div id="settings" class="tab-content">
		<form id="boxi-settings-form">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="provisioning_timeout"><?php esc_html_e( 'Provisioning Timeout', 'boxi-panelalpha' ); ?></label>
					</th>
					<td>
						<input
							type="number"
							id="provisioning_timeout"
							name="provisioning_timeout"
							value="<?php echo esc_attr( $config->get_setting( 'provisioning_timeout', 300 ) ); ?>"
							class="small-text"
							min="60"
							max="1800"
						/>
						<span><?php esc_html_e( 'seconds', 'boxi-panelalpha' ); ?></span>
						<p class="description">
							<?php esc_html_e( 'Maximum time to wait for provisioning to complete (60-1800 seconds).', 'boxi-panelalpha' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="debug_mode"><?php esc_html_e( 'Debug Mode', 'boxi-panelalpha' ); ?></label>
					</th>
					<td>
						<label>
							<input
								type="checkbox"
								id="debug_mode"
								name="debug_mode"
								value="1"
								<?php checked( $config->get_setting( 'debug_mode', false ) ); ?>
							/>
							<?php esc_html_e( 'Enable debug logging', 'boxi-panelalpha' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Log additional debug information (useful for troubleshooting).', 'boxi-panelalpha' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="log_retention_days"><?php esc_html_e( 'Log Retention', 'boxi-panelalpha' ); ?></label>
					</th>
					<td>
						<input
							type="number"
							id="log_retention_days"
							name="log_retention_days"
							value="<?php echo esc_attr( $config->get_setting( 'log_retention_days', 30 ) ); ?>"
							class="small-text"
							min="1"
							max="365"
						/>
						<span><?php esc_html_e( 'days', 'boxi-panelalpha' ); ?></span>
						<p class="description">
							<?php esc_html_e( 'Number of days to keep event logs before automatic cleanup.', 'boxi-panelalpha' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Settings', 'boxi-panelalpha' ); ?>
				</button>
			</p>
		</form>
	</div>
</div>
