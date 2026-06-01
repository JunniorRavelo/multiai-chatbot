<?php
/**
 * Uninstall MultiAI ChatBot.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Required before telemetry.php (class constant UPLOADS_SUBDIR); main plugin file is not loaded on uninstall.
if ( ! defined( 'MULTCH_TEXT_DOMAIN' ) ) {
	define( 'MULTCH_TEXT_DOMAIN', 'multiai-chatbot' );
}

require_once __DIR__ . '/includes/config-constants.php';
require_once __DIR__ . '/includes/telemetry.php';
require_once __DIR__ . '/includes/chat-history.php';

wp_clear_scheduled_hook( 'multch_purge_history' );
wp_clear_scheduled_hook( 'multch_purge_telemetry' );
wp_clear_scheduled_hook( 'chatbot_purge_history' );
wp_clear_scheduled_hook( 'chatbot_purge_telemetry' );

Multch_Telemetry::drop_table();
Multch_Chat_History::drop_tables();
Multch_Telemetry::delete_plugin_transients();
Multch_Telemetry::delete_upload_log_files();

$options = array(
	'multch_plugin_settings',
	'multch_plugin_db_version',
	'multch_plugin_telemetry_db_version',
	'multch_plugin_history_db_version',
	'multch_legacy_migration_done',
	'chatbot_plugin_settings',
	'chatbot_plugin_db_version',
	'chatbot_plugin_telemetry_db_version',
	'chatbot_plugin_history_db_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

global $wpdb;

$legacy_tables = array(
	$wpdb->prefix . 'chatbot_events',
	$wpdb->prefix . 'chatbot_conversations',
	$wpdb->prefix . 'chatbot_messages',
);

foreach ( $legacy_tables as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_chatbot_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_chatbot_' ) . '%'
	)
);

flush_rewrite_rules( false );
