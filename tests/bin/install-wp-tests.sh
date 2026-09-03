#!/usr/bin/env bash
set -euo pipefail

WP_VERSION="${1:-}"
if [[ ! "$WP_VERSION" =~ ^7\.(0|1)$ ]]; then
  echo "Usage: $0 <7.0|7.1>" >&2
  exit 64
fi

: "${WP_CORE_DIR:?WP_CORE_DIR is required}"
: "${WP_TESTS_DIR:?WP_TESTS_DIR is required}"
: "${CB_PLUGIN_FILE:?CB_PLUGIN_FILE is required}"

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PLUGIN_DIR="$(dirname "$CB_PLUGIN_FILE")"
WP_PHPUNIT_VERSION="${WP_VERSION}.0"

rm -rf "$WP_CORE_DIR" "$WP_TESTS_DIR"
mkdir -p "$WP_CORE_DIR" "$WP_TESTS_DIR"

curl --fail --silent --show-error --location \
  "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" \
  | tar -xz --strip-components=1 -C "$WP_CORE_DIR"

curl --fail --silent --show-error --location \
  "https://codeload.github.com/wp-phpunit/wp-phpunit/tar.gz/refs/tags/${WP_PHPUNIT_VERSION}" \
  | tar -xz --strip-components=1 -C "$WP_TESTS_DIR"

rm -rf "$PLUGIN_DIR"
mkdir -p "$PLUGIN_DIR"

tar \
  --exclude='./.git' \
  --exclude='./vendor' \
  --exclude='./.phpunit.result.cache' \
  -C "$REPO_ROOT" -cf - . \
  | tar -C "$PLUGIN_DIR" -xf -

if [[ ! -f "$CB_PLUGIN_FILE" ]]; then
  echo "Core Blueprint test copy was not created at $CB_PLUGIN_FILE" >&2
  exit 1
fi
