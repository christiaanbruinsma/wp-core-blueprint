<?php
declare(strict_types=1);

namespace CB\Core\Integrity\Support;

use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Safe audit wrapper for Core Blueprint.
 *
 * Audit logging must never break a scan, REST response, cron run, or admin action.
 */
final class Audit {
	public static function log( string $event_type, string $severity = 'notice', array $context = [] ): void {
		if ( ! class_exists( '\\CB\\Core\\Log\\AuditLog' ) || ! method_exists( '\\CB\\Core\\Log\\AuditLog', 'log' ) ) {
			return;
		}

		try {
			\CB\Core\Log\AuditLog::log( $event_type, $severity, $context );
		} catch ( Throwable $throwable ) {
			// Intentionally silent.
		}
	}
}
