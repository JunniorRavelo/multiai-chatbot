<?php
/**
 * Chat API handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chatbot_Api_Handler {

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_chat( WP_REST_Request $request ) {
		$started = microtime( true );
		$settings = Chatbot_Plugin::get_settings();
		$session_id = self::get_session_id( $request );
		$session_hash = Chatbot_Telemetry::hash_session( $session_id );

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

		$provider_id = ! empty( $settings['provider'] ) ? (string) $settings['provider'] : 'gemini';
		$provider = self::get_provider( $provider_id );
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
			? (string) $settings['system_prompt']
			: __( 'Eres un asistente útil del sitio web. Responde en español de forma clara y breve.', 'chatbot-plugin-wp' );

		$messages = self::build_messages( $parsed['message'], $parsed['history'] );

		$provider_settings = array(
			'api_key'           => ! empty( $settings['api_key'] ) ? (string) $settings['api_key'] : '',
			'model'             => ! empty( $settings['model'] ) ? (string) $settings['model'] : '',
			'model_candidates'  => ! empty( $settings['model_candidates'] ) ? (string) $settings['model_candidates'] : '',
			'ollama_base_url'   => ! empty( $settings['ollama_base_url'] ) ? (string) $settings['ollama_base_url'] : 'http://127.0.0.1:11434',
			'openai_base_url'   => ! empty( $settings['openai_base_url'] ) ? (string) $settings['openai_base_url'] : 'https://api.openai.com/v1',
			'request_timeout'   => ! empty( $settings['request_timeout'] ) ? (int) $settings['request_timeout'] : 22,
		);

		$result = $provider->complete( $system, $messages, $provider_settings );
		$latency = (int) ( ( microtime( true ) - $started ) * 1000 );

		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$status     = is_array( $error_data ) && isset( $error_data['status'] ) ? (int) $error_data['status'] : 500;
			$error_code = is_array( $error_data ) && isset( $error_data['error_code'] ) ? (string) $error_data['error_code'] : 'SERVER_ERROR';
			$retry_after = is_array( $error_data ) && isset( $error_data['retry_after'] ) ? (int) $error_data['retry_after'] : 0;

			self::record_event( $session_hash, $settings, '', 'error', $latency, $error_code );

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

		self::record_event( $session_hash, $settings, $result['model'], 'success', $latency );

		return new WP_REST_Response(
			array(
				'answer' => $result['text'],
				'meta'   => array(
					'model'    => $result['model'],
					'provider' => $provider_id,
				),
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_stream( WP_REST_Request $request ) {
		$settings = Chatbot_Plugin::get_settings();
		if ( empty( $settings['streaming_enabled'] ) ) {
			return new WP_REST_Response( array( 'error' => __( 'Streaming deshabilitado.', 'chatbot-plugin-wp' ) ), 404 );
		}

		$internal = new WP_REST_Request( 'POST', '/chatbot-plugin/v1/chat' );
		$internal->set_body( $request->get_body() );
		$internal->set_header( 'Content-Type', 'application/json' );
		$session = $request->get_header( 'x-chat-session-id' );
		if ( $session ) {
			$internal->set_header( 'x-chat-session-id', $session );
		}
		$nonce = $request->get_header( 'x-wp-nonce' );
		if ( $nonce ) {
			$internal->set_header( 'x-wp-nonce', $nonce );
		}

		$response = rest_do_request( $internal );
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

		return new WP_REST_Response( null, 200, array(
			'Content-Type'  => 'text/plain; charset=utf-8',
			'X-Chat-Stream' => 'chunked-text',
			'X-Chat-Model'  => isset( $data['meta']['model'] ) ? (string) $data['meta']['model'] : '',
		) );
	}

	/**
	 * Stream response as plain text (custom dispatch).
	 */
	public static function dispatch_stream( WP_REST_Request $request ): void {
		$settings = Chatbot_Plugin::get_settings();
		if ( empty( $settings['streaming_enabled'] ) ) {
			status_header( 404 );
			echo wp_json_encode( array( 'error' => __( 'Streaming deshabilitado.', 'chatbot-plugin-wp' ) ) );
			exit;
		}

		$nonce = $request->get_header( 'x-wp-nonce' );
		if ( ! self::verify_nonce( $nonce ) ) {
			status_header( 403 );
			echo wp_json_encode( array( 'error' => __( 'Nonce inválido.', 'chatbot-plugin-wp' ), 'errorCode' => 'ORIGIN_FORBIDDEN' ) );
			exit;
		}

		$internal = new WP_REST_Request( 'POST', '/chatbot-plugin/v1/chat' );
		$internal->set_body( $request->get_body() );
		$internal->set_header( 'Content-Type', 'application/json' );
		$session = $request->get_header( 'x-chat-session-id' );
		if ( $session ) {
			$internal->set_header( 'x-chat-session-id', $session );
		}
		$internal->set_header( 'x-wp-nonce', $nonce );

		$response = rest_do_request( $internal );
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
		return (bool) wp_verify_nonce( $nonce ? $nonce : '', 'wp_rest' );
	}

	/**
	 * @return Chatbot_AI_Provider|WP_Error
	 */
	private static function get_provider( string $id ) {
		switch ( $id ) {
			case 'gemini':
				return new Chatbot_Provider_Gemini();
			case 'ollama':
				return new Chatbot_Provider_Ollama();
			case 'openai_compatible':
				return new Chatbot_Provider_OpenAI();
			default:
				return new WP_Error(
					'configuration_error',
					__( 'Proveedor de IA no válido.', 'chatbot-plugin-wp' ),
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
				__( 'Solicitud inválida.', 'chatbot-plugin-wp' ),
				array( 'status' => 400, 'error_code' => 'INVALID_REQUEST' )
			);
		}

		$message = isset( $body['message'] ) ? trim( (string) $body['message'] ) : '';
		if ( strlen( $message ) < 2 || strlen( $message ) > 700 ) {
			return new WP_Error(
				'invalid_request',
				__( 'El mensaje debe tener entre 2 y 700 caracteres.', 'chatbot-plugin-wp' ),
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

		return array(
			'message' => $message,
			'history' => $history,
		);
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
		$limit = isset( $settings['rate_limit_per_minute'] ) ? max( 1, (int) $settings['rate_limit_per_minute'] ) : 10;
		$ip    = self::get_client_ip();
		$key   = 'chatbot_rl_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return new WP_REST_Response(
				array(
					'error'      => __( 'Demasiadas solicitudes. Espera un momento.', 'chatbot-plugin-wp' ),
					'errorCode'  => 'RATE_LIMIT_GENERAL',
					'retryAfter' => 60,
				),
				429
			);
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	private static function get_client_ip(): string {
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			return sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}
		if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			return sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_REAL_IP'] ) );
		}
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts = explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] );
			return sanitize_text_field( trim( $parts[0] ) );
		}
		return 'unknown';
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function record_event( string $session_hash, array $settings, string $model, string $status, int $latency_ms, string $error_code = '' ): void {
		Chatbot_Telemetry::record(
			array(
				'session_hash' => $session_hash,
				'provider'     => ! empty( $settings['provider'] ) ? (string) $settings['provider'] : 'gemini',
				'model'        => $model,
				'status'       => $status,
				'latency_ms'   => $latency_ms,
				'error_code'   => $error_code,
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
