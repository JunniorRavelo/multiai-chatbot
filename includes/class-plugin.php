<?php
/**
 * Plugin bootstrap.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Multch_Plugin {

	private static ?Multch_Plugin $instance = null;

	public static function instance(): Multch_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 0 );

		Multch_Admin_Settings::init();
		Multch_Rest_Api::init();
		Multch_Enqueue::init();
	}

	public function init(): void {
		Multch_Migration::maybe_migrate();
		Multch_Chat_History::maybe_upgrade();
		Multch_Telemetry::maybe_upgrade();
		Multch_Admin_Settings::maybe_merge_settings();
		add_action( 'multch_purge_history', array( 'Multch_Chat_History', 'run_retention_purge' ) );
		add_action( 'multch_purge_telemetry', array( 'Multch_Telemetry', 'run_retention_purge' ) );

		if ( ! wp_next_scheduled( 'multch_purge_history' ) ) {
			wp_schedule_event( time(), 'daily', 'multch_purge_history' );
		}

		if ( ! wp_next_scheduled( 'multch_purge_telemetry' ) ) {
			wp_schedule_event( time(), 'daily', 'multch_purge_telemetry' );
		}
	}

	public function load_textdomain(): void {
		$domain = 'multiai-chatbot';
		$locale = determine_locale();
		$mofile = self::resolve_translation_file( $domain, $locale );

		if ( $mofile ) {
			load_textdomain( $domain, $mofile, $locale );
		}
	}

	/**
	 * Busca el .mo del locale activo y variantes cercanas (p. ej. es_CO → es_ES).
	 */
	private static function resolve_translation_file( string $domain, string $locale ): string {
		$candidates = array( $locale );
		$base       = strtok( $locale, '_' );

		if ( 'es' === $base && 'es_ES' !== $locale ) {
			$candidates[] = 'es_ES';
		}

		foreach ( array_unique( $candidates ) as $candidate ) {
			$mofile_name = $domain . '-' . $candidate . '.mo';

			$wp_lang_mofile = WP_LANG_DIR . '/plugins/' . $mofile_name;
			if ( is_readable( $wp_lang_mofile ) ) {
				return $wp_lang_mofile;
			}

			$plugin_mofile = MULTCH_PLUGIN_PATH . 'languages/' . $mofile_name;
			if ( is_readable( $plugin_mofile ) ) {
				return $plugin_mofile;
			}
		}

		return '';
	}

	public static function activate(): void {
		Multch_Migration::maybe_migrate();
		Multch_Telemetry::create_table();
		Multch_Chat_History::create_tables();

		$stored = get_option( 'multch_plugin_settings', false );
		if ( false === $stored ) {
			add_option( 'multch_plugin_settings', Multch_Admin_Settings::default_settings() );
		} else {
			Multch_Admin_Settings::maybe_merge_settings();
		}

		if ( ! wp_next_scheduled( 'multch_purge_history' ) ) {
			wp_schedule_event( time(), 'daily', 'multch_purge_history' );
		}

		if ( ! wp_next_scheduled( 'multch_purge_telemetry' ) ) {
			wp_schedule_event( time(), 'daily', 'multch_purge_telemetry' );
		}

		Multch_Rest_Api::register_stream_rewrite();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'multch_purge_history' );
		wp_clear_scheduled_hook( 'multch_purge_telemetry' );
		flush_rewrite_rules();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$settings = get_option( 'multch_plugin_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings = wp_parse_args( $settings, Multch_Admin_Settings::default_settings() );
		return self::apply_constant_overrides( $settings );
	}

	/**
	 * Permite sobrescribir opciones sensibles desde wp-config.php (equivalente a .env).
	 *
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private static function apply_constant_overrides( array $settings ): array {
		$string_map = array(
			'allowed_origins'             => 'MULTCH_ALLOWED_ORIGINS',
			'cache_ttl_seconds'           => 'MULTCH_CACHE_TTL_SECONDS',
			'telemetry_log_path'          => 'MULTCH_TELEMETRY_LOG_PATH',
			'rate_limit_per_minute'       => 'MULTCH_RATE_LIMIT_PER_MINUTE',
			'rate_limit_per_day'          => 'MULTCH_RATE_LIMIT_PER_DAY',
			'rate_limit_model_per_minute' => 'MULTCH_RATE_LIMIT_MODEL_PER_MINUTE',
			'rate_limit_model_per_day'    => 'MULTCH_RATE_LIMIT_MODEL_PER_DAY',
			'rate_limit_soft_threshold'   => 'MULTCH_RATE_LIMIT_SOFT_THRESHOLD',
			'ip_suspend_after_violations' => 'MULTCH_IP_SUSPEND_AFTER_VIOLATIONS',
			'ip_suspend_seconds'          => 'MULTCH_IP_SUSPEND_SECONDS',
			'internal_chat_base_url'      => 'MULTCH_INTERNAL_CHAT_BASE_URL',
			'provider'                    => 'MULTCH_PROVIDER',
			'model'                       => 'MULTCH_MODEL',
			'model_candidates'            => 'MULTCH_MODEL_CANDIDATES',
			'widget_title'                => 'MULTCH_WIDGET_TITLE',
			'widget_subtitle'             => 'MULTCH_WIDGET_SUBTITLE',
			'welcome_message'             => 'MULTCH_WELCOME_MESSAGE',
			'system_prompt'               => 'MULTCH_SYSTEM_PROMPT',
		);

		$legacy_map = array(
			'allowed_origins'             => 'CHATBOT_ALLOWED_ORIGINS',
			'cache_ttl_seconds'           => 'CHATBOT_CACHE_TTL_SECONDS',
			'telemetry_log_path'          => 'CHATBOT_TELEMETRY_LOG_PATH',
			'rate_limit_per_minute'       => 'CHATBOT_RATE_LIMIT_PER_MINUTE',
			'rate_limit_per_day'          => 'CHATBOT_RATE_LIMIT_PER_DAY',
			'rate_limit_model_per_minute' => 'CHATBOT_RATE_LIMIT_MODEL_PER_MINUTE',
			'rate_limit_model_per_day'    => 'CHATBOT_RATE_LIMIT_MODEL_PER_DAY',
			'rate_limit_soft_threshold'   => 'CHATBOT_RATE_LIMIT_SOFT_THRESHOLD',
			'ip_suspend_after_violations' => 'CHATBOT_IP_SUSPEND_AFTER_VIOLATIONS',
			'ip_suspend_seconds'          => 'CHATBOT_IP_SUSPEND_SECONDS',
			'internal_chat_base_url'      => 'CHATBOT_INTERNAL_CHAT_BASE_URL',
			'provider'                    => 'CHATBOT_PROVIDER',
			'model'                       => 'CHATBOT_MODEL',
			'model_candidates'            => 'CHATBOT_MODEL_CANDIDATES',
			'widget_title'                => 'CHATBOT_WIDGET_TITLE',
			'widget_subtitle'             => 'CHATBOT_WIDGET_SUBTITLE',
			'welcome_message'             => 'CHATBOT_WELCOME_MESSAGE',
			'system_prompt'               => 'CHATBOT_SYSTEM_PROMPT',
		);

		foreach ( $string_map as $key => $constant ) {
			$legacy = $legacy_map[ $key ] ?? null;
			$value  = multch_resolve_constant( $constant, $legacy );
			if ( '' !== $value ) {
				$settings[ $key ] = $value;
			}
		}

		if ( '' !== multch_resolve_constant( 'MULTCH_GEMINI_MODEL', 'CHATBOT_GEMINI_MODEL' ) && '' === multch_resolve_constant( 'MULTCH_MODEL', 'CHATBOT_MODEL' ) ) {
			if ( ( $settings['provider'] ?? '' ) === 'gemini' ) {
				$settings['model'] = multch_resolve_constant( 'MULTCH_GEMINI_MODEL', 'CHATBOT_GEMINI_MODEL' );
			}
		}

		if ( '' !== multch_resolve_constant( 'MULTCH_GEMINI_MODEL_CANDIDATES', 'CHATBOT_GEMINI_MODEL_CANDIDATES' ) && '' === multch_resolve_constant( 'MULTCH_MODEL_CANDIDATES', 'CHATBOT_MODEL_CANDIDATES' ) ) {
			if ( ( $settings['provider'] ?? '' ) === 'gemini' ) {
				$settings['model_candidates'] = multch_resolve_constant( 'MULTCH_GEMINI_MODEL_CANDIDATES', 'CHATBOT_GEMINI_MODEL_CANDIDATES' );
			}
		}

		if ( defined( 'MULTCH_STREAMING_ENABLED' ) || defined( 'CHATBOT_STREAMING_ENABLED' ) ) {
			$settings['streaming_enabled'] = multch_constant_is_true( 'MULTCH_STREAMING_ENABLED', 'CHATBOT_STREAMING_ENABLED' );
		}

		return $settings;
	}
}
