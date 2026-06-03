<?php
/**
 * Admin settings panel.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Multch_Admin_Settings {

	const OPTION_KEY = 'multch_plugin_settings';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_ai_client_notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_multch_export_csv', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_multch_export_history_csv', array( __CLASS__, 'export_history_csv' ) );
		add_action( 'admin_post_multch_purge_history', array( __CLASS__, 'purge_history' ) );
		add_action( 'admin_post_multch_purge_telemetry', array( __CLASS__, 'purge_telemetry' ) );
		add_action( 'wp_ajax_multch_history_detail', array( __CLASS__, 'ajax_history_detail' ) );
		add_action( 'wp_ajax_multch_history_export_json', array( __CLASS__, 'ajax_history_export_json' ) );
		add_action( 'wp_ajax_multch_delete_conversation', array( __CLASS__, 'ajax_delete_conversation' ) );
		add_filter( 'wp_redirect', array( __CLASS__, 'preserve_tab_on_settings_redirect' ), 10, 2 );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'widget_enabled'                 => false,
			'stats_history_enabled'          => false,
			'welcome_message'                => "Hello. I'm an AI agent. I may make mistakes; please verify important information before making decisions.\n\nHow can I help you?",
			'system_prompt'                  => 'You are a helpful website assistant. Respond clearly, briefly, and kindly. If you don\'t know something, say so honestly.',
			'streaming_enabled'              => true,
			'allowed_origins'                => '',
			'cache_ttl_seconds'              => 1800,
			'telemetry_file_log'           => false,
			'rate_limit_per_minute'          => 10,
			'rate_limit_per_day'             => 30,
			'rate_limit_model_per_minute'    => 20,
			'rate_limit_model_per_day'       => 200,
			'rate_limit_soft_threshold'      => 0.8,
			'ip_suspend_after_violations'    => 3,
			'ip_suspend_seconds'             => 900,
			'internal_chat_base_url'         => '',
			'provider'                       => 'wordpress_ai',
			'model'                          => 'gemini-2.5-flash',
			'model_candidates'               => 'gemini-2.5-flash-lite,gpt-4o-mini,claude-sonnet-4-6',
			'allow_google_any_model'         => false,
			'ollama_base_url'                => 'http://127.0.0.1:11434',
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
			'style_show_welcome_label' => true,
			'style_show_model_label'   => true,
			'style_custom_css'      => '',
			'widget_title'          => 'AI Agent',
			'widget_subtitle'       => 'System online',
			'history_retention_days' => 0,
			'telemetry_retention_days' => 0,
		);
	}

	public static function register_menu(): void {
		add_menu_page(
			__( 'MultiAI ChatBot', 'multiai-chatbot' ),
			__( 'MultiAI ChatBot', 'multiai-chatbot' ),
			'manage_options',
			'multch-plugin',
			array( __CLASS__, 'render_page' ),
			'dashicons-format-chat',
			58
		);
	}

	public static function register_settings(): void {
		register_setting(
			'multch_plugin_group',
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by options.php before this sanitize callback runs.
		$tab      = isset( $_POST['multch_admin_tab'] ) ? sanitize_key( wp_unslash( (string) $_POST['multch_admin_tab'] ) ) : '';

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

		Multch_Plugin::clear_settings_cache();

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
			Multch_Plugin::clear_settings_cache();
		}

		self::ensure_option_autoload_off();
	}

	/**
	 * Evita cargar el array de settings en cada request vía autoload de alloptions.
	 */
	public static function ensure_option_autoload_off(): void {
		if ( '1' === get_option( 'multch_settings_autoload_off', '' ) ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time autoload fix for existing installs.
		$autoload = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				self::OPTION_KEY
			)
		);

		if ( in_array( (string) $autoload, array( 'yes', 'on', 'auto' ), true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
			$wpdb->options,
			array( 'autoload' => 'off' ),
			array( 'option_name' => self::OPTION_KEY ),
				array( '%s' ),
				array( '%s' )
			);

			wp_cache_delete( 'alloptions', 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			wp_cache_delete( self::OPTION_KEY, 'options' );
		}

		update_option( 'multch_settings_autoload_off', '1', true );
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
			'widget_title'    => __( 'AI Agent', 'multiai-chatbot' ),
			'widget_subtitle' => __( 'System online', 'multiai-chatbot' ),
			'welcome_message' => __(
				"Hello. I'm an AI agent. I may make mistakes; please verify important information before making decisions.\n\nHow can I help you?",
				'multiai-chatbot'
			),
			'system_prompt'   => __(
				'You are a helpful website assistant. Respond clearly, briefly, and kindly. If you don\'t know something, say so honestly.',
				'multiai-chatbot'
			),
		);
	}

	/**
	 * Claves de General cuyo valor canónico en BD está en inglés y puede mostrarse traducido.
	 *
	 * @return list<string>
	 */
	public static function general_i18n_setting_keys(): array {
		return array(
			'widget_title',
			'widget_subtitle',
			'welcome_message',
			'system_prompt',
		);
	}

	/**
	 * Valores por defecto en inglés (canónicos en BD).
	 *
	 * @return array<string, string>
	 */
	public static function canonical_general_defaults(): array {
		$defaults = self::default_settings();
		$out      = array();

		foreach ( self::general_i18n_setting_keys() as $key ) {
			$out[ $key ] = (string) ( $defaults[ $key ] ?? '' );
		}

		return $out;
	}

	/**
	 * Muestra el valor traducido en admin y frontend si aún es el default canónico en inglés.
	 */
	public static function localize_general_setting_value( string $key, string $value ): string {
		$canonical  = self::canonical_general_defaults();
		$translated = self::translated_general_defaults();

		if ( ! isset( $canonical[ $key ], $translated[ $key ] ) ) {
			return $value;
		}

		if ( $value === $canonical[ $key ] || $value === $translated[ $key ] ) {
			return $translated[ $key ];
		}

		return $value;
	}

	/**
	 * Al guardar, persiste el default canónico en inglés si el usuario dejó el texto traducido por defecto.
	 */
	public static function canonicalize_general_setting_value( string $key, string $value ): string {
		$canonical  = self::canonical_general_defaults();
		$translated = self::translated_general_defaults();

		if ( ! isset( $canonical[ $key ], $translated[ $key ] ) ) {
			return $value;
		}

		if ( $value === $translated[ $key ] || $value === $canonical[ $key ] ) {
			return $canonical[ $key ];
		}

		return $value;
	}

	/**
	 * Cadenas i18n compartidas del preview del admin (Estilo y General).
	 *
	 * @return array<string, string>
	 */
	private static function admin_preview_i18n_strings(): array {
		return array(
			'openPanel'            => __( 'Open panel', 'multiai-chatbot' ),
			'closePanel'           => __( 'Close panel', 'multiai-chatbot' ),
			'openChat'             => __( 'Open chat', 'multiai-chatbot' ),
			'minimize'             => __( 'Minimize', 'multiai-chatbot' ),
			'reset'                => __( 'Reset', 'multiai-chatbot' ),
			'close'                => __( 'Close', 'multiai-chatbot' ),
			'placeholder'          => __( 'Type your message…', 'multiai-chatbot' ),
			'send'                 => __( 'Send', 'multiai-chatbot' ),
			'fallbackTitle'        => __( 'AI Agent', 'multiai-chatbot' ),
			'fallbackSubtitle'     => __( 'System online', 'multiai-chatbot' ),
			'fallbackWelcome'      => __(
				"Hello. I'm an AI agent. I may make mistakes; please verify important information before making decisions.\n\nHow can I help you?",
				'multiai-chatbot'
			),
			'previewSampleUser'      => __( 'What are your opening hours?', 'multiai-chatbot' ),
			'previewSampleAssistant' => __(
				'We are open Monday through Friday, 9:00 AM to 6:00 PM.',
				'multiai-chatbot'
			),
			'welcomeMessageLabel'    => __( 'Welcome message', 'multiai-chatbot' ),
			'previewSampleModel'     => __(
				'gemini-3.5-flash (API used this; configured fallback: gemini-3.1-flash-lite)',
				'multiai-chatbot'
			),
			'widgetDisabled'       => __(
				'Global widget is disabled. The preview shows how copy would look if enabled.',
				'multiai-chatbot'
			),
			'widgetEnabled'        => __( 'Enabled', 'multiai-chatbot' ),
			'widgetDisabledLabel'  => __( 'Disabled', 'multiai-chatbot' ),
			'contrastWarning'      => __(
				'Low contrast between primary color and background; check accessibility.',
				'multiai-chatbot'
			),
			'resetOverrides'       => __( 'Reset color overrides', 'multiai-chatbot' ),
			'exportTheme'          => __( 'Export theme', 'multiai-chatbot' ),
			'importTheme'          => __( 'Import theme', 'multiai-chatbot' ),
			'importSuccess'        => __(
				'Theme imported into the form. Save to apply on the site.',
				'multiai-chatbot'
			),
			'importError'          => __( 'Invalid theme JSON.', 'multiai-chatbot' ),
		);
	}

	/**
	 * Cadenas i18n solo de la pestaña General (admin JS).
	 *
	 * @return array<string, string>
	 */
	/**
	 * Provider-specific field descriptions for the Model admin tab (localized for JS).
	 *
	 * @return array<string, array{model: string, candidates: string}>
	 */
	private static function admin_model_provider_descriptions(): array {
		return array(
			'wordpress_ai' => array(
				'model'      => __( 'Primary model from your connected AI providers.', 'multiai-chatbot' ),
				'candidates' => __( 'Used only if the primary model fails (not on timeout). Choose a different model than the primary.', 'multiai-chatbot' ),
			),
			'ollama'       => array(
				'model'      => __( 'Name of the model installed in Ollama (e.g. llama3).', 'multiai-chatbot' ),
				'candidates' => '',
			),
		);
	}

	public static function maybe_ai_client_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin tab deep-link only.
		if ( ! isset( $_GET['page'] ) || 'multch-plugin' !== sanitize_key( wp_unslash( (string) $_GET['page'] ) ) ) {
			return;
		}

		$settings = self::get_stored_settings();
		$provider = (string) ( $settings['provider'] ?? 'wordpress_ai' );
		if ( 'wordpress_ai' !== $provider || multch_ai_client_available() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			wp_kses(
				sprintf(
					/* translators: %s: WordPress version number. */
					__( 'Cloud AI requires WordPress %s or newer with the built-in AI Client, or choose Ollama for a local model.', 'multiai-chatbot' ),
					'7.0'
				),
				array()
			)
		);
	}

	private static function admin_general_i18n_strings(): array {
		return array(
			'copyShortcode'       => __( 'Copy shortcode', 'multiai-chatbot' ),
			'copied'              => __( 'Copied', 'multiai-chatbot' ),
			'copyFailed'          => __( 'Could not copy.', 'multiai-chatbot' ),
			'restoreWelcome'      => __( 'Restore default welcome message?', 'multiai-chatbot' ),
			'restoreSystemPrompt' => __( 'Restore default system instructions?', 'multiai-chatbot' ),
			/* translators: 1: current character count, 2: maximum allowed characters */
			'charCount'           => __( '%1$d / %2$d characters', 'multiai-chatbot' ),
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

		$z = (int) ( $settings['style_z_index'] ?? 0 );

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
			'zIndex'         => $z > 0 ? $z : 0,
			'fontFamily'     => sanitize_key( (string) ( $settings['style_font_family'] ?? 'system' ) ) ?: 'system',
			'launcherLabel'    => ! empty( $settings['style_launcher_label'] ),
			'showCredit'       => ! empty( $settings['style_show_credit'] ),
			'showWelcomeLabel' => ! empty( $settings['style_show_welcome_label'] ),
			'showModelLabel'   => ! empty( $settings['style_show_model_label'] ),
			'reduceMotion'     => ! empty( $settings['style_reduce_motion'] ),
			'presetAuto'       => ! empty( $settings['style_preset_auto'] ),
			'presetAutoDark'   => $preset_auto_dark,
		);
	}

	/**
	 * Developer credit labels and URLs for the frontend widget.
	 *
	 * @return array{productName: string, authorName: string, productUrl: string, authorUrl: string}
	 */
	public static function developer_credit_for_js(): array {
		return array(
			'productName' => __( 'MultiAI Chatbot', 'multiai-chatbot' ),
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
		$out['stats_history_enabled'] = self::sanitize_checkbox( $input, $current, 'stats_history_enabled', (bool) $defaults['stats_history_enabled'] );
		$out['streaming_enabled'] = self::sanitize_checkbox( $input, $current, 'streaming_enabled', (bool) $defaults['streaming_enabled'] );

		$out['welcome_message'] = self::canonicalize_general_setting_value(
			'welcome_message',
			self::truncate_setting_string(
				sanitize_textarea_field( $input['welcome_message'] ?? $current['welcome_message'] ?? $defaults['welcome_message'] ),
				$limits['welcome_message']
			)
		);
		$out['system_prompt'] = self::canonicalize_general_setting_value(
			'system_prompt',
			self::truncate_setting_string(
				sanitize_textarea_field( $input['system_prompt'] ?? $current['system_prompt'] ?? $defaults['system_prompt'] ),
				$limits['system_prompt']
			)
		);
		$out['widget_title'] = self::canonicalize_general_setting_value(
			'widget_title',
			self::truncate_setting_string(
				sanitize_text_field( $input['widget_title'] ?? $current['widget_title'] ?? $defaults['widget_title'] ),
				$limits['widget_title']
			)
		);
		$out['widget_subtitle'] = self::canonicalize_general_setting_value(
			'widget_subtitle',
			self::truncate_setting_string(
				sanitize_text_field( $input['widget_subtitle'] ?? $current['widget_subtitle'] ?? $defaults['widget_subtitle'] ),
				$limits['widget_subtitle']
			)
		);

		if ( ! empty( $out['widget_enabled'] ) && '' === trim( (string) $out['widget_title'] ) ) {
			add_settings_error(
				'multch_plugin_group',
				'multch_empty_widget_title',
				__( 'The widget is enabled but the title is empty. Visitors may see a blank header until you set a title.', 'multiai-chatbot' ),
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
		$provider = sanitize_key( $input['provider'] ?? $current['provider'] ?? 'wordpress_ai' );
		if ( in_array( $provider, multch_legacy_cloud_provider_ids(), true ) ) {
			$provider = 'wordpress_ai';
		}
		$out['provider'] = in_array( $provider, array( 'wordpress_ai', 'ollama' ), true )
			? $provider
			: (string) ( $current['provider'] ?? 'wordpress_ai' );

		$out['model'] = sanitize_text_field( $input['model'] ?? $current['model'] ?? $defaults['model'] );

		if ( array_key_exists( 'model_fallback', $input ) ) {
			$out['model_candidates'] = sanitize_text_field( (string) ( $input['model_fallback'] ?? '' ) );
		} else {
			$out['model_candidates'] = sanitize_text_field( $input['model_candidates'] ?? $current['model_candidates'] ?? $defaults['model_candidates'] );
		}
		$out['ollama_base_url']  = esc_url_raw( $input['ollama_base_url'] ?? $current['ollama_base_url'] ?? $defaults['ollama_base_url'] );
		$out['request_timeout']  = max( 5, min( 120, (int) ( $input['request_timeout'] ?? $current['request_timeout'] ?? $defaults['request_timeout'] ) ) );
		$out['allow_google_any_model'] = self::sanitize_checkbox(
			$input,
			$current,
			'allow_google_any_model',
			(bool) $defaults['allow_google_any_model']
		);

		unset( $out['api_key'], $out['openai_base_url'], $out['deepseek_base_url'] );

		delete_transient( 'multch_ai_models_cache' );
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
		$out['telemetry_file_log'] = self::sanitize_checkbox( $input, $current, 'telemetry_file_log', (bool) $defaults['telemetry_file_log'] );
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
				__( 'Border radius is invalid; that value was ignored.', 'multiai-chatbot' )
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
				__( 'Panel width is invalid; that value was ignored.', 'multiai-chatbot' )
			);
		}

		$out['style_launcher_label'] = self::sanitize_checkbox( $input, $current, 'style_launcher_label', (bool) $defaults['style_launcher_label'] );
		$out['style_show_credit']    = self::sanitize_checkbox( $input, $current, 'style_show_credit', (bool) $defaults['style_show_credit'] );
		$out['style_show_welcome_label'] = self::sanitize_checkbox( $input, $current, 'style_show_welcome_label', (bool) $defaults['style_show_welcome_label'] );
		$out['style_show_model_label']   = self::sanitize_checkbox( $input, $current, 'style_show_model_label', (bool) $defaults['style_show_model_label'] );

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
				__( 'Panel max height is invalid; that value was ignored.', 'multiai-chatbot' )
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
			'system'  => __( 'System UI', 'multiai-chatbot' ),
			'inherit' => __( 'Inherit from theme', 'multiai-chatbot' ),
			'serif'   => __( 'Serif', 'multiai-chatbot' ),
			'mono'    => __( 'Monospace', 'multiai-chatbot' ),
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
			'style_show_welcome_label',
			'style_show_model_label',
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
			'showCredit'       => ! empty( $merged['style_show_credit'] ),
			'showWelcomeLabel' => ! empty( $merged['style_show_welcome_label'] ),
			'showModelLabel'   => ! empty( $merged['style_show_model_label'] ),
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
		return (array) apply_filters( 'multch_style_config', $config, $settings );
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
	 * Compatibilidad si falta multch_admin_tab en el POST.
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
			'multch_plugin_group',
			'multch_invalid_css_size_' . md5( $message ),
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

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by options.php before settings redirect.
		if ( empty( $_POST['option_page'] ) || 'multch_plugin_group' !== $_POST['option_page'] ) {
			return $location;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by options.php before settings redirect.
		$tab = isset( $_POST['multch_admin_tab'] ) ? sanitize_key( wp_unslash( (string) $_POST['multch_admin_tab'] ) ) : 'general';
		$allowed_tabs = array( 'general', 'model', 'security', 'style' );

		if ( ! in_array( $tab, $allowed_tabs, true ) ) {
			return $location;
		}

		return add_query_arg( 'tab', $tab, remove_query_arg( 'tab', $location ) );
	}

	public static function enqueue_admin_assets( string $hook ): void {
		if ( 'toplevel_page_multch-plugin' !== $hook ) {
			return;
		}

		$admin_css_path = MULTCH_PLUGIN_PATH . 'assets/css/admin.css';
		$admin_css_ver  = file_exists( $admin_css_path )
			? (string) filemtime( $admin_css_path )
			: MULTCH_PLUGIN_VERSION;

		wp_enqueue_style(
			'multch-plugin-admin',
			MULTCH_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			$admin_css_ver
		);

		$admin_feedback_js_path = MULTCH_PLUGIN_PATH . 'assets/js/admin-feedback.js';
		$admin_feedback_js_ver  = file_exists( $admin_feedback_js_path )
			? (string) filemtime( $admin_feedback_js_path )
			: MULTCH_PLUGIN_VERSION;

		wp_enqueue_script(
			'multch-plugin-admin-feedback',
			MULTCH_PLUGIN_URL . 'assets/js/admin-feedback.js',
			array(),
			$admin_feedback_js_ver,
			true
		);

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin tab for asset loading; capability checked via admin screen hook.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'general';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( 'style' === $tab ) {
			wp_enqueue_style( 'wp-color-picker' );

			$multch_css_path = MULTCH_PLUGIN_PATH . 'assets/css/chatbot.css';
			$multch_css_ver  = file_exists( $multch_css_path )
				? (string) filemtime( $multch_css_path )
				: MULTCH_PLUGIN_VERSION;

			$admin_style_js_path = MULTCH_PLUGIN_PATH . 'assets/js/admin-style.js';
			$admin_style_js_ver  = file_exists( $admin_style_js_path )
				? (string) filemtime( $admin_style_js_path )
				: MULTCH_PLUGIN_VERSION;

			wp_enqueue_style(
				'multch-plugin-admin-preview',
				MULTCH_PLUGIN_URL . 'assets/css/chatbot.css',
				array( 'multch-plugin-admin' ),
				$multch_css_ver
			);

			$admin_preview_shared_path = MULTCH_PLUGIN_PATH . 'assets/js/admin-preview-shared.js';
			$admin_preview_shared_ver  = file_exists( $admin_preview_shared_path )
				? (string) filemtime( $admin_preview_shared_path )
				: MULTCH_PLUGIN_VERSION;

			wp_enqueue_script(
				'multch-plugin-admin-preview-shared',
				MULTCH_PLUGIN_URL . 'assets/js/admin-preview-shared.js',
				array(),
				$admin_preview_shared_ver,
				true
			);

			wp_enqueue_script(
				'multch-plugin-admin-style',
				MULTCH_PLUGIN_URL . 'assets/js/admin-style.js',
				array( 'wp-color-picker', 'multch-plugin-admin-preview-shared' ),
				$admin_style_js_ver,
				true
			);

			$settings = Multch_Plugin::get_settings();
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
				'multch-plugin-admin-style',
				'multchStylePreview',
				array(
					'optionKey'       => self::OPTION_KEY,
					'presets'         => self::style_presets(),
					'presetMeta'      => $preset_meta_for_js,
					'exportKeys'      => self::style_export_keys(),
					'widgetTitle'     => self::localize_general_setting_value( 'widget_title', (string) ( $settings['widget_title'] ?? '' ) ),
					'widgetSubtitle'  => self::localize_general_setting_value( 'widget_subtitle', (string) ( $settings['widget_subtitle'] ?? '' ) ),
					'welcomeMessage'  => self::localize_general_setting_value( 'welcome_message', (string) ( $settings['welcome_message'] ?? '' ) ),
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
			$multch_css_path = MULTCH_PLUGIN_PATH . 'assets/css/chatbot.css';
			$multch_css_ver  = file_exists( $multch_css_path )
				? (string) filemtime( $multch_css_path )
				: MULTCH_PLUGIN_VERSION;

			$admin_preview_shared_path = MULTCH_PLUGIN_PATH . 'assets/js/admin-preview-shared.js';
			$admin_preview_shared_ver  = file_exists( $admin_preview_shared_path )
				? (string) filemtime( $admin_preview_shared_path )
				: MULTCH_PLUGIN_VERSION;

			$admin_general_js_path = MULTCH_PLUGIN_PATH . 'assets/js/admin-general.js';
			$admin_general_js_ver  = file_exists( $admin_general_js_path )
				? (string) filemtime( $admin_general_js_path )
				: MULTCH_PLUGIN_VERSION;

			wp_enqueue_style(
				'multch-plugin-admin-preview',
				MULTCH_PLUGIN_URL . 'assets/css/chatbot.css',
				array( 'multch-plugin-admin' ),
				$multch_css_ver
			);

			wp_enqueue_script(
				'multch-plugin-admin-preview-shared',
				MULTCH_PLUGIN_URL . 'assets/js/admin-preview-shared.js',
				array(),
				$admin_preview_shared_ver,
				true
			);

			wp_enqueue_script(
				'multch-plugin-admin-general',
				MULTCH_PLUGIN_URL . 'assets/js/admin-general.js',
				array( 'multch-plugin-admin-preview-shared' ),
				$admin_general_js_ver,
				true
			);

			$settings         = Multch_Plugin::get_settings();
			$display_defaults = self::translated_general_defaults();

			wp_localize_script(
				'multch-plugin-admin-general',
				'multchGeneralPreview',
				array(
					'optionKey'         => self::OPTION_KEY,
					'savedStyle'        => self::preview_style_settings_for_js( $settings ),
					'presets'           => self::style_presets(),
					'limits'            => self::general_field_limits(),
					'defaults'          => $display_defaults,
					'shortcode'         => '[multch_widget]',
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

		if ( 'model' === $tab ) {
			$admin_model_js_path = MULTCH_PLUGIN_PATH . 'assets/js/admin-model.js';
			$admin_model_js_ver  = file_exists( $admin_model_js_path )
				? (string) filemtime( $admin_model_js_path )
				: MULTCH_PLUGIN_VERSION;

			wp_enqueue_script(
				'multch-plugin-admin-model',
				MULTCH_PLUGIN_URL . 'assets/js/admin-model.js',
				array(),
				$admin_model_js_ver,
				true
			);

			wp_localize_script(
				'multch-plugin-admin-model',
				'multchModelAdmin',
				array(
					'descriptions' => self::admin_model_provider_descriptions(),
				)
			);
		}

		if ( 'stats' === $tab ) {
			$admin_stats_js_path = MULTCH_PLUGIN_PATH . 'assets/js/admin-stats.js';
			$admin_stats_js_ver  = file_exists( $admin_stats_js_path )
				? (string) filemtime( $admin_stats_js_path )
				: MULTCH_PLUGIN_VERSION;

			wp_enqueue_script(
				'multch-plugin-admin-stats',
				MULTCH_PLUGIN_URL . 'assets/js/admin-stats.js',
				array(),
				$admin_stats_js_ver,
				true
			);
		}

		if ( 'security' === $tab ) {
			$admin_security_js_path = MULTCH_PLUGIN_PATH . 'assets/js/admin-security.js';
			$admin_security_js_ver  = file_exists( $admin_security_js_path )
				? (string) filemtime( $admin_security_js_path )
				: MULTCH_PLUGIN_VERSION;

			wp_enqueue_script(
				'multch-plugin-admin-security',
				MULTCH_PLUGIN_URL . 'assets/js/admin-security.js',
				array(),
				$admin_security_js_ver,
				true
			);

			wp_localize_script(
				'multch-plugin-admin-security',
				'multchSecurityAdmin',
				array(
					'siteOrigin' => esc_url_raw( home_url( '/' ) ),
					'i18n'       => array(
						'copied'           => __( 'Copied', 'multiai-chatbot' ),
						'copyFailed'       => __( 'Could not copy.', 'multiai-chatbot' ),
						'cacheOff'         => __( 'Disabled', 'multiai-chatbot' ),
						'cacheMinutes'     => __( '%d min', 'multiai-chatbot' ),
						'cacheHours'       => __( '%d h', 'multiai-chatbot' ),
						'suspendHours'     => __( '%1$d h %2$d min', 'multiai-chatbot' ),
						'cacheDays'        => __( '%d days', 'multiai-chatbot' ),
						'cacheDay'         => __( '1 day', 'multiai-chatbot' ),
						'originsDefaultHint' => __( 'Default: only this WordPress site can use the chat API.', 'multiai-chatbot' ),
						'suspendSummary'   => __( 'After %1$d violations · %2$s', 'multiai-chatbot' ),
					),
				)
			);
		}

		if ( 'history' === $tab ) {
			$admin_history_js_path = MULTCH_PLUGIN_PATH . 'assets/js/admin-history.js';
			$admin_history_js_ver  = file_exists( $admin_history_js_path )
				? (string) filemtime( $admin_history_js_path )
				: MULTCH_PLUGIN_VERSION;

			wp_enqueue_script(
				'multch-plugin-admin-history',
				MULTCH_PLUGIN_URL . 'assets/js/admin-history.js',
				array(),
				$admin_history_js_ver,
				true
			);

			wp_localize_script(
				'multch-plugin-admin-history',
				'multchHistoryAdmin',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'multch_history_detail' ),
					'deleteNonce' => wp_create_nonce( 'multch_delete_conversation' ),
					'i18n'    => array(
						'loading'      => __( 'Loading messages…', 'multiai-chatbot' ),
						'error'        => __( 'Could not load the conversation.', 'multiai-chatbot' ),
						'retry'        => __( 'Retry', 'multiai-chatbot' ),
						'copied'       => __( 'Copied', 'multiai-chatbot' ),
						'copyJson'     => __( 'Copy JSON', 'multiai-chatbot' ),
						'copyJsonLoading' => __( 'Preparing JSON…', 'multiai-chatbot' ),
						'copyJsonFailed' => __( 'Could not load conversation JSON.', 'multiai-chatbot' ),
						'copyFailed'   => __( 'Could not copy.', 'multiai-chatbot' ),
						'deleteConfirm' => __( 'Delete this conversation and all its messages?', 'multiai-chatbot' ),
						'deleteFailed' => __( 'Could not delete the conversation.', 'multiai-chatbot' ),
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
		return (array) apply_filters( 'multch_style_presets', $presets );
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
				'label'      => __( 'Sapphire', 'multiai-chatbot' ),
				'desc'       => __( 'Indigo blue with soft violet. Professional and trustworthy.', 'multiai-chatbot' ),
				'badge'      => __( 'Light', 'multiai-chatbot' ),
				'badge_type' => 'light',
				'colors'     => array( '#2563eb', '#6366f1', '#ffffff' ),
			),
			'dark-glass' => array(
				'label'      => __( 'Midnight', 'multiai-chatbot' ),
				'desc'       => __( 'Deep dark with cyan and violet accents. Readable header.', 'multiai-chatbot' ),
				'badge'      => __( 'Dark', 'multiai-chatbot' ),
				'badge_type' => 'dark',
				'colors'     => array( '#38bdf8', '#a78bfa', '#0f172a' ),
			),
			'obsidian'   => array(
				'label'      => __( 'Obsidian', 'multiai-chatbot' ),
				'desc'       => __( 'Charcoal slate with emerald and teal highlights. Calm dark UI.', 'multiai-chatbot' ),
				'badge'      => __( 'Dark', 'multiai-chatbot' ),
				'badge_type' => 'dark',
				'colors'     => array( '#34d399', '#2dd4bf', '#0c1117' ),
			),
			'minimal'    => array(
				'label'      => __( 'Monochrome', 'multiai-chatbot' ),
				'desc'       => __( 'Neutral zinc, straight edges and subtle shadows.', 'multiai-chatbot' ),
				'badge'      => __( 'Neutral', 'multiai-chatbot' ),
				'badge_type' => 'neutral',
				'colors'     => array( '#27272a', '#71717a', '#ffffff' ),
			),
			'ocean'      => array(
				'label'      => __( 'Aqua', 'multiai-chatbot' ),
				'desc'       => __( 'Deep cyan with turquoise highlights. Fresh and modern.', 'multiai-chatbot' ),
				'badge'      => __( 'Light', 'multiai-chatbot' ),
				'badge_type' => 'light',
				'colors'     => array( '#0e7490', '#22d3ee', '#f0fdff' ),
			),
			'sunset'     => array(
				'label'      => __( 'Ember', 'multiai-chatbot' ),
				'desc'       => __( 'Warm orange with pink accents. Cozy and energetic.', 'multiai-chatbot' ),
				'badge'      => __( 'Light', 'multiai-chatbot' ),
				'badge_type' => 'light',
				'colors'     => array( '#ea580c', '#f43f5e', '#fff7ed' ),
			),
			'forest'     => array(
				'label'      => __( 'Emerald', 'multiai-chatbot' ),
				'desc'       => __( 'Emerald green with natural backgrounds. Calm and trustworthy.', 'multiai-chatbot' ),
				'badge'      => __( 'Light', 'multiai-chatbot' ),
				'badge_type' => 'light',
				'colors'     => array( '#059669', '#34d399', '#ecfdf5' ),
			),
			'lavender'   => array(
				'label'      => __( 'Amethyst', 'multiai-chatbot' ),
				'desc'       => __( 'Soft violet with light lavender. Elegant and modern.', 'multiai-chatbot' ),
				'badge'      => __( 'Light', 'multiai-chatbot' ),
				'badge_type' => 'light',
				'colors'     => array( '#7c3aed', '#a855f7', '#faf5ff' ),
			),
			'plum'       => array(
				'label'      => __( 'Plum', 'multiai-chatbot' ),
				'desc'       => __( 'Deep purple with fuchsia accents. Dark and sophisticated.', 'multiai-chatbot' ),
				'badge'      => __( 'Dark', 'multiai-chatbot' ),
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
			'bottom-right'  => __( 'Bottom right', 'multiai-chatbot' ),
			'center-right'  => __( 'Center right', 'multiai-chatbot' ),
			'bottom-left'   => __( 'Bottom left', 'multiai-chatbot' ),
			'center-left'   => __( 'Center left', 'multiai-chatbot' ),
			'bottom-center' => __( 'Bottom center', 'multiai-chatbot' ),
		);
	}

	/**
	 * Blocks admin actions that require statistics/history when the opt-in is off.
	 */
	private static function require_stats_history_enabled_or_die(): void {
		if ( Multch_Plugin::is_stats_history_enabled() ) {
			return;
		}

		wp_die(
			esc_html__( 'Statistics and conversation history are disabled. Enable them under General first.', 'multiai-chatbot' ),
			esc_html__( 'Statistics and history disabled', 'multiai-chatbot' ),
			array( 'response' => 403 )
		);
	}

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
			wp_die( esc_html__( 'Insufficient permissions.', 'multiai-chatbot' ) );
		}
		self::require_stats_history_enabled_or_die();
		check_admin_referer( 'multch_export_csv' );

		$filters = self::get_stats_filters_from_request();
		unset( $filters['offset'], $filters['per_page'] );
		$csv = Multch_Telemetry::export_csv( $filters );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=multch-telemetry-' . gmdate( 'Y-m-d' ) . '.csv' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public static function export_history_csv(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'multiai-chatbot' ) );
		}
		self::require_stats_history_enabled_or_die();
		check_admin_referer( 'multch_export_history_csv' );

		$filters = self::get_history_filters_from_request();
		unset( $filters['offset'], $filters['per_page'] );
		Multch_Chat_History::export_csv( $filters );
		exit;
	}

	public static function purge_history(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'multiai-chatbot' ) );
		}
		self::require_stats_history_enabled_or_die();
		check_admin_referer( 'multch_purge_history' );

		$settings = Multch_Plugin::get_settings();
		$days     = isset( $settings['history_retention_days'] ) ? (int) $settings['history_retention_days'] : 0;
		if ( $days <= 0 ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'             => 'multch-plugin',
						'tab'              => 'history',
						'multch_purge'    => 'disabled',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$result = Multch_Chat_History::purge_older_than_days( $days );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => 'multch-plugin',
					'tab'               => 'history',
					'multch_purged'    => 1,
					'purged_conversations' => (int) ( $result['deleted_conversations'] ?? 0 ),
					'purged_messages'   => (int) ( $result['deleted_messages'] ?? 0 ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function ajax_delete_conversation(): void {
		check_ajax_referer( 'multch_delete_conversation', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'multiai-chatbot' ) ), 403 );
		}

		if ( ! Multch_Plugin::is_stats_history_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Statistics and history are disabled.', 'multiai-chatbot' ) ), 403 );
		}

		$conversation_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( $conversation_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid conversation.', 'multiai-chatbot' ) ), 400 );
		}

		if ( ! Multch_Chat_History::delete_conversation( $conversation_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not delete the conversation.', 'multiai-chatbot' ) ), 500 );
		}

		wp_send_json_success();
	}

	public static function purge_telemetry(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'multiai-chatbot' ) );
		}
		self::require_stats_history_enabled_or_die();
		check_admin_referer( 'multch_purge_telemetry' );

		$settings = Multch_Plugin::get_settings();
		$days     = isset( $settings['telemetry_retention_days'] ) ? (int) $settings['telemetry_retention_days'] : 0;
		if ( $days <= 0 ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'              => 'multch-plugin',
						'tab'               => 'stats',
						'multch_purge'     => 'disabled',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$result = Multch_Telemetry::purge_older_than_days( $days );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'multch-plugin',
					'tab'            => 'stats',
					'multch_purged' => 1,
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

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Admin list/filter GET params; screen requires manage_options.
		$tab               = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'general';
		$settings          = Multch_Plugin::get_settings();
		$stats_history_on  = Multch_Plugin::is_stats_history_enabled();
		$tabs              = array(
			'general'  => __( 'General', 'multiai-chatbot' ),
			'model'    => __( 'AI Model', 'multiai-chatbot' ),
			'security' => __( 'Security', 'multiai-chatbot' ),
			'style'    => __( 'Chat style', 'multiai-chatbot' ),
		);

		if ( $stats_history_on ) {
			$tabs['stats']   = __( 'Statistics', 'multiai-chatbot' );
			$tabs['history'] = __( 'History', 'multiai-chatbot' );
		} elseif ( in_array( $tab, array( 'stats', 'history' ), true ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'                  => 'multch-plugin',
						'tab'                   => 'general',
						'multch_stats_history' => 'disabled',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}

		$widget_on = ! empty( $settings['widget_enabled'] );
		?>
		<div class="wrap multch-admin-wrap">
			<header class="multch-admin-header">
				<div class="multch-admin-header__brand">
					<span class="multch-admin-header__icon dashicons dashicons-format-chat" aria-hidden="true"></span>
					<h1><?php esc_html_e( 'MultiAI ChatBot', 'multiai-chatbot' ); ?></h1>
				</div>
				<span class="multch-admin-badge <?php echo $widget_on ? 'multch-admin-badge--on' : 'multch-admin-badge--off'; ?>">
					<?php
					echo $widget_on
						? esc_html__( 'Enabled', 'multiai-chatbot' )
						: esc_html__( 'Disabled', 'multiai-chatbot' );
					?>
				</span>
			</header>

			<nav class="nav-tab-wrapper multch-admin-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'multiai-chatbot' ); ?>">
				<?php foreach ( $tabs as $id => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=multch-plugin&tab=' . $id ) ); ?>"
						class="nav-tab<?php echo $tab === $id ? ' nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php self::render_save_notices(); ?>

			<?php if ( in_array( $tab, array( 'stats', 'history' ), true ) ) : ?>
				<div class="multch-admin-body">
					<?php
					if ( 'stats' === $tab ) {
						self::render_stats_tab();
					} else {
						self::render_history_tab();
					}
					?>
				</div>
			<?php else : ?>
				<form method="post" action="options.php" class="multch-admin-form">
					<?php settings_fields( 'multch_plugin_group' ); ?>
					<input type="hidden" name="multch_admin_tab" value="<?php echo esc_attr( $tab ); ?>" />

					<div class="multch-admin-body">
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
						class="multch-admin-form-divider"
						role="presentation"
						aria-hidden="true"
						style="display:block;height:1.25rem;min-height:1.25rem;margin-top:1rem;border-top:1px solid #e2e8f0;box-sizing:border-box;"
					></div>

					<div class="multch-admin-footer">
						<?php submit_button( __( 'Save changes', 'multiai-chatbot' ), 'primary', 'submit', false ); ?>
						<span class="multch-admin-footer__hint">
							<?php esc_html_e( 'Changes apply immediately on the public site.', 'multiai-chatbot' ); ?>
						</span>
					</div>
				</form>
			<?php endif; ?>

			<?php Multch_Donation_Footer::render(); ?>
		</div>
		<?php
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	private static function render_save_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin redirect notice only.
		if ( isset( $_GET['multch_stats_history'] ) && 'disabled' === sanitize_key( wp_unslash( (string) $_GET['multch_stats_history'] ) ) ) {
			self::render_admin_notice(
				__( 'Statistics and conversation history are disabled. Enable them under General to access those screens.', 'multiai-chatbot' ),
				'warning'
			);
		}

		$errors = self::dedupe_settings_errors( self::consume_settings_errors( 'multch_plugin_group' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- settings-updated is set by options.php after verified save.
		$save_succeeded = isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'];

		if ( $save_succeeded ) {
			self::render_admin_notice(
				__( 'Changes saved successfully.', 'multiai-chatbot' ),
				'success'
			);
		}

		foreach ( $errors as $error ) {
			$type = (string) ( $error['type'] ?? 'info' );
			if ( ! in_array( $type, array( 'error', 'success', 'warning', 'info', 'updated' ), true ) ) {
				$type = 'info';
			}
			if ( 'updated' === $type ) {
				$type = 'success';
			}
			if ( $save_succeeded && in_array( $type, array( 'success', 'updated' ), true ) ) {
				continue;
			}

			self::render_admin_notice( (string) ( $error['message'] ?? '' ), $type );
		}
	}

	/**
	 * Evita avisos duplicados si el sanitize se ejecuta más de una vez en el mismo guardado.
	 *
	 * @param list<array<string, mixed>> $errors
	 * @return list<array<string, mixed>>
	 */
	private static function dedupe_settings_errors( array $errors ): array {
		$seen   = array();
		$unique = array();

		foreach ( $errors as $error ) {
			$key = (string) ( $error['code'] ?? '' ) . '|' . (string) ( $error['type'] ?? '' ) . '|' . (string) ( $error['message'] ?? '' );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$unique[]     = $error;
		}

		return $unique;
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

		$type_class = 'multch-admin-notice--' . sanitize_html_class( $type );
		$labels     = array(
			'success' => __( 'Saved', 'multiai-chatbot' ),
			'error'   => __( 'Error', 'multiai-chatbot' ),
			'warning' => __( 'Notice', 'multiai-chatbot' ),
			'info'    => __( 'Information', 'multiai-chatbot' ),
		);
		$label      = $labels[ $type ] ?? $labels['info'];
		?>
		<div
			class="multch-admin-notice <?php echo esc_attr( $type_class ); ?>"
			role="<?php echo 'error' === $type ? 'alert' : 'status'; ?>"
			data-auto-dismiss="true"
		>
			<div class="multch-admin-notice__content">
				<strong class="multch-admin-notice__title"><?php echo esc_html( $label ); ?></strong>
				<p class="multch-admin-notice__text"><?php echo esc_html( $message ); ?></p>
			</div>
			<button
				type="button"
				class="multch-admin-notice__dismiss"
				aria-label="<?php esc_attr_e( 'Dismiss notice', 'multiai-chatbot' ); ?>"
			>&times;</button>
		</div>
		<?php
	}

	/**
	 * @param string $title
	 * @param string $description
	 */
	private static function card_open( string $title, string $description = '', string $extra_class = '', string $badge = '' ): void {
		$card_class = 'multch-admin-card';
		if ( '' !== $extra_class ) {
			foreach ( preg_split( '/\s+/', trim( $extra_class ) ) ?: array() as $modifier ) {
				if ( '' !== $modifier ) {
					$card_class .= ' ' . sanitize_html_class( $modifier );
				}
			}
		}
		?>
		<div class="<?php echo esc_attr( $card_class ); ?>">
			<div class="multch-admin-card__head"<?php echo '' !== $badge ? ' data-badge="' . esc_attr( $badge ) . '"' : ''; ?>>
				<h2><?php echo esc_html( $title ); ?></h2>
				<?php if ( '' !== $description ) : ?>
					<p><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</div>
			<div class="multch-admin-card__body">
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
		<div class="multch-admin-preview-card">
			<div class="multch-admin-card">
				<div class="multch-admin-card__head multch-admin-preview__head">
					<div>
						<h2><?php esc_html_e( 'Content preview', 'multiai-chatbot' ); ?></h2>
					</div>
					<button type="button" class="button button-secondary" id="multch-preview-toggle" aria-pressed="false">
						<?php esc_html_e( 'Open panel', 'multiai-chatbot' ); ?>
					</button>
				</div>
				<div class="multch-admin-card__body">
					<div class="multch-admin-preview">
						<div
							class="multch-admin-preview__viewport"
							id="multch-preview-viewport"
							data-preview-position="<?php echo esc_attr( $position ); ?>"
							data-preview-panel-open="false"
							aria-label="<?php esc_attr_e( 'Web page simulation', 'multiai-chatbot' ); ?>"
						>
							<div class="multch-admin-preview__page-mock">
								<span></span><span></span><span></span>
							</div>
							<div class="maicb-preview-widget-host" aria-hidden="false"></div>
							<div class="multch-admin-preview__disabled-overlay" id="multch-preview-disabled-overlay" hidden>
								<p id="multch-preview-disabled-text"></p>
							</div>
						</div>
						<p class="multch-admin-preview__hint" id="multch-preview-hint"><?php echo esc_html( $hint_text ); ?></p>
						<?php if ( $show_contrast ) : ?>
							<p class="multch-admin-preview__contrast" id="multch-preview-contrast" hidden role="status"></p>
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
		$display_title    = self::localize_general_setting_value( 'widget_title', (string) ( $settings['widget_title'] ?? '' ) );
		$display_subtitle = self::localize_general_setting_value( 'widget_subtitle', (string) ( $settings['widget_subtitle'] ?? '' ) );
		$display_welcome  = self::localize_general_setting_value( 'welcome_message', (string) ( $settings['welcome_message'] ?? '' ) );
		$display_prompt   = self::localize_general_setting_value( 'system_prompt', (string) ( $settings['system_prompt'] ?? '' ) );
		$widget_on    = ! empty( $settings['widget_enabled'] );
		$streaming_on = ! empty( $settings['streaming_enabled'] );
		$position     = sanitize_key( (string) ( $settings['style_position'] ?? 'bottom-right' ) );
		if ( ! in_array( $position, self::style_positions(), true ) ) {
			$position = 'bottom-right';
		}

		$model_url = admin_url( 'admin.php?page=multch-plugin&tab=model' );
		?>
		<div class="multch-admin-layout multch-admin-layout--split">
			<div class="multch-admin-general-fields">
		<?php
		self::card_open(
			__( 'Widget availability', 'multiai-chatbot' ),
			__( 'Choose whether the chat appears automatically on every page.', 'multiai-chatbot' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Global widget', 'multiai-chatbot' ); ?></th>
				<td>
					<label class="multch-admin-toggle">
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[widget_enabled]" value="0" />
						<input type="checkbox" id="multch-widget-enabled" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[widget_enabled]" value="1" <?php checked( $widget_on ); ?> />
						<span><?php esc_html_e( 'Show site-wide (wp_footer)', 'multiai-chatbot' ); ?></span>
					</label>
					<?php if ( ! $widget_on ) : ?>
						<p class="description multch-admin-general-notice"><?php esc_html_e( 'While disabled, use the shortcode below to embed the chat on specific pages.', 'multiai-chatbot' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<div class="multch-admin-embed-box">
			<label for="multch-shortcode-display" class="multch-admin-embed-box__label"><?php esc_html_e( 'Embed shortcode', 'multiai-chatbot' ); ?></label>
			<div class="multch-admin-embed-box__row">
				<input type="text" id="multch-shortcode-display" class="large-text code" readonly value="[multch_widget]" />
				<button type="button" class="button button-secondary" id="multch-copy-shortcode"><?php esc_html_e( 'Copy shortcode', 'multiai-chatbot' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Place this shortcode in a page, post, or block where you want the chat to appear.', 'multiai-chatbot' ); ?></p>
		</div>
		<?php
		self::card_close();

		self::card_open(
			__( 'Visitor-facing copy', 'multiai-chatbot' ),
			__( 'Text shown in the widget header and as the first assistant message.', 'multiai-chatbot' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Widget title', 'multiai-chatbot' ); ?></th>
				<td>
					<input
						type="text"
						class="regular-text multch-admin-char-field"
						name="<?php echo esc_attr( self::OPTION_KEY ); ?>[widget_title]"
						id="multch-widget-title"
						value="<?php echo esc_attr( $display_title ); ?>"
						maxlength="<?php echo esc_attr( (string) $limits['widget_title'] ); ?>"
						placeholder="<?php echo esc_attr( (string) $display_defaults['widget_title'] ); ?>"
						data-char-max="<?php echo esc_attr( (string) $limits['widget_title'] ); ?>"
					/>
					<p class="multch-admin-char-count" data-char-for="multch-widget-title" aria-live="polite"></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Subtitle', 'multiai-chatbot' ); ?></th>
				<td>
					<input
						type="text"
						class="regular-text multch-admin-char-field"
						name="<?php echo esc_attr( self::OPTION_KEY ); ?>[widget_subtitle]"
						id="multch-widget-subtitle"
						value="<?php echo esc_attr( $display_subtitle ); ?>"
						maxlength="<?php echo esc_attr( (string) $limits['widget_subtitle'] ); ?>"
						placeholder="<?php echo esc_attr( (string) $display_defaults['widget_subtitle'] ); ?>"
						data-char-max="<?php echo esc_attr( (string) $limits['widget_subtitle'] ); ?>"
					/>
					<p class="multch-admin-char-count" data-char-for="multch-widget-subtitle" aria-live="polite"></p>
					<p class="description"><?php esc_html_e( 'Shown under the title in the chat header (e.g. status line).', 'multiai-chatbot' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Welcome message', 'multiai-chatbot' ); ?></th>
				<td>
					<textarea
						name="<?php echo esc_attr( self::OPTION_KEY ); ?>[welcome_message]"
						id="multch-welcome-message"
						rows="4"
						class="large-text multch-admin-char-field"
						maxlength="<?php echo esc_attr( (string) $limits['welcome_message'] ); ?>"
						placeholder="<?php echo esc_attr( (string) $display_defaults['welcome_message'] ); ?>"
						data-char-max="<?php echo esc_attr( (string) $limits['welcome_message'] ); ?>"
					><?php echo esc_textarea( $display_welcome ); ?></textarea>
					<p class="multch-admin-char-count" data-char-for="multch-welcome-message" aria-live="polite"></p>
					<p class="description"><?php esc_html_e( 'First message visitors see when they open the chat. Visible to everyone.', 'multiai-chatbot' ); ?></p>
					<p class="multch-admin-field-actions">
						<button type="button" class="button button-secondary" id="multch-restore-welcome" data-default="<?php echo esc_attr( (string) $display_defaults['welcome_message'] ); ?>">
							<?php esc_html_e( 'Restore default welcome', 'multiai-chatbot' ); ?>
						</button>
					</p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();

		self::card_open(
			__( 'AI behavior', 'multiai-chatbot' ),
			__( 'Instructions sent to the model with every request. Visitors do not see this text.', 'multiai-chatbot' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'System instructions', 'multiai-chatbot' ); ?></th>
				<td>
					<textarea
						name="<?php echo esc_attr( self::OPTION_KEY ); ?>[system_prompt]"
						id="multch-system-prompt"
						rows="6"
						class="large-text multch-admin-char-field"
						maxlength="<?php echo esc_attr( (string) $limits['system_prompt'] ); ?>"
						placeholder="<?php echo esc_attr( (string) $display_defaults['system_prompt'] ); ?>"
						data-char-max="<?php echo esc_attr( (string) $limits['system_prompt'] ); ?>"
					><?php echo esc_textarea( $display_prompt ); ?></textarea>
					<p class="multch-admin-char-count" data-char-for="multch-system-prompt" aria-live="polite"></p>
					<p class="description">
						<?php esc_html_e( 'Defines tone, scope, and safety. Not shown in the chat UI.', 'multiai-chatbot' ); ?>
						<a href="<?php echo esc_url( $model_url ); ?>"><?php esc_html_e( 'Model and timeout settings', 'multiai-chatbot' ); ?></a>
					</p>
					<p class="multch-admin-field-actions">
						<button type="button" class="button button-secondary" id="multch-restore-system-prompt" data-default="<?php echo esc_attr( (string) $display_defaults['system_prompt'] ); ?>">
							<?php esc_html_e( 'Restore default system prompt', 'multiai-chatbot' ); ?>
						</button>
					</p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();

		self::card_open(
			__( 'Statistics and history', 'multiai-chatbot' ),
			__( 'Optional local storage of chat usage and conversations on your server.', 'multiai-chatbot' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Store statistics and history', 'multiai-chatbot' ); ?></th>
				<td>
					<label class="multch-admin-toggle">
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[stats_history_enabled]" value="0" />
						<input type="checkbox" id="multch-stats-history-enabled" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[stats_history_enabled]" value="1" <?php checked( ! empty( $settings['stats_history_enabled'] ) ); ?> />
						<span><?php esc_html_e( 'Enable usage statistics and conversation history', 'multiai-chatbot' ); ?></span>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, the plugin records anonymous usage statistics (provider, model, latency, errors) and saves chat conversations in your site database. The Statistics and History admin tabs appear. Disabled by default; nothing is stored until you turn this on. Disabling stops new collection; existing records remain until you delete them or uninstall the plugin.', 'multiai-chatbot' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();

		self::card_open(
			__( 'Response delivery', 'multiai-chatbot' ),
			__( 'How assistant replies appear while the model is generating.', 'multiai-chatbot' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Simulated streaming', 'multiai-chatbot' ); ?></th>
				<td>
					<label class="multch-admin-toggle">
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[streaming_enabled]" value="0" />
						<input type="checkbox" id="multch-streaming-enabled" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[streaming_enabled]" value="1" <?php checked( $streaming_on ); ?> />
						<span><?php esc_html_e( 'Enable chunked response', 'multiai-chatbot' ); ?></span>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, the reply is revealed in small chunks for a typing effect. When disabled, the full message appears at once.', 'multiai-chatbot' ); ?>
						<a href="<?php echo esc_url( $model_url ); ?>"><?php esc_html_e( 'Request timeout', 'multiai-chatbot' ); ?></a>
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
			__( 'Preview uses your saved chat style. Edit appearance under Chat style.', 'multiai-chatbot' ),
			$position,
			false
		);
		?>
		</div>
		<?php
	}

	/**
	 * @return list<string>
	 */
	/**
	 * Aviso cuando valores están fijados fuera del panel (p. ej. constantes del servidor).
	 * Sin mencionar wp-config ni nombres de constantes (directrices WordPress.org).
	 */
	private static function render_admin_external_config_notice(): void {
		?>
		<div class="multch-admin-notice multch-admin-notice--warning" role="status">
			<div class="multch-admin-notice__content">
				<strong class="multch-admin-notice__title"><?php esc_html_e( 'Some settings are read-only', 'multiai-chatbot' ); ?></strong>
				<p class="multch-admin-notice__text">
					<?php esc_html_e( 'Your host or server configuration defines some values on this screen. They cannot be changed here; contact your site administrator if you need to update them.', 'multiai-chatbot' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	private static function security_constant_overridden_keys(): array {
		$map = array(
			'allowed_origins'             => array( 'MULTCH_ALLOWED_ORIGINS', 'CHATBOT_ALLOWED_ORIGINS' ),
			'cache_ttl_seconds'           => array( 'MULTCH_CACHE_TTL_SECONDS', 'CHATBOT_CACHE_TTL_SECONDS' ),
			'rate_limit_per_minute'       => array( 'MULTCH_RATE_LIMIT_PER_MINUTE', 'CHATBOT_RATE_LIMIT_PER_MINUTE' ),
			'rate_limit_per_day'          => array( 'MULTCH_RATE_LIMIT_PER_DAY', 'CHATBOT_RATE_LIMIT_PER_DAY' ),
			'rate_limit_model_per_minute' => array( 'MULTCH_RATE_LIMIT_MODEL_PER_MINUTE', 'CHATBOT_RATE_LIMIT_MODEL_PER_MINUTE' ),
			'rate_limit_model_per_day'    => array( 'MULTCH_RATE_LIMIT_MODEL_PER_DAY', 'CHATBOT_RATE_LIMIT_MODEL_PER_DAY' ),
			'rate_limit_soft_threshold'   => array( 'MULTCH_RATE_LIMIT_SOFT_THRESHOLD', 'CHATBOT_RATE_LIMIT_SOFT_THRESHOLD' ),
			'ip_suspend_after_violations' => array( 'MULTCH_IP_SUSPEND_AFTER_VIOLATIONS', 'CHATBOT_IP_SUSPEND_AFTER_VIOLATIONS' ),
			'ip_suspend_seconds'          => array( 'MULTCH_IP_SUSPEND_SECONDS', 'CHATBOT_IP_SUSPEND_SECONDS' ),
			'internal_chat_base_url'      => array( 'MULTCH_INTERNAL_CHAT_BASE_URL', 'CHATBOT_INTERNAL_CHAT_BASE_URL' ),
		);

		$keys = array();
		foreach ( $map as $key => $constants ) {
			if ( '' !== multch_resolve_constant( $constants[0], $constants[1] ) ) {
				$keys[] = $key;
			}
		}

		if ( defined( 'MULTCH_TELEMETRY_FILE_LOG' ) || defined( 'CHATBOT_TELEMETRY_FILE_LOG' ) ) {
			$keys[] = 'telemetry_file_log';
		}

		return $keys;
	}

	private static function format_admin_duration( int $seconds ): string {
		if ( $seconds <= 0 ) {
			return __( 'Disabled', 'multiai-chatbot' );
		}
		if ( $seconds < 3600 ) {
			return sprintf(
				/* translators: %d: number of minutes */
				__( '%d min', 'multiai-chatbot' ),
				(int) round( $seconds / 60 )
			);
		}
		if ( $seconds < 86400 ) {
			$hours   = (int) floor( $seconds / 3600 );
			$minutes = (int) floor( ( $seconds % 3600 ) / 60 );
			if ( $minutes > 0 ) {
				return sprintf(
					/* translators: 1: hours, 2: minutes */
					__( '%1$d h %2$d min', 'multiai-chatbot' ),
					$hours,
					$minutes
				);
			}

			return sprintf(
				/* translators: %d: number of hours */
				__( '%d h', 'multiai-chatbot' ),
				$hours
			);
		}

		$days = (int) floor( $seconds / 86400 );

		return sprintf(
			/* translators: %d: number of days */
			_n( '%d day', '%d days', $days, 'multiai-chatbot' ),
			$days
		);
	}

	/**
	 * @return list<string>
	 */
	private static function parse_origins_list( string $value ): array {
		if ( '' === trim( $value ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $origin ) {
						return trim( (string) $origin );
					},
					explode( ',', $value )
				)
			)
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function render_security_summary_panel( array $settings ): void {
		$site_origin     = esc_url( home_url( '/' ) );
		$origins         = self::parse_origins_list( (string) ( $settings['allowed_origins'] ?? '' ) );
		$cache_ttl       = (int) ( $settings['cache_ttl_seconds'] ?? 0 );
		$stats_enabled   = ! empty( $settings['stats_history_enabled'] );
		$stats_url       = self::build_stats_url( array( 'status' => 'rate_limited', 'paged' => 1 ) );
		$general_url     = admin_url( 'admin.php?page=multch-plugin&tab=general' );
		$log_path        = Multch_Telemetry::get_file_log_path();
		$file_log_on     = ! empty( $settings['telemetry_file_log'] );
		$history_days    = (int) ( $settings['history_retention_days'] ?? 0 );
		$telemetry_days  = (int) ( $settings['telemetry_retention_days'] ?? 0 );
		$suspend_after   = (int) ( $settings['ip_suspend_after_violations'] ?? 3 );
		$suspend_seconds = (int) ( $settings['ip_suspend_seconds'] ?? 900 );
		?>
		<div class="multch-admin-security-sidebar">
			<div class="multch-admin-card multch-admin-security-summary">
				<div class="multch-admin-card__head">
					<h2><?php esc_html_e( 'Protection overview', 'multiai-chatbot' ); ?></h2>
					<p><?php esc_html_e( 'Summary of the active security posture for this site.', 'multiai-chatbot' ); ?></p>
				</div>
				<div class="multch-admin-card__body">
					<dl class="multch-admin-security-summary__list">
						<div class="multch-admin-security-summary__row">
							<dt><?php esc_html_e( 'Endpoint access', 'multiai-chatbot' ); ?></dt>
							<dd>
								<?php if ( empty( $origins ) ) : ?>
									<span class="multch-admin-badge multch-admin-badge--on"><?php esc_html_e( 'This site only', 'multiai-chatbot' ); ?></span>
								<?php else : ?>
									<span class="multch-admin-badge multch-admin-badge--live">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %d: number of allowed origins */
												_n( '%d origin', '%d origins', count( $origins ), 'multiai-chatbot' ),
												count( $origins )
											)
										);
										?>
									</span>
								<?php endif; ?>
							</dd>
						</div>
						<div class="multch-admin-security-summary__row">
							<dt><?php esc_html_e( 'Response cache', 'multiai-chatbot' ); ?></dt>
							<dd id="multch-security-summary-cache"><?php echo esc_html( self::format_admin_duration( $cache_ttl ) ); ?></dd>
						</div>
						<div class="multch-admin-security-summary__row">
							<dt><?php esc_html_e( 'IP suspension', 'multiai-chatbot' ); ?></dt>
							<dd id="multch-security-summary-suspend">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: violations count, 2: suspension duration */
										__( 'After %1$d violations · %2$s', 'multiai-chatbot' ),
										$suspend_after,
										self::format_admin_duration( $suspend_seconds )
									)
								);
								?>
							</dd>
						</div>
						<div class="multch-admin-security-summary__row">
							<dt><?php esc_html_e( 'Statistics & history', 'multiai-chatbot' ); ?></dt>
							<dd>
								<span class="multch-admin-badge <?php echo $stats_enabled ? 'multch-admin-badge--on' : 'multch-admin-badge--off'; ?>">
									<?php
									echo $stats_enabled
										? esc_html__( 'Enabled', 'multiai-chatbot' )
										: esc_html__( 'Disabled', 'multiai-chatbot' );
									?>
								</span>
							</dd>
						</div>
						<?php if ( $stats_enabled ) : ?>
							<div class="multch-admin-security-summary__row">
								<dt><?php esc_html_e( 'Data retention', 'multiai-chatbot' ); ?></dt>
								<dd>
									<?php
									$history_label = $history_days > 0
										? sprintf(
											/* translators: %d: number of days */
											_n( '%d day (history)', '%d days (history)', $history_days, 'multiai-chatbot' ),
											$history_days
										)
										: __( 'History: keep all', 'multiai-chatbot' );
									$telemetry_label = $telemetry_days > 0
										? sprintf(
											/* translators: %d: number of days */
											_n( '%d day (stats)', '%d days (stats)', $telemetry_days, 'multiai-chatbot' ),
											$telemetry_days
										)
										: __( 'Stats: keep all', 'multiai-chatbot' );
									echo esc_html( $history_label . ' · ' . $telemetry_label );
									?>
								</dd>
							</div>
						<?php endif; ?>
						<?php if ( $file_log_on && '' !== $log_path ) : ?>
							<div class="multch-admin-security-summary__row multch-admin-security-summary__row--wide">
								<dt><?php esc_html_e( 'File log', 'multiai-chatbot' ); ?></dt>
								<dd><code class="multch-admin-security-summary__code"><?php echo esc_html( $log_path ); ?></code></dd>
							</div>
						<?php endif; ?>
					</dl>
					<div class="multch-admin-security-summary__links">
						<?php if ( $stats_enabled ) : ?>
							<a class="multch-admin-stats-toolbar__link" href="<?php echo esc_url( $stats_url ); ?>">
								<?php esc_html_e( 'View rate-limited requests', 'multiai-chatbot' ); ?>
							</a>
						<?php else : ?>
							<a class="multch-admin-stats-toolbar__link" href="<?php echo esc_url( $general_url ); ?>">
								<?php esc_html_e( 'Enable statistics under General', 'multiai-chatbot' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="multch-admin-card multch-admin-security-origins-preview">
				<div class="multch-admin-card__head">
					<h2><?php esc_html_e( 'Allowed origins', 'multiai-chatbot' ); ?></h2>
					<p><?php esc_html_e( 'Domains that may call the chat REST endpoint.', 'multiai-chatbot' ); ?></p>
				</div>
				<div class="multch-admin-card__body">
					<div class="multch-admin-origin-chips" id="multch-security-origin-chips" aria-live="polite">
						<?php if ( empty( $origins ) ) : ?>
							<span class="multch-admin-origin-chip multch-admin-origin-chip--default"><?php echo esc_html( $site_origin ); ?></span>
							<p class="description multch-admin-security-origins-preview__hint"><?php esc_html_e( 'Default: only this WordPress site can use the chat API.', 'multiai-chatbot' ); ?></p>
						<?php else : ?>
							<?php foreach ( $origins as $origin ) : ?>
								<span class="multch-admin-origin-chip"><?php echo esc_html( $origin ); ?></span>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
					<div class="multch-admin-origin-box__actions">
						<button type="button" class="button button-secondary button-small" id="multch-copy-site-origin" data-origin="<?php echo esc_attr( $site_origin ); ?>">
							<?php esc_html_e( 'Copy site URL', 'multiai-chatbot' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function render_security_fields( array $settings ): void {
		$site_origin           = esc_url( home_url( '/' ) );
		$cache_ttl             = (int) ( $settings['cache_ttl_seconds'] ?? 1800 );
		$rate_ip_min           = (int) ( $settings['rate_limit_per_minute'] ?? 10 );
		$rate_ip_day           = (int) ( $settings['rate_limit_per_day'] ?? 30 );
		$rate_model_min        = (int) ( $settings['rate_limit_model_per_minute'] ?? 6 );
		$rate_model_day        = (int) ( $settings['rate_limit_model_per_day'] ?? 24 );
		$soft_threshold        = (float) ( $settings['rate_limit_soft_threshold'] ?? 0.8 );
		$suspend_after         = (int) ( $settings['ip_suspend_after_violations'] ?? 3 );
		$suspend_seconds       = (int) ( $settings['ip_suspend_seconds'] ?? 900 );
		$stats_enabled         = ! empty( $settings['stats_history_enabled'] );
		$stats_url             = self::build_stats_url( array( 'status' => 'rate_limited', 'paged' => 1 ) );
		$constant_overrides    = self::security_constant_overridden_keys();
		$log_path              = Multch_Telemetry::get_file_log_path();
		$cache_presets         = array(
			0    => __( 'Off', 'multiai-chatbot' ),
			900  => __( '15 min', 'multiai-chatbot' ),
			1800 => __( '30 min', 'multiai-chatbot' ),
			3600 => __( '1 hour', 'multiai-chatbot' ),
		);
		?>
		<div class="multch-admin-security-toolbar">
			<div class="multch-admin-security-toolbar__intro">
				<h2><?php esc_html_e( 'Endpoint protection', 'multiai-chatbot' ); ?></h2>
				<p><?php esc_html_e( 'Control who can access the chat API, how responses are cached, and how abuse is throttled.', 'multiai-chatbot' ); ?></p>
				<?php if ( $stats_enabled ) : ?>
					<a class="multch-admin-stats-toolbar__link" href="<?php echo esc_url( $stats_url ); ?>">
						<?php esc_html_e( 'Review rate-limited traffic in Statistics', 'multiai-chatbot' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<?php
		if ( ! empty( $constant_overrides ) ) {
			self::render_admin_external_config_notice();
		}
		?>

		<div class="multch-admin-kpi-grid multch-admin-kpi-grid--security">
			<div class="multch-admin-kpi">
				<span class="multch-admin-kpi__label"><?php esc_html_e( 'Cache TTL', 'multiai-chatbot' ); ?></span>
				<span class="multch-admin-kpi__value" id="multch-kpi-cache"><?php echo esc_html( self::format_admin_duration( $cache_ttl ) ); ?></span>
			</div>
			<div class="multch-admin-kpi">
				<span class="multch-admin-kpi__label"><?php esc_html_e( 'Per IP / minute', 'multiai-chatbot' ); ?></span>
				<span class="multch-admin-kpi__value" id="multch-kpi-ip-min"><?php echo esc_html( number_format_i18n( $rate_ip_min ) ); ?></span>
			</div>
			<div class="multch-admin-kpi">
				<span class="multch-admin-kpi__label"><?php esc_html_e( 'Per IP / day', 'multiai-chatbot' ); ?></span>
				<span class="multch-admin-kpi__value" id="multch-kpi-ip-day"><?php echo esc_html( number_format_i18n( $rate_ip_day ) ); ?></span>
			</div>
			<div class="multch-admin-kpi">
				<span class="multch-admin-kpi__label"><?php esc_html_e( 'Model / minute', 'multiai-chatbot' ); ?></span>
				<span class="multch-admin-kpi__value" id="multch-kpi-model-min"><?php echo esc_html( number_format_i18n( $rate_model_min ) ); ?></span>
			</div>
			<div class="multch-admin-kpi">
				<span class="multch-admin-kpi__label"><?php esc_html_e( 'Model / day', 'multiai-chatbot' ); ?></span>
				<span class="multch-admin-kpi__value" id="multch-kpi-model-day"><?php echo esc_html( number_format_i18n( $rate_model_day ) ); ?></span>
			</div>
			<div class="multch-admin-kpi">
				<span class="multch-admin-kpi__label"><?php esc_html_e( 'Soft threshold', 'multiai-chatbot' ); ?></span>
				<span class="multch-admin-kpi__value" id="multch-kpi-soft-threshold"><?php echo esc_html( number_format_i18n( $soft_threshold * 100, 0 ) ); ?>%</span>
			</div>
		</div>

		<div class="multch-admin-layout multch-admin-layout--split">
			<div class="multch-admin-security-fields">
		<?php
		self::card_open(
			__( 'Origins and access', 'multiai-chatbot' ),
			__( 'Control which domains can call the chat endpoint.', 'multiai-chatbot' )
		);
		?>
		<div class="multch-admin-origin-box">
			<label for="multch-allowed-origins" class="multch-admin-origin-box__label"><?php esc_html_e( 'Allowed origins', 'multiai-chatbot' ); ?></label>
			<textarea
				name="<?php echo esc_attr( self::OPTION_KEY ); ?>[allowed_origins]"
				id="multch-allowed-origins"
				rows="3"
				class="large-text code"
				placeholder="<?php echo esc_attr( $site_origin ); ?>"
			><?php echo esc_textarea( (string) ( $settings['allowed_origins'] ?? '' ) ); ?></textarea>
			<p class="description">
				<?php
				printf(
					/* translators: %s: site home URL */
					esc_html__( 'Comma-separated URLs. Leave empty to allow only this site (%s).', 'multiai-chatbot' ),
					esc_html( $site_origin )
				);
				?>
			</p>
		</div>
		<table class="form-table multch-admin-security-form-table" role="presentation">
			<tr>
				<th scope="row"><label for="multch-internal-chat-url"><?php esc_html_e( 'Internal chat URL', 'multiai-chatbot' ); ?></label></th>
				<td>
					<input
						type="url"
						class="regular-text code"
						id="multch-internal-chat-url"
						name="<?php echo esc_attr( self::OPTION_KEY ); ?>[internal_chat_base_url]"
						value="<?php echo esc_attr( (string) ( $settings['internal_chat_base_url'] ?? '' ) ); ?>"
						placeholder="<?php echo esc_attr( untrailingslashit( home_url() ) ); ?>"
					/>
					<p class="description"><?php esc_html_e( 'Optional. Leave empty in most installations. If set, use a local URL (e.g. http://127.0.0.1); do not use the public URL with Cloudflare.', 'multiai-chatbot' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();

		self::card_open(
			__( 'Cache and telemetry', 'multiai-chatbot' ),
			__( 'Reduce repeated model calls and optionally log events to a file.', 'multiai-chatbot' )
		);
		?>
		<div class="multch-admin-security-field-grid multch-admin-security-field-grid--cache">
			<div class="multch-admin-security-field-grid__item">
				<label for="multch-cache-ttl"><?php esc_html_e( 'Cache TTL (seconds)', 'multiai-chatbot' ); ?></label>
				<input
					type="number"
					min="0"
					max="86400"
					id="multch-cache-ttl"
					name="<?php echo esc_attr( self::OPTION_KEY ); ?>[cache_ttl_seconds]"
					value="<?php echo esc_attr( (string) $cache_ttl ); ?>"
					class="small-text"
				/>
				<p class="description" id="multch-cache-ttl-hint"><?php echo esc_html( self::format_admin_duration( $cache_ttl ) ); ?></p>
				<div class="multch-admin-pills multch-admin-pills--cache" role="group" aria-label="<?php esc_attr_e( 'Cache presets', 'multiai-chatbot' ); ?>">
					<?php foreach ( $cache_presets as $seconds => $label ) : ?>
						<button
							type="button"
							class="multch-admin-pills__btn<?php echo (int) $cache_ttl === (int) $seconds ? ' is-active' : ''; ?>"
							data-cache-seconds="<?php echo esc_attr( (string) $seconds ); ?>"
						><?php echo esc_html( $label ); ?></button>
					<?php endforeach; ?>
				</div>
				<p class="description"><?php esc_html_e( 'Enter 0 to disable response caching.', 'multiai-chatbot' ); ?></p>
			</div>
			<div class="multch-admin-security-field-grid__item">
				<span class="multch-admin-security-field-grid__label"><?php esc_html_e( 'Telemetry file log', 'multiai-chatbot' ); ?></span>
				<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[telemetry_file_log]" value="0" />
				<label class="multch-admin-toggle">
					<input
						type="checkbox"
						id="multch-telemetry-file-log"
						name="<?php echo esc_attr( self::OPTION_KEY ); ?>[telemetry_file_log]"
						value="1"
						<?php checked( ! empty( $settings['telemetry_file_log'] ) ); ?>
						<?php disabled( ! $stats_enabled ); ?>
					/>
					<span><?php esc_html_e( 'Also save a copy of events to a log file', 'multiai-chatbot' ); ?></span>
				</label>
				<?php if ( $stats_enabled && '' !== $log_path && ! empty( $settings['telemetry_file_log'] ) ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: path inside the WordPress uploads directory */
							esc_html__( 'Log file: %s', 'multiai-chatbot' ),
							'<code>' . esc_html( $log_path ) . '</code>'
						);
						?>
					</p>
				<?php endif; ?>
				<p class="description">
					<?php if ( $stats_enabled ) : ?>
						<?php esc_html_e( 'Optional. Database statistics use the same preference as “Store statistics and history” in General. Enable this only if you need a JSONL file for external tools.', 'multiai-chatbot' ); ?>
					<?php else : ?>
						<?php
						printf(
							wp_kses(
								/* translators: %s: General settings tab URL. */
								__( 'Turn on <a href="%s">Store statistics and history</a> in General before enabling a file log.', 'multiai-chatbot' ),
								array( 'a' => array( 'href' => array() ) )
							),
							esc_url( admin_url( 'admin.php?page=multch-plugin&tab=general' ) )
						);
						?>
					<?php endif; ?>
				</p>
			</div>
		</div>

		<?php if ( $stats_enabled ) : ?>
			<div class="multch-admin-security-section">
				<h3 class="multch-admin-security-section__title"><?php esc_html_e( 'Data retention', 'multiai-chatbot' ); ?></h3>
				<div class="multch-admin-security-field-grid">
					<div class="multch-admin-security-field-grid__item">
						<label for="multch-history-retention"><?php esc_html_e( 'History retention (days)', 'multiai-chatbot' ); ?></label>
						<input
							type="number"
							min="0"
							max="3650"
							id="multch-history-retention"
							name="<?php echo esc_attr( self::OPTION_KEY ); ?>[history_retention_days]"
							value="<?php echo esc_attr( (string) ( $settings['history_retention_days'] ?? 0 ) ); ?>"
							class="small-text"
						/>
						<p class="description"><?php esc_html_e( '0 = keep indefinitely. Older conversations are purged daily.', 'multiai-chatbot' ); ?></p>
					</div>
					<div class="multch-admin-security-field-grid__item">
						<label for="multch-telemetry-retention"><?php esc_html_e( 'Telemetry retention (days)', 'multiai-chatbot' ); ?></label>
						<input
							type="number"
							min="0"
							max="3650"
							id="multch-telemetry-retention"
							name="<?php echo esc_attr( self::OPTION_KEY ); ?>[telemetry_retention_days]"
							value="<?php echo esc_attr( (string) ( $settings['telemetry_retention_days'] ?? 0 ) ); ?>"
							class="small-text"
						/>
						<p class="description"><?php esc_html_e( '0 = keep indefinitely. Older statistics events are purged daily.', 'multiai-chatbot' ); ?></p>
					</div>
				</div>
			</div>
		<?php else : ?>
			<p class="description multch-admin-security-retention-note">
				<?php
				printf(
					wp_kses(
						/* translators: %s: General settings tab URL. */
						__( 'History and telemetry retention apply when statistics are enabled under <a href="%s">General</a>.', 'multiai-chatbot' ),
						array( 'a' => array( 'href' => array() ) )
					),
					esc_url( admin_url( 'admin.php?page=multch-plugin&tab=general' ) )
				);
				?>
			</p>
			<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[history_retention_days]" value="<?php echo esc_attr( (string) ( $settings['history_retention_days'] ?? 0 ) ); ?>" />
			<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[telemetry_retention_days]" value="<?php echo esc_attr( (string) ( $settings['telemetry_retention_days'] ?? 0 ) ); ?>" />
		<?php endif; ?>
		<?php
		self::card_close();

		self::card_open(
			__( 'Rate limits', 'multiai-chatbot' ),
			__( 'Protect the endpoint and AI provider quota from abuse.', 'multiai-chatbot' )
		);
		?>
		<div class="multch-admin-security-section">
			<h3 class="multch-admin-security-section__title"><?php esc_html_e( 'Per visitor (IP)', 'multiai-chatbot' ); ?></h3>
			<div class="multch-admin-security-field-grid">
				<div class="multch-admin-security-field-grid__item">
					<label for="multch-rate-limit-per-minute"><?php esc_html_e( 'Requests / minute', 'multiai-chatbot' ); ?></label>
					<input
						type="number"
						min="1"
						max="120"
						id="multch-rate-limit-per-minute"
						name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rate_limit_per_minute]"
						value="<?php echo esc_attr( (string) $rate_ip_min ); ?>"
						class="small-text"
					/>
					<p class="description"><?php esc_html_e( 'Maximum chat requests per visitor IP each minute.', 'multiai-chatbot' ); ?></p>
				</div>
				<div class="multch-admin-security-field-grid__item">
					<label for="multch-rate-limit-per-day"><?php esc_html_e( 'Requests / day', 'multiai-chatbot' ); ?></label>
					<input
						type="number"
						min="1"
						max="1000"
						id="multch-rate-limit-per-day"
						name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rate_limit_per_day]"
						value="<?php echo esc_attr( (string) $rate_ip_day ); ?>"
						class="small-text"
					/>
					<p class="description"><?php esc_html_e( 'Maximum chat requests per visitor IP per day.', 'multiai-chatbot' ); ?></p>
				</div>
			</div>
		</div>

		<div class="multch-admin-security-section">
			<h3 class="multch-admin-security-section__title"><?php esc_html_e( 'Global model quota', 'multiai-chatbot' ); ?></h3>
			<div class="multch-admin-security-field-grid">
				<div class="multch-admin-security-field-grid__item">
					<label for="multch-rate-limit-model-per-minute"><?php esc_html_e( 'Calls / minute', 'multiai-chatbot' ); ?></label>
					<input
						type="number"
						min="1"
						max="120"
						id="multch-rate-limit-model-per-minute"
						name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rate_limit_model_per_minute]"
						value="<?php echo esc_attr( (string) $rate_model_min ); ?>"
						class="small-text"
					/>
					<p class="description"><?php esc_html_e( 'Maximum AI calls shared across all visitors each minute.', 'multiai-chatbot' ); ?></p>
				</div>
				<div class="multch-admin-security-field-grid__item">
					<label for="multch-rate-limit-model-per-day"><?php esc_html_e( 'Calls / day', 'multiai-chatbot' ); ?></label>
					<input
						type="number"
						min="1"
						max="5000"
						id="multch-rate-limit-model-per-day"
						name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rate_limit_model_per_day]"
						value="<?php echo esc_attr( (string) $rate_model_day ); ?>"
						class="small-text"
					/>
					<p class="description"><?php esc_html_e( 'Maximum AI calls shared across all visitors per day.', 'multiai-chatbot' ); ?></p>
				</div>
			</div>
		</div>

		<div class="multch-admin-security-section">
			<h3 class="multch-admin-security-section__title"><?php esc_html_e( 'Abuse response', 'multiai-chatbot' ); ?></h3>
			<div class="multch-admin-security-field-grid multch-admin-security-field-grid--abuse">
				<div class="multch-admin-security-field-grid__item">
					<label for="multch-rate-limit-soft-threshold"><?php esc_html_e( 'Soft threshold', 'multiai-chatbot' ); ?></label>
					<input
						type="number"
						min="0.1"
						max="1"
						step="0.05"
						id="multch-rate-limit-soft-threshold"
						name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rate_limit_soft_threshold]"
						value="<?php echo esc_attr( (string) $soft_threshold ); ?>"
						class="small-text"
					/>
					<p class="description"><?php esc_html_e( 'Fraction of the limit (0.1–1) at which a warning is logged.', 'multiai-chatbot' ); ?></p>
				</div>
				<div class="multch-admin-security-field-grid__item">
					<label for="multch-ip-suspend-after"><?php esc_html_e( 'Suspend IP after violations', 'multiai-chatbot' ); ?></label>
					<input
						type="number"
						min="1"
						max="20"
						id="multch-ip-suspend-after"
						name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ip_suspend_after_violations]"
						value="<?php echo esc_attr( (string) $suspend_after ); ?>"
						class="small-text"
					/>
					<p class="description"><?php esc_html_e( 'How many limit violations before the visitor IP is temporarily blocked.', 'multiai-chatbot' ); ?></p>
				</div>
				<div class="multch-admin-security-field-grid__item">
					<label for="multch-ip-suspend-seconds"><?php esc_html_e( 'Suspension duration (sec)', 'multiai-chatbot' ); ?></label>
					<input
						type="number"
						min="60"
						max="86400"
						id="multch-ip-suspend-seconds"
						name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ip_suspend_seconds]"
						value="<?php echo esc_attr( (string) $suspend_seconds ); ?>"
						class="small-text"
					/>
					<p class="description" id="multch-ip-suspend-hint"><?php echo esc_html( self::format_admin_duration( $suspend_seconds ) ); ?></p>
				</div>
			</div>
		</div>
		<?php
		self::card_close();
		?>
			</div>
		<?php
		self::render_security_summary_panel( $settings );
		?>
		</div>
		<?php
	}

	/**
	 * @param array{connectors: list<array<string, string>>, models: list<array<string, string>>, has_connected: bool, client_available: bool} $ai_state
	 */
	private static function render_connectors_status_panel( array $ai_state ): void {
		$connectors_url = multch_connectors_admin_url();
		$refresh_url    = add_query_arg(
			array(
				'page'                   => 'multch-plugin',
				'tab'                    => 'model',
				'multch_refresh_models'  => '1',
			),
			admin_url( 'admin.php' )
		);

		if ( empty( $ai_state['client_available'] ) ) {
			echo '<p class="description">' . esc_html(
				sprintf(
					/* translators: %s: WordPress version number. */
					__( 'WordPress %s or newer is required for cloud AI via Connectors. You can still use Ollama below.', 'multiai-chatbot' ),
					'7.0'
				)
			) . '</p>';
			return;
		}

		if ( empty( $ai_state['connectors'] ) ) {
			echo '<p class="description">';
			printf(
				wp_kses(
					/* translators: %s: Settings → Connectors URL. */
					__( 'No AI connectors are registered yet. Open <a href="%s">Settings → Connectors</a> to add a provider.', 'multiai-chatbot' ),
					array( 'a' => array( 'href' => array() ) )
				),
				esc_url( $connectors_url )
			);
			echo '</p>';
			return;
		}

		echo '<div class="multch-connectors-panel">';
		foreach ( $ai_state['connectors'] as $connector ) {
			$status = (string) ( $connector['status'] ?? '' );
			$badge  = 'multch-connectors-panel__badge--' . sanitize_html_class( $status );
			echo '<div class="multch-connectors-panel__card">';
			if ( ! empty( $connector['logo_url'] ) ) {
				echo '<img class="multch-connectors-panel__logo" src="' . esc_url( (string) $connector['logo_url'] ) . '" alt="" width="32" height="32" />';
			}
			echo '<div class="multch-connectors-panel__body">';
			echo '<strong class="multch-connectors-panel__name">' . esc_html( (string) $connector['name'] ) . '</strong>';
			$description = multch_localize_connector_description(
				(string) ( $connector['id'] ?? '' ),
				(string) ( $connector['description'] ?? '' )
			);
			if ( '' !== $description ) {
				echo '<p class="multch-connectors-panel__desc">' . esc_html( $description ) . '</p>';
			}
			echo '<span class="multch-connectors-panel__badge ' . esc_attr( $badge ) . '">' . esc_html( (string) ( $connector['status_label'] ?? '' ) ) . '</span>';
			echo '</div></div>';
		}
		echo '</div>';

		echo '<p class="description multch-connectors-panel__actions">';
		printf(
			wp_kses(
				/* translators: 1: Connectors settings URL, 2: refresh models URL. */
				__( 'Manage keys in <a href="%1$s">Settings → Connectors</a>. <a href="%2$s">Refresh model list</a>.', 'multiai-chatbot' ),
				array( 'a' => array( 'href' => array() ) )
			),
			esc_url( $connectors_url ),
			esc_url( $refresh_url )
		);
		echo '</p>';

		if ( empty( $ai_state['has_connected'] ) ) {
			echo '<p class="description"><em>' . esc_html__( 'Connect at least one provider to enable cloud chat models.', 'multiai-chatbot' ) . '</em></p>';
		}
	}

	/**
	 * @param array{connectors: list<array<string, string>>, models: list<array<string, string>>, has_connected: bool, client_available: bool} $ai_state
	 * @param array{
	 *     name?: string,
	 *     id?: string,
	 *     enabled?: bool,
	 *     allow_automatic?: bool,
	 *     empty_label?: string
	 * } $args
	 */
	private static function render_model_picker( array $ai_state, string $current_model, array $args = array() ): void {
		$args = wp_parse_args(
			$args,
			array(
				'name'             => self::OPTION_KEY . '[model]',
				'id'               => 'multch-model',
				'enabled'          => true,
				'allow_automatic'  => true,
				'empty_label'      => __( '— Automatic (first available) —', 'multiai-chatbot' ),
			)
		);

		$name    = (string) $args['name'];
		$id      = (string) $args['id'];
		$enabled = (bool) $args['enabled'];
		$models  = is_array( $ai_state['models'] ?? null ) ? $ai_state['models'] : array();

		if ( empty( $models ) ) {
			printf(
				'<input type="text" class="regular-text" name="%1$s" id="%2$s" value="%3$s" autocomplete="off"%4$s />',
				esc_attr( $name ),
				esc_attr( $id ),
				esc_attr( $current_model ),
				$enabled ? '' : ' disabled="disabled"'
			);
			if ( ! empty( $ai_state['has_connected'] ) ) {
				echo '<p class="description">' . esc_html__( 'No models were returned yet. Enter a model ID manually or refresh the list after saving your connector.', 'multiai-chatbot' ) . '</p>';
			}
			return;
		}

		$by_provider = array();
		foreach ( $models as $row ) {
			$pid = (string) ( $row['provider_id'] ?? 'other' );
			if ( ! isset( $by_provider[ $pid ] ) ) {
				$by_provider[ $pid ] = array(
					'label'  => (string) ( $row['provider_name'] ?? $pid ),
					'models' => array(),
				);
			}
			$by_provider[ $pid ]['models'][] = $row;
		}

		echo '<select class="regular-text" name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '"' . ( $enabled ? '' : ' disabled="disabled"' ) . '>';

		printf(
			'<option value="">%s</option>',
			esc_html( (string) $args['empty_label'] )
		);

		$known_ids = array();
		foreach ( $by_provider as $group ) {
			echo '<optgroup label="' . esc_attr( (string) $group['label'] ) . '">';
			foreach ( $group['models'] as $row ) {
				$model_id = (string) ( $row['id'] ?? '' );
				if ( '' === $model_id ) {
					continue;
				}
				$known_ids[] = $model_id;
				$label       = (string) ( $row['name'] ?? $model_id );
				printf(
					'<option value="%1$s" %2$s>%3$s</option>',
					esc_attr( $model_id ),
					selected( $current_model, $model_id, false ),
					esc_html( $label . ' (' . $model_id . ')' )
				);
			}
			echo '</optgroup>';
		}

		if ( '' !== $current_model && ! in_array( $current_model, $known_ids, true ) ) {
			printf(
				'<option value="%1$s" selected>%2$s</option>',
				esc_attr( $current_model ),
				esc_html(
					sprintf(
						/* translators: %s: model ID saved in settings but not in the current connector list */
						__( 'Current: %s (not in list)', 'multiai-chatbot' ),
						$current_model
					)
				)
			);
		}

		echo '</select>';
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function render_model_fields( array $settings ): void {
		$provider = (string) ( $settings['provider'] ?? 'wordpress_ai' );
		if ( in_array( $provider, multch_legacy_cloud_provider_ids(), true ) ) {
			$provider = 'wordpress_ai';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Refresh link only; form save uses options.php nonce.
		$refresh_models = isset( $_GET['multch_refresh_models'] ) && '1' === (string) $_GET['multch_refresh_models'];
		$ai_state       = multch_get_ai_connectors_admin_state( $refresh_models );
		$current_model    = (string) ( $settings['model'] ?? '' );
		$fallback_model   = multch_ai_client_fallback_model( $settings );
		$wp_models_active = 'wordpress_ai' === $provider;
		$candidates_raw        = (string) ( $settings['model_candidates'] ?? '' );
		$legacy_multi          = str_contains( $candidates_raw, ',' );
		$provider_descriptions = self::admin_model_provider_descriptions();

		self::card_open(
			__( 'AI provider', 'multiai-chatbot' ),
			__( 'Use the WordPress AI Client (site-wide Connectors) or a local Ollama server.', 'multiai-chatbot' )
		);

		$constant_overrides = multch_ai_client_constant_overridden_keys();
		if ( ! empty( $constant_overrides ) ) {
			self::render_admin_external_config_notice();
		}
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Provider', 'multiai-chatbot' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[provider]" id="multch-provider">
						<option value="wordpress_ai" <?php selected( $provider, 'wordpress_ai' ); ?>><?php esc_html_e( 'WordPress AI (Connectors)', 'multiai-chatbot' ); ?></option>
						<option value="ollama" <?php selected( $provider, 'ollama' ); ?>>Ollama</option>
					</select>
				</td>
			</tr>
			<tr class="multch-field-wordpress-ai">
				<th scope="row"><?php esc_html_e( 'Site connectors', 'multiai-chatbot' ); ?></th>
				<td>
					<?php self::render_connectors_status_panel( $ai_state ); ?>
				</td>
			</tr>
			<tr class="multch-field-wordpress-ai">
				<th scope="row"><?php esc_html_e( 'Primary model', 'multiai-chatbot' ); ?></th>
				<td>
					<?php
					self::render_model_picker(
						$ai_state,
						$current_model,
						array(
							'enabled'         => $wp_models_active,
							'allow_automatic' => true,
						)
					);
					?>
					<p class="description" id="multch-model-desc"><?php echo esc_html( (string) ( $provider_descriptions['wordpress_ai']['model'] ?? '' ) ); ?></p>
				</td>
			</tr>
			<tr class="multch-field-wordpress-ai">
				<th scope="row"><?php esc_html_e( 'Fallback model', 'multiai-chatbot' ); ?></th>
				<td>
					<?php
					self::render_model_picker(
						$ai_state,
						$fallback_model,
						array(
							'name'            => self::OPTION_KEY . '[model_fallback]',
							'id'              => 'multch-model-fallback',
							'enabled'         => $wp_models_active,
							'allow_automatic' => true,
							'empty_label'     => __( '— None —', 'multiai-chatbot' ),
						)
					);
					?>
					<p class="description" id="multch-model-candidates-desc"><?php echo esc_html( (string) ( $provider_descriptions['wordpress_ai']['candidates'] ?? '' ) ); ?></p>
					<?php if ( $legacy_multi ) : ?>
						<p class="description">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: comma-separated legacy fallback model IDs */
									__( 'Previously saved fallbacks: %s. Saving this tab keeps only the fallback selected above.', 'multiai-chatbot' ),
									$candidates_raw
								)
							);
							?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr class="multch-field-wordpress-ai">
				<th scope="row"><?php esc_html_e( 'Google automatic fallback', 'multiai-chatbot' ); ?></th>
				<td>
					<label>
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[allow_google_any_model]" value="0" />
						<input
							type="checkbox"
							name="<?php echo esc_attr( self::OPTION_KEY ); ?>[allow_google_any_model]"
							value="1"
							<?php checked( ! empty( $settings['allow_google_any_model'] ) ); ?>
							<?php disabled( ! $wp_models_active ); ?>
						/>
						<?php esc_html_e( 'Allow Google to answer with any available text model if the primary and fallback models fail (quota, rate limits, or unavailability).', 'multiai-chatbot' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Order: (1) primary model, (2) fallback model, (3) whatever text model Google returns. The model shown in chat and statistics will reflect the model actually used.', 'multiai-chatbot' ); ?>
					</p>
				</td>
			</tr>
			<tr class="multch-field-ollama">
				<th scope="row"><?php esc_html_e( 'Model', 'multiai-chatbot' ); ?></th>
				<td>
					<input
						type="text"
						class="regular-text"
						name="<?php echo esc_attr( self::OPTION_KEY ); ?>[model]"
						id="multch-model-ollama"
						value="<?php echo esc_attr( $current_model ); ?>"
						<?php disabled( 'ollama' !== $provider ); ?>
					/>
					<p class="description"><?php esc_html_e( 'Ollama model name installed on your server (e.g. llama3).', 'multiai-chatbot' ); ?></p>
				</td>
			</tr>
			<tr class="multch-field-ollama">
				<th scope="row"><?php esc_html_e( 'Ollama base URL', 'multiai-chatbot' ); ?></th>
				<td>
					<input type="url" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ollama_base_url]" value="<?php echo esc_attr( (string) $settings['ollama_base_url'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Local Ollama server reachable from this WordPress host (default: http://127.0.0.1:11434).', 'multiai-chatbot' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Timeout (seconds)', 'multiai-chatbot' ); ?></th>
				<td>
					<input type="number" min="5" max="120" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[request_timeout]" value="<?php echo esc_attr( (string) $settings['request_timeout'] ); ?>" />
				</td>
			</tr>
		</table>
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
		<div class="multch-admin-layout multch-admin-layout--split">
			<div class="multch-admin-style-fields">
		<?php
		self::card_open(
			__( 'Visual theme', 'multiai-chatbot' ),
			__( 'Color palette and shapes. Typography is configured under Colors and shape.', 'multiai-chatbot' )
		);
		?>
		<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_preset]" id="multch-style-preset" value="<?php echo esc_attr( $preset ); ?>" />
		<div class="multch-theme-grid" role="radiogroup" aria-label="<?php esc_attr_e( 'Theme', 'multiai-chatbot' ); ?>">
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
					class="multch-theme-card<?php echo $preset === $id ? ' is-active' : ''; ?>"
					data-preset="<?php echo esc_attr( $id ); ?>"
					role="radio"
					aria-checked="<?php echo $preset === $id ? 'true' : 'false'; ?>"
					aria-label="<?php echo esc_attr( (string) ( $meta['label'] ?? $id ) ); ?>">
					<span class="multch-theme-card__swatches" aria-hidden="true">
						<?php foreach ( array_slice( (array) $colors, 0, 3 ) as $color ) : ?>
							<span class="multch-theme-card__swatch" style="background:<?php echo esc_attr( (string) $color ); ?>"></span>
						<?php endforeach; ?>
					</span>
					<span class="multch-theme-card__label"><?php echo esc_html( (string) ( $meta['label'] ?? $id ) ); ?></span>
					<?php if ( $badge !== '' ) : ?>
						<span class="multch-theme-card__badge multch-theme-card__badge--<?php echo esc_attr( $badge_type ); ?>"><?php echo esc_html( $badge ); ?></span>
					<?php endif; ?>
				</button>
			<?php endforeach; ?>
		</div>
		<p class="description" id="multch-style-preset-desc">
			<?php
			$current_meta = $preset_meta[ $preset ] ?? array( 'desc' => '' );
			echo esc_html( (string) ( $current_meta['desc'] ?? '' ) );
			?>
		</p>
		<?php
		self::card_close();

		self::card_open(
			__( 'Colors and shape', 'multiai-chatbot' ),
			__( 'Optional: override the selected preset.', 'multiai-chatbot' )
		);
		?>
		<p class="multch-style-actions">
			<button type="button" class="button button-secondary" id="multch-style-reset-overrides"><?php esc_html_e( 'Reset color overrides', 'multiai-chatbot' ); ?></button>
			<button type="button" class="button button-secondary" id="multch-style-export"><?php esc_html_e( 'Export theme', 'multiai-chatbot' ); ?></button>
			<button type="button" class="button button-secondary" id="multch-style-import"><?php esc_html_e( 'Import theme', 'multiai-chatbot' ); ?></button>
			<input type="file" id="multch-style-import-file" accept="application/json,.json" hidden />
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Primary color', 'multiai-chatbot' ); ?></th>
				<td>
					<input type="text" class="multch-color-picker" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_primary]" value="<?php echo esc_attr( (string) $settings['style_primary'] ); ?>" placeholder="#2563eb" data-default-color="#2563eb" />
					<p class="description"><?php esc_html_e( 'Send button, user bubbles, and accents.', 'multiai-chatbot' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Accent color', 'multiai-chatbot' ); ?></th>
				<td>
					<input type="text" class="multch-color-picker" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_accent]" value="<?php echo esc_attr( (string) $settings['style_accent'] ); ?>" placeholder="#7c3aed" data-default-color="#7c3aed" />
					<p class="description"><?php esc_html_e( 'Floating button gradient.', 'multiai-chatbot' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Background color', 'multiai-chatbot' ); ?></th>
				<td>
					<input type="text" class="multch-color-picker" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_bg]" value="<?php echo esc_attr( (string) ( $settings['style_bg'] ?? '' ) ); ?>" placeholder="" data-default-color="" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Text color', 'multiai-chatbot' ); ?></th>
				<td>
					<input type="text" class="multch-color-picker" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_fg]" value="<?php echo esc_attr( (string) ( $settings['style_fg'] ?? '' ) ); ?>" placeholder="" data-default-color="" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Font', 'multiai-chatbot' ); ?></th>
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
				<th scope="row"><?php esc_html_e( 'Border radius', 'multiai-chatbot' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_radius]" value="<?php echo esc_attr( (string) $settings['style_radius'] ); ?>" placeholder="1.5rem" class="regular-text" />
					<p class="description"><?php esc_html_e( 'E.g.: 0.75rem, 1.5rem, 16px', 'multiai-chatbot' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Panel width', 'multiai-chatbot' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_panel_width]" value="<?php echo esc_attr( (string) ( $settings['style_panel_width'] ?? '' ) ); ?>" placeholder="380px" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Empty = responsive width (max. 380px).', 'multiai-chatbot' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Panel max height', 'multiai-chatbot' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_panel_max_height]" value="<?php echo esc_attr( (string) ( $settings['style_panel_max_height'] ?? '' ) ); ?>" placeholder="70vh" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Limits the message area height. E.g.: 60vh, 480px', 'multiai-chatbot' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Stack order (z-index)', 'multiai-chatbot' ); ?></th>
				<td>
					<input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_z_index]" value="<?php echo esc_attr( (string) (int) ( $settings['style_z_index'] ?? 0 ) ); ?>" min="0" max="2147483646" step="1" class="small-text" />
					<p class="description"><?php esc_html_e( '0 = default. Raise if another plugin covers the chat.', 'multiai-chatbot' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();

		self::card_open(
			__( 'Motion and automatic theme', 'multiai-chatbot' ),
			__( 'Accessibility and system appearance.', 'multiai-chatbot' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Reduce motion', 'multiai-chatbot' ); ?></th>
				<td>
					<label class="multch-admin-toggle">
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_reduce_motion]" value="0" />
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_reduce_motion]" value="1" <?php checked( ! empty( $settings['style_reduce_motion'] ) ); ?> />
						<span><?php esc_html_e( 'Disable launcher pulse animation', 'multiai-chatbot' ); ?></span>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Match system theme', 'multiai-chatbot' ); ?></th>
				<td>
					<label class="multch-admin-toggle">
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_preset_auto]" value="0" />
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_preset_auto]" value="1" id="multch-style-preset-auto" <?php checked( ! empty( $settings['style_preset_auto'] ) ); ?> />
						<span><?php esc_html_e( 'Use dark preset when the visitor prefers dark mode', 'multiai-chatbot' ); ?></span>
					</label>
					<p class="description"><?php esc_html_e( 'Light mode uses the theme selected above.', 'multiai-chatbot' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Dark mode preset', 'multiai-chatbot' ); ?></th>
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
				<th scope="row"><?php esc_html_e( 'Custom CSS', 'multiai-chatbot' ); ?></th>
				<td>
					<textarea name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_custom_css]" rows="6" class="large-text code" placeholder="#multch-plugin-root .maicb-send { }"><?php echo esc_textarea( (string) ( $settings['style_custom_css'] ?? '' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Scoped to the widget root. No @import. Max 8000 characters.', 'multiai-chatbot' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();

		self::card_open(
			__( 'Screen position', 'multiai-chatbot' ),
			__( 'Where the panel and floating button appear on the site.', 'multiai-chatbot' )
		);
		?>
		<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_position]" value="<?php echo esc_attr( $position ); ?>" id="multch-style-position-input" />
		<div class="multch-position-picker">
			<div class="multch-position-map" role="group" aria-label="<?php esc_attr_e( 'Widget position', 'multiai-chatbot' ); ?>">
				<?php foreach ( self::style_positions() as $pos ) : ?>
					<button type="button"
						class="multch-position-btn<?php echo $position === $pos ? ' is-active' : ''; ?>"
						data-position="<?php echo esc_attr( $pos ); ?>"
						title="<?php echo esc_attr( $position_labels[ $pos ] ?? $pos ); ?>">
						<span class="screen-reader-text"><?php echo esc_html( $position_labels[ $pos ] ?? $pos ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<p class="multch-position-label" id="multch-position-label"><?php echo esc_html( $position_labels[ $position ] ?? $position ); ?></p>
			<p class="description multch-position-picker__hint">
				<?php esc_html_e( 'The preview closes the panel when you change position so you can see where the floating button will sit. Use “Open panel” to preview the chat window.', 'multiai-chatbot' ); ?>
			</p>
		</div>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Edge margin', 'multiai-chatbot' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_offset]" value="<?php echo esc_attr( (string) ( $settings['style_offset'] ?? '1rem' ) ); ?>" placeholder="1rem" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Floating button text', 'multiai-chatbot' ); ?></th>
				<td>
					<label class="multch-admin-toggle">
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_launcher_label]" value="0" />
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_launcher_label]" value="1" <?php checked( ! empty( $settings['style_launcher_label'] ) ); ?> />
						<span><?php esc_html_e( 'Show title next to the 💬 icon', 'multiai-chatbot' ); ?></span>
					</label>
					<p class="description"><?php esc_html_e( 'The title is configured under General → Widget header.', 'multiai-chatbot' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();

		self::card_open(
			__( 'Message labels', 'multiai-chatbot' ),
			__( 'Optional labels under assistant messages in the visitor chat only.', 'multiai-chatbot' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Welcome message label', 'multiai-chatbot' ); ?></th>
				<td>
					<label class="multch-admin-toggle">
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_show_welcome_label]" value="0" />
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_show_welcome_label]" value="1" <?php checked( ! empty( $settings['style_show_welcome_label'] ) ); ?> />
						<span><?php esc_html_e( 'Show “Welcome message” under the first assistant reply', 'multiai-chatbot' ); ?></span>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Model label', 'multiai-chatbot' ); ?></th>
				<td>
					<label class="multch-admin-toggle">
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_show_model_label]" value="0" />
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_show_model_label]" value="1" <?php checked( ! empty( $settings['style_show_model_label'] ) ); ?> />
						<span><?php esc_html_e( 'Show which AI model answered each reply', 'multiai-chatbot' ); ?></span>
					</label>
					<p class="description"><?php esc_html_e( 'These options only affect the public chat widget. Statistics and conversation history in the admin are unchanged.', 'multiai-chatbot' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();

		self::card_open(
			__( 'Developer credit', 'multiai-chatbot' ),
			__( 'Optional attribution shown inside the chat panel.', 'multiai-chatbot' ),
			'multch-admin-card--developer-credit',
			__( 'Optional', 'multiai-chatbot' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Show in chat', 'multiai-chatbot' ); ?></th>
				<td>
					<label class="multch-admin-toggle">
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_show_credit]" value="0" />
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_show_credit]" value="1" <?php checked( ! empty( $settings['style_show_credit'] ) ); ?> />
						<span><?php esc_html_e( 'Show developer credit in chat', 'multiai-chatbot' ); ?></span>
					</label>
					<p class="description"><?php esc_html_e( 'Adds a small line below the message box with the plugin name and a link. Off by default.', 'multiai-chatbot' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();
		?>
			</div>
		<?php
		self::render_content_preview_panel(
			__( 'The preview reflects theme, position, and styles instantly. Save to apply them on the public site.', 'multiai-chatbot' ),
			$position,
			true
		);
		?>
		</div>
		<?php
	}


	private static function get_stats_filters_from_request(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin filter GET params.
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
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * @param array<string, mixed> $query_args
	 */
	private static function build_stats_url( array $query_args ): string {
		$args = array_merge(
			array(
				'page' => 'multch-plugin',
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

		echo '<nav class="multch-admin-tablenav multch-admin-tablenav--stats" aria-label="' . esc_attr__( 'Pagination', 'multiai-chatbot' ) . '">';

		if ( $page > 1 ) {
			$prev_args          = $base_args;
			$prev_args['paged'] = $page - 1;
			echo '<a class="multch-admin-tablenav__prev" href="' . esc_url( self::build_stats_url( $prev_args ) ) . '">' . esc_html__( 'Previous', 'multiai-chatbot' ) . '</a>';
		}

		echo '<span class="multch-admin-tablenav__status">';
		echo esc_html(
			sprintf(
				/* translators: 1: current page, 2: total pages */
				__( 'Page %1$d of %2$d', 'multiai-chatbot' ),
				$page,
				$pages
			)
		);
		echo '</span>';

		$window = 5;
		$start  = max( 1, $page - $window );
		$end    = min( $pages, $page + $window );

		echo '<span class="multch-admin-tablenav__pages">';
		for ( $i = $start; $i <= $end; $i++ ) {
			$page_args          = $base_args;
			$page_args['paged'] = $i;
			echo '<a href="' . esc_url( self::build_stats_url( $page_args ) ) . '" class="' . esc_attr( $page === $i ? 'is-active' : '' ) . '">' . esc_html( (string) $i ) . '</a>';
		}
		echo '</span>';

		if ( $page < $pages ) {
			$next_args          = $base_args;
			$next_args['paged'] = $page + 1;
			echo '<a class="multch-admin-tablenav__next" href="' . esc_url( self::build_stats_url( $next_args ) ) . '">' . esc_html__( 'Next', 'multiai-chatbot' ) . '</a>';
		}

		echo '</nav>';
	}

	private static function format_telemetry_status_label( string $status ): string {
		$labels = array(
			'success'         => __( 'Success', 'multiai-chatbot' ),
			'cached'          => __( 'Cached', 'multiai-chatbot' ),
			'error'           => __( 'Error', 'multiai-chatbot' ),
			'rate_limited'    => __( 'Rate limited', 'multiai-chatbot' ),
			'config_error'    => __( 'Configuration error', 'multiai-chatbot' ),
			'invalid_request' => __( 'Invalid request', 'multiai-chatbot' ),
			'ok'              => __( 'OK', 'multiai-chatbot' ),
		);

		return $labels[ $status ] ?? $status;
	}

	private static function format_telemetry_status_class( string $status ): string {
		if ( in_array( $status, array( 'success', 'ok', 'cached' ), true ) ) {
			return 'cached' === $status ? 'multch-admin-status--cached' : 'multch-admin-status--ok';
		}
		return 'multch-admin-status--err';
	}

	/**
	 * @param array<string, mixed> $event
	 */
	private static function format_telemetry_model_label( array $event ): string {
		$model = (string) ( $event['model'] ?? '' );
		if ( '' === $model ) {
			return '—';
		}

		return multch_format_model_display(
			$model,
			(string) ( $event['model_primary'] ?? '' ),
			! empty( $event['used_fallback'] )
		);
	}

	private static function format_error_code_label( string $code ): string {
		$labels = array(
			'RATE_LIMIT_GENERAL'   => __( 'General limit', 'multiai-chatbot' ),
			'RATE_LIMIT_MODEL'     => __( 'Model limit', 'multiai-chatbot' ),
			'INVALID_REQUEST'      => __( 'Invalid request', 'multiai-chatbot' ),
			'CONFIGURATION_ERROR'  => __( 'Configuration error', 'multiai-chatbot' ),
			'SERVER_ERROR'         => __( 'Server error', 'multiai-chatbot' ),
			'MODEL_NOT_ALLOWED'       => __( 'Model not allowed', 'multiai-chatbot' ),
			'MODEL_SUBSTITUTED'       => __( 'Model substituted', 'multiai-chatbot' ),
			'MODEL_FALLBACK_MISMATCH' => __( 'Fallback model mismatch', 'multiai-chatbot' ),
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

		return Multch_Chat_History::get_public_ids_by_conversation_ids( $ids );
	}

	private static function render_stats_tab(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin list/filter GET params.
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

		$summary      = Multch_Telemetry::get_summary( $filters );
		$daily_series = Multch_Telemetry::get_daily_series( $filters );
		$events       = Multch_Telemetry::list_events( $query_args );
		$total        = Multch_Telemetry::count_events( $filters );
		$pages        = (int) ceil( $total / $per );
		$models       = Multch_Telemetry::get_distinct_models( $filters );
		$error_codes  = Multch_Telemetry::get_distinct_error_codes( $filters );
		$conv_map     = self::map_conversation_public_ids( $events );

		$settings  = Multch_Plugin::get_settings();
		$retention = (int) ( $settings['telemetry_retention_days'] ?? 0 );
		$has_filters = self::stats_has_active_filters( $filters );
		$totals    = $summary['totals'] ?? array();

		$periods = array(
			0   => __( 'All', 'multiai-chatbot' ),
			7   => __( '7 days', 'multiai-chatbot' ),
			30  => __( '30 days', 'multiai-chatbot' ),
			90  => __( '90 days', 'multiai-chatbot' ),
			365 => __( '365 days', 'multiai-chatbot' ),
		);

		$export_url = wp_nonce_url(
			add_query_arg(
				array_merge(
					array( 'action' => 'multch_export_csv' ),
					$filters
				),
				admin_url( 'admin-post.php' )
			),
			'multch_export_csv'
		);

		$purge_url = '';
		if ( $retention > 0 ) {
			$purge_url = wp_nonce_url(
				add_query_arg( 'action', 'multch_purge_telemetry', admin_url( 'admin-post.php' ) ),
				'multch_purge_telemetry'
			);
		}

		$max_daily = 0;
		foreach ( $daily_series as $row ) {
			$max_daily = max( $max_daily, (int) ( $row['total'] ?? 0 ) );
		}

		if ( isset( $_GET['multch_purged'] ) ) {
			$purged = isset( $_GET['purged_events'] ) ? (int) $_GET['purged_events'] : 0;
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html(
				sprintf(
					/* translators: %d: number of deleted events */
					__( 'Purge complete: %d events deleted.', 'multiai-chatbot' ),
					$purged
				)
			);
			echo '</p></div>';
		}
		?>
		<div class="multch-admin-stats-toolbar">
			<div class="multch-admin-stats-toolbar__intro">
				<p><?php esc_html_e( 'Chatbot usage telemetry on your site.', 'multiai-chatbot' ); ?></p>
				<a class="multch-admin-stats-toolbar__link" href="<?php echo esc_url( self::build_history_url( array( 'days' => $days ) ) ); ?>">
					<?php esc_html_e( 'View conversations for the period', 'multiai-chatbot' ); ?>
				</a>
			</div>
			<div class="multch-admin-stats-toolbar__actions">
				<div class="multch-admin-pills" role="group" aria-label="<?php esc_attr_e( 'Period', 'multiai-chatbot' ); ?>">
					<?php foreach ( $periods as $p => $label ) : ?>
						<a href="<?php echo esc_url( self::build_stats_url( array_merge( $filters, array( 'days' => $p, 'paged' => 1 ) ) ) ); ?>"
							class="<?php echo (int) $days === (int) $p ? 'is-active' : ''; ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endforeach; ?>
				</div>
				<a class="button multch-admin-export" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'multiai-chatbot' ); ?></a>
				<?php if ( '' !== $purge_url ) : ?>
					<a class="button button-secondary multch-admin-stats-purge" href="<?php echo esc_url( $purge_url ); ?>" data-confirm="<?php esc_attr_e( 'Purge events older than the configured retention period?', 'multiai-chatbot' ); ?>">
						<?php esc_html_e( 'Purge old', 'multiai-chatbot' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<div class="multch-admin-kpi-grid multch-admin-kpi-grid--stats">
			<div class="multch-admin-kpi">
				<span class="multch-admin-kpi__label"><?php esc_html_e( 'Total requests', 'multiai-chatbot' ); ?></span>
				<span class="multch-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $totals['total_requests'] ?? 0 ) ) ); ?></span>
			</div>
			<div class="multch-admin-kpi multch-admin-kpi--success">
				<span class="multch-admin-kpi__label"><?php esc_html_e( 'Successes', 'multiai-chatbot' ); ?></span>
				<span class="multch-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $totals['success_count'] ?? 0 ) ) ); ?></span>
			</div>
			<div class="multch-admin-kpi">
				<span class="multch-admin-kpi__label"><?php esc_html_e( 'Cached', 'multiai-chatbot' ); ?></span>
				<span class="multch-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $totals['cached_count'] ?? 0 ) ) ); ?></span>
			</div>
			<div class="multch-admin-kpi multch-admin-kpi--error">
				<span class="multch-admin-kpi__label"><?php esc_html_e( 'Errors', 'multiai-chatbot' ); ?></span>
				<span class="multch-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $totals['error_count'] ?? 0 ) ) ); ?></span>
			</div>
			<div class="multch-admin-kpi">
				<span class="multch-admin-kpi__label"><?php esc_html_e( 'Success rate', 'multiai-chatbot' ); ?></span>
				<span class="multch-admin-kpi__value"><?php echo esc_html( number_format_i18n( (float) ( $totals['success_rate'] ?? 0 ), 1 ) ); ?>%</span>
			</div>
			<div class="multch-admin-kpi">
				<span class="multch-admin-kpi__label"><?php esc_html_e( 'Average latency', 'multiai-chatbot' ); ?></span>
				<span class="multch-admin-kpi__value"><?php echo esc_html( number_format_i18n( (float) ( $totals['avg_latency_ms'] ?? 0 ), 0 ) ); ?> <small>ms</small></span>
			</div>
			<div class="multch-admin-kpi">
				<span class="multch-admin-kpi__label"><?php esc_html_e( 'P95 latency', 'multiai-chatbot' ); ?></span>
				<span class="multch-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $totals['p95_latency_ms'] ?? 0 ) ) ); ?> <small>ms</small></span>
			</div>
		</div>

		<?php if ( ! empty( $daily_series ) && $max_daily > 0 ) : ?>
			<div class="multch-admin-card multch-admin-stats-series">
				<div class="multch-admin-card__head">
					<h2><?php esc_html_e( 'Daily activity', 'multiai-chatbot' ); ?></h2>
				</div>
				<div class="multch-admin-card__body">
					<div class="multch-admin-stats-bars">
						<?php foreach ( array_reverse( $daily_series ) as $row ) : ?>
							<?php
							$day_total = (int) ( $row['total'] ?? 0 );
							$height    = $max_daily > 0 ? max( 4, (int) round( ( $day_total / $max_daily ) * 100 ) ) : 0;
							?>
							<div class="multch-admin-stats-bar" title="<?php echo esc_attr( sprintf( '%s: %d', (string) ( $row['day'] ?? '' ), $day_total ) ); ?>">
								<div class="multch-admin-stats-bar__fill" style="height: <?php echo esc_attr( (string) $height ); ?>%;"></div>
								<span class="multch-admin-stats-bar__label"><?php echo esc_html( wp_date( 'd/m', strtotime( (string) ( $row['day'] ?? '' ) . ' UTC' ) ) ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<div class="multch-admin-stats-grid multch-admin-stats-grid--wide">
			<div class="multch-admin-card">
				<div class="multch-admin-card__head"><h2><?php esc_html_e( 'By status', 'multiai-chatbot' ); ?></h2></div>
				<div class="multch-admin-card__body">
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Status', 'multiai-chatbot' ); ?></th><th><?php esc_html_e( 'Count', 'multiai-chatbot' ); ?></th></tr></thead>
						<tbody>
							<?php
							$by_status = (array) ( $summary['by_status'] ?? array() );
							if ( empty( $by_status ) ) :
								?>
								<tr><td colspan="2"><?php esc_html_e( 'No data in this period.', 'multiai-chatbot' ); ?></td></tr>
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
			<div class="multch-admin-card">
				<div class="multch-admin-card__head"><h2><?php esc_html_e( 'By provider', 'multiai-chatbot' ); ?></h2></div>
				<div class="multch-admin-card__body">
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Provider', 'multiai-chatbot' ); ?></th><th><?php esc_html_e( 'Count', 'multiai-chatbot' ); ?></th></tr></thead>
						<tbody>
							<?php
							$by_provider = (array) ( $summary['by_provider'] ?? array() );
							if ( empty( $by_provider ) ) :
								?>
								<tr><td colspan="2"><?php esc_html_e( 'No data in this period.', 'multiai-chatbot' ); ?></td></tr>
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
			<div class="multch-admin-card">
				<div class="multch-admin-card__head"><h2><?php esc_html_e( 'By model', 'multiai-chatbot' ); ?></h2></div>
				<div class="multch-admin-card__body">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Model', 'multiai-chatbot' ); ?></th>
								<th><?php esc_html_e( 'Count', 'multiai-chatbot' ); ?></th>
								<th><?php esc_html_e( 'Avg. latency', 'multiai-chatbot' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$by_model = (array) ( $summary['by_model'] ?? array() );
							if ( empty( $by_model ) ) :
								?>
								<tr><td colspan="3"><?php esc_html_e( 'No data in this period.', 'multiai-chatbot' ); ?></td></tr>
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
			<div class="multch-admin-card">
				<div class="multch-admin-card__head"><h2><?php esc_html_e( 'By error code', 'multiai-chatbot' ); ?></h2></div>
				<div class="multch-admin-card__body">
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Code', 'multiai-chatbot' ); ?></th><th><?php esc_html_e( 'Count', 'multiai-chatbot' ); ?></th></tr></thead>
						<tbody>
							<?php
							$by_error = (array) ( $summary['by_error'] ?? array() );
							if ( empty( $by_error ) ) :
								?>
								<tr><td colspan="2"><?php esc_html_e( 'No errors in this period.', 'multiai-chatbot' ); ?></td></tr>
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

		<div class="multch-admin-card multch-admin-events">
			<div class="multch-admin-card__head">
				<h2><?php esc_html_e( 'Events', 'multiai-chatbot' ); ?></h2>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: number of events */
							_n( '%s event in the period', '%s events in the period', $total, 'multiai-chatbot' ),
							number_format_i18n( $total )
						)
					);
					?>
				</p>
			</div>
			<div class="multch-admin-card__body multch-admin-stats-filters-wrap">
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="multch-admin-stats-filters">
					<input type="hidden" name="page" value="multch-plugin" />
					<input type="hidden" name="tab" value="stats" />
					<input type="hidden" name="days" value="<?php echo esc_attr( (string) $days ); ?>" />
					<div class="multch-admin-stats-filters__field">
						<label for="multch-stats-provider"><?php esc_html_e( 'Provider', 'multiai-chatbot' ); ?></label>
						<select id="multch-stats-provider" name="provider">
							<option value="all"<?php selected( $filters['provider'], 'all' ); ?>><?php esc_html_e( 'All', 'multiai-chatbot' ); ?></option>
							<option value="wordpress_ai"<?php selected( $filters['provider'], 'wordpress_ai' ); ?>><?php esc_html_e( 'WordPress AI', 'multiai-chatbot' ); ?></option>
							<option value="gemini"<?php selected( $filters['provider'], 'gemini' ); ?>>Gemini</option>
							<option value="deepseek"<?php selected( $filters['provider'], 'deepseek' ); ?>>DeepSeek</option>
							<option value="ollama"<?php selected( $filters['provider'], 'ollama' ); ?>>Ollama</option>
							<option value="openai_compatible"<?php selected( $filters['provider'], 'openai_compatible' ); ?>>OpenAI-compatible</option>
						</select>
					</div>
					<div class="multch-admin-stats-filters__field">
						<label for="multch-stats-status"><?php esc_html_e( 'Status', 'multiai-chatbot' ); ?></label>
						<select id="multch-stats-status" name="status">
							<option value="all"<?php selected( $filters['status'], 'all' ); ?>><?php esc_html_e( 'All', 'multiai-chatbot' ); ?></option>
							<option value="success"<?php selected( $filters['status'], 'success' ); ?>><?php esc_html_e( 'Success', 'multiai-chatbot' ); ?></option>
							<option value="cached"<?php selected( $filters['status'], 'cached' ); ?>><?php esc_html_e( 'Cached', 'multiai-chatbot' ); ?></option>
							<option value="error"<?php selected( $filters['status'], 'error' ); ?>><?php esc_html_e( 'Error', 'multiai-chatbot' ); ?></option>
							<option value="rate_limited"<?php selected( $filters['status'], 'rate_limited' ); ?>><?php esc_html_e( 'Rate limited', 'multiai-chatbot' ); ?></option>
							<option value="config_error"<?php selected( $filters['status'], 'config_error' ); ?>><?php esc_html_e( 'Configuration error', 'multiai-chatbot' ); ?></option>
							<option value="invalid_request"<?php selected( $filters['status'], 'invalid_request' ); ?>><?php esc_html_e( 'Invalid request', 'multiai-chatbot' ); ?></option>
						</select>
					</div>
					<div class="multch-admin-stats-filters__field">
						<label for="multch-stats-model"><?php esc_html_e( 'Model', 'multiai-chatbot' ); ?></label>
						<select id="multch-stats-model" name="model">
							<option value="all"<?php selected( $filters['model'], 'all' ); ?>><?php esc_html_e( 'All', 'multiai-chatbot' ); ?></option>
							<?php foreach ( $models as $model ) : ?>
								<option value="<?php echo esc_attr( $model ); ?>"<?php selected( $filters['model'], $model ); ?>><?php echo esc_html( $model ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="multch-admin-stats-filters__field">
						<label for="multch-stats-error"><?php esc_html_e( 'Error code', 'multiai-chatbot' ); ?></label>
						<select id="multch-stats-error" name="error_code">
							<option value="all"<?php selected( $filters['error_code'], 'all' ); ?>><?php esc_html_e( 'All', 'multiai-chatbot' ); ?></option>
							<?php foreach ( $error_codes as $code ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>"<?php selected( $filters['error_code'], $code ); ?>><?php echo esc_html( self::format_error_code_label( $code ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="multch-admin-stats-filters__field">
						<label for="multch-stats-conversation"><?php esc_html_e( 'Conversation (ID)', 'multiai-chatbot' ); ?></label>
						<input type="number" min="0" id="multch-stats-conversation" name="conversation_id" value="<?php echo esc_attr( (string) (int) $filters['conversation_id'] ); ?>" class="small-text" />
					</div>
					<div class="multch-admin-stats-filters__actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'multiai-chatbot' ); ?></button>
						<?php if ( $has_filters ) : ?>
							<a class="button" href="<?php echo esc_url( self::build_stats_url( array( 'days' => $days ) ) ); ?>"><?php esc_html_e( 'Clear filters', 'multiai-chatbot' ); ?></a>
						<?php endif; ?>
					</div>
				</form>
			</div>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'multiai-chatbot' ); ?></th>
						<th><?php esc_html_e( 'Provider', 'multiai-chatbot' ); ?></th>
						<th><?php esc_html_e( 'Model', 'multiai-chatbot' ); ?></th>
						<th><?php esc_html_e( 'Status', 'multiai-chatbot' ); ?></th>
						<th><?php esc_html_e( 'Latency', 'multiai-chatbot' ); ?></th>
						<th><?php esc_html_e( 'Error', 'multiai-chatbot' ); ?></th>
						<th><?php esc_html_e( 'Conversation', 'multiai-chatbot' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $events ) ) : ?>
						<tr>
							<td colspan="7">
								<?php if ( $has_filters ) : ?>
									<?php esc_html_e( 'No results with these filters.', 'multiai-chatbot' ); ?>
								<?php else : ?>
									<?php esc_html_e( 'No events in this period.', 'multiai-chatbot' ); ?>
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
								<td><?php echo esc_html( Multch_Chat_History::format_datetime_local( (string) ( $event['created_at'] ?? '' ) ) ); ?></td>
								<td><?php echo esc_html( (string) ( $event['provider'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( self::format_telemetry_model_label( $event ) ); ?></td>
								<td>
									<span class="multch-admin-status <?php echo esc_attr( self::format_telemetry_status_class( $status ) ); ?>">
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
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	private static function get_history_filters_from_request(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin filter GET params.
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
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * @param array<string, mixed> $query_args
	 */
	private static function build_history_url( array $query_args ): string {
		$args = array_merge(
			array(
				'page' => 'multch-plugin',
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

		echo '<nav class="multch-admin-tablenav multch-admin-tablenav--history" aria-label="' . esc_attr__( 'Pagination', 'multiai-chatbot' ) . '">';

		if ( $page > 1 ) {
			$prev_args           = $base_args;
			$prev_args['paged']  = $page - 1;
			echo '<a class="multch-admin-tablenav__prev" href="' . esc_url( self::build_history_url( $prev_args ) ) . '">' . esc_html__( 'Previous', 'multiai-chatbot' ) . '</a>';
		}

		echo '<span class="multch-admin-tablenav__status">';
		echo esc_html(
			sprintf(
				/* translators: 1: current page, 2: total pages */
				__( 'Page %1$d of %2$d', 'multiai-chatbot' ),
				$page,
				$pages
			)
		);
		echo '</span>';

		$window = 5;
		$start  = max( 1, $page - $window );
		$end    = min( $pages, $page + $window );

		echo '<span class="multch-admin-tablenav__pages">';
		for ( $i = $start; $i <= $end; $i++ ) {
			$page_args          = $base_args;
			$page_args['paged'] = $i;
			echo '<a href="' . esc_url( self::build_history_url( $page_args ) ) . '" class="' . esc_attr( $page === $i ? 'is-active' : '' ) . '">' . esc_html( (string) $i ) . '</a>';
		}
		echo '</span>';

		if ( $page < $pages ) {
			$next_args          = $base_args;
			$next_args['paged'] = $page + 1;
			echo '<a class="multch-admin-tablenav__next" href="' . esc_url( self::build_history_url( $next_args ) ) . '">' . esc_html__( 'Next', 'multiai-chatbot' ) . '</a>';
		}

		echo '</nav>';
	}

	private static function render_history_tab(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin list/filter GET params.
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
			$target_conv = Multch_Chat_History::get_conversation( $expanded_id );
			if ( $target_conv ) {
				$count_args = $filters;
				unset( $count_args['offset'], $count_args['per_page'] );
				$target_page = Multch_Chat_History::find_conversation_page( $expanded_id, $count_args, $per );
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

		$items  = Multch_Chat_History::list_conversations( $query_args );
		$total  = Multch_Chat_History::count_conversations( $filters );
		$pages  = (int) ceil( $total / $per );
		$stats  = Multch_Chat_History::get_summary_stats( $filters );
		$paths  = Multch_Chat_History::get_distinct_page_paths( $filters );

		if ( $expanded_id > 0 ) {
			$ids_on_page = array_map(
				static function ( $item ) {
					return (int) ( $item['id'] ?? 0 );
				},
				$items
			);
			if ( ! in_array( $expanded_id, $ids_on_page, true ) ) {
				$orphan = Multch_Chat_History::get_conversation( $expanded_id );
				if ( $orphan ) {
					array_unshift( $items, $orphan );
				}
			}
		}

		$previews = Multch_Chat_History::get_first_user_previews(
			array_map(
				static function ( $item ) {
					return (int) ( $item['id'] ?? 0 );
				},
				$items
			)
		);

		$settings    = Multch_Plugin::get_settings();
		$retention   = (int) ( $settings['history_retention_days'] ?? 0 );
		$has_filters = '' !== $search || 'all' !== $provider || 'all' !== $status || 'all' !== (string) $filters['page_path'] || 'all' !== (string) $filters['search_in'];

		$periods = array(
			0   => __( 'All', 'multiai-chatbot' ),
			7   => __( '7 days', 'multiai-chatbot' ),
			30  => __( '30 days', 'multiai-chatbot' ),
			90  => __( '90 days', 'multiai-chatbot' ),
		);

		$export_url = wp_nonce_url(
			add_query_arg(
				array_merge(
					array( 'action' => 'multch_export_history_csv' ),
					$filters
				),
				admin_url( 'admin-post.php' )
			),
			'multch_export_history_csv'
		);

		$purge_url = '';
		if ( $retention > 0 ) {
			$purge_url = wp_nonce_url(
				add_query_arg( 'action', 'multch_purge_history', admin_url( 'admin-post.php' ) ),
				'multch_purge_history'
			);
		}

		$count_label = sprintf(
			/* translators: %s: number of conversations */
			_n( '%s conversation', '%s conversations', $total, 'multiai-chatbot' ),
			number_format_i18n( $total )
		);
		$active_period = $periods[ $days ] ?? ( $days > 0 ? sprintf(
			/* translators: %d: number of days */
			__( '%d days', 'multiai-chatbot' ),
			$days
		) : __( 'All', 'multiai-chatbot' ) );

		if ( isset( $_GET['multch_purged'] ) ) {
			$purged_conv = isset( $_GET['purged_conversations'] ) ? (int) $_GET['purged_conversations'] : 0;
			$purged_msg  = isset( $_GET['purged_messages'] ) ? (int) $_GET['purged_messages'] : 0;
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html(
				sprintf(
					/* translators: 1: conversations deleted, 2: messages deleted */
					__( 'Purge complete: %1$d conversations and %2$d messages deleted.', 'multiai-chatbot' ),
					$purged_conv,
					$purged_msg
				)
			);
			echo '</p></div>';
		}
		?>
		<div class="multch-admin-kpi-grid multch-admin-kpi-grid--history">
			<div class="multch-admin-kpi">
				<span class="multch-admin-kpi__label"><?php esc_html_e( 'Conversations', 'multiai-chatbot' ); ?></span>
				<span class="multch-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $stats['total'] ?? 0 ) ) ); ?></span>
			</div>
			<div class="multch-admin-kpi multch-admin-kpi--error">
				<span class="multch-admin-kpi__label"><?php esc_html_e( 'With error', 'multiai-chatbot' ); ?></span>
				<span class="multch-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $stats['errors'] ?? 0 ) ) ); ?></span>
			</div>
			<div class="multch-admin-kpi">
				<span class="multch-admin-kpi__label"><?php esc_html_e( 'Total messages', 'multiai-chatbot' ); ?></span>
				<span class="multch-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $stats['messages'] ?? 0 ) ) ); ?></span>
			</div>
			<div class="multch-admin-kpi">
				<span class="multch-admin-kpi__label"><?php esc_html_e( 'Avg. msgs/conv.', 'multiai-chatbot' ); ?></span>
				<span class="multch-admin-kpi__value"><?php echo esc_html( number_format_i18n( (float) ( $stats['avg_messages'] ?? 0 ), 1 ) ); ?></span>
			</div>
		</div>

		<div class="multch-admin-card multch-admin-history-panel">
			<div class="multch-admin-card__head multch-admin-history-panel__head">
				<div class="multch-admin-history-toolbar">
					<div class="multch-admin-history-toolbar__intro">
						<h2><?php esc_html_e( 'Conversations', 'multiai-chatbot' ); ?></h2>
						<p>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: conversation count label, 2: active period */
									__( '%1$s · %2$s', 'multiai-chatbot' ),
									$count_label,
									$active_period
								)
							);
							?>
						</p>
					</div>
					<div class="multch-admin-history-toolbar__actions">
						<a class="button multch-admin-export" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'multiai-chatbot' ); ?></a>
						<?php if ( '' !== $purge_url ) : ?>
							<a
								class="button button-secondary multch-admin-history-purge"
								href="<?php echo esc_url( $purge_url ); ?>"
								data-confirm="<?php esc_attr_e( 'Purge conversations older than the configured retention period?', 'multiai-chatbot' ); ?>"
							><?php esc_html_e( 'Purge old', 'multiai-chatbot' ); ?></a>
						<?php endif; ?>
					</div>
					<div class="multch-admin-history-toolbar__period">
						<div class="multch-admin-pills multch-admin-pills--history" role="group" aria-label="<?php esc_attr_e( 'Period', 'multiai-chatbot' ); ?>">
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
			<div class="multch-admin-card__body multch-admin-history-panel__filters">
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="multch-admin-history-filters">
					<input type="hidden" name="page" value="multch-plugin" />
					<input type="hidden" name="tab" value="history" />
					<input type="hidden" name="days" value="<?php echo esc_attr( (string) $days ); ?>" />
					<div class="multch-admin-history-filters__field multch-admin-history-filters__field--search">
						<label for="multch-history-search"><?php esc_html_e( 'Search', 'multiai-chatbot' ); ?></label>
						<input type="search" id="multch-history-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'ID, title, path, session, or message…', 'multiai-chatbot' ); ?>" />
					</div>
					<div class="multch-admin-history-filters__field">
						<label for="multch-history-search-in"><?php esc_html_e( 'Search in', 'multiai-chatbot' ); ?></label>
						<select id="multch-history-search-in" name="search_in">
							<option value="all"<?php selected( $filters['search_in'], 'all' ); ?>><?php esc_html_e( 'Metadata and messages', 'multiai-chatbot' ); ?></option>
							<option value="meta"<?php selected( $filters['search_in'], 'meta' ); ?>><?php esc_html_e( 'Metadata only', 'multiai-chatbot' ); ?></option>
							<option value="messages"<?php selected( $filters['search_in'], 'messages' ); ?>><?php esc_html_e( 'Messages only', 'multiai-chatbot' ); ?></option>
						</select>
					</div>
					<div class="multch-admin-history-filters__field">
						<label for="multch-history-page-path"><?php esc_html_e( 'Path', 'multiai-chatbot' ); ?></label>
						<select id="multch-history-page-path" name="page_path">
							<option value="all"<?php selected( $filters['page_path'], 'all' ); ?>><?php esc_html_e( 'All', 'multiai-chatbot' ); ?></option>
							<?php foreach ( $paths as $path ) : ?>
								<option value="<?php echo esc_attr( $path ); ?>"<?php selected( $filters['page_path'], $path ); ?>><?php echo esc_html( $path ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="multch-admin-history-filters__field">
						<label for="multch-history-provider"><?php esc_html_e( 'Provider', 'multiai-chatbot' ); ?></label>
						<select id="multch-history-provider" name="provider">
							<option value="all"<?php selected( $provider, 'all' ); ?>><?php esc_html_e( 'All', 'multiai-chatbot' ); ?></option>
							<option value="wordpress_ai"<?php selected( $provider, 'wordpress_ai' ); ?>><?php esc_html_e( 'WordPress AI', 'multiai-chatbot' ); ?></option>
							<option value="gemini"<?php selected( $provider, 'gemini' ); ?>>Gemini</option>
							<option value="deepseek"<?php selected( $provider, 'deepseek' ); ?>>DeepSeek</option>
							<option value="ollama"<?php selected( $provider, 'ollama' ); ?>>Ollama</option>
							<option value="openai_compatible"<?php selected( $provider, 'openai_compatible' ); ?>>OpenAI-compatible</option>
						</select>
					</div>
					<div class="multch-admin-history-filters__field">
						<label for="multch-history-status"><?php esc_html_e( 'Status', 'multiai-chatbot' ); ?></label>
						<select id="multch-history-status" name="status">
							<option value="all"<?php selected( $status, 'all' ); ?>><?php esc_html_e( 'All', 'multiai-chatbot' ); ?></option>
							<option value="active"<?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'multiai-chatbot' ); ?></option>
							<option value="success"<?php selected( $status, 'success' ); ?>><?php esc_html_e( 'Success', 'multiai-chatbot' ); ?></option>
							<option value="error"<?php selected( $status, 'error' ); ?>><?php esc_html_e( 'Error', 'multiai-chatbot' ); ?></option>
							<option value="cached"<?php selected( $status, 'cached' ); ?>><?php esc_html_e( 'Cached', 'multiai-chatbot' ); ?></option>
						</select>
					</div>
					<div class="multch-admin-history-filters__field">
						<label for="multch-history-orderby"><?php esc_html_e( 'Sort by', 'multiai-chatbot' ); ?></label>
						<select id="multch-history-orderby" name="orderby">
							<option value="updated_at"<?php selected( $orderby, 'updated_at' ); ?>><?php esc_html_e( 'Last activity', 'multiai-chatbot' ); ?></option>
							<option value="started_at"<?php selected( $orderby, 'started_at' ); ?>><?php esc_html_e( 'Start', 'multiai-chatbot' ); ?></option>
						</select>
					</div>
					<div class="multch-admin-history-filters__field">
						<label for="multch-history-order"><?php esc_html_e( 'Direction', 'multiai-chatbot' ); ?></label>
						<select id="multch-history-order" name="order">
							<option value="desc"<?php selected( $order, 'desc' ); ?>><?php esc_html_e( 'Newest first', 'multiai-chatbot' ); ?></option>
							<option value="asc"<?php selected( $order, 'asc' ); ?>><?php esc_html_e( 'Oldest first', 'multiai-chatbot' ); ?></option>
						</select>
					</div>
					<div class="multch-admin-history-filters__actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'multiai-chatbot' ); ?></button>
						<?php if ( $has_filters ) : ?>
							<a class="button" href="<?php echo esc_url( self::build_history_url( array( 'days' => $days ) ) ); ?>"><?php esc_html_e( 'Clear filters', 'multiai-chatbot' ); ?></a>
						<?php endif; ?>
					</div>
				</form>
			</div>
		</div>

		<div class="multch-admin-card multch-admin-history-list">
			<div class="multch-admin-card__head multch-admin-history-list__head">
				<h2><?php echo esc_html( $count_label ); ?></h2>
				<?php if ( $pages > 1 ) : ?>
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: current page, 2: total pages */
								__( 'Page %1$d of %2$d', 'multiai-chatbot' ),
								$page,
								$pages
							)
						);
						?>
					</p>
				<?php endif; ?>
			</div>

			<?php if ( empty( $items ) ) : ?>
				<div class="multch-admin-card__body multch-admin-history-empty">
					<span class="multch-admin-history-empty__icon dashicons dashicons-format-chat" aria-hidden="true"></span>
					<?php if ( $has_filters ) : ?>
						<p><?php esc_html_e( 'No results with these filters.', 'multiai-chatbot' ); ?></p>
						<p><a class="button" href="<?php echo esc_url( self::build_history_url( array( 'days' => $days ) ) ); ?>"><?php esc_html_e( 'Clear filters', 'multiai-chatbot' ); ?></a></p>
					<?php else : ?>
						<p><?php esc_html_e( 'No conversations in this period.', 'multiai-chatbot' ); ?></p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="multch-admin-history-table" role="table" aria-label="<?php esc_attr_e( 'Conversation list', 'multiai-chatbot' ); ?>">
					<div class="multch-admin-history-table__head" role="row">
						<span class="multch-admin-history-table__cell multch-admin-history-table__cell--icon" role="columnheader" aria-hidden="true"></span>
						<span class="multch-admin-history-table__cell multch-admin-history-table__cell--title" role="columnheader"><?php esc_html_e( 'Conversation', 'multiai-chatbot' ); ?></span>
						<span class="multch-admin-history-table__cell multch-admin-history-table__cell--status" role="columnheader"><?php esc_html_e( 'Status', 'multiai-chatbot' ); ?></span>
						<span class="multch-admin-history-table__cell multch-admin-history-table__cell--provider" role="columnheader"><?php esc_html_e( 'Provider', 'multiai-chatbot' ); ?></span>
						<span class="multch-admin-history-table__cell multch-admin-history-table__cell--date" role="columnheader"><?php esc_html_e( 'Updated', 'multiai-chatbot' ); ?></span>
						<span class="multch-admin-history-table__cell multch-admin-history-table__cell--msgs" role="columnheader"><?php esc_html_e( 'Msgs', 'multiai-chatbot' ); ?></span>
						<span class="multch-admin-history-table__cell multch-admin-history-table__cell--action" role="columnheader" aria-hidden="true"></span>
					</div>
					<div class="multch-admin-history-stack" id="multch-history-list" role="rowgroup">
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
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	public static function ajax_history_detail(): void {
		check_ajax_referer( 'multch_history_detail', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'multiai-chatbot' ) ), 403 );
		}

		if ( ! Multch_Plugin::is_stats_history_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Statistics and history are disabled.', 'multiai-chatbot' ) ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified via check_ajax_referer above.
		$conversation_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( $conversation_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid conversation.', 'multiai-chatbot' ) ), 400 );
		}

		$conv = Multch_Chat_History::get_conversation( $conversation_id );
		if ( ! $conv ) {
			wp_send_json_error( array( 'message' => __( 'Conversation not found.', 'multiai-chatbot' ) ), 404 );
		}

		$messages = Multch_Chat_History::get_messages( $conversation_id );

		ob_start();
		self::render_history_card_body( $conv, $messages );
		$html = (string) ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	public static function ajax_history_export_json(): void {
		check_ajax_referer( 'multch_history_detail', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'multiai-chatbot' ) ), 403 );
		}

		if ( ! Multch_Plugin::is_stats_history_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Statistics and history are disabled.', 'multiai-chatbot' ) ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified via check_ajax_referer above.
		$conversation_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( $conversation_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid conversation.', 'multiai-chatbot' ) ), 400 );
		}

		$conv = Multch_Chat_History::get_conversation( $conversation_id );
		if ( ! $conv ) {
			wp_send_json_error( array( 'message' => __( 'Conversation not found.', 'multiai-chatbot' ) ), 404 );
		}

		$messages = Multch_Chat_History::get_messages( $conversation_id );
		$export   = self::build_history_export_payload( $conv, $messages );

		wp_send_json_success( array( 'export' => $export ) );
	}

	/**
	 * Full conversation snapshot for clipboard export (no UI truncation).
	 *
	 * @param array<string, mixed>           $conv
	 * @param array<int, array<string, mixed>> $messages
	 * @return array<string, mixed>
	 */
	private static function build_history_export_payload( array $conv, array $messages ): array {
		$conv_id = (int) ( $conv['id'] ?? 0 );

		$conversation = array();
		foreach ( $conv as $key => $value ) {
			if ( 'message_count' === $key ) {
				$conversation[ $key ] = (int) $value;
				continue;
			}
			if ( 'id' === $key ) {
				$conversation[ $key ] = (int) $value;
				continue;
			}
			$conversation[ (string) $key ] = is_scalar( $value ) || null === $value ? $value : (string) $value;
		}

		$export_messages = array();
		foreach ( $messages as $msg ) {
			$row = array(
				'id'              => (int) ( $msg['id'] ?? 0 ),
				'conversation_id' => (int) ( $msg['conversation_id'] ?? 0 ),
				'role'            => (string) ( $msg['role'] ?? '' ),
				'content'         => (string) ( $msg['content'] ?? '' ),
				'status'          => (string) ( $msg['status'] ?? '' ),
				'latency_ms'      => (int) ( $msg['latency_ms'] ?? 0 ),
				'created_at'      => (string) ( $msg['created_at'] ?? '' ),
			);

			$meta = array();
			if ( ! empty( $msg['meta_json'] ) ) {
				$decoded = json_decode( (string) $msg['meta_json'], true );
				if ( is_array( $decoded ) ) {
					$meta = $decoded;
				}
			}
			if ( ! empty( $meta ) ) {
				$row['meta'] = $meta;
			}

			$export_messages[] = $row;
		}

		$telemetry = Multch_Telemetry::get_events_by_conversation( $conv_id, 500 );

		return array(
			'exported_at'  => gmdate( 'c' ),
			'conversation' => $conversation,
			'messages'     => $export_messages,
			'telemetry'    => $telemetry,
		);
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
	$updated    = Multch_Chat_History::format_datetime_local( (string) ( $item['updated_at'] ?? '' ) );
	$relative   = Multch_Chat_History::format_relative_time( (string) ( $item['updated_at'] ?? '' ) );
	$duration   = Multch_Chat_History::format_duration(
		(string) ( $item['started_at'] ?? '' ),
		(string) ( $item['updated_at'] ?? '' )
	);
	$is_ok      = in_array( $status, array( 'success', 'active', 'cached' ), true );

	if ( '' === $title ) {
		$title = __( '(Untitled)', 'multiai-chatbot' );
	}

	$provider_label = self::format_history_provider_label( $provider, $model );
	$provider_name  = self::format_history_provider_name( $provider );
	$card_id        = 'multch-history-card-' . $id;
	$panel_id       = 'multch-history-panel-' . $id;
	$loaded         = $expanded;
	$messages       = array();
	$status_class   = 'multch-admin-history-card__status--err';
	$avatar_label   = self::format_history_provider_avatar( $provider );

	if ( 'cached' === $status ) {
		$status_class = 'multch-admin-history-card__status--cached';
	} elseif ( $is_ok ) {
		$status_class = 'multch-admin-history-card__status--ok';
	}

	if ( $expanded ) {
		$messages = Multch_Chat_History::get_messages( $id );
	}
	?>
	<article
		class="multch-admin-history-card multch-admin-history-card--<?php echo esc_attr( $status ); ?><?php echo $expanded ? ' is-open' : ''; ?>"
		id="<?php echo esc_attr( $card_id ); ?>"
		data-conversation-id="<?php echo esc_attr( (string) $id ); ?>"
		data-provider="<?php echo esc_attr( $provider ); ?>"
		data-loaded="<?php echo $loaded ? '1' : '0'; ?>"
	>
		<button
			type="button"
			class="multch-admin-history-card__toggle"
			aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>"
			aria-controls="<?php echo esc_attr( $panel_id ); ?>"
		>
			<span class="multch-admin-history-table__cell multch-admin-history-table__cell--icon">
				<span class="multch-admin-history-card__avatar" aria-hidden="true">
					<span class="multch-admin-history-card__avatar-label"><?php echo esc_html( $avatar_label ); ?></span>
				</span>
			</span>

			<span class="multch-admin-history-table__cell multch-admin-history-table__cell--title">
				<span class="multch-admin-history-card__title"<?php echo '' !== $preview ? ' title="' . esc_attr( $preview ) . '"' : ''; ?>><?php echo esc_html( $title ); ?></span>
				<span class="multch-admin-history-card__sub">
					<code class="multch-admin-history-card__ref"><?php echo esc_html( $public_id ); ?></code>
					<?php if ( '' !== $page_path ) : ?>
						<span class="multch-admin-history-tag multch-admin-history-tag--path" title="<?php echo esc_attr( $page_path ); ?>">
							<?php echo esc_html( $page_path ); ?>
						</span>
					<?php endif; ?>
				</span>
			</span>

			<span class="multch-admin-history-table__cell multch-admin-history-table__cell--status">
				<span class="multch-admin-history-card__status <?php echo esc_attr( $status_class ); ?>">
					<span class="multch-admin-history-card__status-dot" aria-hidden="true"></span>
					<?php echo esc_html( self::format_history_status_label( $status ) ); ?>
				</span>
			</span>

			<span class="multch-admin-history-table__cell multch-admin-history-table__cell--provider" data-label="<?php esc_attr_e( 'Provider', 'multiai-chatbot' ); ?>">
				<span class="multch-admin-history-card__provider-stack">
					<?php if ( '' !== $provider_name ) : ?>
						<span class="multch-admin-history-card__provider-name"><?php echo esc_html( $provider_name ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $model ) : ?>
						<span class="multch-admin-history-card__model" title="<?php echo esc_attr( $model ); ?>"><?php echo esc_html( $model ); ?></span>
					<?php elseif ( '' !== $provider_label ) : ?>
						<span class="multch-admin-history-card__model"><?php echo esc_html( $provider_label ); ?></span>
					<?php endif; ?>
				</span>
			</span>

			<span class="multch-admin-history-table__cell multch-admin-history-table__cell--date" data-label="<?php esc_attr_e( 'Updated', 'multiai-chatbot' ); ?>">
				<time datetime="<?php echo esc_attr( (string) ( $item['updated_at'] ?? '' ) ); ?>"><?php echo esc_html( $updated ); ?></time>
				<?php if ( '' !== $relative ) : ?>
					<span class="multch-admin-history-card__relative"><?php
					/* translators: %s: human-readable time difference */
					echo esc_html( sprintf( __( '%s ago', 'multiai-chatbot' ), $relative ) );
					?></span>
				<?php endif; ?>
				<span class="multch-admin-history-card__duration" title="<?php esc_attr_e( 'Conversation duration', 'multiai-chatbot' ); ?>"><?php echo esc_html( $duration ); ?></span>
			</span>

			<span class="multch-admin-history-table__cell multch-admin-history-table__cell--msgs" data-label="<?php esc_attr_e( 'Messages', 'multiai-chatbot' ); ?>">
				<span class="multch-admin-history-card__msgs-count"><?php echo esc_html( number_format_i18n( $msg_count ) ); ?></span>
			</span>

			<span class="multch-admin-history-table__cell multch-admin-history-table__cell--action">
				<span class="multch-admin-history-card__chevron" aria-hidden="true"></span>
			</span>
		</button>

		<div
			class="multch-admin-history-card__panel"
			id="<?php echo esc_attr( $panel_id ); ?>"
			role="region"
			<?php
			/* translators: %s: conversation public ID */
			$history_aria = sprintf( __( 'History of %s', 'multiai-chatbot' ), $public_id );
			?>
			aria-label="<?php echo esc_attr( $history_aria ); ?>"
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
	$started   = Multch_Chat_History::format_datetime_local( (string) ( $conv['started_at'] ?? '' ) );
	$updated   = Multch_Chat_History::format_datetime_local( (string) ( $conv['updated_at'] ?? '' ) );
	$duration  = Multch_Chat_History::format_duration(
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
	$telemetry_events = Multch_Telemetry::get_events_by_conversation( $conv_id, 20 );
	?>
	<div class="multch-admin-history-card__body">
		<div class="multch-admin-history-detail__actions">
			<?php if ( '' !== $public_id ) : ?>
				<button type="button" class="button button-small multch-admin-history-copy" data-copy="<?php echo esc_attr( $public_id ); ?>">
					<?php esc_html_e( 'Copy public ID', 'multiai-chatbot' ); ?>
				</button>
			<?php endif; ?>
			<button type="button" class="button button-small multch-admin-history-copy" data-copy="<?php echo esc_attr( $link_url ); ?>">
				<?php esc_html_e( 'Copy link', 'multiai-chatbot' ); ?>
			</button>
			<button type="button" class="button button-small multch-admin-history-copy-json" data-id="<?php echo esc_attr( (string) $conv_id ); ?>">
				<?php esc_html_e( 'Copy JSON', 'multiai-chatbot' ); ?>
			</button>
			<button type="button" class="button button-small button-link-delete multch-admin-history-delete" data-id="<?php echo esc_attr( (string) $conv_id ); ?>">
				<?php esc_html_e( 'Delete', 'multiai-chatbot' ); ?>
			</button>
		</div>

		<dl class="multch-admin-history-detail__grid">
			<div>
				<dt><?php esc_html_e( 'Internal ID', 'multiai-chatbot' ); ?></dt>
				<dd>#<?php echo esc_html( (string) (int) ( $conv['id'] ?? 0 ) ); ?></dd>
			</div>

			<?php if ( '' !== $public_id ) : ?>
				<div>
					<dt><?php esc_html_e( 'Public ID', 'multiai-chatbot' ); ?></dt>
					<dd><code><?php echo esc_html( $public_id ); ?></code></dd>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $status ) : ?>
				<div>
					<dt><?php esc_html_e( 'Status', 'multiai-chatbot' ); ?></dt>
					<dd><?php echo esc_html( self::format_history_status_label( $status ) ); ?></dd>
				</div>
			<?php endif; ?>

			<div>
				<dt><?php esc_html_e( 'Messages', 'multiai-chatbot' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( $msg_count ) ); ?></dd>
			</div>

			<div>
				<dt><?php esc_html_e( 'Start', 'multiai-chatbot' ); ?></dt>
				<dd><?php echo esc_html( $started ); ?></dd>
			</div>

			<div>
				<dt><?php esc_html_e( 'Last activity', 'multiai-chatbot' ); ?></dt>
				<dd><?php echo esc_html( $updated ); ?></dd>
			</div>

			<div>
				<dt><?php esc_html_e( 'Duration', 'multiai-chatbot' ); ?></dt>
				<dd><?php echo esc_html( $duration ); ?></dd>
			</div>

			<?php if ( '' !== $provider || '' !== $model ) : ?>
				<div>
					<dt><?php esc_html_e( 'Provider / model', 'multiai-chatbot' ); ?></dt>
					<dd><?php echo esc_html( self::format_history_provider_label( $provider, $model ) ); ?></dd>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $session ) : ?>
				<div>
					<dt><?php esc_html_e( 'Session', 'multiai-chatbot' ); ?></dt>
					<dd><code><?php echo esc_html( $session ); ?></code></dd>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $page_path ) : ?>
				<div class="multch-admin-history-detail__grid-wide">
					<dt><?php esc_html_e( 'Path', 'multiai-chatbot' ); ?></dt>
					<dd><?php echo esc_html( $page_path ); ?></dd>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $page_url ) : ?>
				<div class="multch-admin-history-detail__grid-wide">
					<dt><?php esc_html_e( 'URL', 'multiai-chatbot' ); ?></dt>
					<dd>
						<a href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( $page_url ); ?>
						</a>
					</dd>
				</div>
			<?php endif; ?>
		</dl>

		<?php if ( ! empty( $telemetry_events ) ) : ?>
			<div class="multch-admin-history-telemetry">
				<h3 class="multch-admin-history-messages__title"><?php esc_html_e( 'Technical events', 'multiai-chatbot' ); ?></h3>
				<ul class="multch-admin-history-telemetry__list">
					<?php foreach ( $telemetry_events as $event ) : ?>
						<li>
							<span><?php echo esc_html( Multch_Chat_History::format_datetime_local( (string) ( $event['created_at'] ?? '' ) ) ); ?></span>
							<span class="multch-admin-status <?php echo in_array( (string) ( $event['status'] ?? '' ), array( 'success', 'cached' ), true ) ? 'multch-admin-status--ok' : 'multch-admin-status--err'; ?>">
								<?php echo esc_html( (string) ( $event['status'] ?? '' ) ); ?>
							</span>
							<?php if ( ! empty( $event['model'] ) ) : ?>
								<span class="multch-admin-history-telemetry__model"><?php echo esc_html( self::format_telemetry_model_label( $event ) ); ?></span>
							<?php endif; ?>
							<span><?php echo esc_html( number_format_i18n( (int) ( $event['latency_ms'] ?? 0 ) ) ); ?> ms</span>
							<?php if ( ! empty( $event['error_code'] ) ) : ?>
								<code><?php echo esc_html( (string) $event['error_code'] ); ?></code>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="description">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=multch-plugin&tab=stats' ) ); ?>"><?php esc_html_e( 'View all statistics', 'multiai-chatbot' ); ?></a>
				</p>
			</div>
		<?php endif; ?>

		<div class="multch-admin-history-messages">
			<h3 class="multch-admin-history-messages__title"><?php esc_html_e( 'Messages', 'multiai-chatbot' ); ?></h3>
			<?php self::render_history_messages_list( $messages ); ?>
		</div>
	</div>
	<?php
}

/**
 * @param array<string, mixed> $trace
 */
private static function render_history_model_trace( array $trace ): void {
	$steps = isset( $trace['steps'] ) && is_array( $trace['steps'] ) ? $trace['steps'] : array();
	if ( empty( $steps ) ) {
		return;
	}

	$labels = multch_ai_client_trace_slot_labels();
	?>
	<div class="multch-admin-history-model-trace">
		<p class="multch-admin-history-model-trace__title"><?php esc_html_e( 'Model route', 'multiai-chatbot' ); ?></p>
		<ol class="multch-admin-history-model-trace__list">
			<?php foreach ( $steps as $step ) : ?>
				<?php
				$slot   = (string) ( $step['slot'] ?? '' );
				$status = (string) ( $step['status'] ?? '' );
				$model  = (string) ( $step['model'] ?? '' );
				$used   = (string) ( $step['model_used'] ?? '' );
				$code   = (string) ( $step['error_code'] ?? '' );
				$note   = (string) ( $step['message'] ?? '' );
				$slot_label = $labels[ $slot ] ?? $slot;
				$status_class = 'multch-admin-history-model-trace__step--' . sanitize_html_class( $status ?: 'unknown' );
				?>
				<li class="multch-admin-history-model-trace__step <?php echo esc_attr( $status_class ); ?>">
					<span class="multch-admin-history-model-trace__slot"><?php echo esc_html( $slot_label ); ?></span>
					<span class="multch-admin-history-model-trace__model"><code><?php echo esc_html( $model ); ?></code></span>
					<span class="multch-admin-history-model-trace__status"><?php echo esc_html( self::format_model_trace_status_label( $status ) ); ?></span>
					<?php if ( '' !== $used && ! multch_ai_client_models_match( $used, $model ) ) : ?>
						<span class="multch-admin-history-model-trace__used">
							→ <code><?php echo esc_html( $used ); ?></code>
						</span>
					<?php endif; ?>
					<?php if ( '' !== $code ) : ?>
						<code class="multch-admin-history-model-trace__code"><?php echo esc_html( $code ); ?></code>
					<?php endif; ?>
					<?php if ( '' !== $note ) : ?>
						<span class="multch-admin-history-model-trace__note"><?php echo esc_html( $note ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
		<?php if ( ! empty( $trace['model_final'] ) ) : ?>
			<p class="multch-admin-history-model-trace__final description">
				<?php
				printf(
					/* translators: %s: model ID that answered */
					esc_html__( 'Final model: %s', 'multiai-chatbot' ),
					esc_html( (string) $trace['model_final'] )
				);
				?>
			</p>
		<?php endif; ?>
	</div>
	<?php
}

private static function format_model_trace_status_label( string $status ): string {
	$labels = array(
		'success' => __( 'Success', 'multiai-chatbot' ),
		'failed'  => __( 'Failed', 'multiai-chatbot' ),
		'skipped' => __( 'Skipped', 'multiai-chatbot' ),
	);

	return $labels[ $status ] ?? $status;
}

/**
 * @param array<int, array<string, mixed>> $messages
 */
private static function render_history_messages_list( array $messages ): void {
	if ( empty( $messages ) ) {
		echo '<p class="multch-admin-history-messages__empty">' . esc_html__( 'No saved messages.', 'multiai-chatbot' ) . '</p>';
		return;
	}
	?>
	<div class="multch-admin-history-messages__list">
		<?php foreach ( $messages as $msg ) : ?>
			<?php
			$role         = (string) ( $msg['role'] ?? 'user' );
			$when         = Multch_Chat_History::format_datetime_local( (string) ( $msg['created_at'] ?? '' ) );
			$message_text = (string) ( $msg['content'] ?? '' );
			$msg_status   = (string) ( $msg['status'] ?? '' );
			$latency_ms   = (int) ( $msg['latency_ms'] ?? 0 );
			$is_assistant = 'assistant' === $role;
			$show_error   = $is_assistant && 'error' === $msg_status;
			$msg_meta     = array();
			if ( ! empty( $msg['meta_json'] ) ) {
				$decoded = json_decode( (string) $msg['meta_json'], true );
				if ( is_array( $decoded ) ) {
					$msg_meta = $decoded;
				}
			}

			$status_badge_class = 'multch-admin-status--err';
			if ( 'cached' === $msg_status ) {
				$status_badge_class = 'multch-admin-status--cached';
			} elseif ( in_array( $msg_status, array( 'success', 'active' ), true ) ) {
				$status_badge_class = 'multch-admin-status--ok';
			}
			?>
			<div class="multch-admin-history-msg multch-admin-history-msg--<?php echo esc_attr( $role ); ?>">
				<?php if ( $is_assistant ) : ?>
					<span class="multch-admin-history-msg__avatar" aria-hidden="true">AI</span>
				<?php endif; ?>
				<div class="multch-admin-history-msg__content">
				<div class="multch-admin-history-msg__head">
					<span class="multch-admin-history-msg__role">
						<?php echo esc_html( $is_assistant ? __( 'Assistant', 'multiai-chatbot' ) : __( 'User', 'multiai-chatbot' ) ); ?>
					</span>

					<time datetime="<?php echo esc_attr( (string) ( $msg['created_at'] ?? '' ) ); ?>">
						<?php echo esc_html( $when ); ?>
					</time>

					<?php if ( $is_assistant && $latency_ms > 0 ) : ?>
						<span class="multch-admin-history-msg__latency"><?php echo esc_html( number_format_i18n( $latency_ms ) ); ?> ms</span>
					<?php endif; ?>

					<?php if ( $show_error ) : ?>
						<span class="multch-admin-status <?php echo esc_attr( $status_badge_class ); ?>">
							<?php echo esc_html( self::format_history_status_label( $msg_status ) ); ?>
						</span>
					<?php endif; ?>
				</div>

				<div class="multch-admin-history-msg__body"><?php echo nl2br( esc_html( $message_text ) ); ?></div>
				<?php
				if ( $is_assistant && ! empty( $msg_meta['model_trace'] ) && is_array( $msg_meta['model_trace'] ) ) {
					self::render_history_model_trace( $msg_meta['model_trace'] );
				}
				?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

private static function format_history_status_label( string $status ): string {
	$labels = array(
		'active'  => __( 'Active', 'multiai-chatbot' ),
		'success' => __( 'Success', 'multiai-chatbot' ),
		'error'   => __( 'Error', 'multiai-chatbot' ),
		'cached'  => __( 'Cached', 'multiai-chatbot' ),
	);

	return $labels[ $status ] ?? $status;
}

private static function format_history_provider_label( string $provider, string $model = '' ): string {
	$labels = array(
		'wordpress_ai'      => __( 'WordPress AI', 'multiai-chatbot' ),
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
		'wordpress_ai'      => __( 'WordPress AI', 'multiai-chatbot' ),
		'gemini'            => 'Gemini',
		'deepseek'          => 'DeepSeek',
		'ollama'            => 'Ollama',
		'openai_compatible' => 'OpenAI-compatible',
	);

	return $labels[ $provider ] ?? $provider;
}

private static function format_history_provider_avatar( string $provider ): string {
	$labels = array(
		'wordpress_ai'      => 'WP',
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
