<?php
/**
 * Ollama provider.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Multch_Provider_Ollama implements Multch_AI_Provider {

	/**
	 * @param array<int, array{role: string, content: string}> $messages
	 * @param array<string, mixed>                             $settings
	 * @return array{text: string, model: string}|WP_Error
	 */
	public function complete( string $system, array $messages, array $settings ) {
		$base_url = ! empty( $settings['ollama_base_url'] ) ? rtrim( (string) $settings['ollama_base_url'], '/' ) : 'http://127.0.0.1:11434';
		$model    = ! empty( $settings['model'] ) ? (string) $settings['model'] : 'llama3';
		$timeout  = isset( $settings['request_timeout'] ) ? (int) $settings['request_timeout'] : 60;

		$ollama_messages = array(
			array(
				'role'    => 'system',
				'content' => $system,
			),
		);

		foreach ( $messages as $message ) {
			$role = 'assistant' === ( $message['role'] ?? '' ) ? 'assistant' : 'user';
			$content = trim( (string) ( $message['content'] ?? '' ) );
			if ( '' === $content ) {
				continue;
			}
			$ollama_messages[] = array(
				'role'    => $role,
				'content' => $content,
			);
		}

		$response = wp_remote_post(
			$base_url . '/api/chat',
			array(
				'timeout' => max( 10, $timeout ),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'model'    => $model,
						'messages' => $ollama_messages,
						'stream'   => false,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'provider_timeout',
				__( 'Could not connect to Ollama.', MULTCH_TEXT_DOMAIN ),
				array( 'status' => 504, 'error_code' => 'PROVIDER_TIMEOUT' )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'provider_upstream',
				__( 'Ollama returned an error.', MULTCH_TEXT_DOMAIN ),
				array( 'status' => 502, 'error_code' => 'PROVIDER_UPSTREAM' )
			);
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$text = '';
		if ( is_array( $body ) && isset( $body['message']['content'] ) ) {
			$text = trim( (string) $body['message']['content'] );
		}

		if ( '' === $text ) {
			return new WP_Error(
				'model_temp_unavailable',
				__( 'Ollama did not return a valid response.', MULTCH_TEXT_DOMAIN ),
				array( 'status' => 503, 'error_code' => 'MODEL_TEMP_UNAVAILABLE' )
			);
		}

		return array(
			'text'  => $text,
			'model' => $model,
		);
	}
}
