=== MultiAI ChatBot ===
Contributors: jsravelo
Donate link: https://github.com/JunniorRavelo/multiai-chatbot
Tags: chatbot, ai, gemini, live chat, customer support
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI chat widget for WordPress. Supports Gemini, DeepSeek, Ollama, and OpenAI-compatible APIs. Styles, history, and telemetry.

== Description ==

**MultiAI ChatBot** adds an AI assistant to your WordPress site with a floating or embedded widget, a full admin panel, and usage analytics.

Connect the chat to your preferred AI provider, customize appearance without code, and review conversations and statistics from the WordPress dashboard.

= Key features =

* **Multiple AI providers:** Google Gemini, DeepSeek, Ollama (local), and any OpenAI-compatible API.
* **Global widget or shortcode:** Show the chat site-wide or only where you insert `[chatbot_widget]`.
* **Floating and inline modes:** Floating button with slide-out panel or embedded chat in page content.
* **Streaming responses:** Progressive replies for a more natural experience (optional).
* **8 visual themes:** Sapphire, Midnight, Monochrome, Aqua, Ember, Emerald, Amethyst, and Plum.
* **Customization:** Colors, border radius, widget position (5 locations), and panel width.
* **Live preview:** Preview theme, position, and styles from the admin panel.
* **Conversation history:** Browse messages, status, provider, and source page.
* **Telemetry and CSV export:** Latency, errors, models used, and period summaries.
* **Security:** IP rate limiting, API keys on the server (never exposed to the browser), and wp-config.php constant support.

= Admin panel =

* **General** — Enable widget, welcome message, system prompt, streaming, and usage limits.
* **AI Model** — Provider, API key, primary model, and fallback models.
* **Security** — Allowed origins, cache, telemetry, and abuse suspension.
* **Chat Style** — Presets, colors, position, and interactive preview.
* **Statistics** — Totals, provider breakdown, and CSV export.
* **History** — Filterable conversation list with message detail.

= Supported providers =

* **Google Gemini** — Flash models with automatic fallback on errors.
* **DeepSeek** — Official API with fallback model rotation.
* **Ollama** — Local models without an API key (ideal for self-hosted setups).
* **OpenAI-compatible** — OpenAI, Azure OpenAI, or other compatible endpoints.

= Shortcodes =

`[chatbot_widget]` — Floating widget (default).

`[chatbot_widget mode="inline"]` — Embedded panel in content.

Optional style attributes (override global settings): `preset`, `position`, `primary`, `accent`, `radius`, `offset`, `panel_width`, `bg`, `fg`. Example: `[chatbot_widget preset="ocean" position="bottom-left"]`.

= REST API =

* `POST /wp-json/chatbot-plugin/v1/chat` — JSON response.
* `POST /chatbot-plugin/v1/chat/stream` — Text streaming.

The API key is always handled on the server; the frontend only uses the WordPress nonce.

= Requirements =

* WordPress 6.0 or higher
* PHP 8.0 or higher
* For Gemini, DeepSeek, or OpenAI: a valid API key
* For Ollama: a server reachable from the WordPress host

== Installation ==

1. Upload the `chatbot-plugin-wp` folder to `/wp-content/plugins/` or install the ZIP from **Plugins → Add New → Upload Plugin**.
2. Activate the plugin from **Plugins**.
3. Go to **MultiAI ChatBot** in the admin menu.
4. Under **AI Model**, choose a provider and enter your API key (except for Ollama).
5. Under **General**, enable the widget and adjust the welcome message.
6. Save changes. The chat will appear on the frontend when the widget is enabled.

**Note:** After activation, streaming rewrite rules are registered automatically. If streaming does not respond, visit **Settings → Permalinks** and click **Save Changes**.

== Frequently Asked Questions ==

= Do I need an API key? =

Yes, for Gemini, DeepSeek, and OpenAI-compatible providers. Ollama does not require a key, but your WordPress host must be able to reach the Ollama server.

= Is the API key visible in the browser? =

No. All model requests go through the WordPress backend. The frontend only sends messages to the REST endpoint with the WordPress nonce.

= Can I define the API key in wp-config.php? =

Yes. You can use constants such as `CHATBOT_GEMINI_API_KEY`, `CHATBOT_DEEPSEEK_API_KEY`, or `CHATBOT_OPENAI_API_KEY` for better security in production.

= How do I show the chat on only one page? =

Disable the global widget under **General** and insert the shortcode `[chatbot_widget]` on the desired page or post.

= Are conversations stored? =

Yes. Each exchange is stored in the database with a public ID (format `CB-AAAA-MM-DD-HH-MM-SS`) and can be reviewed under **History**.

= What happens when I uninstall the plugin? =

The `uninstall.php` routine removes:

* Database tables: `chatbot_events`, conversation and message tables.
* Plugin options: `chatbot_plugin_settings` and database version options.
* Scheduled cron jobs: `chatbot_purge_history`, `chatbot_purge_telemetry`.
* Plugin transients: response cache, rate limits, violations, and suspension flags.

External log files configured via `telemetry_log_path` pointing outside the plugin directory are **not** deleted automatically.

== Privacy ==

= What data is stored locally? =

* **Conversations:** chat messages and metadata in custom database tables.
* **Telemetry:** request events (provider, model, status, latency) in `chatbot_events`.
* **Settings:** plugin configuration in `chatbot_plugin_settings`.
* **Temporary data:** rate-limit and response-cache transients (`chatbot_*`).

= What data is sent to third parties? =

When a visitor sends a chat message, the plugin forwards content to the AI provider configured by the site administrator (Google Gemini, DeepSeek, Ollama, or an OpenAI-compatible API). This typically includes the visitor message, recent conversation context, and the system prompt from plugin settings.

= Who is responsible for compliance? =

The site administrator chooses the AI provider and must comply with applicable privacy laws and the provider terms of service.

= Retention =

Conversation and telemetry retention can be configured in plugin settings (`history_retention_days`, `telemetry_retention_days`). A value of `0` means unlimited until manual purge or uninstall.

= Does the plugin phone home? =

No. This plugin does not send site data, chat content, or telemetry to the plugin author. Data is processed on your server and only forwarded to AI providers you configure.

= Personal data =

Chat history uses anonymous session identifiers and is not linked to visitor email addresses or WordPress user accounts by default. Administrators can review, export, or delete conversations from the MultiAI ChatBot admin screens.

== Screenshots ==

1. Admin panel — General tab with widget enabled.
2. AI model settings — provider, API key, and fallback models.
3. Chat style — visual themes, colors, and interactive preview.
4. Floating widget on the frontend with Sapphire theme.
5. Conversation history with filters and message detail.

== Changelog ==

= 1.0.0 =
* Initial release.
* AI chat widget (Gemini, DeepSeek, Ollama, OpenAI-compatible).
* Admin panel with General, AI Model, Security, Style, Statistics, and History tabs.
* 8 visual presets and color, position, and dimension customization.
* Interactive admin preview.
* Response streaming, rate limiting, and telemetry with CSV export.
* Conversation history with public ID and AJAX detail.
* Shortcodes `[chatbot_widget]` and `[chatbot_widget mode="inline"]`.
* REST API for JSON chat and streaming.

== Upgrade Notice ==

= 1.0.0 =
First public release of the plugin.
