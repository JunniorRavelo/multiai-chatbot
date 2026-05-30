<?php
/**
 * DeepSeek provider (OpenAI-compatible chat completions API).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chatbot_Provider_DeepSeek implements Chatbot_AI_Provider {

	/**
	 * @param array<int, array{role: string, content: string}> $messages
	 * @param array<string, mixed>                             $settings
	 * @return array{text: string, model: string}|WP_Error
	 */
	public function complete( string $system, array $messages, array $settings ) {
		$api_key = self::resolve_api_key( $settings );
		if ( '' === $api_key ) {
			return new WP_Error(
				'configuration_error',
				__( 'La API key de DeepSeek no está configurada.', 'chatbot-plugin-wp' ),
				array( 'status' => 503, 'error_code' => 'CONFIGURATION_ERROR' )
			);
		}

		$base_url   = self::resolve_base_url( $settings );
		$candidates = self::build_candidates( $settings );
		$timeout    = isset( $settings['request_timeout'] ) ? (int) $settings['request_timeout'] : 22;
		$api_messages = self::build_messages( $system, $messages );
		$last_error   = null;

		foreach ( $candidates as $model ) {
			$response = wp_remote_post(
				$base_url . '/chat/completions',
				array(
					'timeout' => max( 5, $timeout ),
					'headers' => array(
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $api_key,
					),
					'body'    => wp_json_encode(
						array(
							'model'       => $model,
							'messages'    => $api_messages,
							'temperature' => 0.2,
							'max_tokens'  => 600,
						)
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				$last_error = new WP_Error(
					'provider_timeout',
					__( 'No se pudo conectar con DeepSeek. Verifica que el servidor pueda acceder a api.deepseek.com.', 'chatbot-plugin-wp' ),
					array( 'status' => 504, 'error_code' => 'PROVIDER_TIMEOUT' )
				);
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

			if ( in_array( $code, array( 401, 402, 403 ), true ) ) {
				return self::map_api_error( $code, $body );
			}

			if ( 429 === $code ) {
				$last_error = new WP_Error(
					'rate_limit_model',
					__( 'Se alcanzó el límite de DeepSeek. Intenta nuevamente en breve.', 'chatbot-plugin-wp' ),
					array( 'status' => 429, 'error_code' => 'RATE_LIMIT_MODEL_MINUTE', 'retry_after' => 60 )
				);
				continue;
			}

			if ( 404 === $code || 400 === $code ) {
				$last_error = self::map_api_error( $code, $body );
				continue;
			}

			if ( $code < 200 || $code >= 300 ) {
				$last_error = self::map_api_error( $code, $body );
				continue;
			}

			$text = self::extract_text( $body );
			if ( '' !== $text ) {
				return array(
					'text'  => $text,
					'model' => $model,
				);
			}
		}

		if ( $last_error instanceof WP_Error ) {
			return $last_error;
		}

		return new WP_Error(
			'model_temp_unavailable',
			__( 'Los modelos de DeepSeek no están disponibles. Usa deepseek-chat o deepseek-v4-flash en Modelo IA.', 'chatbot-plugin-wp' ),
			array( 'status' => 503, 'error_code' => 'MODEL_TEMP_UNAVAILABLE' )
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return list<string>
	 */
	private static function build_candidates( array $settings ): array {
		$preferred = ! empty( $settings['model'] ) ? trim( (string) $settings['model'] ) : 'deepseek-chat';
		if ( ! self::is_deepseek_model( $preferred ) ) {
			$preferred = 'deepseek-chat';
		}

		$pool_raw = ! empty( $settings['model_candidates'] ) ? (string) $settings['model_candidates'] : '';
		$pool     = array_filter(
			array_map( 'trim', explode( ',', $pool_raw ) ),
			array( self::class, 'is_deepseek_model' )
		);
		$fallbacks = array( 'deepseek-chat', 'deepseek-reasoner' );

		return array_values( array_unique( array_merge( array( $preferred ), $pool, $fallbacks ) ) );
	}

	private static function is_deepseek_model( string $model ): bool {
		return (bool) preg_match( '/^deepseek-/i', trim( $model ) );
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function resolve_base_url( array $settings ): string {
		$base_url = ! empty( $settings['deepseek_base_url'] ) ? rtrim( (string) $settings['deepseek_base_url'], '/' ) : 'https://api.deepseek.com/v1';
		if ( 'https://api.deepseek.com' === $base_url ) {
			return 'https://api.deepseek.com/v1';
		}
		return $base_url;
	}

	/**
	 * @param array<int, array{role: string, content: string}> $messages
	 * @return list<array{role: string, content: string}>
	 */
	private static function build_messages( string $system, array $messages ): array {
		$api_messages = array(
			array(
				'role'    => 'system',
				'content' => $system,
			),
		);

		foreach ( $messages as $message ) {
			$role    = in_array( $message['role'] ?? '', array( 'user', 'assistant' ), true ) ? $message['role'] : 'user';
			$content = trim( (string) ( $message['content'] ?? '' ) );
			if ( '' === $content ) {
				continue;
			}
			$api_messages[] = array(
				'role'    => $role,
				'content' => $content,
			);
		}

		return $api_messages;
	}

	/**
	 * @param mixed $body
	 */
	private static function map_api_error( int $code, $body ): WP_Error {
		$message = '';
		if ( is_array( $body ) && isset( $body['error']['message'] ) ) {
			$message = trim( (string) $body['error']['message'] );
		}

		if ( 401 === $code || 403 === $code ) {
			return new WP_Error(
				'configuration_error',
				'' !== $message ? $message : __( 'La API key de DeepSeek no es válida.', 'chatbot-plugin-wp' ),
				array( 'status' => 503, 'error_code' => 'CONFIGURATION_ERROR' )
			);
		}

		if ( 402 === $code ) {
			return new WP_Error(
				'configuration_error',
				'' !== $message ? $message : __( 'La cuenta de DeepSeek no tiene saldo disponible.', 'chatbot-plugin-wp' ),
				array( 'status' => 503, 'error_code' => 'CONFIGURATION_ERROR' )
			);
		}

		if ( 400 === $code || 404 === $code ) {
			return new WP_Error(
				'model_temp_unavailable',
				'' !== $message ? $message : __( 'El modelo de DeepSeek no es válido o no está disponible.', 'chatbot-plugin-wp' ),
				array( 'status' => 503, 'error_code' => 'MODEL_TEMP_UNAVAILABLE' )
			);
		}

		return new WP_Error(
			'provider_upstream',
			'' !== $message ? $message : __( 'DeepSeek devolvió un error.', 'chatbot-plugin-wp' ),
			array( 'status' => 502, 'error_code' => 'PROVIDER_UPSTREAM' )
		);
	}

	/**
	 * @param mixed $body
	 */
	private static function extract_text( $body ): string {
		if ( ! is_array( $body ) || ! isset( $body['choices'][0]['message'] ) ) {
			return '';
		}

		$message = $body['choices'][0]['message'];
		$text    = isset( $message['content'] ) ? trim( (string) $message['content'] ) : '';

		if ( '' === $text && ! empty( $message['reasoning_content'] ) ) {
			$text = trim( (string) $message['reasoning_content'] );
		}

		return $text;
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function resolve_api_key( array $settings ): string {
		if ( defined( 'CHATBOT_DEEPSEEK_API_KEY' ) && CHATBOT_DEEPSEEK_API_KEY ) {
			return (string) CHATBOT_DEEPSEEK_API_KEY;
		}
		return ! empty( $settings['api_key'] ) ? (string) $settings['api_key'] : '';
	}
}
