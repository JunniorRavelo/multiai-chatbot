<?php
/**
 * REST API routes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chatbot_Rest_Api {

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'init', array( __CLASS__, 'register_stream_rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_stream' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			'chatbot-plugin/v1',
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( 'Chatbot_Api_Handler', 'handle_chat' ),
				'permission_callback' => array( __CLASS__, 'permission_callback' ),
			)
		);

		register_rest_route(
			'chatbot-plugin/v1',
			'/chat/stream',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'stream_info' ),
				'permission_callback' => array( __CLASS__, 'permission_callback' ),
			)
		);
	}

	/**
	 * Stream uses template_redirect for true chunked output.
	 */
	public static function register_stream_rewrite(): void {
		add_rewrite_rule(
			'^chatbot-plugin/v1/chat/stream/?$',
			'index.php?chatbot_stream=1',
			'top'
		);
	}

	/**
	 * @param array<int, string> $vars
	 * @return array<int, string>
	 */
	public static function add_query_vars( array $vars ): array {
		$vars[] = 'chatbot_stream';
		return $vars;
	}

	public static function maybe_handle_stream(): void {
		if ( ! get_query_var( 'chatbot_stream' ) ) {
			return;
		}

		if ( 'POST' !== sanitize_text_field( wp_unslash( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) ) {
			status_header( 405 );
			exit;
		}

		$request = new WP_REST_Request( 'POST', '/chatbot-plugin/v1/chat/stream' );
		$raw     = file_get_contents( 'php://input' );
		if ( $raw ) {
			$request->set_body( $raw );
		}
		$request->set_header( 'Content-Type', 'application/json' );

		$session = isset( $_SERVER['HTTP_X_CHAT_SESSION_ID'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_CHAT_SESSION_ID'] ) ) : '';
		if ( $session ) {
			$request->set_header( 'x-chat-session-id', $session );
		}
		$nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
		if ( $nonce ) {
			$request->set_header( 'x-wp-nonce', $nonce );
		}

		Chatbot_Api_Handler::dispatch_stream( $request );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function permission_callback( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'x-wp-nonce' );
		if ( ! Chatbot_Api_Handler::verify_nonce( $nonce ) ) {
			return new WP_Error(
				'forbidden',
				__( 'Invalid nonce.', 'multiai-chatbot' ),
				array( 'status' => 403, 'errorCode' => 'ORIGIN_FORBIDDEN' )
			);
		}

		$settings = Chatbot_Plugin::get_settings();
		if ( ! Chatbot_Api_Handler::verify_origin( $settings ) ) {
			return new WP_Error(
				'forbidden',
				__( 'Origin not allowed.', 'multiai-chatbot' ),
				array( 'status' => 403, 'errorCode' => 'ORIGIN_FORBIDDEN' )
			);
		}

		return true;
	}

	/**
	 * Inform clients that stream is at rewrite URL (same nonce rules).
	 */
	public static function stream_info( WP_REST_Request $request ): WP_REST_Response {
		$settings = Chatbot_Plugin::get_settings();
		if ( empty( $settings['streaming_enabled'] ) ) {
			return new WP_REST_Response( array( 'error' => __( 'Streaming disabled.', 'multiai-chatbot' ) ), 404 );
		}

		return new WP_REST_Response(
			array(
				'streamUrl' => home_url( '/chatbot-plugin/v1/chat/stream' ),
				'hint'      => __( 'Use POST with the same body and headers as /chat.', 'multiai-chatbot' ),
			),
			200
		);
	}
}
