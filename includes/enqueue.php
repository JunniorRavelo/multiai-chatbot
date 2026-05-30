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
				'mode' => 'floating',
			),
			$atts,
			'chatbot_widget'
		);

		$mode = in_array( $atts['mode'], array( 'floating', 'inline' ), true ) ? $atts['mode'] : 'floating';
		self::enqueue_assets( $mode );

		ob_start();
		self::render_root( $mode );
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

		wp_enqueue_script(
			'chatbot-plugin',
			CHATBOT_PLUGIN_URL . 'assets/js/chatbot.js',
			array(),
			file_exists( CHATBOT_PLUGIN_PATH . 'assets/js/chatbot.js' )
				? (string) filemtime( CHATBOT_PLUGIN_PATH . 'assets/js/chatbot.js' )
				: CHATBOT_PLUGIN_VERSION,
			true
		);

		$style_vars = array();
		if ( ! empty( $settings['style_primary'] ) ) {
			$style_vars['primary'] = (string) $settings['style_primary'];
		}
		if ( ! empty( $settings['style_accent'] ) ) {
			$style_vars['accent'] = (string) $settings['style_accent'];
		}
		if ( ! empty( $settings['style_radius'] ) ) {
			$style_vars['radius'] = (string) $settings['style_radius'];
		}

		$style_offset = trim( (string) ( $settings['style_offset'] ?? '1rem' ) );
		$style_width  = trim( (string) ( $settings['style_panel_width'] ?? '' ) );

		wp_localize_script(
			'chatbot-plugin',
			'chatbotPluginConfig',
			array(
				'restUrl'        => esc_url_raw( rest_url( 'chatbot-plugin/v1/chat' ) ),
				'streamUrl'      => esc_url_raw( home_url( '/chatbot-plugin/v1/chat/stream' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'streaming'      => ! empty( $settings['streaming_enabled'] ),
				'welcomeMessage' => (string) ( $settings['welcome_message'] ?? '' ),
				'widgetTitle'    => (string) ( $settings['widget_title'] ?? 'Agente IA' ),
				'widgetSubtitle' => (string) ( $settings['widget_subtitle'] ?? '' ),
				'style'          => array(
					'preset'        => (string) ( $settings['style_preset'] ?? 'default' ),
					'position'      => (string) ( $settings['style_position'] ?? 'bottom-right' ),
					'offset'        => $style_offset ?: '1rem',
					'panelWidth'    => $style_width,
					'launcherLabel' => ! empty( $settings['style_launcher_label'] ),
					'vars'          => $style_vars,
				),
				'mode'           => $mode,
				'i18n'           => array(
					'placeholder'   => __( 'Escribe tu mensaje…', 'chatbot-plugin-wp' ),
					'send'          => __( 'Enviar', 'chatbot-plugin-wp' ),
					'openLabel'     => __( 'Abrir chat', 'chatbot-plugin-wp' ),
					'closeLabel'    => __( 'Cerrar chat', 'chatbot-plugin-wp' ),
					'resetLabel'    => __( 'Reiniciar chat', 'chatbot-plugin-wp' ),
					'minimizeLabel' => __( 'Minimizar chat', 'chatbot-plugin-wp' ),
					'onlineLabel'   => __( 'Sistema en línea', 'chatbot-plugin-wp' ),
					'thinking'      => __( 'Pensando…', 'chatbot-plugin-wp' ),
					'errorGeneric'  => __( 'No se pudo enviar el mensaje. Intenta de nuevo.', 'chatbot-plugin-wp' ),
				),
			)
		);

		self::$assets_enqueued = true;
	}

	private static function render_root( string $mode ): void {
		static $rendered_floating = false;

		if ( 'floating' === $mode && $rendered_floating ) {
			return;
		}
		if ( 'floating' === $mode ) {
			$rendered_floating = true;
		}

		printf(
			'<div id="chatbot-plugin-root" data-mode="%s"></div>',
			esc_attr( $mode )
		);
	}
}
