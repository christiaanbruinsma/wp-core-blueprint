# Core Blueprint Base test suite

This directory contains the pre-v1 automated regression foundation for Core Blueprint Base.

## Scope

The primary suite uses the real WordPress PHPUnit integration environment. It intentionally avoids mocking the WordPress lifecycle for bootstrap, registry, schema and permission-adjacent contracts.

BASE-V1-A1 covers foundational smoke and contract checks.

BASE-V1-A2 adds request-boundary lifecycle/data scenarios. Any transition that represents a new production WordPress request runs in a separate PHP process while one isolated WordPress table prefix preserves the database state between those processes. This keeps persistent WordPress state realistic without introducing production reset APIs for request-local statics.

Deeper optional-module lifecycle, privileged-action and uninstall testing belong to later roadmap patches.

## Pinned matrix

CI runs:

- WordPress 7.0 + PHP 8.4
- WordPress 7.0 + PHP 8.5
- WordPress 7.1 + PHP 8.4
- WordPress 7.1 + PHP 8.5

The WordPress runtime is downloaded at the exact requested release. The matching `wp-phpunit` library is pinned to `7.0.0` or `7.1.0`.

PHPUnit and PHPUnit Polyfills are locked through `composer.lock`.

## Local setup

Create a disposable MariaDB database. Both the PHPUnit suite and the A2 request-boundary scenario create and remove tables, so never point either test layer at a real site database.

Export the environment used by the installer, for example:

```bash
export WP_CORE_DIR=/tmp/core-blueprint-wp
export WP_TESTS_DIR=/tmp/core-blueprint-wp-tests
export CB_PLUGIN_FILE=/tmp/core-blueprint-wp/wp-content/plugins/core-blueprint/core-blueprint.php
export WP_DB_NAME=wordpress_test
export WP_DB_USER=root
export WP_DB_PASSWORD=root
export WP_DB_HOST=127.0.0.1:3306
export CB_LIFECYCLE_TABLE_PREFIX=cblifecycle_
```

Then run:

```bash
composer install
bash tests/bin/install-wp-tests.sh 7.1
vendor/bin/phpunit
bash tests/bin/run-lifecycle-scenario.sh
```

Use `7.0` instead of `7.1` to exercise the minimum WordPress release.

## A2 request-boundary lifecycle/data scenario

`tests/bin/run-lifecycle-scenario.sh` deliberately starts a new PHP process for every request transition while keeping one isolated WordPress site/database prefix alive between stages.

It covers:

- bare WordPress install with Base still inactive;
- genuine first activation and first trust-root bootstrap;
- deactivation cleanup without deleting persistent Base state;
- reactivation without minting a new Operator or approval;
- established-site Trust Schema / Role Policy drift remaining observable rather than silently auto-repaired;
- preservation of user-selected Base settings across deactivation/reactivation;
- next-request repair of a missing current-version audit table;
- late public schema registration;
- duplicate/reserved schema ownership rejection;
- all-or-nothing schema version-marker advancement for incomplete multi-table installs.

The scenario cleans only its own `CB_LIFECYCLE_TABLE_PREFIX` tables.

## Boundaries

- Composer is development-only. Base does not load `vendor/` at runtime.
- No Node or browser-test stack is required.
- The plugin is copied into the canonical `core-blueprint/` WordPress plugin folder before integration tests run.
- A2 adds no production reset API and does not use Reflection to mutate private static state.
- Request-boundary behaviour gets a fresh PHP process; pure same-request contracts may stay in one process.
- Uninstall testing is intentionally excluded until its isolated disposable-job phase.
