#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
REQUEST="$ROOT/tests/performance/request.php"
RESULTS_DIR="${CB_PERFORMANCE_RESULTS_DIR:-${TMPDIR:-/tmp}/cb-performance-baseline}"
AUTH_GUARD="${WP_CORE_DIR:-}/wp-content/mu-plugins/cb-f2-auth-guard.php"

: "${WP_CORE_DIR:?WP_CORE_DIR is required}"
: "${CB_PLUGIN_FILE:?CB_PLUGIN_FILE is required}"
: "${WP_DB_NAME:?WP_DB_NAME is required}"
: "${WP_DB_USER:?WP_DB_USER is required}"
: "${WP_DB_PASSWORD:?WP_DB_PASSWORD is required}"
: "${WP_DB_HOST:?WP_DB_HOST is required}"

export CB_PERFORMANCE_TABLE_PREFIX="${CB_PERFORMANCE_TABLE_PREFIX:-cbperf_}"

rm -rf -- "$RESULTS_DIR"
mkdir -p -- "$RESULTS_DIR"

cleanup() {
  rm -f -- "$AUTH_GUARD" 2>/dev/null || true
  php "$REQUEST" cleanup >/dev/null 2>&1 || true
}
trap cleanup EXIT

write_auth_guard() {
  mkdir -p -- "$(dirname "$AUTH_GUARD")"
  cat > "$AUTH_GUARD" <<'PHP'
<?php
if ( '1' !== getenv( 'CB_PERFORMANCE_AUTHENTICATED' ) ) {
    return;
}

add_action( 'plugins_loaded', static function (): void {
    $admin = get_user_by( 'login', 'cbadmin' );
    if ( ! $admin instanceof WP_User ) {
        throw new RuntimeException( 'F2 authenticated control could not resolve cbadmin.' );
    }
    wp_set_current_user( (int) $admin->ID );
}, PHP_INT_MAX );

add_action( 'wp_enqueue_scripts', static function (): void {
    if ( ! is_user_logged_in() ) {
        throw new RuntimeException( 'F2 authenticated frontend request lost its user session.' );
    }
    if ( '1' === getenv( 'CB_PERFORMANCE_REQUIRE_HUD_CAP' ) && ! current_user_can( 'cb_core_hud_use' ) ) {
        throw new RuntimeException( 'F2 operator frontend request is missing cb_core_hud_use.' );
    }
}, PHP_INT_MAX );
PHP
}

profile_as() {
  local source_stage="$1"
  local output_scenario="$2"
  local plugin_active="$3"
  local authenticated="$4"
  local require_hud_cap="${5:-0}"
  local output_file="$RESULTS_DIR/${output_scenario}.json"

  echo "[F2] profiling: $output_scenario"
  CB_PERFORMANCE_AUTHENTICATED="$authenticated" \
  CB_PERFORMANCE_REQUIRE_HUD_CAP="$require_hud_cap" \
    php "$REQUEST" "$source_stage" > "$output_file"

  php -r '
    $path = $argv[1];
    $scenario = $argv[2];
    $pluginActive = "1" === $argv[3];
    $authenticated = "1" === $argv[4];
    $raw = file_get_contents($path);
    $data = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data) || 1 !== ($data["schema"] ?? null) || !isset($data["metrics"])) {
        fwrite(STDERR, "Invalid F2 source performance record: {$path}\n");
        exit(1);
    }
    $data["schema"] = 2;
    $data["scenario"] = $scenario;
    $data["context"] = [
        "plugin_active" => $pluginActive,
        "authenticated" => $authenticated,
    ];
    file_put_contents(
        $path,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL
    );
  ' "$output_file" "$output_scenario" "$plugin_active" "$authenticated"
}

echo "[F2] preparing isolated performance site"
php "$REQUEST" install
write_auth_guard

# WordPress-only controls. Base is present on disk but deliberately inactive.
profile_as frontend control_frontend 0 0
profile_as frontend control_operator_frontend 0 1
profile_as admin control_admin 0 1

php "$REQUEST" activate

# Base-enabled comparison requests.
profile_as frontend frontend 1 0
profile_as frontend operator_frontend 1 1 1
profile_as admin admin 1 1
profile_as dashboard dashboard 1 1
profile_as logs logs 1 1
profile_as reports reports 1 1
profile_as safeguards safeguards 1 1

php -r '
  $dir = rtrim($argv[1], "/\\");
  $files = glob($dir . "/*.json") ?: [];
  sort($files);
  $records = [];
  $byScenario = [];
  foreach ($files as $file) {
      if (basename($file) === "baseline.json") {
          continue;
      }
      $record = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
      if (!is_array($record) || 2 !== ($record["schema"] ?? null) || !isset($record["scenario"], $record["metrics"], $record["context"])) {
          throw new RuntimeException("Invalid F2 performance record: {$file}");
      }
      $records[] = $record;
      $byScenario[(string) $record["scenario"]] = $record;
  }

  $pairs = [
      "anonymous_frontend" => [ "control" => "control_frontend", "base" => "frontend" ],
      "operator_frontend"  => [ "control" => "control_operator_frontend", "base" => "operator_frontend" ],
      "generic_admin"      => [ "control" => "control_admin", "base" => "admin" ],
  ];

  $metricPaths = [
      "memory_usage_bytes"       => [ "metrics", "memory", "usage_bytes" ],
      "memory_peak_bytes"        => [ "metrics", "memory", "peak_bytes" ],
      "memory_peak_delta_bytes"  => [ "metrics", "memory", "peak_delta_bytes" ],
      "database_queries"         => [ "metrics", "database", "queries" ],
      "style_count"              => [ "metrics", "assets", "style_count" ],
      "script_count"             => [ "metrics", "assets", "script_count" ],
      "script_module_count"      => [ "metrics", "assets", "script_module_count" ],
      "cb_local_bytes"           => [ "metrics", "assets", "cb_local_bytes" ],
      "autoload_total_count"     => [ "metrics", "autoload", "total_count" ],
      "autoload_total_bytes"     => [ "metrics", "autoload", "total_bytes" ],
      "autoload_cb_count"        => [ "metrics", "autoload", "cb_count" ],
      "autoload_cb_bytes"        => [ "metrics", "autoload", "cb_bytes" ],
      "cron_total_events"        => [ "metrics", "cron", "total_events" ],
      "cron_cb_events"           => [ "metrics", "cron", "cb_events" ],
  ];

  $value = static function (array $record, array $path): int {
      $cursor = $record;
      foreach ($path as $key) {
          if (!is_array($cursor) || !array_key_exists($key, $cursor)) {
              throw new RuntimeException("Missing metric path: " . implode(".", $path));
          }
          $cursor = $cursor[$key];
      }
      if (!is_int($cursor) && !is_float($cursor)) {
          throw new RuntimeException("Non-numeric metric path: " . implode(".", $path));
      }
      return (int) $cursor;
  };

  $comparisons = [];
  foreach ($pairs as $name => $pair) {
      $controlName = $pair["control"];
      $baseName = $pair["base"];
      if (!isset($byScenario[$controlName], $byScenario[$baseName])) {
          throw new RuntimeException("Missing F2 attribution pair: {$name}");
      }
      if (true === ($byScenario[$controlName]["context"]["plugin_active"] ?? null)) {
          throw new RuntimeException("F2 control unexpectedly marks Base active: {$controlName}");
      }
      if (true !== ($byScenario[$baseName]["context"]["plugin_active"] ?? null)) {
          throw new RuntimeException("F2 Base scenario unexpectedly marks Base inactive: {$baseName}");
      }

      $delta = [];
      foreach ($metricPaths as $metric => $path) {
          $delta[$metric] = $value($byScenario[$baseName], $path) - $value($byScenario[$controlName], $path);
      }
      $comparisons[$name] = [
          "control_scenario" => $controlName,
          "base_scenario" => $baseName,
          "delta" => $delta,
      ];
  }

  $out = [
      "schema" => 2,
      "purpose" => "BASE-V1-F2 performance attribution; observation only, no numeric gates",
      "records" => $records,
      "comparisons" => $comparisons,
  ];
  file_put_contents(
      $dir . "/baseline.json",
      json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL
  );
' "$RESULTS_DIR"

echo "[F2] performance attribution JSON"
cat "$RESULTS_DIR/baseline.json"
echo "[F2] performance attribution PASS (measurement integrity only; no numeric thresholds)"
