<?php
/**
 * Uninstall Chatbot Plugin WP.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table = $wpdb->prefix . 'chatbot_events';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

delete_option( 'chatbot_plugin_settings' );
delete_option( 'chatbot_plugin_db_version' );
