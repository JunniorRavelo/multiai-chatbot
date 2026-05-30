<?php
/**
 * OpenAI-compatible provider.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chatbot_Provider_OpenAI implements Chatbot_AI_Provider {

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
				__( 'La API key no está configurada.', 'chatbot-plugin-wp' ),
				array( 'status' => 503, 'error_code' => 'CONFIGURATION_ERROR' )
			);
		}

		$base_url = ! empty( $settings['openai_base_url'] ) ? rtrim( (string) $settings['openai_base_url'], '/' ) : 'https://api.openai.com/v1';
		$model    = ! empty( $settings['model'] ) ? (string) $settings['model'] : 'gpt-4o-mini';
		$timeout  = isset( $settings['request_timeout'] ) ? (int) $settings['request_timeout'] : 22;

		$api_messages = array(
			array(
				'role'    => 'system',
				'content' => $system,
			),
		);

		foreach ( $messages as $message ) {
			$role = in_array( $message['role'] ?? '', array( 'user', 'assistant' ), true ) ? $message['role'] : 'user';
			$content = trim( (string) ( $message['content'] ?? '' ) );
			if ( '' === $content ) {
				continue;
			}
			$api_messages[] = array(
				'role'    => $role,
				'content' => $content,
			);
		}

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
			return new WP_Error(
				'provider_timeout',
				__( 'No se pudo conectar con el proveedor de IA.', 'chatbot-plugin-wp' ),
				array( 'status' => 504, 'error_code' => 'PROVIDER_TIMEOUT' )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 429 === $code ) {
			return new WP_Error(
				'rate_limit_model',
				__( 'Se alcanzó el límite del proveedor. Intenta nuevamente en breve.', 'chatbot-plugin-wp' ),
				array( 'status' => 429, 'error_code' => 'RATE_LIMIT_MODEL_MINUTE', 'retry_after' => 60 )
			);
		}

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'provider_upstream',
				__( 'El proveedor de IA devolvió un error.', 'chatbot-plugin-wp' ),
				array( 'status' => 502, 'error_code' => 'PROVIDER_UPSTREAM' )
			);
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$text = '';
		if ( is_array( $body ) && isset( $body['choices'][0]['message']['content'] ) ) {
			$text = trim( (string) $body['choices'][0]['message']['content'] );
		}

		if ( '' === $text ) {
			return new WP_Error(
				'model_temp_unavailable',
				__( 'El modelo no devolvió una respuesta válida.', 'chatbot-plugin-wp' ),
				array( 'status' => 503, 'error_code' => 'MODEL_TEMP_UNAVAILABLE' )
			);
		}

		return array(
			'text'  => $text,
			'model' => $model,
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function resolve_api_key( array $settings ): string {
		if ( defined( 'CHATBOT_OPENAI_API_KEY' ) && CHATBOT_OPENAI_API_KEY ) {
			return (string) CHATBOT_OPENAI_API_KEY;
		}
		return ! empty( $settings['api_key'] ) ? (string) $settings['api_key'] : '';
	}
}
