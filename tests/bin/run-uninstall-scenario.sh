#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
REQUEST="$ROOT/tests/uninstall/request.php"
AI_SENTINEL="$ROOT/tests/uninstall/ai-governance-sentinel.php"

: "${WP_CORE_DIR:?WP_CORE_DIR is required}"
: "${CB_PLUGIN_FILE:?CB_PLUGIN_FILE is required}"
: "${WP_DB_NAME:?WP_DB_NAME is required}"
: "${WP_DB_USER:?WP_DB_USER is required}"
: "${WP_DB_PASSWORD:?WP_DB_PASSWORD is required}"
: "${WP_DB_HOST:?WP_DB_HOST is required}"

export CB_UNINSTALL_TABLE_PREFIX="${CB_UNINSTALL_TABLE_PREFIX:-cbuninstall_}"

cleanup() {
  php "$REQUEST" cleanup >/dev/null 2>&1 || true
  rm -f -- "$WP_CORE_DIR/wp-content/mu-plugins/cb-a3-uninstall-mail-guard.php"
}
trap cleanup EXIT

run_stage() {
  local stage="$1"
  local output

  echo "[A3 uninstall] request stage: $stage"
  if ! output="$(php "$REQUEST" "$stage" 2>&1)"; then
    printf '%s\n' "$output"
    return 1
  fi

  printf '%s\n' "$output"
  if ! grep -Fq "[A3 uninstall] $stage PASS" <<<"$output"; then
    echo "[A3 uninstall] $stage FAIL: child process exited without its explicit PASS marker." >&2
    return 1
  fi
}

run_ai_stage() {
  local stage="$1"
  local output
  local marker="ai-governance-$stage"

  echo "[A3 uninstall] AI Governance stage: $stage"
  if ! output="$(php "$AI_SENTINEL" "$stage" 2>&1)"; then
    printf '%s\n' "$output"
    return 1
  fi

  printf '%s\n' "$output"
  if ! grep -Fq "[A3 uninstall] $marker PASS" <<<"$output"; then
    echo "[A3 uninstall] $marker FAIL: child process exited without its explicit PASS marker." >&2
    return 1
  fi
}

# The plugin is deleted only through WordPress' real delete_plugins() path.
# Every transition runs in a fresh PHP process against one persistent site.
run_stage install
run_stage activate-base
# The first normal request after activation runs the canonical plugins_loaded
# schema registration/reconciliation lifecycle for Base-owned dedicated stores.
run_stage seed
run_ai_stage seed
run_stage deactivate-base
run_stage delete-base
run_stage verify
run_ai_stage verify

echo "[A3 uninstall] destructive uninstall scenario PASS"
