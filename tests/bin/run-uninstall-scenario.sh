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

run_mail_designer_option_stage() {
  local stage="$1"
  local output
  local marker="mail-designer-$stage"

  echo "[A3 uninstall] Mail Designer option stage: $stage"
  if ! output="$(php -r '
$stage = isset($argv[1]) ? (string) $argv[1] : "";
$host = (string) getenv("WP_DB_HOST");
$port = 3306;
if (preg_match("/^([^:]+):([0-9]+)$/D", $host, $matches)) {
    $host = $matches[1];
    $port = (int) $matches[2];
}
$prefix = (string) getenv("CB_UNINSTALL_TABLE_PREFIX");
if (1 !== preg_match("/^[A-Za-z0-9_]+$/D", $prefix)) {
    fwrite(STDERR, "Unsafe uninstall table prefix.\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = new mysqli(
    $host,
    (string) getenv("WP_DB_USER"),
    (string) getenv("WP_DB_PASSWORD"),
    (string) getenv("WP_DB_NAME"),
    $port
);
$table = $prefix . "options";
$key = "cb_core_mail_template_overrides";

if ("seed" === $stage) {
    $value = serialize(["a3-mail-designer-sentinel" => ["subject" => "delete-me"]]);
    $autoload = "off";
    $statement = $mysqli->prepare(
        "INSERT INTO `{$table}` (option_name, option_value, autoload) VALUES (?, ?, ?) " .
        "ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = VALUES(autoload)"
    );
    $statement->bind_param("sss", $key, $value, $autoload);
    $statement->execute();
    $statement->close();

    $statement = $mysqli->prepare("SELECT option_value FROM `{$table}` WHERE option_name = ? LIMIT 1");
    $statement->bind_param("s", $key);
    $statement->execute();
    $statement->bind_result($stored);
    $found = $statement->fetch();
    $statement->close();
    $mysqli->close();

    $expected = ["a3-mail-designer-sentinel" => ["subject" => "delete-me"]];
    if (!$found || $expected !== unserialize((string) $stored, ["allowed_classes" => false])) {
        fwrite(STDERR, "Mail Designer uninstall sentinel could not be seeded.\n");
        exit(1);
    }
    fwrite(STDOUT, "[A3 uninstall] mail-designer-seed PASS\n");
    exit(0);
}

if ("verify" === $stage) {
    $statement = $mysqli->prepare("SELECT COUNT(*) FROM `{$table}` WHERE option_name = ?");
    $statement->bind_param("s", $key);
    $statement->execute();
    $statement->bind_result($count);
    $statement->fetch();
    $statement->close();
    $mysqli->close();

    if (0 !== (int) $count) {
        fwrite(STDERR, "Mail Designer template overrides survived Base uninstall.\n");
        exit(1);
    }
    fwrite(STDOUT, "[A3 uninstall] mail-designer-verify PASS\n");
    exit(0);
}

$mysqli->close();
fwrite(STDERR, "Unknown Mail Designer option stage.\n");
exit(64);
' "$stage" 2>&1)"; then
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
run_mail_designer_option_stage seed
run_ai_stage seed
run_stage deactivate-base
run_stage delete-base
run_stage verify
run_mail_designer_option_stage verify
run_ai_stage verify

echo "[A3 uninstall] destructive uninstall scenario PASS"
