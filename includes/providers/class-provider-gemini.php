<?php
/**
 * Google Gemini provider.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chatbot_Provider_Gemini implements Chatbot_AI_Provider {

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
				__( 'Gemini API key is not configured.', 'multiai-chatbot' ),
				array( 'status' => 503, 'error_code' => 'CONFIGURATION_ERROR' )
			);
		}

		$preferred = ! empty( $settings['model'] ) ? (string) $settings['model'] : 'gemini-3.1-flash-lite';
		$pool_raw  = ! empty( $settings['model_candidates'] ) ? (string) $settings['model_candidates'] : '';
		$pool      = array_filter( array_map( 'trim', explode( ',', $pool_raw ) ) );
		$fallbacks = array( 'gemini-2.0-flash', 'gemini-2.0-flash-lite' );
		$candidates = array_values( array_unique( array_merge( array( $preferred ), $pool, $fallbacks ) ) );

		$user_prompt = self::build_user_prompt( $messages );
		$timeout     = isset( $settings['request_timeout'] ) ? (int) $settings['request_timeout'] : 22;

		foreach ( $candidates as $model ) {
			$endpoint = sprintf(
				'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
				rawurlencode( $model ),
				rawurlencode( $api_key )
			);

			$response = wp_remote_post(
				$endpoint,
				array(
					'timeout' => max( 5, $timeout ),
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode(
						array(
							'systemInstruction' => array(
								'parts' => array( array( 'text' => $system ) ),
							),
							'contents'          => array(
								array(
									'role'  => 'user',
									'parts' => array( array( 'text' => $user_prompt ) ),
								),
							),
							'generationConfig'  => array(
								'temperature'     => 0.2,
								'maxOutputTokens' => 600,
							),
						)
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( 429 === $code || 404 === $code || 400 === $code ) {
				continue;
			}
			if ( $code < 200 || $code >= 300 ) {
				continue;
			}

			$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			$text = '';
			if ( is_array( $body ) && isset( $body['candidates'][0]['content']['parts'] ) ) {
				foreach ( $body['candidates'][0]['content']['parts'] as $part ) {
					$text .= isset( $part['text'] ) ? (string) $part['text'] : '';
				}
			}
			$text = trim( self::sanitize_output( $text ) );

			if ( '' !== $text ) {
				return array(
					'text'  => $text,
					'model' => $model,
				);
			}
		}

		return new WP_Error(
			'model_temp_unavailable',
			__( 'Models are not available at this time. Please try again later.', 'multiai-chatbot' ),
			array( 'status' => 503, 'error_code' => 'MODEL_TEMP_UNAVAILABLE' )
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function resolve_api_key( array $settings ): string {
		if ( defined( 'CHATBOT_GEMINI_API_KEY' ) && CHATBOT_GEMINI_API_KEY ) {
			return (string) CHATBOT_GEMINI_API_KEY;
		}
		return ! empty( $settings['api_key'] ) ? (string) $settings['api_key'] : '';
	}

	/**
	 * @param array<int, array{role: string, content: string}> $messages
	 */
	private static function build_user_prompt( array $messages ): string {
		$lines = array();
		foreach ( $messages as $message ) {
			$role    = 'assistant' === ( $message['role'] ?? '' ) ? 'Assistant' : 'User';
			$content = trim( (string) ( $message['content'] ?? '' ) );
			if ( '' !== $content ) {
				$lines[] = "{$role}: {$content}";
			}
		}
		return implode( "\n", $lines );
	}

	private static function sanitize_output( string $text ): string {
		$text = preg_replace( '/^\*?\s*style:/im', '', $text ) ?? $text;
		return trim( $text );
	}
}
