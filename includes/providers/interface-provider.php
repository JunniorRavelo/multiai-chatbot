<?php
/**
 * AI provider interface.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Chatbot_AI_Provider {

	/**
	 * @param string               $system   System instruction.
	 * @param array<int, array{role: string, content: string}> $messages Conversation messages.
	 * @param array<string, mixed> $settings Plugin settings subset.
	 * @return array{text: string, model: string}|WP_Error
	 */
	public function complete( string $system, array $messages, array $settings );
}
