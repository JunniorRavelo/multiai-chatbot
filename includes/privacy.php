<?php
/**
 * Privacy policy suggested content for WordPress Privacy tools.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Multch_Privacy {

	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'register_privacy_content' ) );
	}

	public static function register_privacy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = self::get_policy_content();

		wp_add_privacy_policy_content(
			'MultiAI ChatBot',
			wp_kses_post( $content )
		);
	}

	private static function get_policy_content(): string {
		$settings = Multch_Plugin::get_settings();
		$history_days = isset( $settings['history_retention_days'] ) ? (int) $settings['history_retention_days'] : 0;
		$telemetry_days = isset( $settings['telemetry_retention_days'] ) ? (int) $settings['telemetry_retention_days'] : 0;

		$history_retention = $history_days > 0
			? sprintf(
				/* translators: %d: number of days */
				__( 'Conversation history is automatically purged after %d days.', MULTCH_TEXT_DOMAIN ),
				$history_days
			)
			: __( 'Conversation history is kept until manually deleted or until the plugin is uninstalled.', MULTCH_TEXT_DOMAIN );

		$telemetry_retention = $telemetry_days > 0
			? sprintf(
				/* translators: %d: number of days */
				__( 'Telemetry events are automatically purged after %d days.', MULTCH_TEXT_DOMAIN ),
				$telemetry_days
			)
			: __( 'Telemetry events are kept until manually purged or until the plugin is uninstalled.', MULTCH_TEXT_DOMAIN );

		$sections = array(
			'<h2>' . esc_html__( 'What data this plugin collects', MULTCH_TEXT_DOMAIN ) . '</h2>',
			'<p>' . esc_html__( 'When visitors use the chat widget, the plugin may store and process the following data:', MULTCH_TEXT_DOMAIN ) . '</p>',
			'<ul>',
			'<li>' . esc_html__( 'Chat messages sent by visitors and AI assistant replies.', MULTCH_TEXT_DOMAIN ) . '</li>',
			'<li>' . esc_html__( 'An anonymous session identifier (hashed; not linked to WordPress user accounts by default).', MULTCH_TEXT_DOMAIN ) . '</li>',
			'<li>' . esc_html__( 'Page URL and path where the conversation started.', MULTCH_TEXT_DOMAIN ) . '</li>',
			'<li>' . esc_html__( 'Technical telemetry: provider, model, response status, latency, and error codes.', MULTCH_TEXT_DOMAIN ) . '</li>',
			'<li>' . esc_html__( 'Temporary rate-limit data derived from the visitor IP address (hashed in transients).', MULTCH_TEXT_DOMAIN ) . '</li>',
			'</ul>',

			'<h2>' . esc_html__( 'Data sent to third-party AI services', MULTCH_TEXT_DOMAIN ) . '</h2>',
			'<p>' . esc_html__( 'To generate responses, the plugin sends chat content through the WordPress AI Client (using providers configured under Settings → Connectors) or to a self-hosted Ollama server you specify. This typically includes:', MULTCH_TEXT_DOMAIN ) . '</p>',
			'<ul>',
			'<li>' . esc_html__( 'The visitor message and recent conversation context.', MULTCH_TEXT_DOMAIN ) . '</li>',
			'<li>' . esc_html__( 'The system prompt configured in the plugin settings.', MULTCH_TEXT_DOMAIN ) . '</li>',
			'</ul>',
			'<p>' . esc_html__( 'The site administrator is responsible for choosing the provider and ensuring compliance with applicable privacy laws and the provider terms of service.', MULTCH_TEXT_DOMAIN ) . '</p>',

			'<h2>' . esc_html__( 'Data not sent to the plugin author', MULTCH_TEXT_DOMAIN ) . '</h2>',
			'<p>' . esc_html__( 'This plugin does not send site data, chat content, or telemetry to the plugin author. Data is processed on your server and only forwarded to AI providers you configure.', MULTCH_TEXT_DOMAIN ) . '</p>',

			'<h2>' . esc_html__( 'Retention', MULTCH_TEXT_DOMAIN ) . '</h2>',
			'<p>' . esc_html( $history_retention ) . '</p>',
			'<p>' . esc_html( $telemetry_retention ) . '</p>',

			'<h2>' . esc_html__( 'Uninstall', MULTCH_TEXT_DOMAIN ) . '</h2>',
			'<p>' . esc_html__( 'When the plugin is deleted via the WordPress admin, database tables, plugin settings, scheduled tasks, plugin transients, and any optional telemetry log file under wp-content/uploads/multiai-chatbot/ are removed.', MULTCH_TEXT_DOMAIN ) . '</p>',

			'<h2>' . esc_html__( 'Personal data requests', MULTCH_TEXT_DOMAIN ) . '</h2>',
			'<p>' . esc_html__( 'Chat history is stored with anonymous session identifiers and is not linked to visitor email addresses or WordPress user accounts by default. Site administrators can review, export, or delete conversations from the Chatbot admin screens.', MULTCH_TEXT_DOMAIN ) . '</p>',
		);

		return implode( "\n", $sections );
	}
}

Multch_Privacy::init();
