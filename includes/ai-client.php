<?php
/**
 * WordPress AI Client helpers.
 *
 * @package Multch_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the site can use the WordPress AI Client API.
 */
function multch_ai_client_available(): bool {
	return function_exists( 'wp_ai_client_prompt' );
}

/**
 * Start a WordPress AI Client prompt (WP 7.0+).
 *
 * @param string $prompt Latest user message.
 * @return object|null Prompt builder, or null when the API is unavailable.
 */
function multch_wp_ai_client_prompt( string $prompt ) {
	if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
		return null;
	}

	// phpcs:ignore wp_function_not_compatible_with_requires_wp -- Optional WP 7 API; guarded by function_exists() above.
	return wp_ai_client_prompt( $prompt );
}

/**
 * Admin URL for Settings → Connectors (WordPress 7.0+).
 */
function multch_connectors_admin_url(): string {
	if ( function_exists( 'menu_page_url' ) ) {
		$url = menu_page_url( 'wp-connectors', false );
		if ( is_string( $url ) && '' !== $url ) {
			return $url;
		}
	}

	return admin_url( 'options-general.php?page=wp-connectors' );
}

/**
 * WordPress connectors registry (WP 7.0+). Empty when the API is unavailable.
 *
 * @return array<string, mixed>
 */
function multch_wp_get_connectors(): array {
	if ( ! function_exists( 'wp_get_connectors' ) ) {
		return array();
	}

	// phpcs:ignore wp_function_not_compatible_with_requires_wp -- Optional WP 7 API; guarded by function_exists() above.
	return wp_get_connectors();
}

/**
 * Legacy cloud provider IDs migrated to the WordPress AI Client.
 *
 * @return list<string>
 */
function multch_legacy_cloud_provider_ids(): array {
	return array( 'gemini', 'deepseek', 'openai_compatible' );
}

/**
 * Whether an AI connector has credentials and is ready in the WP AI Client registry.
 */
function multch_is_ai_connector_connected( string $connector_id ): bool {
	if ( ! class_exists( 'WordPress\AiClient\AiClient' ) ) {
		return false;
	}

	try {
		$registry = \WordPress\AiClient\AiClient::defaultRegistry();
		return $registry->hasProvider( $connector_id ) && $registry->isProviderConfigured( $connector_id );
	} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		return false;
	}
}

/**
 * AI connectors for the admin Model tab (mirrors Settings → Connectors status).
 *
 * @return array{
 *     connectors: list<array{
 *         id: string,
 *         name: string,
 *         description: string,
 *         logo_url: string,
 *         status: string,
 *         status_label: string
 *     }>,
 *     models: list<array{id: string, name: string, provider_id: string, provider_name: string}>,
 *     has_connected: bool,
 *     client_available: bool
 * }
 */
function multch_get_ai_connectors_admin_state( bool $refresh_models = false ): array {
	$empty = array(
		'connectors'         => array(),
		'models'             => array(),
		'has_connected'      => false,
		'client_available'   => multch_ai_client_available(),
	);

	if ( ! function_exists( 'wp_get_connectors' ) ) {
		return $empty;
	}

	if ( $refresh_models ) {
		delete_transient( 'multch_ai_models_cache' );
	}

	$connectors_out = array();
	$has_connected  = false;

	foreach ( multch_wp_get_connectors() as $connector_id => $connector ) {
		if ( ! is_array( $connector ) || ( $connector['type'] ?? '' ) !== 'ai_provider' ) {
			continue;
		}

		$status = multch_resolve_ai_connector_status( (string) $connector_id, $connector );
		if ( 'connected' === $status ) {
			$has_connected = true;
		}

		$connectors_out[] = array(
			'id'            => (string) $connector_id,
			'name'          => (string) ( $connector['name'] ?? $connector_id ),
			'description'   => (string) ( $connector['description'] ?? '' ),
			'logo_url'      => (string) ( $connector['logo_url'] ?? '' ),
			'status'        => $status,
			'status_label'  => multch_ai_connector_status_label( $status ),
		);
	}

	$models = multch_get_available_text_models_for_admin();

	return array(
		'connectors'       => $connectors_out,
		'models'           => $models,
		'has_connected'    => $has_connected,
		'client_available' => multch_ai_client_available(),
	);
}

/**
 * @param array<string, mixed> $connector Connector data from wp_get_connectors().
 */
function multch_resolve_ai_connector_status( string $connector_id, array $connector ): string {
	$plugin = is_array( $connector['plugin'] ?? null ) ? $connector['plugin'] : array();
	$file   = (string) ( $plugin['file'] ?? '' );

	if ( '' !== $file ) {
		$path = wp_normalize_path( WP_PLUGIN_DIR . '/' . $file );
		if ( ! file_exists( $path ) ) {
			return 'not_installed';
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active( $file ) ) {
			return 'inactive';
		}
	} elseif ( isset( $plugin['is_active'] ) && is_callable( $plugin['is_active'] ) ) {
		if ( ! (bool) call_user_func( $plugin['is_active'] ) ) {
			return 'inactive';
		}
	}

	if ( ! class_exists( 'WordPress\AiClient\AiClient' ) ) {
		return 'unavailable';
	}

	try {
		$registry = \WordPress\AiClient\AiClient::defaultRegistry();
		if ( ! $registry->hasProvider( $connector_id ) ) {
			return 'unavailable';
		}
		if ( $registry->isProviderConfigured( $connector_id ) ) {
			return 'connected';
		}
	} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		return 'unavailable';
	}

	return 'not_configured';
}

/**
 * Connector descriptions from core may follow the site locale inconsistently; provide plugin strings.
 */
function multch_localize_connector_description( string $connector_id, string $fallback ): string {
	$map = array(
		'google'    => __( 'Text and image generation with Gemini and Imagen.', 'multiai-chatbot' ),
		'anthropic' => __( 'Text generation with Claude.', 'multiai-chatbot' ),
		'openai'    => __( 'Text and image generation with GPT and DALL·E.', 'multiai-chatbot' ),
	);

	return $map[ $connector_id ] ?? $fallback;
}

/**
 * @return string Translated short label for connector status badges.
 */
function multch_ai_connector_status_label( string $status ): string {
	switch ( $status ) {
		case 'connected':
			return __( 'Connected', 'multiai-chatbot' );
		case 'not_configured':
			return __( 'Not configured', 'multiai-chatbot' );
		case 'inactive':
			return __( 'Plugin inactive', 'multiai-chatbot' );
		case 'not_installed':
			return __( 'Not installed', 'multiai-chatbot' );
		default:
			return __( 'Unavailable', 'multiai-chatbot' );
	}
}

/**
 * Text-generation models from configured connectors (cached briefly for admin).
 *
 * @return list<array{id: string, name: string, provider_id: string, provider_name: string}>
 */
function multch_get_available_text_models_for_admin(): array {
	$cached = get_transient( 'multch_ai_models_cache' );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	if ( ! class_exists( 'WordPress\AiClient\AiClient' )
		|| ! class_exists( 'WordPress\AiClient\Providers\Models\DTO\ModelRequirements' )
		|| ! class_exists( 'WordPress\AiClient\Providers\Models\Enums\CapabilityEnum' ) ) {
		return array();
	}

	$models = array();

	try {
		$capability_class = 'WordPress\AiClient\Providers\Models\Enums\CapabilityEnum';
		$requirements_class = 'WordPress\AiClient\Providers\Models\DTO\ModelRequirements';

		$requirements = new $requirements_class(
			array(
				$capability_class::textGeneration(),
				$capability_class::chatHistory(),
			),
			array()
		);

		$registry = \WordPress\AiClient\AiClient::defaultRegistry();
		$groups   = $registry->findModelsMetadataForSupport( $requirements );

		foreach ( $groups as $group ) {
			if ( ! is_object( $group ) ) {
				continue;
			}

			$provider_meta = method_exists( $group, 'getProvider' ) ? $group->getProvider() : null;

			$provider_id   = 'unknown';
			$provider_name = 'unknown';
			if ( is_object( $provider_meta ) ) {
				if ( method_exists( $provider_meta, 'getId' ) ) {
					$provider_id = (string) $provider_meta->getId();
				}
				if ( method_exists( $provider_meta, 'getName' ) ) {
					$provider_name = (string) $provider_meta->getName();
				}
			}

			if ( ! multch_is_ai_connector_connected( $provider_id ) ) {
				continue;
			}

			$model_list = method_exists( $group, 'getModels' ) ? $group->getModels() : array();
			foreach ( $model_list as $model_meta ) {
				if ( ! is_object( $model_meta ) || ! method_exists( $model_meta, 'getId' ) ) {
					continue;
				}
				$model_id = trim( (string) $model_meta->getId() );
				if ( '' === $model_id ) {
					continue;
				}
				$model_name = method_exists( $model_meta, 'getName' )
					? trim( (string) $model_meta->getName() )
					: $model_id;

				$models[] = array(
					'id'            => $model_id,
					'name'          => '' !== $model_name ? $model_name : $model_id,
					'provider_id'   => $provider_id,
					'provider_name' => $provider_name,
				);
			}
		}
	} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		$models = array();
	}

	// Deduplicate by model id, preserve first provider association.
	$seen = array();
	$unique = array();
	foreach ( $models as $row ) {
		if ( isset( $seen[ $row['id'] ] ) ) {
			continue;
		}
		$seen[ $row['id'] ] = true;
		$unique[]           = $row;
	}

	set_transient( 'multch_ai_models_cache', $unique, 5 * MINUTE_IN_SECONDS );

	return $unique;
}

/**
 * @param array<int, array{role: string, content: string}> $messages
 * @return array{latest: string, history: list<object>}
 */
function multch_ai_client_split_messages( array $messages ): array {
	$latest  = '';
	$history = $messages;

	if ( ! empty( $history ) ) {
		$last = array_pop( $history );
		if ( 'user' === ( $last['role'] ?? '' ) ) {
			$latest = trim( (string) ( $last['content'] ?? '' ) );
		} else {
			$history[] = $last;
		}
	}

	return array(
		'latest'  => $latest,
		'history' => multch_ai_client_build_history_messages( $history ),
	);
}

/**
 * @param array<int, array{role: string, content: string}> $messages
 * @return list<object>
 */
function multch_ai_client_build_history_messages( array $messages ): array {
	if ( ! class_exists( 'WordPress\AiClient\Messages\DTO\MessagePart' ) ) {
		return array();
	}

	$part_class  = 'WordPress\AiClient\Messages\DTO\MessagePart';
	$user_class  = 'WordPress\AiClient\Messages\DTO\UserMessage';
	$model_class = 'WordPress\AiClient\Messages\DTO\ModelMessage';
	$built       = array();

	foreach ( $messages as $turn ) {
		$text = trim( (string) ( $turn['content'] ?? '' ) );
		if ( '' === $text ) {
			continue;
		}

		$part = new $part_class( $text );
		if ( 'user' === ( $turn['role'] ?? '' ) ) {
			$built[] = new $user_class( array( $part ) );
		} else {
			$built[] = new $model_class( array( $part ) );
		}
	}

	return $built;
}

/**
 * @param array<string, mixed> $settings
 * @return list<string>
 */
function multch_ai_client_model_preferences( array $settings ): array {
	$preferred = ! empty( $settings['model'] ) ? trim( (string) $settings['model'] ) : '';
	$pool_raw  = ! empty( $settings['model_candidates'] ) ? (string) $settings['model_candidates'] : '';
	$pool      = array_filter( array_map( 'trim', explode( ',', $pool_raw ) ) );
	$merged    = array_values(
		array_unique(
			array_filter(
				array_merge(
					$preferred ? array( $preferred ) : array(),
					$pool
				)
			)
		)
	);

	return $merged;
}

/**
 * @param mixed $result GenerativeAiResult from the AI Client.
 */
function multch_ai_client_extract_text( $result ): string {
	if ( is_string( $result ) ) {
		return trim( $result );
	}

	if ( ! is_object( $result ) ) {
		return '';
	}

	if ( method_exists( $result, 'toText' ) ) {
		try {
			$text = trim( (string) $result->toText() );
			if ( '' !== $text ) {
				return $text;
			}
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Try legacy extraction below.
		}
	}

	if ( ! method_exists( $result, 'toMessage' ) ) {
		return '';
	}

	$message = $result->toMessage();
	if ( ! is_object( $message ) || ! method_exists( $message, 'getParts' ) ) {
		return '';
	}

	$text = '';
	foreach ( $message->getParts() as $part ) {
		if ( ! is_object( $part ) || ! method_exists( $part, 'getText' ) ) {
			continue;
		}

		$part_text = $part->getText();
		if ( null === $part_text || '' === trim( (string) $part_text ) ) {
			continue;
		}

		if ( method_exists( $part, 'getChannel' ) ) {
			$channel = $part->getChannel();
			if ( is_object( $channel ) && method_exists( $channel, 'isContent' ) && ! $channel->isContent() ) {
				continue;
			}
		}

		$text .= (string) $part_text;
	}

	return trim( $text );
}

/**
 * @param mixed  $result   GenerativeAiResult from the AI Client.
 * @param string $fallback Model name when metadata is unavailable.
 */
function multch_ai_client_extract_model( $result, string $fallback ): string {
	if ( ! is_object( $result ) || ! method_exists( $result, 'getModelMetadata' ) ) {
		return $fallback;
	}

	$meta = $result->getModelMetadata();
	if ( ! is_object( $meta ) ) {
		return $fallback;
	}

	if ( method_exists( $meta, 'getId' ) ) {
		$id = trim( (string) $meta->getId() );
		if ( '' !== $id ) {
			return $id;
		}
	}

	if ( method_exists( $meta, 'getName' ) ) {
		$name = trim( (string) $meta->getName() );
		if ( '' !== $name ) {
			return $name;
		}
	}

	return $fallback;
}

/**
 * Runs a configured prompt builder and returns text plus model id.
 *
 * @param object               $builder     WP_AI_Client_Prompt_Builder instance.
 * @param list<string>         $preferences Model preference list (may be empty).
 * @param string               $fallback_model
 * @return array{text: string, model: string}|WP_Error
 */
function multch_ai_client_generate_from_builder( $builder, array $preferences, string $fallback_model ) {
	if ( ! empty( $preferences ) && method_exists( $builder, 'using_model_preference' ) ) {
		$builder = $builder->using_model_preference( ...$preferences );
	}

	if ( method_exists( $builder, 'is_supported_for_text_generation' ) && ! $builder->is_supported_for_text_generation() ) {
		return new WP_Error(
			'configuration_error',
			__( 'No AI model is available. Open Settings → Connectors and connect a provider.', 'multiai-chatbot' ),
			array( 'status' => 503, 'error_code' => 'CONFIGURATION_ERROR' )
		);
	}

	$result = $builder->generate_text_result();
	if ( is_wp_error( $result ) ) {
		return multch_ai_client_map_error( $result );
	}

	$text  = multch_ai_client_extract_text( $result );
	$model = multch_ai_client_extract_model( $result, $fallback_model );

	return array(
		'text'  => $text,
		'model' => $model,
	);
}

/**
 * @param WP_Error $error Error from wp_ai_client_prompt().
 * @return WP_Error
 */
function multch_ai_client_map_error( WP_Error $error ): WP_Error {
	$code    = $error->get_error_code();
	$message = $error->get_error_message();
	$data    = $error->get_error_data();
	$status  = 503;
	$app_code = 'PROVIDER_UPSTREAM';

	if ( is_array( $data ) && isset( $data['status'] ) ) {
		$status = (int) $data['status'];
	}

	if ( str_contains( strtolower( $code ), 'rate' ) || 429 === $status ) {
		return new WP_Error(
			'rate_limit_model',
			__( 'Provider rate limit reached. Please try again shortly.', 'multiai-chatbot' ),
			array(
				'status'      => 429,
				'error_code'  => 'RATE_LIMIT_MODEL_MINUTE',
				'retry_after' => 60,
			)
		);
	}

	if ( str_contains( strtolower( $code ), 'config' ) || str_contains( strtolower( $message ), 'not configured' ) ) {
		return new WP_Error(
			'configuration_error',
			__( 'No AI provider is configured. Open Settings → Connectors and connect a provider.', 'multiai-chatbot' ),
			array( 'status' => 503, 'error_code' => 'CONFIGURATION_ERROR' )
		);
	}

	if ( str_contains( strtolower( $code ), 'timeout' ) || str_contains( strtolower( $message ), 'timeout' ) ) {
		return new WP_Error(
			'provider_timeout',
			__( 'Could not connect to the AI provider.', 'multiai-chatbot' ),
			array( 'status' => 504, 'error_code' => 'PROVIDER_TIMEOUT' )
		);
	}

	return new WP_Error(
		'provider_upstream',
		'' !== $message ? $message : __( 'The AI provider returned an error.', 'multiai-chatbot' ),
		array( 'status' => max( 400, min( 599, $status ) ), 'error_code' => $app_code )
	);
}
