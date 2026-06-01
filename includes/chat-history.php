<?php
/**
 * Chat conversation history storage.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Multch_Chat_History {

	const DB_VERSION = '1.0';

	const IDLE_MINUTES = 30;

	/**
	 * Canonical conversations table name (prefix + fixed suffix only).
	 */
	public static function conversations_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'multch_conversations';
	}

	/**
	 * Canonical messages table name (prefix + fixed suffix only).
	 */
	public static function messages_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'multch_messages';
	}

	public static function create_tables(): void {
		global $wpdb;

		$conversations = self::conversations_table();
		$messages      = self::messages_table();
		$charset       = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$conversations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			public_id varchar(32) NOT NULL,
			session_hash varchar(64) NOT NULL DEFAULT '',
			title varchar(200) NOT NULL DEFAULT '',
			provider varchar(32) NOT NULL DEFAULT '',
			model varchar(64) NOT NULL DEFAULT '',
			status varchar(32) NOT NULL DEFAULT 'active',
			message_count int(11) NOT NULL DEFAULT 0,
			page_url varchar(500) DEFAULT NULL,
			page_path varchar(255) DEFAULT NULL,
			started_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY public_id (public_id),
			KEY session_hash (session_hash),
			KEY updated_at (updated_at),
			KEY status (status),
			KEY provider (provider)
		) {$charset};

		CREATE TABLE {$messages} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			role varchar(16) NOT NULL DEFAULT 'user',
			content text NOT NULL,
			status varchar(32) NOT NULL DEFAULT '',
			latency_ms int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'multch_plugin_history_db_version', self::DB_VERSION );
	}

	public static function maybe_upgrade(): void {
		if ( self::DB_VERSION !== get_option( 'multch_plugin_history_db_version', '' ) ) {
			self::create_tables();
		}
	}

	public static function generate_public_id(): string {
		$base = 'MCH-' . wp_date( 'Y-m-d-H-i-s' );
		if ( ! self::public_id_exists( $base ) ) {
			return $base;
		}

		for ( $i = 2; $i <= 99; $i++ ) {
			$candidate = $base . '-' . $i;
			if ( ! self::public_id_exists( $candidate ) ) {
				return $candidate;
			}
		}

		return $base . '-' . wp_generate_password( 4, false, false );
	}

	private static function public_id_exists( string $public_id ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup; table name via %i placeholder.
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE public_id = %s LIMIT 1',
				self::conversations_table(),
				$public_id
			)
		);
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return array{id: int, public_id: string}
	 */
	public static function resolve_conversation( string $session_hash, string $client_ref, array $meta = array() ): array {
		global $wpdb;

		$table = self::conversations_table();
		$now   = current_time( 'mysql', true );

		if ( '' !== $client_ref ) {
			$row = self::find_by_client_ref( $client_ref, $session_hash );
			if ( $row ) {
				return array(
					'id'        => (int) $row['id'],
					'public_id' => (string) $row['public_id'],
				);
			}
		}

		if ( '' !== $session_hash ) {
			$idle_since = gmdate( 'Y-m-d H:i:s', strtotime( '-' . self::IDLE_MINUTES . ' minutes' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup; table name via %i placeholder.
			$active = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, public_id FROM %i
					WHERE session_hash = %s AND status = 'active' AND updated_at >= %s
					ORDER BY updated_at DESC LIMIT 1",
					self::conversations_table(),
					$session_hash,
					$idle_since
				),
				ARRAY_A
			);
			if ( $active ) {
				return array(
					'id'        => (int) $active['id'],
					'public_id' => (string) $active['public_id'],
				);
			}
		}

		$public_id = self::generate_public_id();
		$title     = isset( $meta['title'] ) ? self::truncate_title( (string) $meta['title'] ) : '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom conversations table; no WP API for plugin chat history.
		$wpdb->insert(
			$table,
			array(
				'public_id'     => $public_id,
				'session_hash'  => $session_hash,
				'title'         => $title,
				'provider'      => isset( $meta['provider'] ) ? sanitize_text_field( (string) $meta['provider'] ) : '',
				'model'         => '',
				'status'        => 'active',
				'message_count' => 0,
				'page_url'      => isset( $meta['page_url'] ) ? self::sanitize_url_field( (string) $meta['page_url'] ) : null,
				'page_path'     => isset( $meta['page_path'] ) ? self::sanitize_path_field( (string) $meta['page_path'] ) : null,
				'started_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		return array(
			'id'        => (int) $wpdb->insert_id,
			'public_id' => $public_id,
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function find_by_client_ref( string $client_ref, string $session_hash ) {
		global $wpdb;

		$ref = sanitize_text_field( $client_ref );

		if ( preg_match( '/^\d+$/', $ref ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup; table name via %i placeholder.
			return $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE id = %d LIMIT 1',
					self::conversations_table(),
					(int) $ref
				),
				ARRAY_A
			) ?: null;
		}

		if ( preg_match( '/^(?:MCH|CB)-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}/', $ref ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup; table name via %i placeholder.
			return $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE public_id = %s LIMIT 1',
					self::conversations_table(),
					$ref
				),
				ARRAY_A
			) ?: null;
		}

		return null;
	}

	public static function add_message(
		int $conversation_id,
		string $role,
		string $content,
		array $extra = array()
	): void {
		global $wpdb;

		if ( $conversation_id <= 0 ) {
			return;
		}

		$content = wp_strip_all_tags( $content );
		if ( '' === trim( $content ) ) {
			return;
		}

		$role = 'assistant' === $role ? 'assistant' : 'user';
		$now  = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom messages table; no WP API for plugin chat history.
		$wpdb->insert(
			self::messages_table(),
			array(
				'conversation_id' => $conversation_id,
				'role'            => $role,
				'content'         => $content,
				'status'          => isset( $extra['status'] ) ? sanitize_text_field( (string) $extra['status'] ) : '',
				'latency_ms'      => isset( $extra['latency_ms'] ) ? (int) $extra['latency_ms'] : 0,
				'created_at'      => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		$conv_table = self::conversations_table();
		$updates    = array(
			'updated_at' => $now,
		);
		$formats    = array( '%s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name from plugin helper via %i placeholder.
		$updates['message_count'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE conversation_id = %d',
				self::messages_table(),
				$conversation_id
			)
		);
		$formats[] = '%d';

		if ( 'user' === $role ) {
			$title = self::truncate_title( $content );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name from plugin helper via %i placeholder.
			$current_title = (string) $wpdb->get_var(
				$wpdb->prepare( 'SELECT title FROM %i WHERE id = %d', self::conversations_table(), $conversation_id )
			);
			if ( '' === trim( $current_title ) && '' !== $title ) {
				$updates['title'] = $title;
				$formats[]        = '%s';
			}
		}

		if ( ! empty( $extra['model'] ) ) {
			$updates['model'] = sanitize_text_field( (string) $extra['model'] );
			$formats[]        = '%s';
		}
		if ( ! empty( $extra['provider'] ) ) {
			$updates['provider'] = sanitize_text_field( (string) $extra['provider'] );
			$formats[]           = '%s';
		}
		if ( ! empty( $extra['status'] ) && 'assistant' === $role ) {
			$updates['status'] = sanitize_text_field( (string) $extra['status'] );
			$formats[]         = '%s';
		}
		if ( ! empty( $extra['page_url'] ) ) {
			$updates['page_url'] = self::sanitize_url_field( (string) $extra['page_url'] );
			$formats[]           = '%s';
		}
		if ( ! empty( $extra['page_path'] ) ) {
			$updates['page_path'] = self::sanitize_path_field( (string) $extra['page_path'] );
			$formats[]            = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom conversations table; no WP API for plugin chat history.
		$wpdb->update(
			$conv_table,
			$updates,
			array( 'id' => $conversation_id ),
			$formats,
			array( '%d' )
		);
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array{join: string, where: list<string>, params: list<mixed>, alias: string}
	 */
	private static function query_filters( array $args ): array {
		global $wpdb;

		$where  = array( '1=1' );
		$params = array();
		$join   = '';
		$alias  = 'c';

		$days = isset( $args['days'] ) ? (int) $args['days'] : 0;
		if ( $days > 0 ) {
			$where[]  = "{$alias}.updated_at >= %s";
			$params[] = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		}

		$provider = isset( $args['provider'] ) ? sanitize_key( (string) $args['provider'] ) : '';
		if ( '' !== $provider && 'all' !== $provider ) {
			$where[]  = "{$alias}.provider = %s";
			$params[] = $provider;
		}

		$status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
		if ( '' !== $status && 'all' !== $status ) {
			$where[]  = "{$alias}.status = %s";
			$params[] = $status;
		}

		$page_path = isset( $args['page_path'] ) ? sanitize_text_field( (string) $args['page_path'] ) : '';
		if ( '' !== $page_path && 'all' !== $page_path ) {
			$where[]  = "{$alias}.page_path = %s";
			$params[] = $page_path;
		}

		$search = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
		if ( '' !== $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$scope   = isset( $args['search_in'] ) ? sanitize_key( (string) $args['search_in'] ) : 'all';
			$meta_sql = "({$alias}.public_id LIKE %s OR {$alias}.title LIKE %s OR {$alias}.page_path LIKE %s OR {$alias}.session_hash LIKE %s)";

			if ( 'messages' === $scope ) {
				$messages = self::messages_table();
				$join     = " INNER JOIN {$messages} m ON m.conversation_id = {$alias}.id ";
				$where[]  = "m.content LIKE %s";
				$params[] = $like;
			} elseif ( 'all' === $scope ) {
				$messages = self::messages_table();
				$join     = " LEFT JOIN {$messages} m ON m.conversation_id = {$alias}.id ";
				$where[]  = '(' . $meta_sql . ' OR m.content LIKE %s)';
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
			} else {
				$where[]  = $meta_sql;
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
			}
		}

		return array(
			'join'   => $join,
			'where'  => $where,
			'params' => $params,
			'alias'  => $alias,
		);
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_conversations( array $args = array() ): array {
		global $wpdb;

		$table   = self::conversations_table();
		$filters = self::query_filters( $args );
		$per     = max( 1, min( 50, (int) ( $args['per_page'] ?? 12 ) ) );
		$offset  = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$order   = isset( $args['orderby'] ) && 'started_at' === $args['orderby'] ? 'started_at' : 'updated_at';
		$dir     = isset( $args['order'] ) && 'asc' === strtolower( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$alias   = $filters['alias'];

		$sql = "SELECT DISTINCT {$alias}.* FROM {$table} {$alias} {$filters['join']} WHERE "
			. implode( ' AND ', $filters['where'] )
			. " ORDER BY {$alias}.{$order} {$dir} LIMIT %d OFFSET %d";

		$params   = $filters['params'];
		$params[] = $per;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic history query; table/join names from plugin helpers and whitelisted order columns.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		return $rows ?: array();
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public static function count_conversations( array $args = array() ): int {
		global $wpdb;

		$table   = self::conversations_table();
		$filters = self::query_filters( $args );
		$alias   = $filters['alias'];

		$sql = "SELECT COUNT(DISTINCT {$alias}.id) FROM {$table} {$alias} {$filters['join']} WHERE "
			. implode( ' AND ', $filters['where'] );

		if ( empty( $filters['params'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic history query; table/join names from plugin helpers and whitelisted order columns.
			return (int) $wpdb->get_var( $sql );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic history query; table/join names from plugin helpers and whitelisted order columns.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $filters['params'] ) );
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array{total: int, errors: int, messages: int, avg_messages: float}
	 */
	public static function get_summary_stats( array $args = array() ): array {
		global $wpdb;

		$table   = self::conversations_table();
		$filters = self::query_filters( $args );
		$alias   = $filters['alias'];

		$sql = "SELECT
			COUNT(DISTINCT {$alias}.id) AS total,
			SUM(CASE WHEN {$alias}.status = 'error' THEN 1 ELSE 0 END) AS errors,
			COALESCE(SUM({$alias}.message_count), 0) AS messages
			FROM {$table} {$alias} {$filters['join']} WHERE "
			. implode( ' AND ', $filters['where'] );

		if ( empty( $filters['params'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic history query; table/join names from plugin helpers and whitelisted order columns.
			$row = $wpdb->get_row( $sql, ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic history query; table/join names from plugin helpers and whitelisted order columns.
			$row = $wpdb->get_row( $wpdb->prepare( $sql, $filters['params'] ), ARRAY_A );
		}

		$total    = (int) ( $row['total'] ?? 0 );
		$errors   = (int) ( $row['errors'] ?? 0 );
		$messages = (int) ( $row['messages'] ?? 0 );

		return array(
			'total'        => $total,
			'errors'       => $errors,
			'messages'     => $messages,
			'avg_messages' => $total > 0 ? round( $messages / $total, 1 ) : 0.0,
		);
	}

	/**
	 * @param array<string, mixed> $args
	 * @return list<string>
	 */
	public static function get_distinct_page_paths( array $args = array() ): array {
		global $wpdb;

		$table   = self::conversations_table();
		$filters = self::query_filters( array_merge( $args, array( 'page_path' => 'all', 'search' => '' ) ) );
		$alias   = $filters['alias'];

		$sql = "SELECT DISTINCT {$alias}.page_path FROM {$table} {$alias} WHERE "
			. implode( ' AND ', $filters['where'] )
			. " AND {$alias}.page_path IS NOT NULL AND {$alias}.page_path != '' ORDER BY {$alias}.page_path ASC LIMIT 100";

		if ( empty( $filters['params'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic history query; table/join names from plugin helpers and whitelisted order columns.
			$rows = $wpdb->get_col( $sql );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic history query; table/join names from plugin helpers and whitelisted order columns.
			$rows = $wpdb->get_col( $wpdb->prepare( $sql, $filters['params'] ) );
		}

		return array_values( array_filter( array_map( 'strval', $rows ?: array() ) ) );
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public static function find_conversation_page( int $conversation_id, array $args, int $per_page = 12 ): int {
		if ( $conversation_id <= 0 || $per_page <= 0 ) {
			return 1;
		}

		global $wpdb;

		$conv = self::get_conversation( $conversation_id );
		if ( ! $conv ) {
			return 1;
		}

		$table   = self::conversations_table();
		$filters = self::query_filters( $args );
		$alias   = $filters['alias'];
		$order   = isset( $args['orderby'] ) && 'started_at' === $args['orderby'] ? 'started_at' : 'updated_at';
		$dir     = isset( $args['order'] ) && 'asc' === strtolower( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$pivot   = (string) ( $conv[ $order ] ?? '' );

		if ( '' === $pivot ) {
			return 1;
		}

		if ( 'ASC' === $dir ) {
			$where_op = "{$alias}.{$order} < %s OR ({$alias}.{$order} = %s AND {$alias}.id < %d)";
		} else {
			$where_op = "{$alias}.{$order} > %s OR ({$alias}.{$order} = %s AND {$alias}.id > %d)";
		}

		$sql = "SELECT COUNT(DISTINCT {$alias}.id) FROM {$table} {$alias} {$filters['join']} WHERE "
			. implode( ' AND ', $filters['where'] )
			. ' AND (' . $where_op . ')';

		$params   = $filters['params'];
		$params[] = $pivot;
		$params[] = $pivot;
		$params[] = $conversation_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic history query; table/join names from plugin helpers and whitelisted order columns.
		$before = (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );

		return max( 1, (int) floor( $before / $per_page ) + 1 );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_conversation( int $id ): ?array {
		global $wpdb;

		if ( $id <= 0 ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup; table name via %i placeholder.
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::conversations_table(), $id ),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_messages( int $conversation_id ): array {
		global $wpdb;

		if ( $conversation_id <= 0 ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup; table name via %i placeholder.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE conversation_id = %d ORDER BY created_at ASC, id ASC',
				self::messages_table(),
				$conversation_id
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	public static function format_datetime_local( string $mysql_utc ): string {
		if ( '' === $mysql_utc ) {
			return '—';
		}
		$ts = strtotime( $mysql_utc . ' UTC' );
		if ( false === $ts ) {
			return $mysql_utc;
		}
		return wp_date( 'd/m/Y H:i', $ts );
	}

	public static function format_relative_time( string $mysql_utc ): string {
		if ( '' === $mysql_utc ) {
			return '';
		}
		$ts = strtotime( $mysql_utc . ' UTC' );
		if ( false === $ts ) {
			return '';
		}
		return human_time_diff( $ts, time() );
	}

	public static function format_duration( string $started_at, string $updated_at ): string {
		$start = strtotime( $started_at . ' UTC' );
		$end   = strtotime( $updated_at . ' UTC' );
		if ( false === $start || false === $end || $end <= $start ) {
			return '—';
		}
		$seconds = $end - $start;
		if ( $seconds < 60 ) {
			/* translators: %d: seconds */
			return sprintf( _n( '%d s', '%d s', $seconds, MULTCH_TEXT_DOMAIN ), $seconds );
		}
		if ( $seconds < 3600 ) {
			$mins = (int) floor( $seconds / 60 );
			/* translators: %d: minutes */
			return sprintf( _n( '%d min', '%d min', $mins, MULTCH_TEXT_DOMAIN ), $mins );
		}
		$hours = (int) floor( $seconds / 3600 );
		$mins  = (int) floor( ( $seconds % 3600 ) / 60 );
		if ( $mins > 0 ) {
			/* translators: 1: hours, 2: minutes */
			return sprintf( __( '%1$dh %2$dmin', MULTCH_TEXT_DOMAIN ), $hours, $mins );
		}
		/* translators: %d: hours */
		return sprintf( _n( '%d h', '%d h', $hours, MULTCH_TEXT_DOMAIN ), $hours );
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public static function export_csv( array $args = array() ): void {
		$rows = self::list_conversations(
			array_merge(
				$args,
				array(
					'per_page' => 5000,
					'offset'   => 0,
				)
			)
		);

		$filename = 'multch-history-' . gmdate( 'Y-m-d-His' ) . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- php://output is the standard stream for CSV downloads.
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			return;
		}

		fputcsv(
			$out,
			array(
				__( 'Public ID', MULTCH_TEXT_DOMAIN ),
				__( 'Title', MULTCH_TEXT_DOMAIN ),
				__( 'Provider', MULTCH_TEXT_DOMAIN ),
				__( 'Model', MULTCH_TEXT_DOMAIN ),
				__( 'Status', MULTCH_TEXT_DOMAIN ),
				__( 'Messages', MULTCH_TEXT_DOMAIN ),
				__( 'Path', MULTCH_TEXT_DOMAIN ),
				__( 'Start', MULTCH_TEXT_DOMAIN ),
				__( 'Last activity', MULTCH_TEXT_DOMAIN ),
			)
		);

		foreach ( $rows as $row ) {
			fputcsv(
				$out,
				array(
					(string) ( $row['public_id'] ?? '' ),
					(string) ( $row['title'] ?? '' ),
					(string) ( $row['provider'] ?? '' ),
					(string) ( $row['model'] ?? '' ),
					(string) ( $row['status'] ?? '' ),
					(int) ( $row['message_count'] ?? 0 ),
					(string) ( $row['page_path'] ?? '' ),
					(string) ( $row['started_at'] ?? '' ),
					(string) ( $row['updated_at'] ?? '' ),
				)
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closes php://output stream after CSV export.
		fclose( $out );
	}

	/**
	 * @return array{deleted_conversations: int, deleted_messages: int}
	 */
	public static function purge_older_than_days( int $days ): array {
		if ( $days <= 0 ) {
			return array(
				'deleted_conversations' => 0,
				'deleted_messages'      => 0,
			);
		}

		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table purge; table name via %i placeholder.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE updated_at < %s',
				self::conversations_table(),
				$cutoff
			)
		);

		if ( empty( $ids ) ) {
			return array(
				'deleted_conversations' => 0,
				'deleted_messages'      => 0,
			);
		}

		$ids       = self::sanitize_id_list( $ids );
		$msg_table = self::messages_table();
		$conv_table = self::conversations_table();

		$deleted_messages      = 0;
		$deleted_conversations = 0;

		foreach ( $ids as $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table purge; IDs sanitized via sanitize_id_list().
			$deleted_messages += (int) $wpdb->delete(
				$msg_table,
				array( 'conversation_id' => $id ),
				array( '%d' )
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table purge; IDs sanitized via sanitize_id_list().
			$deleted_conversations += (int) $wpdb->delete(
				$conv_table,
				array( 'id' => $id ),
				array( '%d' )
			);
		}

		return array(
			'deleted_conversations' => $deleted_conversations,
			'deleted_messages'      => $deleted_messages,
		);
	}

	public static function delete_conversation( int $conversation_id ): bool {
		if ( $conversation_id <= 0 ) {
			return false;
		}

		global $wpdb;

		$msg_table  = self::messages_table();
		$conv_table = self::conversations_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete; table names from plugin helpers.
		$wpdb->delete( $msg_table, array( 'conversation_id' => $conversation_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete; table names from plugin helpers.
		$deleted = $wpdb->delete( $conv_table, array( 'id' => $conversation_id ), array( '%d' ) );

		return (bool) $deleted;
	}

	/**
	 * @param list<int> $conversation_ids
	 * @return array<int, string>
	 */
	public static function get_first_user_previews( array $conversation_ids ): array {
		$conversation_ids = self::sanitize_id_list( $conversation_ids );
		if ( empty( $conversation_ids ) ) {
			return array();
		}

		global $wpdb;

		$msg_table = self::messages_table();
		$out       = array();

		foreach ( $conversation_ids as $cid ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup; first user message per conversation.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT conversation_id, content FROM %i WHERE conversation_id = %d AND role = %s ORDER BY id ASC LIMIT 1',
					$msg_table,
					$cid,
					'user'
				),
				ARRAY_A
			);
			if ( ! $row ) {
				continue;
			}
			$conversation_id = (int) ( $row['conversation_id'] ?? 0 );
			if ( $conversation_id <= 0 ) {
				continue;
			}
			$text = wp_strip_all_tags( (string) ( $row['content'] ?? '' ) );
			$text = preg_replace( '/\s+/', ' ', $text ) ?? $text;
			$out[ $conversation_id ] = self::truncate_title( trim( $text ) );
		}

		return $out;
	}

	public static function run_retention_purge(): void {
		$settings = Multch_Plugin::get_settings();
		$days     = isset( $settings['history_retention_days'] ) ? (int) $settings['history_retention_days'] : 0;
		if ( $days <= 0 ) {
			return;
		}
		self::purge_older_than_days( $days );
	}

	/**
	 * @param list<int|string> $ids
	 * @return list<int>
	 */
	private static function sanitize_id_list( array $ids ): array {
		return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	}

	private static function truncate_title( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/\s+/', ' ', $text ) ?? $text;
		$text = trim( $text );
		if ( strlen( $text ) > 120 ) {
			$text = substr( $text, 0, 117 ) . '…';
		}
		return $text;
	}

	private static function sanitize_url_field( string $url ): ?string {
		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return null;
		}
		return substr( $url, 0, 500 );
	}

	private static function sanitize_path_field( string $path ): ?string {
		$path = sanitize_text_field( $path );
		if ( '' === $path ) {
			return null;
		}
		return substr( $path, 0, 255 );
	}

	/**
	 * @param list<int> $conversation_ids
	 * @return array<int, string>
	 */
	public static function get_public_ids_by_conversation_ids( array $conversation_ids ): array {
		$conversation_ids = self::sanitize_id_list( $conversation_ids );
		if ( empty( $conversation_ids ) ) {
			return array();
		}

		global $wpdb;

		$conv_table = self::conversations_table();
		$map        = array();

		foreach ( $conversation_ids as $cid ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup; IDs sanitized via sanitize_id_list().
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT id, public_id FROM %i WHERE id = %d LIMIT 1',
					$conv_table,
					$cid
				),
				ARRAY_A
			);
			if ( ! $row ) {
				continue;
			}
			$map[ (int) ( $row['id'] ?? 0 ) ] = (string) ( $row['public_id'] ?? '' );
		}

		return $map;
	}

	public static function drop_tables(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required on uninstall; table names from plugin helpers via %i.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::messages_table() ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required on uninstall; table names from plugin helpers via %i.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::conversations_table() ) );
		delete_option( 'multch_plugin_history_db_version' );
	}
}
