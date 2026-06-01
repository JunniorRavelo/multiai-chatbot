#!/usr/bin/env python3
"""One-shot rename: chatbot_* / Chatbot_* → multch_* / Multch_* (excludes text domain multiai-chatbot)."""
from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

SKIP_DIRS = {".git", "node_modules", "vendor", "languages"}
EXTENSIONS = {".php", ".js", ".css", ".md", ".txt", ".sh", ".py", ".yml", ".example"}

# Order: longest / most specific first.
REPLACEMENTS = [
    ("MULTCH_PLUGIN_VERSION", "MULTCH_PLUGIN_VERSION"),
    ("MULTCH_PLUGIN_FILE", "MULTCH_PLUGIN_FILE"),
    ("MULTCH_PLUGIN_PATH", "MULTCH_PLUGIN_PATH"),
    ("MULTCH_PLUGIN_URL", "MULTCH_PLUGIN_URL"),
    ("MULTCH_PLUGIN_BASENAME", "MULTCH_PLUGIN_BASENAME"),
    ("Multch_Admin_Settings", "Multch_Admin_Settings"),
    ("Multch_Api_Handler", "Multch_Api_Handler"),
    ("Multch_Chat_History", "Multch_Chat_History"),
    ("Multch_Donation_Footer", "Multch_Donation_Footer"),
    ("Multch_Provider_DeepSeek", "Multch_Provider_DeepSeek"),
    ("Multch_Provider_OpenAI", "Multch_Provider_OpenAI"),
    ("Multch_Provider_Ollama", "Multch_Provider_Ollama"),
    ("Multch_Provider_Gemini", "Multch_Provider_Gemini"),
    ("Multch_AI_Provider", "Multch_AI_Provider"),
    ("Multch_Admin_Settings", "Multch_Admin_Settings"),
    ("Multch_Rest_Api", "Multch_Rest_Api"),
    ("Multch_Enqueue", "Multch_Enqueue"),
    ("Multch_Telemetry", "Multch_Telemetry"),
    ("Multch_Privacy", "Multch_Privacy"),
    ("Multch_Plugin", "Multch_Plugin"),
    ("multch_plugin_telemetry_db_version", "multch_plugin_telemetry_db_version"),
    ("multch_plugin_history_db_version", "multch_plugin_history_db_version"),
    ("multch_plugin_settings", "multch_plugin_settings"),
    ("multch_plugin_db_version", "multch_plugin_db_version"),
    ("multch_plugin_group", "multch_plugin_group"),
    ("multch_plugin_default_root_id", "multch_plugin_default_root_id"),
    ("multch_plugin_widget_class_prefix", "multch_plugin_widget_class_prefix"),
    ("multch_plugin_allocate_root_id", "multch_plugin_allocate_root_id"),
    ("multch_plugin_root_id", "multch_plugin_root_id"),
    ("multch_widget_class_prefix", "multch_widget_class_prefix"),
    ("multch_purge_telemetry", "multch_purge_telemetry"),
    ("multch_purge_history", "multch_purge_history"),
    ("multch_export_history_csv", "multch_export_history_csv"),
    ("multch_export_csv", "multch_export_csv"),
    ("multch_delete_conversation", "multch_delete_conversation"),
    ("multch_history_detail", "multch_history_detail"),
    ("multch_invalid_css_size_", "multch_invalid_css_size_"),
    ("multch_empty_widget_title", "multch_empty_widget_title"),
    ("multch_settings_saved", "multch_settings_saved"),
    ("multch_admin_tab", "multch_admin_tab"),
    ("multch_style_presets", "multch_style_presets"),
    ("multch_style_config", "multch_style_config"),
    ("multch_rl_model_min", "multch_rl_model_min"),
    ("multch_rl_model_day", "multch_rl_model_day"),
    ("multch_violations_", "multch_violations_"),
    ("multch_suspend_", "multch_suspend_"),
    ("multch_rl_min_", "multch_rl_min_"),
    ("multch_rl_day_", "multch_rl_day_"),
    ("multch_resp_", "multch_resp_"),
    ("multch_conversations", "multch_conversations"),
    ("multch_messages", "multch_messages"),
    ("multch_events", "multch_events"),
    ("multch-plugin-admin-preview-shared", "multch-plugin-admin-preview-shared"),
    ("multch-plugin-admin-preview", "multch-plugin-admin-preview"),
    ("multch-plugin-admin-feedback", "multch-plugin-admin-feedback"),
    ("multch-plugin-admin-history", "multch-plugin-admin-history"),
    ("multch-plugin-admin-general", "multch-plugin-admin-general"),
    ("multch-plugin-admin-style", "multch-plugin-admin-style"),
    ("multch-plugin-admin-stats", "multch-plugin-admin-stats"),
    ("multch-plugin-admin", "multch-plugin-admin"),
    ("multch/v1", "multch/v1"),
    ("multch-plugin-root-", "multch-plugin-root-"),
    ("multch-plugin-root", "multch-plugin-root"),
    ("multch-plugin-session-v1", "multch-plugin-session-v1"),
    ("multch-plugin-open-state-v1", "multch-plugin-open-state-v1"),
    ("multch-plugin-anon-id", "multch-plugin-anon-id"),
    ("multch-plugin-conversation-v1", "multch-plugin-conversation-v1"),
    ("multch-plugin", "multch-plugin"),
    ("multch-plugin", "multch-plugin"),
    ("multchPluginConfig", "multchPluginConfig"),
    ("multchStylePreview", "multchStylePreview"),
    ("multchGeneralPreview", "multchGeneralPreview"),
    ("multchHistoryAdmin", "multchHistoryAdmin"),
    ("multch_widget", "multch_widget"),
    ("multch_stream", "multch_stream"),
    ("multch-history-", "multch-history-"),
    ("multch-donation-footer", "multch-donation-footer"),
    ("multch-admin-history", "multch-admin-history"),
    ("multch-admin-stats", "multch-admin-stats"),
    ("multch-admin-general", "multch-admin-general"),
    ("multch-admin-style", "multch-admin-style"),
    ("multch-admin-feedback", "multch-admin-feedback"),
    ("multch-admin", "multch-admin"),
    ("#multch-style-preview", "#multch-style-preview"),
    ("#multch-preview-viewport", "#multch-preview-viewport"),
    ("#multch-preview", "#multch-preview"),
    ("#multch-plugin-root", "#multch-plugin-root"),
    ("Multch_Plugin_WP", "Multch_Plugin"),
    ("_transient_timeout_multch_", "_transient_timeout_multch_"),
    ("_transient_multch_", "_transient_multch_"),
    ("MULTCH_GEMINI_MODEL_CANDIDATES", "MULTCH_GEMINI_MODEL_CANDIDATES"),
    ("MULTCH_GEMINI_MODEL", "MULTCH_GEMINI_MODEL"),
    ("MULTCH_STREAMING_ENABLED", "MULTCH_STREAMING_ENABLED"),
    ("MULTCH_MODEL_CANDIDATES", "MULTCH_MODEL_CANDIDATES"),
    ("MULTCH_INTERNAL_CHAT_BASE_URL", "MULTCH_INTERNAL_CHAT_BASE_URL"),
    ("MULTCH_IP_SUSPEND_AFTER_VIOLATIONS", "MULTCH_IP_SUSPEND_AFTER_VIOLATIONS"),
    ("MULTCH_RATE_LIMIT_MODEL_PER_MINUTE", "MULTCH_RATE_LIMIT_MODEL_PER_MINUTE"),
    ("MULTCH_RATE_LIMIT_MODEL_PER_DAY", "MULTCH_RATE_LIMIT_MODEL_PER_DAY"),
    ("MULTCH_RATE_LIMIT_SOFT_THRESHOLD", "MULTCH_RATE_LIMIT_SOFT_THRESHOLD"),
    ("MULTCH_RATE_LIMIT_PER_MINUTE", "MULTCH_RATE_LIMIT_PER_MINUTE"),
    ("MULTCH_TELEMETRY_LOG_PATH", "MULTCH_TELEMETRY_LOG_PATH"),
    ("MULTCH_RATE_LIMIT_PER_DAY", "MULTCH_RATE_LIMIT_PER_DAY"),
    ("MULTCH_IP_SUSPEND_SECONDS", "MULTCH_IP_SUSPEND_SECONDS"),
    ("MULTCH_CACHE_TTL_SECONDS", "MULTCH_CACHE_TTL_SECONDS"),
    ("MULTCH_WELCOME_MESSAGE", "MULTCH_WELCOME_MESSAGE"),
    ("MULTCH_WIDGET_SUBTITLE", "MULTCH_WIDGET_SUBTITLE"),
    ("MULTCH_ALLOWED_ORIGINS", "MULTCH_ALLOWED_ORIGINS"),
    ("MULTCH_SYSTEM_PROMPT", "MULTCH_SYSTEM_PROMPT"),
    ("MULTCH_WIDGET_TITLE", "MULTCH_WIDGET_TITLE"),
    ("MULTCH_GEMINI_API_KEY", "MULTCH_GEMINI_API_KEY"),
    ("MULTCH_PROVIDER", "MULTCH_PROVIDER"),
    ("MULTCH_MODEL", "MULTCH_MODEL"),
]

# Public conversation IDs: CB- → MCH-
PUBLIC_ID_RE = re.compile(r"'MCH-'")


def should_process(path: Path) -> bool:
    if path.suffix not in EXTENSIONS:
        return False
    parts = path.relative_to(ROOT).parts
    return not any(p in SKIP_DIRS for p in parts)


def process_file(path: Path) -> bool:
    text = path.read_text(encoding="utf-8")
    original = text
    for old, new in REPLACEMENTS:
        text = text.replace(old, new)
    text = text.replace("'MCH-'", "'MCH-'")
    text = text.replace('generate_public_id(): string {\n\t\t$base = \'MCH-\'', "generate_public_id(): string {\n\t\t$base = 'MCH-'")  # noop safety
    if text != original:
        path.write_text(text, encoding="utf-8")
        return True
    return False


def main() -> None:
    changed = 0
    for path in ROOT.rglob("*"):
        if not path.is_file() or not should_process(path):
            continue
        if process_file(path):
            changed += 1
            print(path.relative_to(ROOT))
    print(f"Updated {changed} files.")


if __name__ == "__main__":
    main()
