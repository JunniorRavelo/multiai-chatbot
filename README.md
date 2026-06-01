# MultiAI ChatBot

**Version 1.0.1** · WordPress plugin that adds an AI chat widget (Gemini, DeepSeek, Ollama, or OpenAI-compatible), admin panel, and usage telemetry.

## Naming conventions (namespace)

The public widget uses the `maicb-*` class prefix and the `#chatbot-plugin-root` container with `data-maicb-root`. See [docs/NAMING.md](docs/NAMING.md). Before publishing, run `./scripts/check-namespace`.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- For Gemini, DeepSeek, or OpenAI: a valid API key
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
4. Configure the provider, API key, and styles.
5. After activation, streaming rewrite rules are registered automatically. If the stream does not respond, visit **Settings → Permalinks** and save again.

## Admin panel

| Tab | Contents |
|-----|----------|
| **General** | Global widget, welcome message, system prompt, streaming, rate limit |
| **AI Model** | Provider, API key, model, Ollama/OpenAI/DeepSeek URLs |
| **Chat Style** | CSS presets, custom colors, and widget position |
| **Statistics** | Totals, breakdown, and CSV export |
| **History** | Conversations in cards (ID `CB-YYYY-MM-DD-HH-MM-SS`), filters, and message detail |

## AI providers

### Google Gemini

- Provider: `gemini`
- Default model: `gemini-2.0-flash`
- Fallback models: comma-separated field
- Optional constant in `wp-config.php`:

```php
define( 'CHATBOT_GEMINI_API_KEY', 'your-key' );
```

### DeepSeek

- Provider: `deepseek`
- Default base URL: `https://api.deepseek.com/v1`
- Recommended default model: `deepseek-v4-flash` (fast) or `deepseek-v4-pro` (more capable)
- Fallback models: comma-separated field (rotation on 429/404/400)
- Optional constant in `wp-config.php`:

```php
define( 'CHATBOT_DEEPSEEK_API_KEY', 'your-key' );
```

Get your API key at [platform.deepseek.com](https://platform.deepseek.com/).

### Ollama

- Provider: `ollama`
- No API key required
- Default base URL: `http://127.0.0.1:11434`
- Model: name of the model installed in Ollama (e.g. `llama3`)

### OpenAI-compatible

- Provider: `openai_compatible`
- Base URL: `https://api.openai.com/v1` or another compatible endpoint
- Optional constant:

```php
define( 'CHATBOT_OPENAI_API_KEY', 'your-key' );
```

## Site usage

### Global widget

Enable **Show site-wide** on the General tab. The widget loads on `wp_footer`.

### Shortcode

```
[chatbot_widget]
[chatbot_widget mode="inline"]
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
[chatbot_widget preset="ocean" position="bottom-left"]
[chatbot_widget mode="inline" primary="#059669"]
```

Export/import theme JSON from the admin (Chat Style tab).

## Translations (i18n)

- **Source language:** English in PHP/JS code (`__()`, `esc_html_e()`).
- **Spanish:** [`languages/multiai-chatbot-es_ES.po`](languages/multiai-chatbot-es_ES.po) and [`languages/multiai-chatbot-es_CO.po`](languages/multiai-chatbot-es_CO.po).
- After editing `.po` files, compile `.mo`: `./scripts/compile-languages` (or `php scripts/compile-languages.php`).

## REST API

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/wp-json/chatbot-plugin/v1/chat` | POST | JSON response `{ answer, meta }` |
| `/chatbot-plugin/v1/chat/stream` | POST | Simulated streaming (`text/plain`) |

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
assets/
  css/
    admin.css
    chatbot.css
  js/
    chatbot.js
uninstall.php
```

## Conversation history

Each user/assistant exchange is stored in `{prefix}chatbot_conversations` and `{prefix}chatbot_messages`.

- **Public ID:** `CB-2026-05-29-14-35-42` (date and time in the site timezone)
- **Internal ID:** auto-increment number for administration
- Grouped by visitor session (30 minutes of inactivity starts a new conversation)
- The frontend sends `conversationId` in the body to continue the same thread

## Telemetry

Each chat request logs an event in the `{prefix}chatbot_events` table:

- Provider, model, status, latency, error code
- Session hash (no plain IP address)

CSV export from the **Statistics** tab. On plugin uninstall, the table and options are removed.

## Security

- Do not commit API keys to the repository.
- Use constants in `wp-config.php` in production instead of storing keys only in the database.
- IP rate limiting uses WordPress transients.
- Rotate keys if they were accidentally exposed.

## Author

**J. Santiago Ravelo Velasco**

- GitHub: [github.com/JunniorRavelo/multiai-chatbot](https://github.com/JunniorRavelo/multiai-chatbot)
- GitHub Sponsors: [github.com/sponsors/JunniorRavelo](https://github.com/sponsors/JunniorRavelo)
- LinkedIn: [linkedin.com/in/jsravelo](https://www.linkedin.com/in/jsravelo/)

## License

This project is distributed under the [GNU General Public License v2.0 or later](LICENSE) (GPL-2.0-or-later), compatible with the WordPress.org plugin directory requirements.
