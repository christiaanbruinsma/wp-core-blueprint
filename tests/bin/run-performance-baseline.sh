#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
REQUEST="$ROOT/tests/performance/request.php"
RESULTS_DIR="${CB_PERFORMANCE_RESULTS_DIR:-${TMPDIR:-/tmp}/cb-performance-baseline}"

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
  php "$REQUEST" cleanup >/dev/null 2>&1 || true
}
trap cleanup EXIT

echo "[F1] preparing isolated performance site"
php "$REQUEST" install
php "$REQUEST" activate

scenarios=(frontend admin dashboard logs reports safeguards)
for scenario in "${scenarios[@]}"; do
  echo "[F1] profiling: $scenario"
  output_file="$RESULTS_DIR/${scenario}.json"
  php "$REQUEST" "$scenario" > "$output_file"

  php -r '
    $path = $argv[1];
    $raw = file_get_contents($path);
    $data = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data) || 1 !== ($data["schema"] ?? null) || !isset($data["metrics"])) {
        fwrite(STDERR, "Invalid F1 performance record: {$path}\n");
        exit(1);
    }
  ' "$output_file"

done

php -r '
  $dir = rtrim($argv[1], "/\\");
  $files = glob($dir . "/*.json") ?: [];
  sort($files);
  $records = [];
  foreach ($files as $file) {
      if (basename($file) === "baseline.json") {
          continue;
      }
      $records[] = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
  }
  $out = [
      "schema" => 1,
      "purpose" => "BASE-V1-F1 performance baseline; observation only, no numeric gates",
      "records" => $records,
  ];
  file_put_contents(
      $dir . "/baseline.json",
      json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL
  );
' "$RESULTS_DIR"

echo "[F1] performance baseline JSON"
cat "$RESULTS_DIR/baseline.json"
echo "[F1] performance baseline PASS (measurement integrity only; no numeric thresholds)"
