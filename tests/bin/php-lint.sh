#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
status=0

while IFS= read -r -d '' file; do
  if ! php -l "$file" >/dev/null; then
    php -l "$file" || true
    status=1
  fi
done < <(
  find "$ROOT" \
    -path "$ROOT/vendor" -prune -o \
    -path "$ROOT/.git" -prune -o \
    -type f -name '*.php' -print0
)

exit "$status"
