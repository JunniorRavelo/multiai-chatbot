#!/usr/bin/env bash
# Instala exclusiones de desarrollo en .git/info/exclude (sin .gitignore en el plugin).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
EXCLUDE="${ROOT}/.git/info/exclude"
MARKER="# multiai-chatbot dev excludes"

if [[ ! -d "${ROOT}/.git" ]]; then
	echo "Error: no es un repositorio Git." >&2
	exit 1
fi

if grep -qF "${MARKER}" "${EXCLUDE}" 2>/dev/null; then
	echo "Exclusiones de desarrollo ya instaladas en .git/info/exclude"
	exit 0
fi

{
	echo ""
	echo "${MARKER}"
	cat "${ROOT}/scripts/gitignore.template" | grep -v '^#' | grep -v '^[[:space:]]*$'
} >> "${EXCLUDE}"

echo "Exclusiones instaladas en .git/info/exclude"
echo "No se crea .gitignore en la raíz del plugin."
