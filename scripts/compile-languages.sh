#!/usr/bin/env bash
# Compile .po translation files to .mo for WordPress (chatbot-plugin-wp).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
LANG_DIR="${ROOT}/languages"

if command -v msgfmt >/dev/null 2>&1; then
	for po in "${LANG_DIR}"/chatbot-plugin-wp-*.po; do
		[[ -f "$po" ]] || continue
		mo="${po%.po}.mo"
		msgfmt -o "$mo" "$po"
		echo "compiled: $(basename "$mo")"
	done
	exit 0
fi

php "${ROOT}/scripts/compile-languages.php"
