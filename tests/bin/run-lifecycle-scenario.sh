#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
REQUEST="$ROOT/tests/lifecycle/request.php"

: "${WP_CORE_DIR:?WP_CORE_DIR is required}"
: "${CB_PLUGIN_FILE:?CB_PLUGIN_FILE is required}"
: "${WP_DB_NAME:?WP_DB_NAME is required}"
: "${WP_DB_USER:?WP_DB_USER is required}"
: "${WP_DB_PASSWORD:?WP_DB_PASSWORD is required}"
: "${WP_DB_HOST:?WP_DB_HOST is required}"

export CB_LIFECYCLE_TABLE_PREFIX="${CB_LIFECYCLE_TABLE_PREFIX:-cblifecycle_}"

cleanup() {
  php "$REQUEST" cleanup >/dev/null 2>&1 || true
  rm -f -- "$WP_CORE_DIR/wp-content/mu-plugins/cb-a2-mail-guard.php"
}
trap cleanup EXIT

run_stage() {
  local stage="$1"
  local output

  echo "[A2] request stage: $stage"
  if ! output="$(php "$REQUEST" "$stage" 2>&1)"; then
    printf '%s\n' "$output"
    return 1
  fi

  printf '%s\n' "$output"
  if ! grep -Fq "[A2] $stage PASS" <<<"$output"; then
    echo "[A2] $stage FAIL: child process exited without its explicit PASS marker." >&2
    return 1
  fi
}

# Every call below is intentionally a separate PHP process. WordPress database
# state persists through one isolated table prefix while request-local statics do not.
run_stage install
run_stage first-activation
run_stage deactivation
run_stage reactivation
run_stage damage-schema
run_stage repair-schema
run_stage schema-contracts

echo "[A2] lifecycle/data request-boundary scenario PASS"
