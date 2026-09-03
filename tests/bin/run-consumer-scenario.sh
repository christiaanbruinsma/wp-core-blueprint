#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
REQUEST="$ROOT/tests/consumer/request.php"
ADMIN_REQUEST="$ROOT/tests/consumer/admin-request.php"
STARTER_DIR="$WP_CORE_DIR/wp-content/plugins/core-blueprint-starter-plugin"

: "${WP_CORE_DIR:?WP_CORE_DIR is required}"
: "${CB_PLUGIN_FILE:?CB_PLUGIN_FILE is required}"
: "${WP_DB_NAME:?WP_DB_NAME is required}"
: "${WP_DB_USER:?WP_DB_USER is required}"
: "${WP_DB_PASSWORD:?WP_DB_PASSWORD is required}"
: "${WP_DB_HOST:?WP_DB_HOST is required}"

export CB_CONSUMER_TABLE_PREFIX="${CB_CONSUMER_TABLE_PREFIX:-cbconsumer_}"

cleanup() {
  php "$REQUEST" cleanup >/dev/null 2>&1 || true
  rm -f -- "$WP_CORE_DIR/wp-content/mu-plugins/cb-a3-consumer-mail-guard.php"
  rm -rf -- "$STARTER_DIR"
}
trap cleanup EXIT

run_stage() {
  local stage="$1"
  local request="$REQUEST"
  local output

  if [[ "$stage" == "admin" ]]; then
    request="$ADMIN_REQUEST"
  fi

  echo "[A3 consumer] request stage: $stage"
  if ! output="$(php "$request" "$stage" 2>&1)"; then
    printf '%s\n' "$output"
    return 1
  fi

  printf '%s\n' "$output"
  if ! grep -Fq "[A3 consumer] $stage PASS" <<<"$output"; then
    echo "[A3 consumer] $stage FAIL: child process exited without its explicit PASS marker." >&2
    return 1
  fi
}

# Each stage is a separate PHP process. The isolated WordPress database persists,
# while request-local Base and Starter static state is recreated naturally.
run_stage install
run_stage activate-base
run_stage activate-starter
run_stage runtime
run_stage admin

echo "[A3 consumer] pinned Starter consumer scenario PASS"
