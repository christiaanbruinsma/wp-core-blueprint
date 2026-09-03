# Core Blueprint Base test suite

This directory contains the pre-v1 automated regression foundation for Core Blueprint Base.

## Scope

The primary suite uses the real WordPress PHPUnit integration environment. It intentionally avoids mocking the WordPress lifecycle for bootstrap, registry, schema and permission-adjacent contracts.

BASE-V1-A1 covers only foundational smoke and contract checks. Deeper activation/deactivation, schema repair, optional-module lifecycle, privileged-action and uninstall testing belong to later roadmap patches.

## Pinned matrix

CI runs:

- WordPress 7.0 + PHP 8.4
- WordPress 7.0 + PHP 8.5
- WordPress 7.1 + PHP 8.4
- WordPress 7.1 + PHP 8.5

The WordPress runtime is downloaded at the exact requested release. The matching `wp-phpunit` library is pinned to `7.0.0` or `7.1.0`.

PHPUnit and PHPUnit Polyfills are locked through `composer.lock`.

## Local setup

Create a disposable MariaDB database. The test suite drops and recreates tables, so never point it at a real site database.

Export the environment used by the installer, for example:

```bash
export WP_CORE_DIR=/tmp/core-blueprint-wp
export WP_TESTS_DIR=/tmp/core-blueprint-wp-tests
export CB_PLUGIN_FILE=/tmp/core-blueprint-wp/wp-content/plugins/core-blueprint/core-blueprint.php
export WP_DB_NAME=wordpress_test
export WP_DB_USER=root
export WP_DB_PASSWORD=root
export WP_DB_HOST=127.0.0.1:3306
```

Then run:

```bash
composer install
bash tests/bin/install-wp-tests.sh 7.1
vendor/bin/phpunit
```

Use `7.0` instead of `7.1` to exercise the minimum WordPress release.

## Boundaries

- Composer is development-only. Base does not load `vendor/` at runtime.
- No Node or browser-test stack is required.
- The plugin is copied into the canonical `core-blueprint/` WordPress plugin folder before integration tests run.
- Uninstall testing is intentionally excluded from this suite until its isolated disposable-job phase.
