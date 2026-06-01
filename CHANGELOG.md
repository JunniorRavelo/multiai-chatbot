# Changelog

## 1.0.2

### Changed

- Unique plugin prefix `multch` / `MULTCH_` for PHP classes, hooks, options, transients, REST routes (`multch/v1`), shortcode (`[multch_widget]`), and script handles (WordPress.org naming guidelines).
- Automatic migration from legacy `chatbot_*` options, database tables, and cron events on upgrade.
- `CHATBOT_*` constants in `wp-config.php` remain supported as fallbacks for `MULTCH_*`.

### Breaking (upgrade notes)

- Shortcode is now `[multch_widget]` (replace `[chatbot_widget]` in content).
- REST base path is `/wp-json/multch/v1/` (was `chatbot-plugin/v1`).
- Admin screen slug: `admin.php?page=multch-plugin`.

## 1.0.1

### Changed

- Version bump to 1.0.1 for WordPress.org release.
- `readme.txt`: document optional third-party AI services (Gemini, DeepSeek, OpenAI-compatible, Ollama) with what data is sent, when it is sent, and links to each provider's terms and privacy policies.

## 1.1.0

### Changed

- Widget CSS classes migrated from `cb-*` to `maicb-*` (MultiAI ChatBot) to reduce collisions with themes and other plugins.
- CSS custom properties renamed from `--cb-*` to `--maicb-*` on the public widget.
- Widget styles are scoped under `#multch-plugin-root` and `#multch-style-preview`.
- Widget roots expose `data-maicb-root`; critical controls use `data-maicb` hooks for JavaScript.
- Multiple widget instances receive unique root IDs (`multch-plugin-root-2`, etc.).

### Added

- [docs/NAMING.md](docs/NAMING.md) naming conventions.
- `scripts/check-namespace` audit script.
- WordPress filters: `multch_plugin_root_id`, `multch_widget_class_prefix`.

### Removed

- Deprecated `cb-*` widget class names (no backward-compatible aliases in 1.1.0). Custom CSS must use `maicb-*` selectors.

## 1.0.0

- Initial release.
