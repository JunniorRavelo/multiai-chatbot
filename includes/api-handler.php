<?php
/**
 * Chat API handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Multch_Api_Handler {

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_chat( WP_REST_Request $request ) {
		$started = microtime( true );
		$settings = Multch_Plugin::get_settings();
		$session_id = self::get_session_id( $request );
		$session_hash = Multch_Telemetry::hash_session( $session_id );

		$rate_check = self::enforce_rate_limit( $settings );
		if ( $rate_check instanceof WP_REST_Response ) {
			self::record_event( $session_hash, $settings, '', 'rate_limited', (int) ( ( microtime( true ) - $started ) * 1000 ), 'RATE_LIMIT_GENERAL' );
			return $rate_check;
		}

		$parsed = self::parse_body( $request );
		if ( is_wp_error( $parsed ) ) {
			self::record_event( $session_hash, $settings, '', 'invalid_request', (int) ( ( microtime( true ) - $started ) * 1000 ), 'INVALID_REQUEST' );
			$data = $parsed->get_error_data();
			return new WP_REST_Response(
				array(
					'error'     => $parsed->get_error_message(),
					'errorCode' => is_array( $data ) && isset( $data['error_code'] ) ? $data['error_code'] : 'INVALID_REQUEST',
				),
				is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400
			);
		}

		$provider_id = ! empty( $settings['provider'] ) ? (string) $settings['provider'] : 'wordpress_ai';
		$provider    = self::get_provider( $provider_id );
		if ( is_wp_error( $provider ) ) {
			self::record_event( $session_hash, $settings, '', 'config_error', (int) ( ( microtime( true ) - $started ) * 1000 ), 'CONFIGURATION_ERROR' );
			$data = $provider->get_error_data();
			return new WP_REST_Response(
				array(
					'error'     => $provider->get_error_message(),
					'errorCode' => is_array( $data ) && isset( $data['error_code'] ) ? $data['error_code'] : 'CONFIGURATION_ERROR',
				),
				is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 503
			);
		}

		$system = ! empty( $settings['system_prompt'] )
			? Multch_Admin_Settings::localize_general_setting_value(
				'system_prompt',
				(string) $settings['system_prompt']
			)
			: __( 'You are a helpful website assistant. Respond clearly and briefly.', 'multiai-chatbot' );

		$messages     = self::build_messages( $parsed['message'], $parsed['history'] );
		$conversation = self::resolve_history_conversation(
			$session_hash,
			$parsed,
			$provider_id
		);

		$cache_ttl = isset( $settings['cache_ttl_seconds'] ) ? max( 0, (int) $settings['cache_ttl_seconds'] ) : 0;
		$cache_key = '';
		if ( $cache_ttl > 0 ) {
			$cache_key = 'multch_resp_' . md5(
				$system . '|' . wp_json_encode( $messages ) . '|' . $provider_id . '|' . ( $settings['model'] ?? '' )
			);
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) && ! empty( $cached['answer'] ) ) {
				$latency = (int) ( ( microtime( true ) - $started ) * 1000 );
				self::record_event( $session_hash, $settings, (string) ( $cached['meta']['model'] ?? '' ), 'cached', $latency, '', $conversation );
				self::persist_history_exchange(
					$conversation,
					$parsed['message'],
					(string) $cached['answer'],
					$provider_id,
					(string) ( $cached['meta']['model'] ?? '' ),
					'cached',
					$latency,
					$parsed
				);
				$cached['meta'] = self::append_conversation_meta(
					is_array( $cached['meta'] ?? null ) ? $cached['meta'] : array(),
					$conversation
				);
				return new WP_REST_Response( $cached, 200 );
			}
		}

		$model_limit = self::enforce_model_rate_limit( $settings, $session_hash );
		if ( $model_limit instanceof WP_REST_Response ) {
			$limit_data = $model_limit->get_data();
			$limit_code = is_array( $limit_data ) && ! empty( $limit_data['errorCode'] )
				? (string) $limit_data['errorCode']
				: 'RATE_LIMIT_MODEL';
			self::record_event(
				$session_hash,
				$settings,
				multch_ai_client_configured_models_summary( $settings ),
				'rate_limited',
				(int) ( ( microtime( true ) - $started ) * 1000 ),
				$limit_code,
				$conversation
			);
			return $model_limit;
		}

		$provider_settings = array(
			'model'                  => ! empty( $settings['model'] ) ? (string) $settings['model'] : '',
			'model_candidates'       => ! empty( $settings['model_candidates'] ) ? (string) $settings['model_candidates'] : '',
			'allow_google_any_model' => ! empty( $settings['allow_google_any_model'] ),
			'ollama_base_url'        => ! empty( $settings['ollama_base_url'] ) ? (string) $settings['ollama_base_url'] : 'http://127.0.0.1:11434',
			'request_timeout'        => ! empty( $settings['request_timeout'] ) ? (int) $settings['request_timeout'] : 22,
		);

		$result = $provider->complete( $system, $messages, $provider_settings );
		$latency = (int) ( ( microtime( true ) - $started ) * 1000 );

		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$status     = is_array( $error_data ) && isset( $error_data['status'] ) ? (int) $error_data['status'] : 500;
			$error_code = is_array( $error_data ) && isset( $error_data['error_code'] ) ? (string) $error_data['error_code'] : 'SERVER_ERROR';
			$retry_after = is_array( $error_data ) && isset( $error_data['retry_after'] ) ? (int) $error_data['retry_after'] : 0;

			self::record_provider_failure_events( $session_hash, $settings, $result, $latency, $conversation );
			self::persist_history_exchange(
				$conversation,
				$parsed['message'],
				$result->get_error_message(),
				$provider_id,
				multch_ai_client_configured_models_summary( $settings ),
				'error',
				$latency,
				$parsed
			);

			$response = new WP_REST_Response(
				array(
					'error'     => $result->get_error_message(),
					'errorCode' => $error_code,
				),
				$status
			);
			if ( $retry_after > 0 ) {
				$response->header( 'Retry-After', (string) $retry_after );
			}
			return $response;
		}

		$model_meta = multch_ai_client_model_meta_from_result( $result );

		self::record_event(
			$session_hash,
			$settings,
			$model_meta['model'],
			'success',
			$latency,
			'',
			$conversation,
			$model_meta['modelPrimary'],
			$model_meta['usedFallback']
		);
		self::persist_history_exchange(
			$conversation,
			$parsed['message'],
			$result['text'],
			$provider_id,
			$model_meta['modelLabel'],
			'success',
			$latency,
			$parsed,
			$model_meta
		);

		$response_data = array(
			'answer' => $result['text'],
			'meta'   => self::append_conversation_meta(
				array_merge(
					$model_meta,
					array( 'provider' => $provider_id )
				),
				$conversation
			),
		);

		if ( $cache_ttl > 0 && '' !== $cache_key ) {
			set_transient( $cache_key, $response_data, $cache_ttl );
		}

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_stream( WP_REST_Request $request ) {
		$settings = Multch_Plugin::get_settings();
		if ( empty( $settings['streaming_enabled'] ) ) {
			return new WP_REST_Response( array( 'error' => __( 'Streaming disabled.', 'multiai-chatbot' ) ), 404 );
		}

		$response = self::internal_chat_request( $request, $settings );
		if ( $response->is_error() ) {
			$error = $response->as_error();
			$data  = $error->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500;
			return new WP_REST_Response(
				array(
					'error'     => $error->get_error_message(),
					'errorCode' => $error->get_error_code(),
				),
				$status
			);
		}

		$data = $response->get_data();
		if ( ! is_array( $data ) || empty( $data['answer'] ) ) {
			$status = $response->get_status();
			return new WP_REST_Response( $data, $status );
		}

		$text   = (string) $data['answer'];
		$chunks = self::split_chunks( $text );

		$stream_callback = static function () use ( $chunks ) {
			foreach ( $chunks as $chunk ) {
				echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				if ( function_exists( 'wp_ob_end_flush_all' ) ) {
					@flush(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
				usleep( 25000 );
			}
		};

		$stream_headers = array(
			'Content-Type'  => 'text/plain; charset=utf-8',
			'X-Chat-Stream' => 'chunked-text',
			'X-Chat-Model'  => isset( $data['meta']['model'] ) ? (string) $data['meta']['model'] : '',
		);
		if ( ! empty( $data['meta']['modelLabel'] ) ) {
			$stream_headers['X-Chat-Model-Label'] = (string) $data['meta']['modelLabel'];
		}

		return new WP_REST_Response( null, 200, $stream_headers );
	}

	/**
	 * Stream response as plain text (custom dispatch).
	 */
	public static function dispatch_stream( WP_REST_Request $request ): void {
		$settings = Multch_Plugin::get_settings();
		if ( empty( $settings['streaming_enabled'] ) ) {
			status_header( 404 );
			echo wp_json_encode( array( 'error' => __( 'Streaming disabled.', 'multiai-chatbot' ) ) );
			exit;
		}

		$nonce = $request->get_header( 'x-wp-nonce' );
		if ( ! self::verify_nonce( $nonce ) ) {
			status_header( 403 );
			echo wp_json_encode( array( 'error' => __( 'Invalid nonce.', 'multiai-chatbot' ), 'errorCode' => 'ORIGIN_FORBIDDEN' ) );
			exit;
		}

		if ( ! self::verify_origin( $settings ) ) {
			status_header( 403 );
			echo wp_json_encode( array( 'error' => __( 'Origin not allowed.', 'multiai-chatbot' ), 'errorCode' => 'ORIGIN_FORBIDDEN' ) );
			exit;
		}

		$response = self::internal_chat_request( $request, $settings );
		if ( $response->is_error() ) {
			status_header( $response->get_status() );
			header( 'Content-Type: application/json; charset=utf-8' );
			echo wp_json_encode( $response->get_data() );
			exit;
		}

		$data = $response->get_data();
		if ( ! is_array( $data ) || empty( $data['answer'] ) ) {
			status_header( $response->get_status() );
			header( 'Content-Type: application/json; charset=utf-8' );
			echo wp_json_encode( $data );
			exit;
		}

		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Chat-Stream: chunked-text' );
		if ( ! empty( $data['meta']['model'] ) ) {
			header( 'X-Chat-Model: ' . sanitize_text_field( (string) $data['meta']['model'] ) );
		}
		if ( ! empty( $data['meta']['modelLabel'] ) ) {
			header( 'X-Chat-Model-Label: ' . sanitize_text_field( (string) $data['meta']['modelLabel'] ) );
		}
		if ( ! empty( $data['meta']['conversationId'] ) ) {
			header( 'X-Chat-Conversation-Id: ' . sanitize_text_field( (string) $data['meta']['conversationId'] ) );
		}

		foreach ( self::split_chunks( (string) $data['answer'] ) as $chunk ) {
			echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( function_exists( 'wp_ob_end_flush_all' ) ) {
				@flush(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			usleep( 25000 );
		}
		exit;
	}

	public static function verify_nonce( ?string $nonce ): bool {
		$verified = wp_verify_nonce( $nonce ? $nonce : '', 'wp_rest' );

		return false !== $verified;
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public static function verify_origin( array $settings ): bool {
		$request_origin = self::get_request_origin();
		if ( '' === $request_origin ) {
			return true;
		}

		$allowed = self::parse_allowed_origins( $settings );
		if ( empty( $allowed ) ) {
			$allowed = self::get_site_origins();
		}

		return in_array( $request_origin, $allowed, true );
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return list<string>
	 */
	private static function parse_allowed_origins( array $settings ): array {
		$raw = ! empty( $settings['allowed_origins'] ) ? (string) $settings['allowed_origins'] : '';
		if ( '' === trim( $raw ) ) {
			return array();
		}

		$origins = array();
		foreach ( explode( ',', $raw ) as $part ) {
			$origin = untrailingslashit( trim( $part ) );
			if ( '' !== $origin ) {
				$origins[] = $origin;
			}
		}

		return array_values( array_unique( $origins ) );
	}

	/**
	 * @return list<string>
	 */
	private static function get_site_origins(): array {
		$home = home_url( '/' );
		$origins = array( untrailingslashit( $home ) );

		$parsed = wp_parse_url( $home );
		if ( is_array( $parsed ) && ! empty( $parsed['host'] ) ) {
			$scheme = ! empty( $parsed['scheme'] ) ? (string) $parsed['scheme'] : 'https';
			$host   = (string) $parsed['host'];
			$port   = ! empty( $parsed['port'] ) ? ':' . (int) $parsed['port'] : '';
			$origins[] = $scheme . '://' . $host . $port;
		}

		return array_values( array_unique( $origins ) );
	}

	private static function get_request_origin(): string {
		if ( ! empty( $_SERVER['HTTP_ORIGIN'] ) ) {
			$origin = esc_url_raw( wp_unslash( (string) $_SERVER['HTTP_ORIGIN'] ), array( 'http', 'https' ) );
			return '' !== $origin ? untrailingslashit( $origin ) : '';
		}

		if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = esc_url_raw( wp_unslash( (string) $_SERVER['HTTP_REFERER'] ), array( 'http', 'https' ) );
			$parsed  = wp_parse_url( $referer );
			if ( is_array( $parsed ) && ! empty( $parsed['host'] ) ) {
				$scheme = ! empty( $parsed['scheme'] ) ? (string) $parsed['scheme'] : 'https';
				$port   = ! empty( $parsed['port'] ) ? ':' . (int) $parsed['port'] : '';
				return $scheme . '://' . (string) $parsed['host'] . $port;
			}
		}

		return '';
	}

	/**
	 * @param WP_REST_Request        $request
	 * @param array<string, mixed>   $settings
	 * @return WP_REST_Response
	 */
	private static function internal_chat_request( WP_REST_Request $request, array $settings ): WP_REST_Response {
		$nonce   = (string) $request->get_header( 'x-wp-nonce' );
		$session = (string) $request->get_header( 'x-chat-session-id' );
		$base    = ! empty( $settings['internal_chat_base_url'] ) ? untrailingslashit( (string) $settings['internal_chat_base_url'] ) : '';

		if ( '' !== $base && self::is_public_loop_url( $base ) ) {
			$base = '';
		}

		if ( '' === $base ) {
			$internal = new WP_REST_Request( 'POST', '/multch/v1/chat' );
			$internal->set_body( $request->get_body() );
			$internal->set_header( 'Content-Type', 'application/json' );
			if ( '' !== $session ) {
				$internal->set_header( 'x-chat-session-id', $session );
			}
			if ( '' !== $nonce ) {
				$internal->set_header( 'x-wp-nonce', $nonce );
			}
			return rest_do_request( $internal );
		}

		$url = $base . '/wp-json/multch/v1/chat';
		$headers = array(
			'Content-Type' => 'application/json',
		);
		if ( '' !== $nonce ) {
			$headers['X-WP-Nonce'] = $nonce;
		}
		if ( '' !== $session ) {
			$headers['X-Chat-Session-Id'] = $session;
		}

		$timeout = ! empty( $settings['request_timeout'] ) ? max( 5, (int) $settings['request_timeout'] ) : 22;
		$remote  = wp_remote_post(
			$url,
			array(
				'timeout' => $timeout + 5,
				'headers' => $headers,
				'body'    => $request->get_body(),
			)
		);

		if ( is_wp_error( $remote ) ) {
			return new WP_REST_Response(
				array(
					'error'     => $remote->get_error_message(),
					'errorCode' => 'SERVER_ERROR',
				),
				503
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $remote );
		$raw    = (string) wp_remote_retrieve_body( $remote );
		$data   = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			if ( self::looks_like_html_error_page( $raw ) ) {
				return new WP_REST_Response(
					array(
						'error'     => __( 'The server returned an error page (502). Leave the internal chat URL empty or use a local URL; do not use the public URL with Cloudflare.', 'multiai-chatbot' ),
						'errorCode' => 'PROVIDER_UPSTREAM',
					),
					502
				);
			}
			$data = array(
				'error'     => __( 'Invalid internal response.', 'multiai-chatbot' ),
				'errorCode' => 'SERVER_ERROR',
			);
		}

		return new WP_REST_Response( $data, $status > 0 ? $status : 500 );
	}

	private static function is_public_loop_url( string $base ): bool {
		$base_parts = wp_parse_url( $base );
		$home_parts = wp_parse_url( home_url() );
		if ( ! is_array( $base_parts ) || ! is_array( $home_parts ) || empty( $base_parts['host'] ) || empty( $home_parts['host'] ) ) {
			return false;
		}

		return strtolower( (string) $base_parts['host'] ) === strtolower( (string) $home_parts['host'] );
	}

	private static function looks_like_html_error_page( string $body ): bool {
		$snippet = strtolower( substr( ltrim( $body ), 0, 256 ) );
		return str_contains( $snippet, '<!doctype html' ) || str_contains( $snippet, '<html' );
	}

	/**
	 * @return Multch_AI_Provider|WP_Error
	 */
	private static function get_provider( string $id ) {
		if ( in_array( $id, multch_legacy_cloud_provider_ids(), true ) ) {
			$id = 'wordpress_ai';
		}

		switch ( $id ) {
			case 'wordpress_ai':
				return new Multch_Provider_WordPress_AI();
			case 'ollama':
				return new Multch_Provider_Ollama();
			default:
				return new WP_Error(
					'configuration_error',
					__( 'Invalid AI provider.', 'multiai-chatbot' ),
					array( 'status' => 503, 'error_code' => 'CONFIGURATION_ERROR' )
				);
		}
	}

	/**
	 * @return array{message: string, history: array<int, array{role: string, content: string}>}|WP_Error
	 */
	private static function parse_body( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_Error(
				'invalid_request',
				__( 'Invalid request.', 'multiai-chatbot' ),
				array( 'status' => 400, 'error_code' => 'INVALID_REQUEST' )
			);
		}

		$message = isset( $body['message'] ) ? trim( (string) $body['message'] ) : '';
		if ( strlen( $message ) < 2 || strlen( $message ) > 700 ) {
			return new WP_Error(
				'invalid_request',
				__( 'The message must be between 2 and 700 characters.', 'multiai-chatbot' ),
				array( 'status' => 400, 'error_code' => 'INVALID_REQUEST' )
			);
		}

		$history = array();
		if ( ! empty( $body['history'] ) && is_array( $body['history'] ) ) {
			foreach ( array_slice( $body['history'], -20 ) as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$role = isset( $item['role'] ) && 'assistant' === $item['role'] ? 'assistant' : 'user';
				$content = isset( $item['content'] ) ? trim( (string) $item['content'] ) : '';
				if ( strlen( $content ) < 1 || strlen( $content ) > 700 ) {
					continue;
				}
				$history[] = array(
					'role'    => $role,
					'content' => $content,
				);
			}
		}

		$conversation_id = isset( $body['conversationId'] ) ? trim( (string) $body['conversationId'] ) : '';
		$current_path      = isset( $body['currentPath'] ) ? trim( (string) $body['currentPath'] ) : '';
		$current_url       = isset( $body['currentUrl'] ) ? trim( (string) $body['currentUrl'] ) : '';

		return array(
			'message'          => $message,
			'history'          => $history,
			'conversation_id'  => substr( $conversation_id, 0, 64 ),
			'current_path'     => substr( $current_path, 0, 255 ),
			'current_url'      => substr( $current_url, 0, 500 ),
		);
	}

	/**
	 * @param array{message: string, history: array<int, array{role: string, content: string}>, conversation_id?: string, current_path?: string, current_url?: string} $parsed
	 * @return array{id: int, public_id: string}|null
	 */
	private static function resolve_history_conversation( string $session_hash, array $parsed, string $provider_id ): ?array {
		if ( ! Multch_Plugin::is_stats_history_enabled() ) {
			return null;
		}

		$conv = Multch_Chat_History::resolve_conversation(
			$session_hash,
			$parsed['conversation_id'] ?? '',
			array(
				'title'      => $parsed['message'],
				'provider'   => $provider_id,
				'page_url'   => $parsed['current_url'] ?? '',
				'page_path'  => $parsed['current_path'] ?? '',
			)
		);

		return $conv['id'] > 0 ? $conv : null;
	}

	/**
	 * @param array{id: int, public_id: string}|null $conversation
	 * @param array<string, mixed>                  $parsed
	 */
	/**
	 * @param array<string, mixed> $parsed
	 * @param array{model?: string, modelPrimary?: string, usedFallback?: bool, modelLabel?: string} $model_meta
	 */
	private static function persist_history_exchange(
		?array $conversation,
		string $user_message,
		string $assistant_message,
		string $provider_id,
		string $model,
		string $status,
		int $latency_ms,
		array $parsed,
		array $model_meta = array()
	): void {
		if ( ! Multch_Plugin::is_stats_history_enabled() ) {
			return;
		}

		if ( null === $conversation || $conversation['id'] <= 0 ) {
			return;
		}

		Multch_Chat_History::add_message(
			$conversation['id'],
			'user',
			$user_message,
			array(
				'provider'  => $provider_id,
				'page_url'  => $parsed['current_url'] ?? '',
				'page_path' => $parsed['current_path'] ?? '',
			)
		);

		$assistant_extra = array(
			'provider'   => $provider_id,
			'model'      => $model,
			'status'     => $status,
			'latency_ms' => $latency_ms,
		);
		if ( ! empty( $model_meta['usedFallback'] ) && ! empty( $model_meta['modelPrimary'] ) ) {
			$assistant_extra['model_primary']  = (string) $model_meta['modelPrimary'];
			$assistant_extra['used_fallback'] = true;
		}

		Multch_Chat_History::add_message(
			$conversation['id'],
			'assistant',
			$assistant_message,
			$assistant_extra
		);
	}

	/**
	 * @param array<string, mixed>               $meta
	 * @param array{id: int, public_id: string}|null $conversation
	 * @return array<string, mixed>
	 */
	private static function append_conversation_meta( array $meta, ?array $conversation ): array {
		if ( null !== $conversation ) {
			$meta['conversationId'] = $conversation['public_id'];
			$meta['conversationDbId'] = $conversation['id'];
		}
		return $meta;
	}

	/**
	 * @param array<int, array{role: string, content: string}> $history
	 * @return array<int, array{role: string, content: string}>
	 */
	private static function build_messages( string $message, array $history ): array {
		$messages = array_slice( $history, -12 );
		$messages[] = array(
			'role'    => 'user',
			'content' => $message,
		);
		return $messages;
	}

	private static function get_session_id( WP_REST_Request $request ): string {
		$header = $request->get_header( 'x-chat-session-id' );
		return $header ? substr( sanitize_text_field( $header ), 0, 120 ) : 'anonymous-session';
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return true|WP_REST_Response
	 */
	private static function enforce_rate_limit( array $settings ) {
		$ip      = self::get_client_ip();
		$ip_hash = md5( $ip );

		if ( false !== get_transient( 'multch_suspend_' . $ip_hash ) ) {
			$suspend_seconds = max( 60, (int) ( $settings['ip_suspend_seconds'] ?? 900 ) );
			return new WP_REST_Response(
				array(
					'error'      => __( 'Your IP is temporarily suspended due to too many requests.', 'multiai-chatbot' ),
					'errorCode'  => 'IP_SUSPENDED',
					'retryAfter' => $suspend_seconds,
				),
				429
			);
		}

		$checks = array(
			array(
				'key'    => 'multch_rl_min_' . $ip_hash,
				'limit'  => max( 1, (int) ( $settings['rate_limit_per_minute'] ?? 10 ) ),
				'window' => MINUTE_IN_SECONDS,
				'code'   => 'RATE_LIMIT_GENERAL',
			),
			array(
				'key'    => 'multch_rl_day_' . $ip_hash,
				'limit'  => max( 1, (int) ( $settings['rate_limit_per_day'] ?? 30 ) ),
				'window' => DAY_IN_SECONDS,
				'code'   => 'RATE_LIMIT_GENERAL',
			),
		);

		foreach ( $checks as $check ) {
			$result = self::check_and_increment_limit( $check, $settings, false );
			if ( $result instanceof WP_REST_Response ) {
				self::record_rate_violation( $ip_hash, $settings );
				return $result;
			}
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return true|WP_REST_Response
	 */
	/**
	 * Per-visitor session limits for AI calls (one user message = one increment, regardless of fallback retries).
	 *
	 * @param array<string, mixed> $settings
	 */
	private static function enforce_model_rate_limit( array $settings, string $session_hash ) {
		$scope = '' !== $session_hash ? $session_hash : 'global';

		$checks = array(
			array(
				'key'    => 'multch_rl_model_min_' . $scope,
				'limit'  => max( 1, (int) ( $settings['rate_limit_model_per_minute'] ?? 6 ) ),
				'window' => MINUTE_IN_SECONDS,
				'code'   => 'RATE_LIMIT_MODEL_MINUTE',
			),
			array(
				'key'    => 'multch_rl_model_day_' . $scope,
				'limit'  => max( 1, (int) ( $settings['rate_limit_model_per_day'] ?? 24 ) ),
				'window' => DAY_IN_SECONDS,
				'code'   => 'RATE_LIMIT_MODEL_DAILY',
			),
		);

		foreach ( $checks as $check ) {
			$result = self::check_and_increment_limit( $check, $settings, true );
			if ( $result instanceof WP_REST_Response ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * @param array{key: string, limit: int, window: int, code: string} $check
	 * @param array<string, mixed>                                       $settings
	 * @return true|WP_REST_Response
	 */
	private static function check_and_increment_limit( array $check, array $settings, bool $is_model_limit = false ) {
		$key    = $check['key'];
		$limit  = $check['limit'];
		$window = $check['window'];
		$count  = (int) get_transient( $key );

		self::maybe_log_soft_limit( $key, $count, $limit, (float) ( $settings['rate_limit_soft_threshold'] ?? 0.8 ) );

		if ( $count >= $limit ) {
			$error_message = $is_model_limit
				? __( 'This site’s chat limit for AI messages was reached. Wait a moment before sending another message, or ask the administrator to adjust limits under MultiAI ChatBot → Security.', 'multiai-chatbot' )
				: __( 'Too many requests. Please wait a moment.', 'multiai-chatbot' );

			return new WP_REST_Response(
				array(
					'error'      => $error_message,
					'errorCode'  => $check['code'],
					'retryAfter' => $window,
				),
				429
			);
		}

		set_transient( $key, $count + 1, $window );
		return true;
	}

	private static function maybe_log_soft_limit( string $key, int $count, int $limit, float $threshold ): void {
		if ( $limit <= 0 ) {
			return;
		}

		$threshold = max( 0.1, min( 1.0, $threshold ) );
		$soft_at   = (int) floor( $limit * $threshold );

		if ( $count >= $soft_at && $count < $limit ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'[multch-plugin] Soft rate limit warning for %1$s: %2$d/%3$d',
					$key,
					$count,
					$limit
				)
			);
		}
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function record_rate_violation( string $ip_hash, array $settings ): void {
		$key       = 'multch_violations_' . $ip_hash;
		$count     = (int) get_transient( $key ) + 1;
		$threshold = max( 1, (int) ( $settings['ip_suspend_after_violations'] ?? 3 ) );
		$suspend   = max( 60, (int) ( $settings['ip_suspend_seconds'] ?? 900 ) );

		if ( $count >= $threshold ) {
			set_transient( 'multch_suspend_' . $ip_hash, time() + $suspend, $suspend );
			delete_transient( $key );
			return;
		}

		set_transient( $key, $count, DAY_IN_SECONDS );
	}

	private static function get_client_ip(): string {
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			return sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}
		if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			return sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_REAL_IP'] ) );
		}
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts = explode( ',', sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			return sanitize_text_field( trim( $parts[0] ) );
		}
		return 'unknown';
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	/**
	 * @param array{id: int, public_id: string}|null $conversation
	 */
	/**
	 * @param array{id: int, public_id: string}|null $conversation
	 */
	private static function record_provider_failure_events(
		string $session_hash,
		array $settings,
		WP_Error $error,
		int $latency_ms,
		?array $conversation = null
	): void {
		$data        = $error->get_error_data();
		$attempt_log = is_array( $data ) && ! empty( $data['attempt_log'] ) && is_array( $data['attempt_log'] )
			? $data['attempt_log']
			: array();

		if ( empty( $attempt_log ) ) {
			self::record_event(
				$session_hash,
				$settings,
				multch_ai_client_configured_models_summary( $settings ),
				'error',
				$latency_ms,
				multch_ai_client_extract_error_code( $error ),
				$conversation
			);
			return;
		}

		$count      = count( $attempt_log );
		$per_attempt = max( 1, (int) floor( $latency_ms / $count ) );

		foreach ( $attempt_log as $attempt ) {
			$model = ! empty( $attempt['model'] ) ? (string) $attempt['model'] : 'unknown';
			$code  = ! empty( $attempt['error_code'] ) ? (string) $attempt['error_code'] : multch_ai_client_extract_error_code( $error );

			self::record_event(
				$session_hash,
				$settings,
				$model,
				'error',
				$per_attempt,
				$code,
				$conversation
			);
		}
	}

	/**
	 * @param array{id: int, public_id: string}|null $conversation
	 */
	private static function record_event(
		string $session_hash,
		array $settings,
		string $model,
		string $status,
		int $latency_ms,
		string $error_code = '',
		?array $conversation = null,
		string $model_primary = '',
		bool $used_fallback = false
	): void {
		if ( ! Multch_Plugin::is_stats_history_enabled() ) {
			return;
		}

		Multch_Telemetry::record(
			array(
				'session_hash'    => $session_hash,
				'provider'        => ! empty( $settings['provider'] ) ? (string) $settings['provider'] : 'wordpress_ai',
				'model'           => $model,
				'model_primary'   => $model_primary,
				'used_fallback'   => $used_fallback,
				'status'          => $status,
				'latency_ms'      => $latency_ms,
				'error_code'      => $error_code,
				'conversation_id' => null !== $conversation ? (int) ( $conversation['id'] ?? 0 ) : 0,
			)
		);
	}

	/**
	 * @return array<int, string>
	 */
	private static function split_chunks( string $text, int $min_size = 22, int $max_size = 64 ): array {
		$chunks = array();
		$cursor = 0;
		$length = strlen( $text );
		while ( $cursor < $length ) {
			$size    = random_int( $min_size, $max_size );
			$chunks[] = substr( $text, $cursor, $size );
			$cursor  += $size;
		}
		return $chunks;
	}
}
