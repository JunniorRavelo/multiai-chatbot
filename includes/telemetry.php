<?php
/**
 * Telemetry storage and aggregates.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chatbot_Telemetry {

	const DB_VERSION = '1.0';

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'chatbot_events';
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
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY status (status),
			KEY provider (provider)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'chatbot_plugin_db_version', self::DB_VERSION );
	}

	/**
	 * @param array<string, mixed> $event
	 */
	public static function record( array $event ): void {
		global $wpdb;

		$wpdb->insert(
			self::table_name(),
			array(
				'created_at'   => current_time( 'mysql', true ),
				'session_hash' => isset( $event['session_hash'] ) ? sanitize_text_field( (string) $event['session_hash'] ) : '',
				'provider'     => isset( $event['provider'] ) ? sanitize_text_field( (string) $event['provider'] ) : '',
				'model'        => isset( $event['model'] ) ? sanitize_text_field( (string) $event['model'] ) : '',
				'status'       => isset( $event['status'] ) ? sanitize_text_field( (string) $event['status'] ) : '',
				'latency_ms'   => isset( $event['latency_ms'] ) ? (int) $event['latency_ms'] : 0,
				'error_code'   => ! empty( $event['error_code'] ) ? sanitize_text_field( (string) $event['error_code'] ) : null,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	public static function hash_session( string $session_id ): string {
		$session_id = substr( sanitize_text_field( $session_id ), 0, 120 );
		if ( '' === $session_id ) {
			$session_id = 'anonymous-session';
		}
		return hash_hmac( 'sha256', $session_id, wp_salt( 'auth' ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_summary( int $days = 30 ): array {
		global $wpdb;

		$table = self::table_name();
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) AS total_requests,
					SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) AS success_count,
					SUM(CASE WHEN status != 'success' THEN 1 ELSE 0 END) AS error_count,
					AVG(latency_ms) AS avg_latency_ms
				FROM {$table}
				WHERE created_at >= %s",
				$since
			),
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$by_status = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS count FROM {$table} WHERE created_at >= %s GROUP BY status ORDER BY count DESC",
				$since
			),
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$by_provider = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT provider, COUNT(*) AS count FROM {$table} WHERE created_at >= %s GROUP BY provider ORDER BY count DESC",
				$since
			),
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$by_model = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT model, COUNT(*) AS count FROM {$table} WHERE created_at >= %s GROUP BY model ORDER BY count DESC LIMIT 20",
				$since
			),
			ARRAY_A
		);

		return array(
			'days'         => $days,
			'totals'       => $totals ?: array(),
			'by_status'    => $by_status ?: array(),
			'by_provider'  => $by_provider ?: array(),
			'by_model'     => $by_model ?: array(),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_recent_events( int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		$table = self::table_name();
		$limit = max( 1, min( 200, $limit ) );
		$offset = max( 0, $offset );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	public static function count_events(): int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * @return string CSV content.
	 */
	public static function export_csv( int $days = 30 ): string {
		global $wpdb;

		$table = self::table_name();
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT created_at, session_hash, provider, model, status, latency_ms, error_code FROM {$table} WHERE created_at >= %s ORDER BY created_at DESC",
				$since
			),
			ARRAY_A
		);

		$lines   = array( 'created_at,session_hash,provider,model,status,latency_ms,error_code' );
		$rows    = $rows ?: array();

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
					)
				)
			);
		}

		return implode( "\n", $lines );
	}
}
