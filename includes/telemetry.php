<?php
/**
 * Telemetry storage and aggregates.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Multch_Telemetry {

	const DB_VERSION = '1.1';

	/** @var string Uploads subdirectory for optional file logs (not the plugin folder). */
	const UPLOADS_SUBDIR = MULTCH_TEXT_DOMAIN;

	const LOG_FILENAME = 'telemetry.log';

	/**
	 * @return list<string>
	 */
	public static function failure_statuses(): array {
		return array( 'error', 'rate_limited', 'config_error', 'invalid_request' );
	}

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'multch_events';
	}

	public static function create_table(): void {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			session_hash varchar(64) NOT NULL DEFAULT '',
			provider varchar(32) NOT NULL DEFAULT '',
			model varchar(64) NOT NULL DEFAULT '',
			status varchar(32) NOT NULL DEFAULT '',
			latency_ms int(11) NOT NULL DEFAULT 0,
			error_code varchar(64) DEFAULT NULL,
			conversation_id bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY status (status),
			KEY provider (provider),
			KEY conversation_id (conversation_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'multch_plugin_telemetry_db_version', self::DB_VERSION );
	}

	public static function maybe_upgrade(): void {
		if ( self::DB_VERSION !== get_option( 'multch_plugin_telemetry_db_version', '' ) ) {
			self::create_table();
		}
	}

	public static function drop_table(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required on uninstall; table name from plugin helper via %i.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::table_name() ) );
	}

	public static function delete_plugin_transients(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required on uninstall; no WP API to delete transients by prefix.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_multch_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_multch_' ) . '%'
			)
		);
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array{where: list<string>, params: list<mixed>}
	 */
	private static function query_filters( array $args ): array {
		$where  = array( '1=1' );
		$params = array();

		$days = isset( $args['days'] ) ? (int) $args['days'] : 30;
		if ( $days > 0 ) {
			$where[]  = 'created_at >= %s';
			$params[] = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		}

		$provider = isset( $args['provider'] ) ? sanitize_key( (string) $args['provider'] ) : '';
		if ( '' !== $provider && 'all' !== $provider ) {
			$where[]  = 'provider = %s';
			$params[] = $provider;
		}

		$status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
		if ( '' !== $status && 'all' !== $status ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}

		$model = isset( $args['model'] ) ? sanitize_text_field( (string) $args['model'] ) : '';
		if ( '' !== $model && 'all' !== $model ) {
			$where[]  = 'model = %s';
			$params[] = $model;
		}

		$error_code = isset( $args['error_code'] ) ? sanitize_text_field( (string) $args['error_code'] ) : '';
		if ( '' !== $error_code && 'all' !== $error_code ) {
			$where[]  = 'error_code = %s';
			$params[] = $error_code;
		}

		$conversation_id = isset( $args['conversation_id'] ) ? (int) $args['conversation_id'] : 0;
		if ( $conversation_id > 0 ) {
			$where[]  = 'conversation_id = %d';
			$params[] = $conversation_id;
		}

		return array(
			'where'  => $where,
			'params' => $params,
		);
	}

	/**
	 * @param array<string, mixed> $event
	 */
	public static function record( array $event ): void {
		global $wpdb;

		$row = array(
			'created_at'      => current_time( 'mysql', true ),
			'session_hash'    => isset( $event['session_hash'] ) ? sanitize_text_field( (string) $event['session_hash'] ) : '',
			'provider'        => isset( $event['provider'] ) ? sanitize_text_field( (string) $event['provider'] ) : '',
			'model'           => isset( $event['model'] ) ? sanitize_text_field( (string) $event['model'] ) : '',
			'status'          => isset( $event['status'] ) ? sanitize_text_field( (string) $event['status'] ) : '',
			'latency_ms'      => isset( $event['latency_ms'] ) ? (int) $event['latency_ms'] : 0,
			'error_code'      => ! empty( $event['error_code'] ) ? sanitize_text_field( (string) $event['error_code'] ) : null,
			'conversation_id' => ! empty( $event['conversation_id'] ) ? (int) $event['conversation_id'] : null,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom telemetry table; no WP API for plugin event storage.
		$wpdb->insert(
			self::table_name(),
			$row,
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d' )
		);

		self::maybe_append_file_log( $row );
	}

	/**
	 * @param array<string, mixed> $row
	 */
	/**
	 * Whether optional JSONL file logging is enabled (settings or wp-config constant).
	 */
	public static function is_file_log_enabled(): bool {
		$settings = Multch_Plugin::get_settings();
		return ! empty( $settings['telemetry_file_log'] );
	}

	/**
	 * Absolute path to the optional telemetry log inside wp-content/uploads.
	 */
	public static function get_file_log_path(): string {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return '';
		}

		return trailingslashit( $upload['basedir'] ) . self::UPLOADS_SUBDIR . '/' . self::LOG_FILENAME;
	}

	/**
	 * Ensure uploads/multiai-chatbot exists with basic hardening (index.php, .htaccess).
	 */
	public static function ensure_upload_log_directory(): bool {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return false;
		}

		$dir = trailingslashit( $upload['basedir'] ) . self::UPLOADS_SUBDIR;
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$index = $dir . '/index.php';
		if ( ! is_file( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}

		$htaccess = $dir . '/.htaccess';
		if ( ! is_file( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess, "deny from all\n" );
		}

		return true;
	}

	/**
	 * Remove optional log files created under uploads on uninstall.
	 */
	public static function delete_upload_log_files(): void {
		$path = self::get_file_log_path();
		if ( '' !== $path && is_file( $path ) ) {
			wp_delete_file( $path );
		}

		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return;
		}

		$dir = trailingslashit( $upload['basedir'] ) . self::UPLOADS_SUBDIR;
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$index = $dir . '/index.php';
		if ( is_file( $index ) ) {
			wp_delete_file( $index );
		}

		$htaccess = $dir . '/.htaccess';
		if ( is_file( $htaccess ) ) {
			wp_delete_file( $htaccess );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		@rmdir( $dir );
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private static function maybe_append_file_log( array $row ): void {
		if ( ! self::is_file_log_enabled() ) {
			return;
		}

		if ( ! self::ensure_upload_log_directory() ) {
			return;
		}

		$path = self::get_file_log_path();
		if ( '' === $path ) {
			return;
		}

		$line = wp_json_encode(
			array(
				'ts'              => gmdate( 'c' ),
				'session_hash'    => $row['session_hash'] ?? '',
				'provider'        => $row['provider'] ?? '',
				'model'           => $row['model'] ?? '',
				'status'          => $row['status'] ?? '',
				'latency_ms'      => $row['latency_ms'] ?? 0,
				'error_code'      => $row['error_code'] ?? '',
				'conversation_id' => $row['conversation_id'] ?? 0,
			)
		);

		if ( ! is_string( $line ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $path, $line . "\n", FILE_APPEND | LOCK_EX );
	}

	public static function hash_session( string $session_id ): string {
		$session_id = substr( sanitize_text_field( $session_id ), 0, 120 );
		if ( '' === $session_id ) {
			$session_id = 'anonymous-session';
		}
		return hash_hmac( 'sha256', $session_id, wp_salt( 'auth' ) );
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	public static function get_summary( array $args = array() ): array {
		global $wpdb;

		$table   = self::table_name();
		$filters = self::query_filters( $args );
		$where   = implode( ' AND ', $filters['where'] );
		$fail    = self::failure_statuses();
		$fail_in = "'" . implode( "','", array_map( 'esc_sql', $fail ) ) . "'";

		$sql_totals = "SELECT
			COUNT(*) AS total_requests,
			SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) AS success_count,
			SUM(CASE WHEN status = 'cached' THEN 1 ELSE 0 END) AS cached_count,
			SUM(CASE WHEN status IN ({$fail_in}) THEN 1 ELSE 0 END) AS error_count,
			AVG(latency_ms) AS avg_latency_ms,
			MIN(latency_ms) AS min_latency_ms,
			MAX(latency_ms) AS max_latency_ms
			FROM {$table} WHERE {$where}";

		if ( empty( $filters['params'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters., PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters.
			$totals = $wpdb->get_row( $sql_totals, ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters., PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters.
			$totals = $wpdb->get_row( $wpdb->prepare( $sql_totals, $filters['params'] ), ARRAY_A );
		}

		$totals = $totals ?: array();
		$total  = (int) ( $totals['total_requests'] ?? 0 );
		$totals['success_rate'] = $total > 0
			? round( ( (int) ( $totals['success_count'] ?? 0 ) / $total ) * 100, 1 )
			: 0.0;
		$totals['p95_latency_ms'] = self::get_p95_latency( $args );

		$aggregates = array(
			'by_status'   => "SELECT status, COUNT(*) AS count FROM {$table} WHERE {$where} GROUP BY status ORDER BY count DESC",
			'by_provider' => "SELECT provider, COUNT(*) AS count FROM {$table} WHERE {$where} GROUP BY provider ORDER BY count DESC",
			'by_model'    => "SELECT model, COUNT(*) AS count, AVG(latency_ms) AS avg_latency_ms FROM {$table} WHERE {$where} AND model != '' GROUP BY model ORDER BY count DESC LIMIT 20",
			'by_error'    => "SELECT error_code, COUNT(*) AS count FROM {$table} WHERE {$where} AND error_code IS NOT NULL AND error_code != '' GROUP BY error_code ORDER BY count DESC LIMIT 20",
		);

		$result = array(
			'days'        => (int) ( $args['days'] ?? 30 ),
			'totals'      => $totals,
			'by_status'   => array(),
			'by_provider' => array(),
			'by_model'    => array(),
			'by_error'    => array(),
		);

		foreach ( $aggregates as $key => $sql ) {
			if ( empty( $filters['params'] ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters., PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters., PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters.
				$result[ $key ] = $wpdb->get_results( $sql, ARRAY_A ) ?: array();
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters., PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters., PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters.
				$result[ $key ] = $wpdb->get_results( $wpdb->prepare( $sql, $filters['params'] ), ARRAY_A ) ?: array();
			}
		}

		return $result;
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private static function get_p95_latency( array $args ): int {
		global $wpdb;

		$table   = self::table_name();
		$filters = self::query_filters( $args );
		$where   = implode( ' AND ', $filters['where'] );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		if ( empty( $filters['params'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters., PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters.
			$count = (int) $wpdb->get_var( $count_sql );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters., PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters.
			$count = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $filters['params'] ) );
		}

		if ( $count <= 0 ) {
			return 0;
		}

		$offset = max( 0, (int) floor( $count * 0.95 ) - 1 );
		$sql    = "SELECT latency_ms FROM {$table} WHERE {$where} ORDER BY latency_ms ASC LIMIT 1 OFFSET %d";
		$params = array_merge( $filters['params'], array( $offset ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_daily_series( array $args = array() ): array {
		global $wpdb;

		$table   = self::table_name();
		$filters = self::query_filters( $args );
		$where   = implode( ' AND ', $filters['where'] );
		$fail    = self::failure_statuses();
		$fail_in = "'" . implode( "','", array_map( 'esc_sql', $fail ) ) . "'";

		$sql = "SELECT
			DATE(created_at) AS day,
			COUNT(*) AS total,
			SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) AS success_count,
			SUM(CASE WHEN status IN ({$fail_in}) THEN 1 ELSE 0 END) AS error_count
			FROM {$table}
			WHERE {$where}
			GROUP BY DATE(created_at)
			ORDER BY day DESC
			LIMIT 30";

		if ( empty( $filters['params'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters., PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters.
			$rows = $wpdb->get_results( $sql, ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters., PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters.
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $filters['params'] ), ARRAY_A );
		}

		return $rows ?: array();
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_events( array $args = array() ): array {
		global $wpdb;

		$table   = self::table_name();
		$filters = self::query_filters( $args );
		$where   = implode( ' AND ', $filters['where'] );
		$per     = max( 1, min( 200, (int) ( $args['per_page'] ?? 25 ) ) );
		$offset  = max( 0, (int) ( $args['offset'] ?? 0 ) );

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$params = array_merge( $filters['params'], array( $per, $offset ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		return $rows ?: array();
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public static function count_events( array $args = array() ): int {
		global $wpdb;

		$table   = self::table_name();
		$filters = self::query_filters( $args );
		$sql     = 'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . implode( ' AND ', $filters['where'] );

		if ( empty( $filters['params'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters., PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters.
			return (int) $wpdb->get_var( $sql );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $filters['params'] ) );
	}

	/**
	 * @param array<string, mixed> $args
	 * @return list<string>
	 */
	public static function get_distinct_models( array $args = array() ): array {
		global $wpdb;

		$table   = self::table_name();
		$filters = self::query_filters( array_merge( $args, array( 'model' => 'all' ) ) );
		$sql     = 'SELECT DISTINCT model FROM ' . $table . ' WHERE ' . implode( ' AND ', $filters['where'] ) . " AND model != '' ORDER BY model ASC LIMIT 50";

		if ( empty( $filters['params'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters., PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters.
			$rows = $wpdb->get_col( $sql );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters., PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters.
			$rows = $wpdb->get_col( $wpdb->prepare( $sql, $filters['params'] ) );
		}

		return array_values( array_filter( array_map( 'strval', $rows ?: array() ) ) );
	}

	/**
	 * @param array<string, mixed> $args
	 * @return list<string>
	 */
	public static function get_distinct_error_codes( array $args = array() ): array {
		global $wpdb;

		$table   = self::table_name();
		$filters = self::query_filters( array_merge( $args, array( 'error_code' => 'all' ) ) );
		$sql     = 'SELECT DISTINCT error_code FROM ' . $table . ' WHERE ' . implode( ' AND ', $filters['where'] ) . " AND error_code IS NOT NULL AND error_code != '' ORDER BY error_code ASC LIMIT 50";

		if ( empty( $filters['params'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters., PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters.
			$rows = $wpdb->get_col( $sql );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters., PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic telemetry query; table/where from plugin helpers and whitelisted filters.
			$rows = $wpdb->get_col( $wpdb->prepare( $sql, $filters['params'] ) );
		}

		return array_values( array_filter( array_map( 'strval', $rows ?: array() ) ) );
	}

	/**
	 * Backward-compatible wrapper.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_recent_events( int $limit = 50, int $offset = 0, array $args = array() ): array {
		return self::list_events(
			array_merge(
				$args,
				array(
					'per_page' => $limit,
					'offset'   => $offset,
				)
			)
		);
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public static function export_csv( array $args = array() ): string {
		$rows = self::list_events(
			array_merge(
				$args,
				array(
					'per_page' => 10000,
					'offset'   => 0,
				)
			)
		);

		$lines = array( 'created_at,session_hash,provider,model,status,latency_ms,error_code,conversation_id' );

		foreach ( $rows as $row ) {
			$lines[] = implode(
				',',
				array_map(
					static function ( $value ) {
						$value = (string) $value;
						if ( str_contains( $value, ',' ) || str_contains( $value, '"' ) ) {
							return '"' . str_replace( '"', '""', $value ) . '"';
						}
						return $value;
					},
					array(
						$row['created_at'] ?? '',
						$row['session_hash'] ?? '',
						$row['provider'] ?? '',
						$row['model'] ?? '',
						$row['status'] ?? '',
						(string) ( $row['latency_ms'] ?? 0 ),
						$row['error_code'] ?? '',
						(string) ( $row['conversation_id'] ?? '' ),
					)
				)
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_events_by_conversation( int $conversation_id, int $limit = 50 ): array {
		if ( $conversation_id <= 0 ) {
			return array();
		}

		return self::list_events(
			array(
				'days'            => 0,
				'conversation_id' => $conversation_id,
				'per_page'        => $limit,
				'offset'          => 0,
			)
		);
	}

	/**
	 * @return array{deleted_events: int}
	 */
	public static function purge_older_than_days( int $days ): array {
		if ( $days <= 0 ) {
			return array( 'deleted_events' => 0 );
		}

		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table purge; table name via %i placeholder.
		$deleted = (int) $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE created_at < %s',
				self::table_name(),
				$cutoff
			)
		);

		return array( 'deleted_events' => $deleted );
	}

	public static function run_retention_purge(): void {
		$settings = Multch_Plugin::get_settings();
		$days     = isset( $settings['telemetry_retention_days'] ) ? (int) $settings['telemetry_retention_days'] : 0;
		if ( $days <= 0 ) {
			return;
		}
		self::purge_older_than_days( $days );
	}
}
