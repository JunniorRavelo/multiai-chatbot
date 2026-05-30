<?php
/**
 * Plugin bootstrap.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chatbot_Plugin {

	private static ?Chatbot_Plugin $instance = null;

	public static function instance(): Chatbot_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		Chatbot_Admin_Settings::init();
		Chatbot_Rest_Api::init();
		Chatbot_Enqueue::init();
	}

	public function init(): void {
		// Reserved for future hooks.
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'chatbot-plugin-wp',
			false,
			dirname( CHATBOT_PLUGIN_BASENAME ) . '/languages'
		);
	}

	public static function activate(): void {
		Chatbot_Telemetry::create_table();

		if ( false === get_option( 'chatbot_plugin_settings', false ) ) {
			add_option( 'chatbot_plugin_settings', Chatbot_Admin_Settings::default_settings() );
		}

		Chatbot_Rest_Api::register_stream_rewrite();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$settings = get_option( 'chatbot_plugin_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		return wp_parse_args( $settings, Chatbot_Admin_Settings::default_settings() );
	}
}
