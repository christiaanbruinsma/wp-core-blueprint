<?php
declare(strict_types=1);

$core_dir = rtrim( (string) getenv( 'WP_CORE_DIR' ), "/\\" );
if ( '' === $core_dir || ! is_dir( $core_dir ) ) {
    throw new RuntimeException( 'WP_CORE_DIR must point to the installed WordPress test copy.' );
}

define( 'ABSPATH', $core_dir . '/' );
define( 'DB_NAME', getenv( 'WP_DB_NAME' ) ?: 'wordpress_test' );
define( 'DB_USER', getenv( 'WP_DB_USER' ) ?: 'root' );
define( 'DB_PASSWORD', getenv( 'WP_DB_PASSWORD' ) ?: 'root' );
define( 'DB_HOST', getenv( 'WP_DB_HOST' ) ?: '127.0.0.1:3306' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'AUTH_KEY', 'core-blueprint-tests-auth-key' );
define( 'SECURE_AUTH_KEY', 'core-blueprint-tests-secure-auth-key' );
define( 'LOGGED_IN_KEY', 'core-blueprint-tests-logged-in-key' );
define( 'NONCE_KEY', 'core-blueprint-tests-nonce-key' );
define( 'AUTH_SALT', 'core-blueprint-tests-auth-salt' );
define( 'SECURE_AUTH_SALT', 'core-blueprint-tests-secure-auth-salt' );
define( 'LOGGED_IN_SALT', 'core-blueprint-tests-logged-in-salt' );
define( 'NONCE_SALT', 'core-blueprint-tests-nonce-salt' );

define( 'WP_TESTS_DOMAIN', 'core-blueprint.test' );
define( 'WP_TESTS_EMAIL', 'admin@core-blueprint.test' );
define( 'WP_TESTS_TITLE', 'Core Blueprint Tests' );
define( 'WP_PHP_BINARY', PHP_BINARY );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );
