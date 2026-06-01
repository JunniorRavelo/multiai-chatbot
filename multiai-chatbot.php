<?php
/**
 * Plugin Name: MultiAI ChatBot
 * Plugin URI: https://github.com/JunniorRavelo/multiai-chatbot
 * Description: AI chat widget using the WordPress AI Client and optional Ollama, with configurable styles and telemetry.
 * Version: 1.0.3
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Author: J. Santiago Ravelo Velasco
 * Author URI: https://www.linkedin.com/in/jsravelo/
 * Text Domain: multiai-chatbot
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Multch_Plugin
 * @license GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MULTCH_PLUGIN_VERSION', '1.0.3' );
define( 'MULTCH_TEXT_DOMAIN', MULTCH_TEXT_DOMAIN );
define( 'MULTCH_PLUGIN_FILE', __FILE__ );
define( 'MULTCH_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'MULTCH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MULTCH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once MULTCH_PLUGIN_PATH . 'includes/telemetry.php';
require_once MULTCH_PLUGIN_PATH . 'includes/chat-history.php';
require_once MULTCH_PLUGIN_PATH . 'includes/ai-client.php';
require_once MULTCH_PLUGIN_PATH . 'includes/providers/interface-provider.php';
require_once MULTCH_PLUGIN_PATH . 'includes/providers/class-provider-wordpress-ai.php';
require_once MULTCH_PLUGIN_PATH . 'includes/providers/class-provider-ollama.php';
require_once MULTCH_PLUGIN_PATH . 'includes/api-handler.php';
require_once MULTCH_PLUGIN_PATH . 'includes/rest-api.php';
require_once MULTCH_PLUGIN_PATH . 'includes/widget-namespace.php';
require_once MULTCH_PLUGIN_PATH . 'includes/enqueue.php';
require_once MULTCH_PLUGIN_PATH . 'includes/admin-settings.php';
require_once MULTCH_PLUGIN_PATH . 'includes/donation-footer.php';
require_once MULTCH_PLUGIN_PATH . 'includes/privacy.php';
require_once MULTCH_PLUGIN_PATH . 'includes/config-constants.php';
require_once MULTCH_PLUGIN_PATH . 'includes/class-migration.php';
require_once MULTCH_PLUGIN_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Multch_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Multch_Plugin', 'deactivate' ) );

Multch_Plugin::instance();
