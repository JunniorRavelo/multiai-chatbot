<?php
/**
 * Uninstall MultiAI ChatBot.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/telemetry.php';
require_once __DIR__ . '/includes/chat-history.php';

wp_clear_scheduled_hook( 'chatbot_purge_history' );
wp_clear_scheduled_hook( 'chatbot_purge_telemetry' );

Chatbot_Telemetry::drop_table();
Chatbot_Chat_History::drop_tables();
Chatbot_Telemetry::delete_plugin_transients();

delete_option( 'chatbot_plugin_settings' );
delete_option( 'chatbot_plugin_db_version' );
delete_option( 'chatbot_plugin_telemetry_db_version' );
delete_option( 'chatbot_plugin_history_db_version' );

flush_rewrite_rules( false );
