=== MultiAI ChatBot ===
Contributors: jsravelo
Donate link: https://github.com/JunniorRavelo/multiai-chatbot
Tags: chatbot, ai, gemini, live chat, customer support
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.1
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

* WordPress 6.2 or higher
* PHP 8.0 or higher
* For Gemini, DeepSeek, or OpenAI: a valid API key
* For Ollama: a server reachable from the WordPress host

== Installation ==

1. Upload the `multiai-chatbot` folder to `/wp-content/plugins/` or install the ZIP from **Plugins → Add New → Upload Plugin**.
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

== External services ==

This plugin relies on **optional** third-party AI services chosen by the site administrator. The plugin author does not host these services and does not receive visitor chat data. External calls occur only after you configure a provider under **MultiAI ChatBot → AI Model** and a visitor uses the chat widget on your site.

= When is data sent? =

Data is transmitted to the configured AI provider **when a visitor sends a message** in the chat widget (REST or streaming endpoint on your WordPress site). The plugin does not send chat content to external servers in the background or on page load.

Typical payload per request:

* The visitor's message and recent conversation context (stored locally for continuity).
* The system prompt from plugin settings.
* Provider and model identifiers required to generate a reply.

The plugin also stores conversations and technical telemetry **on your WordPress server** (see == Privacy == below). That local data is not sent to the plugin author.

= Administrator consent =

By installing the plugin, entering an API key (where required), and selecting a provider, the site administrator authorizes the plugin to forward visitor messages to that provider's API. Visitors should be informed through your site's privacy policy; the plugin adds suggested privacy policy text under **Settings → Privacy**.

= Google Gemini (optional) =

* **Service:** Google Gemini API (`generativelanguage.googleapis.com`).
* **Used for:** Generating AI chat replies when Gemini is selected in plugin settings.
* **Data sent:** Chat messages, conversation context, and system prompt, as described above, when a visitor sends a message.
* **Terms of service:** https://ai.google.dev/gemini-api/terms
* **Privacy policy:** https://policies.google.com/privacy
* **Related:** https://developers.google.com/terms (Google APIs Terms of Service)

= DeepSeek (optional) =

* **Service:** DeepSeek API (`api.deepseek.com` by default; configurable base URL).
* **Used for:** Generating AI chat replies when DeepSeek is selected in plugin settings.
* **Data sent:** Chat messages, conversation context, and system prompt when a visitor sends a message.
* **Terms of service:** https://cdn.deepseek.com/policies/en-US/deepseek-open-platform-terms-of-service.html
* **Privacy policy:** https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html

= OpenAI-compatible APIs (optional) =

* **Service:** Any HTTP API compatible with the OpenAI Chat Completions format (default: `api.openai.com`, including OpenAI, Azure OpenAI, or other hosts you configure).
* **Used for:** Generating AI chat replies when an OpenAI-compatible provider is selected in plugin settings.
* **Data sent:** Chat messages, conversation context, and system prompt when a visitor sends a message.
* **OpenAI terms of service:** https://openai.com/api/policies/service-terms
* **OpenAI privacy policy:** https://openai.com/policies/privacy-policy
* **Note:** If you use a non-OpenAI host (for example Azure or a private gateway), that operator's terms and privacy policy apply to data sent to that endpoint.

= Ollama (optional) =

* **Service:** Ollama HTTP API on a server you control (default: `http://127.0.0.1:11434`; configurable in plugin settings).
* **Used for:** Running models locally or on your infrastructure without a cloud API key.
* **Data sent:** Chat messages, conversation context, and system prompt to the Ollama base URL you configure when a visitor sends a message. Data stays on (or transits to) the server you operate unless you point the URL to a third-party host.
* **Ollama website terms:** https://ollama.com/terms
* **Ollama website privacy:** https://ollama.com/privacy
* **Note:** Self-hosted Ollama does not use Ollama's cloud by default; review your own hosting and compliance obligations.

= Services not used by this plugin =

This plugin does **not** contact the plugin author's servers for analytics, licensing, or chat processing. Donation links in the WordPress admin are ordinary hyperlinks opened only if an administrator clicks them. Optional developer credit in the frontend chat is **disabled by default** and must be enabled in **Chat Style** settings.

== Privacy ==

= What data is stored locally? =

* **Conversations:** chat messages and metadata in custom database tables.
* **Telemetry:** request events (provider, model, status, latency) in `chatbot_events`.
* **Settings:** plugin configuration in `chatbot_plugin_settings`.
* **Temporary data:** rate-limit and response-cache transients (`chatbot_*`).

= What data is sent to third parties? =

When a visitor sends a chat message, the plugin forwards content to the AI provider configured by the site administrator (Google Gemini, DeepSeek, Ollama, or an OpenAI-compatible API). This typically includes the visitor message, recent conversation context, and the system prompt from plugin settings. See == External services == for each provider, timing of transmission, and links to terms and privacy policies.

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

= 1.0.1 =
* Document external AI services in readme (purpose, data sent, when sent, terms and privacy links per provider).

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

= 1.0.1 =
Readme update: third-party AI service documentation for WordPress.org compliance. No code changes required for existing installations.

= 1.0.0 =
First public release of the plugin.
