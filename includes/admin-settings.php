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
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'widget_enabled'        => true,
			'welcome_message'       => "Hola. Soy un agente de IA. Puedo cometer errores; verifica la información importante antes de tomar decisiones.\n\n¿En qué puedo ayudarte?",
			'system_prompt'         => 'Eres un asistente útil del sitio web. Responde en español de forma clara, breve y amable. Si no sabes algo, dilo con honestidad.',
			'streaming_enabled'     => true,
			'rate_limit_per_minute' => 10,
			'provider'              => 'gemini',
			'api_key'               => '',
			'model'                 => 'gemini-2.0-flash',
			'model_candidates'      => 'gemini-2.0-flash-lite',
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

		$out['widget_enabled']        = ! empty( $input['widget_enabled'] );
		$out['welcome_message']       = sanitize_textarea_field( $input['welcome_message'] ?? $defaults['welcome_message'] );
		$out['system_prompt']         = sanitize_textarea_field( $input['system_prompt'] ?? $defaults['system_prompt'] );
		$out['streaming_enabled']     = ! empty( $input['streaming_enabled'] );
		$out['rate_limit_per_minute'] = max( 1, min( 60, (int) ( $input['rate_limit_per_minute'] ?? $defaults['rate_limit_per_minute'] ) ) );

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
		$out['style_launcher_label'] = ! empty( $input['style_launcher_label'] );

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
				'desc'       => __( 'Oscuro translúcido con brillo azul y púrpura.', 'chatbot-plugin-wp' ),
				'badge'      => __( 'Oscuro', 'chatbot-plugin-wp' ),
				'badge_type' => 'dark',
				'colors'     => array( '#60a5fa', '#c084fc', '#0f172a' ),
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
			'general' => __( 'General', 'chatbot-plugin-wp' ),
			'model'   => __( 'Modelo IA', 'chatbot-plugin-wp' ),
			'style'   => __( 'Estilo del chat', 'chatbot-plugin-wp' ),
			'stats'   => __( 'Estadísticas', 'chatbot-plugin-wp' ),
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
					<div>
						<h1><?php esc_html_e( 'Chatbot Plugin', 'chatbot-plugin-wp' ); ?></h1>
						<p class="chatbot-admin-header__desc">
							<?php esc_html_e( 'Configura el agente de IA, el proveedor y la apariencia del widget en tu sitio.', 'chatbot-plugin-wp' ); ?>
						</p>
					</div>
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

			<?php if ( 'stats' === $tab ) : ?>
				<div class="chatbot-admin-body">
					<?php self::render_stats_tab(); ?>
				</div>
			<?php else : ?>
				<form method="post" action="options.php" class="chatbot-admin-form">
					<?php settings_fields( 'chatbot_plugin_group' ); ?>

					<div class="chatbot-admin-body">
						<?php if ( 'general' === $tab ) : ?>
							<?php self::render_general_fields( $settings ); ?>
						<?php elseif ( 'model' === $tab ) : ?>
							<?php self::render_model_fields( $settings ); ?>
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
			<tr>
				<th scope="row"><?php esc_html_e( 'Límite por minuto (IP)', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="1" max="60" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rate_limit_per_minute]" value="<?php echo esc_attr( (string) $settings['rate_limit_per_minute'] ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'Protege contra abuso de la API por visitante.', 'chatbot-plugin-wp' ); ?></p>
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
					<p class="description"><?php esc_html_e( 'Ej: gemini-2.0-flash, llama3, gpt-4o-mini', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr class="chatbot-field-gemini">
				<th scope="row"><?php esc_html_e( 'Modelo de respaldo', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" class="large-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[model_candidates]" value="<?php echo esc_attr( (string) $settings['model_candidates'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Solo Gemini. Modelos alternativos separados por coma.', 'chatbot-plugin-wp' ); ?></p>
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
					<p class="description"><?php esc_html_e( 'Deja vacío para mantener la clave actual. En producción puedes definir CHATBOT_GEMINI_API_KEY o CHATBOT_OPENAI_API_KEY en wp-config.php.', 'chatbot-plugin-wp' ); ?></p>
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
							$option_label = trim(
								sprintf(
									'%s (%s)',
									$meta['label'],
									$meta['badge']
								),
								' ()'
							);
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
						<button type="button" class="button button-secondary" id="chatbot-preview-toggle" aria-pressed="true">
							<?php esc_html_e( 'Cerrar panel', 'chatbot-plugin-wp' ); ?>
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
}
