#!/usr/bin/env bash
# Build a WordPress-ready ZIP (no .git, .env, node_modules, etc.).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="multiai-chatbot"
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
	--exclude='.*' \
	--exclude='node_modules' \
	--exclude='*.zip' \
	--exclude='*.csv' \
	--exclude='scripts' \
	--exclude='docs' \
	--exclude='CHANGELOG.md' \
	--exclude='README.md' \
	--exclude-from="${ROOT}/scripts/package-excludes.txt" \
	"${ROOT}/" "${DEST}/"

(
	cd "${BUILD_ROOT}"
	zip -rq "${OUT}" "${SLUG}"
)

echo "Created: ${OUT}"
echo "Upload this file in Plugins → Add New → Upload Plugin."
