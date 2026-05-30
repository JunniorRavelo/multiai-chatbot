<?php
/**
 * Admin settings panel.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chatbot_Admin_Settings {

	const OPTION_KEY = 'chatbot_plugin_settings';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_chatbot_export_csv', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_chatbot_export_history_csv', array( __CLASS__, 'export_history_csv' ) );
		add_action( 'admin_post_chatbot_purge_history', array( __CLASS__, 'purge_history' ) );
		add_action( 'admin_post_chatbot_purge_telemetry', array( __CLASS__, 'purge_telemetry' ) );
		add_action( 'wp_ajax_chatbot_history_detail', array( __CLASS__, 'ajax_history_detail' ) );
		add_action( 'wp_ajax_chatbot_delete_conversation', array( __CLASS__, 'ajax_delete_conversation' ) );
		add_filter( 'wp_redirect', array( __CLASS__, 'preserve_tab_on_settings_redirect' ), 10, 2 );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'widget_enabled'                 => true,
			'welcome_message'                => "Hello. I'm an AI agent. I may make mistakes; please verify important information before making decisions.\n\nHow can I help you?",
			'system_prompt'                  => 'You are a helpful website assistant. Respond clearly, briefly, and kindly. If you don\'t know something, say so honestly.',
			'streaming_enabled'              => true,
			'allowed_origins'                => '',
			'cache_ttl_seconds'              => 1800,
			'telemetry_log_path'             => '',
			'rate_limit_per_minute'          => 10,
			'rate_limit_per_day'             => 30,
			'rate_limit_model_per_minute'    => 6,
			'rate_limit_model_per_day'       => 24,
			'rate_limit_soft_threshold'      => 0.8,
			'ip_suspend_after_violations'    => 3,
			'ip_suspend_seconds'             => 900,
			'internal_chat_base_url'         => '',
			'provider'                       => 'gemini',
			'api_key'                        => '',
			'model'                          => 'gemini-3.1-flash-lite',
			'model_candidates'               => 'gemini-3-flash,gemini-3.1-flash-lite,gemini-2.5-flash,gemini-2.5-flash-lite,gemini-3.1-flash-tts,gemini-2.5-flash-tts',
			'ollama_base_url'       => 'http://127.0.0.1:11434',
			'openai_base_url'       => 'https://api.openai.com/v1',
			'deepseek_base_url'     => 'https://api.deepseek.com/v1',
			'request_timeout'       => 22,
			'style_preset'          => 'default',
			'style_primary'         => '',
			'style_accent'          => '',
			'style_radius'          => '',
			'style_position'        => 'bottom-right',
			'style_offset'          => '1rem',
			'style_panel_width'     => '',
			'style_launcher_label'  => true,
			'style_bg'              => '',
			'style_fg'              => '',
			'style_font_family'     => 'system',
			'style_panel_max_height' => '',
			'style_z_index'         => 0,
			'style_reduce_motion'   => false,
			'style_preset_auto'     => false,
			'style_preset_auto_dark' => 'dark-glass',
			'style_show_credit'     => false,
			'style_custom_css'      => '',
			'widget_title'          => 'AI Agent',
			'widget_subtitle'       => 'System online',
			'history_retention_days' => 0,
			'telemetry_retention_days' => 0,
		);
	}

	public static function register_menu(): void {
		add_menu_page(
			__( 'MultiAI ChatBot', 'chatbot-plugin-wp' ),
			__( 'MultiAI ChatBot', 'chatbot-plugin-wp' ),
			'manage_options',
			'chatbot-plugin',
			array( __CLASS__, 'render_page' ),
			'dashicons-format-chat',
			58
		);
	}

	public static function register_settings(): void {
		register_setting(
			'chatbot_plugin_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::default_settings(),
			)
		);
	}

	/**
	 * @param array<string, mixed>|mixed $input
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( $input ): array {
		$defaults = self::default_settings();
		$input    = is_array( $input ) ? $input : array();
		$current  = self::get_stored_settings();
		$out      = $current;
		$tab      = isset( $_POST['chatbot_admin_tab'] ) ? sanitize_key( wp_unslash( (string) $_POST['chatbot_admin_tab'] ) ) : '';

		switch ( $tab ) {
			case 'general':
				self::sanitize_general_settings( $input, $current, $defaults, $out );
				break;
			case 'model':
				self::sanitize_model_settings( $input, $current, $defaults, $out );
				break;
			case 'security':
				self::sanitize_security_settings( $input, $current, $defaults, $out );
				break;
			case 'style':
				self::sanitize_style_settings( $input, $current, $defaults, $out );
				break;
			default:
				self::sanitize_all_settings( $input, $current, $defaults, $out );
				break;
		}

		$has_errors = ! empty(
			array_filter(
				get_settings_errors( 'chatbot_plugin_group' ),
				static function ( $error ) {
					return 'error' === ( $error['type'] ?? '' );
				}
			)
		);

		if ( ! $has_errors ) {
			add_settings_error(
				'chatbot_plugin_group',
				'chatbot_settings_saved',
				__( 'Changes saved successfully.', 'chatbot-plugin-wp' ),
				'success'
			);
		}

		return wp_parse_args( $out, $defaults );
	}

	/**
	 * Opciones guardadas en BD sin overrides de wp-config.php.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_stored_settings(): array {
		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, self::default_settings() );
	}

	/**
	 * Añade claves nuevas del plugin sin pisar valores existentes.
	 */
	public static function maybe_merge_settings(): void {
		$stored = get_option( self::OPTION_KEY, false );

		if ( false === $stored || ! is_array( $stored ) ) {
			return;
		}

		$merged = wp_parse_args( $stored, self::default_settings() );

		if ( $merged !== $stored ) {
			update_option( self::OPTION_KEY, $merged, false );
		}
	}

	/**
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $current
	 * @param array<string, mixed> $defaults
	 * @param array<string, mixed> $out
	 */
	/**
	 * @return array<string, int>
	 */
	public static function general_field_limits(): array {
		return array(
			'widget_title'    => 80,
			'widget_subtitle' => 120,
			'welcome_message' => 2000,
			'system_prompt'   => 8000,
		);
	}

	/**
	 * Defaults traducidos para la UI del admin (placeholders, restaurar, preview).
	 * Los valores canónicos en BD siguen en default_settings() en inglés.
	 *
	 * @return array<string, string>
	 */
	public static function translated_general_defaults(): array {
		return array(
			'widget_title'    => __( 'AI Agent', 'chatbot-plugin-wp' ),
			'widget_subtitle' => __( 'System online', 'chatbot-plugin-wp' ),
			'welcome_message' => __(
				"Hello. I'm an AI agent. I may make mistakes; please verify important information before making decisions.\n\nHow can I help you?",
				'chatbot-plugin-wp'
			),
			'system_prompt'   => __(
				'You are a helpful website assistant. Respond clearly, briefly, and kindly. If you don\'t know something, say so honestly.',
				'chatbot-plugin-wp'
			),
		);
	}

	/**
	 * Cadenas i18n compartidas del preview del admin (Estilo y General).
	 *
	 * @return array<string, string>
	 */
	private static function admin_preview_i18n_strings(): array {
		return array(
			'openPanel'            => __( 'Open panel', 'chatbot-plugin-wp' ),
			'closePanel'           => __( 'Close panel', 'chatbot-plugin-wp' ),
			'openChat'             => __( 'Open chat', 'chatbot-plugin-wp' ),
			'minimize'             => __( 'Minimize', 'chatbot-plugin-wp' ),
			'reset'                => __( 'Reset', 'chatbot-plugin-wp' ),
			'close'                => __( 'Close', 'chatbot-plugin-wp' ),
			'placeholder'          => __( 'Type your message…', 'chatbot-plugin-wp' ),
			'send'                 => __( 'Send', 'chatbot-plugin-wp' ),
			'fallbackTitle'        => __( 'AI Agent', 'chatbot-plugin-wp' ),
			'fallbackSubtitle'     => __( 'System online', 'chatbot-plugin-wp' ),
			'fallbackWelcome'      => __(
				"Hello. I'm an AI agent. I may make mistakes; please verify important information before making decisions.\n\nHow can I help you?",
				'chatbot-plugin-wp'
			),
			'previewSampleUser'      => __( 'What are your opening hours?', 'chatbot-plugin-wp' ),
			'previewSampleAssistant' => __(
				'We are open Monday through Friday, 9:00 AM to 6:00 PM.',
				'chatbot-plugin-wp'
			),
			'widgetDisabled'       => __(
				'Global widget is disabled. The preview shows how copy would look if enabled.',
				'chatbot-plugin-wp'
			),
			'widgetEnabled'        => __( 'Enabled', 'chatbot-plugin-wp' ),
			'widgetDisabledLabel'  => __( 'Disabled', 'chatbot-plugin-wp' ),
			'contrastWarning'      => __(
				'Low contrast between primary color and background; check accessibility.',
				'chatbot-plugin-wp'
			),
			'resetOverrides'       => __( 'Reset color overrides', 'chatbot-plugin-wp' ),
			'exportTheme'          => __( 'Export theme', 'chatbot-plugin-wp' ),
			'importTheme'          => __( 'Import theme', 'chatbot-plugin-wp' ),
			'importSuccess'        => __(
				'Theme imported into the form. Save to apply on the site.',
				'chatbot-plugin-wp'
			),
			'importError'          => __( 'Invalid theme JSON.', 'chatbot-plugin-wp' ),
		);
	}

	/**
	 * Cadenas i18n solo de la pestaña General (admin JS).
	 *
	 * @return array<string, string>
	 */
	private static function admin_general_i18n_strings(): array {
		return array(
			'copyShortcode'       => __( 'Copy shortcode', 'chatbot-plugin-wp' ),
			'copied'              => __( 'Copied', 'chatbot-plugin-wp' ),
			'copyFailed'          => __( 'Could not copy.', 'chatbot-plugin-wp' ),
			'restoreWelcome'      => __( 'Restore default welcome message?', 'chatbot-plugin-wp' ),
			'restoreSystemPrompt' => __( 'Restore default system instructions?', 'chatbot-plugin-wp' ),
			'charCount'           => __( '%1$d / %2$d characters', 'chatbot-plugin-wp' ),
		);
	}

	/**
	 * @param string $value
	 * @param int    $max
	 */
	private static function truncate_setting_string( string $value, int $max ): string {
		if ( $max < 1 ) {
			return '';
		}
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max );
		}

		return substr( $value, 0, $max );
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	public static function preview_style_settings_for_js( array $settings ): array {
		$vars = array();
		foreach ( array( 'primary' => 'style_primary', 'accent' => 'style_accent', 'radius' => 'style_radius', 'bg' => 'style_bg', 'fg' => 'style_fg' ) as $key => $setting_key ) {
			$val = trim( (string) ( $settings[ $setting_key ] ?? '' ) );
			if ( $val !== '' ) {
				$vars[ $key ] = $val;
			}
		}

		$preset = sanitize_key( (string) ( $settings['style_preset'] ?? 'default' ) );
		if ( ! in_array( $preset, self::style_presets(), true ) ) {
			$preset = 'default';
		}

		$position = sanitize_key( (string) ( $settings['style_position'] ?? 'bottom-right' ) );
		if ( ! in_array( $position, self::style_positions(), true ) ) {
			$position = 'bottom-right';
		}

		$preset_auto_dark = sanitize_key( (string) ( $settings['style_preset_auto_dark'] ?? 'dark-glass' ) );
		if ( ! in_array( $preset_auto_dark, self::style_presets(), true ) ) {
			$preset_auto_dark = 'dark-glass';
		}

		return array(
			'preset'         => $preset,
			'position'       => $position,
			'primary'        => (string) ( $vars['primary'] ?? '' ),
			'accent'         => (string) ( $vars['accent'] ?? '' ),
			'bg'             => (string) ( $vars['bg'] ?? '' ),
			'fg'             => (string) ( $vars['fg'] ?? '' ),
			'radius'         => (string) ( $vars['radius'] ?? '' ),
			'offset'         => trim( (string) ( $settings['style_offset'] ?? '1rem' ) ) ?: '1rem',
			'panelWidth'     => trim( (string) ( $settings['style_panel_width'] ?? '' ) ),
			'panelMaxHeight' => trim( (string) ( $settings['style_panel_max_height'] ?? '' ) ),
			'fontFamily'     => sanitize_key( (string) ( $settings['style_font_family'] ?? 'system' ) ) ?: 'system',
			'launcherLabel'  => ! empty( $settings['style_launcher_label'] ),
			'showCredit'     => ! empty( $settings['style_show_credit'] ),
			'reduceMotion'   => ! empty( $settings['style_reduce_motion'] ),
			'presetAuto'     => ! empty( $settings['style_preset_auto'] ),
			'presetAutoDark' => $preset_auto_dark,
		);
	}

	/**
	 * Developer credit labels and URLs for the frontend widget.
	 *
	 * @return array{productName: string, authorName: string, productUrl: string, authorUrl: string}
	 */
	public static function developer_credit_for_js(): array {
		return array(
			'productName' => __( 'MultiAI Chatbot', 'chatbot-plugin-wp' ),
			'authorName'  => 'Jsravelo',
			'productUrl'  => esc_url_raw( 'https://github.com/JunniorRavelo/multiai-chatbot' ),
			'authorUrl'   => esc_url_raw( 'https://www.linkedin.com/in/jsravelo/' ),
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $current
	 * @param array<string, mixed> $defaults
	 * @param array<string, mixed> $out
	 */
	private static function sanitize_general_settings( array $input, array $current, array $defaults, array &$out ): void {
		$limits = self::general_field_limits();

		$out['widget_enabled'] = self::sanitize_checkbox( $input, $current, 'widget_enabled', (bool) $defaults['widget_enabled'] );
		$out['streaming_enabled'] = self::sanitize_checkbox( $input, $current, 'streaming_enabled', (bool) $defaults['streaming_enabled'] );

		$out['welcome_message'] = self::truncate_setting_string(
			sanitize_textarea_field( $input['welcome_message'] ?? $current['welcome_message'] ?? $defaults['welcome_message'] ),
			$limits['welcome_message']
		);
		$out['system_prompt'] = self::truncate_setting_string(
			sanitize_textarea_field( $input['system_prompt'] ?? $current['system_prompt'] ?? $defaults['system_prompt'] ),
			$limits['system_prompt']
		);
		$out['widget_title'] = self::truncate_setting_string(
			sanitize_text_field( $input['widget_title'] ?? $current['widget_title'] ?? $defaults['widget_title'] ),
			$limits['widget_title']
		);
		$out['widget_subtitle'] = self::truncate_setting_string(
			sanitize_text_field( $input['widget_subtitle'] ?? $current['widget_subtitle'] ?? $defaults['widget_subtitle'] ),
			$limits['widget_subtitle']
		);

		if ( ! empty( $out['widget_enabled'] ) && '' === trim( (string) $out['widget_title'] ) ) {
			add_settings_error(
				'chatbot_plugin_group',
				'chatbot_empty_widget_title',
				__( 'The widget is enabled but the title is empty. Visitors may see a blank header until you set a title.', 'chatbot-plugin-wp' ),
				'warning'
			);
		}
	}

	/**
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $current
	 * @param array<string, mixed> $defaults
	 * @param array<string, mixed> $out
	 */
	private static function sanitize_model_settings( array $input, array $current, array $defaults, array &$out ): void {
		$provider = sanitize_key( $input['provider'] ?? $current['provider'] ?? 'gemini' );
		$out['provider'] = in_array( $provider, array( 'gemini', 'ollama', 'openai_compatible', 'deepseek' ), true )
			? $provider
			: (string) ( $current['provider'] ?? 'gemini' );

		$new_key = isset( $input['api_key'] ) ? trim( (string) $input['api_key'] ) : '';
		if ( '' !== $new_key ) {
			$out['api_key'] = $new_key;
		} else {
			$out['api_key'] = (string) ( $current['api_key'] ?? '' );
		}

		$out['model']            = sanitize_text_field( $input['model'] ?? $current['model'] ?? $defaults['model'] );
		$out['model_candidates'] = sanitize_text_field( $input['model_candidates'] ?? $current['model_candidates'] ?? $defaults['model_candidates'] );
		$out['ollama_base_url']  = esc_url_raw( $input['ollama_base_url'] ?? $current['ollama_base_url'] ?? $defaults['ollama_base_url'] );
		$out['openai_base_url']  = esc_url_raw( $input['openai_base_url'] ?? $current['openai_base_url'] ?? $defaults['openai_base_url'] );
		$out['deepseek_base_url'] = esc_url_raw( $input['deepseek_base_url'] ?? $current['deepseek_base_url'] ?? $defaults['deepseek_base_url'] );
		$out['request_timeout']  = max( 5, min( 120, (int) ( $input['request_timeout'] ?? $current['request_timeout'] ?? $defaults['request_timeout'] ) ) );
	}

	/**
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $current
	 * @param array<string, mixed> $defaults
	 * @param array<string, mixed> $out
	 */
	private static function sanitize_security_settings( array $input, array $current, array $defaults, array &$out ): void {
		$out['allowed_origins']             = self::sanitize_origins_list( (string) ( $input['allowed_origins'] ?? $current['allowed_origins'] ?? $defaults['allowed_origins'] ) );
		$out['cache_ttl_seconds']           = max( 0, min( 86400, (int) ( $input['cache_ttl_seconds'] ?? $current['cache_ttl_seconds'] ?? $defaults['cache_ttl_seconds'] ) ) );
		$out['telemetry_log_path']          = sanitize_text_field( (string) ( $input['telemetry_log_path'] ?? $current['telemetry_log_path'] ?? $defaults['telemetry_log_path'] ) );
		$out['rate_limit_per_minute']       = max( 1, min( 120, (int) ( $input['rate_limit_per_minute'] ?? $current['rate_limit_per_minute'] ?? $defaults['rate_limit_per_minute'] ) ) );
		$out['rate_limit_per_day']          = max( 1, min( 1000, (int) ( $input['rate_limit_per_day'] ?? $current['rate_limit_per_day'] ?? $defaults['rate_limit_per_day'] ) ) );
		$out['rate_limit_model_per_minute'] = max( 1, min( 120, (int) ( $input['rate_limit_model_per_minute'] ?? $current['rate_limit_model_per_minute'] ?? $defaults['rate_limit_model_per_minute'] ) ) );
		$out['rate_limit_model_per_day']    = max( 1, min( 5000, (int) ( $input['rate_limit_model_per_day'] ?? $current['rate_limit_model_per_day'] ?? $defaults['rate_limit_model_per_day'] ) ) );
		$out['rate_limit_soft_threshold']   = max( 0.1, min( 1.0, (float) ( $input['rate_limit_soft_threshold'] ?? $current['rate_limit_soft_threshold'] ?? $defaults['rate_limit_soft_threshold'] ) ) );
		$out['ip_suspend_after_violations'] = max( 1, min( 20, (int) ( $input['ip_suspend_after_violations'] ?? $current['ip_suspend_after_violations'] ?? $defaults['ip_suspend_after_violations'] ) ) );
		$out['ip_suspend_seconds']          = max( 60, min( 86400, (int) ( $input['ip_suspend_seconds'] ?? $current['ip_suspend_seconds'] ?? $defaults['ip_suspend_seconds'] ) ) );
		$out['internal_chat_base_url']      = esc_url_raw( (string) ( $input['internal_chat_base_url'] ?? $current['internal_chat_base_url'] ?? $defaults['internal_chat_base_url'] ) );
		$out['history_retention_days']      = max( 0, min( 3650, (int) ( $input['history_retention_days'] ?? $current['history_retention_days'] ?? $defaults['history_retention_days'] ) ) );
		$out['telemetry_retention_days']    = max( 0, min( 3650, (int) ( $input['telemetry_retention_days'] ?? $current['telemetry_retention_days'] ?? $defaults['telemetry_retention_days'] ) ) );
	}

	/**
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $current
	 * @param array<string, mixed> $defaults
	 * @param array<string, mixed> $out
	 */
	private static function sanitize_style_settings( array $input, array $current, array $defaults, array &$out ): void {
		$preset = sanitize_key( $input['style_preset'] ?? $current['style_preset'] ?? 'default' );
		$out['style_preset'] = in_array( $preset, self::style_presets(), true )
			? $preset
			: (string) ( $current['style_preset'] ?? 'default' );
		$out['style_primary'] = sanitize_hex_color( $input['style_primary'] ?? $current['style_primary'] ?? '' ) ?: '';
		$out['style_accent']  = sanitize_hex_color( $input['style_accent'] ?? $current['style_accent'] ?? '' ) ?: '';

		if ( array_key_exists( 'style_radius', $input ) ) {
			$out['style_radius'] = self::sanitize_css_size( (string) $input['style_radius'] );
			self::maybe_add_css_size_warning(
				$input['style_radius'],
				$out['style_radius'],
				__( 'Border radius is invalid; that value was ignored.', 'chatbot-plugin-wp' )
			);
		}

		$position = sanitize_key( $input['style_position'] ?? $current['style_position'] ?? 'bottom-right' );
		$out['style_position'] = in_array( $position, self::style_positions(), true )
			? $position
			: (string) ( $current['style_position'] ?? 'bottom-right' );

		if ( array_key_exists( 'style_offset', $input ) ) {
			$out['style_offset'] = self::sanitize_css_size( (string) $input['style_offset'] ) ?: (string) ( $current['style_offset'] ?? '1rem' );
		}

		if ( array_key_exists( 'style_panel_width', $input ) ) {
			$out['style_panel_width'] = self::sanitize_css_size( (string) $input['style_panel_width'] );
			self::maybe_add_css_size_warning(
				$input['style_panel_width'],
				$out['style_panel_width'],
				__( 'Panel width is invalid; that value was ignored.', 'chatbot-plugin-wp' )
			);
		}

		$out['style_launcher_label'] = self::sanitize_checkbox( $input, $current, 'style_launcher_label', (bool) $defaults['style_launcher_label'] );
		$out['style_show_credit']    = self::sanitize_checkbox( $input, $current, 'style_show_credit', (bool) $defaults['style_show_credit'] );

		$out['style_bg'] = sanitize_hex_color( $input['style_bg'] ?? $current['style_bg'] ?? '' ) ?: '';
		$out['style_fg'] = sanitize_hex_color( $input['style_fg'] ?? $current['style_fg'] ?? '' ) ?: '';

		$font = sanitize_key( $input['style_font_family'] ?? $current['style_font_family'] ?? 'system' );
		$out['style_font_family'] = array_key_exists( $font, self::style_font_families() )
			? $font
			: (string) ( $current['style_font_family'] ?? 'system' );

		if ( array_key_exists( 'style_panel_max_height', $input ) ) {
			$out['style_panel_max_height'] = self::sanitize_css_size( (string) $input['style_panel_max_height'] );
			self::maybe_add_css_size_warning(
				$input['style_panel_max_height'],
				$out['style_panel_max_height'],
				__( 'Panel max height is invalid; that value was ignored.', 'chatbot-plugin-wp' )
			);
		}

		$z_raw = $input['style_z_index'] ?? $current['style_z_index'] ?? 0;
		$z     = is_numeric( $z_raw ) ? (int) $z_raw : 0;
		$out['style_z_index'] = $z > 0 ? max( 1000, min( 2147483646, $z ) ) : 0;

		$out['style_reduce_motion'] = self::sanitize_checkbox( $input, $current, 'style_reduce_motion', (bool) $defaults['style_reduce_motion'] );
		$out['style_preset_auto']   = self::sanitize_checkbox( $input, $current, 'style_preset_auto', (bool) $defaults['style_preset_auto'] );

		$auto_dark = sanitize_key( $input['style_preset_auto_dark'] ?? $current['style_preset_auto_dark'] ?? 'dark-glass' );
		$out['style_preset_auto_dark'] = in_array( $auto_dark, self::style_presets(), true )
			? $auto_dark
			: (string) ( $current['style_preset_auto_dark'] ?? 'dark-glass' );

		if ( array_key_exists( 'style_custom_css', $input ) ) {
			$out['style_custom_css'] = self::sanitize_style_custom_css( (string) $input['style_custom_css'] );
		}
	}

	/**
	 * @return array<string, string>
	 */
	public static function style_font_families(): array {
		return array(
			'system'  => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
			'inherit' => 'inherit',
			'serif'   => 'Georgia, "Times New Roman", serif',
			'mono'    => 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
		);
	}

	/**
	 * Human-readable font options (English source strings).
	 *
	 * @return array<string, string>
	 */
	public static function style_font_family_labels(): array {
		return array(
			'system'  => __( 'System UI', 'chatbot-plugin-wp' ),
			'inherit' => __( 'Inherit from theme', 'chatbot-plugin-wp' ),
			'serif'   => __( 'Serif', 'chatbot-plugin-wp' ),
			'mono'    => __( 'Monospace', 'chatbot-plugin-wp' ),
		);
	}

	/**
	 * Keys exported/imported as theme JSON.
	 *
	 * @return list<string>
	 */
	public static function style_export_keys(): array {
		return array(
			'style_preset',
			'style_primary',
			'style_accent',
			'style_radius',
			'style_position',
			'style_offset',
			'style_panel_width',
			'style_launcher_label',
			'style_show_credit',
			'style_bg',
			'style_fg',
			'style_font_family',
			'style_panel_max_height',
			'style_z_index',
			'style_reduce_motion',
			'style_preset_auto',
			'style_preset_auto_dark',
			'style_custom_css',
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	public static function build_style_config( array $settings, array $overrides = array() ): array {
		$merged = array_merge( $settings, $overrides );

		$preset = sanitize_key( (string) ( $merged['style_preset'] ?? 'default' ) );
		if ( ! in_array( $preset, self::style_presets(), true ) ) {
			$preset = 'default';
		}

		$position = sanitize_key( (string) ( $merged['style_position'] ?? 'bottom-right' ) );
		if ( ! in_array( $position, self::style_positions(), true ) ) {
			$position = 'bottom-right';
		}

		$vars = array();
		foreach ( array( 'primary' => 'style_primary', 'accent' => 'style_accent', 'radius' => 'style_radius', 'bg' => 'style_bg', 'fg' => 'style_fg' ) as $key => $setting_key ) {
			$val = trim( (string) ( $merged[ $setting_key ] ?? '' ) );
			if ( $val !== '' ) {
				$vars[ $key ] = $val;
			}
		}

		$font_key = sanitize_key( (string) ( $merged['style_font_family'] ?? 'system' ) );
		$fonts    = self::style_font_families();
		$font_family = $fonts[ $font_key ] ?? $fonts['system'];

		$z = (int) ( $merged['style_z_index'] ?? 0 );
		$config = array(
			'preset'          => $preset,
			'position'        => $position,
			'offset'          => trim( (string) ( $merged['style_offset'] ?? '1rem' ) ) ?: '1rem',
			'panelWidth'      => trim( (string) ( $merged['style_panel_width'] ?? '' ) ),
			'panelMaxHeight'  => trim( (string) ( $merged['style_panel_max_height'] ?? '' ) ),
			'launcherLabel'   => ! empty( $merged['style_launcher_label'] ),
			'showCredit'      => ! empty( $merged['style_show_credit'] ),
			'fontFamily'      => $font_family,
			'zIndex'          => $z > 0 ? $z : 0,
			'reduceMotion'    => ! empty( $merged['style_reduce_motion'] ),
			'presetAuto'      => ! empty( $merged['style_preset_auto'] ),
			'presetAutoDark'  => (string) ( $merged['style_preset_auto_dark'] ?? 'dark-glass' ),
			'vars'            => $vars,
		);

		/**
		 * Filter widget style configuration passed to the frontend.
		 *
		 * @param array<string, mixed> $config
		 * @param array<string, mixed> $settings Full plugin settings.
		 */
		return (array) apply_filters( 'chatbot_style_config', $config, $settings );
	}

	/**
	 * @param array<string, string> $atts Shortcode attributes.
	 * @return array<string, mixed>
	 */
	public static function shortcode_style_overrides( array $atts ): array {
		$map = array(
			'preset'     => 'style_preset',
			'position'   => 'style_position',
			'primary'    => 'style_primary',
			'accent'     => 'style_accent',
			'radius'     => 'style_radius',
			'offset'     => 'style_offset',
			'panel_width' => 'style_panel_width',
			'bg'         => 'style_bg',
			'fg'         => 'style_fg',
		);
		$overrides = array();
		foreach ( $map as $attr => $key ) {
			if ( ! empty( $atts[ $attr ] ) ) {
				$overrides[ $key ] = $atts[ $attr ];
			}
		}
		return $overrides;
	}

	public static function sanitize_style_custom_css( string $css ): string {
		$css = wp_strip_all_tags( $css );
		$css = preg_replace( '/@import\b[^;]+;?/i', '', $css ) ?? '';
		$css = preg_replace( '/<\/style>/i', '', $css ) ?? '';
		return substr( trim( $css ), 0, 8000 );
	}

	/**
	 * Compatibilidad si falta chatbot_admin_tab en el POST.
	 *
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $current
	 * @param array<string, mixed> $defaults
	 * @param array<string, mixed> $out
	 */
	private static function sanitize_all_settings( array $input, array $current, array $defaults, array &$out ): void {
		self::sanitize_general_settings( $input, $current, $defaults, $out );
		self::sanitize_model_settings( $input, $current, $defaults, $out );
		self::sanitize_security_settings( $input, $current, $defaults, $out );
		self::sanitize_style_settings( $input, $current, $defaults, $out );
	}

	/**
	 * @param mixed $raw
	 * @param mixed $sanitized
	 */
	private static function maybe_add_css_size_warning( $raw, $sanitized, string $message ): void {
		if ( '' === trim( (string) $raw ) ) {
			return;
		}

		if ( '' !== (string) $sanitized ) {
			return;
		}

		add_settings_error(
			'chatbot_plugin_group',
			'chatbot_invalid_css_size_' . md5( $message ),
			$message,
			'warning'
		);
	}

	/**
	 * @param string $location
	 * @param int    $status
	 * @return string
	 */
	public static function preserve_tab_on_settings_redirect( $location, $status ) {
		unset( $status );

		if ( empty( $_POST['option_page'] ) || 'chatbot_plugin_group' !== $_POST['option_page'] ) {
			return $location;
		}

		$tab = isset( $_POST['chatbot_admin_tab'] ) ? sanitize_key( wp_unslash( (string) $_POST['chatbot_admin_tab'] ) ) : 'general';
		$allowed_tabs = array( 'general', 'model', 'security', 'style' );

		if ( ! in_array( $tab, $allowed_tabs, true ) ) {
			return $location;
		}

		return add_query_arg( 'tab', $tab, remove_query_arg( 'tab', $location ) );
	}

	public static function enqueue_admin_assets( string $hook ): void {
		if ( 'toplevel_page_chatbot-plugin' !== $hook ) {
			return;
		}

		$admin_css_path = CHATBOT_PLUGIN_PATH . 'assets/css/admin.css';
		$admin_css_ver  = file_exists( $admin_css_path )
			? (string) filemtime( $admin_css_path )
			: CHATBOT_PLUGIN_VERSION;

		wp_enqueue_style(
			'chatbot-plugin-admin',
			CHATBOT_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			$admin_css_ver
		);

		$admin_feedback_js_path = CHATBOT_PLUGIN_PATH . 'assets/js/admin-feedback.js';
		$admin_feedback_js_ver  = file_exists( $admin_feedback_js_path )
			? (string) filemtime( $admin_feedback_js_path )
			: CHATBOT_PLUGIN_VERSION;

		wp_enqueue_script(
			'chatbot-plugin-admin-feedback',
			CHATBOT_PLUGIN_URL . 'assets/js/admin-feedback.js',
			array(),
			$admin_feedback_js_ver,
			true
		);

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'general';
		if ( 'style' === $tab ) {
			wp_enqueue_style( 'wp-color-picker' );

			$chatbot_css_path = CHATBOT_PLUGIN_PATH . 'assets/css/chatbot.css';
			$chatbot_css_ver  = file_exists( $chatbot_css_path )
				? (string) filemtime( $chatbot_css_path )
				: CHATBOT_PLUGIN_VERSION;

			$admin_style_js_path = CHATBOT_PLUGIN_PATH . 'assets/js/admin-style.js';
			$admin_style_js_ver  = file_exists( $admin_style_js_path )
				? (string) filemtime( $admin_style_js_path )
				: CHATBOT_PLUGIN_VERSION;

			wp_enqueue_style(
				'chatbot-plugin-admin-preview',
				CHATBOT_PLUGIN_URL . 'assets/css/chatbot.css',
				array( 'chatbot-plugin-admin' ),
				$chatbot_css_ver
			);

			$admin_preview_shared_path = CHATBOT_PLUGIN_PATH . 'assets/js/admin-preview-shared.js';
			$admin_preview_shared_ver  = file_exists( $admin_preview_shared_path )
				? (string) filemtime( $admin_preview_shared_path )
				: CHATBOT_PLUGIN_VERSION;

			wp_enqueue_script(
				'chatbot-plugin-admin-preview-shared',
				CHATBOT_PLUGIN_URL . 'assets/js/admin-preview-shared.js',
				array(),
				$admin_preview_shared_ver,
				true
			);

			wp_enqueue_script(
				'chatbot-plugin-admin-style',
				CHATBOT_PLUGIN_URL . 'assets/js/admin-style.js',
				array( 'wp-color-picker', 'chatbot-plugin-admin-preview-shared' ),
				$admin_style_js_ver,
				true
			);

			$settings = Chatbot_Plugin::get_settings();
			$preset_meta_for_js = array();
			foreach ( self::style_preset_meta() as $id => $meta ) {
				$preset_meta_for_js[ $id ] = array(
					'label' => (string) ( $meta['label'] ?? $id ),
					'desc'  => (string) ( $meta['desc'] ?? '' ),
					'badge' => (string) ( $meta['badge'] ?? '' ),
					'colors' => $meta['colors'] ?? array(),
				);
			}

			wp_localize_script(
				'chatbot-plugin-admin-style',
				'chatbotStylePreview',
				array(
					'optionKey'       => self::OPTION_KEY,
					'presets'         => self::style_presets(),
					'presetMeta'      => $preset_meta_for_js,
					'exportKeys'      => self::style_export_keys(),
					'widgetTitle'     => (string) ( $settings['widget_title'] ?? '' ),
					'widgetSubtitle'  => (string) ( $settings['widget_subtitle'] ?? '' ),
					'welcomeMessage'  => (string) ( $settings['welcome_message'] ?? '' ),
					'defaults'        => self::translated_general_defaults(),
					'generalFieldNames' => array(
						'widget_title',
						'widget_subtitle',
						'welcome_message',
					),
					'i18n'            => self::admin_preview_i18n_strings(),
					'positionLabels'  => self::style_position_labels(),
					'credit'          => self::developer_credit_for_js(),
				)
			);
		}

		if ( 'general' === $tab ) {
			$chatbot_css_path = CHATBOT_PLUGIN_PATH . 'assets/css/chatbot.css';
			$chatbot_css_ver  = file_exists( $chatbot_css_path )
				? (string) filemtime( $chatbot_css_path )
				: CHATBOT_PLUGIN_VERSION;

			$admin_preview_shared_path = CHATBOT_PLUGIN_PATH . 'assets/js/admin-preview-shared.js';
			$admin_preview_shared_ver  = file_exists( $admin_preview_shared_path )
				? (string) filemtime( $admin_preview_shared_path )
				: CHATBOT_PLUGIN_VERSION;

			$admin_general_js_path = CHATBOT_PLUGIN_PATH . 'assets/js/admin-general.js';
			$admin_general_js_ver  = file_exists( $admin_general_js_path )
				? (string) filemtime( $admin_general_js_path )
				: CHATBOT_PLUGIN_VERSION;

			wp_enqueue_style(
				'chatbot-plugin-admin-preview',
				CHATBOT_PLUGIN_URL . 'assets/css/chatbot.css',
				array( 'chatbot-plugin-admin' ),
				$chatbot_css_ver
			);

			wp_enqueue_script(
				'chatbot-plugin-admin-preview-shared',
				CHATBOT_PLUGIN_URL . 'assets/js/admin-preview-shared.js',
				array(),
				$admin_preview_shared_ver,
				true
			);

			wp_enqueue_script(
				'chatbot-plugin-admin-general',
				CHATBOT_PLUGIN_URL . 'assets/js/admin-general.js',
				array( 'chatbot-plugin-admin-preview-shared' ),
				$admin_general_js_ver,
				true
			);

			$settings         = Chatbot_Plugin::get_settings();
			$display_defaults = self::translated_general_defaults();

			wp_localize_script(
				'chatbot-plugin-admin-general',
				'chatbotGeneralPreview',
				array(
					'optionKey'         => self::OPTION_KEY,
					'savedStyle'        => self::preview_style_settings_for_js( $settings ),
					'presets'           => self::style_presets(),
					'limits'            => self::general_field_limits(),
					'defaults'          => $display_defaults,
					'shortcode'         => '[chatbot_widget]',
					'generalFieldNames' => array(
						'widget_title',
						'widget_subtitle',
						'welcome_message',
					),
					'i18n'              => array_merge(
						self::admin_preview_i18n_strings(),
						self::admin_general_i18n_strings()
					),
					'credit'            => self::developer_credit_for_js(),
				)
			);
		}

		if ( 'stats' === $tab ) {
			$admin_stats_js_path = CHATBOT_PLUGIN_PATH . 'assets/js/admin-stats.js';
			$admin_stats_js_ver  = file_exists( $admin_stats_js_path )
				? (string) filemtime( $admin_stats_js_path )
				: CHATBOT_PLUGIN_VERSION;

			wp_enqueue_script(
				'chatbot-plugin-admin-stats',
				CHATBOT_PLUGIN_URL . 'assets/js/admin-stats.js',
				array(),
				$admin_stats_js_ver,
				true
			);
		}

		if ( 'history' === $tab ) {
			$admin_history_js_path = CHATBOT_PLUGIN_PATH . 'assets/js/admin-history.js';
			$admin_history_js_ver  = file_exists( $admin_history_js_path )
				? (string) filemtime( $admin_history_js_path )
				: CHATBOT_PLUGIN_VERSION;

			wp_enqueue_script(
				'chatbot-plugin-admin-history',
				CHATBOT_PLUGIN_URL . 'assets/js/admin-history.js',
				array(),
				$admin_history_js_ver,
				true
			);

			wp_localize_script(
				'chatbot-plugin-admin-history',
				'chatbotHistoryAdmin',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'chatbot_history_detail' ),
					'deleteNonce' => wp_create_nonce( 'chatbot_delete_conversation' ),
					'i18n'    => array(
						'loading'      => __( 'Loading messages…', 'chatbot-plugin-wp' ),
						'error'        => __( 'Could not load the conversation.', 'chatbot-plugin-wp' ),
						'retry'        => __( 'Retry', 'chatbot-plugin-wp' ),
						'copied'       => __( 'Copied', 'chatbot-plugin-wp' ),
						'copyFailed'   => __( 'Could not copy.', 'chatbot-plugin-wp' ),
						'deleteConfirm' => __( 'Delete this conversation and all its messages?', 'chatbot-plugin-wp' ),
						'deleteFailed' => __( 'Could not delete the conversation.', 'chatbot-plugin-wp' ),
					),
				)
			);
		}
	}

	/**
	 * @return list<string>
	 */
	public static function style_presets(): array {
		$presets = array( 'default', 'dark-glass', 'obsidian', 'minimal', 'ocean', 'sunset', 'forest', 'lavender', 'plum' );

		/**
		 * Filter available chat style preset IDs.
		 *
		 * @param list<string> $presets
		 */
		return (array) apply_filters( 'chatbot_style_presets', $presets );
	}

	/**
	 * @return list<string>
	 */
	public static function style_positions(): array {
		return array(
			'bottom-right',
			'center-right',
			'bottom-left',
			'center-left',
			'bottom-center',
		);
	}

	/**
	 * @return array<string, array{label: string, desc: string, badge: string, badge_type: string, colors: list<string>}>
	 */
	public static function style_preset_meta(): array {
		return array(
			'default'    => array(
				'label'      => __( 'Sapphire', 'chatbot-plugin-wp' ),
				'desc'       => __( 'Indigo blue with soft violet. Professional and trustworthy.', 'chatbot-plugin-wp' ),
				'badge'      => __( 'Light', 'chatbot-plugin-wp' ),
				'badge_type' => 'light',
				'colors'     => array( '#2563eb', '#6366f1', '#ffffff' ),
			),
			'dark-glass' => array(
				'label'      => __( 'Midnight', 'chatbot-plugin-wp' ),
				'desc'       => __( 'Deep dark with cyan and violet accents. Readable header.', 'chatbot-plugin-wp' ),
				'badge'      => __( 'Dark', 'chatbot-plugin-wp' ),
				'badge_type' => 'dark',
				'colors'     => array( '#38bdf8', '#a78bfa', '#0f172a' ),
			),
			'obsidian'   => array(
				'label'      => __( 'Obsidian', 'chatbot-plugin-wp' ),
				'desc'       => __( 'Charcoal slate with emerald and teal highlights. Calm dark UI.', 'chatbot-plugin-wp' ),
				'badge'      => __( 'Dark', 'chatbot-plugin-wp' ),
				'badge_type' => 'dark',
				'colors'     => array( '#34d399', '#2dd4bf', '#0c1117' ),
			),
			'minimal'    => array(
				'label'      => __( 'Monochrome', 'chatbot-plugin-wp' ),
				'desc'       => __( 'Neutral zinc, straight edges and subtle shadows.', 'chatbot-plugin-wp' ),
				'badge'      => __( 'Neutral', 'chatbot-plugin-wp' ),
				'badge_type' => 'neutral',
				'colors'     => array( '#27272a', '#71717a', '#ffffff' ),
			),
			'ocean'      => array(
				'label'      => __( 'Aqua', 'chatbot-plugin-wp' ),
				'desc'       => __( 'Deep cyan with turquoise highlights. Fresh and modern.', 'chatbot-plugin-wp' ),
				'badge'      => __( 'Light', 'chatbot-plugin-wp' ),
				'badge_type' => 'light',
				'colors'     => array( '#0e7490', '#22d3ee', '#f0fdff' ),
			),
			'sunset'     => array(
				'label'      => __( 'Ember', 'chatbot-plugin-wp' ),
				'desc'       => __( 'Warm orange with pink accents. Cozy and energetic.', 'chatbot-plugin-wp' ),
				'badge'      => __( 'Light', 'chatbot-plugin-wp' ),
				'badge_type' => 'light',
				'colors'     => array( '#ea580c', '#f43f5e', '#fff7ed' ),
			),
			'forest'     => array(
				'label'      => __( 'Emerald', 'chatbot-plugin-wp' ),
				'desc'       => __( 'Emerald green with natural backgrounds. Calm and trustworthy.', 'chatbot-plugin-wp' ),
				'badge'      => __( 'Light', 'chatbot-plugin-wp' ),
				'badge_type' => 'light',
				'colors'     => array( '#059669', '#34d399', '#ecfdf5' ),
			),
			'lavender'   => array(
				'label'      => __( 'Amethyst', 'chatbot-plugin-wp' ),
				'desc'       => __( 'Soft violet with light lavender. Elegant and modern.', 'chatbot-plugin-wp' ),
				'badge'      => __( 'Light', 'chatbot-plugin-wp' ),
				'badge_type' => 'light',
				'colors'     => array( '#7c3aed', '#a855f7', '#faf5ff' ),
			),
			'plum'       => array(
				'label'      => __( 'Plum', 'chatbot-plugin-wp' ),
				'desc'       => __( 'Deep purple with fuchsia accents. Dark and sophisticated.', 'chatbot-plugin-wp' ),
				'badge'      => __( 'Dark', 'chatbot-plugin-wp' ),
				'badge_type' => 'dark',
				'colors'     => array( '#c084fc', '#e879f9', '#1e1b4b' ),
			),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function style_position_labels(): array {
		return array(
			'bottom-right'  => __( 'Bottom right', 'chatbot-plugin-wp' ),
			'center-right'  => __( 'Center right', 'chatbot-plugin-wp' ),
			'bottom-left'   => __( 'Bottom left', 'chatbot-plugin-wp' ),
			'center-left'   => __( 'Center left', 'chatbot-plugin-wp' ),
			'bottom-center' => __( 'Bottom center', 'chatbot-plugin-wp' ),
		);
	}

	/**
	 * Preserva checkboxes al guardar desde pestañas que no incluyen el campo.
	 * En la pestaña que sí lo incluye, usa input hidden con value="0" antes del checkbox.
	 *
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $current
	 */
	private static function sanitize_checkbox( array $input, array $current, string $key, bool $default ): bool {
		if ( ! array_key_exists( $key, $input ) ) {
			return ! empty( $current[ $key ] ?? $default );
		}

		return ! empty( $input[ $key ] );
	}

	private static function sanitize_origins_list( string $value ): string {
		$parts = array_filter(
			array_map(
				static function ( $origin ) {
					$origin = trim( (string) $origin );
					if ( '' === $origin ) {
						return '';
					}
					return esc_url_raw( $origin, array( 'http', 'https' ) );
				},
				explode( ',', $value )
			)
		);

		return implode( ',', array_unique( $parts ) );
	}

	private static function sanitize_css_size( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^\d+(\.\d+)?(px|rem|em|%|vw|vh)$/', $value ) ) {
			return $value;
		}
		return '';
	}

	public static function export_csv(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'chatbot-plugin-wp' ) );
		}
		check_admin_referer( 'chatbot_export_csv' );

		$filters = self::get_stats_filters_from_request();
		unset( $filters['offset'], $filters['per_page'] );
		$csv = Chatbot_Telemetry::export_csv( $filters );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=chatbot-telemetry-' . gmdate( 'Y-m-d' ) . '.csv' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public static function export_history_csv(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'chatbot-plugin-wp' ) );
		}
		check_admin_referer( 'chatbot_export_history_csv' );

		$filters = self::get_history_filters_from_request();
		unset( $filters['offset'], $filters['per_page'] );
		Chatbot_Chat_History::export_csv( $filters );
		exit;
	}

	public static function purge_history(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'chatbot-plugin-wp' ) );
		}
		check_admin_referer( 'chatbot_purge_history' );

		$settings = Chatbot_Plugin::get_settings();
		$days     = isset( $settings['history_retention_days'] ) ? (int) $settings['history_retention_days'] : 0;
		if ( $days <= 0 ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'             => 'chatbot-plugin',
						'tab'              => 'history',
						'chatbot_purge'    => 'disabled',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$result = Chatbot_Chat_History::purge_older_than_days( $days );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => 'chatbot-plugin',
					'tab'               => 'history',
					'chatbot_purged'    => 1,
					'purged_conversations' => (int) ( $result['deleted_conversations'] ?? 0 ),
					'purged_messages'   => (int) ( $result['deleted_messages'] ?? 0 ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function ajax_delete_conversation(): void {
		check_ajax_referer( 'chatbot_delete_conversation', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'chatbot-plugin-wp' ) ), 403 );
		}

		$conversation_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( $conversation_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid conversation.', 'chatbot-plugin-wp' ) ), 400 );
		}

		if ( ! Chatbot_Chat_History::delete_conversation( $conversation_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not delete the conversation.', 'chatbot-plugin-wp' ) ), 500 );
		}

		wp_send_json_success();
	}

	public static function purge_telemetry(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'chatbot-plugin-wp' ) );
		}
		check_admin_referer( 'chatbot_purge_telemetry' );

		$settings = Chatbot_Plugin::get_settings();
		$days     = isset( $settings['telemetry_retention_days'] ) ? (int) $settings['telemetry_retention_days'] : 0;
		if ( $days <= 0 ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'              => 'chatbot-plugin',
						'tab'               => 'stats',
						'chatbot_purge'     => 'disabled',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$result = Chatbot_Telemetry::purge_older_than_days( $days );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'chatbot-plugin',
					'tab'            => 'stats',
					'chatbot_purged' => 1,
					'purged_events'  => (int) ( $result['deleted_events'] ?? 0 ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'general';
		$settings = Chatbot_Plugin::get_settings();
		$tabs     = array(
			'general'  => __( 'General', 'chatbot-plugin-wp' ),
			'model'    => __( 'AI Model', 'chatbot-plugin-wp' ),
			'security' => __( 'Security', 'chatbot-plugin-wp' ),
			'style'    => __( 'Chat style', 'chatbot-plugin-wp' ),
			'stats'    => __( 'Statistics', 'chatbot-plugin-wp' ),
			'history'  => __( 'History', 'chatbot-plugin-wp' ),
		);

		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}

		$widget_on = ! empty( $settings['widget_enabled'] );
		?>
		<div class="wrap chatbot-admin-wrap">
			<header class="chatbot-admin-header">
				<div class="chatbot-admin-header__brand">
					<span class="chatbot-admin-header__icon dashicons dashicons-format-chat" aria-hidden="true"></span>
					<h1><?php esc_html_e( 'MultiAI ChatBot', 'chatbot-plugin-wp' ); ?></h1>
				</div>
				<span class="chatbot-admin-badge <?php echo $widget_on ? 'chatbot-admin-badge--on' : 'chatbot-admin-badge--off'; ?>">
					<?php
					echo $widget_on
						? esc_html__( 'Enabled', 'chatbot-plugin-wp' )
						: esc_html__( 'Disabled', 'chatbot-plugin-wp' );
					?>
				</span>
			</header>

			<nav class="nav-tab-wrapper chatbot-admin-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'chatbot-plugin-wp' ); ?>">
				<?php foreach ( $tabs as $id => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=chatbot-plugin&tab=' . $id ) ); ?>"
						class="nav-tab<?php echo $tab === $id ? ' nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php self::render_save_notices(); ?>

			<?php if ( in_array( $tab, array( 'stats', 'history' ), true ) ) : ?>
				<div class="chatbot-admin-body">
					<?php
					if ( 'stats' === $tab ) {
						self::render_stats_tab();
					} else {
						self::render_history_tab();
					}
					?>
				</div>
			<?php else : ?>
				<form method="post" action="options.php" class="chatbot-admin-form">
					<?php settings_fields( 'chatbot_plugin_group' ); ?>
					<input type="hidden" name="chatbot_admin_tab" value="<?php echo esc_attr( $tab ); ?>" />

					<div class="chatbot-admin-body">
						<?php if ( 'general' === $tab ) : ?>
							<?php self::render_general_fields( $settings ); ?>
						<?php elseif ( 'model' === $tab ) : ?>
							<?php self::render_model_fields( $settings ); ?>
						<?php elseif ( 'security' === $tab ) : ?>
							<?php self::render_security_fields( $settings ); ?>
						<?php elseif ( 'style' === $tab ) : ?>
							<?php self::render_style_fields( $settings ); ?>
						<?php endif; ?>
					</div>

					<div
						class="chatbot-admin-form-divider"
						role="presentation"
						aria-hidden="true"
						style="display:block;height:1.25rem;min-height:1.25rem;margin-top:1rem;border-top:1px solid #e2e8f0;box-sizing:border-box;"
					></div>

					<div class="chatbot-admin-footer">
						<?php submit_button( __( 'Save changes', 'chatbot-plugin-wp' ), 'primary', 'submit', false ); ?>
						<span class="chatbot-admin-footer__hint">
							<?php esc_html_e( 'Changes apply immediately on the public site.', 'chatbot-plugin-wp' ); ?>
						</span>
					</div>
				</form>
			<?php endif; ?>

			<?php Chatbot_Donation_Footer::render(); ?>
		</div>
		<?php
	}

	private static function render_save_notices(): void {
		$errors = self::consume_settings_errors( 'chatbot_plugin_group' );

		if ( empty( $errors ) && isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) {
			self::render_admin_notice(
				__( 'Changes saved successfully.', 'chatbot-plugin-wp' ),
				'success'
			);
			return;
		}

		foreach ( $errors as $error ) {
			$type = (string) ( $error['type'] ?? 'info' );
			if ( ! in_array( $type, array( 'error', 'success', 'warning', 'info', 'updated' ), true ) ) {
				$type = 'info';
			}
			if ( 'updated' === $type ) {
				$type = 'success';
			}

			self::render_admin_notice( (string) ( $error['message'] ?? '' ), $type );
		}
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function consume_settings_errors( string $setting ): array {
		$errors = get_settings_errors( $setting );
		if ( empty( $errors ) ) {
			return array();
		}

		global $wp_settings_errors;
		if ( ! is_array( $wp_settings_errors ) ) {
			$wp_settings_errors = array();
		}

		$wp_settings_errors = array_values(
			array_filter(
				$wp_settings_errors,
				static function ( $error ) use ( $setting ) {
					return ( $error['setting'] ?? '' ) !== $setting;
				}
			)
		);

		return $errors;
	}

	private static function render_admin_notice( string $message, string $type ): void {
		if ( '' === trim( $message ) ) {
			return;
		}

		$type_class = 'chatbot-admin-notice--' . sanitize_html_class( $type );
		$labels     = array(
			'success' => __( 'Saved', 'chatbot-plugin-wp' ),
			'error'   => __( 'Error', 'chatbot-plugin-wp' ),
			'warning' => __( 'Notice', 'chatbot-plugin-wp' ),
			'info'    => __( 'Information', 'chatbot-plugin-wp' ),
		);
		$label      = $labels[ $type ] ?? $labels['info'];
		?>
		<div
			class="chatbot-admin-notice <?php echo esc_attr( $type_class ); ?>"
			role="<?php echo 'error' === $type ? 'alert' : 'status'; ?>"
			data-auto-dismiss="true"
		>
			<div class="chatbot-admin-notice__content">
				<strong class="chatbot-admin-notice__title"><?php echo esc_html( $label ); ?></strong>
				<p class="chatbot-admin-notice__text"><?php echo esc_html( $message ); ?></p>
			</div>
			<button
				type="button"
				class="chatbot-admin-notice__dismiss"
				aria-label="<?php esc_attr_e( 'Dismiss notice', 'chatbot-plugin-wp' ); ?>"
			>&times;</button>
		</div>
		<?php
	}

	/**
	 * @param string $title
	 * @param string $description
	 */
	private static function card_open( string $title, string $description = '' ): void {
		?>
		<div class="chatbot-admin-card">
			<div class="chatbot-admin-card__head">
				<h2><?php echo esc_html( $title ); ?></h2>
				<?php if ( '' !== $description ) : ?>
					<p><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</div>
			<div class="chatbot-admin-card__body">
		<?php
	}

	private static function card_close(): void {
		?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param string $hint_text
	 * @param string $position
	 * @param bool   $show_contrast
	 */
	private static function render_content_preview_panel( string $hint_text, string $position, bool $show_contrast = false ): void {
		?>
		<div class="chatbot-admin-preview-card">
			<div class="chatbot-admin-card">
				<div class="chatbot-admin-card__head chatbot-admin-preview__head">
					<div>
						<h2><?php esc_html_e( 'Content preview', 'chatbot-plugin-wp' ); ?></h2>
						<p><?php esc_html_e( 'Interactive: try open/close and see visitor-facing text update instantly.', 'chatbot-plugin-wp' ); ?></p>
					</div>
					<button type="button" class="button button-secondary" id="chatbot-preview-toggle" aria-pressed="false">
						<?php esc_html_e( 'Open panel', 'chatbot-plugin-wp' ); ?>
					</button>
				</div>
				<div class="chatbot-admin-card__body">
					<div class="chatbot-admin-preview">
						<div
							class="chatbot-admin-preview__viewport"
							id="chatbot-preview-viewport"
							data-preview-position="<?php echo esc_attr( $position ); ?>"
							data-preview-panel-open="false"
							aria-label="<?php esc_attr_e( 'Web page simulation', 'chatbot-plugin-wp' ); ?>"
						>
							<div class="chatbot-admin-preview__page-mock">
								<span></span><span></span><span></span>
							</div>
							<div class="maicb-preview-widget-host" aria-hidden="false"></div>
							<div class="chatbot-admin-preview__disabled-overlay" id="chatbot-preview-disabled-overlay" hidden>
								<p id="chatbot-preview-disabled-text"></p>
							</div>
						</div>
						<p class="chatbot-admin-preview__hint" id="chatbot-preview-hint"><?php echo esc_html( $hint_text ); ?></p>
						<?php if ( $show_contrast ) : ?>
							<p class="chatbot-admin-preview__contrast" id="chatbot-preview-contrast" hidden role="status"></p>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function render_general_fields( array $settings ): void {
		$display_defaults = self::translated_general_defaults();
		$limits           = self::general_field_limits();
		$widget_on    = ! empty( $settings['widget_enabled'] );
		$streaming_on = ! empty( $settings['streaming_enabled'] );
		$position     = sanitize_key( (string) ( $settings['style_position'] ?? 'bottom-right' ) );
		if ( ! in_array( $position, self::style_positions(), true ) ) {
			$position = 'bottom-right';
		}

		$model_url = admin_url( 'admin.php?page=chatbot-plugin&tab=model' );
		?>
		<div class="chatbot-admin-layout chatbot-admin-layout--split">
			<div class="chatbot-admin-general-fields">
		<?php
		self::card_open(
			__( 'Widget availability', 'chatbot-plugin-wp' ),
			__( 'Choose whether the chat appears automatically on every page.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Global widget', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<label class="chatbot-admin-toggle">
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[widget_enabled]" value="0" />
						<input type="checkbox" id="chatbot-widget-enabled" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[widget_enabled]" value="1" <?php checked( $widget_on ); ?> />
						<span><?php esc_html_e( 'Show site-wide (wp_footer)', 'chatbot-plugin-wp' ); ?></span>
					</label>
					<?php if ( ! $widget_on ) : ?>
						<p class="description chatbot-admin-general-notice"><?php esc_html_e( 'While disabled, use the shortcode below to embed the chat on specific pages.', 'chatbot-plugin-wp' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<div class="chatbot-admin-embed-box">
			<label for="chatbot-shortcode-display" class="chatbot-admin-embed-box__label"><?php esc_html_e( 'Embed shortcode', 'chatbot-plugin-wp' ); ?></label>
			<div class="chatbot-admin-embed-box__row">
				<input type="text" id="chatbot-shortcode-display" class="large-text code" readonly value="[chatbot_widget]" />
				<button type="button" class="button button-secondary" id="chatbot-copy-shortcode"><?php esc_html_e( 'Copy shortcode', 'chatbot-plugin-wp' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Place this shortcode in a page, post, or block where you want the chat to appear.', 'chatbot-plugin-wp' ); ?></p>
		</div>
		<?php
		self::card_close();

		self::card_open(
			__( 'Visitor-facing copy', 'chatbot-plugin-wp' ),
			__( 'Text shown in the widget header and as the first assistant message.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Widget title', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input
						type="text"
						class="regular-text chatbot-admin-char-field"
						name="<?php echo esc_attr( self::OPTION_KEY ); ?>[widget_title]"
						id="chatbot-widget-title"
						value="<?php echo esc_attr( (string) $settings['widget_title'] ); ?>"
						maxlength="<?php echo esc_attr( (string) $limits['widget_title'] ); ?>"
						placeholder="<?php echo esc_attr( (string) $display_defaults['widget_title'] ); ?>"
						data-char-max="<?php echo esc_attr( (string) $limits['widget_title'] ); ?>"
					/>
					<p class="chatbot-admin-char-count" data-char-for="chatbot-widget-title" aria-live="polite"></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Subtitle', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input
						type="text"
						class="regular-text chatbot-admin-char-field"
						name="<?php echo esc_attr( self::OPTION_KEY ); ?>[widget_subtitle]"
						id="chatbot-widget-subtitle"
						value="<?php echo esc_attr( (string) $settings['widget_subtitle'] ); ?>"
						maxlength="<?php echo esc_attr( (string) $limits['widget_subtitle'] ); ?>"
						placeholder="<?php echo esc_attr( (string) $display_defaults['widget_subtitle'] ); ?>"
						data-char-max="<?php echo esc_attr( (string) $limits['widget_subtitle'] ); ?>"
					/>
					<p class="chatbot-admin-char-count" data-char-for="chatbot-widget-subtitle" aria-live="polite"></p>
					<p class="description"><?php esc_html_e( 'Shown under the title in the chat header (e.g. status line).', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Welcome message', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<textarea
						name="<?php echo esc_attr( self::OPTION_KEY ); ?>[welcome_message]"
						id="chatbot-welcome-message"
						rows="4"
						class="large-text chatbot-admin-char-field"
						maxlength="<?php echo esc_attr( (string) $limits['welcome_message'] ); ?>"
						placeholder="<?php echo esc_attr( (string) $display_defaults['welcome_message'] ); ?>"
						data-char-max="<?php echo esc_attr( (string) $limits['welcome_message'] ); ?>"
					><?php echo esc_textarea( (string) $settings['welcome_message'] ); ?></textarea>
					<p class="chatbot-admin-char-count" data-char-for="chatbot-welcome-message" aria-live="polite"></p>
					<p class="description"><?php esc_html_e( 'First message visitors see when they open the chat. Visible to everyone.', 'chatbot-plugin-wp' ); ?></p>
					<p class="chatbot-admin-field-actions">
						<button type="button" class="button button-secondary" id="chatbot-restore-welcome" data-default="<?php echo esc_attr( (string) $display_defaults['welcome_message'] ); ?>">
							<?php esc_html_e( 'Restore default welcome', 'chatbot-plugin-wp' ); ?>
						</button>
					</p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();

		self::card_open(
			__( 'AI behavior', 'chatbot-plugin-wp' ),
			__( 'Instructions sent to the model with every request. Visitors do not see this text.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'System instructions', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<textarea
						name="<?php echo esc_attr( self::OPTION_KEY ); ?>[system_prompt]"
						id="chatbot-system-prompt"
						rows="6"
						class="large-text chatbot-admin-char-field"
						maxlength="<?php echo esc_attr( (string) $limits['system_prompt'] ); ?>"
						placeholder="<?php echo esc_attr( (string) $display_defaults['system_prompt'] ); ?>"
						data-char-max="<?php echo esc_attr( (string) $limits['system_prompt'] ); ?>"
					><?php echo esc_textarea( (string) $settings['system_prompt'] ); ?></textarea>
					<p class="chatbot-admin-char-count" data-char-for="chatbot-system-prompt" aria-live="polite"></p>
					<p class="description">
						<?php esc_html_e( 'Defines tone, scope, and safety. Not shown in the chat UI.', 'chatbot-plugin-wp' ); ?>
						<a href="<?php echo esc_url( $model_url ); ?>"><?php esc_html_e( 'Model and timeout settings', 'chatbot-plugin-wp' ); ?></a>
					</p>
					<p class="chatbot-admin-field-actions">
						<button type="button" class="button button-secondary" id="chatbot-restore-system-prompt" data-default="<?php echo esc_attr( (string) $display_defaults['system_prompt'] ); ?>">
							<?php esc_html_e( 'Restore default system prompt', 'chatbot-plugin-wp' ); ?>
						</button>
					</p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();

		self::card_open(
			__( 'Response delivery', 'chatbot-plugin-wp' ),
			__( 'How assistant replies appear while the model is generating.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Simulated streaming', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<label class="chatbot-admin-toggle">
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[streaming_enabled]" value="0" />
						<input type="checkbox" id="chatbot-streaming-enabled" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[streaming_enabled]" value="1" <?php checked( $streaming_on ); ?> />
						<span><?php esc_html_e( 'Enable chunked response', 'chatbot-plugin-wp' ); ?></span>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, the reply is revealed in small chunks for a typing effect. When disabled, the full message appears at once.', 'chatbot-plugin-wp' ); ?>
						<a href="<?php echo esc_url( $model_url ); ?>"><?php esc_html_e( 'Request timeout', 'chatbot-plugin-wp' ); ?></a>
					</p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();
		?>
			</div>
		<?php
		self::render_content_preview_panel(
			__( 'Preview uses your saved chat style. Edit appearance under Chat style.', 'chatbot-plugin-wp' ),
			$position,
			false
		);
		?>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function render_security_fields( array $settings ): void {
		$site_origin = esc_url( home_url( '/' ) );
		self::card_open(
			__( 'Origins and access', 'chatbot-plugin-wp' ),
			__( 'Control which domains can call the chat endpoint.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Allowed origins', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<textarea name="<?php echo esc_attr( self::OPTION_KEY ); ?>[allowed_origins]" rows="3" class="large-text code" placeholder="<?php echo esc_attr( $site_origin ); ?>"><?php echo esc_textarea( (string) ( $settings['allowed_origins'] ?? '' ) ); ?></textarea>
					<p class="description">
						<?php
						printf(
							/* translators: %s: site home URL */
							esc_html__( 'Comma-separated URLs. Empty = this site only (%s). Equivalent to CHAT_ALLOWED_ORIGINS.', 'chatbot-plugin-wp' ),
							esc_html( $site_origin )
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Internal chat URL', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="url" class="regular-text code" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[internal_chat_base_url]" value="<?php echo esc_attr( (string) ( $settings['internal_chat_base_url'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( untrailingslashit( home_url() ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Optional. Leave empty in most installations. If set, use a local URL (e.g. http://127.0.0.1); do not use the public URL with Cloudflare.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();

		self::card_open(
			__( 'Cache and telemetry', 'chatbot-plugin-wp' ),
			__( 'Reduce repeated model calls and optionally log events to a file.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Cache TTL (seconds)', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="0" max="86400" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[cache_ttl_seconds]" value="<?php echo esc_attr( (string) ( $settings['cache_ttl_seconds'] ?? 1800 ) ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( '0 = disable cache. Equivalent to CHAT_CACHE_TTL_SECONDS.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Telemetry log path', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" class="large-text code" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[telemetry_log_path]" value="<?php echo esc_attr( (string) ( $settings['telemetry_log_path'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( WP_CONTENT_DIR . '/chatbot-telemetry.log' ); ?>" />
					<p class="description"><?php esc_html_e( 'Optional. In addition to the database, write events to this file. Equivalent to CHAT_TELEMETRY_LOG_PATH.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'History retention (days)', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="0" max="3650" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[history_retention_days]" value="<?php echo esc_attr( (string) ( $settings['history_retention_days'] ?? 0 ) ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( '0 = keep indefinitely. Older conversations are purged automatically each day.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Telemetry retention (days)', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="0" max="3650" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[telemetry_retention_days]" value="<?php echo esc_attr( (string) ( $settings['telemetry_retention_days'] ?? 0 ) ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( '0 = keep indefinitely. Older statistics events are purged automatically each day.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();

		self::card_open(
			__( 'Rate limits', 'chatbot-plugin-wp' ),
			__( 'Protect the endpoint and AI provider quota from abuse.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Per IP / minute', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="1" max="120" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rate_limit_per_minute]" value="<?php echo esc_attr( (string) ( $settings['rate_limit_per_minute'] ?? 10 ) ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'CHAT_RATE_LIMIT_PER_MINUTE', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Per IP / day', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="1" max="1000" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rate_limit_per_day]" value="<?php echo esc_attr( (string) ( $settings['rate_limit_per_day'] ?? 30 ) ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'CHAT_RATE_LIMIT_PER_DAY', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Model / minute (global)', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="1" max="120" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rate_limit_model_per_minute]" value="<?php echo esc_attr( (string) ( $settings['rate_limit_model_per_minute'] ?? 6 ) ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'CHAT_RATE_LIMIT_MODEL_PER_MINUTE', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Model / day (global)', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="1" max="5000" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rate_limit_model_per_day]" value="<?php echo esc_attr( (string) ( $settings['rate_limit_model_per_day'] ?? 24 ) ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'CHAT_RATE_LIMIT_MODEL_PER_DAY', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Soft threshold', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="0.1" max="1" step="0.05" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rate_limit_soft_threshold]" value="<?php echo esc_attr( (string) ( $settings['rate_limit_soft_threshold'] ?? 0.8 ) ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'Fraction of the limit (0.1–1) at which a warning is logged. CHAT_RATE_LIMIT_SOFT_THRESHOLD', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Suspend IP after violations', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="1" max="20" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ip_suspend_after_violations]" value="<?php echo esc_attr( (string) ( $settings['ip_suspend_after_violations'] ?? 3 ) ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'CHAT_IP_SUSPEND_AFTER_VIOLATIONS', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Suspension duration (sec)', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="60" max="86400" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ip_suspend_seconds]" value="<?php echo esc_attr( (string) ( $settings['ip_suspend_seconds'] ?? 900 ) ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'CHAT_IP_SUSPEND_SECONDS', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function render_model_fields( array $settings ): void {
		$provider = (string) ( $settings['provider'] ?? 'gemini' );
		self::card_open(
			__( 'AI provider', 'chatbot-plugin-wp' ),
			__( 'Choose the engine and configure credentials and models.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Provider', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[provider]" id="chatbot-provider">
						<option value="gemini" <?php selected( $provider, 'gemini' ); ?>>Google Gemini</option>
						<option value="deepseek" <?php selected( $provider, 'deepseek' ); ?>>DeepSeek</option>
						<option value="ollama" <?php selected( $provider, 'ollama' ); ?>>Ollama</option>
						<option value="openai_compatible" <?php selected( $provider, 'openai_compatible' ); ?>>OpenAI-compatible</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Model', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[model]" id="chatbot-model" value="<?php echo esc_attr( (string) $settings['model'] ); ?>" />
					<p class="description" id="chatbot-model-desc"><?php esc_html_e( 'E.g.: gemini-3.1-flash-lite, deepseek-v4-flash, llama3, gpt-4o-mini.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr class="chatbot-field-gemini chatbot-field-deepseek">
				<th scope="row"><?php esc_html_e( 'Fallback model', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" class="large-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[model_candidates]" value="<?php echo esc_attr( (string) $settings['model_candidates'] ); ?>" />
					<p class="description" id="chatbot-model-candidates-desc"><?php esc_html_e( 'Gemini only. Comma-separated rotation pool (429/404/400 tries the next). Equivalent to GEMINI_MODEL_CANDIDATES.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr class="chatbot-field-ollama">
				<th scope="row"><?php esc_html_e( 'Ollama base URL', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="url" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ollama_base_url]" value="<?php echo esc_attr( (string) $settings['ollama_base_url'] ); ?>" />
				</td>
			</tr>
			<tr class="chatbot-field-openai">
				<th scope="row"><?php esc_html_e( 'OpenAI-compatible base URL', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="url" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[openai_base_url]" value="<?php echo esc_attr( (string) $settings['openai_base_url'] ); ?>" />
				</td>
			</tr>
			<tr class="chatbot-field-deepseek-url">
				<th scope="row"><?php esc_html_e( 'DeepSeek base URL', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="url" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[deepseek_base_url]" value="<?php echo esc_attr( (string) ( $settings['deepseek_base_url'] ?? 'https://api.deepseek.com/v1' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Default: https://api.deepseek.com/v1', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Timeout (seconds)', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="5" max="120" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[request_timeout]" value="<?php echo esc_attr( (string) $settings['request_timeout'] ); ?>" />
				</td>
			</tr>
			<tr class="chatbot-field-api-key">
				<th scope="row"><?php esc_html_e( 'API Key', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="password" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_key]" value="" placeholder="<?php echo ! empty( $settings['api_key'] ) ? '••••••••' : ''; ?>" autocomplete="new-password" />
					<p class="description" id="chatbot-api-key-desc"><?php esc_html_e( 'Leave empty to keep the current key. In production define CHATBOT_GEMINI_API_KEY, CHATBOT_DEEPSEEK_API_KEY or CHATBOT_OPENAI_API_KEY in wp-config.php.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
		</table>
		<script>
		(function () {
			const sel = document.getElementById('chatbot-provider');
			if (!sel) return;
			const modelDesc = document.getElementById('chatbot-model-desc');
			const candidatesDesc = document.getElementById('chatbot-model-candidates-desc');
			const descriptions = {
				gemini: {
					model: '<?php echo esc_js( __( 'E.g.: gemini-3.1-flash-lite, gemini-2.5-flash. Equivalent to GEMINI_MODEL.', 'chatbot-plugin-wp' ) ); ?>',
					candidates: '<?php echo esc_js( __( 'Comma-separated rotation pool (429/404/400 tries the next). Equivalent to GEMINI_MODEL_CANDIDATES.', 'chatbot-plugin-wp' ) ); ?>',
				},
				deepseek: {
					model: '<?php echo esc_js( __( 'E.g.: deepseek-v4-flash, deepseek-v4-pro, deepseek-chat.', 'chatbot-plugin-wp' ) ); ?>',
					candidates: '<?php echo esc_js( __( 'Comma-separated DeepSeek fallback pool (429/404/400 tries the next).', 'chatbot-plugin-wp' ) ); ?>',
				},
				ollama: {
					model: '<?php echo esc_js( __( 'Name of the model installed in Ollama (e.g. llama3).', 'chatbot-plugin-wp' ) ); ?>',
					candidates: '',
				},
				openai_compatible: {
					model: '<?php echo esc_js( __( 'E.g.: gpt-4o-mini, gpt-4o.', 'chatbot-plugin-wp' ) ); ?>',
					candidates: '',
				},
			};
			function toggle() {
				const v = sel.value;
				document.querySelectorAll('.chatbot-field-api-key').forEach(el => {
					el.style.display = v === 'ollama' ? 'none' : '';
				});
				document.querySelectorAll('.chatbot-field-gemini').forEach(el => {
					el.style.display = v === 'gemini' ? '' : 'none';
				});
				document.querySelectorAll('.chatbot-field-deepseek').forEach(el => {
					el.style.display = v === 'deepseek' ? '' : 'none';
				});
				document.querySelectorAll('.chatbot-field-ollama').forEach(el => {
					el.style.display = v === 'ollama' ? '' : 'none';
				});
				document.querySelectorAll('.chatbot-field-openai').forEach(el => {
					el.style.display = v === 'openai_compatible' ? '' : 'none';
				});
				document.querySelectorAll('.chatbot-field-deepseek-url').forEach(el => {
					el.style.display = v === 'deepseek' ? '' : 'none';
				});
				if (modelDesc && descriptions[v]) {
					modelDesc.textContent = descriptions[v].model;
				}
				if (candidatesDesc && descriptions[v]) {
					candidatesDesc.textContent = descriptions[v].candidates;
				}
			}
			sel.addEventListener('change', toggle);
			toggle();
		})();
		</script>
		<?php
		self::card_close();
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function render_style_fields( array $settings ): void {
		$preset   = (string) ( $settings['style_preset'] ?? 'default' );
		$position = (string) ( $settings['style_position'] ?? 'bottom-right' );
		$preset_meta = self::style_preset_meta();
		$position_labels = self::style_position_labels();
		?>
		<div class="chatbot-admin-layout chatbot-admin-layout--split">
			<div class="chatbot-admin-style-fields">
		<?php
		self::card_open(
			__( 'Visual theme', 'chatbot-plugin-wp' ),
			__( 'Color palette and shapes. Typography is configured under Colors and shape.', 'chatbot-plugin-wp' )
		);
		?>
		<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_preset]" id="chatbot-style-preset" value="<?php echo esc_attr( $preset ); ?>" />
		<div class="chatbot-theme-grid" role="radiogroup" aria-label="<?php esc_attr_e( 'Theme', 'chatbot-plugin-wp' ); ?>">
			<?php foreach ( self::style_presets() as $id ) : ?>
				<?php
				$meta = $preset_meta[ $id ] ?? array(
					'label'  => $id,
					'desc'   => '',
					'badge'  => '',
					'colors' => array(),
				);
				$colors = $meta['colors'] ?? array();
				$badge  = (string) ( $meta['badge'] ?? '' );
				$badge_type = (string) ( $meta['badge_type'] ?? 'light' );
				?>
				<button type="button"
					class="chatbot-theme-card<?php echo $preset === $id ? ' is-active' : ''; ?>"
					data-preset="<?php echo esc_attr( $id ); ?>"
					role="radio"
					aria-checked="<?php echo $preset === $id ? 'true' : 'false'; ?>"
					aria-label="<?php echo esc_attr( (string) ( $meta['label'] ?? $id ) ); ?>">
					<span class="chatbot-theme-card__swatches" aria-hidden="true">
						<?php foreach ( array_slice( (array) $colors, 0, 3 ) as $color ) : ?>
							<span class="chatbot-theme-card__swatch" style="background:<?php echo esc_attr( (string) $color ); ?>"></span>
						<?php endforeach; ?>
					</span>
					<span class="chatbot-theme-card__label"><?php echo esc_html( (string) ( $meta['label'] ?? $id ) ); ?></span>
					<?php if ( $badge !== '' ) : ?>
						<span class="chatbot-theme-card__badge chatbot-theme-card__badge--<?php echo esc_attr( $badge_type ); ?>"><?php echo esc_html( $badge ); ?></span>
					<?php endif; ?>
				</button>
			<?php endforeach; ?>
		</div>
		<p class="description" id="chatbot-style-preset-desc">
			<?php
			$current_meta = $preset_meta[ $preset ] ?? array( 'desc' => '' );
			echo esc_html( (string) ( $current_meta['desc'] ?? '' ) );
			?>
		</p>
		<?php
		self::card_close();

		self::card_open(
			__( 'Colors and shape', 'chatbot-plugin-wp' ),
			__( 'Optional: override the selected preset.', 'chatbot-plugin-wp' )
		);
		?>
		<p class="chatbot-style-actions">
			<button type="button" class="button button-secondary" id="chatbot-style-reset-overrides"><?php esc_html_e( 'Reset color overrides', 'chatbot-plugin-wp' ); ?></button>
			<button type="button" class="button button-secondary" id="chatbot-style-export"><?php esc_html_e( 'Export theme', 'chatbot-plugin-wp' ); ?></button>
			<button type="button" class="button button-secondary" id="chatbot-style-import"><?php esc_html_e( 'Import theme', 'chatbot-plugin-wp' ); ?></button>
			<input type="file" id="chatbot-style-import-file" accept="application/json,.json" hidden />
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Primary color', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" class="chatbot-color-picker" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_primary]" value="<?php echo esc_attr( (string) $settings['style_primary'] ); ?>" placeholder="#2563eb" data-default-color="#2563eb" />
					<p class="description"><?php esc_html_e( 'Send button, user bubbles, and accents.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Accent color', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" class="chatbot-color-picker" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_accent]" value="<?php echo esc_attr( (string) $settings['style_accent'] ); ?>" placeholder="#7c3aed" data-default-color="#7c3aed" />
					<p class="description"><?php esc_html_e( 'Floating button gradient.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Background color', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" class="chatbot-color-picker" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_bg]" value="<?php echo esc_attr( (string) ( $settings['style_bg'] ?? '' ) ); ?>" placeholder="" data-default-color="" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Text color', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" class="chatbot-color-picker" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_fg]" value="<?php echo esc_attr( (string) ( $settings['style_fg'] ?? '' ) ); ?>" placeholder="" data-default-color="" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Font', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_font_family]">
						<?php foreach ( self::style_font_family_labels() as $font_id => $font_label ) : ?>
							<option value="<?php echo esc_attr( $font_id ); ?>" <?php selected( (string) ( $settings['style_font_family'] ?? 'system' ), $font_id ); ?>>
								<?php echo esc_html( $font_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Border radius', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_radius]" value="<?php echo esc_attr( (string) $settings['style_radius'] ); ?>" placeholder="1.5rem" class="regular-text" />
					<p class="description"><?php esc_html_e( 'E.g.: 0.75rem, 1.5rem, 16px', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Panel width', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_panel_width]" value="<?php echo esc_attr( (string) ( $settings['style_panel_width'] ?? '' ) ); ?>" placeholder="380px" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Empty = responsive width (max. 380px).', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Panel max height', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_panel_max_height]" value="<?php echo esc_attr( (string) ( $settings['style_panel_max_height'] ?? '' ) ); ?>" placeholder="70vh" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Limits the message area height. E.g.: 60vh, 480px', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Stack order (z-index)', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_z_index]" value="<?php echo esc_attr( (string) (int) ( $settings['style_z_index'] ?? 0 ) ); ?>" min="0" max="2147483646" step="1" class="small-text" />
					<p class="description"><?php esc_html_e( '0 = default. Raise if another plugin covers the chat.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();

		self::card_open(
			__( 'Motion and automatic theme', 'chatbot-plugin-wp' ),
			__( 'Accessibility and system appearance.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Reduce motion', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<label class="chatbot-admin-toggle">
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_reduce_motion]" value="0" />
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_reduce_motion]" value="1" <?php checked( ! empty( $settings['style_reduce_motion'] ) ); ?> />
						<span><?php esc_html_e( 'Disable launcher pulse animation', 'chatbot-plugin-wp' ); ?></span>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Match system theme', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<label class="chatbot-admin-toggle">
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_preset_auto]" value="0" />
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_preset_auto]" value="1" id="chatbot-style-preset-auto" <?php checked( ! empty( $settings['style_preset_auto'] ) ); ?> />
						<span><?php esc_html_e( 'Use dark preset when the visitor prefers dark mode', 'chatbot-plugin-wp' ); ?></span>
					</label>
					<p class="description"><?php esc_html_e( 'Light mode uses the theme selected above.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Dark mode preset', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_preset_auto_dark]">
						<?php foreach ( self::style_presets() as $id ) : ?>
							<?php $meta = $preset_meta[ $id ] ?? array( 'label' => $id ); ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( (string) ( $settings['style_preset_auto_dark'] ?? 'dark-glass' ), $id ); ?>>
								<?php echo esc_html( (string) ( $meta['label'] ?? $id ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Custom CSS', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<textarea name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_custom_css]" rows="6" class="large-text code" placeholder="#chatbot-plugin-root .maicb-send { }"><?php echo esc_textarea( (string) ( $settings['style_custom_css'] ?? '' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Scoped to the widget root. No @import. Max 8000 characters.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();

		self::card_open(
			__( 'Developer credit', 'chatbot-plugin-wp' ),
			__( 'Optional attribution shown inside the chat panel.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Show in chat', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<label class="chatbot-admin-toggle">
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_show_credit]" value="0" />
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_show_credit]" value="1" <?php checked( ! empty( $settings['style_show_credit'] ) ); ?> />
						<span><?php esc_html_e( 'Show developer credit in chat', 'chatbot-plugin-wp' ); ?></span>
					</label>
					<p class="description"><?php esc_html_e( 'Adds a small line below the message box with the plugin name and a link. Off by default.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();

		self::card_open(
			__( 'Screen position', 'chatbot-plugin-wp' ),
			__( 'Where the panel and floating button appear on the site.', 'chatbot-plugin-wp' )
		);
		?>
		<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_position]" value="<?php echo esc_attr( $position ); ?>" id="chatbot-style-position-input" />
		<div class="chatbot-position-picker">
			<div class="chatbot-position-map" role="group" aria-label="<?php esc_attr_e( 'Widget position', 'chatbot-plugin-wp' ); ?>">
				<?php foreach ( self::style_positions() as $pos ) : ?>
					<button type="button"
						class="chatbot-position-btn<?php echo $position === $pos ? ' is-active' : ''; ?>"
						data-position="<?php echo esc_attr( $pos ); ?>"
						title="<?php echo esc_attr( $position_labels[ $pos ] ?? $pos ); ?>">
						<span class="screen-reader-text"><?php echo esc_html( $position_labels[ $pos ] ?? $pos ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<p class="chatbot-position-label" id="chatbot-position-label"><?php echo esc_html( $position_labels[ $position ] ?? $position ); ?></p>
			<p class="description chatbot-position-picker__hint">
				<?php esc_html_e( 'The preview closes the panel when you change position so you can see where the floating button will sit. Use “Open panel” to preview the chat window.', 'chatbot-plugin-wp' ); ?>
			</p>
		</div>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Edge margin', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_offset]" value="<?php echo esc_attr( (string) ( $settings['style_offset'] ?? '1rem' ) ); ?>" placeholder="1rem" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Floating button text', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<label class="chatbot-admin-toggle">
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_launcher_label]" value="0" />
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_launcher_label]" value="1" <?php checked( ! empty( $settings['style_launcher_label'] ) ); ?> />
						<span><?php esc_html_e( 'Show title next to the 💬 icon', 'chatbot-plugin-wp' ); ?></span>
					</label>
					<p class="description"><?php esc_html_e( 'The title is configured under General → Widget header.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();
		?>
			</div>
		<?php
		self::render_content_preview_panel(
			__( 'The preview reflects theme, position, and styles instantly. Save to apply them on the public site.', 'chatbot-plugin-wp' ),
			$position,
			true
		);
		?>
		</div>
		<?php
	}


	private static function get_stats_filters_from_request(): array {
		$days = array_key_exists( 'days', $_GET )
			? max( 0, min( 365, (int) $_GET['days'] ) )
			: 30;

		return array(
			'days'            => $days > 0 ? $days : 0,
			'provider'        => isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( (string) $_GET['provider'] ) ) : 'all',
			'status'          => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( (string) $_GET['status'] ) ) : 'all',
			'model'           => isset( $_GET['model'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['model'] ) ) : 'all',
			'error_code'      => isset( $_GET['error_code'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['error_code'] ) ) : 'all',
			'conversation_id' => isset( $_GET['conversation_id'] ) ? max( 0, (int) $_GET['conversation_id'] ) : 0,
		);
	}

	/**
	 * @param array<string, mixed> $query_args
	 */
	private static function build_stats_url( array $query_args ): string {
		$args = array_merge(
			array(
				'page' => 'chatbot-plugin',
				'tab'  => 'stats',
			),
			$query_args
		);

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * @param array<string, mixed> $filters
	 */
	private static function stats_has_active_filters( array $filters ): bool {
		return 'all' !== (string) ( $filters['provider'] ?? 'all' )
			|| 'all' !== (string) ( $filters['status'] ?? 'all' )
			|| 'all' !== (string) ( $filters['model'] ?? 'all' )
			|| 'all' !== (string) ( $filters['error_code'] ?? 'all' )
			|| (int) ( $filters['conversation_id'] ?? 0 ) > 0;
	}

	/**
	 * @param array<string, mixed> $base_args
	 */
	private static function render_stats_pagination( int $page, int $pages, array $base_args ): void {
		if ( $pages <= 1 ) {
			return;
		}

		echo '<nav class="chatbot-admin-tablenav chatbot-admin-tablenav--stats" aria-label="' . esc_attr__( 'Pagination', 'chatbot-plugin-wp' ) . '">';

		if ( $page > 1 ) {
			$prev_args          = $base_args;
			$prev_args['paged'] = $page - 1;
			echo '<a class="chatbot-admin-tablenav__prev" href="' . esc_url( self::build_stats_url( $prev_args ) ) . '">' . esc_html__( 'Previous', 'chatbot-plugin-wp' ) . '</a>';
		}

		echo '<span class="chatbot-admin-tablenav__status">';
		echo esc_html(
			sprintf(
				/* translators: 1: current page, 2: total pages */
				__( 'Page %1$d of %2$d', 'chatbot-plugin-wp' ),
				$page,
				$pages
			)
		);
		echo '</span>';

		$window = 5;
		$start  = max( 1, $page - $window );
		$end    = min( $pages, $page + $window );

		echo '<span class="chatbot-admin-tablenav__pages">';
		for ( $i = $start; $i <= $end; $i++ ) {
			$page_args          = $base_args;
			$page_args['paged'] = $i;
			echo '<a href="' . esc_url( self::build_stats_url( $page_args ) ) . '" class="' . esc_attr( $page === $i ? 'is-active' : '' ) . '">' . esc_html( (string) $i ) . '</a>';
		}
		echo '</span>';

		if ( $page < $pages ) {
			$next_args          = $base_args;
			$next_args['paged'] = $page + 1;
			echo '<a class="chatbot-admin-tablenav__next" href="' . esc_url( self::build_stats_url( $next_args ) ) . '">' . esc_html__( 'Next', 'chatbot-plugin-wp' ) . '</a>';
		}

		echo '</nav>';
	}

	private static function format_telemetry_status_label( string $status ): string {
		$labels = array(
			'success'         => __( 'Success', 'chatbot-plugin-wp' ),
			'cached'          => __( 'Cached', 'chatbot-plugin-wp' ),
			'error'           => __( 'Error', 'chatbot-plugin-wp' ),
			'rate_limited'    => __( 'Rate limited', 'chatbot-plugin-wp' ),
			'config_error'    => __( 'Configuration error', 'chatbot-plugin-wp' ),
			'invalid_request' => __( 'Invalid request', 'chatbot-plugin-wp' ),
			'ok'              => __( 'OK', 'chatbot-plugin-wp' ),
		);

		return $labels[ $status ] ?? $status;
	}

	private static function format_telemetry_status_class( string $status ): string {
		if ( in_array( $status, array( 'success', 'ok', 'cached' ), true ) ) {
			return 'cached' === $status ? 'chatbot-admin-status--cached' : 'chatbot-admin-status--ok';
		}
		return 'chatbot-admin-status--err';
	}

	private static function format_error_code_label( string $code ): string {
		$labels = array(
			'RATE_LIMIT_GENERAL'   => __( 'General limit', 'chatbot-plugin-wp' ),
			'RATE_LIMIT_MODEL'     => __( 'Model limit', 'chatbot-plugin-wp' ),
			'INVALID_REQUEST'      => __( 'Invalid request', 'chatbot-plugin-wp' ),
			'CONFIGURATION_ERROR'  => __( 'Configuration error', 'chatbot-plugin-wp' ),
			'SERVER_ERROR'         => __( 'Server error', 'chatbot-plugin-wp' ),
		);

		return $labels[ $code ] ?? $code;
	}

	/**
	 * @param array<int, array<string, mixed>> $events
	 * @return array<int, string>
	 */
	private static function map_conversation_public_ids( array $events ): array {
		$ids = array();
		foreach ( $events as $event ) {
			$id = (int) ( $event['conversation_id'] ?? 0 );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return Chatbot_Chat_History::get_public_ids_by_conversation_ids( $ids );
	}

	private static function render_stats_tab(): void {
		$page    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$per     = 25;
		$filters = self::get_stats_filters_from_request();
		$days    = (int) $filters['days'];

		$query_args = array_merge(
			$filters,
			array(
				'per_page' => $per,
				'offset'   => ( $page - 1 ) * $per,
			)
		);

		$summary      = Chatbot_Telemetry::get_summary( $filters );
		$daily_series = Chatbot_Telemetry::get_daily_series( $filters );
		$events       = Chatbot_Telemetry::list_events( $query_args );
		$total        = Chatbot_Telemetry::count_events( $filters );
		$pages        = (int) ceil( $total / $per );
		$models       = Chatbot_Telemetry::get_distinct_models( $filters );
		$error_codes  = Chatbot_Telemetry::get_distinct_error_codes( $filters );
		$conv_map     = self::map_conversation_public_ids( $events );

		$settings  = Chatbot_Plugin::get_settings();
		$retention = (int) ( $settings['telemetry_retention_days'] ?? 0 );
		$has_filters = self::stats_has_active_filters( $filters );
		$totals    = $summary['totals'] ?? array();

		$periods = array(
			0   => __( 'All', 'chatbot-plugin-wp' ),
			7   => __( '7 days', 'chatbot-plugin-wp' ),
			30  => __( '30 days', 'chatbot-plugin-wp' ),
			90  => __( '90 days', 'chatbot-plugin-wp' ),
			365 => __( '365 days', 'chatbot-plugin-wp' ),
		);

		$export_url = wp_nonce_url(
			add_query_arg(
				array_merge(
					array( 'action' => 'chatbot_export_csv' ),
					$filters
				),
				admin_url( 'admin-post.php' )
			),
			'chatbot_export_csv'
		);

		$purge_url = '';
		if ( $retention > 0 ) {
			$purge_url = wp_nonce_url(
				add_query_arg( 'action', 'chatbot_purge_telemetry', admin_url( 'admin-post.php' ) ),
				'chatbot_purge_telemetry'
			);
		}

		$max_daily = 0;
		foreach ( $daily_series as $row ) {
			$max_daily = max( $max_daily, (int) ( $row['total'] ?? 0 ) );
		}

		if ( isset( $_GET['chatbot_purged'] ) ) {
			$purged = isset( $_GET['purged_events'] ) ? (int) $_GET['purged_events'] : 0;
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html(
				sprintf(
					/* translators: %d: number of deleted events */
					__( 'Purge complete: %d events deleted.', 'chatbot-plugin-wp' ),
					$purged
				)
			);
			echo '</p></div>';
		}
		?>
		<div class="chatbot-admin-stats-toolbar">
			<div class="chatbot-admin-stats-toolbar__intro">
				<p><?php esc_html_e( 'Chatbot usage telemetry on your site.', 'chatbot-plugin-wp' ); ?></p>
				<a class="chatbot-admin-stats-toolbar__link" href="<?php echo esc_url( self::build_history_url( array( 'days' => $days ) ) ); ?>">
					<?php esc_html_e( 'View conversations for the period', 'chatbot-plugin-wp' ); ?>
				</a>
			</div>
			<div class="chatbot-admin-stats-toolbar__actions">
				<div class="chatbot-admin-pills" role="group" aria-label="<?php esc_attr_e( 'Period', 'chatbot-plugin-wp' ); ?>">
					<?php foreach ( $periods as $p => $label ) : ?>
						<a href="<?php echo esc_url( self::build_stats_url( array_merge( $filters, array( 'days' => $p, 'paged' => 1 ) ) ) ); ?>"
							class="<?php echo (int) $days === (int) $p ? 'is-active' : ''; ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endforeach; ?>
				</div>
				<a class="button chatbot-admin-export" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'chatbot-plugin-wp' ); ?></a>
				<?php if ( '' !== $purge_url ) : ?>
					<a class="button button-secondary chatbot-admin-stats-purge" href="<?php echo esc_url( $purge_url ); ?>" data-confirm="<?php esc_attr_e( 'Purge events older than the configured retention period?', 'chatbot-plugin-wp' ); ?>">
						<?php esc_html_e( 'Purge old', 'chatbot-plugin-wp' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<div class="chatbot-admin-kpi-grid chatbot-admin-kpi-grid--stats">
			<div class="chatbot-admin-kpi">
				<span class="chatbot-admin-kpi__label"><?php esc_html_e( 'Total requests', 'chatbot-plugin-wp' ); ?></span>
				<span class="chatbot-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $totals['total_requests'] ?? 0 ) ) ); ?></span>
			</div>
			<div class="chatbot-admin-kpi chatbot-admin-kpi--success">
				<span class="chatbot-admin-kpi__label"><?php esc_html_e( 'Successes', 'chatbot-plugin-wp' ); ?></span>
				<span class="chatbot-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $totals['success_count'] ?? 0 ) ) ); ?></span>
			</div>
			<div class="chatbot-admin-kpi">
				<span class="chatbot-admin-kpi__label"><?php esc_html_e( 'Cached', 'chatbot-plugin-wp' ); ?></span>
				<span class="chatbot-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $totals['cached_count'] ?? 0 ) ) ); ?></span>
			</div>
			<div class="chatbot-admin-kpi chatbot-admin-kpi--error">
				<span class="chatbot-admin-kpi__label"><?php esc_html_e( 'Errors', 'chatbot-plugin-wp' ); ?></span>
				<span class="chatbot-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $totals['error_count'] ?? 0 ) ) ); ?></span>
			</div>
			<div class="chatbot-admin-kpi">
				<span class="chatbot-admin-kpi__label"><?php esc_html_e( 'Success rate', 'chatbot-plugin-wp' ); ?></span>
				<span class="chatbot-admin-kpi__value"><?php echo esc_html( number_format_i18n( (float) ( $totals['success_rate'] ?? 0 ), 1 ) ); ?>%</span>
			</div>
			<div class="chatbot-admin-kpi">
				<span class="chatbot-admin-kpi__label"><?php esc_html_e( 'Average latency', 'chatbot-plugin-wp' ); ?></span>
				<span class="chatbot-admin-kpi__value"><?php echo esc_html( number_format_i18n( (float) ( $totals['avg_latency_ms'] ?? 0 ), 0 ) ); ?> <small>ms</small></span>
			</div>
			<div class="chatbot-admin-kpi">
				<span class="chatbot-admin-kpi__label"><?php esc_html_e( 'P95 latency', 'chatbot-plugin-wp' ); ?></span>
				<span class="chatbot-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $totals['p95_latency_ms'] ?? 0 ) ) ); ?> <small>ms</small></span>
			</div>
		</div>

		<?php if ( ! empty( $daily_series ) && $max_daily > 0 ) : ?>
			<div class="chatbot-admin-card chatbot-admin-stats-series">
				<div class="chatbot-admin-card__head">
					<h2><?php esc_html_e( 'Daily activity', 'chatbot-plugin-wp' ); ?></h2>
				</div>
				<div class="chatbot-admin-card__body">
					<div class="chatbot-admin-stats-bars">
						<?php foreach ( array_reverse( $daily_series ) as $row ) : ?>
							<?php
							$day_total = (int) ( $row['total'] ?? 0 );
							$height    = $max_daily > 0 ? max( 4, (int) round( ( $day_total / $max_daily ) * 100 ) ) : 0;
							?>
							<div class="chatbot-admin-stats-bar" title="<?php echo esc_attr( sprintf( '%s: %d', (string) ( $row['day'] ?? '' ), $day_total ) ); ?>">
								<div class="chatbot-admin-stats-bar__fill" style="height: <?php echo esc_attr( (string) $height ); ?>%;"></div>
								<span class="chatbot-admin-stats-bar__label"><?php echo esc_html( wp_date( 'd/m', strtotime( (string) ( $row['day'] ?? '' ) . ' UTC' ) ) ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<div class="chatbot-admin-stats-grid chatbot-admin-stats-grid--wide">
			<div class="chatbot-admin-card">
				<div class="chatbot-admin-card__head"><h2><?php esc_html_e( 'By status', 'chatbot-plugin-wp' ); ?></h2></div>
				<div class="chatbot-admin-card__body">
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Status', 'chatbot-plugin-wp' ); ?></th><th><?php esc_html_e( 'Count', 'chatbot-plugin-wp' ); ?></th></tr></thead>
						<tbody>
							<?php
							$by_status = (array) ( $summary['by_status'] ?? array() );
							if ( empty( $by_status ) ) :
								?>
								<tr><td colspan="2"><?php esc_html_e( 'No data in this period.', 'chatbot-plugin-wp' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $by_status as $row ) : ?>
									<?php $st = (string) ( $row['status'] ?? '' ); ?>
									<tr>
										<td>
											<a href="<?php echo esc_url( self::build_stats_url( array_merge( $filters, array( 'status' => $st, 'paged' => 1 ) ) ) ); ?>">
												<?php echo esc_html( self::format_telemetry_status_label( $st ) ); ?>
											</a>
										</td>
										<td><?php echo esc_html( number_format_i18n( (int) ( $row['count'] ?? 0 ) ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
			<div class="chatbot-admin-card">
				<div class="chatbot-admin-card__head"><h2><?php esc_html_e( 'By provider', 'chatbot-plugin-wp' ); ?></h2></div>
				<div class="chatbot-admin-card__body">
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Provider', 'chatbot-plugin-wp' ); ?></th><th><?php esc_html_e( 'Count', 'chatbot-plugin-wp' ); ?></th></tr></thead>
						<tbody>
							<?php
							$by_provider = (array) ( $summary['by_provider'] ?? array() );
							if ( empty( $by_provider ) ) :
								?>
								<tr><td colspan="2"><?php esc_html_e( 'No data in this period.', 'chatbot-plugin-wp' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $by_provider as $row ) : ?>
									<?php $pv = (string) ( $row['provider'] ?? '' ); ?>
									<tr>
										<td>
											<a href="<?php echo esc_url( self::build_stats_url( array_merge( $filters, array( 'provider' => $pv, 'paged' => 1 ) ) ) ); ?>">
												<?php echo esc_html( $pv ); ?>
											</a>
										</td>
										<td><?php echo esc_html( number_format_i18n( (int) ( $row['count'] ?? 0 ) ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
			<div class="chatbot-admin-card">
				<div class="chatbot-admin-card__head"><h2><?php esc_html_e( 'By model', 'chatbot-plugin-wp' ); ?></h2></div>
				<div class="chatbot-admin-card__body">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Model', 'chatbot-plugin-wp' ); ?></th>
								<th><?php esc_html_e( 'Count', 'chatbot-plugin-wp' ); ?></th>
								<th><?php esc_html_e( 'Avg. latency', 'chatbot-plugin-wp' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$by_model = (array) ( $summary['by_model'] ?? array() );
							if ( empty( $by_model ) ) :
								?>
								<tr><td colspan="3"><?php esc_html_e( 'No data in this period.', 'chatbot-plugin-wp' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $by_model as $row ) : ?>
									<?php $md = (string) ( $row['model'] ?? '' ); ?>
									<tr>
										<td>
											<a href="<?php echo esc_url( self::build_stats_url( array_merge( $filters, array( 'model' => $md, 'paged' => 1 ) ) ) ); ?>">
												<?php echo esc_html( $md ); ?>
											</a>
										</td>
										<td><?php echo esc_html( number_format_i18n( (int) ( $row['count'] ?? 0 ) ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (float) ( $row['avg_latency_ms'] ?? 0 ), 0 ) ); ?> ms</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
			<div class="chatbot-admin-card">
				<div class="chatbot-admin-card__head"><h2><?php esc_html_e( 'By error code', 'chatbot-plugin-wp' ); ?></h2></div>
				<div class="chatbot-admin-card__body">
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Code', 'chatbot-plugin-wp' ); ?></th><th><?php esc_html_e( 'Count', 'chatbot-plugin-wp' ); ?></th></tr></thead>
						<tbody>
							<?php
							$by_error = (array) ( $summary['by_error'] ?? array() );
							if ( empty( $by_error ) ) :
								?>
								<tr><td colspan="2"><?php esc_html_e( 'No errors in this period.', 'chatbot-plugin-wp' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $by_error as $row ) : ?>
									<?php $ec = (string) ( $row['error_code'] ?? '' ); ?>
									<tr>
										<td>
											<a href="<?php echo esc_url( self::build_stats_url( array_merge( $filters, array( 'error_code' => $ec, 'paged' => 1 ) ) ) ); ?>">
												<?php echo esc_html( self::format_error_code_label( $ec ) ); ?>
											</a>
										</td>
										<td><?php echo esc_html( number_format_i18n( (int) ( $row['count'] ?? 0 ) ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<div class="chatbot-admin-card chatbot-admin-events">
			<div class="chatbot-admin-card__head">
				<h2><?php esc_html_e( 'Events', 'chatbot-plugin-wp' ); ?></h2>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: number of events */
							_n( '%s event in the period', '%s events in the period', $total, 'chatbot-plugin-wp' ),
							number_format_i18n( $total )
						)
					);
					?>
				</p>
			</div>
			<div class="chatbot-admin-card__body chatbot-admin-stats-filters-wrap">
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="chatbot-admin-stats-filters">
					<input type="hidden" name="page" value="chatbot-plugin" />
					<input type="hidden" name="tab" value="stats" />
					<input type="hidden" name="days" value="<?php echo esc_attr( (string) $days ); ?>" />
					<div class="chatbot-admin-stats-filters__field">
						<label for="chatbot-stats-provider"><?php esc_html_e( 'Provider', 'chatbot-plugin-wp' ); ?></label>
						<select id="chatbot-stats-provider" name="provider">
							<option value="all"<?php selected( $filters['provider'], 'all' ); ?>><?php esc_html_e( 'All', 'chatbot-plugin-wp' ); ?></option>
							<option value="gemini"<?php selected( $filters['provider'], 'gemini' ); ?>>Gemini</option>
							<option value="deepseek"<?php selected( $filters['provider'], 'deepseek' ); ?>>DeepSeek</option>
							<option value="ollama"<?php selected( $filters['provider'], 'ollama' ); ?>>Ollama</option>
							<option value="openai_compatible"<?php selected( $filters['provider'], 'openai_compatible' ); ?>>OpenAI-compatible</option>
						</select>
					</div>
					<div class="chatbot-admin-stats-filters__field">
						<label for="chatbot-stats-status"><?php esc_html_e( 'Status', 'chatbot-plugin-wp' ); ?></label>
						<select id="chatbot-stats-status" name="status">
							<option value="all"<?php selected( $filters['status'], 'all' ); ?>><?php esc_html_e( 'All', 'chatbot-plugin-wp' ); ?></option>
							<option value="success"<?php selected( $filters['status'], 'success' ); ?>><?php esc_html_e( 'Success', 'chatbot-plugin-wp' ); ?></option>
							<option value="cached"<?php selected( $filters['status'], 'cached' ); ?>><?php esc_html_e( 'Cached', 'chatbot-plugin-wp' ); ?></option>
							<option value="error"<?php selected( $filters['status'], 'error' ); ?>><?php esc_html_e( 'Error', 'chatbot-plugin-wp' ); ?></option>
							<option value="rate_limited"<?php selected( $filters['status'], 'rate_limited' ); ?>><?php esc_html_e( 'Rate limited', 'chatbot-plugin-wp' ); ?></option>
							<option value="config_error"<?php selected( $filters['status'], 'config_error' ); ?>><?php esc_html_e( 'Configuration error', 'chatbot-plugin-wp' ); ?></option>
							<option value="invalid_request"<?php selected( $filters['status'], 'invalid_request' ); ?>><?php esc_html_e( 'Invalid request', 'chatbot-plugin-wp' ); ?></option>
						</select>
					</div>
					<div class="chatbot-admin-stats-filters__field">
						<label for="chatbot-stats-model"><?php esc_html_e( 'Model', 'chatbot-plugin-wp' ); ?></label>
						<select id="chatbot-stats-model" name="model">
							<option value="all"<?php selected( $filters['model'], 'all' ); ?>><?php esc_html_e( 'All', 'chatbot-plugin-wp' ); ?></option>
							<?php foreach ( $models as $model ) : ?>
								<option value="<?php echo esc_attr( $model ); ?>"<?php selected( $filters['model'], $model ); ?>><?php echo esc_html( $model ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="chatbot-admin-stats-filters__field">
						<label for="chatbot-stats-error"><?php esc_html_e( 'Error code', 'chatbot-plugin-wp' ); ?></label>
						<select id="chatbot-stats-error" name="error_code">
							<option value="all"<?php selected( $filters['error_code'], 'all' ); ?>><?php esc_html_e( 'All', 'chatbot-plugin-wp' ); ?></option>
							<?php foreach ( $error_codes as $code ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>"<?php selected( $filters['error_code'], $code ); ?>><?php echo esc_html( self::format_error_code_label( $code ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="chatbot-admin-stats-filters__field">
						<label for="chatbot-stats-conversation"><?php esc_html_e( 'Conversation (ID)', 'chatbot-plugin-wp' ); ?></label>
						<input type="number" min="0" id="chatbot-stats-conversation" name="conversation_id" value="<?php echo esc_attr( (string) (int) $filters['conversation_id'] ); ?>" class="small-text" />
					</div>
					<div class="chatbot-admin-stats-filters__actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'chatbot-plugin-wp' ); ?></button>
						<?php if ( $has_filters ) : ?>
							<a class="button" href="<?php echo esc_url( self::build_stats_url( array( 'days' => $days ) ) ); ?>"><?php esc_html_e( 'Clear filters', 'chatbot-plugin-wp' ); ?></a>
						<?php endif; ?>
					</div>
				</form>
			</div>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'chatbot-plugin-wp' ); ?></th>
						<th><?php esc_html_e( 'Provider', 'chatbot-plugin-wp' ); ?></th>
						<th><?php esc_html_e( 'Model', 'chatbot-plugin-wp' ); ?></th>
						<th><?php esc_html_e( 'Status', 'chatbot-plugin-wp' ); ?></th>
						<th><?php esc_html_e( 'Latency', 'chatbot-plugin-wp' ); ?></th>
						<th><?php esc_html_e( 'Error', 'chatbot-plugin-wp' ); ?></th>
						<th><?php esc_html_e( 'Conversation', 'chatbot-plugin-wp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $events ) ) : ?>
						<tr>
							<td colspan="7">
								<?php if ( $has_filters ) : ?>
									<?php esc_html_e( 'No results with these filters.', 'chatbot-plugin-wp' ); ?>
								<?php else : ?>
									<?php esc_html_e( 'No events in this period.', 'chatbot-plugin-wp' ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $events as $event ) : ?>
							<?php
							$status  = (string) ( $event['status'] ?? '' );
							$conv_id = (int) ( $event['conversation_id'] ?? 0 );
							$err     = (string) ( $event['error_code'] ?? '' );
							?>
							<tr>
								<td><?php echo esc_html( Chatbot_Chat_History::format_datetime_local( (string) ( $event['created_at'] ?? '' ) ) ); ?></td>
								<td><?php echo esc_html( (string) ( $event['provider'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $event['model'] ?? '' ) ); ?></td>
								<td>
									<span class="chatbot-admin-status <?php echo esc_attr( self::format_telemetry_status_class( $status ) ); ?>">
										<?php echo esc_html( self::format_telemetry_status_label( $status ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( number_format_i18n( (int) ( $event['latency_ms'] ?? 0 ) ) ); ?> ms</td>
								<td><?php echo '' !== $err ? esc_html( self::format_error_code_label( $err ) ) : '—'; ?></td>
								<td>
									<?php if ( $conv_id > 0 ) : ?>
										<a href="<?php echo esc_url( self::build_history_url( array( 'conversation' => $conv_id, 'days' => 0 ) ) ); ?>">
											<?php
											$public_id = (string) ( $conv_map[ $conv_id ] ?? '' );
											echo esc_html( '' !== $public_id ? $public_id : '#' . $conv_id );
											?>
										</a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<?php self::render_stats_pagination( $page, $pages, array_merge( $filters, array( 'paged' => $page ) ) ); ?>
		</div>
		<?php
	}

	private static function get_history_filters_from_request(): array {
		$days = array_key_exists( 'days', $_GET )
			? max( 0, min( 365, (int) $_GET['days'] ) )
			: 30;

		return array(
			'days'       => $days > 0 ? $days : 0,
			'search'     => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : '',
			'search_in'  => isset( $_GET['search_in'] ) ? sanitize_key( wp_unslash( (string) $_GET['search_in'] ) ) : 'all',
			'provider'   => isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( (string) $_GET['provider'] ) ) : 'all',
			'status'     => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( (string) $_GET['status'] ) ) : 'all',
			'page_path'  => isset( $_GET['page_path'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['page_path'] ) ) : 'all',
			'orderby'    => isset( $_GET['orderby'] ) && 'started_at' === $_GET['orderby'] ? 'started_at' : 'updated_at',
			'order'      => isset( $_GET['order'] ) && 'asc' === $_GET['order'] ? 'asc' : 'desc',
		);
	}

	/**
	 * @param array<string, mixed> $query_args
	 */
	private static function build_history_url( array $query_args ): string {
		$args = array_merge(
			array(
				'page' => 'chatbot-plugin',
				'tab'  => 'history',
			),
			$query_args
		);

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * @param array<string, mixed> $base_args
	 */
	private static function render_history_pagination( int $page, int $pages, array $base_args ): void {
		if ( $pages <= 1 ) {
			return;
		}

		echo '<nav class="chatbot-admin-tablenav chatbot-admin-tablenav--history" aria-label="' . esc_attr__( 'Pagination', 'chatbot-plugin-wp' ) . '">';

		if ( $page > 1 ) {
			$prev_args           = $base_args;
			$prev_args['paged']  = $page - 1;
			echo '<a class="chatbot-admin-tablenav__prev" href="' . esc_url( self::build_history_url( $prev_args ) ) . '">' . esc_html__( 'Previous', 'chatbot-plugin-wp' ) . '</a>';
		}

		echo '<span class="chatbot-admin-tablenav__status">';
		echo esc_html(
			sprintf(
				/* translators: 1: current page, 2: total pages */
				__( 'Page %1$d of %2$d', 'chatbot-plugin-wp' ),
				$page,
				$pages
			)
		);
		echo '</span>';

		$window = 5;
		$start  = max( 1, $page - $window );
		$end    = min( $pages, $page + $window );

		echo '<span class="chatbot-admin-tablenav__pages">';
		for ( $i = $start; $i <= $end; $i++ ) {
			$page_args          = $base_args;
			$page_args['paged'] = $i;
			echo '<a href="' . esc_url( self::build_history_url( $page_args ) ) . '" class="' . esc_attr( $page === $i ? 'is-active' : '' ) . '">' . esc_html( (string) $i ) . '</a>';
		}
		echo '</span>';

		if ( $page < $pages ) {
			$next_args          = $base_args;
			$next_args['paged'] = $page + 1;
			echo '<a class="chatbot-admin-tablenav__next" href="' . esc_url( self::build_history_url( $next_args ) ) . '">' . esc_html__( 'Next', 'chatbot-plugin-wp' ) . '</a>';
		}

		echo '</nav>';
	}

	private static function render_history_tab(): void {
		$expanded_id = isset( $_GET['conversation'] ) ? (int) $_GET['conversation'] : 0;
		$page        = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$per         = 12;

		$filters = self::get_history_filters_from_request();
		$days    = (int) $filters['days'];
		$search  = (string) $filters['search'];
		$provider = (string) $filters['provider'];
		$status  = (string) $filters['status'];
		$orderby = (string) $filters['orderby'];
		$order   = (string) $filters['order'];

		if ( $expanded_id > 0 ) {
			$target_conv = Chatbot_Chat_History::get_conversation( $expanded_id );
			if ( $target_conv ) {
				$count_args = $filters;
				unset( $count_args['offset'], $count_args['per_page'] );
				$target_page = Chatbot_Chat_History::find_conversation_page( $expanded_id, $count_args, $per );
				if ( $target_page !== $page ) {
					wp_safe_redirect(
						self::build_history_url(
							array_merge(
								$filters,
								array(
									'paged'        => $target_page,
									'conversation' => $expanded_id,
								)
							)
						)
					);
					exit;
				}
			}
		}

		$query_args = array_merge(
			$filters,
			array(
				'per_page' => $per,
				'offset'   => ( $page - 1 ) * $per,
			)
		);

		$items  = Chatbot_Chat_History::list_conversations( $query_args );
		$total  = Chatbot_Chat_History::count_conversations( $filters );
		$pages  = (int) ceil( $total / $per );
		$stats  = Chatbot_Chat_History::get_summary_stats( $filters );
		$paths  = Chatbot_Chat_History::get_distinct_page_paths( $filters );

		if ( $expanded_id > 0 ) {
			$ids_on_page = array_map(
				static function ( $item ) {
					return (int) ( $item['id'] ?? 0 );
				},
				$items
			);
			if ( ! in_array( $expanded_id, $ids_on_page, true ) ) {
				$orphan = Chatbot_Chat_History::get_conversation( $expanded_id );
				if ( $orphan ) {
					array_unshift( $items, $orphan );
				}
			}
		}

		$previews = Chatbot_Chat_History::get_first_user_previews(
			array_map(
				static function ( $item ) {
					return (int) ( $item['id'] ?? 0 );
				},
				$items
			)
		);

		$settings    = Chatbot_Plugin::get_settings();
		$retention   = (int) ( $settings['history_retention_days'] ?? 0 );
		$has_filters = '' !== $search || 'all' !== $provider || 'all' !== $status || 'all' !== (string) $filters['page_path'] || 'all' !== (string) $filters['search_in'];

		$periods = array(
			0   => __( 'All', 'chatbot-plugin-wp' ),
			7   => __( '7 days', 'chatbot-plugin-wp' ),
			30  => __( '30 days', 'chatbot-plugin-wp' ),
			90  => __( '90 days', 'chatbot-plugin-wp' ),
		);

		$export_url = wp_nonce_url(
			add_query_arg(
				array_merge(
					array( 'action' => 'chatbot_export_history_csv' ),
					$filters
				),
				admin_url( 'admin-post.php' )
			),
			'chatbot_export_history_csv'
		);

		$purge_url = '';
		if ( $retention > 0 ) {
			$purge_url = wp_nonce_url(
				add_query_arg( 'action', 'chatbot_purge_history', admin_url( 'admin-post.php' ) ),
				'chatbot_purge_history'
			);
		}

		$count_label = sprintf(
			/* translators: %s: number of conversations */
			_n( '%s conversation', '%s conversations', $total, 'chatbot-plugin-wp' ),
			number_format_i18n( $total )
		);
		$active_period = $periods[ $days ] ?? ( $days > 0 ? sprintf(
			/* translators: %d: number of days */
			__( '%d days', 'chatbot-plugin-wp' ),
			$days
		) : __( 'All', 'chatbot-plugin-wp' ) );

		if ( isset( $_GET['chatbot_purged'] ) ) {
			$purged_conv = isset( $_GET['purged_conversations'] ) ? (int) $_GET['purged_conversations'] : 0;
			$purged_msg  = isset( $_GET['purged_messages'] ) ? (int) $_GET['purged_messages'] : 0;
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html(
				sprintf(
					/* translators: 1: conversations deleted, 2: messages deleted */
					__( 'Purge complete: %1$d conversations and %2$d messages deleted.', 'chatbot-plugin-wp' ),
					$purged_conv,
					$purged_msg
				)
			);
			echo '</p></div>';
		}
		?>
		<div class="chatbot-admin-kpi-grid chatbot-admin-kpi-grid--history">
			<div class="chatbot-admin-kpi">
				<span class="chatbot-admin-kpi__label"><?php esc_html_e( 'Conversations', 'chatbot-plugin-wp' ); ?></span>
				<span class="chatbot-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $stats['total'] ?? 0 ) ) ); ?></span>
			</div>
			<div class="chatbot-admin-kpi chatbot-admin-kpi--error">
				<span class="chatbot-admin-kpi__label"><?php esc_html_e( 'With error', 'chatbot-plugin-wp' ); ?></span>
				<span class="chatbot-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $stats['errors'] ?? 0 ) ) ); ?></span>
			</div>
			<div class="chatbot-admin-kpi">
				<span class="chatbot-admin-kpi__label"><?php esc_html_e( 'Total messages', 'chatbot-plugin-wp' ); ?></span>
				<span class="chatbot-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $stats['messages'] ?? 0 ) ) ); ?></span>
			</div>
			<div class="chatbot-admin-kpi">
				<span class="chatbot-admin-kpi__label"><?php esc_html_e( 'Avg. msgs/conv.', 'chatbot-plugin-wp' ); ?></span>
				<span class="chatbot-admin-kpi__value"><?php echo esc_html( number_format_i18n( (float) ( $stats['avg_messages'] ?? 0 ), 1 ) ); ?></span>
			</div>
		</div>

		<div class="chatbot-admin-card chatbot-admin-history-panel">
			<div class="chatbot-admin-card__head chatbot-admin-history-panel__head">
				<div class="chatbot-admin-history-toolbar">
					<div class="chatbot-admin-history-toolbar__intro">
						<h2><?php esc_html_e( 'Conversations', 'chatbot-plugin-wp' ); ?></h2>
						<p>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: conversation count label, 2: active period */
									__( '%1$s · %2$s', 'chatbot-plugin-wp' ),
									$count_label,
									$active_period
								)
							);
							?>
						</p>
					</div>
					<div class="chatbot-admin-history-toolbar__actions">
						<a class="button chatbot-admin-export" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'chatbot-plugin-wp' ); ?></a>
						<?php if ( '' !== $purge_url ) : ?>
							<a
								class="button button-secondary chatbot-admin-history-purge"
								href="<?php echo esc_url( $purge_url ); ?>"
								data-confirm="<?php esc_attr_e( 'Purge conversations older than the configured retention period?', 'chatbot-plugin-wp' ); ?>"
							><?php esc_html_e( 'Purge old', 'chatbot-plugin-wp' ); ?></a>
						<?php endif; ?>
					</div>
					<div class="chatbot-admin-history-toolbar__period">
						<div class="chatbot-admin-pills chatbot-admin-pills--history" role="group" aria-label="<?php esc_attr_e( 'Period', 'chatbot-plugin-wp' ); ?>">
							<?php foreach ( $periods as $p => $label ) : ?>
								<?php
								$url = self::build_history_url(
									array_merge(
										$filters,
										array(
											'days'  => $p,
											'paged' => 1,
										)
									)
								);
								?>
								<a href="<?php echo esc_url( $url ); ?>" class="<?php echo (int) $days === (int) $p ? 'is-active' : ''; ?>">
									<?php echo esc_html( $label ); ?>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
			<div class="chatbot-admin-card__body chatbot-admin-history-panel__filters">
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="chatbot-admin-history-filters">
					<input type="hidden" name="page" value="chatbot-plugin" />
					<input type="hidden" name="tab" value="history" />
					<input type="hidden" name="days" value="<?php echo esc_attr( (string) $days ); ?>" />
					<div class="chatbot-admin-history-filters__field chatbot-admin-history-filters__field--search">
						<label for="chatbot-history-search"><?php esc_html_e( 'Search', 'chatbot-plugin-wp' ); ?></label>
						<input type="search" id="chatbot-history-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'ID, title, path, session, or message…', 'chatbot-plugin-wp' ); ?>" />
					</div>
					<div class="chatbot-admin-history-filters__field">
						<label for="chatbot-history-search-in"><?php esc_html_e( 'Search in', 'chatbot-plugin-wp' ); ?></label>
						<select id="chatbot-history-search-in" name="search_in">
							<option value="all"<?php selected( $filters['search_in'], 'all' ); ?>><?php esc_html_e( 'Metadata and messages', 'chatbot-plugin-wp' ); ?></option>
							<option value="meta"<?php selected( $filters['search_in'], 'meta' ); ?>><?php esc_html_e( 'Metadata only', 'chatbot-plugin-wp' ); ?></option>
							<option value="messages"<?php selected( $filters['search_in'], 'messages' ); ?>><?php esc_html_e( 'Messages only', 'chatbot-plugin-wp' ); ?></option>
						</select>
					</div>
					<div class="chatbot-admin-history-filters__field">
						<label for="chatbot-history-page-path"><?php esc_html_e( 'Path', 'chatbot-plugin-wp' ); ?></label>
						<select id="chatbot-history-page-path" name="page_path">
							<option value="all"<?php selected( $filters['page_path'], 'all' ); ?>><?php esc_html_e( 'All', 'chatbot-plugin-wp' ); ?></option>
							<?php foreach ( $paths as $path ) : ?>
								<option value="<?php echo esc_attr( $path ); ?>"<?php selected( $filters['page_path'], $path ); ?>><?php echo esc_html( $path ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="chatbot-admin-history-filters__field">
						<label for="chatbot-history-provider"><?php esc_html_e( 'Provider', 'chatbot-plugin-wp' ); ?></label>
						<select id="chatbot-history-provider" name="provider">
							<option value="all"<?php selected( $provider, 'all' ); ?>><?php esc_html_e( 'All', 'chatbot-plugin-wp' ); ?></option>
							<option value="gemini"<?php selected( $provider, 'gemini' ); ?>>Gemini</option>
							<option value="deepseek"<?php selected( $provider, 'deepseek' ); ?>>DeepSeek</option>
							<option value="ollama"<?php selected( $provider, 'ollama' ); ?>>Ollama</option>
							<option value="openai_compatible"<?php selected( $provider, 'openai_compatible' ); ?>>OpenAI-compatible</option>
						</select>
					</div>
					<div class="chatbot-admin-history-filters__field">
						<label for="chatbot-history-status"><?php esc_html_e( 'Status', 'chatbot-plugin-wp' ); ?></label>
						<select id="chatbot-history-status" name="status">
							<option value="all"<?php selected( $status, 'all' ); ?>><?php esc_html_e( 'All', 'chatbot-plugin-wp' ); ?></option>
							<option value="active"<?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'chatbot-plugin-wp' ); ?></option>
							<option value="success"<?php selected( $status, 'success' ); ?>><?php esc_html_e( 'Success', 'chatbot-plugin-wp' ); ?></option>
							<option value="error"<?php selected( $status, 'error' ); ?>><?php esc_html_e( 'Error', 'chatbot-plugin-wp' ); ?></option>
							<option value="cached"<?php selected( $status, 'cached' ); ?>><?php esc_html_e( 'Cached', 'chatbot-plugin-wp' ); ?></option>
						</select>
					</div>
					<div class="chatbot-admin-history-filters__field">
						<label for="chatbot-history-orderby"><?php esc_html_e( 'Sort by', 'chatbot-plugin-wp' ); ?></label>
						<select id="chatbot-history-orderby" name="orderby">
							<option value="updated_at"<?php selected( $orderby, 'updated_at' ); ?>><?php esc_html_e( 'Last activity', 'chatbot-plugin-wp' ); ?></option>
							<option value="started_at"<?php selected( $orderby, 'started_at' ); ?>><?php esc_html_e( 'Start', 'chatbot-plugin-wp' ); ?></option>
						</select>
					</div>
					<div class="chatbot-admin-history-filters__field">
						<label for="chatbot-history-order"><?php esc_html_e( 'Direction', 'chatbot-plugin-wp' ); ?></label>
						<select id="chatbot-history-order" name="order">
							<option value="desc"<?php selected( $order, 'desc' ); ?>><?php esc_html_e( 'Newest first', 'chatbot-plugin-wp' ); ?></option>
							<option value="asc"<?php selected( $order, 'asc' ); ?>><?php esc_html_e( 'Oldest first', 'chatbot-plugin-wp' ); ?></option>
						</select>
					</div>
					<div class="chatbot-admin-history-filters__actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'chatbot-plugin-wp' ); ?></button>
						<?php if ( $has_filters ) : ?>
							<a class="button" href="<?php echo esc_url( self::build_history_url( array( 'days' => $days ) ) ); ?>"><?php esc_html_e( 'Clear filters', 'chatbot-plugin-wp' ); ?></a>
						<?php endif; ?>
					</div>
				</form>
			</div>
		</div>

		<div class="chatbot-admin-card chatbot-admin-history-list">
			<div class="chatbot-admin-card__head chatbot-admin-history-list__head">
				<h2><?php echo esc_html( $count_label ); ?></h2>
				<?php if ( $pages > 1 ) : ?>
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: current page, 2: total pages */
								__( 'Page %1$d of %2$d', 'chatbot-plugin-wp' ),
								$page,
								$pages
							)
						);
						?>
					</p>
				<?php endif; ?>
			</div>

			<?php if ( empty( $items ) ) : ?>
				<div class="chatbot-admin-card__body chatbot-admin-history-empty">
					<span class="chatbot-admin-history-empty__icon dashicons dashicons-format-chat" aria-hidden="true"></span>
					<?php if ( $has_filters ) : ?>
						<p><?php esc_html_e( 'No results with these filters.', 'chatbot-plugin-wp' ); ?></p>
						<p><a class="button" href="<?php echo esc_url( self::build_history_url( array( 'days' => $days ) ) ); ?>"><?php esc_html_e( 'Clear filters', 'chatbot-plugin-wp' ); ?></a></p>
					<?php else : ?>
						<p><?php esc_html_e( 'No conversations in this period.', 'chatbot-plugin-wp' ); ?></p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="chatbot-admin-history-table" role="table" aria-label="<?php esc_attr_e( 'Conversation list', 'chatbot-plugin-wp' ); ?>">
					<div class="chatbot-admin-history-table__head" role="row">
						<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--icon" role="columnheader" aria-hidden="true"></span>
						<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--title" role="columnheader"><?php esc_html_e( 'Conversation', 'chatbot-plugin-wp' ); ?></span>
						<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--status" role="columnheader"><?php esc_html_e( 'Status', 'chatbot-plugin-wp' ); ?></span>
						<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--provider" role="columnheader"><?php esc_html_e( 'Provider', 'chatbot-plugin-wp' ); ?></span>
						<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--date" role="columnheader"><?php esc_html_e( 'Updated', 'chatbot-plugin-wp' ); ?></span>
						<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--msgs" role="columnheader"><?php esc_html_e( 'Msgs', 'chatbot-plugin-wp' ); ?></span>
						<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--action" role="columnheader" aria-hidden="true"></span>
					</div>
					<div class="chatbot-admin-history-stack" id="chatbot-history-list" role="rowgroup">
					<?php foreach ( $items as $item ) : ?>
						<?php
						$item_id = (int) ( $item['id'] ?? 0 );
						self::render_history_card(
							$item,
							$expanded_id === $item_id,
							(string) ( $previews[ $item_id ] ?? '' )
						);
						?>
					<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php self::render_history_pagination( $page, $pages, array_merge( $filters, $expanded_id > 0 ? array( 'conversation' => $expanded_id ) : array() ) ); ?>
		</div>
		<?php
	}

	public static function ajax_history_detail(): void {
		check_ajax_referer( 'chatbot_history_detail', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'chatbot-plugin-wp' ) ), 403 );
		}

		$conversation_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( $conversation_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid conversation.', 'chatbot-plugin-wp' ) ), 400 );
		}

		$conv = Chatbot_Chat_History::get_conversation( $conversation_id );
		if ( ! $conv ) {
			wp_send_json_error( array( 'message' => __( 'Conversation not found.', 'chatbot-plugin-wp' ) ), 404 );
		}

		$messages = Chatbot_Chat_History::get_messages( $conversation_id );

		ob_start();
		self::render_history_card_body( $conv, $messages );
		$html = (string) ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
 * @param array<string, mixed> $item
 */
private static function render_history_card( array $item, bool $expanded = false, string $preview = '' ): void {
	$id         = (int) ( $item['id'] ?? 0 );
	$public_id  = (string) ( $item['public_id'] ?? '' );
	$title      = (string) ( $item['title'] ?? '' );
	$status     = (string) ( $item['status'] ?? '' );
	$provider   = (string) ( $item['provider'] ?? '' );
	$model      = (string) ( $item['model'] ?? '' );
	$msg_count  = (int) ( $item['message_count'] ?? 0 );
	$page_path  = (string) ( $item['page_path'] ?? '' );
	$updated    = Chatbot_Chat_History::format_datetime_local( (string) ( $item['updated_at'] ?? '' ) );
	$relative   = Chatbot_Chat_History::format_relative_time( (string) ( $item['updated_at'] ?? '' ) );
	$duration   = Chatbot_Chat_History::format_duration(
		(string) ( $item['started_at'] ?? '' ),
		(string) ( $item['updated_at'] ?? '' )
	);
	$is_ok      = in_array( $status, array( 'success', 'active', 'cached' ), true );

	if ( '' === $title ) {
		$title = __( '(Untitled)', 'chatbot-plugin-wp' );
	}

	$provider_label = self::format_history_provider_label( $provider, $model );
	$provider_name  = self::format_history_provider_name( $provider );
	$card_id        = 'chatbot-history-card-' . $id;
	$panel_id       = 'chatbot-history-panel-' . $id;
	$loaded         = $expanded;
	$messages       = array();
	$status_class   = 'chatbot-admin-history-card__status--err';
	$avatar_label   = self::format_history_provider_avatar( $provider );

	if ( 'cached' === $status ) {
		$status_class = 'chatbot-admin-history-card__status--cached';
	} elseif ( $is_ok ) {
		$status_class = 'chatbot-admin-history-card__status--ok';
	}

	if ( $expanded ) {
		$messages = Chatbot_Chat_History::get_messages( $id );
	}
	?>
	<article
		class="chatbot-admin-history-card chatbot-admin-history-card--<?php echo esc_attr( $status ); ?><?php echo $expanded ? ' is-open' : ''; ?>"
		id="<?php echo esc_attr( $card_id ); ?>"
		data-conversation-id="<?php echo esc_attr( (string) $id ); ?>"
		data-provider="<?php echo esc_attr( $provider ); ?>"
		data-loaded="<?php echo $loaded ? '1' : '0'; ?>"
	>
		<button
			type="button"
			class="chatbot-admin-history-card__toggle"
			aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>"
			aria-controls="<?php echo esc_attr( $panel_id ); ?>"
		>
			<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--icon">
				<span class="chatbot-admin-history-card__avatar" aria-hidden="true">
					<span class="chatbot-admin-history-card__avatar-label"><?php echo esc_html( $avatar_label ); ?></span>
				</span>
			</span>

			<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--title">
				<span class="chatbot-admin-history-card__title"<?php echo '' !== $preview ? ' title="' . esc_attr( $preview ) . '"' : ''; ?>><?php echo esc_html( $title ); ?></span>
				<span class="chatbot-admin-history-card__sub">
					<code class="chatbot-admin-history-card__ref"><?php echo esc_html( $public_id ); ?></code>
					<?php if ( '' !== $page_path ) : ?>
						<span class="chatbot-admin-history-tag chatbot-admin-history-tag--path" title="<?php echo esc_attr( $page_path ); ?>">
							<?php echo esc_html( $page_path ); ?>
						</span>
					<?php endif; ?>
				</span>
			</span>

			<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--status">
				<span class="chatbot-admin-history-card__status <?php echo esc_attr( $status_class ); ?>">
					<span class="chatbot-admin-history-card__status-dot" aria-hidden="true"></span>
					<?php echo esc_html( self::format_history_status_label( $status ) ); ?>
				</span>
			</span>

			<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--provider" data-label="<?php esc_attr_e( 'Provider', 'chatbot-plugin-wp' ); ?>">
				<span class="chatbot-admin-history-card__provider-stack">
					<?php if ( '' !== $provider_name ) : ?>
						<span class="chatbot-admin-history-card__provider-name"><?php echo esc_html( $provider_name ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $model ) : ?>
						<span class="chatbot-admin-history-card__model" title="<?php echo esc_attr( $model ); ?>"><?php echo esc_html( $model ); ?></span>
					<?php elseif ( '' !== $provider_label ) : ?>
						<span class="chatbot-admin-history-card__model"><?php echo esc_html( $provider_label ); ?></span>
					<?php endif; ?>
				</span>
			</span>

			<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--date" data-label="<?php esc_attr_e( 'Updated', 'chatbot-plugin-wp' ); ?>">
				<time datetime="<?php echo esc_attr( (string) ( $item['updated_at'] ?? '' ) ); ?>"><?php echo esc_html( $updated ); ?></time>
				<?php if ( '' !== $relative ) : ?>
					<span class="chatbot-admin-history-card__relative"><?php echo esc_html( sprintf( __( '%s ago', 'chatbot-plugin-wp' ), $relative ) ); ?></span>
				<?php endif; ?>
				<span class="chatbot-admin-history-card__duration" title="<?php esc_attr_e( 'Conversation duration', 'chatbot-plugin-wp' ); ?>"><?php echo esc_html( $duration ); ?></span>
			</span>

			<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--msgs" data-label="<?php esc_attr_e( 'Messages', 'chatbot-plugin-wp' ); ?>">
				<span class="chatbot-admin-history-card__msgs-count"><?php echo esc_html( number_format_i18n( $msg_count ) ); ?></span>
			</span>

			<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--action">
				<span class="chatbot-admin-history-card__chevron" aria-hidden="true"></span>
			</span>
		</button>

		<div
			class="chatbot-admin-history-card__panel"
			id="<?php echo esc_attr( $panel_id ); ?>"
			role="region"
			aria-label="<?php echo esc_attr( sprintf( __( 'History of %s', 'chatbot-plugin-wp' ), $public_id ) ); ?>"
			<?php echo $expanded ? '' : 'hidden'; ?>
		>
			<?php if ( $expanded ) : ?>
				<?php self::render_history_card_body( $item, $messages ); ?>
			<?php endif; ?>
		</div>
	</article>
	<?php
}

/**
 * @param array<string, mixed> $conv
 * @param array<int, array<string, mixed>> $messages
 */
private static function render_history_card_body( array $conv, array $messages ): void {
	$conv_id   = (int) ( $conv['id'] ?? 0 );
	$started   = Chatbot_Chat_History::format_datetime_local( (string) ( $conv['started_at'] ?? '' ) );
	$updated   = Chatbot_Chat_History::format_datetime_local( (string) ( $conv['updated_at'] ?? '' ) );
	$duration  = Chatbot_Chat_History::format_duration(
		(string) ( $conv['started_at'] ?? '' ),
		(string) ( $conv['updated_at'] ?? '' )
	);
	$status    = (string) ( $conv['status'] ?? '' );
	$provider  = (string) ( $conv['provider'] ?? '' );
	$model     = (string) ( $conv['model'] ?? '' );
	$page_url  = (string) ( $conv['page_url'] ?? '' );
	$page_path = (string) ( $conv['page_path'] ?? '' );
	$session   = (string) ( $conv['session_hash'] ?? '' );
	$public_id = (string) ( $conv['public_id'] ?? '' );
	$msg_count = (int) ( $conv['message_count'] ?? count( $messages ) );
	$link_url  = self::build_history_url(
		array(
			'conversation' => $conv_id,
			'days'         => 0,
		)
	);
	$telemetry_events = Chatbot_Telemetry::get_events_by_conversation( $conv_id, 20 );
	?>
	<div class="chatbot-admin-history-card__body">
		<div class="chatbot-admin-history-detail__actions">
			<?php if ( '' !== $public_id ) : ?>
				<button type="button" class="button button-small chatbot-admin-history-copy" data-copy="<?php echo esc_attr( $public_id ); ?>">
					<?php esc_html_e( 'Copy public ID', 'chatbot-plugin-wp' ); ?>
				</button>
			<?php endif; ?>
			<button type="button" class="button button-small chatbot-admin-history-copy" data-copy="<?php echo esc_attr( $link_url ); ?>">
				<?php esc_html_e( 'Copy link', 'chatbot-plugin-wp' ); ?>
			</button>
			<button type="button" class="button button-small button-link-delete chatbot-admin-history-delete" data-id="<?php echo esc_attr( (string) $conv_id ); ?>">
				<?php esc_html_e( 'Delete', 'chatbot-plugin-wp' ); ?>
			</button>
		</div>

		<dl class="chatbot-admin-history-detail__grid">
			<div>
				<dt><?php esc_html_e( 'Internal ID', 'chatbot-plugin-wp' ); ?></dt>
				<dd>#<?php echo esc_html( (string) (int) ( $conv['id'] ?? 0 ) ); ?></dd>
			</div>

			<?php if ( '' !== $public_id ) : ?>
				<div>
					<dt><?php esc_html_e( 'Public ID', 'chatbot-plugin-wp' ); ?></dt>
					<dd><code><?php echo esc_html( $public_id ); ?></code></dd>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $status ) : ?>
				<div>
					<dt><?php esc_html_e( 'Status', 'chatbot-plugin-wp' ); ?></dt>
					<dd><?php echo esc_html( self::format_history_status_label( $status ) ); ?></dd>
				</div>
			<?php endif; ?>

			<div>
				<dt><?php esc_html_e( 'Messages', 'chatbot-plugin-wp' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( $msg_count ) ); ?></dd>
			</div>

			<div>
				<dt><?php esc_html_e( 'Start', 'chatbot-plugin-wp' ); ?></dt>
				<dd><?php echo esc_html( $started ); ?></dd>
			</div>

			<div>
				<dt><?php esc_html_e( 'Last activity', 'chatbot-plugin-wp' ); ?></dt>
				<dd><?php echo esc_html( $updated ); ?></dd>
			</div>

			<div>
				<dt><?php esc_html_e( 'Duration', 'chatbot-plugin-wp' ); ?></dt>
				<dd><?php echo esc_html( $duration ); ?></dd>
			</div>

			<?php if ( '' !== $provider || '' !== $model ) : ?>
				<div>
					<dt><?php esc_html_e( 'Provider / model', 'chatbot-plugin-wp' ); ?></dt>
					<dd><?php echo esc_html( self::format_history_provider_label( $provider, $model ) ); ?></dd>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $session ) : ?>
				<div>
					<dt><?php esc_html_e( 'Session', 'chatbot-plugin-wp' ); ?></dt>
					<dd><code><?php echo esc_html( $session ); ?></code></dd>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $page_path ) : ?>
				<div class="chatbot-admin-history-detail__grid-wide">
					<dt><?php esc_html_e( 'Path', 'chatbot-plugin-wp' ); ?></dt>
					<dd><?php echo esc_html( $page_path ); ?></dd>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $page_url ) : ?>
				<div class="chatbot-admin-history-detail__grid-wide">
					<dt><?php esc_html_e( 'URL', 'chatbot-plugin-wp' ); ?></dt>
					<dd>
						<a href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( $page_url ); ?>
						</a>
					</dd>
				</div>
			<?php endif; ?>
		</dl>

		<?php if ( ! empty( $telemetry_events ) ) : ?>
			<div class="chatbot-admin-history-telemetry">
				<h3 class="chatbot-admin-history-messages__title"><?php esc_html_e( 'Technical events', 'chatbot-plugin-wp' ); ?></h3>
				<ul class="chatbot-admin-history-telemetry__list">
					<?php foreach ( $telemetry_events as $event ) : ?>
						<li>
							<span><?php echo esc_html( Chatbot_Chat_History::format_datetime_local( (string) ( $event['created_at'] ?? '' ) ) ); ?></span>
							<span class="chatbot-admin-status <?php echo in_array( (string) ( $event['status'] ?? '' ), array( 'success', 'cached' ), true ) ? 'chatbot-admin-status--ok' : 'chatbot-admin-status--err'; ?>">
								<?php echo esc_html( (string) ( $event['status'] ?? '' ) ); ?>
							</span>
							<span><?php echo esc_html( number_format_i18n( (int) ( $event['latency_ms'] ?? 0 ) ) ); ?> ms</span>
							<?php if ( ! empty( $event['error_code'] ) ) : ?>
								<code><?php echo esc_html( (string) $event['error_code'] ); ?></code>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="description">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=chatbot-plugin&tab=stats' ) ); ?>"><?php esc_html_e( 'View all statistics', 'chatbot-plugin-wp' ); ?></a>
				</p>
			</div>
		<?php endif; ?>

		<div class="chatbot-admin-history-messages">
			<h3 class="chatbot-admin-history-messages__title"><?php esc_html_e( 'Messages', 'chatbot-plugin-wp' ); ?></h3>
			<?php self::render_history_messages_list( $messages ); ?>
		</div>
	</div>
	<?php
}

/**
 * @param array<int, array<string, mixed>> $messages
 */
private static function render_history_messages_list( array $messages ): void {
	if ( empty( $messages ) ) {
		echo '<p class="chatbot-admin-history-messages__empty">' . esc_html__( 'No saved messages.', 'chatbot-plugin-wp' ) . '</p>';
		return;
	}
	?>
	<div class="chatbot-admin-history-messages__list">
		<?php foreach ( $messages as $msg ) : ?>
			<?php
			$role         = (string) ( $msg['role'] ?? 'user' );
			$when         = Chatbot_Chat_History::format_datetime_local( (string) ( $msg['created_at'] ?? '' ) );
			$message_text = (string) ( $msg['content'] ?? '' );
			$msg_status   = (string) ( $msg['status'] ?? '' );
			$latency_ms   = (int) ( $msg['latency_ms'] ?? 0 );
			$is_assistant = 'assistant' === $role;
			$show_error   = $is_assistant && 'error' === $msg_status;

			$status_badge_class = 'chatbot-admin-status--err';
			if ( 'cached' === $msg_status ) {
				$status_badge_class = 'chatbot-admin-status--cached';
			} elseif ( in_array( $msg_status, array( 'success', 'active' ), true ) ) {
				$status_badge_class = 'chatbot-admin-status--ok';
			}
			?>
			<div class="chatbot-admin-history-msg chatbot-admin-history-msg--<?php echo esc_attr( $role ); ?>">
				<?php if ( $is_assistant ) : ?>
					<span class="chatbot-admin-history-msg__avatar" aria-hidden="true">AI</span>
				<?php endif; ?>
				<div class="chatbot-admin-history-msg__content">
				<div class="chatbot-admin-history-msg__head">
					<span class="chatbot-admin-history-msg__role">
						<?php echo esc_html( $is_assistant ? __( 'Assistant', 'chatbot-plugin-wp' ) : __( 'User', 'chatbot-plugin-wp' ) ); ?>
					</span>

					<time datetime="<?php echo esc_attr( (string) ( $msg['created_at'] ?? '' ) ); ?>">
						<?php echo esc_html( $when ); ?>
					</time>

					<?php if ( $is_assistant && $latency_ms > 0 ) : ?>
						<span class="chatbot-admin-history-msg__latency"><?php echo esc_html( number_format_i18n( $latency_ms ) ); ?> ms</span>
					<?php endif; ?>

					<?php if ( $show_error ) : ?>
						<span class="chatbot-admin-status <?php echo esc_attr( $status_badge_class ); ?>">
							<?php echo esc_html( self::format_history_status_label( $msg_status ) ); ?>
						</span>
					<?php endif; ?>
				</div>

				<div class="chatbot-admin-history-msg__body"><?php echo nl2br( esc_html( $message_text ) ); ?></div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

private static function format_history_status_label( string $status ): string {
	$labels = array(
		'active'  => __( 'Active', 'chatbot-plugin-wp' ),
		'success' => __( 'Success', 'chatbot-plugin-wp' ),
		'error'   => __( 'Error', 'chatbot-plugin-wp' ),
		'cached'  => __( 'Cached', 'chatbot-plugin-wp' ),
	);

	return $labels[ $status ] ?? $status;
}

private static function format_history_provider_label( string $provider, string $model = '' ): string {
	$labels = array(
		'gemini'            => 'Gemini',
		'deepseek'          => 'DeepSeek',
		'ollama'            => 'Ollama',
		'openai_compatible' => 'OpenAI-compatible',
	);

	$label = $labels[ $provider ] ?? $provider;
	if ( '' === $label ) {
		return '';
	}

	return '' !== $model ? $label . ' · ' . $model : $label;
}

private static function format_history_provider_name( string $provider ): string {
	$labels = array(
		'gemini'            => 'Gemini',
		'deepseek'          => 'DeepSeek',
		'ollama'            => 'Ollama',
		'openai_compatible' => 'OpenAI-compatible',
	);

	return $labels[ $provider ] ?? $provider;
}

private static function format_history_provider_avatar( string $provider ): string {
	$labels = array(
		'gemini'            => 'G',
		'deepseek'          => 'DS',
		'ollama'            => 'O',
		'openai_compatible' => 'AI',
	);

	if ( isset( $labels[ $provider ] ) ) {
		return $labels[ $provider ];
	}

	$provider = trim( $provider );
	if ( '' === $provider ) {
		return '?';
	}

	if ( function_exists( 'mb_substr' ) ) {
		return mb_strtoupper( mb_substr( $provider, 0, 1, 'UTF-8' ), 'UTF-8' );
	}

	return strtoupper( substr( $provider, 0, 1 ) );
}
}
