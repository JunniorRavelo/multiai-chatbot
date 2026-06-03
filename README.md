# MultiAI ChatBot

**Version 1.0.4** · WordPress plugin that adds an AI chat widget via the WordPress AI Client (Connectors), your own Google Gemini API key (**Google IA**), or Ollama, plus admin panel and usage telemetry.

## Naming conventions (namespace)

The public widget uses the `maicb-*` class prefix and the `#multch-plugin-root` container with `data-maicb-root`. See [docs/NAMING.md](docs/NAMING.md). Before publishing, run `./scripts/check-namespace`.

## Requirements

- WordPress 6.2+ (**tested up to 7.0**; 7.0+ recommended for cloud AI via Connectors)
- PHP 8.0+
- For WordPress AI: providers connected under **Settings → Connectors**
- For Google IA: a [Google AI (Gemini) API key](https://aistudio.google.com/apikey); WordPress 7.0+ recommended so the admin model list matches **Settings → Connectors**
- For Ollama: a server reachable from the WordPress host (e.g. `http://127.0.0.1:11434`)

## Installation

### WordPress ZIP (without `.git`)

WordPress **does not allow** uploading a ZIP that includes the `.git` folder. Generate the package from the repository:

```bash
./scripts/package-plugin
```

This creates `multiai-chatbot.zip`, ready for **Plugins → Add New → Upload Plugin**. The ZIP **does not include** `scripts/` (development tools). For production, always use that ZIP or exclude `scripts/` if you deploy via Git (e.g. WP Pusher). Translation compilation (`./scripts/compile-languages`) is for local development only.

### Publishing and Plugin Check

Before submitting to WordPress.org, build and verify the production package:

```bash
./scripts/verify-plugin-package
```

This runs `./scripts/package-plugin`, confirms the ZIP excludes `scripts/`, `.github/`, `.git`, and `.env`, and runs `wp plugin check` when WP-CLI is available.

**Important:** Run [Plugin Check](https://wordpress.org/plugins/plugin-check/) (or `wp plugin check`) on the **unzipped contents of `multiai-chatbot.zip`**, not on the full Git repository. Scanning the repo includes development-only files (`scripts/`, `.github/`) that are not shipped in the ZIP and will produce false positives.

1. Copy the `multiai-chatbot` folder to `wp-content/plugins/` (or use the ZIP above).
2. Activate the plugin under **Plugins**.
3. Go to **MultiAI ChatBot** in the admin menu.
4. Under **AI Model**, choose **WordPress AI**, **Google IA**, or **Ollama**. For WordPress AI, connect providers under **Settings → Connectors** first. For Google IA, enter your Gemini API key and pick primary/fallback models from the Connectors catalog.
5. After activation, streaming rewrite rules are registered automatically. If the stream does not respond, visit **Settings → Permalinks** and save again.

## Admin panel

| Tab | Contents |
|-----|----------|
| **General** | Global widget, welcome message, system prompt, streaming, optional statistics/history |
| **AI Model** | Provider (WordPress AI, Google IA, or Ollama), models, API key (Google IA), Ollama URL |
| **Security** | Allowed origins, cache, telemetry, IP rate limits, and abuse suspension |
| **Chat Style** | CSS presets, custom colors, and widget position |
| **Statistics** | Totals, breakdown, and CSV export |
| **History** | Conversations in cards (ID `CB-YYYY-MM-DD-HH-MM-SS`), filters, and message detail |

## Choosing WordPress AI vs Google IA (Gemini)

You enable **one** provider at a time under **MultiAI ChatBot → AI Model**.

| | **WordPress AI** | **Google IA** |
|---|------------------|---------------|
| **Best for** | Connectors already set up; OpenAI, Google Gemini, or Anthropic | Gemini only; key in this plugin or `wp-config.php` |
| **API key** | **Settings → Connectors** (not stored in this plugin) | **AI Model** tab or `MULTCH_GEMINI_API_KEY` |
| **Request path** | WordPress AI Client → Connectors | Direct HTTPS to `generativelanguage.googleapis.com` |
| **WP version** | 7.0+ recommended | 6.2+ (7.0+ recommended for model list from Connectors) |
| **Same Gemini model IDs** | Yes | Yes (catalog from Connectors when available) |

**Data sent to third parties:** only when a visitor sends a chat message (message, recent context, system prompt). The API key is never sent to the browser. **Local** history and telemetry are optional (off by default). See [docs/AI-PROVIDERS.md](docs/AI-PROVIDERS.md) and `readme.txt` (== External services ==, == Privacy ==) for WordPress.org compliance detail.

**Suggested site privacy policy text:** **Settings → Privacy** (registered by the plugin).

## AI providers

### WordPress AI (Connectors)

- Provider ID: `wordpress_ai`
- Requires WordPress 7.0+ with the built-in AI Client
- Connect **OpenAI**, **Google (Gemini)**, or **Anthropic** under **Settings → Connectors** (the connectors currently supported by WordPress 7.0+)
- Set a **primary model** and optional **fallback model** in **MultiAI ChatBot → AI Model**
- Optional **Google automatic fallback** when the configured models fail
- Optional `wp-config.php` overrides for model preference:

```php
define( 'MULTCH_MODEL', 'gemini-2.5-flash' );
define( 'MULTCH_MODEL_CANDIDATES', 'gpt-4o-mini,claude-sonnet-4-6' );
```

### Google IA (own Gemini API key)

- Provider ID: `google_ia`
- Uses the [Google Generative Language API](https://ai.google.dev/) with an API key you supply (not WordPress Connectors credentials)
- **Primary model** and **fallback model** are chosen from the same Gemini model IDs listed by WordPress Connectors (Connectors is used as a catalog only; requests go to Google with your key)
- If the primary model fails (quota, rate limit, or unavailability), the plugin tries the fallback model
- Configure in admin under **MultiAI ChatBot → AI Model**, or in `wp-config.php`:

```php
define( 'MULTCH_PROVIDER', 'google_ia' );
define( 'MULTCH_GEMINI_API_KEY', 'your-api-key' );
define( 'MULTCH_GEMINI_MODEL', 'gemini-2.5-flash' );
define( 'MULTCH_GEMINI_MODEL_CANDIDATES', 'gemini-2.5-flash-lite' );
```

Legacy names `CHATBOT_PROVIDER`, `CHATBOT_GEMINI_API_KEY`, `CHATBOT_GEMINI_MODEL`, and `CHATBOT_GEMINI_MODEL_CANDIDATES` are also supported.

### Ollama

- Provider ID: `ollama`
- No API key in this plugin
- Default base URL: `http://127.0.0.1:11434`
- Model: name of the model installed in Ollama (e.g. `llama3`)

## Site usage

### Global widget

Enable **Show site-wide** on the General tab. The widget loads on `wp_footer`.

### Shortcode

```
[multch_widget]
[multch_widget mode="inline"]
```

- `floating` (default): floating button + panel
- `inline`: panel embedded in the page

## Styles

Presets available on the **Chat Style** tab (visual selector with preview):

| ID | Name |
|----|------|
| `default` | Sapphire |
| `dark-glass` | Midnight |
| `obsidian` | Obsidian |
| `minimal` | Monochrome |
| `ocean` | Aqua |
| `sunset` | Ember |
| `forest` | Emerald |
| `lavender` | Amethyst |
| `plum` | Plum |

**Positions:** `bottom-right`, `center-right`, `bottom-left`, `center-left`, `bottom-center`.

**Optional overrides:** primary color, accent, background, text, radius, panel max width and height, font, z-index, animations, and automatic theme via `prefers-color-scheme`.

**Per-page style via shortcode:**

```
[multch_widget preset="ocean" position="bottom-left"]
[multch_widget mode="inline" primary="#059669"]
```

Export/import theme JSON from the admin (Chat Style tab).

## Translations (i18n)

- **Source language:** English in PHP/JS code (`__()`, `esc_html_e()`).
- **Spanish:** [`languages/multiai-chatbot-es_ES.po`](languages/multiai-chatbot-es_ES.po) and [`languages/multiai-chatbot-es_CO.po`](languages/multiai-chatbot-es_CO.po).
- After editing `.po` files, compile `.mo`: `./scripts/compile-languages` (or `php scripts/compile-languages.php`).

## REST API

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/wp-json/multch/v1/chat` | POST | JSON response `{ answer, meta }` |
| `/multch/v1/chat/stream` | POST | Simulated streaming (`text/plain`) |

Required headers:

- `X-WP-Nonce`: REST nonce (`wp_rest`)
- `X-Chat-Session-Id`: anonymous session identifier (optional)

Example body:

```json
{
  "message": "Hello",
  "history": [
    { "role": "user", "content": "..." },
    { "role": "assistant", "content": "..." }
  ],
  "currentPath": "/",
  "currentUrl": "https://example.com/"
}
```

The API key is **never** exposed to the frontend.

## Plugin structure

```
multiai-chatbot.php
includes/
  class-plugin.php
  admin-settings.php
  api-handler.php
  rest-api.php
  telemetry.php
  enqueue.php
  providers/
    class-provider-wordpress-ai.php
    class-provider-google-ia.php
    class-provider-ollama.php
assets/
  css/
    admin.css
    chatbot.css
  js/
    chatbot.js
uninstall.php
```

## Conversation history

When **Store statistics and history** is enabled under General (disabled by default), each user/assistant exchange is stored in `{prefix}multch_conversations` and `{prefix}multch_messages`.

- **Public ID:** `CB-2026-05-29-14-35-42` (date and time in the site timezone)
- **Internal ID:** auto-increment number for administration
- Grouped by visitor session (30 minutes of inactivity starts a new conversation)
- The frontend sends `conversationId` in the body to continue the same thread

## Telemetry

When statistics and history are enabled, each chat request logs an event in the `{prefix}multch_events` table:

- Provider, model, status, latency, error code
- Session hash (no plain IP address)

CSV export from the **Statistics** tab. On plugin uninstall, the table and options are removed.

## Security

- Do not commit API keys to the repository.
- For **Google IA**, prefer `MULTCH_GEMINI_API_KEY` in `wp-config.php` in production instead of storing the key only in the database.
- **WordPress AI** keys stay in **Settings → Connectors**; they are never sent to the browser.
- IP rate limiting uses WordPress transients.
- Rotate keys if they were accidentally exposed.

See [docs/env.example](docs/env.example) and [docs/AI-PROVIDERS.md](docs/AI-PROVIDERS.md) for configuration and provider choice.

## WordPress.org submission

Before uploading to the plugin directory:

1. Run `./scripts/verify-plugin-package` and [Plugin Check](https://wordpress.org/plugins/plugin-check/) on the **unzipped ZIP**, not the full Git repo.
2. Ensure `readme.txt` documents all optional external services (Connectors: OpenAI, Anthropic, Google Gemini; plus Google IA and Ollama), when data is sent, and privacy links — see == External services == and == Privacy ==.
3. Confirm **Stable tag** and `Version` in `multiai-chatbot.php` match the release you submit.
4. Set **Tested up to** in `readme.txt` to the latest stable WordPress major version (currently **7.0**). WordPress.org rejects values below the current minimum (for example, `6.8` when `6.9+` is required).

## Author

**J. Santiago Ravelo Velasco**

- GitHub: [github.com/JunniorRavelo/multiai-chatbot](https://github.com/JunniorRavelo/multiai-chatbot)
- GitHub Sponsors: [github.com/sponsors/JunniorRavelo](https://github.com/sponsors/JunniorRavelo)
- LinkedIn: [linkedin.com/in/jsravelo](https://www.linkedin.com/in/jsravelo/)

## License

This project is distributed under the [GNU General Public License v2.0 or later](LICENSE) (GPL-2.0-or-later), compatible with the WordPress.org plugin directory requirements.
