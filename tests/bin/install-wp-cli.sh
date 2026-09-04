#!/usr/bin/env bash
set -euo pipefail

WP_CLI_VERSION="2.12.0"
WP_CLI_ASSET="wp-cli-${WP_CLI_VERSION}.phar"
WP_CLI_SHA256="ce34ddd838f7351d6759068d09793f26755463b4a4610a5a5c0a97b68220d85c"
WP_CLI_URL="https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/${WP_CLI_ASSET}"
WP_CLI_DEST="${CB_WP_CLI_PHAR:-/tmp/core-blueprint-tools/${WP_CLI_ASSET}}"
WP_CLI_TMP="${WP_CLI_DEST}.download.$$"

mkdir -p "$(dirname "$WP_CLI_DEST")"

cleanup() {
  rm -f -- "$WP_CLI_TMP"
}
trap cleanup EXIT

curl --fail --silent --show-error --location \
  --output "$WP_CLI_TMP" \
  "$WP_CLI_URL"

actual_sha256="$(sha256sum "$WP_CLI_TMP" | awk '{print $1}')"
if [[ "$actual_sha256" != "$WP_CLI_SHA256" ]]; then
  echo "[B0] WP-CLI checksum mismatch: expected $WP_CLI_SHA256, got $actual_sha256" >&2
  exit 1
fi

chmod 0755 "$WP_CLI_TMP"
mv -f -- "$WP_CLI_TMP" "$WP_CLI_DEST"
trap - EXIT

echo "[B0] Installed pinned WP-CLI ${WP_CLI_VERSION} at ${WP_CLI_DEST}"
