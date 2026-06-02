<?php
/**
 * Frontend assets and shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Multch_Enqueue {

	private static bool $assets_enqueued = false;

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_global' ) );
		add_action( 'wp_footer', array( __CLASS__, 'maybe_render_global' ), 99 );
		add_shortcode( 'multch_widget', array( __CLASS__, 'shortcode' ) );
	}

	public static function maybe_enqueue_global(): void {
		if ( is_admin() ) {
			return;
		}
		$settings = Multch_Plugin::get_settings();
		if ( empty( $settings['widget_enabled'] ) ) {
			return;
		}
		self::enqueue_assets( 'floating' );
	}

	public static function maybe_render_global(): void {
		if ( is_admin() ) {
			return;
		}
		$settings = Multch_Plugin::get_settings();
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
			'multch_widget'
		);

		$mode = in_array( $atts['mode'], array( 'floating', 'inline' ), true ) ? $atts['mode'] : 'floating';
		self::enqueue_assets( $mode );

		$overrides = Multch_Admin_Settings::shortcode_style_overrides( $atts );

		ob_start();
		self::render_root( $mode, $overrides );
		return (string) ob_get_clean();
	}

	private static function enqueue_assets( string $mode ): void {
		if ( self::$assets_enqueued ) {
			return;
		}

		$settings = Multch_Plugin::get_settings();

		$css_path = MULTCH_PLUGIN_PATH . 'assets/css/chatbot.css';
		$css_ver  = file_exists( $css_path )
			? (string) filemtime( $css_path )
			: MULTCH_PLUGIN_VERSION;

		$js_path = MULTCH_PLUGIN_PATH . 'assets/js/chatbot.js';
		$js_ver  = file_exists( $js_path )
			? (string) filemtime( $js_path )
			: MULTCH_PLUGIN_VERSION;

		wp_enqueue_style(
			'multch-plugin',
			MULTCH_PLUGIN_URL . 'assets/css/chatbot.css',
			array(),
			$css_ver
		);

		$custom_css = trim( (string) ( $settings['style_custom_css'] ?? '' ) );
		if ( $custom_css !== '' ) {
			wp_add_inline_style( 'multch-plugin', $custom_css );
		}

		wp_enqueue_script(
			'multch-plugin',
			MULTCH_PLUGIN_URL . 'assets/js/chatbot.js',
			array(),
			$js_ver,
			true
		);

		$style = Multch_Admin_Settings::build_style_config( $settings );

		wp_localize_script(
			'multch-plugin',
			'multchPluginConfig',
			array(
				'restUrl'        => esc_url_raw( rest_url( 'multch/v1/chat' ) ),
				'streamUrl'      => esc_url_raw( home_url( '/multch/v1/chat/stream' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'streaming'      => ! empty( $settings['streaming_enabled'] ),
				'welcomeMessage' => Multch_Admin_Settings::localize_general_setting_value(
					'welcome_message',
					(string) ( $settings['welcome_message'] ?? '' )
				),
				'widgetTitle'    => Multch_Admin_Settings::localize_general_setting_value(
					'widget_title',
					(string) ( $settings['widget_title'] ?? 'AI Agent' )
				),
				'widgetSubtitle' => Multch_Admin_Settings::localize_general_setting_value(
					'widget_subtitle',
					(string) ( $settings['widget_subtitle'] ?? '' )
				),
				'style'          => $style,
				'credit'         => Multch_Admin_Settings::developer_credit_for_js(),
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
					'errorGeneric'   => __( 'Could not send the message. Please try again.', 'multiai-chatbot' ),
					'quotaExhausted' => __( 'The AI provider quota was reached. The chat tried your configured models. Wait a few minutes or change models in MultiAI ChatBot → AI Model.', 'multiai-chatbot' ),
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

		$root_id = multch_plugin_allocate_root_id( $mode );

		$override_attr = '';
		if ( $style_overrides !== array() ) {
			$settings      = Multch_Plugin::get_settings();
			$style_payload = Multch_Admin_Settings::build_style_config( $settings, $style_overrides );
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
