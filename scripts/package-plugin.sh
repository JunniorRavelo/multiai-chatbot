#!/usr/bin/env bash
# Build a WordPress-ready ZIP (no .git, .env, node_modules, etc.).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="chatbot-plugin-wp"
BUILD_ROOT="$(mktemp -d)"
DEST="${BUILD_ROOT}/${SLUG}"
OUT="${ROOT}/${SLUG}.zip"

cleanup() {
	rm -rf "${BUILD_ROOT}"
}
trap cleanup EXIT

rm -f "${OUT}"
mkdir -p "${DEST}"

rsync -a \
	--exclude='.git' \
	--exclude='.git/' \
	--exclude='.env' \
	--exclude='node_modules' \
	--exclude='*.zip' \
	--exclude='.DS_Store' \
	--exclude='scripts' \
	"${ROOT}/" "${DEST}/"

(
	cd "${BUILD_ROOT}"
	zip -rq "${OUT}" "${SLUG}"
)

echo "Created: ${OUT}"
echo "Upload this file in Plugins → Add New → Upload Plugin."
