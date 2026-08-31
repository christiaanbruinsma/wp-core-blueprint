<?php
declare(strict_types=1);

namespace CB\Core\Integrity\Scanner;

use function add_option;
use function bin2hex;
use function delete_option;
use function get_option;
use function is_array;
use function random_bytes;
use function sanitize_key;
use function time;
use function update_option;

/**
 * Cross-entrypoint lock for manual, cron, Hub, API and baseline-triggered scans.
 *
 * The lock may outlive a single PHP request. Resumable scan jobs keep ownership
 * by storing the token in their persisted job state and refreshing the lock on
 * every execution slice.
 */
final class ScannerLock {
	private const OPTION    = 'cb_core_integrity_scan_lock';
	private const STALE_TTL = 7200;

	public static function acquire( string $source, string $job_id = '' ): string {
		$token = bin2hex( random_bytes( 16 ) );
		$data  = [
			'token'       => $token,
			'source'      => sanitize_key( $source ),
			'job_id'      => sanitize_key( $job_id ),
			'acquired_at' => time(),
			'refreshed_at'=> time(),
		];

		if ( add_option( self::OPTION, $data, '', false ) ) {
			return $token;
		}

		$existing = self::current();
		$reference = (int) ( $existing['refreshed_at'] ?? $existing['acquired_at'] ?? 0 );
		$age       = time() - $reference;

		if ( $age > self::STALE_TTL ) {
			delete_option( self::OPTION );
			if ( add_option( self::OPTION, $data, '', false ) ) {
				return $token;
			}
			$existing = self::current();
		}

		throw new ScanLockedException( $existing );
	}

	/** Refresh a persisted job lock without changing its owner token. */
	public static function refresh( string $token ): bool {
		$current = self::current();
		if ( '' === $token || $token !== (string) ( $current['token'] ?? '' ) ) {
			return false;
		}

		$current['refreshed_at'] = time();
		return update_option( self::OPTION, $current, false ) || self::is_owned_by( $token );
	}

	public static function is_owned_by( string $token ): bool {
		$current = self::current();
		return '' !== $token && $token === (string) ( $current['token'] ?? '' );
	}

	public static function release( string $token ): void {
		$current = self::current();
		if ( '' !== $token && $token === (string) ( $current['token'] ?? '' ) ) {
			delete_option( self::OPTION );
		}
	}

	public static function current(): array {
		$value = get_option( self::OPTION, [] );
		return is_array( $value ) ? $value : [];
	}
}
