=== MultiAI ChatBot ===
Contributors: jsravelo
Donate link: https://github.com/JunniorRavelo/multiai-chatbot
Tags: chatbot, ai, gemini, live chat, customer support
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI chat widget for WordPress via Connectors, your Gemini API key, or Ollama. Custom styles, history, and telemetry.

== Description ==

**MultiAI ChatBot** adds an AI assistant to your WordPress site with a floating or embedded widget, a full admin panel, and usage analytics.

Connect the chat to AI models via **Settings → Connectors**, your own Gemini API key, or Ollama; customize appearance without code; and review conversations and statistics from the WordPress dashboard.

= Choosing WordPress AI or Google IA for Gemini =

If you want **Google Gemini**, pick **one** of these paths under **MultiAI ChatBot → AI Model** (you cannot combine both at once):

* **WordPress AI** — Connect Google under **Settings → Connectors** (WordPress 7.0+). API keys live in WordPress core, shared with other AI plugins. The plugin calls models through the WordPress AI Client; it does not store your Gemini key.
* **Google IA** — Enter your own [Google AI (Gemini) API key](https://aistudio.google.com/apikey) in this plugin (or `MULTCH_GEMINI_API_KEY` in `wp-config.php`). Requests go **directly** from your server to Google's Generative Language API. Model names in the admin still match the Connectors catalog when WordPress 7.0+ is available, but **credentials are yours**, not Connectors.

**When to use WordPress AI:** you already use Connectors, want one place for OpenAI/Google/Anthropic keys, or run several AI plugins on the same site.

**When to use Google IA:** you only need Gemini, already have a Google API key, or prefer storing the key in this plugin / `wp-config.php` instead of Connectors.

**Data treatment:** in both cases, visitor messages are sent to generate a reply **only when someone uses the chat** (see == External services == and == Privacy ==). The difference is **where the API key is stored** and **whether WordPress core or this plugin calls Google**. Local conversation history and telemetry are optional and stay on your server unless you enable them under **General**.

= Key features =

* **WordPress AI Client:** Uses WordPress 7.0+ Connectors (OpenAI, Google Gemini, and Anthropic Claude) with credentials managed by core—not in this plugin.
* **Google IA (optional):** Your own Google Gemini API key; primary and fallback models use the same model IDs as the Connectors catalog, with direct calls to Google's API from your server.
* **Ollama (optional):** Local models on your own server without cloud API keys in the plugin.
* **Global widget or shortcode:** Show the chat site-wide or only where you insert `[multch_widget]`.
* **Floating and inline modes:** Floating button with slide-out panel or embedded chat in page content.
* **Streaming responses:** Progressive replies for a more natural experience (optional).
* **8 visual themes:** Sapphire, Midnight, Monochrome, Aqua, Ember, Emerald, Amethyst, and Plum.
* **Customization:** Colors, border radius, widget position (5 locations), and panel width.
* **Live preview:** Preview theme, position, and styles from the admin panel.
* **Conversation history (opt-in):** Enable under General to browse messages, status, provider, and source page locally.
* **Telemetry and CSV export (opt-in):** Latency, errors, models used, and period summaries when statistics and history are enabled.
* **Security:** IP rate limiting; Connectors credentials and Google IA API keys stay on the server (never in the browser).

= Admin panel =

* **General** — Enable widget, welcome message, system prompt, streaming, and optional statistics/history collection (off by default).
* **AI Model** — Provider (WordPress AI, Google IA, or Ollama), primary/fallback models, Gemini API key (Google IA), and Ollama URL.
* **Security** — Allowed origins, cache, telemetry, and abuse suspension.
* **Chat Style** — Presets, colors, position, and interactive preview.
* **Statistics** — Totals, provider breakdown, and CSV export.
* **History** — Filterable conversation list with message detail.

= Supported providers =

* **WordPress AI (recommended)** — Models from **Settings → Connectors** on WordPress 7.0+ (OpenAI, Google Gemini, or Anthropic Claude). Configure API keys once for all compatible plugins.
* **Google IA** — Gemini with your own API key stored in this plugin (or `wp-config.php`). Model pickers list Gemini IDs from Connectors; chat requests use the Google Generative Language API, not Connectors credentials.
* **Ollama** — Local models on your infrastructure (no Connectors key required).

= Shortcodes =

`[multch_widget]` — Floating widget (default).

`[multch_widget mode="inline"]` — Embedded panel in content.

Optional style attributes (override global settings): `preset`, `position`, `primary`, `accent`, `radius`, `offset`, `panel_width`, `bg`, `fg`. Example: `[multch_widget preset="ocean" position="bottom-left"]`.

= REST API =

* `POST /wp-json/multch/v1/chat` — JSON response.
* `POST /multch/v1/chat/stream` — Text streaming.

Provider credentials are never sent to the browser; the frontend only uses the WordPress nonce.

= Requirements =

* WordPress 6.2 or higher (WordPress **7.0+** recommended for cloud AI via Connectors)
* PHP 8.0 or higher
* For WordPress AI: at least one provider connected under **Settings → Connectors**
* For Google IA: a Google AI (Gemini) API key; WordPress 7.0+ recommended to populate the model list from Connectors
* For Ollama: a server reachable from the WordPress host

== Installation ==

1. Upload the `multiai-chatbot` folder to `/wp-content/plugins/` or install the ZIP from **Plugins → Add New → Upload Plugin**.
2. Activate the plugin from **Plugins**.
3. Go to **MultiAI ChatBot** in the admin menu.
4. Under **AI Model**, choose **WordPress AI**, **Google IA**, or **Ollama**. For WordPress AI, connect providers under **Settings → Connectors** first. For Google IA, enter your Gemini API key and select primary and fallback models.
5. Under **General**, enable the widget and adjust the welcome message.
6. Save changes. The chat will appear on the frontend when the widget is enabled.

**Note:** After activation, streaming rewrite rules are registered automatically. If streaming does not respond, visit **Settings → Permalinks** and click **Save Changes**.

== Frequently Asked Questions ==

= Do I need an API key in this plugin? =

It depends on the provider you choose:

* **WordPress AI** — No. API keys are configured under **Settings → Connectors** in WordPress 7.0+.
* **Google IA** — Yes. You need a Google AI (Gemini) API key, entered in **MultiAI ChatBot → AI Model** or defined as `MULTCH_GEMINI_API_KEY` in `wp-config.php`.
* **Ollama** — No API key in the plugin, but your WordPress host must reach the Ollama server.

= Are provider credentials visible in the browser? =

No. All model requests go through the WordPress backend. The frontend only sends messages to the REST endpoint with the WordPress nonce.

= Should I use WordPress AI or Google IA for Gemini? =

Use **WordPress AI** if you want credentials in **Settings → Connectors** and may use OpenAI, Anthropic, or Google Gemini through WordPress 7.0+ Connectors.

Use **Google IA** if you want Gemini with an API key stored in **MultiAI ChatBot → AI Model** or `wp-config.php`, with direct calls to Google's API from your server. You still pick model IDs from the same Gemini list as Connectors when WordPress 7.0+ is installed; only the **routing and key storage** change.

Both options send the same kind of chat content to an AI service when a visitor messages the widget (message, context, system prompt). Neither sends your API key to the browser.

= What personal data is processed? =

* **To AI providers (when the chat is used):** visitor messages, recent context, and your system prompt—only for the provider you selected (Connectors path, Google IA, or Ollama).
* **On your server (optional):** if **Store statistics and history** is enabled under **General**, messages, anonymous session IDs, page URL, and technical telemetry are stored locally. Rate limiting uses hashed IP-derived transients.
* **Not collected by the plugin author:** no chat or telemetry is sent to the plugin developer.

Update your site privacy policy accordingly; suggested text is available under **Settings → Privacy**.

= How do I show the chat on only one page? =

Disable the global widget under **General** and insert the shortcode `[multch_widget]` on the desired page or post.

= Are conversations stored? =

Only if you enable **Store statistics and history** under **General** (off by default). When enabled, each exchange is saved in the database with a public ID (format `CB-AAAA-MM-DD-HH-MM-SS`) and can be reviewed under **History**.

When that option is off, conversations are not saved on the server. Messages are still sent to your configured AI provider when visitors use the chat, and the browser may keep recent messages in localStorage for continuity (they expire after 24 hours).

= What happens when I uninstall the plugin? =

The `uninstall.php` routine removes:

* Database tables: `multch_events`, conversation and message tables.
* Plugin options: `multch_plugin_settings` and database version options.
* Scheduled cron jobs: `multch_purge_history`, `multch_purge_telemetry`.
* Plugin transients: response cache, rate limits, violations, and suspension flags.

Optional telemetry log files under `wp-content/uploads/multiai-chatbot/` are removed on uninstall.

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

By installing the plugin, configuring an AI provider (Connectors, Google IA, or Ollama), and enabling the widget, the site administrator authorizes the plugin to forward visitor messages to generate replies. Visitors should be informed through your site's privacy policy; the plugin adds suggested privacy policy text under **Settings → Privacy**.

= WordPress AI Client (optional) =

* **Service:** AI models from **Settings → Connectors** in WordPress 7.0+. Connectors currently supports **OpenAI**, **Google Gemini**, and **Anthropic Claude**.
* **Used for:** Generating chat replies when **WordPress AI** is selected in plugin settings—not when **Google IA** is selected.
* **Data sent:** Chat messages, conversation context, and system prompt when a visitor sends a message. Routing and credentials are handled by WordPress core; this plugin does not store Connectors API keys and does not call third-party AI URLs directly on this path.
* **Gemini via Connectors:** If you connect Google in Connectors and choose a Gemini model here, data is sent according to Google's policies through the WordPress AI Client, not through the separate **Google IA** code path.
* **Terms and privacy** (verify current URLs on the provider site):
* **OpenAI** (e.g. GPT models): https://openai.com/policies/terms-of-use — https://openai.com/policies/privacy-policy
* **Anthropic** (e.g. Claude models): https://www.anthropic.com/legal/terms — https://www.anthropic.com/legal/privacy
* **Google** (Gemini via Connectors): https://ai.google.dev/gemini-api/terms — https://policies.google.com/privacy

= Google IA / Gemini API (optional) =

* **Service:** [Google Generative Language API](https://ai.google.dev/) (Gemini models). This is a **separate provider** in this plugin—not the same as connecting Google under Connectors.
* **Used for:** Generating chat replies when **Google IA** is selected in plugin settings—not when **WordPress AI** is selected, even if both would use the same Gemini model name.
* **Data sent:** Chat messages, conversation context, and system prompt to `generativelanguage.googleapis.com` when a visitor sends a message, using the API key you configure in the plugin or `wp-config.php`.
* **Credentials:** Stored in plugin settings and/or `MULTCH_GEMINI_API_KEY` (recommended in `wp-config.php` for production). Never exposed to the visitor's browser.
* **Model IDs:** The admin UI lists Gemini model names from **Settings → Connectors** when available; only the model ID string is reused—the HTTP request uses **your** API key and does not use Connectors credentials.
* **Google terms:** https://ai.google.dev/gemini-api/terms
* **Google privacy:** https://policies.google.com/privacy

= Ollama (optional) =

* **Service:** Ollama HTTP API on a server you control (default: `http://127.0.0.1:11434`; configurable in plugin settings).
* **Used for:** Running models locally or on your infrastructure without a cloud API key.
* **Data sent:** Chat messages, conversation context, and system prompt to the Ollama base URL you configure when a visitor sends a message. Data stays on (or transits to) the server you operate unless you point the URL to a third-party host.
* **Ollama website terms:** https://ollama.com/terms
* **Ollama website privacy:** https://ollama.com/privacy
* **Note:** Self-hosted Ollama does not use Ollama's cloud by default; review your own hosting and compliance obligations.

= Services not used by this plugin =

This plugin does **not** contact the plugin author's servers for analytics, licensing, or chat processing. OpenAI and Anthropic are used only through **WordPress AI** and **Settings → Connectors**. Google Gemini is available through Connectors (**WordPress AI**) or through the separate **Google IA** provider with your own API key. Legacy direct integrations (including DeepSeek) were removed in version 1.0.3 and are no longer supported. Donation links in the WordPress admin are ordinary hyperlinks opened only if an administrator clicks them. Optional developer credit in the frontend chat is **disabled by default** and must be enabled in **Chat Style** settings.

== Privacy ==

= What data is stored locally? =

* **Conversations:** chat messages and metadata in custom database tables.
* **Telemetry:** request events (provider, model, status, latency) in `multch_events`.
* **Settings:** plugin configuration in `multch_plugin_settings`.
* **Temporary data:** rate-limit and response-cache transients (`chatbot_*`).

= What data is sent to third parties? =

When a visitor sends a chat message, the plugin forwards content through the WordPress AI Client (Connectors), the Google Generative Language API (Google IA), or your configured Ollama server. This typically includes the visitor message, recent conversation context, and the system prompt from plugin settings. See == External services == for details, timing of transmission, and links to terms and privacy policies where applicable.

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
2. AI model settings — provider, model preferences, and Connectors link.
3. Chat style — visual themes, colors, and interactive preview.
4. Floating widget on the frontend with Sapphire theme.
5. Conversation history with filters and message detail.

== Changelog ==

= 1.0.4 =
* New **Google IA** provider: own Gemini API key, primary and fallback models; model IDs from the WordPress Connectors catalog.
* Documentation: how to choose **WordPress AI (Connectors)** vs **Google IA (direct Gemini API)**, data sent, and privacy (readme, README, `docs/AI-PROVIDERS.md`).
* External services: explicit terms and privacy links for Google IA, Ollama, and Connectors providers (OpenAI, Anthropic, Google Gemini).

= 1.0.3 =
* Text domain aligned with plugin slug (`multiai-chatbot`) via `MULTCH_TEXT_DOMAIN` constant.
* Optional telemetry file log is limited to `wp-content/uploads/multiai-chatbot/` (replaces arbitrary `telemetry_log_path`).
* Migrate cloud AI to the WordPress 7.0 AI Client (`wp_ai_client_prompt`) and **Settings → Connectors** instead of direct HTTP calls to third-party APIs.
* Remove in-plugin API key and base URL fields for Gemini, DeepSeek, and OpenAI-compatible providers.
* Keep Ollama for optional local/self-hosted models.
* Automatic migration of legacy provider settings to `wordpress_ai`.

= 1.0.2 =
* Prefix and migration updates (see repository changelog).

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
* Shortcodes `[multch_widget]` and `[multch_widget mode="inline"]`.
* REST API for JSON chat and streaming.

== Upgrade Notice ==

= 1.0.4 =
You can use **Google IA** with your own Gemini API key without moving that key to Connectors. Model names still come from the Connectors list when available.

= 1.0.3 =
Cloud AI now uses WordPress Connectors. Configure providers under **Settings → Connectors** and choose **WordPress AI** under **MultiAI ChatBot → AI Model**. API keys from older plugin versions were cleared on migration; use **Google IA** if you prefer storing a Gemini key in this plugin again.

= 1.0.1 =
Readme update: third-party AI service documentation for WordPress.org compliance. No code changes required for existing installations.

= 1.0.0 =
First public release of the plugin.
