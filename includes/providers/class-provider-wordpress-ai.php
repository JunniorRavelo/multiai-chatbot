<?php
/**
 * WordPress AI Client provider (WordPress 7.0+ Connectors).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Multch_Provider_WordPress_AI implements Multch_AI_Provider {

	/**
	 * @param array<int, array{role: string, content: string}> $messages
	 * @param array<string, mixed>                             $settings
	 * @return array{text: string, model: string}|WP_Error
	 */
	public function complete( string $system, array $messages, array $settings ) {
		if ( ! multch_ai_client_available() ) {
			return new WP_Error(
				'configuration_error',
				__( 'WordPress AI Client is not available. Use WordPress 7.0 or newer, or choose Ollama for a local model.', 'multiai-chatbot' ),
				array( 'status' => 503, 'error_code' => 'CONFIGURATION_ERROR' )
			);
		}

		if ( ! class_exists( 'WordPress\AiClient\Messages\DTO\UserMessage' ) ) {
			return new WP_Error(
				'configuration_error',
				__( 'WordPress AI Client libraries are not loaded.', 'multiai-chatbot' ),
				array( 'status' => 503, 'error_code' => 'CONFIGURATION_ERROR' )
			);
		}

		$split = multch_ai_client_split_messages( $messages );
		if ( '' === $split['latest'] ) {
			return new WP_Error(
				'invalid_request',
				__( 'Invalid request.', 'multiai-chatbot' ),
				array( 'status' => 400, 'error_code' => 'INVALID_REQUEST' )
			);
		}

		$preferences    = multch_ai_client_model_preferences( $settings );
		$fallback_model = ! empty( $preferences[0] ) ? (string) $preferences[0] : 'wordpress-ai';

		$builder = multch_wp_ai_client_prompt( $split['latest'] )
			->using_system_instruction( $system )
			->using_temperature( 0.2 )
			->using_max_tokens( 600 );

		if ( ! empty( $split['history'] ) ) {
			$builder = $builder->with_history( ...$split['history'] );
		}

		$result = multch_ai_client_generate_from_builder( $builder, $preferences, $fallback_model );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( '' !== $result['text'] ) {
			return $result;
		}

		// Retry without model preferences (e.g. invalid model id or unavailable fallback).
		$retry_builder = multch_wp_ai_client_prompt( $split['latest'] )
			->using_system_instruction( $system )
			->using_temperature( 0.2 )
			->using_max_tokens( 600 );

		if ( ! empty( $split['history'] ) ) {
			$retry_builder = $retry_builder->with_history( ...$split['history'] );
		}

		$retry = multch_ai_client_generate_from_builder( $retry_builder, array(), $fallback_model );
		if ( is_wp_error( $retry ) ) {
			return $retry;
		}

		if ( '' !== $retry['text'] ) {
			return $retry;
		}

		return new WP_Error(
			'model_temp_unavailable',
			__( 'The model did not return a valid response. Check Settings → Connectors and the model ID in AI Model.', 'multiai-chatbot' ),
			array( 'status' => 503, 'error_code' => 'MODEL_TEMP_UNAVAILABLE' )
		);
	}
}
