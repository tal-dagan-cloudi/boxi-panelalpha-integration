<?php
/**
 * Plugin Name: Boxi PanelAlpha Integration
 * Plugin URI: https://boxi.co.il
 * Description: Automates WordPress hosting provisioning via PanelAlpha when WooCommerce orders are completed.
 * Version: 1.0.0
 * Author: Boxi Team
 * Author URI: https://boxi.co.il
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * Text Domain: boxi-panelalpha
 * Domain Path: /languages
 * License: Proprietary
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' )) {
	exit;
}

// Define plugin constants
define( 'BOXI_PANELALPHA_VERSION', '1.0.0' );
define( 'BOXI_PANELALPHA_PLUGIN_FILE', __FILE__ );
define( 'BOXI_PANELALPHA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BOXI_PANELALPHA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BOXI_PANELALPHA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if WooCommerce is active
 */
function boxi_panelalpha_check_woocommerce() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function() {
			?>
			<div class="notice notice-error">
				<p><strong>Boxi PanelAlpha Integration</strong> requires WooCommerce to be installed and active.</p>
			</div>
			<?php
		});
		return false;
	}
	return true;
}

/**
 * Initialize the plugin
 */
function boxi_panelalpha_init() {
	// Check for WooCommerce
	if ( ! boxi_panelalpha_check_woocommerce() ) {
		return;
	}

	// Load plugin text domain for translations
	load_plugin_textdomain( 'boxi-panelalpha', false, dirname( BOXI_PANELALPHA_PLUGIN_BASENAME ) . '/languages' );

	// Require core classes
	require_once BOXI_PANELALPHA_PLUGIN_DIR . 'includes/class-config-manager.php';
	require_once BOXI_PANELALPHA_PLUGIN_DIR . 'includes/class-integration-logger.php';
	require_once BOXI_PANELALPHA_PLUGIN_DIR . 'includes/class-panelalpha-client.php';
	require_once BOXI_PANELALPHA_PLUGIN_DIR . 'includes/class-rate-limiter.php';
	require_once BOXI_PANELALPHA_PLUGIN_DIR . 'includes/class-queue-manager.php';
	require_once BOXI_PANELALPHA_PLUGIN_DIR . 'includes/class-provisioning-orchestrator.php';
	require_once BOXI_PANELALPHA_PLUGIN_DIR . 'includes/class-event-listener.php';
	require_once BOXI_PANELALPHA_PLUGIN_DIR . 'includes/class-customer-sync.php';

	// Require admin classes
	if ( is_admin() ) {
		require_once BOXI_PANELALPHA_PLUGIN_DIR . 'admin/class-admin-ui.php';
	}

	// Initialize the main plugin class
	require_once BOXI_PANELALPHA_PLUGIN_DIR . 'includes/class-boxi-panelalpha.php';
	Boxi_PanelAlpha::get_instance();
}
add_action( 'plugins_loaded', 'boxi_panelalpha_init' );

/**
 * Plugin activation hook
 */
function boxi_panelalpha_activate() {
	// Check WordPress version
	if ( version_compare( get_bloginfo( 'version' ), '5.8', '<' ) ) {
		wp_die( __( 'Boxi PanelAlpha Integration requires WordPress 5.8 or higher.', 'boxi-panelalpha' ) );
	}

	// Check PHP version
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		wp_die( __( 'Boxi PanelAlpha Integration requires PHP 7.4 or higher.', 'boxi-panelalpha' ) );
	}

	// Check for WooCommerce
	if ( ! class_exists( 'WooCommerce' ) ) {
		wp_die( __( 'Boxi PanelAlpha Integration requires WooCommerce to be installed and active.', 'boxi-panelalpha' ) );
	}

	// Set default options
	add_option( 'boxi_panelalpha_version', BOXI_PANELALPHA_VERSION );
	add_option( 'boxi_panelalpha_settings', array(
		'rate_limit' => 55,
		'provisioning_timeout' => 300, // 5 minutes in seconds
		'retry_attempts' => 5,
		'log_retention_days' => 90,
	));
	add_option( 'boxi_panelalpha_product_mappings', array() );

	// Create custom database table for logs (optional - can use post type instead)
	global $wpdb;
	$table_name = $wpdb->prefix . 'boxi_integration_logs';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		level varchar(10) NOT NULL,
		event_type varchar(50) NOT NULL,
		order_id bigint(20) DEFAULT NULL,
		customer_email varchar(255) DEFAULT NULL,
		status varchar(20) NOT NULL,
		message text NOT NULL,
		context longtext DEFAULT NULL,
		PRIMARY KEY  (id),
		KEY timestamp (timestamp),
		KEY level (level),
		KEY event_type (event_type),
		KEY order_id (order_id),
		KEY status (status)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	// Schedule cleanup cron job (runs daily)
	if ( ! wp_next_scheduled( 'boxi_panelalpha_cleanup_logs' ) ) {
		wp_schedule_event( time(), 'daily', 'boxi_panelalpha_cleanup_logs' );
	}

	// Flush rewrite rules
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'boxi_panelalpha_activate' );

/**
 * Plugin deactivation hook
 */
function boxi_panelalpha_deactivate() {
	// Clear scheduled cron jobs
	wp_clear_scheduled_hook( 'boxi_panelalpha_cleanup_logs' );

	// Note: We don't delete data on deactivation, only on uninstall
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'boxi_panelalpha_deactivate' );

/**
 * Plugin uninstall hook (in separate uninstall.php file)
 * See uninstall.php for cleanup logic
 */

/**
 * Add settings link to plugins page
 */
function boxi_panelalpha_add_settings_link( $links ) {
	$settings_link = '<a href="' . admin_url( 'admin.php?page=boxi-integration' ) . '">' . __( 'Settings', 'boxi-panelalpha' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . BOXI_PANELALPHA_PLUGIN_BASENAME, 'boxi_panelalpha_add_settings_link' );

/**
 * Cleanup old logs (cron job callback)
 */
function boxi_panelalpha_cleanup_old_logs() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'boxi_integration_logs';

	$settings = get_option( 'boxi_panelalpha_settings', array() );
	$retention_days = isset( $settings['log_retention_days'] ) ? (int) $settings['log_retention_days'] : 90;

	$wpdb->query( $wpdb->prepare(
		"DELETE FROM $table_name WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
		$retention_days
	));
}
add_action( 'boxi_panelalpha_cleanup_logs', 'boxi_panelalpha_cleanup_old_logs' );
