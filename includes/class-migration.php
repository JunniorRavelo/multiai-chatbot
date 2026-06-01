<?php
/**
 * Migrate legacy chatbot_* identifiers to multch_*.
 *
 * @package Multch_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Multch_Migration {

	const LEGACY_OPTION_SETTINGS = 'multch_plugin_settings';
	const LEGACY_OPTION_HISTORY  = 'multch_plugin_history_db_version';
	const LEGACY_OPTION_TELEMETRY = 'multch_plugin_telemetry_db_version';
	const LEGACY_OPTION_DB       = 'multch_plugin_db_version';

	const CRON_HISTORY   = 'multch_purge_history';
	const CRON_TELEMETRY = 'multch_purge_telemetry';

	/**
	 * Run once per site when legacy data exists.
	 */
	public static function maybe_migrate(): void {
		$done = get_option( 'multch_legacy_migration_done', '' );
		if ( '1' === $done ) {
			return;
		}

		self::migrate_options();
		self::migrate_tables();
		self::migrate_cron();
		self::migrate_ai_providers();
		self::migrate_telemetry_file_log();
		self::delete_legacy_transients();

		update_option( 'multch_legacy_migration_done', '1', false );
	}

	private static function migrate_options(): void {
		$map = array(
			self::LEGACY_OPTION_SETTINGS  => Multch_Admin_Settings::OPTION_KEY,
			self::LEGACY_OPTION_HISTORY   => 'multch_plugin_history_db_version',
			self::LEGACY_OPTION_TELEMETRY => 'multch_plugin_telemetry_db_version',
			self::LEGACY_OPTION_DB        => 'multch_plugin_db_version',
		);

		foreach ( $map as $legacy => $new ) {
			$value = get_option( $legacy, null );
			if ( null === $value ) {
				continue;
			}
			if ( false === get_option( $new, false ) ) {
				add_option( $new, $value, '', false );
			}
			delete_option( $legacy );
		}
	}

	private static function migrate_tables(): void {
		global $wpdb;

		$renames = array(
			$wpdb->prefix . 'multch_events'         => Multch_Telemetry::table_name(),
			$wpdb->prefix . 'multch_conversations'  => Multch_Chat_History::conversations_table(),
			$wpdb->prefix . 'multch_messages'       => Multch_Chat_History::messages_table(),
		);

		foreach ( $renames as $old => $new ) {
			if ( $old === $new ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-time rename; table names from fixed plugin suffixes.
			$exists_old = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$exists_new = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new ) );
			if ( $exists_old === $old && $exists_new !== $new ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->query( "RENAME TABLE `{$old}` TO `{$new}`" );
			}
		}
	}

	/**
	 * Map legacy direct cloud providers to the WordPress AI Client.
	 */
	private static function migrate_ai_providers(): void {
		$done = get_option( 'multch_ai_client_migration_done', '' );
		if ( '1' === $done ) {
			return;
		}

		$stored = get_option( Multch_Admin_Settings::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			update_option( 'multch_ai_client_migration_done', '1', false );
			return;
		}

		$provider = (string) ( $stored['provider'] ?? '' );
		if ( in_array( $provider, multch_legacy_cloud_provider_ids(), true ) ) {
			$stored['provider'] = 'wordpress_ai';
		}

		unset( $stored['api_key'], $stored['openai_base_url'], $stored['deepseek_base_url'] );

		update_option( Multch_Admin_Settings::OPTION_KEY, wp_parse_args( $stored, Multch_Admin_Settings::default_settings() ), false );
		Multch_Plugin::clear_settings_cache();
		update_option( 'multch_ai_client_migration_done', '1', false );
	}

	/**
	 * Replace legacy arbitrary telemetry_log_path with uploads-only file log flag.
	 */
	private static function migrate_telemetry_file_log(): void {
		if ( '1' === get_option( 'multch_telemetry_file_log_migration_done', '' ) ) {
			return;
		}

		$stored = get_option( Multch_Admin_Settings::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			update_option( 'multch_telemetry_file_log_migration_done', '1', false );
			return;
		}

		if ( array_key_exists( 'telemetry_log_path', $stored ) ) {
			if ( ! empty( $stored['telemetry_log_path'] ) ) {
				$stored['telemetry_file_log'] = true;
			}
			unset( $stored['telemetry_log_path'] );
			update_option(
				Multch_Admin_Settings::OPTION_KEY,
				wp_parse_args( $stored, Multch_Admin_Settings::default_settings() ),
				false
			);
		}

		update_option( 'multch_telemetry_file_log_migration_done', '1', false );
	}

	private static function migrate_cron(): void {
		$map = array(
			self::CRON_HISTORY   => 'multch_purge_history',
			self::CRON_TELEMETRY => 'multch_purge_telemetry',
		);

		foreach ( $map as $legacy => $new ) {
			$timestamp = wp_next_scheduled( $legacy );
			while ( $timestamp ) {
				wp_unschedule_event( $timestamp, $legacy );
				$timestamp = wp_next_scheduled( $legacy );
			}
			if ( ! wp_next_scheduled( $new ) ) {
				wp_schedule_event( time(), 'daily', $new );
			}
		}
	}

	private static function delete_legacy_transients(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_multch_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_multch_' ) . '%',
				$wpdb->esc_like( '_transient_multch-plugin' ) . '%',
				$wpdb->esc_like( '_transient_timeout_multch-plugin' ) . '%'
			)
		);
	}
}
