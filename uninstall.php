<?php
/**
 * Uninstall script
 *
 * Fired when the plugin is uninstalled via WordPress admin.
 * Cleans up all plugin data from the database.
 */

// Exit if accessed directly or not uninstalling
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options
delete_option( 'boxi_panelalpha_version' );
delete_option( 'boxi_panelalpha_settings' );
delete_option( 'boxi_panelalpha_api_url' );
delete_option( 'boxi_panelalpha_api_token_encrypted' );
delete_option( 'boxi_panelalpha_product_mappings' );

// Delete all user meta related to PanelAlpha user IDs
delete_metadata( 'user', 0, '_panelalpha_user_id', '', true );

// Delete all order meta related to PanelAlpha
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_panelalpha_%'" );

// Drop custom database table
$table_name = $wpdb->prefix . 'boxi_integration_logs';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

// Clear any cached data
wp_cache_flush();

// Note: We intentionally do NOT cancel services in PanelAlpha during uninstall
// This prevents accidental service termination if the plugin is temporarily removed
