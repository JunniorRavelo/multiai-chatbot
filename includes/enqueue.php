<?php
/**
 * Frontend assets and shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chatbot_Enqueue {

	private static bool $assets_enqueued = false;

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_global' ) );
		add_action( 'wp_footer', array( __CLASS__, 'maybe_render_global' ), 99 );
		add_shortcode( 'chatbot_widget', array( __CLASS__, 'shortcode' ) );
	}

	public static function maybe_enqueue_global(): void {
		if ( is_admin() ) {
			return;
		}
		$settings = Chatbot_Plugin::get_settings();
		if ( empty( $settings['widget_enabled'] ) ) {
			return;
		}
		self::enqueue_assets( 'floating' );
	}

	public static function maybe_render_global(): void {
		if ( is_admin() ) {
			return;
		}
		$settings = Chatbot_Plugin::get_settings();
		if ( empty( $settings['widget_enabled'] ) ) {
			return;
		}
		self::render_root( 'floating' );
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public static function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'mode'        => 'floating',
				'preset'      => '',
				'position'    => '',
				'primary'     => '',
				'accent'      => '',
				'radius'      => '',
				'offset'      => '',
				'panel_width' => '',
				'bg'          => '',
				'fg'          => '',
			),
			$atts,
			'chatbot_widget'
		);

		$mode = in_array( $atts['mode'], array( 'floating', 'inline' ), true ) ? $atts['mode'] : 'floating';
		self::enqueue_assets( $mode );

		$overrides = Chatbot_Admin_Settings::shortcode_style_overrides( $atts );

		ob_start();
		self::render_root( $mode, $overrides );
		return (string) ob_get_clean();
	}

	private static function enqueue_assets( string $mode ): void {
		if ( self::$assets_enqueued ) {
			return;
		}

		$settings = Chatbot_Plugin::get_settings();

		wp_enqueue_style(
			'chatbot-plugin',
			CHATBOT_PLUGIN_URL . 'assets/css/chatbot.css',
			array(),
			file_exists( CHATBOT_PLUGIN_PATH . 'assets/css/chatbot.css' )
				? (string) filemtime( CHATBOT_PLUGIN_PATH . 'assets/css/chatbot.css' )
				: CHATBOT_PLUGIN_VERSION
		);

		$custom_css = trim( (string) ( $settings['style_custom_css'] ?? '' ) );
		if ( $custom_css !== '' ) {
			wp_add_inline_style( 'chatbot-plugin', $custom_css );
		}

		wp_enqueue_script(
			'chatbot-plugin',
			CHATBOT_PLUGIN_URL . 'assets/js/chatbot.js',
			array(),
			file_exists( CHATBOT_PLUGIN_PATH . 'assets/js/chatbot.js' )
				? (string) filemtime( CHATBOT_PLUGIN_PATH . 'assets/js/chatbot.js' )
				: CHATBOT_PLUGIN_VERSION,
			true
		);

		$style = Chatbot_Admin_Settings::build_style_config( $settings );

		wp_localize_script(
			'chatbot-plugin',
			'chatbotPluginConfig',
			array(
				'restUrl'        => esc_url_raw( rest_url( 'chatbot-plugin/v1/chat' ) ),
				'streamUrl'      => esc_url_raw( home_url( '/chatbot-plugin/v1/chat/stream' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'streaming'      => ! empty( $settings['streaming_enabled'] ),
				'welcomeMessage' => (string) ( $settings['welcome_message'] ?? '' ),
				'widgetTitle'    => (string) ( $settings['widget_title'] ?? 'AI Agent' ),
				'widgetSubtitle' => (string) ( $settings['widget_subtitle'] ?? '' ),
				'style'          => $style,
				'credit'         => Chatbot_Admin_Settings::developer_credit_for_js(),
				'mode'           => $mode,
				'i18n'           => array(
					'placeholder'   => __( 'Type your message…', 'multiai-chatbot' ),
					'send'          => __( 'Send', 'multiai-chatbot' ),
					'openLabel'     => __( 'Open chat', 'multiai-chatbot' ),
					'closeLabel'    => __( 'Close chat', 'multiai-chatbot' ),
					'resetLabel'    => __( 'Reset chat', 'multiai-chatbot' ),
					'minimizeLabel' => __( 'Minimize chat', 'multiai-chatbot' ),
					'onlineLabel'   => __( 'System online', 'multiai-chatbot' ),
					'thinking'      => __( 'Thinking…', 'multiai-chatbot' ),
					'welcomeLabel'  => __( 'Welcome message', 'multiai-chatbot' ),
					'errorGeneric'  => __( 'Could not send the message. Please try again.', 'multiai-chatbot' ),
				),
			)
		);

		self::$assets_enqueued = true;
	}

	/**
	 * @param array<string, mixed> $style_overrides
	 */
	private static function render_root( string $mode, array $style_overrides = array() ): void {
		static $rendered_floating = false;

		if ( 'floating' === $mode && $rendered_floating ) {
			return;
		}
		if ( 'floating' === $mode ) {
			$rendered_floating = true;
		}

		$root_id = chatbot_plugin_allocate_root_id( $mode );

		$override_attr = '';
		if ( $style_overrides !== array() ) {
			$settings      = Chatbot_Plugin::get_settings();
			$style_payload = Chatbot_Admin_Settings::build_style_config( $settings, $style_overrides );
			$override_attr = sprintf(
				' data-style-override="%s"',
				esc_attr( wp_json_encode( $style_payload ) )
			);
		}

		printf(
			'<div id="%1$s" class="maicb-root" data-maicb-root data-mode="%2$s"%3$s></div>',
			esc_attr( $root_id ),
			esc_attr( $mode ),
			$override_attr // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr on JSON
		);
	}
}
