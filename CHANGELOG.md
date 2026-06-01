# Changelog

## 1.0.1

### Changed

- Version bump to 1.0.1 for WordPress.org release.
- `readme.txt`: document optional third-party AI services (Gemini, DeepSeek, OpenAI-compatible, Ollama) with what data is sent, when it is sent, and links to each provider's terms and privacy policies.

## 1.1.0

### Changed

- Widget CSS classes migrated from `cb-*` to `maicb-*` (MultiAI ChatBot) to reduce collisions with themes and other plugins.
- CSS custom properties renamed from `--cb-*` to `--maicb-*` on the public widget.
- Widget styles are scoped under `#chatbot-plugin-root` and `#chatbot-style-preview`.
- Widget roots expose `data-maicb-root`; critical controls use `data-maicb` hooks for JavaScript.
- Multiple widget instances receive unique root IDs (`chatbot-plugin-root-2`, etc.).

### Added

- [docs/NAMING.md](docs/NAMING.md) naming conventions.
- `scripts/check-namespace` audit script.
- WordPress filters: `chatbot_plugin_root_id`, `chatbot_widget_class_prefix`.

### Removed

- Deprecated `cb-*` widget class names (no backward-compatible aliases in 1.1.0). Custom CSS must use `maicb-*` selectors.

## 1.0.0

- Initial release.
