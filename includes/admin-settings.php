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
		add_action( 'wp_ajax_chatbot_history_detail', array( __CLASS__, 'ajax_history_detail' ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'widget_enabled'                 => true,
			'welcome_message'                => "Hola. Soy un agente de IA. Puedo cometer errores; verifica la información importante antes de tomar decisiones.\n\n¿En qué puedo ayudarte?",
			'system_prompt'                  => 'Eres un asistente útil del sitio web. Responde en español de forma clara, breve y amable. Si no sabes algo, dilo con honestidad.',
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
			'request_timeout'       => 22,
			'style_preset'          => 'default',
			'style_primary'         => '',
			'style_accent'          => '',
			'style_radius'          => '',
			'style_position'        => 'bottom-right',
			'style_offset'          => '1rem',
			'style_panel_width'     => '',
			'style_launcher_label'  => true,
			'widget_title'          => 'Agente IA',
			'widget_subtitle'       => 'Sistema en línea',
		);
	}

	public static function register_menu(): void {
		add_menu_page(
			__( 'Chatbot', 'chatbot-plugin-wp' ),
			__( 'Chatbot', 'chatbot-plugin-wp' ),
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
		$current  = Chatbot_Plugin::get_settings();

		$out = array();

		$out['widget_enabled']        = self::sanitize_checkbox( $input, $current, 'widget_enabled', (bool) $defaults['widget_enabled'] );
		$out['welcome_message']       = sanitize_textarea_field( $input['welcome_message'] ?? $defaults['welcome_message'] );
		$out['system_prompt']         = sanitize_textarea_field( $input['system_prompt'] ?? $defaults['system_prompt'] );
		$out['streaming_enabled']              = self::sanitize_checkbox( $input, $current, 'streaming_enabled', (bool) $defaults['streaming_enabled'] );
		$out['allowed_origins']                = self::sanitize_origins_list( (string) ( $input['allowed_origins'] ?? $current['allowed_origins'] ?? $defaults['allowed_origins'] ) );
		$out['cache_ttl_seconds']              = max( 0, min( 86400, (int) ( $input['cache_ttl_seconds'] ?? $current['cache_ttl_seconds'] ?? $defaults['cache_ttl_seconds'] ) ) );
		$out['telemetry_log_path']             = sanitize_text_field( (string) ( $input['telemetry_log_path'] ?? $current['telemetry_log_path'] ?? $defaults['telemetry_log_path'] ) );
		$out['rate_limit_per_minute']          = max( 1, min( 120, (int) ( $input['rate_limit_per_minute'] ?? $current['rate_limit_per_minute'] ?? $defaults['rate_limit_per_minute'] ) ) );
		$out['rate_limit_per_day']             = max( 1, min( 1000, (int) ( $input['rate_limit_per_day'] ?? $current['rate_limit_per_day'] ?? $defaults['rate_limit_per_day'] ) ) );
		$out['rate_limit_model_per_minute']    = max( 1, min( 120, (int) ( $input['rate_limit_model_per_minute'] ?? $current['rate_limit_model_per_minute'] ?? $defaults['rate_limit_model_per_minute'] ) ) );
		$out['rate_limit_model_per_day']       = max( 1, min( 5000, (int) ( $input['rate_limit_model_per_day'] ?? $current['rate_limit_model_per_day'] ?? $defaults['rate_limit_model_per_day'] ) ) );
		$out['rate_limit_soft_threshold']      = max( 0.1, min( 1.0, (float) ( $input['rate_limit_soft_threshold'] ?? $current['rate_limit_soft_threshold'] ?? $defaults['rate_limit_soft_threshold'] ) ) );
		$out['ip_suspend_after_violations']    = max( 1, min( 20, (int) ( $input['ip_suspend_after_violations'] ?? $current['ip_suspend_after_violations'] ?? $defaults['ip_suspend_after_violations'] ) ) );
		$out['ip_suspend_seconds']             = max( 60, min( 86400, (int) ( $input['ip_suspend_seconds'] ?? $current['ip_suspend_seconds'] ?? $defaults['ip_suspend_seconds'] ) ) );
		$out['internal_chat_base_url']         = esc_url_raw( (string) ( $input['internal_chat_base_url'] ?? $current['internal_chat_base_url'] ?? $defaults['internal_chat_base_url'] ) );

		$provider = sanitize_key( $input['provider'] ?? 'gemini' );
		$out['provider'] = in_array( $provider, array( 'gemini', 'ollama', 'openai_compatible' ), true ) ? $provider : 'gemini';

		$new_key = isset( $input['api_key'] ) ? trim( (string) $input['api_key'] ) : '';
		if ( '' !== $new_key ) {
			$out['api_key'] = $new_key;
		} else {
			$out['api_key'] = (string) ( $current['api_key'] ?? '' );
		}

		$out['model']            = sanitize_text_field( $input['model'] ?? $defaults['model'] );
		$out['model_candidates'] = sanitize_text_field( $input['model_candidates'] ?? $defaults['model_candidates'] );
		$out['ollama_base_url']  = esc_url_raw( $input['ollama_base_url'] ?? $defaults['ollama_base_url'] );
		$out['openai_base_url']  = esc_url_raw( $input['openai_base_url'] ?? $defaults['openai_base_url'] );
		$out['request_timeout']  = max( 5, min( 120, (int) ( $input['request_timeout'] ?? $defaults['request_timeout'] ) ) );

		$preset = sanitize_key( $input['style_preset'] ?? 'default' );
		$out['style_preset'] = in_array( $preset, self::style_presets(), true ) ? $preset : 'default';
		$out['style_primary']  = sanitize_hex_color( $input['style_primary'] ?? '' ) ?: '';
		$out['style_accent']   = sanitize_hex_color( $input['style_accent'] ?? '' ) ?: '';
		$out['style_radius']   = self::sanitize_css_size( $input['style_radius'] ?? '' );
		$position = sanitize_key( $input['style_position'] ?? 'bottom-right' );
		$out['style_position'] = in_array( $position, self::style_positions(), true ) ? $position : 'bottom-right';
		$out['style_offset']       = self::sanitize_css_size( $input['style_offset'] ?? '1rem' ) ?: '1rem';
		$out['style_panel_width']  = self::sanitize_css_size( $input['style_panel_width'] ?? '' );
		$out['style_launcher_label'] = self::sanitize_checkbox( $input, $current, 'style_launcher_label', (bool) $defaults['style_launcher_label'] );

		$out['widget_title']    = sanitize_text_field( $input['widget_title'] ?? $defaults['widget_title'] );
		$out['widget_subtitle'] = sanitize_text_field( $input['widget_subtitle'] ?? $defaults['widget_subtitle'] );

		return wp_parse_args( $out, $defaults );
	}

	public static function enqueue_admin_assets( string $hook ): void {
		if ( 'toplevel_page_chatbot-plugin' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'chatbot-plugin-admin',
			CHATBOT_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			CHATBOT_PLUGIN_VERSION
		);

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'general';
		if ( 'style' === $tab ) {
			wp_enqueue_style( 'wp-color-picker' );

			wp_enqueue_style(
				'chatbot-plugin-admin-preview',
				CHATBOT_PLUGIN_URL . 'assets/css/chatbot.css',
				array( 'chatbot-plugin-admin' ),
				CHATBOT_PLUGIN_VERSION
			);

			wp_enqueue_script(
				'chatbot-plugin-admin-style',
				CHATBOT_PLUGIN_URL . 'assets/js/admin-style.js',
				array( 'wp-color-picker' ),
				CHATBOT_PLUGIN_VERSION,
				true
			);

			$settings = Chatbot_Plugin::get_settings();
			wp_localize_script(
				'chatbot-plugin-admin-style',
				'chatbotStylePreview',
				array(
					'optionKey'       => self::OPTION_KEY,
					'widgetTitle'     => (string) ( $settings['widget_title'] ?? '' ),
					'widgetSubtitle'  => (string) ( $settings['widget_subtitle'] ?? '' ),
					'welcomeMessage'  => (string) ( $settings['welcome_message'] ?? '' ),
					'i18n'            => array(
						'openPanel'  => __( 'Abrir panel', 'chatbot-plugin-wp' ),
						'closePanel' => __( 'Cerrar panel', 'chatbot-plugin-wp' ),
					),
					'positionLabels'  => self::style_position_labels(),
				)
			);
		}

		if ( 'history' === $tab ) {
			wp_enqueue_script(
				'chatbot-plugin-admin-history',
				CHATBOT_PLUGIN_URL . 'assets/js/admin-history.js',
				array(),
				CHATBOT_PLUGIN_VERSION,
				true
			);

			wp_localize_script(
				'chatbot-plugin-admin-history',
				'chatbotHistoryAdmin',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'chatbot_history_detail' ),
					'i18n'    => array(
						'loading' => __( 'Cargando mensajes…', 'chatbot-plugin-wp' ),
						'error'   => __( 'No se pudo cargar la conversación.', 'chatbot-plugin-wp' ),
					),
				)
			);
		}
	}

	/**
	 * @return list<string>
	 */
	public static function style_presets(): array {
		return array( 'default', 'dark-glass', 'minimal', 'ocean' );
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
				'desc'       => __( 'Azul índigo con violeta suave. Profesional y confiable.', 'chatbot-plugin-wp' ),
				'badge'      => __( 'Claro', 'chatbot-plugin-wp' ),
				'badge_type' => 'light',
				'colors'     => array( '#2563eb', '#6366f1', '#ffffff' ),
			),
			'dark-glass' => array(
				'label'      => __( 'Midnight', 'chatbot-plugin-wp' ),
				'desc'       => __( 'Oscuro profundo con acentos cian y violeta. Cabecera legible.', 'chatbot-plugin-wp' ),
				'badge'      => __( 'Oscuro', 'chatbot-plugin-wp' ),
				'badge_type' => 'dark',
				'colors'     => array( '#38bdf8', '#a78bfa', '#0f172a' ),
			),
			'minimal'    => array(
				'label'      => __( 'Monochrome', 'chatbot-plugin-wp' ),
				'desc'       => __( 'Zinc neutro, bordes rectos y sombras discretas.', 'chatbot-plugin-wp' ),
				'badge'      => __( 'Neutro', 'chatbot-plugin-wp' ),
				'badge_type' => 'neutral',
				'colors'     => array( '#27272a', '#71717a', '#ffffff' ),
			),
			'ocean'      => array(
				'label'      => __( 'Aqua', 'chatbot-plugin-wp' ),
				'desc'       => __( 'Cian profundo con destellos turquesa. Fresco y moderno.', 'chatbot-plugin-wp' ),
				'badge'      => __( 'Claro', 'chatbot-plugin-wp' ),
				'badge_type' => 'light',
				'colors'     => array( '#0e7490', '#22d3ee', '#f0fdff' ),
			),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function style_position_labels(): array {
		return array(
			'bottom-right'  => __( 'Abajo derecha', 'chatbot-plugin-wp' ),
			'center-right'  => __( 'Centro derecha', 'chatbot-plugin-wp' ),
			'bottom-left'   => __( 'Abajo izquierda', 'chatbot-plugin-wp' ),
			'center-left'   => __( 'Centro izquierda', 'chatbot-plugin-wp' ),
			'bottom-center' => __( 'Abajo centro', 'chatbot-plugin-wp' ),
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
			wp_die( esc_html__( 'Sin permisos.', 'chatbot-plugin-wp' ) );
		}
		check_admin_referer( 'chatbot_export_csv' );

		$days = isset( $_GET['days'] ) ? max( 1, min( 365, (int) $_GET['days'] ) ) : 30;
		$csv  = Chatbot_Telemetry::export_csv( $days );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=chatbot-telemetry-' . gmdate( 'Y-m-d' ) . '.csv' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
			'model'    => __( 'Modelo IA', 'chatbot-plugin-wp' ),
			'security' => __( 'Seguridad', 'chatbot-plugin-wp' ),
			'style'    => __( 'Estilo del chat', 'chatbot-plugin-wp' ),
			'stats'    => __( 'Estadísticas', 'chatbot-plugin-wp' ),
			'history'  => __( 'Historial', 'chatbot-plugin-wp' ),
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
					<h1><?php esc_html_e( 'Chatbot Plugin', 'chatbot-plugin-wp' ); ?></h1>
				</div>
				<span class="chatbot-admin-badge <?php echo $widget_on ? 'chatbot-admin-badge--on' : 'chatbot-admin-badge--off'; ?>">
					<?php
					echo $widget_on
						? esc_html__( 'Widget activo', 'chatbot-plugin-wp' )
						: esc_html__( 'Widget desactivado', 'chatbot-plugin-wp' );
					?>
				</span>
			</header>

			<nav class="nav-tab-wrapper chatbot-admin-nav" aria-label="<?php esc_attr_e( 'Secciones de configuración', 'chatbot-plugin-wp' ); ?>">
				<?php foreach ( $tabs as $id => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=chatbot-plugin&tab=' . $id ) ); ?>"
						class="nav-tab<?php echo $tab === $id ? ' nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

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

					<div class="chatbot-admin-footer">
						<?php submit_button( __( 'Guardar cambios', 'chatbot-plugin-wp' ), 'primary', 'submit', false ); ?>
						<span class="chatbot-admin-footer__hint">
							<?php esc_html_e( 'Los cambios se aplican de inmediato en el sitio público.', 'chatbot-plugin-wp' ); ?>
						</span>
					</div>
				</form>
			<?php endif; ?>

			<?php Chatbot_Donation_Footer::render(); ?>
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
	 * @param array<string, mixed> $settings
	 */
	private static function render_general_fields( array $settings ): void {
		self::card_open(
			__( 'Visibilidad y textos', 'chatbot-plugin-wp' ),
			__( 'Controla dónde aparece el chat y los mensajes que ve el visitante.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Widget global', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<label class="chatbot-admin-toggle">
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[widget_enabled]" value="0" />
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[widget_enabled]" value="1" <?php checked( ! empty( $settings['widget_enabled'] ) ); ?> />
						<span><?php esc_html_e( 'Mostrar en todo el sitio (wp_footer)', 'chatbot-plugin-wp' ); ?></span>
					</label>
					<p class="description"><?php esc_html_e( 'También puedes usar el shortcode [chatbot_widget].', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Mensaje de bienvenida', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<textarea name="<?php echo esc_attr( self::OPTION_KEY ); ?>[welcome_message]" rows="4" class="large-text"><?php echo esc_textarea( (string) $settings['welcome_message'] ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Instrucciones del sistema', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<textarea name="<?php echo esc_attr( self::OPTION_KEY ); ?>[system_prompt]" rows="5" class="large-text"><?php echo esc_textarea( (string) $settings['system_prompt'] ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Streaming simulado', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<label class="chatbot-admin-toggle">
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[streaming_enabled]" value="0" />
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[streaming_enabled]" value="1" <?php checked( ! empty( $settings['streaming_enabled'] ) ); ?> />
						<span><?php esc_html_e( 'Activar respuesta por trozos', 'chatbot-plugin-wp' ); ?></span>
					</label>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();

		self::card_open(
			__( 'Cabecera del widget', 'chatbot-plugin-wp' ),
			__( 'Título y subtítulo mostrados en la barra superior del chat.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Título del widget', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[widget_title]" value="<?php echo esc_attr( (string) $settings['widget_title'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Subtítulo', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[widget_subtitle]" value="<?php echo esc_attr( (string) $settings['widget_subtitle'] ); ?>" />
				</td>
			</tr>
		</table>
		<?php
		self::card_close();
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function render_security_fields( array $settings ): void {
		$site_origin = esc_url( home_url( '/' ) );
		self::card_open(
			__( 'Orígenes y acceso', 'chatbot-plugin-wp' ),
			__( 'Controla desde qué dominios pueden llamar al endpoint del chat.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Orígenes permitidos', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<textarea name="<?php echo esc_attr( self::OPTION_KEY ); ?>[allowed_origins]" rows="3" class="large-text code" placeholder="<?php echo esc_attr( $site_origin ); ?>"><?php echo esc_textarea( (string) ( $settings['allowed_origins'] ?? '' ) ); ?></textarea>
					<p class="description">
						<?php
						printf(
							/* translators: %s: site home URL */
							esc_html__( 'URLs separadas por coma. Vacío = solo este sitio (%s). Equivalente a CHAT_ALLOWED_ORIGINS.', 'chatbot-plugin-wp' ),
							esc_html( $site_origin )
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'URL interna del chat', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="url" class="regular-text code" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[internal_chat_base_url]" value="<?php echo esc_attr( (string) ( $settings['internal_chat_base_url'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( untrailingslashit( home_url() ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Opcional. Usada por el streaming para llamar a /chat sin saltos públicos. Equivalente a INTERNAL_CHAT_BASE_URL.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();

		self::card_open(
			__( 'Caché y telemetría', 'chatbot-plugin-wp' ),
			__( 'Reduce llamadas repetidas al modelo y registra eventos en archivo opcional.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'TTL de caché (segundos)', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="0" max="86400" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[cache_ttl_seconds]" value="<?php echo esc_attr( (string) ( $settings['cache_ttl_seconds'] ?? 1800 ) ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( '0 = desactivar caché. Equivalente a CHAT_CACHE_TTL_SECONDS.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Ruta de log de telemetría', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" class="large-text code" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[telemetry_log_path]" value="<?php echo esc_attr( (string) ( $settings['telemetry_log_path'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( WP_CONTENT_DIR . '/chatbot-telemetry.log' ); ?>" />
					<p class="description"><?php esc_html_e( 'Opcional. Además de la base de datos, escribe eventos en este archivo. Equivalente a CHAT_TELEMETRY_LOG_PATH.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();

		self::card_open(
			__( 'Límites de tasa', 'chatbot-plugin-wp' ),
			__( 'Protege el endpoint y la cuota del proveedor de IA contra abuso.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Por IP / minuto', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="1" max="120" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rate_limit_per_minute]" value="<?php echo esc_attr( (string) ( $settings['rate_limit_per_minute'] ?? 10 ) ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'CHAT_RATE_LIMIT_PER_MINUTE', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Por IP / día', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="1" max="1000" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rate_limit_per_day]" value="<?php echo esc_attr( (string) ( $settings['rate_limit_per_day'] ?? 30 ) ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'CHAT_RATE_LIMIT_PER_DAY', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Modelo / minuto (global)', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="1" max="120" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rate_limit_model_per_minute]" value="<?php echo esc_attr( (string) ( $settings['rate_limit_model_per_minute'] ?? 6 ) ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'CHAT_RATE_LIMIT_MODEL_PER_MINUTE', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Modelo / día (global)', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="1" max="5000" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rate_limit_model_per_day]" value="<?php echo esc_attr( (string) ( $settings['rate_limit_model_per_day'] ?? 24 ) ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'CHAT_RATE_LIMIT_MODEL_PER_DAY', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Umbral suave', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="0.1" max="1" step="0.05" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rate_limit_soft_threshold]" value="<?php echo esc_attr( (string) ( $settings['rate_limit_soft_threshold'] ?? 0.8 ) ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'Fracción del límite (0.1–1) a partir de la cual se registra advertencia. CHAT_RATE_LIMIT_SOFT_THRESHOLD', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Suspender IP tras violaciones', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="1" max="20" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ip_suspend_after_violations]" value="<?php echo esc_attr( (string) ( $settings['ip_suspend_after_violations'] ?? 3 ) ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'CHAT_IP_SUSPEND_AFTER_VIOLATIONS', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Duración suspensión (seg)', 'chatbot-plugin-wp' ); ?></th>
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
			__( 'Proveedor de IA', 'chatbot-plugin-wp' ),
			__( 'Elige el motor y configura credenciales y modelos.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Proveedor', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[provider]" id="chatbot-provider">
						<option value="gemini" <?php selected( $provider, 'gemini' ); ?>>Google Gemini</option>
						<option value="ollama" <?php selected( $provider, 'ollama' ); ?>>Ollama</option>
						<option value="openai_compatible" <?php selected( $provider, 'openai_compatible' ); ?>>OpenAI-compatible</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Modelo', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[model]" value="<?php echo esc_attr( (string) $settings['model'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Ej: gemini-3.1-flash-lite, llama3, gpt-4o-mini. Equivalente a GEMINI_MODEL.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr class="chatbot-field-gemini">
				<th scope="row"><?php esc_html_e( 'Modelo de respaldo', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" class="large-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[model_candidates]" value="<?php echo esc_attr( (string) $settings['model_candidates'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Solo Gemini. Pool de rotación separado por coma (429/404/400 prueba el siguiente). Equivalente a GEMINI_MODEL_CANDIDATES.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr class="chatbot-field-ollama">
				<th scope="row"><?php esc_html_e( 'URL base Ollama', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="url" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ollama_base_url]" value="<?php echo esc_attr( (string) $settings['ollama_base_url'] ); ?>" />
				</td>
			</tr>
			<tr class="chatbot-field-openai">
				<th scope="row"><?php esc_html_e( 'URL base OpenAI-compatible', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="url" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[openai_base_url]" value="<?php echo esc_attr( (string) $settings['openai_base_url'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Timeout (segundos)', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="5" max="120" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[request_timeout]" value="<?php echo esc_attr( (string) $settings['request_timeout'] ); ?>" />
				</td>
			</tr>
			<tr class="chatbot-field-api-key">
				<th scope="row"><?php esc_html_e( 'API Key', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="password" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_key]" value="" placeholder="<?php echo ! empty( $settings['api_key'] ) ? '••••••••' : ''; ?>" autocomplete="new-password" />
					<p class="description"><?php esc_html_e( 'Deja vacío para mantener la clave actual. En producción define CHATBOT_GEMINI_API_KEY o CHATBOT_OPENAI_API_KEY en wp-config.php (equivalente a GEMINI_API_KEY).', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
		</table>
		<script>
		(function () {
			const sel = document.getElementById('chatbot-provider');
			if (!sel) return;
			function toggle() {
				const v = sel.value;
				document.querySelectorAll('.chatbot-field-api-key').forEach(el => {
					el.style.display = v === 'ollama' ? 'none' : '';
				});
				document.querySelectorAll('.chatbot-field-gemini').forEach(el => {
					el.style.display = v === 'gemini' ? '' : 'none';
				});
				document.querySelectorAll('.chatbot-field-ollama').forEach(el => {
					el.style.display = v === 'ollama' ? '' : 'none';
				});
				document.querySelectorAll('.chatbot-field-openai').forEach(el => {
					el.style.display = v === 'openai_compatible' ? '' : 'none';
				});
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
			__( 'Tema visual', 'chatbot-plugin-wp' ),
			__( 'Paleta de colores, tipografía y formas del chat.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Tema', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_preset]" id="chatbot-style-preset">
						<?php foreach ( self::style_presets() as $id ) : ?>
							<?php
							$meta = $preset_meta[ $id ] ?? array(
								'label' => $id,
								'desc'  => '',
								'badge' => '',
							);
							$badge = (string) ( $meta['badge'] ?? '' );
							$option_label = $badge !== ''
								? sprintf( '%s (%s)', $meta['label'], $badge )
								: (string) $meta['label'];
							?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $preset, $id ); ?>>
								<?php echo esc_html( $option_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description" id="chatbot-style-preset-desc">
						<?php
						$current_meta = $preset_meta[ $preset ] ?? array( 'desc' => '' );
						echo esc_html( (string) ( $current_meta['desc'] ?? '' ) );
						?>
					</p>
				</td>
			</tr>
		</table>
		<script>
		(function () {
			const sel = document.getElementById('chatbot-style-preset');
			const desc = document.getElementById('chatbot-style-preset-desc');
			if (!sel || !desc) return;
			const descriptions = <?php echo wp_json_encode( array_map( static fn( $m ) => $m['desc'] ?? '', $preset_meta ) ); ?>;
			function updateDesc() {
				desc.textContent = descriptions[sel.value] || '';
			}
			sel.addEventListener('change', updateDesc);
		})();
		</script>
		<?php
		self::card_close();

		self::card_open(
			__( 'Colores y forma', 'chatbot-plugin-wp' ),
			__( 'Opcional: sobrescribe los colores del preset seleccionado.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Color primario', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" class="chatbot-color-picker" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_primary]" value="<?php echo esc_attr( (string) $settings['style_primary'] ); ?>" placeholder="#2563eb" data-default-color="#2563eb" />
					<p class="description"><?php esc_html_e( 'Botón de envío, burbujas del usuario y acentos.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Color acento', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" class="chatbot-color-picker" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_accent]" value="<?php echo esc_attr( (string) $settings['style_accent'] ); ?>" placeholder="#7c3aed" data-default-color="#7c3aed" />
					<p class="description"><?php esc_html_e( 'Degradado del botón flotante.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Radio de borde', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_radius]" value="<?php echo esc_attr( (string) $settings['style_radius'] ); ?>" placeholder="1.5rem" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Ej: 0.75rem, 1.5rem, 16px', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Ancho del panel', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_panel_width]" value="<?php echo esc_attr( (string) ( $settings['style_panel_width'] ?? '' ) ); ?>" placeholder="380px" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Vacío = ancho adaptable (máx. 380px).', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();

		self::card_open(
			__( 'Posición en pantalla', 'chatbot-plugin-wp' ),
			__( 'Dónde aparece el panel y el botón flotante en el sitio.', 'chatbot-plugin-wp' )
		);
		?>
		<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_position]" value="<?php echo esc_attr( $position ); ?>" id="chatbot-style-position-input" />
		<div class="chatbot-position-picker">
			<div class="chatbot-position-map" role="group" aria-label="<?php esc_attr_e( 'Posición del widget', 'chatbot-plugin-wp' ); ?>">
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
		</div>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Margen al borde', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_offset]" value="<?php echo esc_attr( (string) ( $settings['style_offset'] ?? '1rem' ) ); ?>" placeholder="1rem" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Texto en botón flotante', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<label class="chatbot-admin-toggle">
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_launcher_label]" value="0" />
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_launcher_label]" value="1" <?php checked( ! empty( $settings['style_launcher_label'] ) ); ?> />
						<span><?php esc_html_e( 'Mostrar título junto al icono 💬', 'chatbot-plugin-wp' ); ?></span>
					</label>
					<p class="description"><?php esc_html_e( 'El título se configura en General → Cabecera del widget.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();
		?>
			</div>
			<div class="chatbot-admin-preview-card">
				<div class="chatbot-admin-card">
					<div class="chatbot-admin-card__head chatbot-admin-preview__head">
						<div>
							<h2><?php esc_html_e( 'Vista previa', 'chatbot-plugin-wp' ); ?></h2>
							<p><?php esc_html_e( 'Interactiva: prueba abrir/cerrar y cambia opciones al instante.', 'chatbot-plugin-wp' ); ?></p>
						</div>
						<button type="button" class="button button-secondary" id="chatbot-preview-toggle" aria-pressed="false">
							<?php esc_html_e( 'Abrir panel', 'chatbot-plugin-wp' ); ?>
						</button>
					</div>
					<div class="chatbot-admin-card__body">
						<div class="chatbot-admin-preview">
							<div class="chatbot-admin-preview__viewport" id="chatbot-preview-viewport" aria-label="<?php esc_attr_e( 'Simulación de página web', 'chatbot-plugin-wp' ); ?>">
								<div class="chatbot-admin-preview__page-mock">
									<span></span><span></span><span></span>
								</div>
							</div>
							<p class="chatbot-admin-preview__hint"><?php esc_html_e( 'Los cambios se aplican al guardar en el sitio público.', 'chatbot-plugin-wp' ); ?></p>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}


	private static function render_stats_tab(): void {
		$days    = isset( $_GET['days'] ) ? max( 7, min( 90, (int) $_GET['days'] ) ) : 30;
		$summary = Chatbot_Telemetry::get_summary( $days );
		$page    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$per     = 25;
		$events  = Chatbot_Telemetry::get_recent_events( $per, ( $page - 1 ) * $per );
		$total   = Chatbot_Telemetry::count_events();

		$totals = $summary['totals'] ?? array();
		$export_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=chatbot_export_csv&days=' . $days ),
			'chatbot_export_csv'
		);

		$periods = array( 7, 30, 90 );
		?>
		<div class="chatbot-admin-stats-toolbar">
			<p><?php esc_html_e( 'Telemetría de uso del chatbot en tu sitio.', 'chatbot-plugin-wp' ); ?></p>
			<div style="display:flex;flex-wrap:wrap;align-items:center;gap:0.75rem;">
				<div class="chatbot-admin-pills" role="group" aria-label="<?php esc_attr_e( 'Periodo', 'chatbot-plugin-wp' ); ?>">
					<?php foreach ( $periods as $p ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=chatbot-plugin&tab=stats&days=' . $p ) ); ?>"
							class="<?php echo (int) $days === $p ? 'is-active' : ''; ?>">
							<?php echo esc_html( sprintf( /* translators: %d: number of days */ __( '%dd', 'chatbot-plugin-wp' ), $p ) ); ?>
						</a>
					<?php endforeach; ?>
				</div>
				<a class="button chatbot-admin-export" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Exportar CSV', 'chatbot-plugin-wp' ); ?></a>
			</div>
		</div>

		<div class="chatbot-admin-kpi-grid">
			<div class="chatbot-admin-kpi">
				<span class="chatbot-admin-kpi__label"><?php esc_html_e( 'Total peticiones', 'chatbot-plugin-wp' ); ?></span>
				<span class="chatbot-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $totals['total_requests'] ?? 0 ) ) ); ?></span>
			</div>
			<div class="chatbot-admin-kpi chatbot-admin-kpi--success">
				<span class="chatbot-admin-kpi__label"><?php esc_html_e( 'Éxitos', 'chatbot-plugin-wp' ); ?></span>
				<span class="chatbot-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $totals['success_count'] ?? 0 ) ) ); ?></span>
			</div>
			<div class="chatbot-admin-kpi chatbot-admin-kpi--error">
				<span class="chatbot-admin-kpi__label"><?php esc_html_e( 'Errores', 'chatbot-plugin-wp' ); ?></span>
				<span class="chatbot-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $totals['error_count'] ?? 0 ) ) ); ?></span>
			</div>
			<div class="chatbot-admin-kpi">
				<span class="chatbot-admin-kpi__label"><?php esc_html_e( 'Latencia media', 'chatbot-plugin-wp' ); ?></span>
				<span class="chatbot-admin-kpi__value"><?php echo esc_html( number_format_i18n( (float) ( $totals['avg_latency_ms'] ?? 0 ), 0 ) ); ?> <small style="font-size:0.55em;font-weight:600;color:var(--cb-admin-muted);">ms</small></span>
			</div>
		</div>

		<div class="chatbot-admin-stats-grid">
			<div class="chatbot-admin-card">
				<div class="chatbot-admin-card__head">
					<h2><?php esc_html_e( 'Por estado', 'chatbot-plugin-wp' ); ?></h2>
				</div>
				<div class="chatbot-admin-card__body">
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Estado', 'chatbot-plugin-wp' ); ?></th><th><?php esc_html_e( 'Cantidad', 'chatbot-plugin-wp' ); ?></th></tr></thead>
						<tbody>
							<?php
							$by_status = (array) ( $summary['by_status'] ?? array() );
							if ( empty( $by_status ) ) :
								?>
								<tr><td colspan="2"><?php esc_html_e( 'Sin datos en este periodo.', 'chatbot-plugin-wp' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $by_status as $row ) : ?>
									<tr><td><?php echo esc_html( (string) ( $row['status'] ?? '' ) ); ?></td><td><?php echo esc_html( number_format_i18n( (int) ( $row['count'] ?? 0 ) ) ); ?></td></tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
			<div class="chatbot-admin-card">
				<div class="chatbot-admin-card__head">
					<h2><?php esc_html_e( 'Por proveedor', 'chatbot-plugin-wp' ); ?></h2>
				</div>
				<div class="chatbot-admin-card__body">
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Proveedor', 'chatbot-plugin-wp' ); ?></th><th><?php esc_html_e( 'Cantidad', 'chatbot-plugin-wp' ); ?></th></tr></thead>
						<tbody>
							<?php
							$by_provider = (array) ( $summary['by_provider'] ?? array() );
							if ( empty( $by_provider ) ) :
								?>
								<tr><td colspan="2"><?php esc_html_e( 'Sin datos en este periodo.', 'chatbot-plugin-wp' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $by_provider as $row ) : ?>
									<tr><td><?php echo esc_html( (string) ( $row['provider'] ?? '' ) ); ?></td><td><?php echo esc_html( number_format_i18n( (int) ( $row['count'] ?? 0 ) ) ); ?></td></tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<div class="chatbot-admin-card chatbot-admin-events">
			<div class="chatbot-admin-card__head">
				<h2><?php esc_html_e( 'Últimos eventos', 'chatbot-plugin-wp' ); ?></h2>
				<p><?php esc_html_e( 'Registro detallado de las peticiones más recientes.', 'chatbot-plugin-wp' ); ?></p>
			</div>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Fecha', 'chatbot-plugin-wp' ); ?></th>
						<th><?php esc_html_e( 'Proveedor', 'chatbot-plugin-wp' ); ?></th>
						<th><?php esc_html_e( 'Modelo', 'chatbot-plugin-wp' ); ?></th>
						<th><?php esc_html_e( 'Estado', 'chatbot-plugin-wp' ); ?></th>
						<th><?php esc_html_e( 'Latencia', 'chatbot-plugin-wp' ); ?></th>
						<th><?php esc_html_e( 'Error', 'chatbot-plugin-wp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $events ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'Sin eventos aún.', 'chatbot-plugin-wp' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $events as $event ) : ?>
							<?php
							$status = (string) ( $event['status'] ?? '' );
							$is_ok  = in_array( $status, array( 'ok', 'success' ), true );
							?>
							<tr>
								<td><?php echo esc_html( (string) ( $event['created_at'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $event['provider'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $event['model'] ?? '' ) ); ?></td>
								<td>
									<span class="chatbot-admin-status <?php echo $is_ok ? 'chatbot-admin-status--ok' : 'chatbot-admin-status--err'; ?>">
										<?php echo esc_html( $status ); ?>
									</span>
								</td>
								<td><?php echo esc_html( number_format_i18n( (int) ( $event['latency_ms'] ?? 0 ) ) ); ?> ms</td>
								<td><?php echo esc_html( (string) ( $event['error_code'] ?? '—' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		<?php
		$pages = (int) ceil( $total / $per );
		if ( $pages > 1 ) {
			echo '<nav class="chatbot-admin-tablenav" aria-label="' . esc_attr__( 'Paginación', 'chatbot-plugin-wp' ) . '">';
			for ( $i = 1; $i <= min( $pages, 10 ); $i++ ) {
				$url = admin_url( 'admin.php?page=chatbot-plugin&tab=stats&days=' . $days . '&paged=' . $i );
				$class = $page === $i ? ' style="color:var(--cb-admin-primary);border-color:var(--cb-admin-primary);"' : '';
				echo '<a href="' . esc_url( $url ) . '"' . $class . '>' . esc_html( (string) $i ) . '</a>';
			}
			echo '</nav>';
		}
		?>
		</div>
		<?php
	}

	private static function render_history_tab(): void {
		$expanded_id = isset( $_GET['conversation'] ) ? (int) $_GET['conversation'] : 0;

		$days     = isset( $_GET['days'] ) ? max( 0, min( 365, (int) $_GET['days'] ) ) : 30;
		$page     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$per      = 12;
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : '';
		$provider = isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( (string) $_GET['provider'] ) ) : 'all';
		$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( (string) $_GET['status'] ) ) : 'all';
		$orderby  = isset( $_GET['orderby'] ) && 'started_at' === $_GET['orderby'] ? 'started_at' : 'updated_at';
		$order    = isset( $_GET['order'] ) && 'asc' === $_GET['order'] ? 'asc' : 'desc';

		$query_args = array(
			'days'     => $days > 0 ? $days : 0,
			'search'   => $search,
			'provider' => $provider,
			'status'   => $status,
			'per_page' => $per,
			'offset'   => ( $page - 1 ) * $per,
			'orderby'  => $orderby,
			'order'    => $order,
		);

		$items = Chatbot_Chat_History::list_conversations( $query_args );
		$total = Chatbot_Chat_History::count_conversations( $query_args );
		$pages = (int) ceil( $total / $per );

		$periods = array(
			0   => __( 'Todo', 'chatbot-plugin-wp' ),
			7   => __( '7 días', 'chatbot-plugin-wp' ),
			30  => __( '30 días', 'chatbot-plugin-wp' ),
			90  => __( '90 días', 'chatbot-plugin-wp' ),
		);

		$base_url = admin_url( 'admin.php?page=chatbot-plugin&tab=history' );
		$count_label = sprintf(
			/* translators: %s: number of conversations */
			_n( '%s conversación', '%s conversaciones', $total, 'chatbot-plugin-wp' ),
			number_format_i18n( $total )
		);
		$active_period = $periods[ $days ] ?? ( $days > 0 ? sprintf(
			/* translators: %d: number of days */
			__( '%d días', 'chatbot-plugin-wp' ),
			$days
		) : __( 'Todo', 'chatbot-plugin-wp' ) );
		?>
		<div class="chatbot-admin-card chatbot-admin-history-panel">
			<div class="chatbot-admin-card__head chatbot-admin-history-panel__head">
				<div class="chatbot-admin-history-toolbar">
					<div class="chatbot-admin-history-toolbar__intro">
						<h2><?php esc_html_e( 'Conversaciones', 'chatbot-plugin-wp' ); ?></h2>
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
					<div class="chatbot-admin-history-toolbar__period">
						<div class="chatbot-admin-pills chatbot-admin-pills--history" role="group" aria-label="<?php esc_attr_e( 'Periodo', 'chatbot-plugin-wp' ); ?>">
							<?php foreach ( $periods as $p => $label ) : ?>
								<?php
								$url = add_query_arg(
									array(
										'page'     => 'chatbot-plugin',
										'tab'      => 'history',
										'days'     => $p,
										's'        => $search,
										'provider' => $provider,
										'status'   => $status,
										'orderby'  => $orderby,
										'order'    => $order,
									),
									admin_url( 'admin.php' )
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
					<?php if ( $days > 0 ) : ?>
						<input type="hidden" name="days" value="<?php echo esc_attr( (string) $days ); ?>" />
					<?php endif; ?>
					<div class="chatbot-admin-history-filters__field chatbot-admin-history-filters__field--search">
						<label for="chatbot-history-search"><?php esc_html_e( 'Buscar', 'chatbot-plugin-wp' ); ?></label>
						<input type="search" id="chatbot-history-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'ID, título, ruta o sesión…', 'chatbot-plugin-wp' ); ?>" />
					</div>
					<div class="chatbot-admin-history-filters__field">
						<label for="chatbot-history-provider"><?php esc_html_e( 'Proveedor', 'chatbot-plugin-wp' ); ?></label>
						<select id="chatbot-history-provider" name="provider">
							<option value="all"<?php selected( $provider, 'all' ); ?>><?php esc_html_e( 'Todos', 'chatbot-plugin-wp' ); ?></option>
							<option value="gemini"<?php selected( $provider, 'gemini' ); ?>>Gemini</option>
							<option value="ollama"<?php selected( $provider, 'ollama' ); ?>>Ollama</option>
							<option value="openai_compatible"<?php selected( $provider, 'openai_compatible' ); ?>>OpenAI-compatible</option>
						</select>
					</div>
					<div class="chatbot-admin-history-filters__field">
						<label for="chatbot-history-status"><?php esc_html_e( 'Estado', 'chatbot-plugin-wp' ); ?></label>
						<select id="chatbot-history-status" name="status">
							<option value="all"<?php selected( $status, 'all' ); ?>><?php esc_html_e( 'Todos', 'chatbot-plugin-wp' ); ?></option>
							<option value="active"<?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Activa', 'chatbot-plugin-wp' ); ?></option>
							<option value="success"<?php selected( $status, 'success' ); ?>><?php esc_html_e( 'Éxito', 'chatbot-plugin-wp' ); ?></option>
							<option value="error"<?php selected( $status, 'error' ); ?>><?php esc_html_e( 'Error', 'chatbot-plugin-wp' ); ?></option>
							<option value="cached"<?php selected( $status, 'cached' ); ?>><?php esc_html_e( 'En caché', 'chatbot-plugin-wp' ); ?></option>
						</select>
					</div>
					<div class="chatbot-admin-history-filters__field">
						<label for="chatbot-history-orderby"><?php esc_html_e( 'Ordenar por', 'chatbot-plugin-wp' ); ?></label>
						<select id="chatbot-history-orderby" name="orderby">
							<option value="updated_at"<?php selected( $orderby, 'updated_at' ); ?>><?php esc_html_e( 'Última actividad', 'chatbot-plugin-wp' ); ?></option>
							<option value="started_at"<?php selected( $orderby, 'started_at' ); ?>><?php esc_html_e( 'Inicio', 'chatbot-plugin-wp' ); ?></option>
						</select>
					</div>
					<div class="chatbot-admin-history-filters__field">
						<label for="chatbot-history-order"><?php esc_html_e( 'Dirección', 'chatbot-plugin-wp' ); ?></label>
						<select id="chatbot-history-order" name="order">
							<option value="desc"<?php selected( $order, 'desc' ); ?>><?php esc_html_e( 'Más reciente', 'chatbot-plugin-wp' ); ?></option>
							<option value="asc"<?php selected( $order, 'asc' ); ?>><?php esc_html_e( 'Más antiguo', 'chatbot-plugin-wp' ); ?></option>
						</select>
					</div>
					<div class="chatbot-admin-history-filters__actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Filtrar', 'chatbot-plugin-wp' ); ?></button>
						<?php if ( '' !== $search || 'all' !== $provider || 'all' !== $status ) : ?>
							<a class="button" href="<?php echo esc_url( $base_url . ( $days > 0 ? '&days=' . $days : '' ) ); ?>"><?php esc_html_e( 'Limpiar', 'chatbot-plugin-wp' ); ?></a>
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
								__( 'Página %1$d de %2$d', 'chatbot-plugin-wp' ),
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
					<p><?php esc_html_e( 'No hay conversaciones en este periodo o con estos filtros.', 'chatbot-plugin-wp' ); ?></p>
				</div>
			<?php else : ?>
				<div class="chatbot-admin-history-table" role="table" aria-label="<?php esc_attr_e( 'Listado de conversaciones', 'chatbot-plugin-wp' ); ?>">
					<div class="chatbot-admin-history-table__head" role="row">
						<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--icon" role="columnheader" aria-hidden="true"></span>
						<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--title" role="columnheader"><?php esc_html_e( 'Conversación', 'chatbot-plugin-wp' ); ?></span>
						<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--status" role="columnheader"><?php esc_html_e( 'Estado', 'chatbot-plugin-wp' ); ?></span>
						<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--provider" role="columnheader"><?php esc_html_e( 'Proveedor', 'chatbot-plugin-wp' ); ?></span>
						<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--date" role="columnheader"><?php esc_html_e( 'Actualizado', 'chatbot-plugin-wp' ); ?></span>
						<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--msgs" role="columnheader"><?php esc_html_e( 'Msgs', 'chatbot-plugin-wp' ); ?></span>
						<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--action" role="columnheader" aria-hidden="true"></span>
					</div>
					<div class="chatbot-admin-history-stack" id="chatbot-history-list" role="rowgroup">
					<?php foreach ( $items as $item ) : ?>
						<?php
						$item_id = (int) ( $item['id'] ?? 0 );
						self::render_history_card( $item, $expanded_id === $item_id );
						?>
					<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php
			if ( $pages > 1 ) {
				echo '<nav class="chatbot-admin-tablenav" aria-label="' . esc_attr__( 'Paginación', 'chatbot-plugin-wp' ) . '">';
				for ( $i = 1; $i <= min( $pages, 15 ); $i++ ) {
					$url = add_query_arg(
						array(
							'page'     => 'chatbot-plugin',
							'tab'      => 'history',
							'days'     => $days,
							'paged'    => $i,
							's'        => $search,
							'provider' => $provider,
							'status'   => $status,
							'orderby'  => $orderby,
							'order'    => $order,
						),
						admin_url( 'admin.php' )
					);
					$class = $page === $i ? 'is-active' : '';
					echo '<a href="' . esc_url( $url ) . '"' . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . '>' . esc_html( (string) $i ) . '</a>';
				}
				echo '</nav>';
			}
			?>
		</div>
		<?php
	}

	public static function ajax_history_detail(): void {
		check_ajax_referer( 'chatbot_history_detail', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sin permisos.', 'chatbot-plugin-wp' ) ), 403 );
		}

		$conversation_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( $conversation_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Conversación no válida.', 'chatbot-plugin-wp' ) ), 400 );
		}

		$conv = Chatbot_Chat_History::get_conversation( $conversation_id );
		if ( ! $conv ) {
			wp_send_json_error( array( 'message' => __( 'Conversación no encontrada.', 'chatbot-plugin-wp' ) ), 404 );
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
private static function render_history_card( array $item, bool $expanded = false ): void {
	$id         = (int) ( $item['id'] ?? 0 );
	$public_id  = (string) ( $item['public_id'] ?? '' );
	$title      = (string) ( $item['title'] ?? '' );
	$status     = (string) ( $item['status'] ?? '' );
	$provider   = (string) ( $item['provider'] ?? '' );
	$model      = (string) ( $item['model'] ?? '' );
	$msg_count  = (int) ( $item['message_count'] ?? 0 );
	$page_path  = (string) ( $item['page_path'] ?? '' );
	$updated    = Chatbot_Chat_History::format_datetime_local( (string) ( $item['updated_at'] ?? '' ) );
	$session    = (string) ( $item['session_hash'] ?? '' );
	$is_ok      = in_array( $status, array( 'success', 'active', 'cached' ), true );

	if ( '' === $title ) {
		$title = __( '(Sin título)', 'chatbot-plugin-wp' );
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
				<span class="chatbot-admin-history-card__title"><?php echo esc_html( $title ); ?></span>
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

			<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--provider">
				<?php if ( '' !== $provider_name ) : ?>
					<span class="chatbot-admin-history-card__provider-name"><?php echo esc_html( $provider_name ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $model ) : ?>
					<span class="chatbot-admin-history-card__model" title="<?php echo esc_attr( $model ); ?>"><?php echo esc_html( $model ); ?></span>
				<?php elseif ( '' !== $provider_label ) : ?>
					<span class="chatbot-admin-history-card__model"><?php echo esc_html( $provider_label ); ?></span>
				<?php endif; ?>
			</span>

			<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--date">
				<time datetime="<?php echo esc_attr( (string) ( $item['updated_at'] ?? '' ) ); ?>"><?php echo esc_html( $updated ); ?></time>
			</span>

			<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--msgs" data-label="<?php esc_attr_e( 'Mensajes:', 'chatbot-plugin-wp' ); ?>">
				<?php echo esc_html( number_format_i18n( $msg_count ) ); ?>
			</span>

			<span class="chatbot-admin-history-table__cell chatbot-admin-history-table__cell--action">
				<span class="chatbot-admin-history-card__chevron" aria-hidden="true"></span>
			</span>
		</button>

		<div
			class="chatbot-admin-history-card__panel"
			id="<?php echo esc_attr( $panel_id ); ?>"
			role="region"
			aria-label="<?php echo esc_attr( sprintf( __( 'Historial de %s', 'chatbot-plugin-wp' ), $public_id ) ); ?>"
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
	$started   = Chatbot_Chat_History::format_datetime_local( (string) ( $conv['started_at'] ?? '' ) );
	$updated   = Chatbot_Chat_History::format_datetime_local( (string) ( $conv['updated_at'] ?? '' ) );
	$status    = (string) ( $conv['status'] ?? '' );
	$provider  = (string) ( $conv['provider'] ?? '' );
	$model     = (string) ( $conv['model'] ?? '' );
	$page_url  = (string) ( $conv['page_url'] ?? '' );
	$page_path = (string) ( $conv['page_path'] ?? '' );
	$session   = (string) ( $conv['session_hash'] ?? '' );
	$public_id = (string) ( $conv['public_id'] ?? '' );
	$msg_count = (int) ( $conv['message_count'] ?? count( $messages ) );
	?>
	<div class="chatbot-admin-history-card__body">
		<dl class="chatbot-admin-history-detail__grid">
			<div>
				<dt><?php esc_html_e( 'ID interno', 'chatbot-plugin-wp' ); ?></dt>
				<dd>#<?php echo esc_html( (string) (int) ( $conv['id'] ?? 0 ) ); ?></dd>
			</div>

			<?php if ( '' !== $public_id ) : ?>
				<div>
					<dt><?php esc_html_e( 'ID público', 'chatbot-plugin-wp' ); ?></dt>
					<dd><code><?php echo esc_html( $public_id ); ?></code></dd>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $status ) : ?>
				<div>
					<dt><?php esc_html_e( 'Estado', 'chatbot-plugin-wp' ); ?></dt>
					<dd><?php echo esc_html( self::format_history_status_label( $status ) ); ?></dd>
				</div>
			<?php endif; ?>

			<div>
				<dt><?php esc_html_e( 'Mensajes', 'chatbot-plugin-wp' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( $msg_count ) ); ?></dd>
			</div>

			<div>
				<dt><?php esc_html_e( 'Inicio', 'chatbot-plugin-wp' ); ?></dt>
				<dd><?php echo esc_html( $started ); ?></dd>
			</div>

			<div>
				<dt><?php esc_html_e( 'Última actividad', 'chatbot-plugin-wp' ); ?></dt>
				<dd><?php echo esc_html( $updated ); ?></dd>
			</div>

			<?php if ( '' !== $provider || '' !== $model ) : ?>
				<div>
					<dt><?php esc_html_e( 'Proveedor / modelo', 'chatbot-plugin-wp' ); ?></dt>
					<dd><?php echo esc_html( self::format_history_provider_label( $provider, $model ) ); ?></dd>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $session ) : ?>
				<div>
					<dt><?php esc_html_e( 'Sesión', 'chatbot-plugin-wp' ); ?></dt>
					<dd><code><?php echo esc_html( $session ); ?></code></dd>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $page_path ) : ?>
				<div class="chatbot-admin-history-detail__grid-wide">
					<dt><?php esc_html_e( 'Ruta', 'chatbot-plugin-wp' ); ?></dt>
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

		<div class="chatbot-admin-history-messages">
			<h3 class="chatbot-admin-history-messages__title"><?php esc_html_e( 'Mensajes', 'chatbot-plugin-wp' ); ?></h3>
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
		echo '<p class="chatbot-admin-history-messages__empty">' . esc_html__( 'Sin mensajes guardados.', 'chatbot-plugin-wp' ) . '</p>';
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

			$status_badge_class = 'chatbot-admin-status--err';
			if ( 'cached' === $msg_status ) {
				$status_badge_class = 'chatbot-admin-status--cached';
			} elseif ( in_array( $msg_status, array( 'success', 'active' ), true ) ) {
				$status_badge_class = 'chatbot-admin-status--ok';
			}
			?>
			<div class="chatbot-admin-history-msg chatbot-admin-history-msg--<?php echo esc_attr( $role ); ?>">
				<div class="chatbot-admin-history-msg__head">
					<span class="chatbot-admin-history-msg__role">
						<?php echo esc_html( 'user' === $role ? __( 'Usuario', 'chatbot-plugin-wp' ) : __( 'Asistente', 'chatbot-plugin-wp' ) ); ?>
					</span>

					<time datetime="<?php echo esc_attr( (string) ( $msg['created_at'] ?? '' ) ); ?>">
						<?php echo esc_html( $when ); ?>
					</time>

					<?php if ( 'assistant' === $role && '' !== $msg_status ) : ?>
						<span class="chatbot-admin-status <?php echo esc_attr( $status_badge_class ); ?>">
							<?php echo esc_html( self::format_history_status_label( $msg_status ) ); ?>
						</span>
					<?php endif; ?>
				</div>

				<div class="chatbot-admin-history-msg__body"><?php echo esc_html( $message_text ); ?></div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

private static function format_history_status_label( string $status ): string {
	$labels = array(
		'active'  => __( 'Activa', 'chatbot-plugin-wp' ),
		'success' => __( 'Éxito', 'chatbot-plugin-wp' ),
		'error'   => __( 'Error', 'chatbot-plugin-wp' ),
		'cached'  => __( 'En caché', 'chatbot-plugin-wp' ),
	);

	return $labels[ $status ] ?? $status;
}

private static function format_history_provider_label( string $provider, string $model = '' ): string {
	$labels = array(
		'gemini'            => 'Gemini',
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
		'ollama'            => 'Ollama',
		'openai_compatible' => 'OpenAI-compatible',
	);

	return $labels[ $provider ] ?? $provider;
}

private static function format_history_provider_avatar( string $provider ): string {
	$labels = array(
		'gemini'            => 'G',
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
