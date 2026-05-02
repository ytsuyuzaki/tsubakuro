#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="tsubakuro"
DIST_DIR="dist"
BUILD_DIR="${DIST_DIR}/build/${PLUGIN_SLUG}"
ZIP_FILE="${DIST_DIR}/${PLUGIN_SLUG}.zip"

rm -rf "${DIST_DIR}/build" "${ZIP_FILE}"
mkdir -p "${BUILD_DIR}"

copy_path() {
	local path="$1"
	if [ -e "${path}" ]; then
		mkdir -p "${BUILD_DIR}/$(dirname "${path}")"
		cp -R "${path}" "${BUILD_DIR}/${path}"
	fi
}

copy_path "tsubakuro.php"
copy_path "README.md"
copy_path "includes"
copy_path "admin"
copy_path "public"
copy_path "templates"

mkdir -p "${DIST_DIR}"
(
	cd "${DIST_DIR}/build"
	zip -rq "../${PLUGIN_SLUG}.zip" "${PLUGIN_SLUG}"
)

rm -rf "${DIST_DIR}/build"
echo "Created ${ZIP_FILE}"
