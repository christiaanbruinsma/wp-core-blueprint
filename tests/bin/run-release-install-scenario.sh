#!/usr/bin/env bash
set -euo pipefail

CANDIDATE_ZIP="${1:-}"
PREVIOUS_ZIP="${2:-}"
CANDIDATE_VERSION="${3:-}"
PREVIOUS_VERSION="${4:-}"

if [[ -z "$CANDIDATE_ZIP" || -z "$PREVIOUS_ZIP" || -z "$CANDIDATE_VERSION" || -z "$PREVIOUS_VERSION" ]]; then
  echo "Usage: $0 <candidate.zip> <previous.zip> <candidate-version> <previous-version>" >&2
  exit 64
fi

: "${CB_WP_CLI_PHAR:?CB_WP_CLI_PHAR is required}"
: "${WP_DB_NAME:?WP_DB_NAME is required}"
: "${WP_DB_USER:?WP_DB_USER is required}"
: "${WP_DB_PASSWORD:?WP_DB_PASSWORD is required}"
: "${WP_DB_HOST:?WP_DB_HOST is required}"

for file in "$CANDIDATE_ZIP" "$PREVIOUS_ZIP" "$CB_WP_CLI_PHAR"; do
  if [[ ! -f "$file" ]]; then
    echo "[H] Required file missing: $file" >&2
    exit 1
  fi
done

WP_VERSION="7.0"
TMP_ROOT="$(mktemp -d)"
cleanup() {
  rm -rf -- "$TMP_ROOT"
}
trap cleanup EXIT

wp_cli() {
  local site_dir="$1"
  shift
  php "$CB_WP_CLI_PHAR" --path="$site_dir" --no-color "$@"
}

prepare_site() {
  local site_dir="$1"
  local prefix="$2"
  local url="$3"
  local title="$4"

  mkdir -p "$site_dir"
  curl --fail --silent --show-error --location \
    "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" \
    | tar -xz --strip-components=1 -C "$site_dir"

  wp_cli "$site_dir" config create \
    --dbname="$WP_DB_NAME" \
    --dbuser="$WP_DB_USER" \
    --dbpass="$WP_DB_PASSWORD" \
    --dbhost="$WP_DB_HOST" \
    --dbprefix="$prefix" \
    --skip-check

  wp_cli "$site_dir" config set WP_DEBUG true --raw
  wp_cli "$site_dir" config set WP_DEBUG_LOG true --raw
  wp_cli "$site_dir" config set WP_DEBUG_DISPLAY false --raw

  wp_cli "$site_dir" core install \
    --url="$url" \
    --title="$title" \
    --admin_user='cb-release-admin' \
    --admin_password='cb-release-password-only-for-ci' \
    --admin_email='cb-release@example.test' \
    --skip-email
}

assert_clean_debug_log() {
  local site_dir="$1"
  local debug_log="$site_dir/wp-content/debug.log"
  if [[ -s "$debug_log" ]]; then
    echo "[H] WordPress debug.log is not empty:" >&2
    cat "$debug_log" >&2
    exit 1
  fi
}

assert_active_version() {
  local site_dir="$1"
  local expected="$2"
  local actual

  if ! wp_cli "$site_dir" plugin is-active core-blueprint >/dev/null 2>&1; then
    echo "[H] Core Blueprint is not active at $site_dir" >&2
    exit 1
  fi

  actual="$(wp_cli "$site_dir" plugin get core-blueprint --field=version)"
  if [[ "$actual" != "$expected" ]]; then
    echo "[H] Plugin version mismatch: expected $expected, got $actual" >&2
    exit 1
  fi

  runtime_version="$(wp_cli "$site_dir" eval 'echo defined( "CB_CORE_VERSION" ) ? CB_CORE_VERSION : "missing";')"
  if [[ "$runtime_version" != "$expected" ]]; then
    echo "[H] Runtime version mismatch: expected $expected, got $runtime_version" >&2
    exit 1
  fi
}

FRESH_SITE="$TMP_ROOT/fresh"
prepare_site "$FRESH_SITE" 'cbhfresh_' 'http://core-blueprint-release-fresh.test' 'Core Blueprint Release Fresh'
wp_cli "$FRESH_SITE" plugin install "$CANDIDATE_ZIP" --activate
assert_active_version "$FRESH_SITE" "$CANDIDATE_VERSION"

operator_exists="$(wp_cli "$FRESH_SITE" eval 'echo get_role( "cb_operator" ) ? "yes" : "no";')"
if [[ "$operator_exists" != "yes" ]]; then
  echo "[H] Fresh package activation did not create cb_operator." >&2
  exit 1
fi

assert_clean_debug_log "$FRESH_SITE"
echo "[H] fresh release ZIP install/activation PASS: $CANDIDATE_VERSION"

UPDATE_SITE="$TMP_ROOT/update"
prepare_site "$UPDATE_SITE" 'cbhupdate_' 'http://core-blueprint-release-update.test' 'Core Blueprint Release Update'
wp_cli "$UPDATE_SITE" plugin install "$PREVIOUS_ZIP" --activate
assert_active_version "$UPDATE_SITE" "$PREVIOUS_VERSION"
assert_clean_debug_log "$UPDATE_SITE"

wp_cli "$UPDATE_SITE" plugin install "$CANDIDATE_ZIP" --force
assert_active_version "$UPDATE_SITE" "$CANDIDATE_VERSION"
assert_clean_debug_log "$UPDATE_SITE"

echo "[H] update-over-current-RC PASS: $PREVIOUS_VERSION -> $CANDIDATE_VERSION"
echo "[H] release install/update scenario PASS"
